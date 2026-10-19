<?php

namespace App\Domains\Application\Actions;

use App\Domains\Application\Mail\ApplicationRejectedMail;
use App\Domains\Application\Models\Application;
use App\Enums\PipelineStage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Keputusan "Tolak" dari HR: pindah ke stage rejected, catat alasan internal, dan
 * (opsional, pilihan HR) antrekan email penolakan ke kandidat.
 */
class RejectApplicationAction
{
    /**
     * @return array{application: Application, emailQueued: bool}
     */
    public function execute(Application $application, ?string $reason, bool $sendEmail): array
    {
        if ($application->stage === PipelineStage::REJECTED->value) {
            throw ValidationException::withMessages([
                'stage' => 'Kandidat ini sudah berstatus ditolak.',
            ]);
        }

        if ($application->stage === PipelineStage::HIRED->value) {
            throw ValidationException::withMessages([
                'stage' => 'Kandidat yang sudah hired tidak bisa ditolak.',
            ]);
        }

        $email = trim((string) ($application->contact_info['email'] ?? ''));
        $emailQueued = false;

        $application->update([
            'stage' => PipelineStage::REJECTED->value,
            'rejection_reason' => $reason !== null && trim($reason) !== '' ? trim($reason) : null,
            'rejected_at' => now(),
            'rejection_notified_at' => null,
        ]);

        // Disimpan dulu baru diantrekan: job email memuat ulang model dari DB.
        if ($sendEmail && $email !== '') {
            try {
                Mail::to($email)->queue(
                    (new ApplicationRejectedMail($application->loadMissing('vacancy')))->onQueue('emails')
                );
                $application->update(['rejection_notified_at' => now()]);
                $emailQueued = true;
            } catch (\Throwable $e) {
                // Penolakan tetap tersimpan; HR diberi tahu email tidak terkirim.
                Log::error('Gagal mengantrekan email penolakan: '.$e->getMessage(), [
                    'application_id' => $application->id,
                ]);
            }
        }

        return ['application' => $application, 'emailQueued' => $emailQueued];
    }
}
