<?php

namespace Tests\Feature;

use App\Domains\Application\Models\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationBulkStageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
    }

    private function makeApplicant(string $name, string $stage = 'applied'): Application
    {
        return Application::create([
            'personal_info' => ['fullName' => $name],
            'contact_info' => ['email' => strtolower($name).'@example.com', 'phone' => '081'],
            'additional_info' => ['positionApplied' => 'Frontend Dev'],
            'stage' => $stage,
        ]);
    }

    public function test_bulk_stage_moves_selected_applicants(): void
    {
        $a = $this->makeApplicant('Andi');
        $b = $this->makeApplicant('Budi');
        $c = $this->makeApplicant('Citra');

        $this->patchJson('/api/applicants/bulk-stage', [
            'ids' => [$a->id, $b->id],
            'stage' => 'screening',
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.updated', 2);

        $this->assertSame('screening', $a->refresh()->stage);
        $this->assertSame('screening', $b->refresh()->stage);
        $this->assertSame('applied', $c->refresh()->stage);
    }

    public function test_bulk_stage_supports_rejected(): void
    {
        $a = $this->makeApplicant('Andi');

        $this->patchJson('/api/applicants/bulk-stage', [
            'ids' => [$a->id],
            'stage' => 'rejected',
        ])->assertStatus(200);

        $this->assertSame('rejected', $a->refresh()->stage);
    }

    public function test_bulk_stage_ignores_unknown_ids(): void
    {
        $a = $this->makeApplicant('Andi');

        $this->patchJson('/api/applicants/bulk-stage', [
            'ids' => [$a->id, '00000000-0000-0000-0000-000000000000'],
            'stage' => 'screening',
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.updated', 1);
    }

    public function test_bulk_stage_rejects_empty_ids(): void
    {
        $this->patchJson('/api/applicants/bulk-stage', [
            'ids' => [],
            'stage' => 'screening',
        ])->assertStatus(422);
    }

    public function test_bulk_stage_rejects_invalid_stage(): void
    {
        $a = $this->makeApplicant('Andi');

        $this->patchJson('/api/applicants/bulk-stage', [
            'ids' => [$a->id],
            'stage' => 'bukan-stage',
        ])->assertStatus(422);
    }
}
