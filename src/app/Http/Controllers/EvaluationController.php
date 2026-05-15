<?php

namespace App\Http\Controllers;

use App\Core\Http\Controllers\BaseApiController;
use App\Domains\Evaluation\Requests\EvaluationRequest;
use App\Domains\Evaluation\Resources\EvaluationResource;
use App\Domains\Evaluation\Services\EvaluationService;
use Illuminate\Http\Request;

class EvaluationController extends BaseApiController
{
    public function index(Request $request, EvaluationService $service)
    {
        $data = $service->getList(
            filters: $request->only(['search', 'applicant_id', 'recommendation']),
            perPage: (int) $request->get('per_page', $request->get('limit', 50))
        );

        return $this->success(
            EvaluationResource::collection($data),
            'Data evaluasi berhasil diambil'
        );
    }

    public function show(string $id, EvaluationService $service)
    {
        return $this->success(
            new EvaluationResource($service->getDetail($id)),
            'Detail evaluasi berhasil diambil'
        );
    }

    public function store(EvaluationRequest $request, EvaluationService $service)
    {
        return $this->success(
            new EvaluationResource($service->createEvaluation($request->validated())),
            'Evaluasi berhasil ditambahkan'
        );
    }

    public function update(string $id, EvaluationRequest $request, EvaluationService $service)
    {
        return $this->success(
            new EvaluationResource($service->updateEvaluation($id, $request->validated())),
            'Evaluasi berhasil diperbarui'
        );
    }

    public function destroy(string $id, EvaluationService $service)
    {
        $service->delete($id);

        return $this->success(null, 'Evaluasi berhasil dihapus');
    }
}
