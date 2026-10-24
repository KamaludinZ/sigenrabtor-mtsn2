<?php

namespace App\Support;

/**
 * Normalisasi teks uraian agar tercetak rapi dan seragam di dokumen.
 */
class Teks
{
    /**
     * Rapikan spasi: spasi/tab berulang jadi satu, spasi di tepi baris dibuang,
     * lebih dari satu baris kosong diringkas menjadi satu.
     */
    public static function rapikan(string $teks): string
    {
        $teks = str_replace(["\r\n", "\r"], "\n", $teks);
        $teks = preg_replace('/[ \t\x{00A0}]+/u', ' ', $teks);
        $teks = preg_replace('/ *\n */', "\n", $teks);
        $teks = preg_replace('/\n{3,}/', "\n\n", $teks);

        return trim($teks);
    }
}
