<?php

namespace Tests\Feature;

use App\Domains\JobRequest\Models\JobRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
    }

    private function jobRequestData(array $override = []): array
    {
        return array_merge([
            'title'           => 'Backend Developer',
            'department'      => 'Engineering',
            'location'        => 'Jakarta',
            'employment_type' => 'full-time',
            'headcount'       => 2,
            'justification'   => 'Need more engineers.',
            'requested_by'    => 'Manager A',
            'priority'        => 'normal',
        ], $override);
    }

    private function makeJobRequest(array $override = []): JobRequest
    {
        return JobRequest::create($this->jobRequestData($override));
    }

    // ------------------------------------------------------------------ index

    public function test_index_returns_list_of_job_requests(): void
    {
        $this->makeJobRequest(['title' => 'Job A']);
        $this->makeJobRequest(['title' => 'Job B']);

        $this->getJson('/api/job-requests')
            ->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_index_filters_by_status(): void
    {
        $this->makeJobRequest(['status' => 'pending']);
        $this->makeJobRequest(['status' => 'approved']);

        $this->getJson('/api/job-requests?status=pending')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_index_filters_by_priority(): void
    {
        $this->makeJobRequest(['priority' => 'high']);
        $this->makeJobRequest(['priority' => 'normal']);

        $this->getJson('/api/job-requests?priority=high')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    // ------------------------------------------------------------------ store

    public function test_store_creates_job_request_with_pending_status(): void
    {
        $response = $this->postJson('/api/job-requests', $this->jobRequestData());

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.title', 'Backend Developer')
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('job_requests', ['title' => 'Backend Developer']);
    }

    public function test_store_requires_title(): void
    {
        $this->postJson('/api/job-requests', [])
            ->assertStatus(422)
            ->assertJsonStructure(['errors']);
    }

    public function test_store_rejects_invalid_employment_type(): void
    {
        $this->postJson('/api/job-requests', $this->jobRequestData([
            'employment_type' => 'casual',
        ]))->assertStatus(422);
    }

    public function test_store_accepts_requirements_array(): void
    {
        $response = $this->postJson('/api/job-requests', $this->jobRequestData([
            'requirements' => ['PHP 8+', 'Laravel', 'PostgreSQL'],
        ]));

        $response->assertStatus(200);
        $this->assertDatabaseHas('job_requests', ['title' => 'Backend Developer']);
    }

    // ------------------------------------------------------------------ show

    public function test_show_returns_job_request(): void
    {
        $jr = $this->makeJobRequest(['title' => 'Data Engineer']);

        $this->getJson("/api/job-requests/{$jr->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.title', 'Data Engineer');
    }

    // ------------------------------------------------------------------ update

    public function test_update_modifies_job_request(): void
    {
        $jr = $this->makeJobRequest();

        $this->putJson("/api/job-requests/{$jr->id}", array_merge(
            $this->jobRequestData(),
            ['title' => 'Senior Backend Dev', 'headcount' => 3]
        ))->assertStatus(200)
            ->assertJsonPath('data.title', 'Senior Backend Dev')
            ->assertJsonPath('data.headcount', 3);

        $this->assertDatabaseHas('job_requests', [
            'id'    => $jr->id,
            'title' => 'Senior Backend Dev',
        ]);
    }

    public function test_patch_also_modifies_job_request(): void
    {
        $jr = $this->makeJobRequest();

        $this->patchJson("/api/job-requests/{$jr->id}", ['priority' => 'high'])
            ->assertStatus(200)
            ->assertJsonPath('data.priority', 'high');
    }

    // ------------------------------------------------------------------ approve

    public function test_approve_sets_status_to_approved(): void
    {
        $jr = $this->makeJobRequest(['status' => 'pending']);

        $this->postJson("/api/job-requests/{$jr->id}/approve", [
            'reviewer_notes' => 'Looks good!',
        ])->assertStatus(200)
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('job_requests', [
            'id'     => $jr->id,
            'status' => 'approved',
        ]);
    }

    // ------------------------------------------------------------------ reject

    public function test_reject_sets_status_to_rejected(): void
    {
        $jr = $this->makeJobRequest(['status' => 'pending']);

        $this->postJson("/api/job-requests/{$jr->id}/reject", [
            'reviewer_notes' => 'Budget not available.',
        ])->assertStatus(200)
            ->assertJsonPath('data.status', 'rejected');

        $this->assertDatabaseHas('job_requests', [
            'id'     => $jr->id,
            'status' => 'rejected',
        ]);
    }

    // ------------------------------------------------------------------ destroy

    public function test_destroy_deletes_job_request(): void
    {
        $jr = $this->makeJobRequest();

        $this->deleteJson("/api/job-requests/{$jr->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('job_requests', ['id' => $jr->id]);
    }
}
