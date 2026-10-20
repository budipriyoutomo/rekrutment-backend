<?php

namespace App\Domains\ProfileCompletion\Actions;

use App\Domains\Application\DTO\ApplicationDTO;
use App\Domains\Application\Models\Application;
use App\Domains\Application\Models\ApplicationCertification;
use App\Domains\Application\Models\ApplicationEducation;
use App\Domains\Application\Models\ApplicationExperience;
use App\Domains\Application\Services\ApplicantFileUploader;
use App\Domains\Application\Services\ApplicationCertificationService;
use App\Domains\Application\Services\ApplicationEducationService;
use App\Domains\Application\Services\ApplicationExperienceService;
use App\Domains\Application\Services\ApplicationService;
use App\Domains\ProfileCompletion\Models\ProfileCompletionToken;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Simpan form lengkap dari link profile completion KE LAMARAN YANG SUDAH ADA.
 *
 * Dulu form ini membuat lamaran baru lewat POST /applications: dengan email
 * yang sama ditolak sebagai duplikat, dengan email lain muncul lamaran kedua
 * sementara lamaran asli ditandai "done" tanpa data. Sekarang data, dokumen,
 * status `done`, dan pemakaian token disimpan bersama dalam satu transaksi.
 */
class CompleteProfileAction
{
    /** Dokumen yang wajib ada setelah profil dilengkapi. */
    public const REQUIRED_DOCUMENTS = ['cv' => 'CV', 'foto' => 'Foto', 'ijazah' => 'Ijazah'];

    /**
     * Isian additional_info yang ditentukan saat melamar (posisi, lokasi,
     * sumber) — dipertahankan dari lamaran asli bila sudah terisi.
     */
    private const LOCKED_ADDITIONAL = ['positionApplied', 'workLocation', 'quickApply', 'jobSource'];

    public function __construct(
        private ApplicationService $applications,
        private ApplicantFileUploader $uploader,
        private ApplicationEducationService $educations,
        private ApplicationExperienceService $experiences,
        private ApplicationCertificationService $certifications,
    ) {}

    /** @param  array<string, UploadedFile|null>  $files */
    public function execute(ProfileCompletionToken $token, ApplicationDTO $dto, array $files): Application
    {
        $app = $token->application;
        $data = $dto->toArray();

        $this->applications->ensureCanApply($data, $app->id);
        $this->ensureRequiredDocuments($app, $files);

        $uploaded = $this->uploader->uploadAll($files);

        try {
            $replaced = DB::transaction(function () use ($token, $app, $dto, $data, $uploaded) {
                // Kunci token: dua kiriman paralel tidak boleh sama-sama tersimpan.
                $locked = ProfileCompletionToken::query()->lockForUpdate()->findOrFail($token->id);
                if ($locked->isUsed()) {
                    throw ValidationException::withMessages(['token' => 'Link ini sudah pernah digunakan.']);
                }

                $app->refresh();
                $documents = $app->documents ?? [];
                $replaced = array_intersect_key($documents, $uploaded);

                $app->update([
                    'personal_info' => array_merge($app->personal_info ?? [], $data['personal_info'] ?? []),
                    'contact_info' => array_merge($app->contact_info ?? [], $data['contact_info'] ?? []),
                    'parent_info' => array_merge($app->parent_info ?? [], $data['parent_info'] ?? []),
                    'spouse_info' => array_merge($app->spouse_info ?? [], $data['spouse_info'] ?? []),
                    'additional_info' => $this->mergeAdditional($app->additional_info ?? [], $data['additional_info'] ?? []),
                    'documents' => array_merge($documents, $uploaded),
                    'profile_completion_status' => 'done',
                ]);

                $this->replaceRelations($app->id, $dto);
                $locked->markAsUsed();

                return $replaced;
            });
        } catch (\Throwable $e) {
            $this->uploader->delete($uploaded);
            throw $e;
        }

        // File lama yang diganti baru dihapus setelah data aman tersimpan.
        $this->uploader->delete($replaced);

        return $app->fresh();
    }

    /** @param  array<string, UploadedFile|null>  $files */
    private function ensureRequiredDocuments(Application $app, array $files): void
    {
        $missing = [];
        foreach (self::REQUIRED_DOCUMENTS as $type => $label) {
            if (empty($files[$type]) && ! $app->hasDocument($type)) {
                $missing[$type] = "{$label} wajib diunggah.";
            }
        }

        if ($missing !== []) {
            throw ValidationException::withMessages($missing);
        }
    }

    private function mergeAdditional(array $existing, array $incoming): array
    {
        $locked = array_filter(
            array_intersect_key($existing, array_flip(self::LOCKED_ADDITIONAL)),
            fn ($v) => $v !== null && $v !== ''
        );

        return array_merge($existing, $incoming, $locked);
    }

    /** Pendidikan/kursus/pengalaman diganti total dengan isian terbaru (baris kosong dibuang). */
    private function replaceRelations(string $applicationId, ApplicationDTO $dto): void
    {
        ApplicationEducation::where('application_id', $applicationId)->delete();
        ApplicationExperience::where('application_id', $applicationId)->delete();
        ApplicationCertification::where('application_id', $applicationId)->delete();

        $freshGraduate = ($dto->additionalInfo['freshGraduate'] ?? null) === 'ya';

        $this->educations->createMany($applicationId, $this->filled($dto->education, 'schoolName'));
        $this->certifications->createMany($applicationId, $this->filled($dto->certifications, 'courseName'));
        $this->experiences->createMany($applicationId, $freshGraduate ? [] : $this->filled($dto->workExperience, 'companyName'));
    }

    private function filled(array $rows, string $key): array
    {
        return array_values(array_filter($rows, fn ($row) => is_array($row) && trim((string) ($row[$key] ?? '')) !== ''));
    }
}
