<?php

namespace App\Domains\Preboarding\Services;

use App\Domains\Application\Models\Application;
use App\Domains\Offer\Models\Offer;
use App\Domains\Preboarding\Actions\StartPreboardingAction;
use App\Domains\Preboarding\Models\Preboarding;
use App\Enums\PipelineStage;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Sisi HR: daftar, verifikasi dokumen, minta revisi, kirim ulang link. */
class PreboardingService
{
    public function __construct(private StartPreboardingAction $starter) {}

    public function getList(array $filters = [], int $perPage = 50): LengthAwarePaginator
    {
        return Preboarding::query()
            ->with('application.vacancy', 'offer', 'employee:id,preboarding_id')
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['applicationId'] ?? null, fn ($q, $id) => $q->where('application_id', $id))
            ->when($filters['search'] ?? null, function ($q, $search) {
                $like = $q->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
                $q->whereHas('application', fn ($a) => $a->where('personal_info->fullName', $like, "%{$search}%"));
            })
            ->latest()
            ->paginate($perPage);
    }

    public function getDetail(string $id): Preboarding
    {
        return Preboarding::with('application.vacancy', 'offer', 'employee:id,preboarding_id')->findOrFail($id);
    }

    /** Mulai pre-boarding manual untuk kandidat hired yang belum punya (mis. hired tanpa offer). */
    public function startForApplication(string $applicationId): Preboarding
    {
        $application = Application::findOrFail($applicationId);

        if ($application->stage !== PipelineStage::HIRED->value) {
            throw ValidationException::withMessages([
                'application_id' => 'Pre-boarding hanya untuk kandidat berstatus hired.',
            ]);
        }

        if (Preboarding::where('application_id', $applicationId)->exists()) {
            throw ValidationException::withMessages([
                'application_id' => 'Kandidat ini sudah memiliki pre-boarding. Gunakan kirim ulang link.',
            ]);
        }

        $offer = $application->offers()->where('status', Offer::STATUS_ACCEPTED)->latest()->first();

        return $this->getDetail($this->starter->execute($application, $offer)->id);
    }

    public function resend(string $id): Preboarding
    {
        $preboarding = $this->getDetail($id);

        if ($preboarding->status === Preboarding::STATUS_VERIFIED) {
            throw ValidationException::withMessages(['status' => 'Pre-boarding ini sudah terverifikasi.']);
        }

        $this->ensureEmail($preboarding);
        $this->starter->send($preboarding);

        return $this->getDetail($id);
    }

    /**
     * Tandai satu dokumen valid / ditolak. Begitu semua dokumen wajib valid pada
     * data yang sudah dikirim kandidat, pre-boarding otomatis terverifikasi.
     */
    public function reviewDocument(string $id, string $type, string $status, ?string $note): Preboarding
    {
        $preboarding = $this->getDetail($id);
        $doc = $preboarding->document($type);

        if (empty($doc['path'])) {
            throw ValidationException::withMessages(['type' => 'Dokumen ini belum diunggah kandidat.']);
        }

        if ($preboarding->status === Preboarding::STATUS_VERIFIED) {
            throw ValidationException::withMessages(['status' => 'Pre-boarding ini sudah terverifikasi.']);
        }

        $documents = $preboarding->documents;
        $documents[$type] = array_merge($doc, [
            'status'      => $status,
            'note'        => $status === Preboarding::DOC_REJECTED ? trim((string) $note) : null,
            'reviewed_at' => now()->toISOString(),
        ]);
        $preboarding->documents = $documents;

        if ($preboarding->status === Preboarding::STATUS_SUBMITTED && $preboarding->allDocumentsValid()) {
            $preboarding->status = Preboarding::STATUS_VERIFIED;
            $preboarding->verified_at = now();
        }

        $preboarding->save();

        return $this->getDetail($id);
    }

    /** Kembalikan ke kandidat untuk mengunggah ulang dokumen yang ditolak. */
    public function requestRevision(string $id): Preboarding
    {
        $preboarding = $this->getDetail($id);

        if ($preboarding->status !== Preboarding::STATUS_SUBMITTED) {
            throw ValidationException::withMessages(['status' => 'Revisi hanya bisa diminta untuk data yang sudah dikirim kandidat.']);
        }

        if (empty($preboarding->rejectedDocuments())) {
            throw ValidationException::withMessages(['documents' => 'Tandai minimal satu dokumen sebagai ditolak beserta catatannya.']);
        }

        $this->ensureEmail($preboarding);
        $preboarding->update(['status' => Preboarding::STATUS_NEEDS_REVISION]);
        $this->starter->send($preboarding, revision: true);

        return $this->getDetail($id);
    }

    public function downloadDocument(string $id, string $type): StreamedResponse
    {
        $doc = $this->getDetail($id)->document($type);

        if (empty($doc['path'])) {
            abort(404, 'Dokumen belum diunggah.');
        }

        return Storage::disk($doc['disk'])->response($doc['path'], $doc['file_name'], [
            'Content-Type' => $doc['mime_type'] ?? 'application/octet-stream',
        ], 'inline');
    }

    private function ensureEmail(Preboarding $preboarding): void
    {
        if ($preboarding->candidateEmail() === null) {
            throw ValidationException::withMessages(['application_id' => 'Kandidat tidak memiliki alamat email.']);
        }
    }
}
