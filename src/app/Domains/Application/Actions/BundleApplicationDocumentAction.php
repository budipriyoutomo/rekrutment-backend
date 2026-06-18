<?php

namespace App\Domains\Application\Actions;
 
use App\Domains\Application\Events\ApplicationBundlingRequested;
use App\Domains\Application\Models\Application;
use InvalidArgumentException;

class BundleApplicationDocumentAction
{
    /**
     * Mengeksekusi validasi awal data dan memicu background job antrean.
     *
     * @param string $applicationId UUID / ID dari aplikasi pelamar
     * @return void
     * @throws InvalidArgumentException
     */
    public function execute(string $applicationId): void
    {
        // Validasi awal di database Postgres untuk memastikan ID valid
        $application = Application::find($applicationId);

        if (!$application) {
            throw new InvalidArgumentException("Data pelamar dengan ID {$applicationId} tidak ditemukan.");
        }

        $documents = $application->documents ?? [];
        $documents['bundle'] = [
            'status' => 'processing',
            'path' => null,
            'file_url' => null,
            'file_name' => "bundle_{$applicationId}.pdf",
            'mime_type' => 'application/pdf',
            'size' => null,
            'message' => 'Bundle document sedang diproses.',
            'generated_at' => null,
        ];

        $application->update([
            'documents' => $documents,
        ]);
        
        // Memicu event untuk menandakan bahwa proses bundling telah diminta
        event(new ApplicationBundlingRequested($applicationId));
    }
}
