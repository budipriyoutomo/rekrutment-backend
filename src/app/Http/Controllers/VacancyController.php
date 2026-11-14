<?php

namespace App\Http\Controllers;

use App\Core\Http\Controllers\BaseApiController;
use App\Core\Http\PerPage;
use App\Domains\Vacancy\Requests\VacancyRequest;
use App\Domains\Vacancy\Resources\VacancyResource;
use App\Domains\Vacancy\Services\VacancyService;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class VacancyController extends BaseApiController
{
    public function index(Request $request, VacancyService $service)
    {
        $filters = $request->only(['status', 'department', 'search']);

        // Pengunjung publik (landing page) hanya boleh melihat lowongan yang dibuka.
        if (! $this->isStaffRequest()) {
            $filters['status'] = 'open';
        }

        $data = $service->getList(
            filters: $filters,
            perPage: PerPage::from($request, 20)
        );

        return $this->success(
            VacancyResource::collection($data),
            'Data vacancy berhasil diambil'
        );
    }

    public function show(string $id, VacancyService $service)
    {
        return $this->success(
            new VacancyResource($service->getDetail($id, onlyOpen: ! $this->isStaffRequest())),
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

    /**
     * Route GET vacancies publik (tanpa middleware auth); token bersifat opsional.
     * Staf HR yang login & aktif melihat semua status — dropdown filter lowongan
     * di Applicants/Pipeline/Offers juga memuat lowongan draft/tertutup.
     */
    private function isStaffRequest(): bool
    {
        if (! request()->bearerToken()) {
            return false;
        }

        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Throwable) {
            return false;
        }

        return $user !== false && $user !== null && (bool) $user->is_active;
    }
}
