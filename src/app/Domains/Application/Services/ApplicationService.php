<?php

namespace App\Domains\Application\Services;

use App\Core\Services\BaseService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

use App\Domains\Application\Models\Application;
use App\Domains\Vacancy\Services\VacancyService;
use App\Enums\PipelineStage;

class ApplicationService extends BaseService
{
    public function __construct(
        Application $model,
        private ApplicationExperienceService $experienceService,
        private ApplicationEducationService $educationService,
        private ApplicationCertificationService $certificationService,
        private VacancyService $vacancyService,
    ) {
        parent::__construct($model);
    }

    /**
     * ============================================
     * CREATE FULL APPLICATION
     * ============================================
     */
    public function createFull(array $data, array $relations): Application
    {
        $relations = array_merge([
            'education' => [],
            'workExperience' => [],
            'certifications' => [],
        ], $relations);

        // 🔥 Cegah lamaran ganda dari orang yang sama. Email pelamar berada di
        // contact_info (form lengkap maupun Quick Apply); personal_info dicek
        // sebagai cadangan untuk data lama yang formatnya berbeda.
        $email = $this->normalizeApplicantEmail(
            $data['contact_info']['email'] ?? $data['personal_info']['email'] ?? null
        );

        if ($email !== null && $this->emailAlreadyApplied($email)) {
            throw $this->duplicateApplicationException();
        }

        try {
            return DB::transaction(function () use ($data, $relations) {

                $app = $this->create($data);

                $this->educationService->createMany($app->id, $relations['education']);
                $this->experienceService->createMany($app->id, $relations['workExperience']);
                $this->certificationService->createMany($app->id, $relations['certifications']);

                return $app;
            });

        } catch (UniqueConstraintViolationException $e) {
            // Race: dua submit paralel lolos pengecekan di atas, unique index DB
            // menolak yang kedua. Perlakukan sama seperti duplikat biasa.
            throw $this->duplicateApplicationException();
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);
            throw new \Exception($e->getMessage());
        }
    }

    /** Normalisasi email pelamar: lowercase + trim, null bila kosong. */
    private function normalizeApplicantEmail(?string $email): ?string
    {
        $email = strtolower(trim((string) $email));

        return $email === '' ? null : $email;
    }

    /** Apakah email (sudah dinormalisasi) pernah dipakai melamar. */
    private function emailAlreadyApplied(string $email): bool
    {
        $contact = $this->jsonEmailExpression('contact_info');
        $personal = $this->jsonEmailExpression('personal_info');

        return Application::query()
            ->where(function ($q) use ($contact, $personal, $email) {
                $q->whereRaw("lower(trim($contact)) = ?", [$email])
                  ->orWhereRaw("lower(trim($personal)) = ?", [$email]);
            })
            ->exists();
    }

    /** Ekspresi SQL untuk mengambil `email` dari kolom JSON, per driver DB. */
    private function jsonEmailExpression(string $column): string
    {
        return $this->model->getConnection()->getDriverName() === 'pgsql'
            ? "$column->>'email'"
            : "json_extract($column, '$.email')";
    }

    private function duplicateApplicationException(): ValidationException
    {
        return ValidationException::withMessages([
            'contactInfo.email' => 'Email ini sudah pernah mengirim lamaran. Setiap pelamar hanya boleh mengirim satu lamaran.',
        ]);
    }

    /**
     * ============================================
     * GET LIST (WITH FILTER + PAGINATION)
     * ============================================
     */
    public function getList(array $filters = [], int $perPage = 10)
    {
        $query = Application::query()->with('vacancy:id,title,location,status');

        // 🔍 filter lowongan; 'none' = lamaran yang belum terhubung ke lowongan
        if (!empty($filters['vacancyId'])) {
            $filters['vacancyId'] === 'none'
                ? $query->whereNull('vacancy_id')
                : $query->where('vacancy_id', $filters['vacancyId']);
        }

        // 🔍 filter stage/status
        $stage = $filters['stage'] ?? $filters['status'] ?? null;
        if (!empty($stage)) {
            $query->where('stage', $stage);
        }

        // 🔍 kebalikan filter stage — dipakai halaman Candidates yang menyembunyikan pelamar baru
        if (!empty($filters['excludeStage'])) {
            $query->where('stage', '!=', $filters['excludeStage']);
        }

        // 🔍 search name / email
        if (!empty($filters['search'])) {
            $search = $filters['search'];

            $query->where(function ($q) use ($search) {
                $q->where('personal_info->fullName', 'ilike', "%$search%")
                  ->orWhere('contact_info->email', 'ilike', "%$search%");
            });
        }

        // 🔍 filter date range
        if (!empty($filters['startDate']) && !empty($filters['endDate'])) {
            $query->whereBetween('created_at', [
                $filters['startDate'],
                $filters['endDate']
            ]);
        }

        // 🔍 filter berdasarkan inputan quick apply (/apply)
        if (!empty($filters['gender'])) {
            $query->where('personal_info->gender', $filters['gender']);
        }

        if (!empty($filters['position'])) {
            $query->where('additional_info->positionApplied', $filters['position']);
        }

        if (!empty($filters['workLocation'])) {
            $query->where('additional_info->workLocation', $filters['workLocation']);
        }

        if (!empty($filters['jobSource'])) {
            $query->where('additional_info->jobSource', $filters['jobSource']);
        }

        if (!empty($filters['hasVehicle'])) {
            $query->where('additional_info->hasVehicle', $filters['hasVehicle']);
        }

        if (!empty($filters['workedBefore'])) {
            $query->where('additional_info->workedAtCompany', $filters['workedBefore']);
        }

        if (!empty($filters['domicile'])) {
            $domicile = $filters['domicile'];

            $query->where(function ($q) use ($domicile) {
                $q->where('contact_info->currentAddress', 'ilike', "%$domicile%")
                  ->orWhere('contact_info->homeAddress', 'ilike', "%$domicile%");
            });
        }

        // usia disimpan sebagai string di JSON, ambil digitnya saja sebelum cast
        if (!empty($filters['ageMin'])) {
            $query->whereRaw(
                "NULLIF(regexp_replace(additional_info->>'age', '\D', '', 'g'), '')::int >= ?",
                [(int) $filters['ageMin']]
            );
        }

        if (!empty($filters['ageMax'])) {
            $query->whereRaw(
                "NULLIF(regexp_replace(additional_info->>'age', '\D', '', 'g'), '')::int <= ?",
                [(int) $filters['ageMax']]
            );
        }

        return $query
            ->latest()
            ->paginate($perPage);
    }

    /**
     * ============================================
     * GET DETAIL
     * ============================================
     */
    public function getDetail(string $id): Application
    {
        return Application::with([
            'vacancy:id,title,location,status',
            'educations',
            'experiences',
            'certifications'
        ])->findOrFail($id);
    }

    /**
     * ============================================
     * UPDATE STAGE
     * ============================================
     */
    public function updateStatus(string $id, string $stage): Application
    {
        $app = Application::findOrFail($id);

        $app->update([
            'stage' => $stage
        ]);

        $this->closeFilledVacanciesIfHired($stage, [$app->vacancy_id]);

        return $app;
    }

    /**
     * ============================================
     * BULK UPDATE STAGE
     * ============================================
     * Dipakai bulk action di halaman Applicants. Mengembalikan jumlah baris
     * yang benar-benar berubah (id yang tidak ada diabaikan diam-diam).
     */
    public function bulkUpdateStatus(array $ids, string $stage): int
    {
        $ids = array_values(array_unique(array_filter($ids)));

        if (empty($ids)) {
            return 0;
        }

        $updated = Application::whereIn('id', $ids)->update(['stage' => $stage]);

        $this->closeFilledVacanciesIfHired(
            $stage,
            Application::whereIn('id', $ids)->pluck('vacancy_id')->all()
        );

        return $updated;
    }

    /** Lowongan otomatis ditutup saat kuota headcount-nya terisi oleh pelamar hired. */
    private function closeFilledVacanciesIfHired(string $stage, array $vacancyIds): void
    {
        if ($stage === PipelineStage::HIRED->value) {
            $this->vacancyService->closeFilledVacancies($vacancyIds);
        }
    }

    /**
     * ============================================
     * ASSIGN VACANCY
     * ============================================
     * HR menautkan/memindahkan lamaran ke lowongan (mis. lamaran lama yang tak
     * tercocokkan otomatis). null = lepas dari lowongan.
     */
    public function assignVacancy(string $id, ?string $vacancyId): Application
    {
        $app = Application::findOrFail($id);
        $app->update(['vacancy_id' => $vacancyId]);

        $this->closeFilledVacanciesIfHired($app->stage, [$vacancyId]);

        return $app->load('vacancy:id,title,location,status');
    }

    /**
     * ============================================
     * ADD HR NOTE
     * ============================================
     */
    public function addNote(string $id, string $text): Application
    {
        $app = Application::findOrFail($id);
        $notes = $app->notes ?? [];

        array_unshift($notes, [
            'id' => Str::uuid()->toString(),
            'text' => $text,
            'date' => now()->toDateString(),
            'createdAt' => now()->toISOString(),
        ]);

        $app->update([
            'notes' => $notes,
        ]);

        return $app->refresh();
    }
}
