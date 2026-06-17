<?php

namespace App\Domains\Application\Actions;
 
use App\Domains\Application\Events\ApplicationBundlingRequested;
use Illuminate\Support\Facades\DB;
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
        $exists = DB::table('applications')->where('id', $applicationId)->exists();

        if (!$exists) {
            throw new InvalidArgumentException("Data pelamar dengan ID {$applicationId} tidak ditemukan.");
        }

        
        // Memicu event untuk menandakan bahwa proses bundling telah diminta
        event(new ApplicationBundlingRequested($applicationId));
    }
}