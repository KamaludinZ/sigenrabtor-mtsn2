<?php

namespace App\Support\Dokumen;

/**
 * Hasil susunan KAK/TOR satu dokumen (PRD §7B.2, §6 `documents`): baris tabel `dokumen_bagian` (migrasi 000039) — satu
 * per bagian 1–9 SusunanKak — berisi judul, blok isi siap cetak (tanpa penanda layar `KUNCI_LAYAR`), status bagian
 * (`STATUS`, dari SusunanOtomatis: kurang bila ada isian kurang, periksa bila lengkap tetapi ada temuan, selain itu
 * lengkap) beserta daftar kurang/periksa, dan sidik md5 isi bagian. `sidik()` = sidik seluruh hasil (`documents.sidik_isi`)
 * untuk mengenali perubahan sejak disusun. Bagian hanya untuk dokumen yang memuat KAK/TOR (`JENIS`, bukan RAB saja).
 * Aturan bentuk blok (`JENIS_BLOK` jenis → kunci isi wajib) sama dengan CHECK `isi_bagian_kak_sah()` di database.
 */
final class HasilKakTor
{
    /** Jenis blok SusunanKak → kunci isi wajibnya (null = tanpa isi; penanda pagu). */
    public const JENIS_BLOK = [
        'paragraf' => 'teks',
        'subjudul' => 'teks',
        'daftar' => 'butir',
        'kosong' => 'pesan',
        'menunggu' => 'pesan',
        'pengesahan' => 'kolom',
        'matrik' => 'tahapan',
        'jadwal' => 'baris',
        'pagu' => null,
    ];

    /** Kunci blok yang hanya untuk layar (penyuntingan & penanda kosong), tidak ikut tersimpan. */
    public const KUNCI_LAYAR = ['sunting', 'sunting_butir', 'asal', 'wajib'];

    public const STATUS = ['lengkap', 'kurang', 'periksa'];

    /** Jenis dokumen yang memuat KAK/TOR (punya bagian). */
    public const JENIS = ['KAK+TOR', 'KAK', 'TOR', 'lengkap'];

    public const JUMLAH_BAGIAN = 9;

    public const MAKS_JUDUL = 100;

    /**
     * Baris dokumen_bagian dari susunan (urut nomor).
     *
     * @return list<array{nomor: int, judul: string, isi: list<array<string, mixed>>, status: string, kurang: list<string>, periksa: list<string>, sidik: string}>
     */
    public static function bagian(SusunanKak $kak, SusunanOtomatis $susunan): array
    {
        $status = array_column($susunan->bagian, null, 'nomor');

        return array_map(function (array $b) use ($status) {
            $isi = array_map(fn (array $blok) => array_diff_key($blok, array_flip(self::KUNCI_LAYAR)), array_values($b['isi']));
            $s = $status[$b['nomor']] ?? ['status' => 'lengkap', 'kurang' => [], 'periksa' => []];

            return [
                'nomor' => $b['nomor'],
                'judul' => $b['judul'],
                'isi' => $isi,
                'status' => $s['status'],
                'kurang' => array_values($s['kurang']),
                'periksa' => $s['status'] === 'kurang' ? [] : array_values($s['periksa']),
                'sidik' => md5((string) json_encode([$b['judul'], $isi], JSON_UNESCAPED_UNICODE)),
            ];
        }, $kak->bagian);
    }

    /**
     * Sidik seluruh hasil susunan (urut nomor bagian).
     *
     * @param  list<array{nomor: int, sidik: string}>  $bagian
     */
    public static function sidik(array $bagian): string
    {
        usort($bagian, fn (array $a, array $b) => $a['nomor'] <=> $b['nomor']);

        return md5(implode('|', array_map(fn (array $b) => $b['nomor'].':'.$b['sidik'], $bagian)));
    }
}
