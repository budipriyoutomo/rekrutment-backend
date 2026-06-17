<?php

namespace App\Http\Controllers;

use App\Core\Http\Controllers\BaseApiController;
use App\Domains\Vacancy\Requests\VacancyRequest;
use App\Domains\Vacancy\Resources\VacancyResource;
use App\Domains\Vacancy\Services\VacancyService;
use Illuminate\Http\Request;

class VacancyController extends BaseApiController
{
    public function index(Request $request, VacancyService $service)
    {
        $data = $service->getList(
            filters: $request->only(['status', 'department', 'search']),
            perPage: (int) $request->get('per_page', $request->get('limit', 20))
        );

        return $this->success(
            VacancyResource::collection($data),
            'Data vacancy berhasil diambil'
        );
    }

    public function show(string $id, VacancyService $service)
    {
        return $this->success(
            new VacancyResource($service->getDetail($id)),
            'Detail vacancy berhasil diambil'
        );
    }

    public function store(VacancyRequest $request, VacancyService $service)
    {
        return $this->success(
            new VacancyResource($service->createVacancy($request->validated())),
            'Vacancy berhasil dibuat'
        );
    }

    public function update(string $id, VacancyRequest $request, VacancyService $service)
    {
        return $this->success(
            new VacancyResource($service->updateVacancy($id, $request->validated())),
            'Vacancy berhasil diperbarui'
        );
    }

    public function close(string $id, VacancyService $service)
    {
        return $this->success(
            new VacancyResource($service->closeVacancy($id)),
            'Vacancy berhasil ditutup'
        );
    }

    public function destroy(string $id, VacancyService $service)
    {
        $service->deleteVacancy($id);

        return $this->success(null, 'Vacancy berhasil dihapus');
    }
}
