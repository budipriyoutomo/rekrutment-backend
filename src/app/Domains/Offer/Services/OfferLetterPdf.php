<?php

namespace App\Domains\Offer\Services;

use App\Domains\Offer\Models\Offer;
use App\Domains\SalarySlip\Services\PayslipPdfConverter;
use App\Domains\Setting\Models\CompanySetting;
use Illuminate\Support\Str;

/**
 * Render offer letter: Blade → HTML → PDF via LibreOffice (converter yang sama
 * dengan slip gaji). PDF tidak disimpan; dirender ulang setiap dibutuhkan agar
 * selalu sesuai data offer terbaru.
 */
class OfferLetterPdf
{
    public function __construct(private PayslipPdfConverter $converter) {}

    public function html(Offer $offer): string
    {
        $offer->loadMissing('application');

        return view('pdf.offer-letter', [
            'offer'   => $offer,
            'company' => [
                'name'    => CompanySetting::get('company_name') ?: config('app.name'),
                'address' => CompanySetting::get('company_address'),
                'phone'   => CompanySetting::get('company_phone'),
                'email'   => CompanySetting::get('company_email'),
            ],
        ])->render();
    }

    /** Isi PDF (bytes). */
    public function render(Offer $offer): string
    {
        $workDir = storage_path('app/tmp/offer-' . Str::uuid());
        mkdir($workDir, 0777, true);

        try {
            $htmlPath = $workDir . DIRECTORY_SEPARATOR . 'offer.html';
            file_put_contents($htmlPath, $this->html($offer));

            return file_get_contents($this->converter->toPdf($htmlPath, $workDir));
        } finally {
            foreach (glob($workDir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($workDir);
        }
    }
}
