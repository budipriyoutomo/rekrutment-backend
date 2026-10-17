<?php

namespace App\Domains\Preboarding\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Data karyawan baru dari kandidat. NPWP & BPJS opsional (belum semua orang punya). */
class SubmitPreboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bankName'                      => ['required', 'string', 'max:100'],
            'bankAccountNumber'             => ['required', 'string', 'regex:/^[0-9]{6,20}$/'],
            'bankAccountHolder'             => ['required', 'string', 'max:150'],
            'npwp'                          => ['nullable', 'string', 'regex:/^[0-9.\-]{15,20}$/'],
            'bpjsKesehatan'                 => ['nullable', 'string', 'regex:/^[0-9]{13}$/'],
            'bpjsKetenagakerjaan'           => ['nullable', 'string', 'regex:/^[0-9]{11}$/'],
            'emergencyContactName'          => ['required', 'string', 'max:150'],
            'emergencyContactRelationship'  => ['required', 'string', 'max:50'],
            'emergencyContactPhone'         => ['required', 'string', 'regex:/^[0-9+\- ]{8,20}$/'],
            'uniformSize'                   => ['nullable', 'in:XS,S,M,L,XL,XXL,XXXL'],
        ];
    }

    public function messages(): array
    {
        return [
            'bankAccountNumber.regex'     => 'Nomor rekening hanya berisi 6-20 digit angka.',
            'npwp.regex'                  => 'Format NPWP tidak valid.',
            'bpjsKesehatan.regex'         => 'Nomor BPJS Kesehatan harus 13 digit.',
            'bpjsKetenagakerjaan.regex'   => 'Nomor BPJS Ketenagakerjaan harus 11 digit.',
            'emergencyContactPhone.regex' => 'Nomor telepon kontak darurat tidak valid.',
        ];
    }
}
