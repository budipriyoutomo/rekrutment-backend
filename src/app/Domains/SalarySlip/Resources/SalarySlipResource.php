<?php

namespace App\Domains\SalarySlip\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SalarySlipResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'batchId'    => $this->batch_id,
            // identitas
            'nik'        => $this->nik,
            'nama'       => $this->nama,
            'jabatan'    => $this->jabatan,
            'periode'    => $this->periode,
            'cabang'     => $this->cabang,
            'perusahaan' => $this->perusahaan,
            'email'      => $this->email,
            // komponen penerimaan
            'gajiPokok'           => $this->gaji_pokok,
            'tunjanganJabatan'    => $this->tunjangan_jabatan,
            'tunjanganMakan'      => $this->tunjangan_makan,
            'tunjanganTransport'  => $this->tunjangan_transport,
            'tunjanganLain'       => $this->tunjangan_lain,
            'lembur'              => $this->lembur,
            'tambahanGaji'        => $this->tambahan_gaji,
            'phDibayar'           => $this->ph_dibayar,
            'refundSeragam'       => $this->refund_seragam,
            'jumlahServiceCharge' => $this->jumlah_service_charge,
            'totalPenerimaan'     => $this->total_penerimaan,
            // absensi
            'hkHari'     => $this->hk_hari,
            'alphaHari'  => $this->alpha_hari,
            'ijinApHari' => $this->ijin_ap_hari,
            'sakitHari'  => $this->sakit_hari,
            'cutiHari'   => $this->cuti_hari,
            // potongan
            'totalPotAbsen'       => $this->total_pot_absen,
            'bpjsKetenagakerjaan' => $this->bpjs_ketenagakerjaan,
            'bpjsKesehatan'       => $this->bpjs_kesehatan,
            'pinjaman'            => $this->pinjaman,
            'pph21'               => $this->pph21,
            'potonganSeragam'     => $this->potongan_seragam,
            'koreksi'             => $this->koreksi,
            'totalPotongan'       => $this->total_potongan,
            'takeHomePay'         => $this->take_home_pay,
            // info pembayaran
            'sistemPembayaran' => $this->sistem_pembayaran,
            'noRekening'       => $this->no_rekening,
            'namaBank'         => $this->nama_bank,
            'atasNama'         => $this->atas_nama,
            // lifecycle pengiriman (dipakai FE untuk badge & tooltip)
            'sendStatus'    => $this->send_status,
            'sendError'     => $this->send_error,
            'pdfPath'       => $this->pdf_path,
            'mailAccountId' => $this->mail_account_id,
            'sentAt'        => $this->sent_at?->toIso8601String(),
            'createdAt'     => $this->created_at?->toIso8601String(),
        ];
    }
}
