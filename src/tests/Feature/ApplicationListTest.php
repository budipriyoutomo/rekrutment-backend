<?php

namespace Tests\Feature;

use App\Domains\Application\Models\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
    }

    private function makeApplications(int $count, string $stage = 'applied'): void
    {
        foreach (range(1, $count) as $i) {
            Application::create([
                'personal_info' => ['fullName' => "Candidate {$i}"],
                'contact_info' => ['email' => "cand{$i}-{$stage}@example.com", 'phone' => '081'],
                'additional_info' => ['positionApplied' => 'Frontend Dev'],
                'stage' => $stage,
            ]);
        }
    }

    public function test_index_returns_pagination_meta(): void
    {
        $this->makeApplications(12);

        $this->getJson('/api/applicants?limit=5&page=1')
            ->assertStatus(200)
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.total', 12)
            ->assertJsonPath('meta.page', 1)
            ->assertJsonPath('meta.limit', 5)
            ->assertJsonPath('meta.totalPages', 3);
    }

    public function test_index_returns_requested_page(): void
    {
        $this->makeApplications(12);

        $this->getJson('/api/applicants?limit=5&page=3')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.page', 3)
            ->assertJsonPath('meta.total', 12);
    }

    public function test_index_data_stays_a_flat_array(): void
    {
        $this->makeApplications(2);

        $response = $this->getJson('/api/applicants')->assertStatus(200);

        $this->assertTrue(array_is_list($response->json('data')));
    }

    public function test_index_filters_by_stage(): void
    {
        $this->makeApplications(3, 'applied');
        $this->makeApplications(4, 'interview');

        $this->getJson('/api/applicants?stage=applied')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 3);
    }

    public function test_index_excludes_stage(): void
    {
        $this->makeApplications(3, 'applied');
        $this->makeApplications(4, 'interview');
        $this->makeApplications(2, 'hired');

        $this->getJson('/api/applicants?excludeStage=applied')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 6);
    }

    public function test_index_filters_by_city_including_legacy_prefix(): void
    {
        $make = fn (string $email, array $info) => Application::create([
            'personal_info' => ['fullName' => $email],
            'contact_info' => ['email' => "{$email}@example.com", 'phone' => '081'],
            'additional_info' => $info,
            'stage' => 'applied',
        ]);

        $make('baru', ['city' => 'Bandung', 'workLocation' => 'Bandung - RIAU', 'positionApplied' => 'Bandung - Cook']);
        $make('lama-lokasi', ['workLocation' => 'Bandung - GEDEBAGE', 'positionApplied' => 'Cook']);
        $make('lama-posisi', ['positionApplied' => 'Bandung - Barista']);
        $make('jakarta', ['city' => 'Jakarta', 'workLocation' => 'Jakarta - HO', 'positionApplied' => 'Jakarta - Driver']);
        $make('mirip', ['workLocation' => 'Bandung Barat - X', 'positionApplied' => 'Cook']);

        $this->getJson('/api/applicants?city=Bandung')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 3);
    }
}
