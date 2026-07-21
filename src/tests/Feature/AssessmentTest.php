<?php

namespace Tests\Feature;

use App\Domains\Application\Models\Application;
use App\Domains\Assessment\Mail\AssessmentInvitationMail;
use App\Domains\Assessment\Models\Assessment;
use App\Domains\Assessment\Models\AssessmentAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AssessmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
        Mail::fake();
    }

    private function makeApplication(): Application
    {
        return Application::create([
            'personal_info'   => ['fullName' => 'Budi Kandidat'],
            'contact_info'    => ['email' => 'budi@example.com', 'phone' => '081'],
            'additional_info' => ['positionApplied' => 'Backend Dev'],
            'stage'           => 'screening',
        ]);
    }

    /** Paket 2 soal: bobot 1 dan 3, passing 70%. */
    private function assessmentPayload(array $override = []): array
    {
        return array_merge([
            'title'            => 'Tes Logika Dasar',
            'description'      => 'Tes pilihan ganda',
            'duration_minutes' => 30,
            'passing_score'    => 70,
            'questions'        => [
                [
                    'question'       => '2 + 2 = ?',
                    'options'        => [
                        ['key' => 'A', 'text' => '3'],
                        ['key' => 'B', 'text' => '4'],
                    ],
                    'correct_answer' => 'B',
                    'score'          => 1,
                ],
                [
                    'question'       => 'Ibu kota Indonesia?',
                    'options'        => [
                        ['key' => 'A', 'text' => 'Jakarta'],
                        ['key' => 'B', 'text' => 'Bandung'],
                    ],
                    'correct_answer' => 'A',
                    'score'          => 3,
                ],
            ],
        ], $override);
    }

    private function makeAssessment(array $override = []): Assessment
    {
        $id = $this->postJson('/api/assessments', $this->assessmentPayload($override))
            ->assertStatus(200)
            ->json('data.id');

        return Assessment::with('questions')->findOrFail($id);
    }

    // -------------------------------------------------------------- paket tes

    public function test_store_creates_assessment_with_questions(): void
    {
        $this->postJson('/api/assessments', $this->assessmentPayload())
            ->assertStatus(200)
            ->assertJsonPath('data.title', 'Tes Logika Dasar')
            ->assertJsonPath('data.passingScore', 70)
            ->assertJsonCount(2, 'data.questions');

        $this->assertDatabaseCount('assessment_questions', 2);
    }

    public function test_store_rejects_correct_answer_outside_options(): void
    {
        $payload = $this->assessmentPayload();
        $payload['questions'][0]['correct_answer'] = 'Z';

        $this->postJson('/api/assessments', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('questions.0.correct_answer');
    }

    public function test_update_syncs_questions(): void
    {
        $assessment = $this->makeAssessment();
        $keep       = $assessment->questions->first();

        $this->patchJson("/api/assessments/{$assessment->id}", [
            'questions' => [[
                'id'             => $keep->id,
                'question'       => '2 + 2 = ? (revisi)',
                'options'        => [
                    ['key' => 'A', 'text' => '3'],
                    ['key' => 'B', 'text' => '4'],
                ],
                'correct_answer' => 'B',
                'score'          => 2,
            ]],
        ])->assertStatus(200)->assertJsonCount(1, 'data.questions');

        // Soal yang tidak dikirim ulang ikut terhapus.
        $this->assertDatabaseCount('assessment_questions', 1);
        $this->assertDatabaseHas('assessment_questions', [
            'id'       => $keep->id,
            'question' => '2 + 2 = ? (revisi)',
            'score'    => 2,
        ]);
    }

    public function test_update_without_questions_field_keeps_question_bank(): void
    {
        $assessment = $this->makeAssessment();

        $this->patchJson("/api/assessments/{$assessment->id}", ['title' => 'Judul Baru'])
            ->assertStatus(200)
            ->assertJsonPath('data.title', 'Judul Baru');

        $this->assertDatabaseCount('assessment_questions', 2);
    }

    // -------------------------------------------------------------- penugasan

    public function test_assign_sends_email_and_moves_candidate_to_assessment_stage(): void
    {
        $assessment  = $this->makeAssessment();
        $application = $this->makeApplication();

        $this->postJson("/api/assessments/{$assessment->id}/assign", [
            'application_id' => $application->id,
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'sent');

        Mail::assertSent(AssessmentInvitationMail::class);

        $this->assertDatabaseHas('applications', [
            'id'    => $application->id,
            'stage' => 'assessment',
        ]);
    }

    public function test_assign_rejects_inactive_assessment(): void
    {
        $assessment  = $this->makeAssessment(['is_active' => false]);
        $application = $this->makeApplication();

        $this->postJson("/api/assessments/{$assessment->id}/assign", [
            'application_id' => $application->id,
        ])->assertStatus(422);

        Mail::assertNothingSent();
        $this->assertDatabaseHas('applications', [
            'id'    => $application->id,
            'stage' => 'screening',
        ]);
    }

    public function test_delete_is_blocked_once_assessment_has_been_assigned(): void
    {
        $assignment = $this->assign();

        $this->deleteJson("/api/assessments/{$assignment->assessment_id}")
            ->assertStatus(409);

        $this->assertDatabaseHas('assessments', ['id' => $assignment->assessment_id]);
    }

    // ------------------------------------------------------- pengerjaan publik

    private function assign(): AssessmentAssignment
    {
        $assessment  = $this->makeAssessment();
        $application = $this->makeApplication();

        $id = $this->postJson("/api/assessments/{$assessment->id}/assign", [
            'application_id' => $application->id,
        ])->assertStatus(200)->json('data.id');

        return AssessmentAssignment::findOrFail($id);
    }

    public function test_validate_token_does_not_leak_questions_before_start(): void
    {
        $assignment = $this->assign();

        $response = $this->getJson("/api/assessment/{$assignment->token}")
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Budi Kandidat')
            ->assertJsonPath('data.totalQuestions', 2);

        $this->assertArrayNotHasKey('questions', $response->json('data'));
    }

    public function test_start_returns_questions_without_correct_answers(): void
    {
        $assignment = $this->assign();

        $response = $this->postJson("/api/assessment/{$assignment->token}/start")
            ->assertStatus(200)
            ->assertJsonCount(2, 'data.questions');

        foreach ($response->json('data.questions') as $question) {
            $this->assertArrayNotHasKey('correctAnswer', $question);
            $this->assertArrayNotHasKey('correct_answer', $question);
            $this->assertArrayNotHasKey('score', $question);
        }

        $this->assertDatabaseHas('assessment_assignments', [
            'id'     => $assignment->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_start_is_idempotent_and_does_not_reset_timer(): void
    {
        $assignment = $this->assign();

        $first = $this->postJson("/api/assessment/{$assignment->token}/start")
            ->assertStatus(200)->json('data.startedAt');

        $second = $this->postJson("/api/assessment/{$assignment->token}/start")
            ->assertStatus(200)->json('data.startedAt');

        $this->assertSame($first, $second);
    }

    public function test_submit_auto_grades_and_marks_passed(): void
    {
        $assignment = $this->assign();
        $this->postJson("/api/assessment/{$assignment->token}/start");

        $questions = $assignment->assessment->questions()->orderBy('order')->get();

        // Kedua soal benar → 4/4 = 100% → lulus.
        $this->postJson("/api/assessment/{$assignment->token}/submit", [
            'answers' => [
                $questions[0]->id => 'B',
                $questions[1]->id => 'A',
            ],
        ])->assertStatus(200);

        $assignment->refresh();

        $this->assertSame('graded', $assignment->status);
        $this->assertEquals(100.0, $assignment->score);
        $this->assertTrue($assignment->passed);
        $this->assertNotNull($assignment->submitted_at);
    }

    public function test_submit_weights_questions_by_score_and_marks_failed(): void
    {
        $assignment = $this->assign();
        $this->postJson("/api/assessment/{$assignment->token}/start");

        $questions = $assignment->assessment->questions()->orderBy('order')->get();

        // Hanya soal bobot 1 yang benar → 1/4 = 25% → di bawah passing 70%.
        $this->postJson("/api/assessment/{$assignment->token}/submit", [
            'answers' => [
                $questions[0]->id => 'B',
                $questions[1]->id => 'B',
            ],
        ])->assertStatus(200);

        $assignment->refresh();

        $this->assertEquals(25.0, $assignment->score);
        $this->assertFalse($assignment->passed);
    }

    public function test_unanswered_questions_count_as_wrong(): void
    {
        $assignment = $this->assign();
        $this->postJson("/api/assessment/{$assignment->token}/start");

        $this->postJson("/api/assessment/{$assignment->token}/submit", ['answers' => []])
            ->assertStatus(200);

        $assignment->refresh();

        $this->assertEquals(0.0, $assignment->score);
        $this->assertFalse($assignment->passed);
    }

    public function test_submit_response_hides_score_from_candidate(): void
    {
        $assignment = $this->assign();
        $this->postJson("/api/assessment/{$assignment->token}/start");

        $response = $this->postJson("/api/assessment/{$assignment->token}/submit", ['answers' => []])
            ->assertStatus(200);

        $this->assertNull($response->json('data'));
    }

    public function test_cannot_submit_before_starting(): void
    {
        $assignment = $this->assign();

        $this->postJson("/api/assessment/{$assignment->token}/submit", ['answers' => []])
            ->assertStatus(422);
    }

    public function test_cannot_take_the_same_test_twice(): void
    {
        $assignment = $this->assign();
        $this->postJson("/api/assessment/{$assignment->token}/start");
        $this->postJson("/api/assessment/{$assignment->token}/submit", ['answers' => []]);

        $this->getJson("/api/assessment/{$assignment->token}")->assertStatus(409);
        $this->postJson("/api/assessment/{$assignment->token}/submit", ['answers' => []])
            ->assertStatus(409);
    }

    public function test_expired_token_is_rejected(): void
    {
        $assignment = $this->assign();
        $assignment->update(['expires_at' => now()->subDay()]);

        $this->getJson("/api/assessment/{$assignment->token}")->assertStatus(410);
        $this->postJson("/api/assessment/{$assignment->token}/start")->assertStatus(410);
    }

    public function test_submit_after_deadline_is_rejected(): void
    {
        $assignment = $this->assign();
        $this->postJson("/api/assessment/{$assignment->token}/start");

        // Durasi 30 menit + toleransi 2 menit; mundurkan waktu mulai jauh melewatinya.
        $assignment->update(['started_at' => now()->subMinutes(45)]);

        $this->postJson("/api/assessment/{$assignment->token}/submit", ['answers' => []])
            ->assertStatus(422);

        $this->assertSame('in_progress', $assignment->refresh()->status);
    }

    public function test_unknown_token_returns_404(): void
    {
        $this->getJson('/api/assessment/token-yang-tidak-ada')->assertStatus(404);
    }

    // ------------------------------------------- versioning soal (snapshot)

    public function test_assign_freezes_questions_snapshot(): void
    {
        $assignment = $this->assign();

        $snapshot = $assignment->questions_snapshot;

        $this->assertIsArray($snapshot);
        $this->assertCount(2, $snapshot);
        $this->assertSame('2 + 2 = ?', $snapshot[0]['question']);
        $this->assertSame('B', $snapshot[0]['correct_answer']);
        $this->assertSame(1, $snapshot[0]['score']);
        $this->assertSame(3, $snapshot[1]['score']);
    }

    /**
     * HR mengedit paket saat kandidat sedang mengerjakan: kunci jawaban diubah
     * dan satu soal dihapus. Kandidat tetap dinilai atas versi yang ia kerjakan,
     * bukan versi baru — kalau tidak, jawaban benar bisa dinilai 0.
     */
    public function test_editing_package_mid_test_does_not_affect_running_candidate(): void
    {
        $assessment  = $this->makeAssessment();
        $application = $this->makeApplication();

        $assignmentId = $this->postJson("/api/assessments/{$assessment->id}/assign", [
            'application_id' => $application->id,
        ])->assertStatus(200)->json('data.id');

        $assignment = AssessmentAssignment::findOrFail($assignmentId);
        $q1         = $assessment->questions->firstWhere('question', '2 + 2 = ?');
        $q2         = $assessment->questions->firstWhere('question', 'Ibu kota Indonesia?');

        $this->postJson("/api/assessment/{$assignment->token}/start")
            ->assertStatus(200)
            ->assertJsonCount(2, 'data.questions');

        // Kunci soal 1 diubah B -> A, soal 2 dibuang dari paket.
        $this->patchJson("/api/assessments/{$assessment->id}", [
            'questions' => [
                [
                    'id'             => $q1->id,
                    'question'       => '2 + 2 = ?',
                    'options'        => [['key' => 'A', 'text' => '3'], ['key' => 'B', 'text' => '4']],
                    'correct_answer' => 'A',
                    'score'          => 1,
                ],
            ],
        ])->assertStatus(200);

        // Soal kandidat tidak ikut berubah meski paket sudah dipangkas.
        $this->postJson("/api/assessment/{$assignment->token}/start")
            ->assertStatus(200)
            ->assertJsonCount(2, 'data.questions');

        $this->postJson("/api/assessment/{$assignment->token}/submit", [
            'answers' => [$q1->id => 'B', $q2->id => 'A'],
        ])->assertStatus(200);

        $graded = $assignment->refresh();

        $this->assertSame(100.0, (float) $graded->score);
        $this->assertTrue($graded->passed);
    }

    public function test_grading_falls_back_to_live_questions_when_snapshot_absent(): void
    {
        $assignment = $this->assign();

        // Assignment lama (dibuat sebelum fitur snapshot) tidak punya snapshot.
        $assignment->forceFill(['questions_snapshot' => null])->save();

        $questions = $assignment->assessment->questions;

        $this->postJson("/api/assessment/{$assignment->token}/start")
            ->assertStatus(200)
            ->assertJsonCount(2, 'data.questions');

        $this->postJson("/api/assessment/{$assignment->token}/submit", [
            'answers' => [
                $questions[0]->id => $questions[0]->correct_answer,
                $questions[1]->id => $questions[1]->correct_answer,
            ],
        ])->assertStatus(200);

        $this->assertSame(100.0, (float) $assignment->refresh()->score);
    }

    // ------------------------------------------------------------ hasil (HR)

    public function test_hr_can_list_assignment_results(): void
    {
        $assignment = $this->assign();
        $this->postJson("/api/assessment/{$assignment->token}/start");
        $this->postJson("/api/assessment/{$assignment->token}/submit", ['answers' => []]);

        $this->getJson('/api/assessment-assignments')
            ->assertStatus(200)
            ->assertJsonPath('data.0.status', 'graded')
            ->assertJsonPath('data.0.passed', false)
            ->assertJsonPath('data.0.applicant.name', 'Budi Kandidat');
    }

    public function test_assessment_endpoints_require_permission(): void
    {
        $this->actingAsUserWithPermissions(['interviews']);

        $this->getJson('/api/assessments')->assertStatus(403);
    }
}
