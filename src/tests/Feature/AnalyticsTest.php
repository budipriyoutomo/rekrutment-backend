<?php

namespace Tests\Feature;

use App\Domains\Application\Models\Application;
use App\Domains\Employee\Models\Employee;
use App\Domains\Employee\Models\OnboardingTask;
use App\Domains\Evaluation\Models\Evaluation;
use App\Domains\Interview\Models\Interview;
use App\Domains\Offer\Models\Offer;
use App\Domains\Preboarding\Models\Preboarding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Fase 6 Offer→Onboarding: metrik offer, time-to-hire, funnel, onboarding di /analytics.
 */
class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
    }

    private function app(string $stage = 'applied', ?Carbon $createdAt = null): Application
    {
        static $n = 0;
        $n++;

        $app = Application::create([
            'personal_info' => ['fullName' => "Kandidat {$n}"],
            'contact_info' => ['email' => "k{$n}@example.com"],
            'stage' => $stage,
        ]);

        if ($createdAt) {
            $app->created_at = $createdAt;
            $app->saveQuietly();
        }

        return $app;
    }

    private function offer(Application $app, string $status, array $override = []): Offer
    {
        static $n = 0;
        $n++;

        return Offer::create(array_merge([
            'number' => 'OFR/2026/10/'.str_pad((string) $n, 4, '0', STR_PAD_LEFT), 'application_id' => $app->id,
            'position' => 'Cook', 'employment_type' => 'full-time', 'base_salary' => 5000000,
            'start_date' => '2026-11-02', 'expires_at' => '2026-10-30', 'status' => $status,
            'sent_at' => $status === Offer::STATUS_DRAFT ? null : now(),
        ], $override));
    }

    private function employee(Application $app, array $override = []): Employee
    {
        return Employee::create(array_merge([
            'employee_number' => 'EMP-'.Str::random(6), 'application_id' => $app->id, 'full_name' => 'X',
            'position' => 'Cook', 'start_date' => now()->toDateString(), 'status' => Employee::STATUS_ONBOARDING,
        ], $override));
    }

    public function test_offer_stats_and_acceptance_rate(): void
    {
        $this->offer($this->app('hired'), Offer::STATUS_ACCEPTED);
        $this->offer($this->app('hired'), Offer::STATUS_ACCEPTED);
        $this->offer($this->app('hired'), Offer::STATUS_ACCEPTED);
        $this->offer($this->app('offer'), Offer::STATUS_DECLINED);
        $this->offer($this->app('offer'), Offer::STATUS_SENT);
        $this->offer($this->app('offer'), Offer::STATUS_WITHDRAWN);

        $this->getJson('/api/analytics')
            ->assertStatus(200)
            ->assertJsonPath('data.offers.accepted', 3)
            ->assertJsonPath('data.offers.declined', 1)
            ->assertJsonPath('data.offers.sent', 1)
            ->assertJsonPath('data.offers.withdrawn', 1)
            ->assertJsonPath('data.offers.acceptance_rate', 75);
    }

    public function test_acceptance_rate_null_without_decided_offers(): void
    {
        $this->offer($this->app('offer'), Offer::STATUS_SENT);

        $this->getJson('/api/analytics')->assertJsonPath('data.offers.acceptance_rate', null);
    }

    public function test_time_to_hire_from_application_to_offer_acceptance(): void
    {
        $this->offer($this->app('hired', Carbon::parse('2026-10-01 09:00')), Offer::STATUS_ACCEPTED, ['responded_at' => Carbon::parse('2026-10-11 15:00')]);
        $this->offer($this->app('hired', Carbon::parse('2026-10-01 09:00')), Offer::STATUS_ACCEPTED, ['responded_at' => Carbon::parse('2026-10-21 08:00')]);
        $this->offer($this->app('hired', Carbon::parse('2026-10-01 09:00')), Offer::STATUS_ACCEPTED, ['responded_at' => Carbon::parse('2026-10-31 08:00')]);
        $this->offer($this->app('offer', Carbon::parse('2026-01-01')), Offer::STATUS_DECLINED, ['responded_at' => now()]);

        $this->getJson('/api/analytics')
            ->assertJsonPath('data.time_to_hire.samples', 3)
            ->assertJsonPath('data.time_to_hire.avg_days', 20)
            ->assertJsonPath('data.time_to_hire.median_days', 20);
    }

    public function test_time_to_hire_empty(): void
    {
        $this->getJson('/api/analytics')
            ->assertJsonPath('data.time_to_hire.samples', 0)
            ->assertJsonPath('data.time_to_hire.avg_days', null);
    }

    public function test_funnel_counts_real_milestones(): void
    {
        $interviewed = $this->app('rejected');
        $offeredDeclined = $this->app('offer');
        $hiredActive = $this->app('hired');
        $draftOnly = $this->app('offer');
        $this->app('applied');

        foreach ([$interviewed, $offeredDeclined, $hiredActive] as $a) {
            Interview::create([
                'applicant_id' => $a->id, 'applicant_name' => 'x', 'position' => 'Cook',
                'date' => '2026-10-10', 'time' => '10:00', 'duration' => '60 min', 'type' => 'online', 'status' => 'completed', 'interviewers' => ['Pak A'],
            ]);
        }
        $this->offer($offeredDeclined, Offer::STATUS_DECLINED);
        $this->offer($hiredActive, Offer::STATUS_ACCEPTED);
        $this->offer($draftOnly, Offer::STATUS_DRAFT);
        $this->employee($hiredActive, ['status' => Employee::STATUS_ACTIVE]);

        $this->getJson('/api/analytics')->assertJsonPath('data.funnel', [
            'applied' => 5, 'interviewed' => 3, 'offered' => 2, 'accepted' => 1, 'onboarded' => 1,
        ]);
    }

    public function test_onboarding_stats(): void
    {
        $mk = fn (string $status) => Preboarding::create([
            'application_id' => $this->app('hired')->id, 'status' => $status,
            'token' => Str::random(64), 'token_expires_at' => now()->addDay(), 'documents' => [],
        ]);
        $mk(Preboarding::STATUS_PENDING);
        $mk(Preboarding::STATUS_NEEDS_REVISION);
        $mk(Preboarding::STATUS_SUBMITTED);
        $verified = $mk(Preboarding::STATUS_VERIFIED);
        $converted = $mk(Preboarding::STATUS_VERIFIED);

        $soon = $this->employee($converted->application, ['preboarding_id' => $converted->id, 'start_date' => now()->addDays(3)->toDateString()]);
        $this->employee($this->app('hired'), ['start_date' => now()->addDays(10)->toDateString()]);
        $this->employee($this->app('hired'), ['status' => Employee::STATUS_ACTIVE, 'start_date' => now()->subMonth()->toDateString()]);

        OnboardingTask::create(['employee_id' => $soon->id, 'title' => 'Lewat', 'due_date' => now()->subDay()->toDateString()]);
        OnboardingTask::create(['employee_id' => $soon->id, 'title' => 'Selesai lewat', 'due_date' => now()->subDay()->toDateString(), 'completed_at' => now()]);
        OnboardingTask::create(['employee_id' => $soon->id, 'title' => 'Nanti', 'due_date' => now()->addDay()->toDateString()]);

        $this->getJson('/api/analytics')->assertJsonPath('data.onboarding', [
            'preboarding_waiting' => 2,
            'preboarding_to_review' => 1,
            'preboarding_to_convert' => 1,
            'employees_onboarding' => 2,
            'starting_this_week' => 1,
            'overdue_tasks' => 1,
        ]);
        $this->assertNotNull($verified);
    }

    public function test_avg_evaluation_score_uses_four_aspects(): void
    {
        $app = $this->app('interview');
        foreach ([[4, 4, 4, 4], [2, 3, 5, 2]] as [$c, $t, $e, $cf]) {
            Evaluation::create([
                'applicant_id' => $app->id, 'applicant_name' => 'x', 'evaluator' => 'y',
                'communication_score' => $c, 'technical_score' => $t, 'experience_score' => $e, 'culture_fit_score' => $cf,
                'recommendation' => 'hire',
            ]);
        }

        $this->getJson('/api/analytics')->assertJsonPath('data.summary.avg_evaluation_score', 3.5);
    }
}
