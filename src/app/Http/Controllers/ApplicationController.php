<?php

namespace App\Http\Controllers;

use App\Core\Http\Controllers\BaseApiController;
use App\Domains\Application\Requests\SubmitApplicationRequest;
use App\Domains\Application\DTO\ApplicationDTO;
use App\Domains\Application\Actions\SubmitApplicationAction;
use App\Core\Services\FileUploadService;
use App\Domains\Application\Models\Application;
use Illuminate\Http\Request;
use App\Domains\Application\Services\ApplicationService;
use App\Domains\Application\Resources\ApplicationResource;

class ApplicationController extends BaseApiController
{

    public function index(Request $request, ApplicationService $service)
    {
        $data = $service->getList(
            filters: $request->only(['status', 'search', 'startDate', 'endDate']),
            perPage: $request->get('per_page', 10)
        );

        return $this->success(
            ApplicationResource::collection($data)
        );
    }

    public function show(string $id, ApplicationService $service)
    {
        $data = $service->getDetail($id);

        return $this->success(
            new ApplicationResource($data)
        );
    }

    public function submit(
        SubmitApplicationRequest $request,
        SubmitApplicationAction $action
    ) {
        $dto = ApplicationDTO::fromRequest($request);

        $result = $action->execute($dto);

        return $this->success($result, 'Lamaran berhasil dikirim');
    }

    public function upload(Request $request, FileUploadService $service)
    {
        $validated = $request->validate([
            'file' => [
                'required',
                'file',
                'max:2048',
                'mimes:pdf,jpg,jpeg,png,doc,docx'
            ],
            'type' => [
                'required',
                'in:cv,foto,ktp,ijazah,others'
            ],
            'application_id' => [
                'required',
                'uuid',
                'exists:applications,id'
            ],

        ]);

        // mapping folder biar tidak hardcoded di FE
        $directory = $this->resolveDirectory($validated['type']);

        $result = $service->upload(
            $validated['file'],
            $directory
        );

        $app = Application::findOrFail($validated['application_id']);

        $documents = $app->documents ?? [];

        $documents[$validated['type']] = $result;

        $app->update([
            'documents' => $documents
        ]);

        return $this->success($result, 'Upload berhasil');
    }

    public function status(string $id)
    {
        $app = Application::query()
            ->select(['id', 'status', 'updated_at'])
            ->findOrFail($id);

        return $this->success([
            'status' => $app->status,
            'updatedAt' => $app->updated_at,
        ]);
    }

    public function updateStatus(string $id, Request $request, ApplicationService $service)
    {
        $validated = $request->validate([
            'status' => [
                'required',
                'in:applied, screening, interview, final_interview, offer, hired, rejected'

            ]
        ]);

        $result = $service->updateStatus($id, $validated['status']);

        return $this->success($result, 'Status berhasil diperbarui');
    }

    /**
     * Mapping type → directory
     */
    protected function resolveDirectory(string $type): string
    {
        return match ($type) {
            'cv' => 'applications/cv',
            'foto' => 'applications/foto',
            'ktp' => 'applications/ktp',
            'ijazah' => 'applications/ijazah',
            default => 'applications/others',
        };
    }
}