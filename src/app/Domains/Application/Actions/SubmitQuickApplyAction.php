<?php

namespace App\Domains\Application\Actions;

use App\Domains\Application\DTO\ApplicationDTO;
use App\Domains\Application\Services\ApplicantFileUploader;
use App\Domains\Application\Services\ApplicationService;
use Illuminate\Http\UploadedFile;

/**
 * Quick Apply: lamaran hanya tersimpan bila CV & foto berhasil diunggah.
 *
 * Urutan: cek email duplikat → unggah CV (DOC/DOCX dikonversi ke PDF) → unggah
 * foto → simpan lamaran beserta `documents`. Bila langkah mana pun gagal, file
 * yang sudah terunggah dihapus kembali sehingga tidak ada data setengah jadi.
 */
class SubmitQuickApplyAction
{
    public function __construct(
        private SubmitApplicationAction $submit,
        private ApplicationService $service,
        private ApplicantFileUploader $uploader,
    ) {}

    public function execute(ApplicationDTO $dto, UploadedFile $cv, UploadedFile $foto): array
    {
        // Tolak duplikat lebih dulu agar tidak membuang waktu konversi/unggah.
        $this->service->ensureCanApply($dto->toArray());

        $documents = $this->uploader->uploadAll(['cv' => $cv, 'foto' => $foto]);

        try {
            return $this->submit->execute($dto, $documents);
        } catch (\Throwable $e) {
            $this->uploader->delete($documents);
            throw $e;
        }
    }
}
