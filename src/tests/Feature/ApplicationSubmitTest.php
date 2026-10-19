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
            'contactInfo' => ['email' => 'budi@example.com', 'phone' => '08123456789'],
            'education' => [],
            'certifications' => [],
            'workExperience' => [],
            'parentInfo' => ['fatherName' => 'Slamet'],
            'spouseInfo' => ['spouseName' => ''],
            'additionalInfo' => $additionalInfo,
        ];
    }

    public function test_rejects_work_location_from_another_city(): void
    {
        $this->postJson('/api/applications', $this->payload([
            'positionApplied' => 'Bandung - Cook',
            'workLocation' => 'Jakarta - HEAD OFFICE JAKARTA',
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('additionalInfo.workLocation');
    }

    public function test_accepts_work_location_in_the_same_city(): void
    {
        $this->postJson('/api/applications', $this->payload([
            'positionApplied' => 'Bandung - Cook',
            'workLocation' => 'Bandung - KARNIVOR - JL. RIAU',
        ]))->assertStatus(200);
    }

    public function test_allows_values_without_city_prefix(): void
    {
        $this->postJson('/api/applications', $this->payload([
            'positionApplied' => 'Cook',
            'workLocation' => 'Jakarta - HEAD OFFICE JAKARTA',
        ]))->assertStatus(200);
    }

    public function test_allows_submission_without_work_location(): void
    {
        $this->postJson('/api/applications', $this->payload([
            'positionApplied' => 'Bandung - Cook',
        ]))->assertStatus(200);
    }

    public function test_rejects_second_application_with_the_same_email(): void
    {
        $this->postJson('/api/applications', $this->payload())->assertStatus(200);

        $this->postJson('/api/applications', $this->payload())
            ->assertStatus(422)
            ->assertJsonValidationErrors('contactInfo.email');

        $this->assertDatabaseCount('applications', 1);
    }

    public function test_duplicate_email_check_is_case_and_whitespace_insensitive(): void
    {
        $first = $this->payload();
        $first['contactInfo']['email'] = 'budi@example.com';
        $this->postJson('/api/applications', $first)->assertStatus(200);

        $second = $this->payload();
        $second['contactInfo']['email'] = '  BUDI@Example.com  ';
        $this->postJson('/api/applications', $second)
            ->assertStatus(422)
            ->assertJsonValidationErrors('contactInfo.email');

        $this->assertDatabaseCount('applications', 1);
    }

    public function test_different_emails_can_both_apply(): void
    {
        $first = $this->payload();
        $this->postJson('/api/applications', $first)->assertStatus(200);

        $second = $this->payload();
        $second['contactInfo']['email'] = 'siti@example.com';
        $this->postJson('/api/applications', $second)->assertStatus(200);

        $this->assertDatabaseCount('applications', 2);
    }
}
