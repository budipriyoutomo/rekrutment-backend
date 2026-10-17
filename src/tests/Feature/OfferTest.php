<?php

namespace Tests\Feature;

use App\Domains\Application\Models\Application;
use App\Domains\Offer\Mail\OfferLetterMail;
use App\Domains\Offer\Models\Offer;
use App\Domains\Offer\Services\OfferLetterPdf;
use App\Domains\SalarySlip\Services\PayslipPdfConverter;
use App\Domains\Setting\Models\CompanySetting;
use App\Domains\Vacancy\Models\Vacancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Fase 3 Offer→Onboarding: offer letter (HR) & respon kandidat (publik, bertoken).
 */
class OfferTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
        Mail::fake();
    }

    private function makeApplication(array $override = []): Application
    {
        return Application::create(array_merge([
            'personal_info'   => ['fullName' => 'Siti Kandidat'],
            'contact_info'    => ['email' => 'siti@example.com', 'phone' => '081'],
            'additional_info' => ['positionApplied' => 'Bandung - Head Chef'],
            'stage'           => 'offer',
        ], $override));
    }

    private function offerPayload(Application $app, array $override = []): array
    {
        return array_merge([
            'application_id'  => $app->id,
            'position'        => 'Bandung - Head Chef',
            'department'      => 'Kitchen',
            'location'        => 'Bandung - RIAU',
            'employment_type' => 'contract',
            'base_salary'     => 8500000,
            'allowances'      => [['name' => 'Tunjangan Makan', 'amount' => 500000]],
            'start_date'      => now()->addMonth()->toDateString(),
            'expires_at'      => now()->addWeek()->toDateString(),
            'terms'           => 'Masa percobaan 3 bulan.',
        ], $override);
    }

    private function makeOffer(Application $app, array $override = []): Offer
    {
        return Offer::create(array_merge([
            'number'          => 'OFR/2026/10/' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'application_id'  => $app->id,
            'position'        => 'Bandung - Head Chef',
            'employment_type' => 'full-time',
            'base_salary'     => 8500000,
            'allowances'      => [['name' => 'Tunjangan Makan', 'amount' => 500000]],
            'start_date'      => now()->addMonth()->toDateString(),
            'expires_at'      => now()->addWeek()->toDateString(),
            'status'          => Offer::STATUS_DRAFT,
        ], $override));
    }

    private function sentOffer(Application $app, array $override = []): Offer
    {
        return $this->makeOffer($app, array_merge([
            'status'  => Offer::STATUS_SENT,
            'token'   => str_repeat('a', 64),
            'sent_at' => now(),
        ], $override));
    }

    // ---------------------------------------------------------------- HR: CRUD

    public function test_create_draft_offer_copies_vacancy_and_numbers_it(): void
    {
        $vacancy = Vacancy::create([
            'title' => 'Bandung - Head Chef', 'department' => 'Kitchen', 'location' => 'Bandung - RIAU',
            'type' => 'full-time', 'status' => 'open', 'description' => 'x',
        ]);
        $app = $this->makeApplication(['vacancy_id' => $vacancy->id]);

        $this->postJson('/api/offers', $this->offerPayload($app))
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.number', 'OFR/' . now()->format('Y/m') . '/0001')
            ->assertJsonPath('data.vacancyId', $vacancy->id)
            ->assertJsonPath('data.candidateName', 'Siti Kandidat')
            ->assertJsonPath('data.totalCompensation', 9000000)
            ->assertJsonPath('data.publicUrl', null);

        $second = $this->makeApplication(['contact_info' => ['email' => 'b@example.com']]);
        $this->postJson('/api/offers', $this->offerPayload($second))
            ->assertJsonPath('data.number', 'OFR/' . now()->format('Y/m') . '/0002');
    }

    public function test_create_requires_offer_stage(): void
    {
        $app = $this->makeApplication(['stage' => 'interview']);

        $this->postJson('/api/offers', $this->offerPayload($app))
            ->assertStatus(422)
            ->assertJsonValidationErrors('application_id');
    }

    public function test_only_one_active_offer_per_candidate(): void
    {
        $app = $this->makeApplication();
        $this->makeOffer($app, ['status' => Offer::STATUS_SENT]);

        $this->postJson('/api/offers', $this->offerPayload($app))->assertStatus(422);

        Offer::query()->update(['status' => Offer::STATUS_WITHDRAWN]);
        $this->postJson('/api/offers', $this->offerPayload($app))->assertStatus(200);
    }

    public function test_create_validates_terms(): void
    {
        $app = $this->makeApplication();

        $this->postJson('/api/offers', $this->offerPayload($app, [
            'base_salary' => -1,
            'expires_at'  => now()->subDay()->toDateString(),
            'allowances'  => [['name' => '', 'amount' => 10]],
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['base_salary', 'expires_at', 'allowances.0.name']);
    }

    public function test_only_draft_can_be_updated_or_deleted(): void
    {
        $app = $this->makeApplication();
        $draft = $this->makeOffer($app);

        $this->patchJson("/api/offers/{$draft->id}", ['base_salary' => 9000000])
            ->assertStatus(200)
            ->assertJsonPath('data.baseSalary', 9000000);

        $draft->update(['status' => Offer::STATUS_SENT]);
        $this->patchJson("/api/offers/{$draft->id}", ['base_salary' => 1])->assertStatus(422);
        $this->deleteJson("/api/offers/{$draft->id}")->assertStatus(422);

        $other = $this->makeOffer($this->makeApplication(['contact_info' => ['email' => 'c@example.com']]));
        $this->deleteJson("/api/offers/{$other->id}")->assertStatus(200);
        $this->assertModelMissing($other);
    }

    public function test_list_filters_by_application_and_status(): void
    {
        $app = $this->makeApplication();
        $this->makeOffer($app, ['status' => Offer::STATUS_WITHDRAWN]);
        $this->makeOffer($app);
        $this->makeOffer($this->makeApplication([
            'personal_info' => ['fullName' => 'Budi Lain'],
            'contact_info'  => ['email' => 'd@example.com'],
        ]));

        $this->getJson("/api/offers?applicationId={$app->id}")->assertJsonCount(2, 'data');
        $this->getJson("/api/offers?applicationId={$app->id}&status=draft")->assertJsonCount(1, 'data');
        $this->getJson('/api/offers?search=siti')->assertJsonCount(2, 'data');
    }

    public function test_offer_writes_require_offers_permission(): void
    {
        $app = $this->makeApplication();

        $this->actingAsUserWithPermissions(['candidates']);
        $this->getJson("/api/offers?applicationId={$app->id}")->assertStatus(200);
        $this->postJson('/api/offers', $this->offerPayload($app))->assertStatus(403);

        $this->actingAsUserWithPermissions(['offers']);
        $this->postJson('/api/offers', $this->offerPayload($app))->assertStatus(200);
    }

    // ---------------------------------------------------------------- HR: send

    public function test_send_draft_generates_token_and_queues_mail(): void
    {
        $offer = $this->makeOffer($this->makeApplication());

        $this->postJson("/api/offers/{$offer->id}/send")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'sent');

        $offer->refresh();
        $this->assertSame(64, strlen($offer->token));
        $this->assertNotNull($offer->sent_at);
        $this->assertStringEndsWith('/offer/' . $offer->token, $offer->publicUrl());

        Mail::assertQueued(OfferLetterMail::class, fn ($mail) => $mail->hasTo('siti@example.com'));
    }

    public function test_resend_keeps_same_token(): void
    {
        $offer = $this->sentOffer($this->makeApplication());

        $this->postJson("/api/offers/{$offer->id}/send")->assertStatus(200);

        $this->assertSame(str_repeat('a', 64), $offer->fresh()->token);
        Mail::assertQueued(OfferLetterMail::class);
    }

    public function test_send_rejects_past_expiry_missing_email_and_closed_offers(): void
    {
        $expired = $this->makeOffer($this->makeApplication(), ['expires_at' => now()->subDay()->toDateString()]);
        $noEmail = $this->makeOffer($this->makeApplication(['contact_info' => ['phone' => '081']]));
        $accepted = $this->makeOffer($this->makeApplication(['contact_info' => ['email' => 'e@example.com']]), ['status' => Offer::STATUS_ACCEPTED]);

        $this->postJson("/api/offers/{$expired->id}/send")->assertStatus(422)->assertJsonValidationErrors('expires_at');
        $this->postJson("/api/offers/{$noEmail->id}/send")->assertStatus(422)->assertJsonValidationErrors('application_id');
        $this->postJson("/api/offers/{$accepted->id}/send")->assertStatus(422);
        Mail::assertNothingQueued();
    }

    public function test_withdrawn_offer_link_no_longer_works(): void
    {
        $offer = $this->sentOffer($this->makeApplication());

        $this->postJson("/api/offers/{$offer->id}/withdraw")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'withdrawn');

        $this->postJson('/api/offer/' . $offer->token . '/accept')->assertStatus(410);
        $this->postJson("/api/offers/{$offer->id}/withdraw")->assertStatus(422);
    }

    // ------------------------------------------------------- candidate (public)

    public function test_public_show_exposes_candidate_view_only(): void
    {
        CompanySetting::set('company_name', 'Altima Group');
        $offer = $this->sentOffer($this->makeApplication());
        auth()->logout();

        $this->getJson('/api/offer/' . $offer->token)
            ->assertStatus(200)
            ->assertJsonPath('data.candidateName', 'Siti Kandidat')
            ->assertJsonPath('data.companyName', 'Altima Group')
            ->assertJsonPath('data.baseSalary', 8500000)
            ->assertJsonPath('data.totalCompensation', 9000000)
            ->assertJsonPath('data.status', 'sent')
            ->assertJsonMissingPath('data.token')
            ->assertJsonMissingPath('data.applicationId')
            ->assertJsonMissingPath('data.declineReason');

        $this->getJson('/api/offer/' . str_repeat('z', 64))->assertStatus(404);
    }

    public function test_accept_hires_candidate_and_closes_filled_vacancy(): void
    {
        $vacancy = Vacancy::create([
            'title' => 'Bandung - Head Chef', 'department' => 'Kitchen', 'location' => 'Bandung - RIAU',
            'type' => 'full-time', 'status' => 'open', 'description' => 'x', 'headcount' => 1,
        ]);
        $app = $this->makeApplication(['vacancy_id' => $vacancy->id]);
        $offer = $this->sentOffer($app);
        auth()->logout();

        $this->postJson('/api/offer/' . $offer->token . '/accept')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'accepted');

        $this->assertSame('hired', $app->fresh()->stage);
        $this->assertNotNull($offer->fresh()->responded_at);
        $this->assertSame('closed', $vacancy->fresh()->status);

        $this->postJson('/api/offer/' . $offer->token . '/accept')->assertStatus(409);
        $this->postJson('/api/offer/' . $offer->token . '/decline')->assertStatus(409);
    }

    public function test_decline_records_reason_and_keeps_stage(): void
    {
        $app = $this->makeApplication();
        $offer = $this->sentOffer($app);
        auth()->logout();

        $this->postJson('/api/offer/' . $offer->token . '/decline', ['reason' => 'Gaji belum sesuai'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'declined');

        $this->assertSame('Gaji belum sesuai', $offer->fresh()->decline_reason);
        $this->assertSame('offer', $app->fresh()->stage);

        $this->actingAsAdmin();
        $this->getJson("/api/offers/{$offer->id}")->assertJsonPath('data.declineReason', 'Gaji belum sesuai');
    }

    public function test_responding_after_expiry_marks_offer_expired(): void
    {
        $app = $this->makeApplication();
        $offer = $this->sentOffer($app, ['expires_at' => now()->subDay()->toDateString()]);

        $this->postJson('/api/offer/' . $offer->token . '/accept')->assertStatus(410);

        $this->assertSame('expired', $offer->fresh()->status);
        $this->assertSame('offer', $app->fresh()->stage);
    }

    public function test_offer_is_still_valid_on_its_expiry_date(): void
    {
        $offer = $this->sentOffer($this->makeApplication(), ['expires_at' => now()->toDateString()]);

        $this->postJson('/api/offer/' . $offer->token . '/accept')->assertStatus(200);
    }

    public function test_expire_command_marks_only_overdue_sent_offers(): void
    {
        $overdue = $this->sentOffer($this->makeApplication(), ['expires_at' => now()->subDay()->toDateString()]);
        $today = $this->sentOffer($this->makeApplication(['contact_info' => ['email' => 'f@example.com']]), [
            'token' => str_repeat('b', 64), 'expires_at' => now()->toDateString(),
        ]);
        $draft = $this->makeOffer($this->makeApplication(['contact_info' => ['email' => 'g@example.com']]), [
            'expires_at' => now()->subDay()->toDateString(),
        ]);

        $this->artisan('offers:expire')->expectsOutputToContain('1 offer')->assertSuccessful();

        $this->assertSame('expired', $overdue->fresh()->status);
        $this->assertSame('sent', $today->fresh()->status);
        $this->assertSame('draft', $draft->fresh()->status);
    }

    // -------------------------------------------------------------- documents

    public function test_offer_letter_html_contains_terms(): void
    {
        CompanySetting::set('company_name', 'Altima Group');
        $offer = $this->makeOffer($this->makeApplication(), [
            'terms'      => "Masa percobaan 3 bulan.\n<script>x</script>",
            'start_date' => '2026-11-01',
        ]);

        $html = app(OfferLetterPdf::class)->html($offer);

        $this->assertStringContainsString('Altima Group', $html);
        $this->assertStringContainsString('Siti Kandidat', $html);
        $this->assertStringContainsString('Rp 8.500.000', $html);
        $this->assertStringContainsString('Tunjangan Makan', $html);
        $this->assertStringContainsString('Rp 9.000.000', $html);
        $this->assertStringContainsString('1 November 2026', $html);
        $this->assertStringContainsString('Masa percobaan 3 bulan.<br', $html);
        $this->assertStringNotContainsString('<script>x</script>', $html);
    }

    public function test_offer_letter_renders_pdf_when_libreoffice_available(): void
    {
        if (! app(PayslipPdfConverter::class)->isAvailable()) {
            $this->markTestSkipped('LibreOffice tidak tersedia.');
        }

        $offer = $this->makeOffer($this->makeApplication());

        $response = $this->get("/api/offers/{$offer->id}/pdf")->assertStatus(200);

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_offer_mail_links_to_public_page_and_attaches_pdf(): void
    {
        // render() ikut membangun lampiran; PDF asli butuh LibreOffice, jadi dipalsukan
        $this->mock(OfferLetterPdf::class)->shouldReceive('render')->andReturn('%PDF-fake');
        $offer = $this->sentOffer($this->makeApplication(), ['start_date' => '2026-11-01']);

        $mail = new OfferLetterMail($offer->load('application'));

        $mail->assertHasSubject('Surat Penawaran Kerja – Bandung - Head Chef');
        $mail->assertSeeInHtml($offer->publicUrl());
        $mail->assertSeeInHtml('1 November 2026');
        $mail->assertHasAttachedData('%PDF-fake', $offer->pdfFileName(), ['mime' => 'application/pdf']);
    }
}
