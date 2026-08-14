<?php

namespace App\Core\Support;

/**
 * Nilai master data position/location berformat "Kota - Nama"
 * (mis. "Bandung - Cook", "Bandung - KARNIVOR - JL. RIAU"). Kota diambil dari
 * prefix sebelum " - " PERTAMA saja, sehingga nama outlet yang sendirinya
 * mengandung " - " tetap utuh.
 */
final class CityPrefix
{
    /** Kota dari sebuah nilai master data, atau null bila tanpa prefix kota. */
    public static function of(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $sep = mb_strpos($value, ' - ');

        return $sep === false ? null : trim(mb_substr($value, 0, $sep));
    }

    /**
     * True bila kedua nilai punya prefix kota dan kotanya berbeda.
     * Nilai tanpa prefix dianggap "tanpa batasan" agar entri master data lama
     * atau tambahan manual HR tanpa prefix kota tidak ikut tertolak.
     */
    public static function mismatch(mixed $a, mixed $b): bool
    {
        $cityA = self::of($a);
        $cityB = self::of($b);

        if ($cityA === null || $cityB === null) {
            return false;
        }

        return mb_strtolower($cityA) !== mb_strtolower($cityB);
    }
}
