<?php

namespace App\Domains\SalarySlip\Services;

use App\Domains\SalarySlip\DTO\SalarySlipDTO;
use App\Domains\SalarySlip\Models\SalarySlip;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SalarySlipService
{
    public function getList(array $filters = [], int $perPage = 50): LengthAwarePaginator
    {
        $query = SalarySlip::query()->latest();

        // Partisi menu: 'unsent' = belum pernah terkirim (menu Salary Slips),
        // 'sent' = sudah pernah terkirim (menu Report Salary Slip). Memakai
        // sent_at agar slip yang sedang dikirim-ulang (status queued/processing)
        // tetap berada di menu Report, tidak lompat balik ke Salary Slips.
        if (! empty($filters['status'])) {
            if ($filters['status'] === 'sent') {
                $query->whereNotNull('sent_at');
            } elseif ($filters['status'] === 'unsent') {
                $query->whereNull('sent_at');
            }
        }

        if (! empty($filters['periode'])) {
            $query->where('periode', $filters['periode']);
        }
        // pgsql: LIKE peka huruf besar/kecil → pakai ILIKE (sqlite LIKE sudah tidak peka).
        $like = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        if (! empty($filters['cabang'])) {
            $query->where('cabang', $like, '%'.$filters['cabang'].'%');
        }
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search, $like) {
                $q->where('nama', $like, "%{$search}%")
                    ->orWhere('nik', $like, "%{$search}%")
                    ->orWhere('email', $like, "%{$search}%");
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * Kembalikan set kombinasi "nik|periode" yang SUDAH ada di DB,
     * dibatasi pada nik & periode yang muncul di file (agar query ringan).
     *
     * @param  string[]  $niks
     * @param  string[]  $periodes
     * @return array<string,bool> key = "nik|periode"
     */
    public function existingNikPeriodeKeys(array $niks, array $periodes): array
    {
        if (empty($niks) || empty($periodes)) {
            return [];
        }

        return SalarySlip::query()
            ->whereIn('nik', array_values(array_unique($niks)))
            ->whereIn('periode', array_values(array_unique($periodes)))
            ->get(['nik', 'periode'])
            ->mapWithKeys(fn ($s) => ["{$s->nik}|{$s->periode}" => true])
            ->all();
    }

    /**
     * Insert massal. Bila ada baris yang bentrok unique (nik, periode) — mis. import
     * paralel yang lolos pengecekan awal — insert diulang per baris dan index baris
     * yang bentrok dikembalikan di `duplicates` (index sesuai urutan $dtos).
     *
     * @param  SalarySlipDTO[]  $dtos
     * @return array{inserted:int, duplicates:int[]}
     */
    public function bulkInsert(array $dtos): array
    {
        $rows = array_map(fn (SalarySlipDTO $dto) => array_merge($dto->toArray(), [
            'id' => Str::uuid()->toString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]), $dtos);

        try {
            SalarySlip::insert($rows);

            return ['inserted' => count($rows), 'duplicates' => []];
        } catch (UniqueConstraintViolationException) {
            return $this->insertRowByRow($rows);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{inserted:int, duplicates:int[]}
     */
    private function insertRowByRow(array $rows): array
    {
        $inserted = 0;
        $duplicates = [];

        foreach ($rows as $index => $row) {
            try {
                // Savepoint per baris agar kegagalan tidak membatalkan transaksi luar (pgsql).
                DB::transaction(fn () => SalarySlip::insert($row));
                $inserted++;
            } catch (UniqueConstraintViolationException) {
                $duplicates[] = $index;
            }
        }

        return ['inserted' => $inserted, 'duplicates' => $duplicates];
    }

    /**
     * Hapus slip beserta PDF hasil generate-nya (storage/app/payslips/{id}.pdf).
     */
    public function bulkDelete(array $ids): int
    {
        $existingIds = SalarySlip::whereIn('id', $ids)->pluck('id')->all();
        $deleted = SalarySlip::whereIn('id', $existingIds)->delete();

        foreach ($existingIds as $id) {
            $pdf = storage_path("app/payslips/{$id}.pdf");
            if (is_file($pdf)) {
                @unlink($pdf);
            }
        }

        return $deleted;
    }

    public function getByIds(array $ids): Collection
    {
        return SalarySlip::whereIn('id', $ids)->get();
    }

    public function markAsSent(array $ids): void
    {
        SalarySlip::whereIn('id', $ids)->update(['sent_at' => now()]);
    }
}
