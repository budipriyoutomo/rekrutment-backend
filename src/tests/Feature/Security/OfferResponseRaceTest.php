<?php

namespace Tests\Feature\Security;

use App\Domains\Application\Models\Application;
use App\Domains\Offer\Actions\RespondToOfferAction;
use App\Domains\Offer\Models\Offer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class OfferResponseRaceTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'race-token-0000000000000000000000000000000000000000000000000000';

    private function sentOffer(): Offer
    {
        $application = Application::create([
            'personal_info' => ['fullName' => 'Siti Kandidat'],
            'contact_info' => ['email' => 'siti@example.com'],
            'stage' => 'offer',
        ]);

        return Offer::create([
            'number' => 'OFR/2026/11/0001',
            'application_id' => $application->id,
            'position' => 'Head Chef',
            'employment_type' => 'full-time',
            'base_salary' => 8500000,
            'start_date' => now()->addMonth()->toDateString(),
            'expires_at' => now()->addWeek()->toDateString(),
            'status' => Offer::STATUS_SENT,
            'token' => self::TOKEN,
            'sent_at' => now(),
        ]);
    }

    /**
     * Respons lain (tab/perangkat kedua) mengubah status tepat setelah offer
     * pertama kali dibaca, sebelum respons ini sempat menyimpan.
     */
    private function concurrentResponseAfterFirstRead(string $statusFromOtherRequest): void
    {
        $fired = false;
        Offer::retrieved(function (Offer $offer) use (&$fired, $statusFromOtherRequest) {
            if ($fired) {
                return;
            }
            $fired = true;
            DB::table('offers')->where('id', $offer->id)->update([
                'status' => $statusFromOtherRequest,
                'responded_at' => now(),
            ]);
        });
    }

    public function test_offer_cannot_be_accepted_after_declined(): void
    {
        $offer = $this->sentOffer();
        $this->concurrentResponseAfterFirstRead(Offer::STATUS_DECLINED);

        try {
            app(RespondToOfferAction::class)->accept(self::TOKEN);
            $this->fail('Accept seharusnya ditolak karena offer sudah ditolak.');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }

        $this->assertSame(Offer::STATUS_DECLINED, $offer->fresh()->status);
        $this->assertSame('offer', $offer->application->fresh()->stage);
    }

    public function test_offer_cannot_be_declined_after_accepted(): void
    {
        $offer = $this->sentOffer();
        $this->concurrentResponseAfterFirstRead(Offer::STATUS_ACCEPTED);

        try {
            app(RespondToOfferAction::class)->decline(self::TOKEN, 'Berubah pikiran');
            $this->fail('Decline seharusnya ditolak karena offer sudah diterima.');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }

        $this->assertSame(Offer::STATUS_ACCEPTED, $offer->fresh()->status);
        $this->assertNull($offer->fresh()->decline_reason);
    }

    public function test_normal_accept_still_works(): void
    {
        $offer = $this->sentOffer();

        app(RespondToOfferAction::class)->accept(self::TOKEN);

        $this->assertSame(Offer::STATUS_ACCEPTED, $offer->fresh()->status);
        $this->assertSame('hired', $offer->application->fresh()->stage);
    }
}
