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
            'type'       => ['required', Rule::enum(MasterDataType::class)],
            'name'       => ['required', 'string', 'max:200'],
            'code'       => [
                'nullable', 'string', 'max:50',
                Rule::unique('master_data')->where('type', $this->input('type')),
            ],
            'is_active'  => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
        ];
    }

    protected function rulesForUpdate(): array
    {
        $id = $this->route('id');

        return [
            'type'       => ['required', Rule::enum(MasterDataType::class)],
            'name'       => ['required', 'string', 'max:200'],
            'code'       => [
                'nullable', 'string', 'max:50',
                Rule::unique('master_data')->where('type', $this->input('type'))->ignore($id),
            ],
            'is_active'  => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
        ];
    }
}
