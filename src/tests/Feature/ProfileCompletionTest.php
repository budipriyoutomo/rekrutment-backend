<?php

namespace Tests\Feature;

use App\Core\Services\FileUploadService;
use App\Domains\Application\Models\Application;
use App\Domains\Application\Models\ApplicationEducation;
use App\Domains\ProfileCompletion\Models\ProfileCompletionToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileCompletionTest extends TestCase
{
    use RefreshDatabase;

    private string $disk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->disk = config('filesystems.upload_disk');
        Storage::fake($this->disk);
    }

    /** Lamaran hasil Quick Apply: sudah punya CV & foto, belum ada ijazah. */
    private function quickApplication(array $override = []): Application
    {
        Storage::disk($this->disk)->put('applications/cv/lama.pdf', 'cv');
        Storage::disk($this->disk)->put('applications/foto/lama.jpg', 'foto');

        return Application::create(array_merge([
            'personal_info' => ['fullName' => 'Rina', 'gender' => 'Perempuan'],
            'contact_info' => ['email' => 'rina@example.com', 'phone' => '08123456789', 'homeAddress' => 'Bandung'],
            'additional_info' => [
                'quickApply' => true,
                'positionApplied' => 'Bandung - Cook',
                'workLocation' => 'Bandung - KARNIVOR - JL. RIAU',
                'jobSource' => 'Instagram',
            ],
            'documents' => [
                'cv' => ['path' => 'applications/cv/lama.pdf', 'file_name' => 'cv-rina.pdf', 'size' => 1234, 'file_url' => 'http://x/cv'],
                'foto' => ['path' => 'applications/foto/lama.jpg', 'file_name' => 'rina.jpg', 'size' => 999],
            ],
            'notes' => [['text' => 'catatan internal HR']],
            'stage' => 'screening',
        ], $override));
    }

    private function token(Application $app, array $override = []): ProfileCompletionToken
    {
        return ProfileCompletionToken::create(array_merge([
            'application_id' => $app->id,
            'email' => 'rina@example.com',
            'token' => 'tok-123',
            'expires_at' => now()->addDays(7),
        ], $override));
    }

    private function payload(array $override = []): array
    {
        return array_replace_recursive([
            'personalInfo' => ['fullName' => 'Rina Lengkap', 'gender' => 'P', 'idNumber' => '3273010101010001'],
            'contactInfo' => ['email' => 'rina@example.com', 'phone' => '08123456789', 'homeAddress' => 'Jl. Riau 1'],
            'education' => [
                ['level' => 'SMA/SMK', 'schoolName' => 'SMAN 1 Bandung', 'city' => 'Bandung', 'yearStart' => '2015', 'yearEnd' => '2018'],
                ['level' => 'Universitas', 'schoolName' => '', 'city' => '', 'yearStart' => '', 'yearEnd' => ''],
            ],
            'certifications' => [['courseName' => '', 'organization' => '', 'year' => '', 'duration' => '']],
            'workExperience' => [[
                'companyName' => 'Resto A', 'jobPosition' => 'Cook', 'yearStart' => '2019', 'yearEnd' => '2023',
                'restaurantType' => 'Casual Dining', 'posExperience' => 'yes', 'posSystem' => 'Moka',
            ]],
            'parentInfo' => ['fatherName' => 'Slamet', 'parentStatus' => 'lengkap'],
            'spouseInfo' => ['spouseName' => ''],
            'additionalInfo' => [
                'freshGraduate' => 'tidak',
                'expectedSalary' => '5000000',
                'positionApplied' => 'Bandung - Cook',
                'workLocation' => 'Bandung - KARNIVOR - JL. RIAU',
            ],
        ], $override);
    }

    private function submit(array $files = [], ?array $payload = null, string $token = 'tok-123')
    {
        return $this->post("/api/profile-completion/{$token}", array_merge(
            ['payload' => json_encode($payload ?? $this->payload())],
            $files
        ), ['Accept' => 'application/json']);
    }

    private function ijazah(): UploadedFile
    {
        return UploadedFile::fake()->create('ijazah.pdf', 300, 'application/pdf');
    }

    // ---------------------------------------------------------------- GET token

    public function test_validate_token_returns_prefill_without_internal_data(): void
    {
        $app = $this->quickApplication();
        $this->token($app);

        $res = $this->getJson('/api/profile-completion/tok-123')->assertOk();

        $res->assertJsonPath('data.profile.personalInfo.fullName', 'Rina')
            ->assertJsonPath('data.profile.additionalInfo.positionApplied', 'Bandung - Cook')
            ->assertJsonPath('data.profile.documents.cv', ['name' => 'cv-rina.pdf', 'size' => 1234])
            ->assertJsonMissingPath('data.profile.documents.ijazah')
            ->assertJsonMissingPath('data.profile.notes');
        $this->assertStringNotContainsString('applications/cv/lama.pdf', $res->getContent());
        $this->assertStringNotContainsString('catatan internal HR', $res->getContent());
    }

    public function test_validate_token_reports_invalid_expired_and_used_links(): void
    {
        $app = $this->quickApplication();
        $this->getJson('/api/profile-completion/tidak-ada')->assertStatus(404);

        $this->token($app, ['token' => 'expired', 'expires_at' => now()->subDay()]);
        $this->getJson('/api/profile-completion/expired')->assertStatus(410);

        $this->token($app, ['token' => 'used', 'used_at' => now()]);
        $this->getJson('/api/profile-completion/used')->assertStatus(409);
    }

    // ------------------------------------------------------------------ submit

    public function test_submit_updates_the_existing_application_with_the_same_email(): void
    {
        $app = $this->quickApplication();
        $this->token($app);

        $this->submit(['ijazah' => $this->ijazah()])
            ->assertOk()
            ->assertJsonPath('data.applicationId', $app->id);

        $this->assertDatabaseCount('applications', 1);
        $app->refresh();

        $this->assertSame('done', $app->profile_completion_status);
        $this->assertSame('Rina Lengkap', $app->personal_info['fullName']);
        $this->assertSame('3273010101010001', $app->personal_info['idNumber']);
        $this->assertSame('Jl. Riau 1', $app->contact_info['homeAddress']);
        $this->assertSame('lengkap', $app->parent_info['parentStatus']);
        // Data saat melamar tetap terjaga.
        $this->assertTrue($app->additional_info['quickApply']);
        $this->assertSame('Instagram', $app->additional_info['jobSource']);
        $this->assertSame('screening', $app->stage);
        // CV & foto lama dipertahankan, ijazah ditambahkan.
        $this->assertSame('applications/cv/lama.pdf', $app->documents['cv']['path']);
        $this->assertSame('applications/foto/lama.jpg', $app->documents['foto']['path']);
        Storage::disk($this->disk)->assertExists($app->documents['ijazah']['path']);
        $this->assertNotNull(ProfileCompletionToken::where('token', 'tok-123')->value('used_at'));
    }

    public function test_submit_saves_relations_and_skips_empty_rows(): void
    {
        $app = $this->quickApplication();
        $this->token($app);

        $this->submit(['ijazah' => $this->ijazah()])->assertOk();

        $this->assertSame(['SMAN 1 Bandung'], ApplicationEducation::where('application_id', $app->id)->pluck('school_name')->all());
        $this->assertSame(0, $app->certifications()->count());
        $exp = $app->experiences()->sole();
        $this->assertSame('Resto A', $exp->company_name);
        $this->assertSame('Casual Dining', $exp->restaurant_type);
        $this->assertSame('yes', $exp->pos_experience);
        $this->assertSame('Moka', $exp->pos_system);
    }

    public function test_position_and_work_location_cannot_be_changed(): void
    {
        $app = $this->quickApplication();
        $this->token($app);

        $this->submit(['ijazah' => $this->ijazah()], $this->payload(['additionalInfo' => [
            'positionApplied' => 'Bandung - Head Chef',
            'workLocation' => 'Bandung - KARNIVOR - JL. RIAU',
        ]]))->assertOk();

        $this->assertSame('Bandung - Cook', $app->fresh()->additional_info['positionApplied']);
    }

    public function test_new_files_replace_old_ones_and_old_files_are_deleted(): void
    {
        $app = $this->quickApplication();
        $this->token($app);

        $this->submit([
            'cv' => UploadedFile::fake()->create('cv-baru.pdf', 4000, 'application/pdf'), // 4MB: di atas batas Quick Apply
            'ijazah' => $this->ijazah(),
        ])->assertOk();

        $cv = $app->fresh()->documents['cv'];
        $this->assertSame('cv-baru.pdf', $cv['file_name']);
        Storage::disk($this->disk)->assertExists($cv['path']);
        Storage::disk($this->disk)->assertMissing('applications/cv/lama.pdf');
    }

    public function test_requires_documents_that_the_application_does_not_have_yet(): void
    {
        $app = $this->quickApplication(['documents' => null]);
        $this->token($app);

        $this->submit()
            ->assertStatus(422)
            ->assertJsonValidationErrors(['cv', 'foto', 'ijazah']);

        $this->assertSame('undone', $app->fresh()->profile_completion_status ?? 'undone');
    }

    public function test_rejects_email_used_by_another_application(): void
    {
        Application::create([
            'personal_info' => ['fullName' => 'Orang Lain'],
            'contact_info' => ['email' => 'lain@example.com'],
            'stage' => 'applied',
        ]);
        $app = $this->quickApplication();
        $this->token($app);

        $this->submit(['ijazah' => $this->ijazah()], $this->payload(['contactInfo' => ['email' => 'lain@example.com']]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('contactInfo.email');

        $this->assertNull(ProfileCompletionToken::where('token', 'tok-123')->value('used_at'));
    }

    public function test_rejects_expired_and_used_tokens(): void
    {
        $app = $this->quickApplication();
        $this->token($app, ['token' => 'expired', 'expires_at' => now()->subDay()]);
        $this->token($app, ['token' => 'used', 'used_at' => now()]);

        $this->submit(['ijazah' => $this->ijazah()], null, 'expired')->assertStatus(410);
        $this->submit(['ijazah' => $this->ijazah()], null, 'used')->assertStatus(409);
        $this->submit(['ijazah' => $this->ijazah()], null, 'tidak-ada')->assertStatus(404);
        $this->assertSame('Rina', $app->fresh()->personal_info['fullName']);
    }

    public function test_nothing_changes_when_an_upload_fails(): void
    {
        $app = $this->quickApplication();
        $this->token($app);

        $this->mock(FileUploadService::class, function ($mock) {
            $mock->shouldReceive('upload')->once()->andReturn(['path' => 'applications/ktp/x.jpg']);
            $mock->shouldReceive('upload')->once()->andThrow(new \Exception('S3 down'));
            $mock->shouldReceive('delete')->once()->with('applications/ktp/x.jpg')->andReturnTrue();
        });

        $this->submit([
            'ktp' => UploadedFile::fake()->image('ktp.jpg'),
            'ijazah' => $this->ijazah(),
        ])->assertStatus(422)->assertJsonValidationErrors('ijazah');

        $app->refresh();
        $this->assertSame('Rina', $app->personal_info['fullName']);
        $this->assertArrayNotHasKey('ktp', $app->documents);
        $this->assertNull(ProfileCompletionToken::where('token', 'tok-123')->value('used_at'));
    }

    // --------------------------------------------------- public upload endpoint

    public function test_public_upload_cannot_overwrite_an_existing_document(): void
    {
        $app = $this->quickApplication();

        $this->post('/api/applications/upload', [
            'file' => UploadedFile::fake()->create('palsu.pdf', 10, 'application/pdf'),
            'type' => 'cv',
            'application_id' => $app->id,
        ], ['Accept' => 'application/json'])->assertStatus(409);

        $this->assertSame('applications/cv/lama.pdf', $app->fresh()->documents['cv']['path']);
    }

    public function test_public_upload_can_still_fill_an_empty_slot(): void
    {
        $app = $this->quickApplication();

        $this->post('/api/applications/upload', [
            'file' => UploadedFile::fake()->image('ktp.jpg'),
            'type' => 'ktp',
            'application_id' => $app->id,
        ], ['Accept' => 'application/json'])->assertOk();

        $this->assertTrue($app->fresh()->hasDocument('ktp'));
    }
}
