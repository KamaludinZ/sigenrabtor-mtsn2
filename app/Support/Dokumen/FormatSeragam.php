<?php

namespace App\Support\Dokumen;

use App\Contracts\KegiatanRepository;
use App\Support\Stub\StubData;

/**
 * Format seragam dokumen madrasah (PRD §9 Format Seragam: kop & profil, susunan baku, penomoran otomatis, pilihan
 * template) — dipakai halaman admin `Pengaturan\FormatSeragam`:
 * - `TOKEN` = penanda pola nomor dokumen → keterangan; `nomor($pola, $urut, $nilai, $digit)` mengganti penanda (urut
 *   berangka nol di depan sepanjang `$digit`, bulan angka & Romawi, tahun, jenis, leading, sumber dana, singkatan
 *   madrasah); `bulanRomawi()`; `galatPola()` (wajib, ≤ MAKS_POLA, memuat {urut}, hanya penanda TOKEN).
 * - `susunan()` = susunan baku KAK/TOR (sembilan bagian, judul dari SusunanKak) dan RAB (baris kepala SusunanRab + kolom
 *   TabelRab::KOLOM) dibaca dari susunan contoh kegiatan sehingga selalu sama dengan dokumen yang tercetak.
 */
final class FormatSeragam
{
    public const TOKEN = [
        '{urut}' => 'Nomor urut dokumen jenis itu di tahun anggaran (nol di depan)',
        '{jenis}' => 'Singkatan jenis dokumen (KAK, TOR, RAB, …)',
        '{leading}' => 'Kode leading kegiatan',
        '{sumber}' => 'Sumber dana (DIPA/Komite)',
        '{bulan}' => 'Bulan dokumen (01–12)',
        '{bulan_romawi}' => 'Bulan dokumen dalam angka Romawi (I–XII)',
        '{tahun}' => 'Tahun anggaran',
        '{madrasah}' => 'Nama singkat madrasah tanpa spasi',
    ];

    public const MAKS_POLA = 80;

    /** Singkatan jenis dokumen untuk penanda {jenis}. */
    public const SINGKATAN = ['KAK+TOR' => 'KAK-TOR', 'KAK' => 'KAK', 'TOR' => 'TOR', 'RAB' => 'RAB', 'lengkap' => 'DOK'];

    private const ROMAWI = [1 => 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];

    public static function bulanRomawi(int $bulan): string
    {
        return self::ROMAWI[$bulan] ?? '';
    }

    /**
     * @param  array{jenis?: string, leading?: string|null, sumber?: string|null, bulan?: int, tahun?: int, madrasah?: string}  $nilai
     */
    public static function nomor(string $pola, int $urut, array $nilai, int $digit = 3): string
    {
        $bulan = (int) ($nilai['bulan'] ?? 1);
        $ganti = [
            '{urut}' => str_pad((string) $urut, max(1, $digit), '0', STR_PAD_LEFT),
            '{jenis}' => self::SINGKATAN[$nilai['jenis'] ?? ''] ?? (string) ($nilai['jenis'] ?? ''),
            '{leading}' => (string) ($nilai['leading'] ?? ''),
            '{sumber}' => mb_strtoupper((string) ($nilai['sumber'] ?? '')),
            '{bulan}' => str_pad((string) $bulan, 2, '0', STR_PAD_LEFT),
            '{bulan_romawi}' => self::bulanRomawi($bulan),
            '{tahun}' => (string) ($nilai['tahun'] ?? StubData::tahunAktif()['tahun']),
            '{madrasah}' => str_replace(' ', '', (string) ($nilai['madrasah'] ?? StubData::madrasah()['nama_singkat'])),
        ];

        return trim((string) preg_replace('#/{2,}#', '/', strtr($pola, $ganti)), '/');
    }

    public static function galatPola(string $pola): ?string
    {
        $pola = trim($pola);
        preg_match_all('/\{[a-z_]+\}/', $pola, $penanda);
        $asing = array_values(array_diff(array_unique($penanda[0]), array_keys(self::TOKEN)));

        return match (true) {
            $pola === '' => 'Pola nomor wajib diisi.',
            mb_strlen($pola) > self::MAKS_POLA => 'Pola nomor paling banyak '.self::MAKS_POLA.' karakter.',
            ! str_contains($pola, '{urut}') => 'Pola nomor wajib memuat {urut} agar setiap dokumen bernomor berbeda.',
            $asing !== [] => 'Penanda '.implode(', ', $asing).' tidak dikenal.',
            default => null,
        };
    }

    /**
     * @return array{kak: list<array{nomor: int, judul: string}>, rab_kepala: list<string>, rab_kolom: list<string>}
     */
    public static function susunan(): array
    {
        $contoh = app(KegiatanRepository::class)->semua()[0] ?? ['id' => 0, 'nama_kegiatan' => 'Contoh kegiatan', 'sumber_dana' => []];
        $sumber = $contoh['sumber_dana'][0]['sumber'] ?? null;

        return [
            'kak' => array_map(fn (array $b) => ['nomor' => (int) $b['nomor'], 'judul' => (string) $b['judul']], SusunanKak::dari($contoh, $sumber)->bagian),
            'rab_kepala' => array_column(SusunanRab::dari($contoh, $sumber)->kepala, 'label'),
            'rab_kolom' => array_column(TabelRab::KOLOM, 0),
        ];
    }
}
