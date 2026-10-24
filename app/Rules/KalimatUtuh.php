<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Menolak teks yang tampak terpotong, mis. sasaran "...sesuai rencana riil yang dibutuhkan oleh".
 *
 * Teks dianggap terpotong bila kata terakhirnya kata sambung/depan yang selalu butuh lanjutan.
 */
class KalimatUtuh implements ValidationRule
{
    /** @var list<string> */
    public const KATA_GANTUNG = [
        'oleh', 'dan', 'yang', 'untuk', 'di', 'ke', 'dari', 'dengan', 'serta', 'atau', 'pada',
        'bagi', 'dalam', 'sebagai', 'kepada', 'agar', 'sehingga', 'karena', 'terhadap', 'melalui',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        $kataTerakhir = self::kataTerakhir($value);

        if ($kataTerakhir !== null && in_array($kataTerakhir, self::KATA_GANTUNG, true)) {
            $fail("Kalimat :attribute tampak terpotong (diakhiri kata “{$kataTerakhir}”). Lengkapi kalimatnya.");
        }
    }

    public static function kataTerakhir(string $value): ?string
    {
        $bersih = rtrim(trim($value), " \t\n\r\0\x0B.,;:");

        if ($bersih === '') {
            return null;
        }

        $kata = preg_split('/\s+/u', $bersih);

        return mb_strtolower(end($kata));
    }
}
