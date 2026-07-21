<?php

namespace Tests\Feature;

use App\Domains\Application\Models\Application;
use App\Domains\Evaluation\Models\Evaluation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EvaluationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
    }

    private function makeApplication(): Application
    {
        return Application::create([
            'personal_info'   => ['fullName' => 'Candidate Name'],
            'contact_info'    => ['email' => 'cand@example.com', 'phone' => '081'],
            'additional_info' => ['positionApplied' => 'Frontend Dev'],
        ]);
    }

    private function evalData(string $applicantId, array $override = []): array
    {
        return array_merge([
            'applicant_id'       => $applicantId,
            'applicant_name'     => 'Candidate Name',
            'position'           => 'Frontend Dev',
            'evaluator'          => 'HR Manager',
            'date'               => now()->format('Y-m-d'),
            'communication_score' => 4,
            'technical_score'    => 3,
            'experience_score'   => 4,
            'culture_fit_score'  => 5,
            'recommendation'     => 'hire',
        ], $override);
    }

    private function makeEvaluation(string $applicantId, array $override = []): Evaluation
    {
        return Evaluation::create($this->evalData($applicantId, $override));
    }

    // ------------------------------------------------------------------ index

    public function test_index_returns_list_of_evaluations(): void
    {
        $app = $this->makeApplication();
        $this->makeEvaluation($app->id);
        $this->makeEvaluation($app->id, ['evaluator' => 'Tech Lead']);

        $this->getJson('/api/evaluations')
            ->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonCount(2, 'data');
    }

    // ------------------------------------------------------------------ store

    public function test_store_creates_evaluation(): void
    {
        $app = $this->makeApplication();

        $response = $this->postJson('/api/evaluations', $this->evalData($app->id));

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.evaluator', 'HR Manager');

        $this->assertDatabaseHas('evaluations', ['applicant_id' => $app->id]);
    }

    public function test_store_requires_mandatory_fields(): void
    {
        $this->postJson('/api/evaluations', [])
            ->assertStatus(422)
            ->assertJsonStructure(['errors']);
    }

    public function test_store_rejects_score_out_of_range(): void
    {
        $app = $this->makeApplication();

        $this->postJson('/api/evaluations', $this->evalData($app->id, [
            'communication_score' => 6, // max is 5
        ]))->assertStatus(422);
    }

    public function test_store_rejects_invalid_recommendation(): void
    {
        $app = $this->makeApplication();

        $this->postJson('/api/evaluations', $this->evalData($app->id, [
            'recommendation' => 'maybe',
        ]))->assertStatus(422);
    }

    // ------------------------------------------------------------------ show

    public function test_show_returns_evaluation(): void
    {
        $app  = $this->makeApplication();
        $eval = $this->makeEvaluation($app->id);

        $this->getJson("/api/evaluations/{$eval->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $eval->id);
    }

    // ------------------------------------------------------------------ update

    public function test_update_modifies_evaluation(): void
    {
        $app  = $this->makeApplication();
        $eval = $this->makeEvaluation($app->id);

        $this->patchJson("/api/evaluations/{$eval->id}", ['recommendation' => 'strong_hire'])
            ->assertStatus(200)
            ->assertJsonPath('data.recommendation', 'strong_hire');

        $this->assertDatabaseHas('evaluations', [
            'id'             => $eval->id,
            'recommendation' => 'strong_hire',
        ]);
    }

    // ------------------------------------------------------------------ destroy

    public function test_destroy_deletes_evaluation(): void
    {
        $app  = $this->makeApplication();
        $eval = $this->makeEvaluation($app->id);

        $this->deleteJson("/api/evaluations/{$eval->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('evaluations', ['id' => $eval->id]);
    }
}
