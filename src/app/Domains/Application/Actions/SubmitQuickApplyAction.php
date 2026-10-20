<?php

namespace App\Domains\Application\Actions;

use App\Core\Services\FileUploadService;
use App\Domains\Application\DTO\ApplicationDTO;
use App\Domains\Application\Services\ApplicationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

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
        private FileUploadService $files,
    ) {}

    public function execute(ApplicationDTO $dto, UploadedFile $cv, UploadedFile $foto): array
    {
        // Tolak duplikat lebih dulu agar tidak membuang waktu konversi/unggah.
        $this->service->ensureCanApply($dto->toArray());

        $documents = [];

        try {
            $documents['cv'] = $this->uploadOrFail('cv', 'CV gagal diproses. Coba unggah ulang dalam format PDF.',
                fn () => $this->files->uploadCvAsPdf($cv, 'applications/cv'));
            $documents['foto'] = $this->uploadOrFail('foto', 'Foto gagal diunggah. Silakan coba lagi.',
                fn () => $this->files->upload($foto, 'applications/foto'));

            return $this->submit->execute($dto, $documents);
        } catch (\Throwable $e) {
            $this->cleanup($documents);
            throw $e;
        }
    }

    private function uploadOrFail(string $field, string $message, callable $upload): array
    {
        try {
            return $upload();
        } catch (\Throwable $e) {
            report($e);
            throw ValidationException::withMessages([$field => $message]);
        }
    }

    /** @param  array<string, array>  $documents */
    private function cleanup(array $documents): void
    {
        foreach ($documents as $document) {
            if (empty($document['path'])) {
                continue;
            }
            try {
                $this->files->delete($document['path']);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}
