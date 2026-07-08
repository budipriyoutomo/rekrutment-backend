<?php

namespace App\Domains\SalarySlip\Models;

use App\Core\Models\BaseModel;
use App\Traits\HasUuid;
use App\Traits\HasUserstamps;

class SalarySlip extends BaseModel
{
    use HasUuid, HasUserstamps;

    protected $table = 'salary_slips';

    // Default agar instance model konsisten dengan default kolom DB
    protected $attributes = [
        'send_status' => self::STATUS_DRAFT,
    ];

    // Status lifecycle pengiriman
    public const STATUS_DRAFT      = 'draft';
    public const STATUS_QUEUED     = 'queued';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SENT       = 'sent';
    public const STATUS_FAILED     = 'failed';

    protected $fillable = [
        'batch_id',
        // identitas
        'nik', 'nama', 'jabatan', 'periode', 'cabang', 'perusahaan', 'email',
        // komponen penerimaan
        'gaji_pokok', 'tunjangan_jabatan', 'tunjangan_makan', 'tunjangan_transport',
        'tunjangan_lain', 'lembur', 'tambahan_gaji', 'keterangan_tambahan_gaji',
        'ph_dibayar', 'refund_seragam',
        'jumlah_service_charge', 'total_penerimaan',
        // absensi
        'hk_hari', 'alpha_hari', 'ijin_ap_hari', 'sakit_hari', 'cuti_hari',
        // potongan
        'total_pot_absen', 'bpjs_ketenagakerjaan', 'bpjs_kesehatan', 'pinjaman',
        'pph21', 'potongan_seragam', 'koreksi', 'total_potongan',
        'take_home_pay',
        // info pembayaran
        'sistem_pembayaran', 'no_rekening', 'nama_bank', 'atas_nama',
        // lifecycle
        'send_status', 'send_error', 'pdf_path', 'mail_account_id', 'sent_at',
    ];

    protected $casts = [
        // nominal
        'gaji_pokok'            => 'integer',
        'tunjangan_jabatan'     => 'integer',
        'tunjangan_makan'       => 'integer',
        'tunjangan_transport'   => 'integer',
        'tunjangan_lain'        => 'integer',
        'lembur'                => 'integer',
        'tambahan_gaji'         => 'integer',
        'ph_dibayar'            => 'integer',
        'refund_seragam'        => 'integer',
        'jumlah_service_charge' => 'integer',
        'total_penerimaan'      => 'integer',
        'total_pot_absen'       => 'integer',
        'bpjs_ketenagakerjaan'  => 'integer',
        'bpjs_kesehatan'        => 'integer',
        'pinjaman'              => 'integer',
        'pph21'                 => 'integer',
        'potongan_seragam'      => 'integer',
        'koreksi'               => 'integer',
        'total_potongan'        => 'integer',
        'take_home_pay'         => 'integer',
        // absensi
        'hk_hari'      => 'integer',
        'alpha_hari'   => 'integer',
        'ijin_ap_hari' => 'integer',
        'sakit_hari'   => 'integer',
        'cuti_hari'    => 'integer',
        // timestamp
        'sent_at' => 'datetime',
    ];

    public function batch()
    {
        return $this->belongsTo(ImportBatch::class, 'batch_id');
    }

    public function mailAccount()
    {
        return $this->belongsTo(MailAccount::class, 'mail_account_id');
    }

    public function sendJobs()
    {
        return $this->hasMany(SendJob::class, 'salary_slip_id');
    }
}
