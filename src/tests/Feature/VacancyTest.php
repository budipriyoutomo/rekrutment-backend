<?php

namespace Tests\Feature;

use App\Domains\Vacancy\Models\Vacancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VacancyTest extends TestCase
{
    use RefreshDatabase;

    private function vacancyData(array $override = []): array
    {
        return array_merge([
            'title'       => 'Software Engineer',
            'department'  => 'Engineering',
            'location'    => 'Jakarta',
            'type'        => 'full-time',
            'status'      => 'open',
            'description' => 'Build great software.',
        ], $override);
    }

    private function makeVacancy(array $override = []): Vacancy
    {
        return Vacancy::create($this->vacancyData($override));
    }

    // ------------------------------------------------------------------ index

    public function test_index_returns_list_of_vacancies(): void
    {
        $this->makeVacancy(['title' => 'Dev A']);
        $this->makeVacancy(['title' => 'Dev B']);

        $this->getJson('/api/vacancies')
            ->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_index_filters_by_status(): void
    {
        $this->makeVacancy(['status' => 'open']);
        $this->makeVacancy(['status' => 'closed']);

        $this->getJson('/api/vacancies?status=open')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    // ------------------------------------------------------------------ store

    public function test_store_creates_vacancy(): void
    {
        $response = $this->postJson('/api/vacancies', $this->vacancyData());

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.title', 'Software Engineer');

        $this->assertDatabaseHas('vacancies', ['title' => 'Software Engineer']);
    }

    public function test_store_requires_title_department_location(): void
    {
        $this->postJson('/api/vacancies', [])
            ->assertStatus(422)
            ->assertJsonStructure(['errors']);
    }

    public function test_store_rejects_invalid_type(): void
    {
        $this->postJson('/api/vacancies', $this->vacancyData(['type' => 'freelance']))
            ->assertStatus(422);
    }

    public function test_store_persists_salary_range_as_json(): void
    {
        $response = $this->postJson('/api/vacancies', $this->vacancyData([
            'salary' => ['min' => 25000000, 'max' => 40000000],
        ]));

        $response->assertStatus(200)
            ->assertJsonPath('data.salary.min', 25000000)
            ->assertJsonPath('data.salary.max', 40000000);

        $vacancy = Vacancy::first();
        $this->assertSame(['min' => 25000000, 'max' => 40000000], $vacancy->salary);
    }

    public function test_store_rejects_max_salary_below_min(): void
    {
        $this->postJson('/api/vacancies', $this->vacancyData([
            'salary' => ['min' => 40000000, 'max' => 10000000],
        ]))->assertStatus(422);
    }

    // ------------------------------------------------------------------ show

    public function test_show_returns_vacancy(): void
    {
        $vacancy = $this->makeVacancy(['title' => 'Backend Dev']);

        $this->getJson("/api/vacancies/{$vacancy->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.title', 'Backend Dev');
    }

    public function test_show_returns_404_for_unknown_id(): void
    {
        $this->getJson('/api/vacancies/00000000-0000-0000-0000-000000000000')
            ->assertStatus(404);
    }

    // ------------------------------------------------------------------ update

    public function test_update_modifies_vacancy(): void
    {
        $vacancy = $this->makeVacancy();

        $this->putJson("/api/vacancies/{$vacancy->id}", $this->vacancyData(['title' => 'Senior Dev']))
            ->assertStatus(200)
            ->assertJsonPath('data.title', 'Senior Dev');

        $this->assertDatabaseHas('vacancies', ['id' => $vacancy->id, 'title' => 'Senior Dev']);
    }

    // ------------------------------------------------------------------ close

    public function test_close_sets_status_to_closed(): void
    {
        $vacancy = $this->makeVacancy(['status' => 'open']);

        $this->patchJson("/api/vacancies/{$vacancy->id}/close")
            ->assertStatus(200);

        $this->assertDatabaseHas('vacancies', ['id' => $vacancy->id, 'status' => 'closed']);
    }

    // ------------------------------------------------------------------ destroy

    public function test_destroy_deletes_vacancy(): void
    {
        $vacancy = $this->makeVacancy();

        $this->deleteJson("/api/vacancies/{$vacancy->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('vacancies', ['id' => $vacancy->id]);
    }
}
