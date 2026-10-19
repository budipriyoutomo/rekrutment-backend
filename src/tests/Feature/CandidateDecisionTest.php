<?php

namespace Tests\Feature;

use App\Domains\Application\Mail\ApplicationRejectedMail;
use App\Domains\Application\Models\Application;
use App\Domains\Evaluation\Models\Evaluation;
use App\Domains\Setting\Models\CompanySetting;
use App\Domains\Vacancy\Models\Vacancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Fase 2 Offer→Onboarding: ringkasan evaluasi & keputusan tolak pasca-interview.
 */
class CandidateDecisionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
        Mail::fake();
    }

    private function makeApplication(array $override = []): Application
    {
        return Application::create(array_merge([
            'personal_info' => ['fullName' => 'Siti Kandidat'],
            'contact_info' => ['email' => 'siti@example.com', 'phone' => '081'],
            'additional_info' => ['positionApplied' => 'Bandung - Cook'],
            'stage' => 'interview',
        ], $override));
    }

    private function makeEvaluation(Application $app, array $override = []): Evaluation
    {
        return Evaluation::create(array_merge([
            'applicant_id' => $app->id,
            'applicant_name' => 'Siti Kandidat',
            'evaluator' => 'Pak Interviewer',
            'date' => '2026-10-10',
            'communication_score' => 4,
            'technical_score' => 4,
            'experience_score' => 4,
            'culture_fit_score' => 4,
            'recommendation' => 'hire',
        ], $override));
    }

    // ------------------------------------------------------- evaluation summary

    public function test_summary_aggregates_scores_and_recommendations(): void
    {
        $app = $this->makeApplication();
        $this->makeEvaluation($app, ['recommendation' => 'strong_hire']);
        $this->makeEvaluation($app, [
            'communication_score' => 2,
            'technical_score' => 3,
            'experience_score' => 5,
            'culture_fit_score' => 1,
            'recommendation' => 'hold',
        ]);
        $this->makeEvaluation($this->makeApplication(['contact_info' => ['email' => 'lain@example.com']]));

        $this->getJson("/api/applicants/{$app->id}/evaluation-summary")
            ->assertStatus(200)
            ->assertJsonPath('data.count', 2)
            ->assertJsonPath('data.averageScores.communication', 3)
            ->assertJsonPath('data.averageScores.technical', 3.5)
            ->assertJsonPath('data.averageScores.experience', 4.5)
            ->assertJsonPath('data.averageScores.cultureFit', 2.5)
            ->assertJsonPath('data.averageScores.overall', 3.4)
            ->assertJsonPath('data.recommendations', ['strong_hire' => 1, 'hire' => 0, 'hold' => 1, 'reject' => 0])
            ->assertJsonCount(2, 'data.evaluations')
            ->assertJsonPath('data.evaluations.0.evaluator', 'Pak Interviewer');
    }

    public function test_summary_without_evaluations_returns_nulls(): void
    {
        $app = $this->makeApplication();

        $this->getJson("/api/applicants/{$app->id}/evaluation-summary")
            ->assertStatus(200)
            ->assertJsonPath('data.count', 0)
            ->assertJsonPath('data.averageScores.overall', null)
            ->assertJsonPath('data.recommendations.reject', 0)
            ->assertJsonCount(0, 'data.evaluations');
    }

    public function test_summary_for_unknown_applicant_is_404(): void
    {
        $this->getJson('/api/applicants/9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d/evaluation-summary')
            ->assertStatus(404);
    }

    // ------------------------------------------------------------------ reject

    public function test_reject_with_email_queues_mail_and_records_reason(): void
    {
        $app = $this->makeApplication();

        $this->postJson("/api/applicants/{$app->id}/reject", [
            'reason' => 'Skor teknis di bawah standar',
            'send_email' => true,
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.emailQueued', true)
            ->assertJsonPath('data.application.stage', 'rejected')
            ->assertJsonPath('data.application.rejection.reason', 'Skor teknis di bawah standar');

        $app->refresh();
        $this->assertSame('rejected', $app->stage);
        $this->assertNotNull($app->rejected_at);
        $this->assertNotNull($app->rejection_notified_at);

        Mail::assertQueued(ApplicationRejectedMail::class, fn ($mail) => $mail->hasTo('siti@example.com'));
    }

    public function test_reject_sends_email_by_default(): void
    {
        $app = $this->makeApplication();

        $this->postJson("/api/applicants/{$app->id}/reject")->assertStatus(200);

        Mail::assertQueued(ApplicationRejectedMail::class);
    }

    public function test_reject_without_email_does_not_queue_mail(): void
    {
        $app = $this->makeApplication();

        $this->postJson("/api/applicants/{$app->id}/reject", ['send_email' => false])
            ->assertStatus(200)
            ->assertJsonPath('data.emailQueued', false);

        $this->assertNull($app->fresh()->rejection_notified_at);
        $this->assertNull($app->fresh()->rejection_reason);
        Mail::assertNothingQueued();
    }

    public function test_reject_candidate_without_email_still_rejects(): void
    {
        $app = $this->makeApplication(['contact_info' => ['phone' => '081']]);

        $this->postJson("/api/applicants/{$app->id}/reject", ['send_email' => true])
            ->assertStatus(200)
            ->assertJsonPath('data.emailQueued', false);

        $this->assertSame('rejected', $app->fresh()->stage);
        Mail::assertNothingQueued();
    }

    public function test_cannot_reject_twice_or_reject_hired(): void
    {
        $rejected = $this->makeApplication(['stage' => 'rejected']);
        $hired = $this->makeApplication(['stage' => 'hired', 'contact_info' => ['email' => 'h@example.com']]);

        $this->postJson("/api/applicants/{$rejected->id}/reject")->assertStatus(422);
        $this->postJson("/api/applicants/{$hired->id}/reject")->assertStatus(422);
        Mail::assertNothingQueued();
    }

    public function test_rejection_info_hidden_once_candidate_moves_on(): void
    {
        $app = $this->makeApplication();
        $this->postJson("/api/applicants/{$app->id}/reject", ['reason' => 'x', 'send_email' => false]);

        $this->patchJson("/api/applicants/{$app->id}/stage", ['stage' => 'interview'])->assertStatus(200);

        $this->getJson("/api/applicants/{$app->id}")->assertJsonPath('data.rejection', null);
    }

    public function test_rejection_mail_uses_vacancy_title_and_company_name_without_reason(): void
    {
        CompanySetting::set('company_name', 'Altima Group');
        $vacancy = Vacancy::create([
            'title' => 'Bandung - Head Chef', 'department' => 'Kitchen', 'location' => 'Bandung - RIAU',
            'type' => 'full-time', 'status' => 'open', 'description' => 'x',
        ]);
        $app = $this->makeApplication(['vacancy_id' => $vacancy->id]);
        $app->rejection_reason = 'RAHASIA INTERNAL';

        $mail = new ApplicationRejectedMail($app->load('vacancy'));

        $mail->assertHasSubject('Informasi Hasil Seleksi – Bandung - Head Chef');
        $mail->assertSeeInHtml('Altima Group');
        $mail->assertSeeInHtml('Siti Kandidat');
        $mail->assertDontSeeInHtml('RAHASIA INTERNAL');
    }
}
