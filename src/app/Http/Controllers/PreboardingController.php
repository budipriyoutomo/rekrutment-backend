<?php

namespace App\Http\Controllers;

use App\Core\Http\Controllers\BaseApiController;
use App\Domains\Preboarding\Models\Preboarding;
use App\Domains\Preboarding\Resources\PreboardingResource;
use App\Domains\Preboarding\Services\PreboardingService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PreboardingController extends BaseApiController
{
    public function index(Request $request, PreboardingService $service)
    {
        $data = $service->getList(
            filters: $request->only(['status', 'applicationId', 'search']),
            perPage: (int) $request->get('per_page', $request->get('limit', 50))
        );

        return $this->successPaginated(PreboardingResource::collection($data), $data, 'Data pre-boarding berhasil diambil');
    }

    public function show(string $id, PreboardingService $service)
    {
        return $this->success(new PreboardingResource($service->getDetail($id)), 'Detail pre-boarding berhasil diambil');
    }

    public function store(Request $request, PreboardingService $service)
    {
        $validated = $request->validate(['application_id' => ['required', 'uuid', 'exists:applications,id']]);

        return $this->success(
            new PreboardingResource($service->startForApplication($validated['application_id'])),
            'Link pre-boarding sedang dikirim ke kandidat'
        );
    }

    public function resend(string $id, PreboardingService $service)
    {
        return $this->success(new PreboardingResource($service->resend($id)), 'Link pre-boarding dikirim ulang');
    }

    public function reviewDocument(string $id, string $type, Request $request, PreboardingService $service)
    {
        abort_unless(array_key_exists($type, Preboarding::REQUIRED_DOCUMENTS), 404);

        $validated = $request->validate([
            'status' => ['required', Rule::in([Preboarding::DOC_VALID, Preboarding::DOC_REJECTED])],
            'note' => ['required_if:status,'.Preboarding::DOC_REJECTED, 'nullable', 'string', 'max:500'],
        ], ['note.required_if' => 'Tulis alasan agar kandidat tahu apa yang harus diperbaiki.']);

        return $this->success(
            new PreboardingResource($service->reviewDocument($id, $type, $validated['status'], $validated['note'] ?? null)),
            'Status dokumen diperbarui'
        );
    }

    public function requestRevision(string $id, PreboardingService $service)
    {
        return $this->success(new PreboardingResource($service->requestRevision($id)), 'Permintaan revisi dikirim ke kandidat');
    }

    public function downloadDocument(string $id, string $type, PreboardingService $service)
    {
        abort_unless(array_key_exists($type, Preboarding::REQUIRED_DOCUMENTS), 404);

        return $service->downloadDocument($id, $type);
    }
}
