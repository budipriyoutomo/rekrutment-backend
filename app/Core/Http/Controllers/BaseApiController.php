<?php

namespace App\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

abstract class BaseApiController
{
    protected function response(
        bool $status,
        string $message,
        $data = null,
        int $code = 200,
        array $meta = []
    ): JsonResponse {
        return response()->json([
            'status' => $status,
            'message' => $message,
            'data' => $data,
            'meta' => array_merge([
                'request_id' => Str::uuid()->toString(),
            ], $meta),
        ], $code);
    }

    protected function success($data = null, string $message = 'Success'): JsonResponse
    {
        return $this->response(true, $message, $data);
    }

    protected function error(string $message = 'Error', int $code = 400, $errors = null): JsonResponse
    {
        return $this->response(false, $message, $errors, $code);
    }
}