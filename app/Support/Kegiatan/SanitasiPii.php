<?php

namespace App\Support\Kegiatan;

/**
 * Sanitasi data pribadi (PII) sebelum teks dikirim ke penyedia AI (PRD N8; dipakai PayloadAi::bersihkan sesudah redaksi
 * NIP & nama pejabat): alamat surel, nomor rekening, NIK (16 digit), nomor telepon/WA Indonesia, dan nama orang yang
 * dikenal aplikasi (akun pengguna, susunan panitia kegiatan — utuh maupun tanpa gelar) diganti penanda `PENANDA`.
 * Teks lain (angka biasa, tanggal, tahun) tidak diubah. Idempoten.
 */
final class SanitasiPii
{
    /** Jenis PII → penanda pengganti. */
    public const PENANDA = [
        'surel' => '[surel disembunyikan]',
        'rekening' => '[nomor rekening disembunyikan]',
        'nik' => '[NIK disembunyikan]',
        'telepon' => '[nomor telepon disembunyikan]',
        'nama' => '[nama disembunyikan]',
    ];

    /** Pola per jenis, diterapkan berurutan (surel & rekening sebelum deret angka). */
    private const POLA = [
        'surel' => '/[A-Za-z0-9._%+-]+@[A-Za-z0-9-]+(?:\.[A-Za-z0-9-]+)*\.[A-Za-z]{2,}/u',
        'rekening' => '/\b(?:no(?:mor)?\.?\s*)?rek(?:ening)?\b\.?\s*(?:[A-Za-z]{2,10}\s+)?(?:no(?:mor)?\.?\s*)?:?\s*\d[\d .\-]{4,}\d/iu',
        'nik' => '/(?<!\d)\d{16}(?!\d)/',
        'telepon' => '/(?<![\w+(])\(?(?:\+62|62|0)[\s.\-]?\(?\d{2,4}\)?[\s.\-]?\d{3,4}[\s.\-]?\d{3,5}(?!\d)/',
    ];

    /**
     * @param  list<string>  $nama  Nama orang yang juga disembunyikan (mis. panitia & akun); kurang dari 3 huruf diabaikan.
     */
    public static function bersihkan(string $teks, array $nama = []): string
    {
        foreach (self::POLA as $jenis => $pola) {
            $teks = preg_replace($pola, self::PENANDA[$jenis], $teks);
        }

        foreach (self::variasiNama($nama) as $n) {
            $teks = preg_replace('/(?<![\p{L}])'.preg_quote($n, '/').'(?![\p{L}])/iu', self::PENANDA['nama'], $teks);
        }

        return $teks;
    }

    /**
     * Jenis PII yang ada di teks (kunci PENANDA, tanpa nama), mis. untuk catatan riwayat atau peringatan.
     *
     * @return list<string>
     */
    public static function jenis(string $teks): array
    {
        $ada = [];
        foreach (self::POLA as $jenis => $pola) {
            if (preg_match($pola, $teks)) {
                $ada[] = $jenis;
                $teks = preg_replace($pola, '', $teks);
            }
        }

        return $ada;
    }

    /**
     * Nama utuh dan nama tanpa gelar di belakang koma ("Ahmad Fauzi, S.Pd." → juga "Ahmad Fauzi" bila ≥ 2 kata),
     * terpanjang dulu agar nama bergelar diganti utuh.
     *
     * @param  list<string>  $nama
     * @return list<string>
     */
    private static function variasiNama(array $nama): array
    {
        $variasi = [];
        foreach ($nama as $n) {
            $n = trim(preg_replace('/\s+/u', ' ', $n));
            $inti = trim(explode(',', $n)[0]);
            foreach ([$n, $inti] as $v) {
                if (mb_strlen($v) >= 3 && ($v === $n || str_contains($v, ' '))) {
                    $variasi[mb_strtolower($v)] = $v;
                }
            }
        }
        $variasi = array_values($variasi);
        usort($variasi, fn (string $a, string $b) => mb_strlen($b) <=> mb_strlen($a));

        return $variasi;
    }
}
