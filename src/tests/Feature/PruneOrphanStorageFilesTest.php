<?php

namespace Tests\Feature;

use App\Domains\Application\Models\Application;
use App\Domains\Preboarding\Models\Preboarding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PruneOrphanStorageFilesTest extends TestCase
{
    use RefreshDatabase;

    private string $disk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->disk = config('filesystems.upload_disk');
        Storage::fake($this->disk);
    }

    /** Tulis file dengan mtime N jam lalu. */
    private function putFile(string $path, int $hoursAgo = 48): void
    {
        Storage::disk($this->disk)->put($path, 'x');
        touch(Storage::disk($this->disk)->path($path), now()->subHours($hoursAgo)->getTimestamp());
    }

    private function seedFixtures(): string
    {
        $app = Application::create([
            'personal_info' => ['fullName' => 'A'],
            'contact_info' => ['email' => 'a@example.com'],
            'additional_info' => [],
            'stage' => 'applied',
            'documents' => [
                'cv' => ['path' => 'applications/cv/keep.pdf', 'disk' => $this->disk],
                'foto' => 'applications/foto/legacy.jpg',
                'ktp' => ['path' => 'https://is3.example/bucket/applications/ktp/url.jpg'],
                'bundle' => ['path' => 'final_bundel/rekrutmen_1.pdf'],
            ],
        ]);
        Preboarding::create([
            'application_id' => $app->id,
            'status' => Preboarding::STATUS_PENDING,
            'token' => Str::random(64),
            'token_expires_at' => now()->addDays(30),
            'documents' => ['kk' => ['path' => "preboarding/{$app->id}/kk.jpg", 'disk' => $this->disk]],
        ]);

        foreach (['applications/cv/keep.pdf', 'applications/foto/legacy.jpg', 'applications/ktp/url.jpg',
            'final_bundel/rekrutmen_1.pdf', "preboarding/{$app->id}/kk.jpg",
            'applications/cv/orphan.pdf', 'final_bundel/rekrutmen_old.pdf', 'other/untouched.txt'] as $path) {
            $this->putFile($path);
        }
        $this->putFile('applications/foto/recent.jpg', 1);

        return "preboarding/{$app->id}/kk.jpg";
    }

    public function test_dry_run_does_not_delete(): void
    {
        $this->seedFixtures();

        $this->artisan('storage:prune-orphans')->assertSuccessful();

        Storage::disk($this->disk)->assertExists(['applications/cv/orphan.pdf', 'final_bundel/rekrutmen_old.pdf']);
    }

    public function test_delete_removes_only_old_unreferenced_files_in_known_prefixes(): void
    {
        $kk = $this->seedFixtures();

        $this->artisan('storage:prune-orphans', ['--delete' => true, '--force' => true])->assertSuccessful();

        $disk = Storage::disk($this->disk);
        $disk->assertMissing(['applications/cv/orphan.pdf', 'final_bundel/rekrutmen_old.pdf']);
        $disk->assertExists([
            'applications/cv/keep.pdf',
            'applications/foto/legacy.jpg',
            'applications/ktp/url.jpg',
            'final_bundel/rekrutmen_1.pdf',
            $kk,
            'applications/foto/recent.jpg',
            'other/untouched.txt',
        ]);
    }

    public function test_min_age_zero_includes_recent_files(): void
    {
        $this->seedFixtures();

        $this->artisan('storage:prune-orphans', ['--delete' => true, '--force' => true, '--min-age' => 0])->assertSuccessful();

        Storage::disk($this->disk)->assertMissing('applications/foto/recent.jpg');
    }
}
