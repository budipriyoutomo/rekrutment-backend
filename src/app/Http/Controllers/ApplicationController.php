<?php

namespace App\Http\Controllers;

use App\Core\Http\Controllers\BaseApiController;
use App\Domains\Application\Requests\SubmitApplicationRequest;
use App\Domains\Application\DTO\ApplicationDTO;
use App\Domains\Application\Actions\SubmitApplicationAction;
use App\Domains\Application\Actions\BundleApplicationDocumentAction;
use App\Core\Services\FileUploadService;
use App\Domains\Application\Models\Application;
use App\Domains\ProfileCompletion\Actions\SendProfileCompletionEmailAction;
use Illuminate\Http\Request;
use App\Domains\Application\Services\ApplicationService;
use App\Domains\Application\Resources\ApplicationResource;
use App\Domains\Application\Resources\ApplicationBundleResource;
use Illuminate\Support\Facades\Log;
use Exception;


class ApplicationController extends BaseApiController
{

    public function index(Request $request, ApplicationService $service)
    {
        $data = $service->getList(
            filters: $request->only([
                'status',
                'stage',
                'search',
                'startDate',
                'endDate',
                'gender',
                'position',
                'workLocation',
                'jobSource',
                'hasVehicle',
                'workedBefore',
                'domicile',
                'ageMin',
                'ageMax',
            ]),
            perPage: $request->get('per_page', $request->get('limit', 10))
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

        $result = $validated['type'] === 'cv'
            ? $service->uploadCvAsPdf($validated['file'], $directory)
            : $service->upload($validated['file'], $directory);

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
            ->select(['id', 'stage', 'updated_at'])
            ->findOrFail($id);

        return $this->success([
            'status' => $app->stage,
            'stage' => $app->stage,
            'updatedAt' => $app->updated_at,
        ]);
    }

    public function updateStatus(string $id, Request $request, ApplicationService $service)
    {
        $validated = $request->validate([
            'stage' => [
                'required',
                'in:applied,screening,profile_completion,interview,offer,hired,rejected,on_hold'

            ]
        ]);

        $result = $service->updateStatus($id, $validated['stage']);

        return $this->success($result, 'Status berhasil diperbarui');
    }

    public function addNote(string $id, Request $request, ApplicationService $service)
    {
        $validated = $request->validate([
            'text' => ['required', 'string', 'max:5000'],
        ]);

        $result = $service->addNote($id, $validated['text']);

        return $this->success(
            new ApplicationResource($result),
            'Catatan berhasil ditambahkan'
        );
    }

    public function sendProfileCompletion(string $id, SendProfileCompletionEmailAction $action)
    {
        $app = Application::findOrFail($id);

        try {
            $action->execute($app);

            return $this->success(null, 'Email profile completion berhasil dikirim.');
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (Exception $e) {
            Log::error('Gagal mengirim email profile completion: ' . $e->getMessage(), [
                'application_id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengirim email. Silakan coba lagi.',
            ], 500);
        }
    }

    public function bundleDocuments(string $id, BundleApplicationDocumentAction $action)
    {
        try {
            $action->execute($id);

            $app = Application::with(['educations', 'experiences', 'certifications'])->findOrFail($id);

        // Gunakan ApplicationBundleResource di sini
            return $this->success(
                new ApplicationBundleResource($app), 
                'Proses penggabungan berkas pelamar berhasil dimasukkan ke antrean. File final akan segera siap di S3.'
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);

        } catch (Exception $e) {
            Log::error("Gagal memicu bundling pada ApplicationController: " . $e->getMessage(), [
                'application_id' => $id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem internal saat mempersiapkan dokumen.'
            ], 500);
        }
    }

    public function bundleStatus(string $id)
    {
        $app = Application::findOrFail($id);
        $bundle = $app->documents['bundle'] ?? null;

        if (!$bundle) {
            return $this->success([
                'status' => 'not_requested',
                'ready' => false,
                'path' => null,
                'file_url' => null,
                'file_name' => null,
                'mime_type' => 'application/pdf',
                'size' => null,
                'message' => 'Bundle document belum dibuat.',
            ]);
        }

        return $this->success([
            'status' => $bundle['status'] ?? 'processing',
            'ready' => ($bundle['status'] ?? null) === 'ready',
            'path' => $bundle['path'] ?? null,
            'file_url' => $bundle['file_url'] ?? null,
            'file_name' => $bundle['file_name'] ?? null,
            'mime_type' => $bundle['mime_type'] ?? 'application/pdf',
            'size' => $bundle['size'] ?? null,
            'message' => $bundle['message'] ?? null,
            'generated_at' => $bundle['generated_at'] ?? null,
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
