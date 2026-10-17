<?php

namespace App\Domains\Offer\Actions;

use App\Domains\Offer\Mail\OfferLetterMail;
use App\Domains\Offer\Models\Offer;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Kirim offer ke kandidat: draft → sent dengan token baru. Offer yang sudah
 * terkirim bisa dikirim ulang (token sama) bila kandidat tidak menerima email.
 */
class SendOfferAction
{
    public function execute(Offer $offer): Offer
    {
        $offer->loadMissing('application');

        if (! in_array($offer->status, Offer::ACTIVE_STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => 'Hanya offer draft atau terkirim yang bisa dikirim.',
            ]);
        }

        if ($offer->isPastExpiry()) {
            throw ValidationException::withMessages([
                'expires_at' => 'Masa berlaku offer sudah lewat. Perbarui tanggal berlaku sebelum mengirim.',
            ]);
        }

        $email = $offer->candidateEmail();
        if ($email === null) {
            throw ValidationException::withMessages([
                'application_id' => 'Kandidat tidak memiliki alamat email.',
            ]);
        }

        if ($offer->status === Offer::STATUS_DRAFT) {
            $offer->update([
                'status'  => Offer::STATUS_SENT,
                'token'   => Str::random(64),
                'sent_at' => now(),
            ]);
        }

        Mail::to($email)->queue((new OfferLetterMail($offer))->onQueue('emails'));

        return $offer;
    }
}
