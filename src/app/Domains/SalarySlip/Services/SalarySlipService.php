<?php

namespace App\Domains\SalarySlip\Services;

use App\Domains\SalarySlip\DTO\SalarySlipDTO;
use App\Domains\SalarySlip\Models\SalarySlip;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class SalarySlipService
{
    public function getList(array $filters = [], int $perPage = 50): LengthAwarePaginator
    {
        $query = SalarySlip::query()->latest();

        // Partisi menu: 'unsent' = belum pernah terkirim (menu Salary Slips),
        // 'sent' = sudah pernah terkirim (menu Report Salary Slip). Memakai
        // sent_at agar slip yang sedang dikirim-ulang (status queued/processing)
        // tetap berada di menu Report, tidak lompat balik ke Salary Slips.
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'sent') {
                $query->whereNotNull('sent_at');
            } elseif ($filters['status'] === 'unsent') {
                $query->whereNull('sent_at');
            }
        }

        if (!empty($filters['periode'])) {
            $query->where('periode', $filters['periode']);
        }
        if (!empty($filters['cabang'])) {
            $query->where('cabang', 'like', '%' . $filters['cabang'] . '%');
        }
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('nik', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage);
    }

    public function store(SalarySlipDTO $dto): SalarySlip
    {
        return SalarySlip::create($dto->toArray());
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

    public function bulkInsert(array $dtos): int
    {
        $rows = array_map(fn(SalarySlipDTO $dto) => array_merge($dto->toArray(), [
            'id'         => \Illuminate\Support\Str::uuid()->toString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]), $dtos);

        SalarySlip::insert($rows);

        return count($rows);
    }

    public function bulkDelete(array $ids): int
    {
        return SalarySlip::whereIn('id', $ids)->delete();
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
