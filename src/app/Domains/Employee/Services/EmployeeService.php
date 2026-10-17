<?php

namespace App\Domains\Employee\Services;

use App\Domains\Employee\Mail\WelcomeEmployeeMail;
use App\Domains\Employee\Models\Employee;
use App\Domains\Employee\Models\OnboardingTask;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class EmployeeService
{
    public function getList(array $filters = [], int $perPage = 50): LengthAwarePaginator
    {
        return Employee::query()
            ->with('tasks')
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['applicationId'] ?? null, fn ($q, $id) => $q->where('application_id', $id))
            ->when($filters['search'] ?? null, function ($q, $search) {
                $like = $q->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
                $q->where(fn ($q) => $q->where('full_name', $like, "%{$search}%")->orWhere('employee_number', $like, "%{$search}%"));
            })
            ->orderBy('start_date')
            ->paginate($perPage);
    }

    public function getDetail(string $id): Employee
    {
        return Employee::with('tasks')->findOrFail($id);
    }

    public function update(string $id, array $data): Employee
    {
        $employee = Employee::findOrFail($id);
        $employee->update($data);

        return $this->getDetail($id);
    }

    public function addTask(string $employeeId, array $data): Employee
    {
        $employee = Employee::findOrFail($employeeId);
        $employee->tasks()->create([
            ...$data,
            'sort_order' => (int) $employee->tasks()->max('sort_order') + 1,
        ]);

        return $this->syncStatus($employee);
    }

    /** Ubah task (tandai selesai/belum, catatan, dsb). */
    public function updateTask(string $taskId, array $data): Employee
    {
        $task = OnboardingTask::findOrFail($taskId);

        if (array_key_exists('completed', $data)) {
            $completed = (bool) $data['completed'];
            unset($data['completed']);
            $data['completed_at'] = $completed ? ($task->completed_at ?? now()) : null;
            $data['completed_by'] = $completed ? ($task->completed_by ?? Auth::id()) : null;
        }

        $task->update($data);

        return $this->syncStatus($task->employee);
    }

    public function deleteTask(string $taskId): Employee
    {
        $task = OnboardingTask::findOrFail($taskId);
        $employee = $task->employee;
        $task->delete();

        return $this->syncStatus($employee);
    }

    /**
     * Status mengikuti checklist: semua task selesai → active, ada yang dibuka lagi
     * → kembali onboarding. Karyawan tanpa task tidak diubah otomatis.
     */
    private function syncStatus(Employee $employee): Employee
    {
        $employee->load('tasks');
        $tasks = $employee->tasks;

        if ($tasks->isNotEmpty()) {
            $allDone = $tasks->every(fn (OnboardingTask $t) => $t->completed_at !== null);

            if ($allDone && $employee->status !== Employee::STATUS_ACTIVE) {
                $employee->update(['status' => Employee::STATUS_ACTIVE, 'onboarded_at' => now()]);
            } elseif (! $allDone && $employee->status === Employee::STATUS_ACTIVE) {
                $employee->update(['status' => Employee::STATUS_ONBOARDING, 'onboarded_at' => null]);
            }
        }

        return $this->getDetail($employee->id);
    }

    /** Kirim email sambutan H-1 ke karyawan yang mulai besok. Mengembalikan jumlahnya. */
    public function sendWelcomeEmails(): int
    {
        $employees = Employee::query()
            ->whereDate('start_date', now()->addDay()->toDateString())
            ->whereNull('welcome_sent_at')
            ->whereNotNull('email')
            ->get();

        foreach ($employees as $employee) {
            Mail::to($employee->email)->queue((new WelcomeEmployeeMail($employee))->onQueue('emails'));
            $employee->update(['welcome_sent_at' => now()]);
        }

        return $employees->count();
    }

    public function ensureUniqueNumber(string $id, string $number): void
    {
        if (Employee::where('employee_number', $number)->where('id', '!=', $id)->exists()) {
            throw ValidationException::withMessages(['employee_number' => 'NIK ini sudah dipakai karyawan lain.']);
        }
    }
}
