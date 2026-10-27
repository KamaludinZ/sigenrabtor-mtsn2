<?php

namespace App\Support\Pengaturan;

/**
 * Aturan isian latar belakang baku (PRD §13): paragraf baku satu paragraf dengan penanda `{tahun}` saja, kalimat penutup
 * wajib memuat `{nama_kegiatan}` tepat sekali (tempat sisip nama kegiatan) dan boleh `{tahun}`.
 */
final class LatarBelakangBaku
{
    public const MAKS_PARAGRAF = 3000;

    public const MAKS_PENUTUP = 500;

    /** Satu paragraf: spasi & baris baru dirapatkan. */
    public static function rapikan(string $teks): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $teks));
    }

    public static function galatParagraf(string $teks): ?string
    {
        if ($teks === '') {
            return 'Paragraf latar belakang baku wajib diisi.';
        }
        if (mb_strlen($teks) > self::MAKS_PARAGRAF) {
            return 'Terlalu panjang (maksimal '.self::MAKS_PARAGRAF.' karakter).';
        }
        $asing = TeksBaku::penandaAsing($teks, [TeksBaku::TAHUN]);
        if ($asing !== []) {
            return 'Penanda '.implode(', ', $asing).' tidak dikenal — di paragraf baku hanya '.TeksBaku::TAHUN.' yang bisa dipakai; nama kegiatan ada di kalimat penutup.';
        }

        return null;
    }

    public static function galatPenutup(string $teks): ?string
    {
        if ($teks === '') {
            return 'Kalimat penutup wajib diisi.';
        }
        if (mb_strlen($teks) > self::MAKS_PENUTUP) {
            return 'Terlalu panjang (maksimal '.self::MAKS_PENUTUP.' karakter).';
        }
        $asing = TeksBaku::penandaAsing($teks, [TeksBaku::TAHUN, TeksBaku::NAMA_KEGIATAN]);
        if ($asing !== []) {
            return 'Penanda '.implode(', ', $asing).' tidak dikenal — gunakan '.TeksBaku::NAMA_KEGIATAN.' atau '.TeksBaku::TAHUN.'.';
        }

        return match (substr_count($teks, TeksBaku::NAMA_KEGIATAN)) {
            0 => 'Sisipkan '.TeksBaku::NAMA_KEGIATAN.' di tempat nama kegiatan tercetak.',
            1 => null,
            default => TeksBaku::NAMA_KEGIATAN.' hanya boleh dipakai sekali.',
        };
    }
}
