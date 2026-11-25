<?php

namespace App\Services;

class Rupiah
{
    /** Singkat untuk label grafik: 8,5jt / 450rb / 1,2M. */
    public static function compact(int|float $n): string
    {
        $fmt = fn (float $v, string $unit) => rtrim(rtrim(number_format($v, 1, ',', ''), '0'), ',') . $unit;

        return match (true) {
            $n >= 1_000_000_000 => $fmt($n / 1_000_000_000, 'M'),
            $n >= 1_000_000 => $fmt($n / 1_000_000, 'jt'),
            $n >= 1_000 => $fmt($n / 1_000, 'rb'),
            default => (string) (int) $n,
        };
    }

    public static function format(int|float $n): string
    {
        return 'Rp ' . number_format($n, 0, ',', '.');
    }
}
