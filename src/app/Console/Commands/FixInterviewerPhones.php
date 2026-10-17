<?php

namespace App\Console\Commands;

use App\Domains\Interviewer\Models\Interviewer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Pulihkan leading zero nomor telepon interviewer yang hilang akibat bug
 * BaseResource lama (string numerik "0812..." dikirim sebagai 812...).
 *
 * Hanya menyentuh nomor yang diawali "8" dan seluruhnya digit — format lokal
 * Indonesia tanpa 0. Nomor "0...", "62...", "+62..." atau yang mengandung
 * spasi/strip tidak diubah. Aman dijalankan berulang (idempotent).
 */
class FixInterviewerPhones extends Command
{
    protected $signature = 'interviewers:fix-phones {--apply : Simpan perubahan (default hanya preview)}';

    protected $description = 'Tambahkan kembali leading zero pada nomor telepon interviewer yang hilang';

    public function handle(): int
    {
        $rows = Interviewer::query()
            ->whereNotNull('phone')
            ->get(['id', 'name', 'phone'])
            ->filter(fn (Interviewer $i) => preg_match('/^8\d{7,12}$/', $i->phone) === 1);

        if ($rows->isEmpty()) {
            $this->info('Tidak ada nomor telepon yang perlu diperbaiki.');
            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Nama', 'Sekarang', 'Menjadi'],
            $rows->map(fn ($i) => [$i->id, $i->name, $i->phone, '0' . $i->phone])->all()
        );

        if (! $this->option('apply')) {
            $this->warn("{$rows->count()} nomor akan diperbaiki. Jalankan ulang dengan --apply untuk menyimpan.");
            return self::SUCCESS;
        }

        DB::transaction(function () use ($rows) {
            foreach ($rows as $i) {
                $i->update(['phone' => '0' . $i->phone]);
            }
        });

        $this->info("{$rows->count()} nomor telepon diperbaiki.");
        return self::SUCCESS;
    }
}
