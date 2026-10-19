<?php

namespace App\Http\Controllers;

use App\Core\Http\Controllers\BaseApiController;
use App\Domains\Employee\Actions\ConvertToEmployeeAction;
use App\Domains\Employee\Resources\EmployeeResource;
use App\Domains\Employee\Services\EmployeeService;
use App\Domains\Preboarding\Models\Preboarding;
use Illuminate\Http\Request;

class EmployeeController extends BaseApiController
{
    public function index(Request $request, EmployeeService $service)
    {
        $data = $service->getList(
            filters: $request->only(['status', 'applicationId', 'search']),
            perPage: (int) $request->get('per_page', $request->get('limit', 50))
        );

        return $this->successPaginated(EmployeeResource::collection($data), $data, 'Data karyawan berhasil diambil');
    }

    public function show(string $id, EmployeeService $service)
    {
        return $this->success(new EmployeeResource($service->getDetail($id)), 'Detail karyawan berhasil diambil');
    }

    /** Konversi pre-boarding terverifikasi menjadi karyawan. */
    public function store(Request $request, ConvertToEmployeeAction $action)
    {
        $validated = $request->validate([
            'preboarding_id' => ['required', 'uuid', 'exists:preboardings,id'],
            'employee_number' => ['nullable', 'string', 'max:50'],
            'start_date' => ['nullable', 'date'],
        ]);

        $employee = $action->execute(
            Preboarding::findOrFail($validated['preboarding_id']),
            $validated['employee_number'] ?? null,
            $validated['start_date'] ?? null,
        );

        return $this->success(new EmployeeResource($employee), "Karyawan {$employee->employee_number} berhasil dibuat");
    }

    public function update(string $id, Request $request, EmployeeService $service)
    {
        $validated = $request->validate([
            'employee_number' => ['sometimes', 'string', 'max:50'],
            'position' => ['sometimes', 'string', 'max:200'],
            'department' => ['sometimes', 'nullable', 'string', 'max:100'],
            'location' => ['sometimes', 'nullable', 'string', 'max:150'],
            'start_date' => ['sometimes', 'date'],
        ]);

        if (isset($validated['employee_number'])) {
            $service->ensureUniqueNumber($id, $validated['employee_number']);
        }

        return $this->success(new EmployeeResource($service->update($id, $validated)), 'Data karyawan diperbarui');
    }

    public function addTask(string $id, Request $request, EmployeeService $service)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'pic' => ['nullable', 'string', 'max:100'],
            'due_date' => ['required', 'date'],
        ]);

        return $this->success(new EmployeeResource($service->addTask($id, $validated)), 'Task ditambahkan');
    }

    public function updateTask(string $taskId, Request $request, EmployeeService $service)
    {
        $validated = $request->validate([
            'completed' => ['sometimes', 'boolean'],
            'title' => ['sometimes', 'string', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'pic' => ['sometimes', 'nullable', 'string', 'max:100'],
            'due_date' => ['sometimes', 'date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        return $this->success(new EmployeeResource($service->updateTask($taskId, $validated)), 'Task diperbarui');
    }

    public function deleteTask(string $taskId, EmployeeService $service)
    {
        return $this->success(new EmployeeResource($service->deleteTask($taskId)), 'Task dihapus');
    }
}
