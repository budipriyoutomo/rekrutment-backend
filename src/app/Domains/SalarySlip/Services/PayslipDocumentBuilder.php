<?php

namespace App\Domains\SalarySlip\Services;

use App\Domains\SalarySlip\Models\SalarySlip;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

/**
 * Membangun dokumen .docx slip gaji yang mereplikasi layout "Template Payslip.docx"
 * (header ALTIMA GROUP / SLIP GAJI, info karyawan, dua kolom PENERIMAAN vs PEMOTONGAN,
 * total, TAKE HOME PAY, info pembayaran, footer). Hasil docx dikonversi ke PDF.
 *
 * Dirancang muat 1 halaman A5 landscape, konten center (margin kiri-kanan seimbang).
 */
class PayslipDocumentBuilder
{
    // Lebar kolom (twips). Total per tabel = 2*(W_LABEL+W_VALUE) = 9600 (~169mm),
    // ter-center di area cetak A5 landscape sehingga sisi kiri & kanan seimbang.
    private const W_LABEL = 2600;
    private const W_VALUE = 2200;
    private const W_HALF  = self::W_LABEL + self::W_VALUE; // 4800

    public function build(SalarySlip $slip, string $savePath): void
    {
        $phpWord = new PhpWord();
        $phpWord->setDefaultFontName('Arial');
        $phpWord->setDefaultFontSize(8);

        // A5 landscape (A5 = 148mm x 210mm; twips). Margin kiri = kanan (seimbang).
        $section = $phpWord->addSection([
            'orientation' => 'landscape',
            'pageSizeW'   => 11906, // 210mm (sisi panjang)
            'pageSizeH'   => 8391,  // 148mm (sisi pendek)
            'marginTop' => 360, 'marginBottom' => 300, 'marginLeft' => 700, 'marginRight' => 700,
        ]);

        // ---- Kop ----
        // Satu baris: "ALTIMA GROUP" rata kiri, "SLIP GAJI" tetap center di halaman.
        // Kolom kiri & kanan sama lebar (W_SIDE) agar kolom tengah center pada halaman.
        $wSide   = self::W_LABEL;
        $wCenter = 2 * self::W_HALF - 2 * $wSide;
        $kop = $this->centeredTable($section, ['cellMargin' => 0]);
        $kop->addRow();
        $kop->addCell($wSide, ['valign' => 'center'])
            ->addText('ALTIMA GROUP', ['bold' => true, 'size' => 12], ['spaceAfter' => 0]);
        $kop->addCell($wCenter, ['valign' => 'center'])
            ->addText('SLIP GAJI', ['bold' => true, 'size' => 14], ['alignment' => 'center', 'spaceAfter' => 0]);
        $kop->addCell($wSide);
        $this->spacer($section);

        // ---- Info karyawan ----
        $info = $this->centeredTable($section, ['cellMargin' => 20]);
        $this->infoRow($info, 'NAMA KARYAWAN', $slip->nama, 'PERIODE', $slip->periode);
        $this->infoRow($info, 'NIK', $slip->nik, 'CABANG', $slip->cabang);
        $this->infoRow($info, 'JABATAN', $slip->jabatan, 'NAMA PERUSAHAAN', $slip->perusahaan);
        $this->spacer($section);

        // ---- Dua kolom PENERIMAAN / PEMOTONGAN ----
        $penerimaan = [
            ['GAJI POKOK', $this->money($slip->gaji_pokok)],
            ['TUNJANGAN JABATAN', $this->money($slip->tunjangan_jabatan)],
            ['TUNJANGAN MAKAN', $this->money($slip->tunjangan_makan)],
            ['TUNJANGAN TRANSPORT', $this->money($slip->tunjangan_transport)],
            ['TUNJANGAN LAIN-LAIN', $this->money($slip->tunjangan_lain)],
            ['LEMBUR', $this->money($slip->lembur)],
            ['TAMBAHAN GAJI', $this->money($slip->tambahan_gaji)],
            ['PH DIBAYAR', $this->money($slip->ph_dibayar)],
            ['REFUND SERAGAM', $this->money($slip->refund_seragam)],
            ['JUMLAH SERVICE CHARGE', $this->money($slip->jumlah_service_charge)],
        ];

        $pemotongan = [
            ['HK', $this->days($slip->hk_hari)],
            ['ALPHA', $this->days($slip->alpha_hari)],
            ['IJIN/AP', $this->days($slip->ijin_ap_hari)],
            ['SAKIT', $this->days($slip->sakit_hari)],
            ['CUTI', $this->days($slip->cuti_hari)],
            ['TOTAL NILAI POT. ABSEN', $this->money($slip->total_pot_absen)],
            ['BPJS KETENAGAKERJAAN', $this->money($slip->bpjs_ketenagakerjaan)],
            ['BPJS KESEHATAN', $this->money($slip->bpjs_kesehatan)],
            ['PINJAMAN', $this->money($slip->pinjaman)],
            ['PPH21', $this->money($slip->pph21)],
            ['POTONGAN SERAGAM', $this->money($slip->potongan_seragam)],
            ['KOREKSI', $this->money($slip->koreksi)],
        ];

        $table = $this->centeredTable($section, [
            'borderSize' => 4, 'borderColor' => 'BFBFBF', 'cellMargin' => 20,
        ]);

        $table->addRow();
        $this->headerCell($table, 'PENERIMAAN');
        $this->headerCell($table, 'PEMOTONGAN');

        $rows = max(count($penerimaan), count($pemotongan));
        for ($i = 0; $i < $rows; $i++) {
            $table->addRow();
            $this->itemCells($table, $penerimaan[$i][0] ?? '', $penerimaan[$i][1] ?? '');
            $this->itemCells($table, $pemotongan[$i][0] ?? '', $pemotongan[$i][1] ?? '');
        }

        $table->addRow();
        $this->itemCells($table, 'TOTAL PENERIMAAN', $this->money($slip->total_penerimaan), true);
        $this->itemCells($table, 'TOTAL POTONGAN', $this->money($slip->total_potongan), true);

        $this->spacer($section);

        // ---- Take Home Pay ----
        $thp = $this->centeredTable($section, ['cellMargin' => 20]);
        $thp->addRow();
        $thp->addCell(self::W_HALF)->addText('TAKE HOME PAY', ['bold' => true, 'size' => 10]);
        $thp->addCell(self::W_HALF)->addText(
            $this->money($slip->take_home_pay),
            ['bold' => true, 'size' => 10],
            ['alignment' => 'right'],
        );

        $this->spacer($section);

        // ---- Info pembayaran + tanda tangan (dua kolom sejajar agar hemat tinggi) ----
        $foot = $this->centeredTable($section, ['cellMargin' => 20]);
        $foot->addRow();
        $payCell = $foot->addCell(self::W_HALF);
        $this->payLine($payCell, 'SISTEM PEMBAYARAN', $slip->sistem_pembayaran ?? '-');
        $this->payLine($payCell, 'NO. REKENING', $slip->no_rekening ?? '-');
        $this->payLine($payCell, 'NAMA BANK', $slip->nama_bank ?? '-');
        $this->payLine($payCell, 'ATAS NAMA', $slip->atas_nama ?? '-');

        $signCell = $foot->addCell(self::W_HALF);
        $signCell->addText('Jakarta, ' . now()->translatedFormat('d F Y'), [], ['alignment' => 'center']);
        $signCell->addTextBreak(2);
        $signCell->addText('ADMINISTRATOR', ['bold' => true], ['alignment' => 'center']);

        IOFactory::createWriter($phpWord, 'Word2007')->save($savePath);
    }

