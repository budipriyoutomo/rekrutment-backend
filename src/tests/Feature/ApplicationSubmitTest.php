<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationSubmitTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $additionalInfo = []): array
    {
        return [
            'personalInfo' => ['fullName' => 'Budi Santoso'],
            'contactInfo'  => ['email' => 'budi@example.com', 'phone' => '08123456789'],
            'education'    => [],
            'certifications' => [],
            'workExperience' => [],
            'parentInfo'   => ['fatherName' => 'Slamet'],
            'spouseInfo'   => ['spouseName' => ''],
            'additionalInfo' => $additionalInfo,
        ];
    }

    public function test_rejects_work_location_from_another_city(): void
    {
        $this->postJson('/api/applications', $this->payload([
            'positionApplied' => 'Bandung - Cook',
            'workLocation'    => 'Jakarta - HEAD OFFICE JAKARTA',
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('additionalInfo.workLocation');
    }

    public function test_accepts_work_location_in_the_same_city(): void
    {
        $this->postJson('/api/applications', $this->payload([
            'positionApplied' => 'Bandung - Cook',
            'workLocation'    => 'Bandung - KARNIVOR - JL. RIAU',
        ]))->assertStatus(200);
    }

    public function test_allows_values_without_city_prefix(): void
    {
        $this->postJson('/api/applications', $this->payload([
            'positionApplied' => 'Cook',
            'workLocation'    => 'Jakarta - HEAD OFFICE JAKARTA',
        ]))->assertStatus(200);
    }

    public function test_allows_submission_without_work_location(): void
    {
        $this->postJson('/api/applications', $this->payload([
            'positionApplied' => 'Bandung - Cook',
        ]))->assertStatus(200);
    }
}
