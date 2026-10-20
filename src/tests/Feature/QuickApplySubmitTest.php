<?php

namespace Tests\Feature;

use App\Core\Services\FileUploadService;
use App\Domains\Application\Models\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QuickApplySubmitTest extends TestCase
{
    use RefreshDatabase;

    private string $disk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->disk = config('filesystems.upload_disk');
        Storage::fake($this->disk);
    }

    private function payload(string $email = 'rina@example.com'): array
    {
        return [
            'personalInfo' => ['fullName' => 'Rina Quick', 'gender' => 'Perempuan'],
            'contactInfo' => ['email' => $email, 'phone' => '08123456789'],
            'education' => [],
            'certifications' => [],
            'workExperience' => [],
            'parentInfo' => [],
            'spouseInfo' => [],
            'additionalInfo' => [
                'quickApply' => true,
                'positionApplied' => 'Bandung - Cook',
                'workLocation' => 'Bandung - KARNIVOR - JL. RIAU',
            ],
        ];
    }

    private function submit(array $files, ?array $payload = null)
    {
        return $this->post('/api/applications/quick', array_merge(
            ['payload' => json_encode($payload ?? $this->payload())],
            $files
        ), ['Accept' => 'application/json']);
    }

    private function cv(): UploadedFile
    {
        return UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf');
    }

    private function foto(): UploadedFile
    {
        return UploadedFile::fake()->image('foto.jpg');
    }

    public function test_saves_application_together_with_cv_and_foto(): void
    {
        $id = $this->submit(['cv' => $this->cv(), 'foto' => $this->foto()])
            ->assertStatus(200)
            ->json('data.applicationId');

        $app = Application::findOrFail($id);
        $this->assertTrue($app->hasDocument('cv'));
        $this->assertTrue($app->hasDocument('foto'));
        $this->assertSame('Rina Quick', $app->personal_info['fullName']);
        Storage::disk($this->disk)->assertExists($app->documents['cv']['path']);
        Storage::disk($this->disk)->assertExists($app->documents['foto']['path']);
    }

    public function test_rejects_without_cv(): void
    {
        $this->submit(['foto' => $this->foto()])
            ->assertStatus(422)
            ->assertJsonValidationErrors('cv');

        $this->assertDatabaseCount('applications', 0);
    }

    public function test_rejects_without_foto(): void
    {
        $this->submit(['cv' => $this->cv()])
            ->assertStatus(422)
            ->assertJsonValidationErrors('foto');

        $this->assertDatabaseCount('applications', 0);
    }

    public function test_rejects_oversized_and_wrong_type_files(): void
    {
        $this->submit([
            'cv' => UploadedFile::fake()->create('cv.pdf', 3000, 'application/pdf'),
            'foto' => UploadedFile::fake()->create('foto.pdf', 10, 'application/pdf'),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['cv', 'foto']);

        $this->assertDatabaseCount('applications', 0);
    }

    public function test_still_validates_application_fields(): void
    {
        $payload = $this->payload();
        $payload['additionalInfo']['workLocation'] = 'Jakarta - HEAD OFFICE JAKARTA';

        $this->submit(['cv' => $this->cv(), 'foto' => $this->foto()], $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('additionalInfo.workLocation');

        $this->assertDatabaseCount('applications', 0);
    }

    public function test_duplicate_email_is_rejected_before_uploading(): void
    {
        $this->submit(['cv' => $this->cv(), 'foto' => $this->foto()])->assertStatus(200);
        $filesBefore = count(Storage::disk($this->disk)->allFiles());

        $this->submit(['cv' => $this->cv(), 'foto' => $this->foto()])
            ->assertStatus(422)
            ->assertJsonValidationErrors('contactInfo.email');

        $this->assertDatabaseCount('applications', 1);
        $this->assertCount($filesBefore, Storage::disk($this->disk)->allFiles());
    }

    public function test_nothing_is_saved_when_cv_processing_fails(): void
    {
        $this->mock(FileUploadService::class, function ($mock) {
            $mock->shouldReceive('uploadCvAsPdf')->andThrow(new \Exception('Gagal mengonversi CV ke PDF'));
            $mock->shouldNotReceive('upload');
        });

        $this->submit(['cv' => UploadedFile::fake()->create('cv.docx', 50), 'foto' => $this->foto()])
            ->assertStatus(422)
            ->assertJsonValidationErrors('cv');

        $this->assertDatabaseCount('applications', 0);
    }

    public function test_uploaded_cv_is_removed_when_foto_upload_fails(): void
    {
        $this->mock(FileUploadService::class, function ($mock) {
            $mock->shouldReceive('uploadCvAsPdf')->andReturn(['path' => 'applications/cv/x.pdf']);
            $mock->shouldReceive('upload')->andThrow(new \Exception('S3 down'));
            $mock->shouldReceive('delete')->once()->with('applications/cv/x.pdf')->andReturnTrue();
        });

        $this->submit(['cv' => $this->cv(), 'foto' => $this->foto()])
            ->assertStatus(422)
            ->assertJsonValidationErrors('foto');

        $this->assertDatabaseCount('applications', 0);
    }

    public function test_missing_documents_command_lists_incomplete_applications(): void
    {
        Application::create([
            'personal_info' => ['fullName' => 'Tanpa Dokumen'],
            'contact_info' => ['email' => 'kosong@example.com'],
            'additional_info' => ['quickApply' => true],
            'stage' => 'applied',
        ]);
        Application::create([
            'personal_info' => ['fullName' => 'Lengkap'],
            'contact_info' => ['email' => 'lengkap@example.com'],
            'additional_info' => ['quickApply' => true],
            'documents' => ['cv' => ['path' => 'a'], 'foto' => ['path' => 'b']],
            'stage' => 'applied',
        ]);

        $this->artisan('applications:missing-documents', ['--quick-only' => true])
            ->expectsOutputToContain('Tanpa Dokumen')
            ->doesntExpectOutputToContain('Lengkap')
            ->expectsOutputToContain('1 lamaran belum lengkap')
            ->assertSuccessful();
    }
}
