<?php

namespace App\Domains\SalarySlip\Services;

use setasign\Fpdi\Fpdi;

/**
 * Menambahkan watermark teks (mis. "ALTIMA GROUP") yang benar-benar transparan
 * (memakai alpha/ExtGState PDF) di LAYER PALING BAWAH tiap halaman — digambar
 * lebih dulu, lalu konten asli ditumpuk di atasnya sehingga data tetap terbaca.
 *
 * Memakai setasign/fpdi yang sudah tersedia di project (tanpa dependency tambahan).
 */
class PayslipWatermarker
{
    public function stamp(string $pdfPath, string $text = 'ALTIMA GROUP'): void
    {
        $pdf = new class extends Fpdi {
            public $angle = 0;
            protected $extgstates = [];

            // --- Transparansi (FPDF alpha extension via ExtGState) ---
            public function SetAlpha(float $alpha): void
            {
                $n = count($this->extgstates) + 1;
                $this->extgstates[$n]['parms'] = ['ca' => $alpha, 'CA' => $alpha, 'BM' => '/Normal'];
                $this->_out(sprintf('/GS%d gs', $n));
            }

            protected function _enddoc(): void
            {
                if (count($this->extgstates) && $this->PDFVersion < '1.4') {
                    $this->PDFVersion = '1.4';
                }
                parent::_enddoc();
            }

            protected function _putextgstates(): void
            {
                foreach (array_keys($this->extgstates) as $i) {
                    $this->_newobj();
                    $this->extgstates[$i]['n'] = $this->n;
                    $this->_put('<</Type /ExtGState');
                    $p = $this->extgstates[$i]['parms'];
                    $this->_put(sprintf('/ca %.3F', $p['ca']));
                    $this->_put(sprintf('/CA %.3F', $p['CA']));
                    $this->_put('/BM ' . $p['BM']);
                    $this->_put('>>');
                    $this->_put('endobj');
                }
            }

            protected function _putresourcedict(): void
            {
                parent::_putresourcedict();
                $this->_put('/ExtGState <<');
                foreach ($this->extgstates as $k => $extgstate) {
                    $this->_put('/GS' . $k . ' ' . $extgstate['n'] . ' 0 R');
                }
                $this->_put('>>');
            }

            protected function _putresources(): void
            {
                $this->_putextgstates();
                parent::_putresources();
            }

            // --- Rotasi ---
            public function Rotate($angle, $x = -1, $y = -1): void
            {
                if ($x === -1) {
                    $x = $this->x;
                }
                if ($y === -1) {
                    $y = $this->y;
                }
                if ($this->angle !== 0) {
                    $this->_out('Q');
                }
                $this->angle = $angle;
                if ($angle !== 0) {
                    $angle *= M_PI / 180;
                    $c  = cos($angle);
                    $s  = sin($angle);
                    $cx = $x * $this->k;
                    $cy = ($this->h - $y) * $this->k;
                    $this->_out(sprintf(
                        'q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm',
                        $c, $s, -$s, $c, $cx, $cy, -$cx, -$cy
                    ));
                }
            }

            protected function _endpage(): void
            {
                if ($this->angle !== 0) {
                    $this->angle = 0;
                    $this->_out('Q');
                }
                parent::_endpage();
            }
        };

        $pageCount = $pdf->setSourceFile($pdfPath);

        for ($p = 1; $p <= $pageCount; $p++) {
            $tpl  = $pdf->importPage($p);
            $size = $pdf->getTemplateSize($tpl);

            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);

            // 1) Watermark DULU (layer paling bawah), sangat transparan.
            $pdf->SetFont('Helvetica', 'B', 36);
            $pdf->SetTextColor(120, 120, 120);
            $pdf->SetAlpha(0.08);

            $cx = $size['width'] / 2;
            $cy = $size['height'] / 2;
            $textWidth = $pdf->GetStringWidth($text);

            $pdf->Rotate(28, $cx, $cy);
            $pdf->Text($cx - $textWidth / 2, $cy, $text);
            $pdf->Rotate(0);

            // 2) Konten asli DITUMPUK di atas watermark, opaque penuh.
            $pdf->SetAlpha(1);
            $pdf->useTemplate($tpl);
        }

        $pdf->Output('F', $pdfPath);
    }
}
