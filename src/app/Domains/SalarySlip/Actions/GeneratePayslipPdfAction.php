<?php

namespace App\Domains\SalarySlip\Actions;

use App\Domains\SalarySlip\Models\SalarySlip;
use App\Domains\SalarySlip\Services\PayslipDocumentBuilder;
use App\Domains\SalarySlip\Services\PayslipPdfConverter;
use App\Domains\SalarySlip\Services\PayslipWatermarker;
use Illuminate\Support\Str;

/**
 * Generate PDF slip gaji: render docx dari data slip -> konversi PDF -> simpan ke storage.
 * Idempotent: kalau PDF sudah pernah dibuat & filenya ada, dipakai ulang (kecuali $force).
 */
class GeneratePayslipPdfAction
{
    public function __construct(
        private PayslipDocumentBuilder $builder,
        private PayslipPdfConverter    $converter,
        private PayslipWatermarker     $watermarker,
    ) {}

    /**
     * @return string path absolut file PDF.
     */
    public function execute(SalarySlip $slip, bool $force = false): string
    {
        $relative = "payslips/{$slip->id}.pdf";
        $absolute = storage_path("app/{$relative}");

        if (! $force && $slip->pdf_path && is_file($absolute)) {
            return $absolute;
        }

        $tmpDir = storage_path('app/temp_processing/payslip_' . Str::random(12));
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0777, true);
        }

        try {
            $docxPath = "{$tmpDir}/{$slip->id}.docx";
            $this->builder->build($slip, $docxPath);

            $pdfTmp = $this->converter->toPdf($docxPath, $tmpDir);
            $this->watermarker->stamp($pdfTmp);

            if (! is_dir(dirname($absolute))) {
                mkdir(dirname($absolute), 0777, true);
            }
            copy($pdfTmp, $absolute);
        } finally {
            $this->cleanup($tmpDir);
        }

        $slip->update(['pdf_path' => $relative]);

        return $absolute;
    }

    private function cleanup(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (glob($dir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($dir);
    }
}
