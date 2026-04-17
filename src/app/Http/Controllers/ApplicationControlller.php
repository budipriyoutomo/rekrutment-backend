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
        $request->validate([
            'file' => ['required', 'file'],
            'type' => ['required', 'in:cv,photo,idCard,diploma']
        ]);

        $result = $service->upload($request->file('file'), $request->type);

        return $this->success($result, 'Upload berhasil');
    }

    public function status($id)
    {
        $app = Application::findOrFail($id);

        return $this->success([
            'status' => $app->status,
            'updatedAt' => $app->updated_at,
        ]);
    }
}