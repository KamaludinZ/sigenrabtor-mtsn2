<?php

namespace App\Support\Dokumen;

use App\Contracts\TemplateDokumenRepository;
use App\Support\Kegiatan\TahunKegiatan;

/**
 * Template yang dipakai dokumen satu kegiatan (PRD §9 "template yang dipakai tercatat dan ikut tercetak pada hasil
 * dokumen") — ditampilkan di kepala lembar Pratinjau Dokumen, panel Unduh, dan data API pratinjau:
 * - `format` = format susunan baku KAK/TOR yang dipakai pratinjau, cetak, & PDF (FormatKakTor);
 * - `kak` = master Word KAK/TOR (PilihanTemplate::untukKegiatan: nama, jenis, catatan versi, pemilik, asal + label
 *   DaftarTemplate) atau null = Word disusun dari susunan baku; `ada_berkas` false = berkasnya hilang (Word kembali ke
 *   susunan baku);
 * - `cover` = template cover (CoverDokumen::untukKegiatan) atau null = bingkai bawaan.
 */
final class TemplateDipakai
{
    /** Asal pilihan master → label singkat. */
    public const ASAL = [
        'kegiatan' => 'dipilih untuk kegiatan ini',
        'leading' => 'aturan khusus leading (Format Seragam)',
        'default_leading' => 'default template leading',
        'jenis' => 'pilihan per jenis kegiatan (Format Seragam)',
        'bawaan' => 'bawaan',
    ];

    /**
     * @param  array<string, mixed>  $kegiatan
     * @return array{format: array{kunci: string, label: string}, kak: array{id: string, nama: string, jenis: string, catatan: string|null, leading: string|null, asal: string, label_asal: string, ada_berkas: bool}|null, cover: array{id: string, nama: string}|null}
     */
    public static function untuk(array $kegiatan, ?string $sumber = null, ?string $format = null): array
    {
        $tahun = TahunKegiatan::dari($kegiatan);
        $format = FormatKakTor::sah($format) ? $format : FormatKakTor::untuk($kegiatan, $tahun);
        $berlaku = PilihanTemplate::untukKegiatan($kegiatan, $tahun);
        $master = $berlaku['kak'] !== null ? app(TemplateDokumenRepository::class)->temukan($berlaku['kak']) : null;
        $cover = CoverDokumen::untukKegiatan($kegiatan, $sumber, $tahun);

        return [
            'format' => ['kunci' => $format, 'label' => FormatKakTor::label($format)],
            'kak' => $master === null ? null : [
                'id' => (string) $master['id'],
                'nama' => (string) $master['nama'],
                'jenis' => (string) $master['jenis'],
                'catatan' => $master['catatan'] ?? null,
                'leading' => $master['leading'] ?? null,
                'asal' => $berlaku['asal'],
                'label_asal' => self::ASAL[$berlaku['asal']] ?? $berlaku['asal'],
                'ada_berkas' => PilihanTemplate::masterKak($kegiatan, $tahun) !== null,
            ],
            'cover' => filled($cover['cover_id'] ?? null) && filled($cover['nama'] ?? null) ? ['id' => (string) $cover['cover_id'], 'nama' => (string) $cover['nama']] : null,
        ];
    }
}
