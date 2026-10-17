<?php

namespace Tests\Feature;

use App\Domains\Application\Models\Application;
use App\Domains\JobRequest\Models\JobRequest;
use App\Domains\Vacancy\Models\Vacancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 1 Offer→Onboarding: relasi lamaran ↔ lowongan ↔ job request dan headcount.
 */
class VacancyLinkTest extends TestCase
{
    use RefreshDatabase;

    private function makeVacancy(array $override = []): Vacancy
    {
        return Vacancy::create(array_merge([
            'title'       => 'Bandung - Cook',
            'department'  => 'Kitchen',
            'location'    => 'Bandung - KARNIVOR - JL. RIAU',
            'type'        => 'full-time',
            'status'      => 'open',
            'description' => 'Masak.',
        ], $override));
    }

    private function makeApplication(array $override = []): Application
    {
        static $n = 0;
        $n++;

        return Application::create(array_merge([
            'personal_info'   => ['fullName' => "Pelamar $n"],
            'contact_info'    => ['email' => "pelamar$n@example.com", 'phone' => '081'],
            'additional_info' => ['positionApplied' => 'Bandung - Cook'],
            'stage'           => 'offer',
        ], $override));
    }

    private function submitPayload(array $additionalInfo, ?string $vacancyId = null): array
    {
        return array_filter([
            'personalInfo'   => ['fullName' => 'Budi Santoso'],
            'contactInfo'    => ['email' => 'budi@example.com', 'phone' => '08123456789'],
            'education'      => [],
            'certifications' => [],
            'workExperience' => [],
            'parentInfo'     => [],
            'spouseInfo'     => [],
            'additionalInfo' => $additionalInfo,
            'vacancyId'      => $vacancyId,
        ], fn ($v) => $v !== null);
    }

    private function submittedApplication(): Application
    {
        return Application::where('contact_info->email', 'budi@example.com')->firstOrFail();
    }

    // ------------------------------------------------------------ submit link

    public function test_submit_auto_links_open_vacancy_with_matching_title_preferring_location(): void
    {
        $this->makeVacancy(['location' => 'Bandung - LAIN']);
        $sameLocation = $this->makeVacancy();
        $this->makeVacancy(['title' => 'Bandung - Waiter']);

        $this->postJson('/api/applications', $this->submitPayload([
            'positionApplied' => 'Bandung - Cook',
            'workLocation'    => 'Bandung - KARNIVOR - JL. RIAU',
        ]))->assertStatus(200);

        $this->assertSame($sameLocation->id, $this->submittedApplication()->vacancy_id);
    }

    public function test_submit_ignores_closed_vacancies(): void
    {
        $this->makeVacancy(['status' => 'closed']);

        $this->postJson('/api/applications', $this->submitPayload([
            'positionApplied' => 'Bandung - Cook',
        ]))->assertStatus(200);

        $this->assertNull($this->submittedApplication()->vacancy_id);
    }

    public function test_submit_uses_explicit_vacancy_id_when_title_matches(): void
    {
        $this->makeVacancy(['location' => 'Bandung - KARNIVOR - JL. RIAU']);
        $explicit = $this->makeVacancy(['location' => 'Bandung - LAIN']);

        $this->postJson('/api/applications', $this->submitPayload([
            'positionApplied' => 'Bandung - Cook',
            'workLocation'    => 'Bandung - KARNIVOR - JL. RIAU',
        ], $explicit->id))->assertStatus(200);

        $this->assertSame($explicit->id, $this->submittedApplication()->vacancy_id);
    }

    public function test_submit_falls_back_to_auto_match_when_applicant_changed_position(): void
    {
        $waiter = $this->makeVacancy(['title' => 'Bandung - Waiter']);
        $cook = $this->makeVacancy();

        $this->postJson('/api/applications', $this->submitPayload([
            'positionApplied' => 'Bandung - Cook',
        ], $waiter->id))->assertStatus(200);

        $this->assertSame($cook->id, $this->submittedApplication()->vacancy_id);
    }

    public function test_submit_with_unknown_vacancy_id_still_succeeds(): void
    {
        $this->postJson('/api/applications', $this->submitPayload([
            'positionApplied' => 'Bandung - Cook',
        ], '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d'))->assertStatus(200);

        $this->assertNull($this->submittedApplication()->vacancy_id);
    }

    // ------------------------------------------------------- applicants list

    public function test_applicant_list_filters_by_vacancy_and_exposes_it(): void
    {
        $this->actingAsAdmin();
        $vacancy = $this->makeVacancy();
        $linked = $this->makeApplication(['vacancy_id' => $vacancy->id]);
        $unlinked = $this->makeApplication();

        $this->getJson("/api/applicants?vacancyId={$vacancy->id}")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $linked->id)
            ->assertJsonPath('data.0.vacancyId', $vacancy->id)
            ->assertJsonPath('data.0.vacancy.title', 'Bandung - Cook');

