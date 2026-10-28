<?php

namespace App\Support\Pengaturan;

/**
 * Aturan butir dasar hukum (PRD §13): satu butir = satu kalimat tanpa tanda baca akhir; saat dicetak penanda `{tahun}`
 * dan SK RKTM/RKAM (`{no_sk_rktm}`/`{tanggal_sk_rktm}`, dari tahun anggaran — "…" selama kosong) diisi dan tanda baca
 * akhir disusun menurut urutan (titik koma, butir terakhir titik) sehingga menambah, menghapus, atau mengurutkan butir
 * tidak perlu membetulkan tanda baca butir lain.
 */
final class DaftarDasarHukum
{
    public const MAKS_BUTIR = 30;

    public const MAKS_KARAKTER = 500;

    /** Satu baris, spasi tunggal, tanpa tanda baca akhir (; . ,). */
    public static function rapikan(string $teks): string
    {
        $teks = trim((string) preg_replace('/\s+/u', ' ', $teks));

        return rtrim($teks, ' ;.,');
    }

    /**
     * Butir siap cetak tahun $tahun, urut cetak.
     *
     * @param  list<string>  $butir  Teks mentah.
     * @return list<string>
     */
    public static function cetak(array $butir, int $tahun): array
    {
        $akhir = count($butir) - 1;
        $sk = SkRktm::nilai($tahun);

        return array_map(fn (string $teks, int $i) => self::rapikan(TeksBaku::cetak($teks, $tahun, $sk)).($i === $akhir ? '.' : ';'), $butir, array_keys($butir));
    }

    /** Penanda yang boleh di butir dasar hukum. */
    public static function boleh(): array
    {
        return [TeksBaku::TAHUN, ...array_keys(SkRktm::PENANDA)];
    }

    /**
     * Butir memakai penanda SK sementara SK tahun $tahun belum lengkap (dokumen perlu dilengkapi).
     *
     * @param  list<string>  $butir  Teks mentah.
     */
    public static function skKurang(array $butir, int $tahun): bool
    {
        return collect($butir)->contains(fn (string $teks) => SkRktm::dipakai($teks)) && ! SkRktm::lengkap($tahun);
    }

    /**
     * Pesan galat isian butir (sudah dirapikan), atau null bila sah. $kecuali = id butir yang sedang diubah.
     *
     * @param  list<array{id: string, teks: string}>  $daftar
     */
    public static function galat(string $teks, array $daftar, ?string $kecuali = null): ?string
    {
        if ($teks === '') {
            return 'Butir dasar hukum wajib diisi.';
        }
        if (mb_strlen($teks) > self::MAKS_KARAKTER) {
            return 'Terlalu panjang (maksimal '.self::MAKS_KARAKTER.' karakter).';
        }

        $asing = TeksBaku::penandaAsing($teks, self::boleh());
        if ($asing !== []) {
            return 'Penanda '.implode(', ', $asing).' tidak dikenal — di dasar hukum hanya '.implode(', ', self::boleh()).' yang bisa dipakai.';
        }

        $kunci = mb_strtolower($teks);
        foreach ($daftar as $i => $butir) {
            if ($butir['id'] !== $kecuali && mb_strtolower(self::rapikan($butir['teks'])) === $kunci) {
                return 'Butir ini sudah ada di nomor '.($i + 1).'.';
            }
        }

        return null;
    }
}
