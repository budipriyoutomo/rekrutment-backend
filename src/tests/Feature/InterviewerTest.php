<?php

namespace Tests\Feature;

use App\Domains\Interviewer\Models\Interviewer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InterviewerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
    }

    private function interviewerData(array $override = []): array
    {
        return array_merge([
            'name'       => 'Budi Santoso',
            'role'       => 'HR Manager',
            'department' => 'Human Resource',
            'email'      => 'budi@example.com',
            'phone'      => '08123456789',
            'active'     => true,
        ], $override);
    }

    private function makeInterviewer(array $override = []): Interviewer
    {
        return Interviewer::create($this->interviewerData($override));
    }

    // ------------------------------------------------------------------ index

    public function test_index_returns_list_of_interviewers(): void
    {
        $this->makeInterviewer(['name' => 'Alice']);
        $this->makeInterviewer(['name' => 'Bob', 'email' => 'bob@example.com']);

        $this->getJson('/api/interviewers')
            ->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonCount(2, 'data');
    }

    // ------------------------------------------------------------------ store

    public function test_store_creates_interviewer(): void
    {
        $response = $this->postJson('/api/interviewers', $this->interviewerData());

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.name', 'Budi Santoso');

        $this->assertDatabaseHas('interviewers', ['email' => 'budi@example.com']);
    }

    public function test_store_requires_name_and_role(): void
    {
        $this->postJson('/api/interviewers', [])
            ->assertStatus(422)
            ->assertJsonStructure(['errors']);
    }

    // ------------------------------------------------------------------ show

    public function test_show_returns_interviewer(): void
    {
        $interviewer = $this->makeInterviewer(['name' => 'Dewi']);

        $this->getJson("/api/interviewers/{$interviewer->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Dewi');
    }

    // ------------------------------------------------------------------ update

    public function test_update_modifies_interviewer(): void
    {
        $interviewer = $this->makeInterviewer();

        $this->patchJson("/api/interviewers/{$interviewer->id}", ['name' => 'Updated Name'])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Name');

        $this->assertDatabaseHas('interviewers', ['id' => $interviewer->id, 'name' => 'Updated Name']);
    }

    public function test_update_uses_patch_method(): void
    {
        // Verifikasi bahwa PATCH (bukan PUT) diterima
        $interviewer = $this->makeInterviewer();

        $this->patchJson("/api/interviewers/{$interviewer->id}", ['role' => 'Tech Lead'])
            ->assertStatus(200);
    }

    // ------------------------------------------------------------------ destroy

    public function test_destroy_deletes_interviewer(): void
    {
        $interviewer = $this->makeInterviewer();

        $this->deleteJson("/api/interviewers/{$interviewer->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('interviewers', ['id' => $interviewer->id]);
    }
}
