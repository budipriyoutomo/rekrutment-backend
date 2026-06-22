<?php

namespace Tests\Feature;

use App\Domains\Application\Models\Application;
use App\Domains\Interview\Models\Interview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InterviewTest extends TestCase
{
    use RefreshDatabase;

    private function makeApplication(): Application
    {
        return Application::create([
            'personal_info' => ['fullName' => 'Test Candidate'],
            'contact_info'  => ['email' => 'candidate@example.com', 'phone' => '081234567890'],
            'additional_info' => ['positionApplied' => 'Backend Developer'],
        ]);
    }

    private function interviewData(string $applicantId, array $override = []): array
    {
        return array_merge([
            'applicantId'   => $applicantId,
            'applicantName' => 'Test Candidate',
            'position'      => 'Backend Developer',
            'date'          => now()->addDays(7)->format('Y-m-d'),
            'time'          => '09:00',
            'duration'      => '60 min',
            'type'          => 'online',
            'interviewers'  => ['Budi', 'Alice'],
        ], $override);
    }

    private function makeInterview(string $applicantId, array $override = []): Interview
    {
        return Interview::create(array_merge([
            'applicant_id'   => $applicantId,
            'applicant_name' => 'Test Candidate',
            'position'       => 'Backend Developer',
            'date'           => now()->addDays(7)->format('Y-m-d'),
            'time'           => '09:00',
            'duration'       => '60 min',
            'type'           => 'online',
            'interviewers'   => ['Budi', 'Alice'],
            'status'         => 'scheduled',
        ], $override));
    }

    // ------------------------------------------------------------------ index

    public function test_index_returns_list_of_interviews(): void
    {
        $app = $this->makeApplication();
        $this->makeInterview($app->id);
        $this->makeInterview($app->id);

        $this->getJson('/api/interviews')
            ->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonCount(2, 'data');
    }

    // ------------------------------------------------------------------ store

    public function test_store_creates_interview(): void
    {
        $app = $this->makeApplication();

        $response = $this->postJson('/api/interviews', $this->interviewData($app->id));

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.applicantName', 'Test Candidate');

        $this->assertDatabaseHas('interviews', ['applicant_id' => $app->id]);
    }

    public function test_store_requires_applicant_id_date_time_type_interviewers(): void
    {
        $this->postJson('/api/interviews', [])
            ->assertStatus(422)
            ->assertJsonStructure(['errors']);
    }

    public function test_store_rejects_nonexistent_applicant_id(): void
    {
        $this->postJson('/api/interviews', $this->interviewData(
            '00000000-0000-0000-0000-000000000000'
        ))->assertStatus(422);
    }

    // ------------------------------------------------------------------ show

    public function test_show_returns_interview(): void
    {
        $app       = $this->makeApplication();
        $interview = $this->makeInterview($app->id);

        $this->getJson("/api/interviews/{$interview->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $interview->id);
    }

    // ------------------------------------------------------------------ update

    public function test_update_modifies_interview(): void
    {
        $app       = $this->makeApplication();
        $interview = $this->makeInterview($app->id);

        $this->patchJson("/api/interviews/{$interview->id}", ['status' => 'completed'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseHas('interviews', [
            'id'     => $interview->id,
            'status' => 'completed',
        ]);
    }

    // ------------------------------------------------------------------ destroy

    public function test_destroy_deletes_interview(): void
    {
        $app       = $this->makeApplication();
        $interview = $this->makeInterview($app->id);

        $this->deleteJson("/api/interviews/{$interview->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('interviews', ['id' => $interview->id]);
    }
}
