<?php

namespace App\Http\Controllers;

use App\Core\Http\Controllers\BaseApiController;
use App\Domains\Interviewer\Requests\InterviewerRequest;
use App\Domains\Interviewer\Resources\InterviewerResource;
use App\Domains\Interviewer\Services\InterviewerService;
use Illuminate\Http\Request;

class InterviewerController extends BaseApiController
{
    public function index(Request $request, InterviewerService $service)
    {
        $data = $service->getList(
            filters: $request->only(['search', 'role', 'department', 'active']),
            perPage: (int) $request->get('per_page', $request->get('limit', 50))
        );

        return $this->success(
            InterviewerResource::collection($data),
            'Data interviewer berhasil diambil'
        );
    }

    public function show(string $id, InterviewerService $service)
    {
        return $this->success(
            new InterviewerResource($service->getDetail($id)),
            'Detail interviewer berhasil diambil'
        );
    }

    public function store(InterviewerRequest $request, InterviewerService $service)
    {
        return $this->success(
            new InterviewerResource($service->createInterviewer($request->validated())),
            'Interviewer berhasil ditambahkan'
        );
    }

    public function update(string $id, InterviewerRequest $request, InterviewerService $service)
    {
        return $this->success(
            new InterviewerResource($service->updateInterviewer($id, $request->validated())),
            'Interviewer berhasil diperbarui'
        );
    }

    public function destroy(string $id, InterviewerService $service)
    {
        $service->delete($id);

        return $this->success(null, 'Interviewer berhasil dihapus');
    }
}
