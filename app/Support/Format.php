<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Throwable;

/**
 * Format tampilan baku Indonesia (tanggal, angka) yang dipakai layar dan dokumen.
 */
class Format
{
    /**
     * "2025-12-23" → "23 Desember 2025". Mengembalikan null bila kosong/tidak valid.
     */
    public static function tanggal(?string $tanggal): ?string
    {
        if ($tanggal === null || trim($tanggal) === '') {
            return null;
        }

        try {
            return Carbon::parse($tanggal)->locale('id')->translatedFormat('j F Y');
        } catch (Throwable) {
            return null;
        }
    }
}
