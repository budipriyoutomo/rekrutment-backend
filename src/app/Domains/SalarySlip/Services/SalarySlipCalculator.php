<?php

namespace App\Domains\SalarySlip\Services;

/**
 * Validasi konsistensi nilai total terhadap komponen.
 * Sesuai keputusan bisnis: total diambil dari file APA ADANYA, kalkulator ini
 * hanya menghasilkan WARNING (bukan error) bila tidak match — tidak menolak baris.
 */
class SalarySlipCalculator
{
    /** Komponen penjumlah total_penerimaan. */
    public const PENERIMAAN_COMPONENTS = [
        'gaji_pokok', 'tunjangan_jabatan', 'tunjangan_makan', 'tunjangan_transport',
        'tunjangan_lain', 'lembur', 'tambahan_gaji', 'ph_dibayar', 'refund_seragam',
        'jumlah_service_charge',
    ];

    /** Komponen penjumlah total_potongan. */
    public const POTONGAN_COMPONENTS = [
        'total_pot_absen', 'bpjs_ketenagakerjaan', 'bpjs_kesehatan', 'pinjaman',
        'pph21', 'potongan_seragam', 'koreksi',
    ];

    /**
     * @param  array<string,mixed>  $row
     * @return string[] daftar warning (kosong = konsisten)
     */
    public function warnings(array $row): array
    {
        $warnings = [];

        $sumPenerimaan = $this->sum($row, self::PENERIMAAN_COMPONENTS);
        $sumPotongan   = $this->sum($row, self::POTONGAN_COMPONENTS);

        if ($this->has($row, 'total_penerimaan')) {
            $filed = (int) $row['total_penerimaan'];
            if ($filed !== $sumPenerimaan) {
                $warnings[] = "Total Penerimaan di file ({$filed}) tidak sama dengan penjumlahan komponen ({$sumPenerimaan}).";
            }
        }

        if ($this->has($row, 'total_potongan')) {
            $filed = (int) $row['total_potongan'];
            if ($filed !== $sumPotongan) {
                $warnings[] = "Total Potongan di file ({$filed}) tidak sama dengan penjumlahan komponen ({$sumPotongan}).";
            }
        }

        if ($this->has($row, 'take_home_pay')) {
            $filed = (int) $row['take_home_pay'];
            // pakai total dari file bila ada, jika tidak pakai hasil penjumlahan
            $penerimaan = $this->has($row, 'total_penerimaan') ? (int) $row['total_penerimaan'] : $sumPenerimaan;
            $potongan   = $this->has($row, 'total_potongan') ? (int) $row['total_potongan'] : $sumPotongan;
            $expected   = $penerimaan - $potongan;
            if ($filed !== $expected) {
                $warnings[] = "Take Home Pay di file ({$filed}) tidak sama dengan Total Penerimaan - Total Potongan ({$expected}).";
            }
        }

        return $warnings;
    }

    private function sum(array $row, array $keys): int
    {
        $total = 0;
        foreach ($keys as $k) {
            if ($this->has($row, $k)) {
                $total += (int) $row[$k];
            }
        }

        return $total;
    }

    private function has(array $row, string $key): bool
    {
        return isset($row[$key]) && $row[$key] !== '' && is_numeric($row[$key]);
    }
}
