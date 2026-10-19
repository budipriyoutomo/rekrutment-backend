<?php

namespace Tests\Feature;

use App\Domains\Application\Models\Application;
use App\Domains\Employee\Mail\WelcomeEmployeeMail;
use App\Domains\Employee\Models\Employee;
use App\Domains\Employee\Models\OnboardingTaskTemplate;
use App\Domains\Offer\Models\Offer;
use App\Domains\Preboarding\Models\Preboarding;
use App\Domains\Setting\Models\CompanySetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Fase 5 Offer→Onboarding: konversi ke karyawan (NIK), checklist onboarding, email sambutan.
 */
class EmployeeOnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
        Mail::fake();
    }

    private function verifiedPreboarding(array $offerOverride = [], array $appOverride = []): Preboarding
    {
        static $n = 0;
        $n++;

        $app = Application::create(array_merge([
            'personal_info' => ['fullName' => "Siti Kandidat {$n}"],
            'contact_info' => ['email' => "siti{$n}@example.com", 'phone' => '0812'],
            'additional_info' => ['positionApplied' => 'Bandung - Head Chef'],
            'stage' => 'hired',
        ], $appOverride));

        $offer = Offer::create(array_merge([
            'number' => 'OFR/2026/10/'.str_pad((string) $n, 4, '0', STR_PAD_LEFT), 'application_id' => $app->id,
            'position' => 'Bandung - Head Chef', 'department' => 'Kitchen', 'location' => 'Bandung - RIAU',
            'employment_type' => 'contract', 'base_salary' => 8500000, 'start_date' => '2026-11-02',
            'expires_at' => now()->addWeek()->toDateString(), 'status' => Offer::STATUS_ACCEPTED,
        ], $offerOverride));

        return Preboarding::create([
            'application_id' => $app->id,
            'offer_id' => $offer->id,
            'status' => Preboarding::STATUS_VERIFIED,
            'token' => Str::random(64),
            'token_expires_at' => now()->addDays(30),
            'data' => ['bankName' => 'BCA', 'bankAccountNumber' => '1234567890'],
            'documents' => [],
        ]);
    }

    private function template(array $override = []): OnboardingTaskTemplate
    {
        return OnboardingTaskTemplate::create(array_merge([
            'title' => 'Siapkan seragam', 'pic' => 'HR', 'offset_days' => -3,
        ], $override));
    }

    // ------------------------------------------------------------- konversi

    public function test_convert_creates_employee_with_number_and_template_tasks(): void
    {
        $this->template(['title' => 'Buat akun email', 'pic' => 'IT', 'offset_days' => -7]);
        $this->template(['title' => 'Orientasi dapur', 'pic' => 'Head Chef', 'offset_days' => 0, 'department' => 'Kitchen']);
        $this->template(['title' => 'Kenalan tim sales', 'department' => 'Sales']);
        $this->template(['title' => 'Template nonaktif', 'is_active' => false]);
        $preboarding = $this->verifiedPreboarding();

        $this->postJson('/api/employees', ['preboarding_id' => $preboarding->id])
            ->assertStatus(200)
            ->assertJsonPath('data.employeeNumber', 'EMP-2026-0001')
            ->assertJsonPath('data.fullName', 'Siti Kandidat 1')
            ->assertJsonPath('data.position', 'Bandung - Head Chef')
            ->assertJsonPath('data.department', 'Kitchen')
            ->assertJsonPath('data.employmentType', 'contract')
            ->assertJsonPath('data.startDate', '2026-11-02')
            ->assertJsonPath('data.status', 'onboarding')
            ->assertJsonPath('data.personalData.bankName', 'BCA')
            ->assertJsonPath('data.progress.total', 2)
            ->assertJsonPath('data.tasks.0.title', 'Buat akun email')
            ->assertJsonPath('data.tasks.0.dueDate', '2026-10-26')
            ->assertJsonPath('data.tasks.1.title', 'Orientasi dapur')
            ->assertJsonPath('data.tasks.1.dueDate', '2026-11-02');

        $this->getJson("/api/preboardings/{$preboarding->id}")
            ->assertJsonPath('data.employeeId', Employee::first()->id);
    }

    public function test_employee_numbers_follow_prefix_and_year_sequence(): void
    {
        CompanySetting::set('employee_id_prefix', 'alt');

        $a = $this->verifiedPreboarding();
        $b = $this->verifiedPreboarding();
        $c = $this->verifiedPreboarding(['start_date' => '2027-01-04']);

        $this->postJson('/api/employees', ['preboarding_id' => $a->id])->assertJsonPath('data.employeeNumber', 'ALT-2026-0001');
        $this->postJson('/api/employees', ['preboarding_id' => $b->id])->assertJsonPath('data.employeeNumber', 'ALT-2026-0002');
        $this->postJson('/api/employees', ['preboarding_id' => $c->id])->assertJsonPath('data.employeeNumber', 'ALT-2027-0001');
    }

    public function test_hr_can_set_custom_unique_employee_number(): void
    {
        $a = $this->verifiedPreboarding();
        $b = $this->verifiedPreboarding();

        $this->postJson('/api/employees', ['preboarding_id' => $a->id, 'employee_number' => 'PAY-00123'])
            ->assertJsonPath('data.employeeNumber', 'PAY-00123');
        $this->postJson('/api/employees', ['preboarding_id' => $b->id, 'employee_number' => 'PAY-00123'])
            ->assertStatus(422)->assertJsonValidationErrors('employee_number');
    }

    public function test_convert_requires_verified_preboarding_once(): void
    {
        $preboarding = $this->verifiedPreboarding();
        $preboarding->update(['status' => Preboarding::STATUS_SUBMITTED]);

        $this->postJson('/api/employees', ['preboarding_id' => $preboarding->id])->assertStatus(422);

        $preboarding->update(['status' => Preboarding::STATUS_VERIFIED]);
        $this->postJson('/api/employees', ['preboarding_id' => $preboarding->id])->assertStatus(200);
        $this->postJson('/api/employees', ['preboarding_id' => $preboarding->id])->assertStatus(422);
    }

    public function test_convert_without_offer_requires_start_date(): void
    {
        $preboarding = $this->verifiedPreboarding();
        $preboarding->update(['offer_id' => null]);

        $this->postJson('/api/employees', ['preboarding_id' => $preboarding->id])
            ->assertStatus(422)->assertJsonValidationErrors('start_date');

        $this->postJson('/api/employees', ['preboarding_id' => $preboarding->id, 'start_date' => '2026-12-01'])
            ->assertStatus(200)
            ->assertJsonPath('data.startDate', '2026-12-01')
            ->assertJsonPath('data.employeeNumber', 'EMP-2026-0001');
    }

    // ------------------------------------------------------------- checklist

    public function test_completing_all_tasks_activates_and_reopening_reverts(): void
    {
        $this->template(['title' => 'A']);
        $this->template(['title' => 'B', 'offset_days' => 0]);
        $employee = Employee::find($this->postJson('/api/employees', ['preboarding_id' => $this->verifiedPreboarding()->id])->json('data.id'));
        [$a, $b] = $employee->tasks->all();

        $this->patchJson("/api/onboarding-tasks/{$a->id}", ['completed' => true, 'notes' => 'Ukuran M'])
            ->assertJsonPath('data.status', 'onboarding')
            ->assertJsonPath('data.progress.completed', 1);

        $this->patchJson("/api/onboarding-tasks/{$b->id}", ['completed' => true])
            ->assertJsonPath('data.status', 'active');
        $this->assertNotNull($employee->fresh()->onboarded_at);
        $this->assertSame('Ukuran M', $a->fresh()->notes);

        $this->patchJson("/api/onboarding-tasks/{$b->id}", ['completed' => false])
            ->assertJsonPath('data.status', 'onboarding');
        $this->assertNull($employee->fresh()->onboarded_at);
    }

    public function test_hr_can_add_and_delete_ad_hoc_tasks(): void
    {
        $employee = Employee::find($this->postJson('/api/employees', ['preboarding_id' => $this->verifiedPreboarding()->id])->json('data.id'));

        $response = $this->postJson("/api/employees/{$employee->id}/tasks", [
            'title' => 'Ambil kartu akses', 'pic' => 'Security', 'due_date' => '2026-11-02',
        ])->assertStatus(200)->assertJsonPath('data.progress.total', 1);

        $taskId = $response->json('data.tasks.0.id');
        $this->patchJson("/api/onboarding-tasks/{$taskId}", ['completed' => true])->assertJsonPath('data.status', 'active');
        $this->deleteJson("/api/onboarding-tasks/{$taskId}")->assertStatus(200)->assertJsonPath('data.progress.total', 0);
    }

    public function test_overdue_tasks_are_flagged(): void
    {
        $this->template(['title' => 'Lewat', 'offset_days' => -30]);
        $preboarding = $this->verifiedPreboarding(['start_date' => now()->addDays(5)->toDateString()]);

        $this->postJson('/api/employees', ['preboarding_id' => $preboarding->id])
            ->assertJsonPath('data.tasks.0.overdue', true)
            ->assertJsonPath('data.progress.overdue', 1);
    }

    public function test_templates_crud_and_validation(): void
    {
        $id = $this->postJson('/api/onboarding-templates', [
            'title' => 'Buat akun email', 'pic' => 'IT', 'offset_days' => -7, 'department' => '  ',
        ])->assertStatus(200)->assertJsonPath('data.department', null)->json('data.id');

        $this->patchJson("/api/onboarding-templates/{$id}", ['is_active' => false])->assertJsonPath('data.isActive', false);
        $this->postJson('/api/onboarding-templates', ['title' => 'X', 'offset_days' => 999])->assertStatus(422);
        $this->getJson('/api/onboarding-templates')->assertJsonCount(1, 'data');
        $this->deleteJson("/api/onboarding-templates/{$id}")->assertStatus(200);
        $this->assertDatabaseCount('onboarding_task_templates', 0);
    }

    public function test_employee_endpoints_require_onboarding_permission(): void
    {
        $preboarding = $this->verifiedPreboarding();

        $this->actingAsUserWithPermissions(['candidates']);
        $this->getJson("/api/employees?applicationId={$preboarding->application_id}")->assertStatus(200);
        $this->postJson('/api/employees', ['preboarding_id' => $preboarding->id])->assertStatus(403);
        $this->getJson('/api/onboarding-templates')->assertStatus(403);
    }

    // ------------------------------------------------------- email sambutan

    public function test_welcome_email_sent_once_to_employees_starting_tomorrow(): void
    {
        $tomorrow = $this->verifiedPreboarding(['start_date' => now()->addDay()->toDateString()]);
        $nextWeek = $this->verifiedPreboarding(['start_date' => now()->addWeek()->toDateString()]);
        $this->postJson('/api/employees', ['preboarding_id' => $tomorrow->id]);
        $this->postJson('/api/employees', ['preboarding_id' => $nextWeek->id]);

        $this->artisan('onboarding:welcome')->expectsOutputToContain('1 email')->assertSuccessful();
        $this->artisan('onboarding:welcome')->expectsOutputToContain('0 email')->assertSuccessful();

        Mail::assertQueued(WelcomeEmployeeMail::class, 1);
        Mail::assertQueued(WelcomeEmployeeMail::class, fn ($m) => $m->hasTo($tomorrow->application->contact_info['email']));
    }

    public function test_welcome_email_content(): void
    {
        CompanySetting::set('company_name', 'Altima Group');
        $preboarding = $this->verifiedPreboarding(['start_date' => '2026-11-02']);
        $employee = Employee::find($this->postJson('/api/employees', ['preboarding_id' => $preboarding->id])->json('data.id'));

        $mail = new WelcomeEmployeeMail($employee);

        $mail->assertHasSubject('Selamat Datang di Altima Group – Besok Hari Pertama Anda');
        $mail->assertSeeInHtml('Senin, 2 November 2026');
        $mail->assertSeeInHtml('EMP-2026-0001');
    }

    public function test_employee_number_prefix_setting_is_validated(): void
    {
        $this->putJson('/api/settings/company', ['employee_id_prefix' => 'EMP-X'])->assertStatus(422);
        $this->putJson('/api/settings/company', ['employee_id_prefix' => 'ALT'])->assertStatus(200);
        $this->assertSame('ALT', CompanySetting::get('employee_id_prefix'));
    }
}