        $this->getJson('/api/applicants?vacancyId=none')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $unlinked->id)
            ->assertJsonPath('data.0.vacancy', null);
    }

    public function test_hr_can_assign_and_detach_vacancy(): void
    {
        $this->actingAsAdmin();
        $vacancy = $this->makeVacancy();
        $app = $this->makeApplication();

        $this->patchJson("/api/applicants/{$app->id}/vacancy", ['vacancy_id' => $vacancy->id])
            ->assertStatus(200)
            ->assertJsonPath('data.vacancy.id', $vacancy->id);

        $this->patchJson("/api/applicants/{$app->id}/vacancy", ['vacancy_id' => null])
            ->assertStatus(200)
            ->assertJsonPath('data.vacancyId', null);

        $this->patchJson("/api/applicants/{$app->id}/vacancy", ['vacancy_id' => '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d'])
            ->assertStatus(422);
    }

    // ------------------------------------------------------------- headcount

    public function test_vacancy_resource_counts_applicants_and_hired(): void
    {
        $this->actingAsAdmin();
        $vacancy = $this->makeVacancy(['headcount' => 3]);
        $this->makeApplication(['vacancy_id' => $vacancy->id, 'stage' => 'hired']);
        $this->makeApplication(['vacancy_id' => $vacancy->id, 'stage' => 'interview']);
        $this->makeApplication();

        $this->getJson("/api/vacancies/{$vacancy->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.headcount', 3)
            ->assertJsonPath('data.applicantCount', 2)
            ->assertJsonPath('data.hiredCount', 1);
    }

    public function test_hiring_last_slot_closes_vacancy(): void
    {
        $this->actingAsAdmin();
        $vacancy = $this->makeVacancy(['headcount' => 2]);
        $first = $this->makeApplication(['vacancy_id' => $vacancy->id]);
        $second = $this->makeApplication(['vacancy_id' => $vacancy->id]);

        $this->patchJson("/api/applicants/{$first->id}/stage", ['stage' => 'hired'])->assertStatus(200);
        $this->assertSame('open', $vacancy->fresh()->status);

        $this->patchJson("/api/applicants/{$second->id}/stage", ['stage' => 'hired'])->assertStatus(200);
        $this->assertSame('closed', $vacancy->fresh()->status);
    }

    public function test_bulk_hiring_closes_filled_vacancy(): void
    {
        $this->actingAsAdmin();
        $vacancy = $this->makeVacancy(['headcount' => 2]);
        $ids = [
            $this->makeApplication(['vacancy_id' => $vacancy->id])->id,
            $this->makeApplication(['vacancy_id' => $vacancy->id])->id,
        ];

        $this->patchJson('/api/applicants/bulk-stage', ['ids' => $ids, 'stage' => 'hired'])->assertStatus(200);

        $this->assertSame('closed', $vacancy->fresh()->status);
    }

    public function test_vacancy_without_headcount_stays_open_after_hiring(): void
    {
        $this->actingAsAdmin();
        $vacancy = $this->makeVacancy(['headcount' => null]);
        $app = $this->makeApplication(['vacancy_id' => $vacancy->id]);

        $this->patchJson("/api/applicants/{$app->id}/stage", ['stage' => 'hired'])->assertStatus(200);

        $this->assertSame('open', $vacancy->fresh()->status);
    }

    public function test_vacancy_headcount_is_editable(): void
    {
        $this->actingAsAdmin();
        $vacancy = $this->makeVacancy();

        $this->patchJson("/api/vacancies/{$vacancy->id}", ['headcount' => 4])
            ->assertStatus(200)
            ->assertJsonPath('data.headcount', 4);

        $this->patchJson("/api/vacancies/{$vacancy->id}", ['headcount' => 0])->assertStatus(422);
    }

    // ------------------------------------------------- job request → vacancy

    private function makeJobRequest(array $override = []): JobRequest
    {
        return JobRequest::create(array_merge([
            'title'           => 'Bandung - Cook',
            'department'      => 'Kitchen',
            'location'        => 'Bandung - KARNIVOR - JL. RIAU',
            'employment_type' => 'contract',
            'headcount'       => 3,
            'justification'   => 'Outlet baru.',
            'requirements'    => ['Bisa masak'],
            'status'          => 'approved',
        ], $override));
    }

    public function test_create_vacancy_from_approved_job_request(): void
    {
        $this->actingAsAdmin();
        $jobRequest = $this->makeJobRequest();

        $this->postJson("/api/job-requests/{$jobRequest->id}/create-vacancy")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.title', 'Bandung - Cook')
            ->assertJsonPath('data.type', 'contract')
            ->assertJsonPath('data.headcount', 3)
            ->assertJsonPath('data.jobRequestId', $jobRequest->id)
            ->assertJsonPath('data.requirements', ['Bisa masak']);

        $this->getJson("/api/job-requests/{$jobRequest->id}")
            ->assertJsonPath('data.vacancyId', Vacancy::first()->id);
    }

    public function test_create_vacancy_rejects_unapproved_job_request(): void
    {
        $this->actingAsAdmin();
        $jobRequest = $this->makeJobRequest(['status' => 'pending']);

        $this->postJson("/api/job-requests/{$jobRequest->id}/create-vacancy")->assertStatus(422);
        $this->assertDatabaseCount('vacancies', 0);
    }

    public function test_create_vacancy_only_once_per_job_request(): void
    {
        $this->actingAsAdmin();
        $jobRequest = $this->makeJobRequest();

        $this->postJson("/api/job-requests/{$jobRequest->id}/create-vacancy")->assertStatus(200);
        $this->postJson("/api/job-requests/{$jobRequest->id}/create-vacancy")->assertStatus(422);

        $this->assertDatabaseCount('vacancies', 1);
    }
}
