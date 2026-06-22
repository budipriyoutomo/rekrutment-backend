<?php

namespace App\Http\Controllers;

use App\Core\Http\Controllers\BaseApiController;
use App\Domains\JobRequest\Requests\JobRequestRequest;
use App\Domains\JobRequest\Resources\JobRequestResource;
use App\Domains\JobRequest\Services\JobRequestService;
use Illuminate\Http\Request;

class JobRequestController extends BaseApiController
{
    public function index(Request $request, JobRequestService $service)
    {
        $data = $service->getList(
            filters: $request->only(['search', 'status', 'department', 'priority']),
            perPage: (int) $request->get('per_page', $request->get('limit', 50))
        );

        return $this->success(
            JobRequestResource::collection($data),
            'Data job request berhasil diambil'
        );
    }

    public function show(string $id, JobRequestService $service)
    {
        return $this->success(
            new JobRequestResource($service->getDetail($id)),
            'Detail job request berhasil diambil'
        );
    }

    public function store(JobRequestRequest $request, JobRequestService $service)
    {
        return $this->success(
            new JobRequestResource($service->createJobRequest($request->validated())),
            'Job request berhasil dibuat'
        );
    }

    public function update(string $id, JobRequestRequest $request, JobRequestService $service)
    {
        return $this->success(
            new JobRequestResource($service->updateJobRequest($id, $request->validated())),
            'Job request berhasil diperbarui'
        );
    }

    public function destroy(string $id, JobRequestService $service)
    {
        $service->delete($id);

        return $this->success(null, 'Job request berhasil dihapus');
    }

    public function approve(string $id, Request $request, JobRequestService $service)
    {
        return $this->success(
            new JobRequestResource($service->approve($id, $request->input('reviewer_notes'))),
            'Job request berhasil disetujui'
        );
    }

    public function reject(string $id, Request $request, JobRequestService $service)
    {
        return $this->success(
            new JobRequestResource($service->reject($id, $request->input('reviewer_notes'))),
            'Job request berhasil ditolak'
        );
    }
}
