<?php

namespace App\Domains\Application\Services;

use App\Core\Services\FileUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Unggah dokumen pelamar (cv/foto/ktp/ijazah) sekaligus, semua-atau-tidak:
 * bila satu file gagal, file yang sudah terunggah di batch itu dihapus lagi
 * dan error dilaporkan pada field-nya (422) agar pelamar bisa mencoba ulang.
 */
class ApplicantFileUploader
{
    private const DIRECTORIES = [
        'cv' => 'applications/cv',
        'foto' => 'applications/foto',
        'ktp' => 'applications/ktp',
        'ijazah' => 'applications/ijazah',
    ];

    private const FAILURE_MESSAGES = [
        'cv' => 'CV gagal diproses. Coba unggah ulang dalam format PDF.',
        'foto' => 'Foto gagal diunggah. Silakan coba lagi.',
        'ktp' => 'KTP gagal diunggah. Silakan coba lagi.',
        'ijazah' => 'Ijazah gagal diunggah. Silakan coba lagi.',
    ];

    public function __construct(private FileUploadService $files) {}

    /**
     * @param  array<string, UploadedFile|null>  $files  per tipe dokumen
     * @return array<string, array> metadata hasil unggah per tipe
     */
    public function uploadAll(array $files): array
    {
        $documents = [];

        foreach (array_filter($files) as $type => $file) {
            try {
                $directory = self::DIRECTORIES[$type] ?? 'applications/others';
                $documents[$type] = $type === 'cv'
                    ? $this->files->uploadCvAsPdf($file, $directory)
                    : $this->files->upload($file, $directory);
            } catch (\Throwable $e) {
                report($e);
                $this->delete($documents);
                throw ValidationException::withMessages([
                    $type => self::FAILURE_MESSAGES[$type] ?? 'File gagal diunggah. Silakan coba lagi.',
                ]);
            }
        }

        return $documents;
    }

    /**
     * Hapus file dokumen (best effort — kegagalan hanya dilaporkan).
     *
     * @param  array<string, array|null>  $documents
     */
    public function delete(array $documents): void
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