    /** Tabel dengan lebar tetap 9600 twips & rata tengah pada halaman. */
    private function centeredTable(Section $section, array $style): Table
    {
        return $section->addTable(array_merge([
            'alignment'  => 'center',
            'width'      => 2 * self::W_HALF,
            'unit'       => 'dxa',
            'layout'     => 'fixed',
        ], $style));
    }

    private function spacer(Section $section): void
    {
        // Jarak vertikal tipis antar blok tanpa memakan tinggi selayaknya baris penuh.
        $section->addText('', ['size' => 3], ['spaceAfter' => 0, 'spaceBefore' => 0]);
    }

    private function infoRow(Table $table, string $l1, ?string $v1, string $l2, ?string $v2): void
    {
        $table->addRow();
        $table->addCell(self::W_LABEL)->addText($l1, ['bold' => true]);
        $table->addCell(self::W_VALUE)->addText(': ' . ($v1 ?? '-'));
        $table->addCell(self::W_LABEL)->addText($l2, ['bold' => true]);
        $table->addCell(self::W_VALUE)->addText(': ' . ($v2 ?? '-'));
    }

    private function payLine($cell, string $label, string $value): void
    {
        $cell->addText($label . ' : ' . $value);
    }

    private function headerCell(Table $table, string $title): void
    {
        $table->addCell(self::W_HALF, [
            'gridSpan' => 2, 'bgColor' => 'D9D9D9',
        ])->addText($title, ['bold' => true], ['alignment' => 'center']);
    }

    private function itemCells(Table $table, string $label, string $value, bool $bold = false): void
    {
        $table->addCell(self::W_LABEL)->addText($label, ['bold' => $bold]);
        $table->addCell(self::W_VALUE)->addText($value, ['bold' => $bold], ['alignment' => 'right']);
    }

    private function money(?int $value): string
    {
        return 'Rp ' . number_format((int) $value, 2, ',', '.');
    }

    private function days(?int $value): string
    {
        return (int) $value . ' Hari';
    }
}
