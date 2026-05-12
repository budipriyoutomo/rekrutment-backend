<?php

namespace App\Http\Controllers;

use App\Core\Http\Controllers\BaseApiController;
use App\Domains\Interview\Requests\InterviewRequest;
use App\Domains\Interview\Resources\InterviewResource;
use App\Domains\Interview\Services\InterviewService;
use Illuminate\Http\Request;

class InterviewController extends BaseApiController
{
    public function index(Request $request, InterviewService $service)
    {
        $data = $service->getList(
            filters: $request->only(['status', 'type', 'date', 'startDate', 'endDate', 'search']),
            perPage: (int) $request->get('per_page', $request->get('limit', 50))
        );

        return $this->success(
            InterviewResource::collection($data),
            'Data interview berhasil diambil'
        );
    }

    public function show(string $id, InterviewService $service)
    {
        return $this->success(
            new InterviewResource($service->getDetail($id)),
            'Detail interview berhasil diambil'
        );
    }

    public function store(InterviewRequest $request, InterviewService $service)
    {
        $interview = $service->createSchedule($request->validated());

        return $this->success(
            new InterviewResource($interview),
            'Interview berhasil dijadwalkan'
        );
    }

    public function update(string $id, InterviewRequest $request, InterviewService $service)
    {
        return $this->success(
            new InterviewResource($service->updateSchedule($id, $request->validated())),
            'Interview berhasil diperbarui'
        );
    }

    public function destroy(string $id, InterviewService $service)
    {
        $service->delete($id);

        return $this->success(null, 'Interview berhasil dihapus');
    }

    public function sendInvitation(string $id, InterviewService $service)
    {
        return $this->success(
            new InterviewResource($service->sendInvitation($id)),
            'Email invitation berhasil dikirim'
        );
    }
}
