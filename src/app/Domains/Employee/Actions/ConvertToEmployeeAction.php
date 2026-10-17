<?php

namespace App\Domains\Employee\Actions;

use App\Domains\Employee\Models\Employee;
use App\Domains\Employee\Models\OnboardingTaskTemplate;
use App\Domains\Employee\Services\EmployeeNumberGenerator;
use App\Domains\Preboarding\Models\Preboarding;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pre-boarding terverifikasi → data karyawan (NIK + salinan data pre-boarding) dan
 * checklist onboarding dari template yang berlaku untuk departemennya.
 */
class ConvertToEmployeeAction
{
    public function __construct(private EmployeeNumberGenerator $numbers) {}

    public function execute(Preboarding $preboarding, ?string $employeeNumber = null, ?string $startDate = null): Employee
    {
        $preboarding->loadMissing('application.vacancy', 'offer');
        $application = $preboarding->application;
        $offer = $preboarding->offer;

        if ($preboarding->status !== Preboarding::STATUS_VERIFIED) {
            throw ValidationException::withMessages([
                'preboarding_id' => 'Pre-boarding harus terverifikasi sebelum dikonversi menjadi karyawan.',
            ]);
        }

        if (Employee::where('application_id', $application->id)->exists()) {
            throw ValidationException::withMessages([
                'preboarding_id' => 'Kandidat ini sudah terdaftar sebagai karyawan.',
            ]);
        }

        $start = $startDate ? Carbon::parse($startDate) : $offer?->start_date;
        if (! $start) {
            throw ValidationException::withMessages([
                'start_date' => 'Tanggal mulai bekerja wajib diisi (tidak ada offer yang diterima).',
            ]);
        }

        $employeeNumber = $employeeNumber !== null && trim($employeeNumber) !== '' ? trim($employeeNumber) : null;
        if ($employeeNumber && Employee::where('employee_number', $employeeNumber)->exists()) {
            throw ValidationException::withMessages(['employee_number' => 'NIK ini sudah dipakai karyawan lain.']);
        }

        return DB::transaction(function () use ($preboarding, $application, $offer, $start, $employeeNumber) {
            $department = $offer?->department ?? $application->vacancy?->department;

            $employee = Employee::create([
                'employee_number' => $employeeNumber ?? $this->numbers->next($start),
                'application_id'  => $application->id,
                'preboarding_id'  => $preboarding->id,
                'offer_id'        => $offer?->id,
                'full_name'       => $application->personal_info['fullName'] ?? 'Karyawan',
                'email'           => $application->contact_info['email'] ?? null,
                'phone'           => $application->contact_info['phone'] ?? null,
                'position'        => $preboarding->position() ?? '-',
                'department'      => $department,
                'location'        => $offer?->location ?? $application->vacancy?->location,
                'employment_type' => $offer?->employment_type ?? $application->vacancy?->type ?? 'full-time',
                'start_date'      => $start->toDateString(),
                'personal_data'   => $preboarding->data,
                'status'          => Employee::STATUS_ONBOARDING,
            ]);

            OnboardingTaskTemplate::query()->applicableTo($department)->get()
                ->each(fn (OnboardingTaskTemplate $t, int $i) => $employee->tasks()->create([
                    'template_id' => $t->id,
                    'title'       => $t->title,
                    'description' => $t->description,
                    'pic'         => $t->pic,
                    'due_date'    => $start->copy()->addDays($t->offset_days)->toDateString(),
                    'sort_order'  => $i,
                ]));

            return $employee->load('tasks');
        });
    }
}
