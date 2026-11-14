<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerPageLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
    }

    public function test_per_page_is_capped_at_1000(): void
    {
        $this->getJson('/api/applicants?per_page=1000000')
            ->assertStatus(200)
            ->assertJsonPath('meta.limit', 1000);
    }

    public function test_limit_alias_is_also_capped(): void
    {
        $this->getJson('/api/applicants?limit=999999')
            ->assertStatus(200)
            ->assertJsonPath('meta.limit', 1000);
    }

    public function test_existing_large_frontend_limits_are_kept(): void
    {
        $this->getJson('/api/applicants?limit=1000')
            ->assertStatus(200)
            ->assertJsonPath('meta.limit', 1000);
    }

    public function test_non_numeric_per_page_falls_back_to_default(): void
    {
        $this->getJson('/api/applicants?per_page=abc')
            ->assertStatus(200)
            ->assertJsonPath('meta.limit', 10);
    }

    public function test_zero_or_negative_per_page_is_raised_to_one(): void
    {
        $this->getJson('/api/applicants?per_page=-5')
            ->assertStatus(200)
            ->assertJsonPath('meta.limit', 1);
    }

    public function test_array_per_page_does_not_crash(): void
    {
        $this->getJson('/api/applicants?per_page[]=5')
            ->assertStatus(200)
            ->assertJsonPath('meta.limit', 10);
    }

    public function test_other_list_endpoints_accept_bad_per_page(): void
    {
        foreach (['/api/users', '/api/vacancies', '/api/salary-slips', '/api/offers', '/api/interviews'] as $endpoint) {
            $this->getJson("{$endpoint}?per_page=abc")->assertStatus(200);
            $this->getJson("{$endpoint}?per_page=1000000")->assertStatus(200);
        }
    }
}
