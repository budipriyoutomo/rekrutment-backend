<?php

namespace App\Http\Controllers;

use App\Core\Http\Controllers\BaseApiController;
use App\Domains\Assessment\Requests\AssessmentRequest;
use App\Domains\Assessment\Requests\AssignAssessmentRequest;
use App\Domains\Assessment\Resources\AssessmentAssignmentResource;
use App\Domains\Assessment\Resources\AssessmentResource;
use App\Domains\Assessment\Services\AssessmentService;
use Illuminate\Http\Request;
use RuntimeException;

class AssessmentController extends BaseApiController
{
    /*
    |--------------------------------------------------------------------------
    | PAKET TES
    |--------------------------------------------------------------------------
    */

    public function index(Request $request, AssessmentService $service)
    {
        $data = $service->getList(
            filters: $request->only(['is_active', 'search']),
            perPage: (int) $request->get('per_page', $request->get('limit', 50))
        );

        return $this->success(
            AssessmentResource::collection($data),
            'Data paket tes berhasil diambil'
        );
    }

    public function show(string $id, AssessmentService $service)
    {
        return $this->success(
            new AssessmentResource($service->getDetail($id)),
            'Detail paket tes berhasil diambil'
        );
    }

    public function store(AssessmentRequest $request, AssessmentService $service)
    {
        return $this->success(
            new AssessmentResource($service->createAssessment($request->validated())),
            'Paket tes berhasil dibuat'
        );
    }

    public function update(string $id, AssessmentRequest $request, AssessmentService $service)
    {
        return $this->success(
            new AssessmentResource($service->updateAssessment($id, $request->validated())),
            'Paket tes berhasil diperbarui'
        );
    }

    public function destroy(string $id, AssessmentService $service)
    {
        try {
            $service->deleteAssessment($id);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 409);
        }

        return $this->success(null, 'Paket tes berhasil dihapus');
    }

    /*
    |--------------------------------------------------------------------------
    | PENUGASAN KE KANDIDAT
    |--------------------------------------------------------------------------
    */

    public function assign(string $id, AssignAssessmentRequest $request, AssessmentService $service)
    {
        try {
            $assignment = $service->assignToApplicant($id, $request->validated()['application_id']);
        } catch (\InvalidArgumentException | RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success(
            new AssessmentAssignmentResource($assignment),
            'Undangan tes berhasil dikirim ke kandidat'
        );
    }

    public function assignments(Request $request, AssessmentService $service)
    {
        $data = $service->getAssignments(
            filters: $request->only(['status', 'assessment_id', 'application_id', 'passed']),
            perPage: (int) $request->get('per_page', $request->get('limit', 50))
        );

        return $this->success(
            AssessmentAssignmentResource::collection($data),
            'Data hasil tes berhasil diambil'
        );
    }

    public function assignmentDetail(string $id, AssessmentService $service)
    {
        return $this->success(
            new AssessmentAssignmentResource($service->getAssignmentDetail($id)),
            'Detail hasil tes berhasil diambil'
        );
    }
}
