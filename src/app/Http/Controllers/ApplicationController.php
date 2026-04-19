<?php

namespace App\Http\Controllers;

use App\Core\Http\Controllers\BaseApiController;
use App\Domains\Application\Requests\SubmitApplicationRequest;
use App\Domains\Application\DTO\ApplicationDTO;
use App\Domains\Application\Actions\SubmitApplicationAction;
use App\Core\Services\FileUploadService;
use App\Domains\Application\Models\Application;
use Illuminate\Http\Request;

class ApplicationController extends BaseApiController
{
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
            ]
        ]);

        // mapping folder biar tidak hardcoded di FE
        $directory = $this->resolveDirectory($validated['type']);

        $result = $service->upload(
            $validated['file'],
            $directory
        );

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