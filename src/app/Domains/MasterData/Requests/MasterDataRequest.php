<?php

namespace App\Domains\MasterData\Requests;

use App\Core\Http\Requests\BaseRequest;
use App\Domains\MasterData\Enums\MasterDataType;
use Illuminate\Validation\Rule;

class MasterDataRequest extends BaseRequest
{
    protected function rulesForCreate(): array
    {
        return [
            'type' => ['required', Rule::enum(MasterDataType::class)],
            'name' => ['required', 'string', 'max:200'],
            'code' => [
                'nullable', 'string', 'max:50',
                Rule::unique('master_data')->where('type', $this->input('type')),
            ],
            'parent_id' => $this->parentRules(),
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
        ];
    }

    protected function rulesForUpdate(): array
    {
        $id = $this->route('id');

        return [
            'type' => ['required', Rule::enum(MasterDataType::class)],
            'name' => ['required', 'string', 'max:200'],
            'code' => [
                'nullable', 'string', 'max:50',
                Rule::unique('master_data')->where('type', $this->input('type'))->ignore($id),
            ],
            'parent_id' => $this->parentRules(),
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
        ];
    }

    /**
     * Lokasi wajib punya kota. Posisi boleh tanpa kota (berlaku di semua kota).
     * Field hanya dipakai bila dikirim, sehingga klien lama tetap kompatibel.
     */
    private function parentRules(): array
    {
        $required = $this->input('type') === MasterDataType::LOCATION->value && $this->has('parent_id');

        return [
            'sometimes',
            $required ? 'required' : 'nullable',
            'uuid',
            Rule::exists('master_data', 'id')->where('type', MasterDataType::CITY->value),
        ];
    }
}
