<?php

namespace App\Domains\Preboarding\Actions;

use App\Domains\Application\Models\Application;
use App\Domains\Offer\Models\Offer;
use App\Domains\Preboarding\Mail\PreboardingInvitationMail;
use App\Domains\Preboarding\Models\Preboarding;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Buat (atau ambil yang sudah ada) pre-boarding untuk kandidat hired, lalu kirim
 * link pengisian data. Dipanggil otomatis saat offer diterima, atau manual oleh HR.
 */
class StartPreboardingAction
{
    public function execute(Application $application, ?Offer $offer = null): Preboarding
    {
        $preboarding = Preboarding::firstOrCreate(
            ['application_id' => $application->id],
            [
                'offer_id' => $offer?->id,
                'status' => Preboarding::STATUS_PENDING,
                'token' => Str::random(64),
                'token_expires_at' => now()->addDays(Preboarding::TOKEN_VALID_DAYS),
                'documents' => [],
            ]
        );

        if ($offer && $preboarding->offer_id !== $offer->id) {
            $preboarding->update(['offer_id' => $offer->id]);
        }

        return $this->send($preboarding);
    }

    /** Kirim (ulang) email undangan; masa berlaku link diperpanjang. */
    public function send(Preboarding $preboarding, bool $revision = false): Preboarding
    {
        $preboarding->loadMissing('application', 'offer');

        $preboarding->update([
            'token_expires_at' => now()->addDays(Preboarding::TOKEN_VALID_DAYS),
            'sent_at' => now(),
        ]);

        if ($email = $preboarding->candidateEmail()) {
            Mail::to($email)->queue((new PreboardingInvitationMail($preboarding, $revision))->onQueue('emails'));
        }

        return $preboarding;
    }
}
