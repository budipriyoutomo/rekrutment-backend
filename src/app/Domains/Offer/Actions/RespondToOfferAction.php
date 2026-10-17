<?php

namespace App\Domains\Offer\Actions;

use App\Domains\Offer\Models\Offer;
use App\Domains\Preboarding\Actions\StartPreboardingAction;
use App\Domains\Vacancy\Services\VacancyService;
use App\Enums\PipelineStage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Respon kandidat lewat link bertoken. Menerima → kandidat otomatis hired (dan
 * lowongan ditutup bila kuota terpenuhi). Menolak → stage tetap, HR yang memutuskan
 * langkah berikutnya.
 */
class RespondToOfferAction
{
    public function __construct(
        private VacancyService $vacancyService,
        private StartPreboardingAction $startPreboarding,
    ) {}

    /**
     * Cari offer yang masih bisa direspon. 404 token tak dikenal, 409 sudah
     * direspon, 410 ditarik/kedaluwarsa (offer lewat masa berlaku ditandai expired).
     */
    public function findRespondable(string $token): Offer
    {
        $offer = $this->findByToken($token);
        $this->assertRespondable($offer);

        return $offer;
    }

    public function findByToken(string $token): Offer
    {
        $offer = Offer::with('application')->where('token', $token)->first();

        if (! $offer) {
            throw new HttpException(404, 'Link offer tidak valid.');
        }

        if ($offer->status === Offer::STATUS_SENT && $offer->isPastExpiry()) {
            $offer->update(['status' => Offer::STATUS_EXPIRED]);
        }

        return $offer;
    }

    public function accept(string $token): Offer
    {
        // Dicari di luar transaksi: penandaan expired tidak boleh ikut ter-rollback
        $offer = $this->findRespondable($token);

        DB::transaction(function () use ($offer) {
            $offer->update([
                'status'       => Offer::STATUS_ACCEPTED,
                'responded_at' => now(),
            ]);

            $offer->application->update(['stage' => PipelineStage::HIRED->value]);
            $this->vacancyService->closeFilledVacancies([$offer->application->vacancy_id]);
        });

        // Pre-boarding dimulai otomatis; gagal di sini tidak membatalkan penerimaan
        // offer — HR masih bisa memulainya manual dari menu Onboarding.
        try {
            $this->startPreboarding->execute($offer->application, $offer);
        } catch (\Throwable $e) {
            Log::error('Gagal memulai pre-boarding setelah offer diterima: ' . $e->getMessage(), [
                'offer_id' => $offer->id,
            ]);
        }

        return $offer;
    }

    public function decline(string $token, ?string $reason): Offer
    {
        $offer = $this->findRespondable($token);

        $offer->update([
            'status'         => Offer::STATUS_DECLINED,
            'responded_at'   => now(),
            'decline_reason' => $reason !== null && trim($reason) !== '' ? trim($reason) : null,
        ]);

        return $offer;
    }

    private function assertRespondable(Offer $offer): void
    {
        match ($offer->status) {
            Offer::STATUS_SENT => null,
            Offer::STATUS_ACCEPTED, Offer::STATUS_DECLINED => throw new HttpException(409, 'Offer ini sudah Anda respon sebelumnya.'),
            Offer::STATUS_EXPIRED => throw new HttpException(410, 'Masa berlaku offer ini sudah habis.'),
            default => throw new HttpException(410, 'Offer ini sudah tidak berlaku.'),
        };
    }
}
