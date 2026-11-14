<?php

namespace App\Core\Http;

use Illuminate\Http\Request;

/**
 * Ukuran halaman dari query `per_page` (atau alias `limit`), dibatasi 1..MAX.
 *
 * MAX sengaja 1000 (bukan 100): beberapa halaman HR (Pipeline, Interviews,
 * Evaluations) memuat seluruh data sekaligus dengan limit 500–1000.
 * Input non-numerik/array jatuh ke default, bukan TypeError 500.
 */
final class PerPage
{
    public const MAX = 1000;

    public static function from(Request $request, int $default): int
    {
        $value = $request->query('per_page', $request->query('limit'));

        if (! is_scalar($value) || ! is_numeric($value)) {
            return $default;
        }

        return min(max((int) $value, 1), self::MAX);
    }
}
