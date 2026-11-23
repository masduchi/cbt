<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Throwable;

class Week
{
    /**
     * Awal minggu (Senin) dari parameter ?week=, dengan fallback ke minggu berjalan.
     */
    public static function start(?string $value): Carbon
    {
        try {
            $date = $value !== null && trim($value) !== '' ? Carbon::parse($value) : now();
        } catch (Throwable) {
            $date = now();
        }

        return $date->startOfWeek(Carbon::MONDAY)->startOfDay();
    }
}
