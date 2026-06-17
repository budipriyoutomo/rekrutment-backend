<?php

namespace App\Http\Controllers;

use App\Core\Http\Controllers\BaseApiController;
use App\Domains\Setting\Models\CompanySetting;
use Illuminate\Http\Request;

class CompanySettingController extends BaseApiController
{
    private const ALLOWED_KEYS = [
        'company_name',
        'company_email',
        'company_phone',
        'company_address',
        'company_website',
        'company_logo_url',
        'company_tagline',
    ];

    public function index()
    {
        $map = CompanySetting::allAsMap();

        $settings = [];
        foreach (self::ALLOWED_KEYS as $key) {
            $settings[$key] = $map[$key] ?? null;
        }

        return $this->success($settings, 'Company settings berhasil diambil');
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'company_name'     => ['nullable', 'string', 'max:200'],
            'company_email'    => ['nullable', 'email', 'max:200'],
            'company_phone'    => ['nullable', 'string', 'max:50'],
            'company_address'  => ['nullable', 'string', 'max:500'],
            'company_website'  => ['nullable', 'url', 'max:200'],
            'company_logo_url' => ['nullable', 'url', 'max:500'],
            'company_tagline'  => ['nullable', 'string', 'max:200'],
        ]);

        foreach ($validated as $key => $value) {
            if (in_array($key, self::ALLOWED_KEYS)) {
                CompanySetting::set($key, $value);
            }
        }

        return $this->success(CompanySetting::allAsMap(), 'Settings berhasil disimpan');
    }
}
