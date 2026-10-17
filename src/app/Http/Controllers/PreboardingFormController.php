<?php

namespace App\Http\Controllers;

use App\Core\Http\Controllers\BaseApiController;
use App\Domains\Preboarding\Models\Preboarding;
use App\Domains\Preboarding\Requests\SubmitPreboardingRequest;
use App\Domains\Preboarding\Resources\PublicPreboardingResource;
use App\Domains\Preboarding\Services\PreboardingSubmissionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Endpoint publik untuk kandidat mengisi data karyawan baru lewat token dari email. */
class PreboardingFormController extends BaseApiController
{
    public function show(string $token, PreboardingSubmissionService $service)
    {
        return $this->guarded(fn () => $this->success(
            new PublicPreboardingResource($service->findByToken($token)),
            'Token valid'
        ));
    }

    public function upload(string $token, Request $request, PreboardingSubmissionService $service)
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(array_keys(Preboarding::REQUIRED_DOCUMENTS))],
            'file' => ['required', 'file', 'max:4096', 'mimes:pdf,jpg,jpeg,png'],
        ], [
            'file.max'   => 'Ukuran file maksimal 4MB.',
            'file.mimes' => 'Format file harus PDF, JPG, atau PNG.',
        ]);

        return $this->guarded(fn () => $this->success(
            new PublicPreboardingResource($service->uploadDocument($token, $validated['type'], $validated['file'])),
            'Dokumen berhasil diunggah'
        ));
    }

    public function submit(string $token, SubmitPreboardingRequest $request, PreboardingSubmissionService $service)
    {
        return $this->guarded(fn () => $this->success(
            new PublicPreboardingResource($service->submit($token, $request->validated())),
            'Data berhasil dikirim. Tim HR akan meninjaunya.'
        ));
    }

    private function guarded(callable $callback)
    {
        try {
            return $callback();
        } catch (HttpException $e) {
            return $this->error($e->getMessage(), $e->getStatusCode());
        }
    }
}
