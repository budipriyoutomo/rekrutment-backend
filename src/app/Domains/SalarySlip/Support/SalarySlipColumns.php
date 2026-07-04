<?php

namespace App\Domains\SalarySlip\Support;

/**
 * Kontrak kolom import/template salary slip — satu sumber kebenaran yang dipakai
 * oleh template export (Phase 1) maupun parser/validator import (Phase 2).
 *
 * Key = header snake_case pada file. Nilai = metadata untuk sheet "Petunjuk".
 * Kolom internal/komputasi (id, batch_id, send_status, pdf_path, dst) TIDAK termasuk.
 */
class SalarySlipColumns
{
    public const TYPE_STRING = 'string';
    public const TYPE_EMAIL  = 'email';
    public const TYPE_PERIOD = 'period'; // YYYY-MM
    public const TYPE_MONEY  = 'money';  // integer polos, tanpa Rp/titik
    public const TYPE_DAYS   = 'days';   // integer hari

    /**
     * Urutan di sini = urutan kolom pada template.
     * [key => [label, type, required, hint]]
     */
    public const COLUMNS = [
        // --- identitas (wajib) ---
        'nik'        => ['NIK', self::TYPE_STRING, true,  'Nomor Induk Karyawan'],
        'nama'       => ['Nama Karyawan', self::TYPE_STRING, true, 'Nama lengkap karyawan'],
        'jabatan'    => ['Jabatan', self::TYPE_STRING, true, ''],
        'periode'    => ['Periode', self::TYPE_PERIOD, true, 'Format YYYY-MM, mis. 2026-06'],
        'cabang'     => ['Cabang', self::TYPE_STRING, true, ''],
        'perusahaan' => ['Nama Perusahaan', self::TYPE_STRING, true, ''],
        'email'      => ['Email', self::TYPE_EMAIL, true, 'Email tujuan pengiriman slip, harus valid'],
        // --- komponen penerimaan ---
        'gaji_pokok'            => ['Gaji Pokok', self::TYPE_MONEY, false, 'Angka polos tanpa Rp/titik, mis. 5000000'],
        'tunjangan_jabatan'     => ['Tunjangan Jabatan', self::TYPE_MONEY, false, ''],
        'tunjangan_makan'       => ['Tunjangan Makan', self::TYPE_MONEY, false, ''],
        'tunjangan_transport'   => ['Tunjangan Transport', self::TYPE_MONEY, false, ''],
        'tunjangan_lain'        => ['Tunjangan Lain-lain', self::TYPE_MONEY, false, ''],
        'lembur'                => ['Lembur', self::TYPE_MONEY, false, ''],
        'tambahan_gaji'         => ['Tambahan Gaji', self::TYPE_MONEY, false, ''],
        'ph_dibayar'            => ['PH Dibayar', self::TYPE_MONEY, false, ''],
        'refund_seragam'        => ['Refund Seragam', self::TYPE_MONEY, false, ''],
        'jumlah_service_charge' => ['Jumlah Service Charge', self::TYPE_MONEY, false, ''],
        'total_penerimaan'      => ['Total Penerimaan', self::TYPE_MONEY, false, 'Divalidasi terhadap penjumlahan komponen penerimaan'],
        // --- absensi (hari) ---
        'hk_hari'      => ['HK (Hari Kerja)', self::TYPE_DAYS, false, 'Jumlah hari'],
        'alpha_hari'   => ['Alpha', self::TYPE_DAYS, false, 'Jumlah hari'],
        'ijin_ap_hari' => ['Ijin/AP', self::TYPE_DAYS, false, 'Jumlah hari'],
        'sakit_hari'   => ['Sakit', self::TYPE_DAYS, false, 'Jumlah hari'],
        'cuti_hari'    => ['Cuti', self::TYPE_DAYS, false, 'Jumlah hari'],
        // --- potongan ---
        'total_pot_absen'      => ['Total Nilai Pot. Absen', self::TYPE_MONEY, false, ''],
        'bpjs_ketenagakerjaan' => ['BPJS Ketenagakerjaan', self::TYPE_MONEY, false, ''],
        'bpjs_kesehatan'       => ['BPJS Kesehatan', self::TYPE_MONEY, false, ''],
        'pinjaman'             => ['Pinjaman', self::TYPE_MONEY, false, ''],
        'pph21'                => ['PPH21', self::TYPE_MONEY, false, ''],
        'potongan_seragam'     => ['Potongan Seragam', self::TYPE_MONEY, false, ''],
        'koreksi'              => ['Koreksi', self::TYPE_MONEY, false, ''],
        'total_potongan'       => ['Total Potongan', self::TYPE_MONEY, false, 'Divalidasi terhadap penjumlahan komponen potongan'],
        'take_home_pay'        => ['Take Home Pay', self::TYPE_MONEY, false, 'Divalidasi = total penerimaan - total potongan'],
        // --- info pembayaran ---
        'sistem_pembayaran' => ['Sistem Pembayaran', self::TYPE_STRING, false, 'mis. Transfer / Tunai'],
        'no_rekening'       => ['No. Rekening', self::TYPE_STRING, false, ''],
        'nama_bank'         => ['Nama Bank', self::TYPE_STRING, false, ''],
        'atas_nama'         => ['Atas Nama', self::TYPE_STRING, false, ''],
    ];

    /** @return string[] header snake_case sesuai urutan template */
    public static function keys(): array
    {
        return array_keys(self::COLUMNS);
    }

    /** @return string[] kolom wajib */
    public static function requiredKeys(): array
    {
        return array_keys(array_filter(
            self::COLUMNS,
            fn (array $meta) => $meta[2] === true,
        ));
    }

    /** @return string[] kolom bertipe uang/hari (integer polos) */
    public static function numericKeys(): array
    {
        return array_keys(array_filter(
            self::COLUMNS,
            fn (array $meta) => in_array($meta[1], [self::TYPE_MONEY, self::TYPE_DAYS], true),
        ));
    }

    /** Satu baris contoh (dummy) untuk template. */
    public static function exampleRow(): array
    {
        return [
            'nik' => '12345', 'nama' => 'Budi Santoso', 'jabatan' => 'Staff Operasional',
            'periode' => '2026-06', 'cabang' => 'Jakarta Pusat', 'perusahaan' => 'PT Maju Bersama',
            'email' => 'budi.santoso@example.com',
            'gaji_pokok' => 5000000, 'tunjangan_jabatan' => 500000, 'tunjangan_makan' => 300000,
            'tunjangan_transport' => 300000, 'tunjangan_lain' => 0, 'lembur' => 200000,
            'tambahan_gaji' => 0, 'ph_dibayar' => 0, 'refund_seragam' => 0,
            'jumlah_service_charge' => 0, 'total_penerimaan' => 6600000,
            'hk_hari' => 22, 'alpha_hari' => 0, 'ijin_ap_hari' => 0, 'sakit_hari' => 0, 'cuti_hari' => 0,
            'total_pot_absen' => 0, 'bpjs_ketenagakerjaan' => 120000, 'bpjs_kesehatan' => 100000,
            'pinjaman' => 0, 'pph21' => 80000, 'potongan_seragam' => 0, 'koreksi' => 0,
            'total_potongan' => 300000, 'take_home_pay' => 6300000,
            'sistem_pembayaran' => 'Transfer', 'no_rekening' => '1234567890',
            'nama_bank' => 'BCA', 'atas_nama' => 'Budi Santoso',
        ];
    }
}
