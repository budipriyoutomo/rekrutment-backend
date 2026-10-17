<?php

namespace Tests\Feature;

use App\Domains\Application\Models\Application;
use App\Domains\Offer\Models\Offer;
use App\Domains\Preboarding\Mail\PreboardingInvitationMail;
use App\Domains\Preboarding\Models\Preboarding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Fase 4 Offer→Onboarding: pre-boarding (data & dokumen karyawan baru).
 */
class PreboardingTest extends TestCase
{
    use RefreshDatabase;

    private string $disk;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->disk = config('filesystems.upload_disk');
        Storage::fake($this->disk);
    }

    private function makeApplication(array $override = []): Application
    {
        return Application::create(array_merge([
            'personal_info'   => ['fullName' => 'Siti Kandidat'],
            'contact_info'    => ['email' => 'siti@example.com', 'phone' => '081'],
            'additional_info' => ['positionApplied' => 'Bandung - Head Chef'],
            'stage'           => 'hired',
        ], $override));
    }

    private function makePreboarding(array $override = [], ?Application $app = null): Preboarding
    {
        return Preboarding::create(array_merge([
            'application_id'   => ($app ?? $this->makeApplication())->id,
            'status'           => Preboarding::STATUS_PENDING,
            'token'            => Str::random(64),
            'token_expires_at' => now()->addDays(30),
            'documents'        => [],
        ], $override));
    }

    private function validData(array $override = []): array
    {
        return array_merge([
            'bankName'                     => 'BCA',
            'bankAccountNumber'            => '1234567890',
            'bankAccountHolder'            => 'Siti Kandidat',
            'npwp'                         => '12.345.678.9-012.345',
            'bpjsKesehatan'                => '0001234567890',
            'bpjsKetenagakerjaan'          => '12345678901',
            'emergencyContactName'         => 'Ibu Siti',
            'emergencyContactRelationship' => 'Ibu',
            'emergencyContactPhone'        => '08123456789',
            'uniformSize'                  => 'M',
        ], $override);
    }

    private function uploadAll(Preboarding $preboarding): void
    {
        foreach (array_keys(Preboarding::REQUIRED_DOCUMENTS) as $type) {
            $this->post("/api/preboarding/{$preboarding->token}/documents", [
                'type' => $type,
                'file' => UploadedFile::fake()->image("{$type}.jpg"),
            ], ['Accept' => 'application/json'])->assertStatus(200);
        }
    }

    /** Pre-boarding yang sudah dikirim kandidat dengan semua dokumen terunggah. */
    private function submittedPreboarding(): Preboarding
    {
        $preboarding = $this->makePreboarding();
        $this->uploadAll($preboarding);
        $this->postJson("/api/preboarding/{$preboarding->token}/submit", $this->validData())->assertStatus(200);

        return $preboarding->fresh();
    }

    // ------------------------------------------------- start (offer accepted)

    public function test_accepting_offer_starts_preboarding_and_emails_candidate(): void
    {
        $app = $this->makeApplication(['stage' => 'offer']);
        $offer = Offer::create([
            'number' => 'OFR/2026/10/0001', 'application_id' => $app->id, 'position' => 'Bandung - Head Chef',
            'employment_type' => 'full-time', 'base_salary' => 8500000, 'start_date' => '2026-11-01',
            'expires_at' => now()->addWeek()->toDateString(), 'status' => Offer::STATUS_SENT, 'token' => str_repeat('a', 64),
        ]);

        $this->postJson('/api/offer/' . $offer->token . '/accept')->assertStatus(200);

        $preboarding = Preboarding::where('application_id', $app->id)->firstOrFail();
        $this->assertSame('pending', $preboarding->status);
        $this->assertSame($offer->id, $preboarding->offer_id);
        $this->assertNotNull($preboarding->sent_at);
        Mail::assertQueued(PreboardingInvitationMail::class, fn ($m) => $m->hasTo('siti@example.com') && ! $m->revision);
    }

    public function test_hr_can_start_preboarding_manually_only_for_hired_once(): void
    {
        $this->actingAsAdmin();
        $hired = $this->makeApplication();
        $interview = $this->makeApplication(['stage' => 'interview', 'contact_info' => ['email' => 'x@example.com']]);

        $this->postJson('/api/preboardings', ['application_id' => $interview->id])->assertStatus(422);
        $this->postJson('/api/preboardings', ['application_id' => $hired->id])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.candidateName', 'Siti Kandidat');
        $this->postJson('/api/preboardings', ['application_id' => $hired->id])->assertStatus(422);

        Mail::assertQueued(PreboardingInvitationMail::class, 1);
    }

    // --------------------------------------------------------- candidate side

    public function test_public_show_lists_required_documents_without_paths(): void
    {
        $preboarding = $this->makePreboarding();
        $this->uploadAll($preboarding);

        $response = $this->getJson("/api/preboarding/{$preboarding->token}")
            ->assertStatus(200)
            ->assertJsonPath('data.candidateName', 'Siti Kandidat')
            ->assertJsonPath('data.editable', true)
            ->assertJsonCount(5, 'data.documents')
            ->assertJsonPath('data.documents.0.type', 'ktp')
            ->assertJsonPath('data.documents.0.uploaded', true)
            ->assertJsonPath('data.documents.0.status', 'pending');

        $this->assertStringNotContainsString('preboarding/', $response->getContent());
    }

    public function test_unknown_or_expired_token(): void
    {
        $expired = $this->makePreboarding(['token_expires_at' => now()->subMinute()]);

        $this->getJson('/api/preboarding/' . str_repeat('z', 64))->assertStatus(404);
        $this->getJson("/api/preboarding/{$expired->token}")->assertStatus(410);
    }

    public function test_upload_stores_document_privately_and_replaces_old_file(): void
    {
        $preboarding = $this->makePreboarding();

        $this->post("/api/preboarding/{$preboarding->token}/documents", [
            'type' => 'ktp', 'file' => UploadedFile::fake()->image('ktp-lama.jpg'),
        ], ['Accept' => 'application/json'])->assertStatus(200);
        $oldPath = $preboarding->fresh()->document('ktp')['path'];

        $this->post("/api/preboarding/{$preboarding->token}/documents", [
            'type' => 'ktp', 'file' => UploadedFile::fake()->create('ktp.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertStatus(200);

        $doc = $preboarding->fresh()->document('ktp');
        Storage::disk($this->disk)->assertExists($doc['path']);
        Storage::disk($this->disk)->assertMissing($oldPath);
        $this->assertSame('private', Storage::disk($this->disk)->getVisibility($doc['path']));
        $this->assertSame('ktp.pdf', $doc['file_name']);
        $this->assertSame('pending', $doc['status']);
    }

    public function test_upload_validates_type_and_file(): void
    {
        $preboarding = $this->makePreboarding();

        $this->post("/api/preboarding/{$preboarding->token}/documents", [
            'type' => 'paspor', 'file' => UploadedFile::fake()->image('x.jpg'),
        ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('type');

        $this->post("/api/preboarding/{$preboarding->token}/documents", [
            'type' => 'ktp', 'file' => UploadedFile::fake()->create('x.exe', 10),
        ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('file');

        $this->post("/api/preboarding/{$preboarding->token}/documents", [
            'type' => 'ktp', 'file' => UploadedFile::fake()->create('big.pdf', 5000, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('file');
    }

    public function test_submit_requires_all_documents_and_valid_data(): void
    {
        $preboarding = $this->makePreboarding();

        $this->postJson("/api/preboarding/{$preboarding->token}/submit", $this->validData())
            ->assertStatus(422)
            ->assertJsonValidationErrors('documents');

        $this->uploadAll($preboarding);

        $this->postJson("/api/preboarding/{$preboarding->token}/submit", $this->validData([
            'bankAccountNumber' => '12ab',
            'bpjsKesehatan'     => '123',
            'emergencyContactName' => '',
        ]))->assertStatus(422)->assertJsonValidationErrors(['bankAccountNumber', 'bpjsKesehatan', 'emergencyContactName']);

        $this->postJson("/api/preboarding/{$preboarding->token}/submit", $this->validData(['npwp' => null, 'bpjsKesehatan' => null]))
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.editable', false)
            ->assertJsonPath('data.data.bankName', 'BCA');
    }

    public function test_candidate_cannot_edit_after_submit(): void
    {
        $preboarding = $this->submittedPreboarding();

        $this->postJson("/api/preboarding/{$preboarding->token}/submit", $this->validData())->assertStatus(409);
        $this->post("/api/preboarding/{$preboarding->token}/documents", [
            'type' => 'ktp', 'file' => UploadedFile::fake()->image('x.jpg'),
        ], ['Accept' => 'application/json'])->assertStatus(409);
    }

    // ------------------------------------------------------------ HR review

    public function test_all_documents_valid_verifies_preboarding(): void
    {
        $this->actingAsAdmin();
        $preboarding = $this->submittedPreboarding();

        foreach (array_keys(Preboarding::REQUIRED_DOCUMENTS) as $i => $type) {
            $response = $this->postJson("/api/preboardings/{$preboarding->id}/documents/{$type}/review", ['status' => 'valid'])
                ->assertStatus(200);
        }

        $response->assertJsonPath('data.status', 'verified');
        $this->assertNotNull($preboarding->fresh()->verified_at);
    }

    public function test_rejecting_requires_note_and_revision_flow(): void
    {
        $this->actingAsAdmin();
        $preboarding = $this->submittedPreboarding();
        $base = "/api/preboardings/{$preboarding->id}";

        $this->postJson("{$base}/request-revision")->assertStatus(422);
        $this->postJson("{$base}/documents/ktp/review", ['status' => 'rejected'])
            ->assertStatus(422)->assertJsonValidationErrors('note');
        $this->postJson("{$base}/documents/ktp/review", ['status' => 'rejected', 'note' => 'Foto KTP buram'])->assertStatus(200);
        foreach (['kk', 'ijazah', 'foto', 'buku_tabungan'] as $type) {
            $this->postJson("{$base}/documents/{$type}/review", ['status' => 'valid'])->assertStatus(200);
        }

        $this->postJson("{$base}/request-revision")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'needs_revision');
        Mail::assertQueued(PreboardingInvitationMail::class, fn ($m) => $m->revision);

        // Kandidat: dokumen yang sudah valid tak bisa diganti, submit ditolak sebelum KTP diunggah ulang
        $token = $preboarding->token;
        $this->post("/api/preboarding/{$token}/documents", ['type' => 'kk', 'file' => UploadedFile::fake()->image('kk.jpg')], ['Accept' => 'application/json'])
            ->assertStatus(422);
        $this->getJson("/api/preboarding/{$token}")->assertJsonPath('data.documents.0.note', 'Foto KTP buram');
        $this->postJson("/api/preboarding/{$token}/submit", $this->validData())->assertStatus(422)->assertJsonValidationErrors('documents');

        $this->post("/api/preboarding/{$token}/documents", ['type' => 'ktp', 'file' => UploadedFile::fake()->image('ktp.jpg')], ['Accept' => 'application/json'])
            ->assertStatus(200);
        $this->postJson("/api/preboarding/{$token}/submit", $this->validData())->assertJsonPath('data.status', 'submitted');

        $this->postJson("{$base}/documents/ktp/review", ['status' => 'valid'])->assertJsonPath('data.status', 'verified');
    }

    public function test_revision_email_lists_rejected_documents(): void
    {
        $preboarding = $this->makePreboarding(['documents' => [
            'ktp' => ['path' => 'x', 'status' => 'rejected', 'note' => 'Foto KTP buram'],
        ]]);

        $mail = new PreboardingInvitationMail($preboarding->load('application'), revision: true);

        $mail->assertHasSubject('Perbaikan Data Karyawan Baru Diperlukan');
        $mail->assertSeeInHtml('Foto KTP buram');
        $mail->assertSeeInHtml($preboarding->publicUrl());
    }

    public function test_hr_downloads_document_through_authenticated_endpoint(): void
    {
        $preboarding = $this->makePreboarding();
        $this->post("/api/preboarding/{$preboarding->token}/documents", [
            'type' => 'ktp', 'file' => UploadedFile::fake()->create('ktp.pdf', 50, 'application/pdf'),
        ], ['Accept' => 'application/json']);

        $this->getJson("/api/preboardings/{$preboarding->id}/documents/ktp")->assertStatus(401);

        $this->actingAsAdmin();
        $this->get("/api/preboardings/{$preboarding->id}/documents/ktp")
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');
        $this->get("/api/preboardings/{$preboarding->id}/documents/kk")->assertStatus(404);
    }

    public function test_resend_extends_link_and_requires_onboarding_permission(): void
    {
        $preboarding = $this->makePreboarding(['token_expires_at' => now()->subDay()]);

        $this->actingAsUserWithPermissions(['candidates']);
        $this->getJson("/api/preboardings?applicationId={$preboarding->application_id}")
            ->assertStatus(200)->assertJsonCount(1, 'data');
        $this->postJson("/api/preboardings/{$preboarding->id}/resend")->assertStatus(403);

        $this->actingAsUserWithPermissions(['onboarding']);
        $this->postJson("/api/preboardings/{$preboarding->id}/resend")->assertStatus(200);

        $this->assertTrue($preboarding->fresh()->token_expires_at->isFuture());
        Mail::assertQueued(PreboardingInvitationMail::class);
    }
}
