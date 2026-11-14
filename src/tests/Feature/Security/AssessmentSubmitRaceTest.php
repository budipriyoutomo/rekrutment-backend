<?php

namespace Tests\Feature\Security;

use App\Domains\Application\Models\Application;
use App\Domains\Assessment\Models\Assessment;
use App\Domains\Assessment\Models\AssessmentAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AssessmentSubmitRaceTest extends TestCase
{
    use RefreshDatabase;

    private function startedAssignment(): AssessmentAssignment
    {
        $this->actingAsAdmin();

        $assessmentId = $this->postJson('/api/assessments', [
            'title' => 'Tes Logika',
            'duration_minutes' => 30,
            'passing_score' => 70,
            'questions' => [[
                'question' => '2 + 2 = ?',
                'options' => [['key' => 'A', 'text' => '3'], ['key' => 'B', 'text' => '4']],
                'correct_answer' => 'B',
                'score' => 1,
            ]],
        ])->assertStatus(200)->json('data.id');

        $application = Application::create([
            'personal_info' => ['fullName' => 'Budi Kandidat'],
            'contact_info' => ['email' => 'budi@example.com'],
            'stage' => 'screening',
        ]);

        $assignmentId = $this->postJson("/api/assessments/{$assessmentId}/assign", [
            'application_id' => $application->id,
        ])->assertStatus(200)->json('data.id');

        $assignment = AssessmentAssignment::findOrFail($assignmentId);
        $this->postJson("/api/assessment/{$assignment->token}/start")->assertStatus(200);

        return $assignment->fresh();
    }

    public function test_assessment_cannot_be_submitted_twice(): void
    {
        $assignment = $this->startedAssignment();
        $questionId = Assessment::with('questions')->find($assignment->assessment_id)->questions->first()->id;

        // Kiriman pertama (tab lain) selesai dinilai tepat setelah kiriman ini
        // membaca assignment — sebelum sempat menyimpan.
        $fired = false;
        AssessmentAssignment::retrieved(function (AssessmentAssignment $a) use (&$fired) {
            if ($fired) {
                return;
            }
            $fired = true;
            DB::table('assessment_assignments')->where('id', $a->id)->update([
                'status' => 'graded',
                'score' => 100,
                'passed' => true,
                'answers' => json_encode(['pertama' => 'B']),
                'submitted_at' => now(),
            ]);
        });

        $this->postJson("/api/assessment/{$assignment->token}/submit", [
            'answers' => [$questionId => 'A'],
        ])->assertStatus(409);

        $fresh = $assignment->fresh();
        $this->assertEquals(100, $fresh->score);
        $this->assertSame(['pertama' => 'B'], $fresh->answers);
    }

    public function test_second_sequential_submit_is_rejected(): void
    {
        $assignment = $this->startedAssignment();
        $questionId = Assessment::with('questions')->find($assignment->assessment_id)->questions->first()->id;

        $this->postJson("/api/assessment/{$assignment->token}/submit", ['answers' => [$questionId => 'B']])
            ->assertStatus(200);
        $this->postJson("/api/assessment/{$assignment->token}/submit", ['answers' => [$questionId => 'A']])
            ->assertStatus(409);

        $this->assertEquals(100, $assignment->fresh()->score);
    }
}
