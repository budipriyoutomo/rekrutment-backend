<?php

namespace App\Domains\SalarySlip\DTO;

class SalarySlipDTO
{
    public function __construct(
        // identitas
        public readonly string $nik,
        public readonly string $nama,
        public readonly string $jabatan,
        public readonly string $periode,
        public readonly string $cabang,
        public readonly string $perusahaan,
        public readonly string $email,
        // komponen penerimaan
        public readonly int $gaji_pokok = 0,
        public readonly int $tunjangan_jabatan = 0,
        public readonly int $tunjangan_makan = 0,
        public readonly int $tunjangan_transport = 0,
        public readonly int $tunjangan_lain = 0,
        public readonly int $lembur = 0,
        public readonly int $tambahan_gaji = 0,
        public readonly int $ph_dibayar = 0,
        public readonly int $refund_seragam = 0,
        public readonly int $jumlah_service_charge = 0,
        public readonly int $total_penerimaan = 0,
        // absensi
        public readonly int $hk_hari = 0,
        public readonly int $alpha_hari = 0,
        public readonly int $ijin_ap_hari = 0,
        public readonly int $sakit_hari = 0,
        public readonly int $cuti_hari = 0,
        // potongan
        public readonly int $total_pot_absen = 0,
        public readonly int $bpjs_ketenagakerjaan = 0,
        public readonly int $bpjs_kesehatan = 0,
        public readonly int $pinjaman = 0,
        public readonly int $pph21 = 0,
        public readonly int $potongan_seragam = 0,
        public readonly int $koreksi = 0,
        public readonly int $total_potongan = 0,
        public readonly int $take_home_pay = 0,
        // info pembayaran
        public readonly ?string $sistem_pembayaran = null,
        public readonly ?string $no_rekening = null,
        public readonly ?string $nama_bank = null,
        public readonly ?string $atas_nama = null,
        // grouping
        public readonly ?string $batch_id = null,
    ) {}

    private const MONEY_FIELDS = [
        'gaji_pokok', 'tunjangan_jabatan', 'tunjangan_makan', 'tunjangan_transport',
        'tunjangan_lain', 'lembur', 'tambahan_gaji', 'ph_dibayar', 'refund_seragam',
        'jumlah_service_charge', 'total_penerimaan',
        'hk_hari', 'alpha_hari', 'ijin_ap_hari', 'sakit_hari', 'cuti_hari',
        'total_pot_absen', 'bpjs_ketenagakerjaan', 'bpjs_kesehatan', 'pinjaman',
        'pph21', 'potongan_seragam', 'koreksi', 'total_potongan', 'take_home_pay',
    ];

    private const STRING_FIELDS = [
        'nik', 'nama', 'jabatan', 'periode', 'cabang', 'perusahaan', 'email',
        'sistem_pembayaran', 'no_rekening', 'nama_bank', 'atas_nama', 'batch_id',
    ];

    public static function fromArray(array $data): self
    {
        $args = [];

        foreach (self::STRING_FIELDS as $f) {
            if (array_key_exists($f, $data)) {
                $args[$f] = $data[$f] === null ? null : trim((string) $data[$f]);
            }
        }

        foreach (self::MONEY_FIELDS as $f) {
            if (array_key_exists($f, $data)) {
                $args[$f] = (int) $data[$f];
            }
        }

        return new self(...$args);
    }

    public function toArray(): array
    {
        $out = [];
        foreach (array_merge(self::STRING_FIELDS, self::MONEY_FIELDS) as $f) {
            $out[$f] = $this->{$f};
        }

        return $out;
    }
}
