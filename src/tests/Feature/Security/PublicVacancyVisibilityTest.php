<?php

namespace Tests\Feature\Security;

use App\Domains\Vacancy\Models\Vacancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicVacancyVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function makeVacancy(string $status, string $title): Vacancy
    {
        return Vacancy::create([
            'title' => $title,
            'department' => 'Engineering',
            'location' => 'Jakarta',
            'type' => 'full-time',
            'status' => $status,
            'description' => 'Deskripsi',
        ]);
    }

    public function test_public_vacancy_list_excludes_draft_and_closed(): void
    {
        $this->makeVacancy('open', 'Buka');
        $this->makeVacancy('draft', 'Draf');
        $this->makeVacancy('closed', 'Tutup');

        $this->getJson('/api/vacancies')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Buka');
    }

    public function test_public_status_filter_cannot_reveal_drafts(): void
    {
        $this->makeVacancy('draft', 'Draf');

        $this->getJson('/api/vacancies?status=draft')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_public_cannot_show_draft_vacancy(): void
    {
        $draft = $this->makeVacancy('draft', 'Draf');

        $this->getJson("/api/vacancies/{$draft->id}")->assertStatus(404);
    }

    public function test_public_can_show_open_vacancy(): void
    {
        $open = $this->makeVacancy('open', 'Buka');

        $this->getJson("/api/vacancies/{$open->id}")->assertStatus(200);
    }

    public function test_invalid_token_is_treated_as_public(): void
    {
        $this->makeVacancy('draft', 'Draf');

        $this->withHeader('Authorization', 'Bearer bukan-token')
            ->getJson('/api/vacancies')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_hr_can_list_draft_vacancies(): void
    {
        $this->actingAsUserWithPermissions(['vacancies']);
        $this->makeVacancy('open', 'Buka');
        $this->makeVacancy('draft', 'Draf');

        $this->getJson('/api/vacancies')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_hr_without_vacancies_permission_still_sees_all_for_filters(): void
    {
        // Dropdown filter lowongan di Applicants/Pipeline/Offers juga memuat lowongan tertutup.
        $this->actingAsUserWithPermissions(['offers']);
        $this->makeVacancy('closed', 'Tutup');

        $this->getJson('/api/vacancies')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_hr_can_show_draft_vacancy(): void
    {
        $this->actingAsAdmin();
        $draft = $this->makeVacancy('draft', 'Draf');

        $this->getJson("/api/vacancies/{$draft->id}")->assertStatus(200);
    }

    public function test_inactive_user_is_treated_as_public(): void
    {
        $this->actingAsAdmin(['is_active' => false]);
        $this->makeVacancy('draft', 'Draf');

        $this->getJson('/api/vacancies')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }
}
