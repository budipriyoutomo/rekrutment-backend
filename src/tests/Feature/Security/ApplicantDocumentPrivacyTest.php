<?php

namespace Tests\Feature\Security;

use App\Core\Services\FileUploadService;
use App\Domains\Application\Models\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ApplicantDocumentPrivacyTest extends TestCase
{
    use RefreshDatabase;

    private string $disk;

    private string $bundleDisk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->disk = config('filesystems.upload_disk');
        $this->bundleDisk = config('filesystems.bundle_disk');
        Storage::fake($this->disk);
        Storage::fake($this->bundleDisk);
    }

    private function pdf(string $name = 'cv.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
    }

    private function applicationWithDocuments(array $documents): Application
    {
        return Application::create([
            'personal_info' => ['fullName' => 'Kandidat Rahasia'],
            'contact_info' => ['email' => 'kandidat@example.com'],
            'stage' => 'applied',
            'documents' => $documents,
        ]);
    }

    public function test_uploaded_applicant_document_is_private(): void
    {
        $result = (new FileUploadService)->upload($this->pdf(), 'applications/cv');

        Storage::disk($this->disk)->assertExists($result['path']);
        $this->assertSame('private', Storage::disk($this->disk)->getVisibility($result['path']));
    }

    public function test_upload_metadata_does_not_store_permanent_public_url(): void
    {
        $result = (new FileUploadService)->upload($this->pdf(), 'applications/cv');

        $this->assertNull($result['file_url']);
    }

    public function test_stored_filename_uses_guessed_extension(): void
    {
        // File asli (bukan fake) agar MIME dideteksi dari isi, bukan dari nama.
        $tmp = tempnam(sys_get_temp_dir(), 'cv');
        file_put_contents($tmp, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
        $disguised = new UploadedFile($tmp, 'cv.html', null, null, true);

        $result = (new FileUploadService)->upload($disguised, 'applications/cv');
        @unlink($tmp);

        $this->assertStringEndsWith('.pdf', $result['path']);
    }

    public function test_applicant_resource_returns_temporary_url(): void
    {
        $this->actingAsAdmin();
        Storage::disk($this->disk)->put('applications/ktp/abc.jpg', 'x', ['visibility' => 'private']);
        $application = $this->applicationWithDocuments([
            'ktp' => [
                'path' => 'applications/ktp/abc.jpg',
                'disk' => $this->disk,
                'file_name' => 'ktp.jpg',
                'file_url' => 'https://bucket.example.com/applications/ktp/abc.jpg',
            ],
        ]);

        $url = $this->getJson("/api/applicants/{$application->id}")
            ->assertStatus(200)
            ->json('data.documents.ktp.file_url');

        $this->assertStringContainsString('expiration=', $url);
        $this->assertStringNotContainsString('bucket.example.com', $url);
    }

    public function test_document_without_path_keeps_null_url(): void
    {
        $this->actingAsAdmin();
        $application = $this->applicationWithDocuments(['cv' => null]);

        $this->getJson("/api/applicants/{$application->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.documents.cv', null);
    }

    public function test_bundle_status_returns_temporary_url(): void
    {
        $this->actingAsAdmin();
        Storage::disk($this->bundleDisk)->put('final_bundel/rekrutmen_x.pdf', 'x', ['visibility' => 'private']);
        $application = $this->applicationWithDocuments([
            'bundle' => [
                'status' => 'ready',
                'path' => 'final_bundel/rekrutmen_x.pdf',
                'file_url' => 'https://bucket.example.com/final_bundel/rekrutmen_x.pdf',
                'file_name' => 'bundel.pdf',
            ],
        ]);

        $url = $this->getJson("/api/applicants/{$application->id}/bundle/status")
            ->assertStatus(200)
            ->json('data.file_url');

        $this->assertStringContainsString('expiration=', $url);
    }

    public function test_privatize_command_dry_run_changes_nothing(): void
    {
        Storage::disk($this->disk)->put('applications/cv/lama.pdf', 'x', ['visibility' => 'public']);

        $this->artisan('storage:privatize-applicant-documents')
            ->expectsOutputToContain('applications/cv/lama.pdf')
            ->assertSuccessful();

        $this->assertSame('public', Storage::disk($this->disk)->getVisibility('applications/cv/lama.pdf'));
    }

    public function test_privatize_command_apply_makes_documents_private(): void
    {
        Storage::disk($this->disk)->put('applications/cv/lama.pdf', 'x', ['visibility' => 'public']);
        Storage::disk($this->disk)->put('applications/ktp/lama.jpg', 'x', ['visibility' => 'public']);
        Storage::disk($this->bundleDisk)->put('final_bundel/rekrutmen_lama.pdf', 'x', ['visibility' => 'public']);
        Storage::disk($this->disk)->put('lain/logo.png', 'x', ['visibility' => 'public']);

        $this->artisan('storage:privatize-applicant-documents', ['--apply' => true])->assertSuccessful();

        $this->assertSame('private', Storage::disk($this->disk)->getVisibility('applications/cv/lama.pdf'));
        $this->assertSame('private', Storage::disk($this->disk)->getVisibility('applications/ktp/lama.jpg'));
        $this->assertSame('private', Storage::disk($this->bundleDisk)->getVisibility('final_bundel/rekrutmen_lama.pdf'));
        // Di luar folder dokumen pelamar tidak disentuh.
        $this->assertSame('public', Storage::disk($this->disk)->getVisibility('lain/logo.png'));
    }
}
