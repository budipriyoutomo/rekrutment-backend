<?php

namespace App\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

abstract class BaseRequest extends FormRequest
{
    abstract protected function rulesForCreate(): array;
    abstract protected function rulesForUpdate(): array;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->method()) {
            'POST' => $this->rulesForCreate(),
            'PUT', 'PATCH' => $this->rulesForUpdate(),
            default => []
        };
    }

    public function dto(string $class)
    {
        return $class::fromRequest($this);
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422)
        );
    }

    protected function prepareForValidation()
    {
        $this->merge($this->clean($this->all()));
    }

    private function clean(array $data): array
    {
        return array_map(function ($value) {
            if (is_array($value)) return $this->clean($value);
            if (is_string($value)) return trim($value) ?: null;
            return $value;
        }, $data);
    }
}