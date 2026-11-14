<?php

namespace Tests\Feature\Security;

use App\Domains\Application\Models\Application;
use App\Domains\ProfileCompletion\Models\ProfileCompletionToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * POST /profile-completion/{token}/complete dihapus: hanya menandai profil
 * "done" tanpa menyimpan data, dan frontend sudah memakai POST /{token} (submit).
 */
class DeprecatedProfileCompletionEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_endpoint_no_longer_marks_profile_done(): void
    {
        $application = Application::create([
            'personal_info' => ['fullName' => 'Rina'],
            'contact_info' => ['email' => 'rina@example.com'],
            'stage' => 'applied',
        ]);
        ProfileCompletionToken::create([
            'application_id' => $application->id,
            'email' => 'rina@example.com',
            'token' => 'tok-deprecated',
            'expires_at' => now()->addDays(7),
        ]);

        $this->postJson('/api/profile-completion/tok-deprecated/complete')->assertStatus(404);

        $this->assertNotSame('done', $application->fresh()->profile_completion_status);
    }
}
