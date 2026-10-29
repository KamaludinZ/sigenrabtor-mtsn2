<?php

namespace App\Support\Dokumen;

use App\Contracts\RiwayatDokumenRepository;
use App\Enums\StatusKegiatan;
use App\Support\Kegiatan\TahunKegiatan;
use App\Support\MasterKode;

/**
 * Struktur riwayat dokumen (PRD §7 Riwayat Dokumen, F28) lewat RiwayatDokumenRepository: `entri()` menyusun satu catatan
 * dari kegiatan saat dicetak/diunduh/diarsipkan (cuplikan nama, leading, tahun, status, cap DRAF, isi ringkas), `kunciVersi()`
 * = kegiatan × jenis × sumber × format (penomoran versi), dan `tampil()` = catatan siap tampil (label jenis/format/asal/
 * status, ukuran terbaca, nama unit leading, ketersediaan kegiatan untuk dibuka ulang). Daftar & penyaringan di halaman
 * Riwayat Dokumen memakai struktur ini.
 */
final class RiwayatDokumen
{
    /** Format berkas → [label, ikon]. */
    public const FORMAT = [
        'pdf' => ['PDF', 'file-text'],
        'docx' => ['Word', 'file-text'],
        'xlsx' => ['Excel', 'sheet'],
    ];

    /** Asal catatan → label. */
    public const ASAL = [
        'cetak' => 'Dicetak',
        'unduh' => 'Diunduh',
        'arsip' => 'Arsip final',
    ];

    /**
     * Catatan riwayat satu berkas dokumen kegiatan (tanpa id, versi & waktu — diisi repositori).
     *
     * @param  array<string, mixed>  $kegiatan
     * @param  array{id?: int|null, name: string}  $pengguna
     * @param  array{total_rab?: int|null, bagian_lengkap?: int|null, jumlah_bagian?: int|null}  $ringkas
     * @return array<string, mixed>
     */
    public static function entri(array $kegiatan, string $jenis, ?string $sumber, string $format, string $asal, string $namaBerkas, ?int $ukuran, bool $draf, array $pengguna, array $ringkas = [], ?string $nomor = null): array
    {
        return [
            'kegiatan_id' => isset($kegiatan['id']) ? (int) $kegiatan['id'] : null,
            'tahun_anggaran' => TahunKegiatan::dari($kegiatan),
            'kode_leading' => (string) ($kegiatan['leading'] ?? ''),
            'nama_kegiatan' => trim((string) ($kegiatan['nama_kegiatan'] ?? '')) ?: 'Tanpa nama',
            'jenis' => $jenis,
            'sumber' => $sumber,
            'format' => $format,
            'asal' => $asal,
            'nama_berkas' => $namaBerkas,
            'ukuran' => $ukuran,
            'status' => (StatusKegiatan::tryFrom((string) ($kegiatan['status'] ?? '')) ?? StatusKegiatan::Draf)->value,
            'bertanda_draf' => $draf,
            'nomor_dokumen' => $nomor,
            'ringkas' => [
                'total_rab' => $ringkas['total_rab'] ?? null,
                'bagian_lengkap' => $ringkas['bagian_lengkap'] ?? null,
                'jumlah_bagian' => $ringkas['jumlah_bagian'] ?? null,
            ],
            'user_id' => isset($pengguna['id']) ? (int) $pengguna['id'] : null,
            'dibuat_oleh' => $pengguna['name'],
        ];
    }

    /** Awalan nama berkas per jenis dokumen (sama dengan StatusCetak::namaBerkas / CetakDokumen::jenisBerkas). */
    public const AWALAN = ['lengkap' => 'KAK-TOR_RAB', 'KAK+TOR' => 'KAK-TOR', 'KAK' => 'KAK', 'TOR' => 'TOR', 'RAB' => 'RAB'];

    /** Nama berkas baku dengan tahun dokumen itu sendiri, mis. "RAB_Supervisi_Akademik_DIPA_2025_DRAF.xlsx". */
    public static function namaBerkas(string $jenis, string $namaKegiatan, ?string $sumber, int $tahun, bool $draf, string $format): string
    {
        $nama = rtrim(mb_substr(trim((string) preg_replace('/[^A-Za-z0-9]+/', '_', $namaKegiatan), '_') ?: 'Kegiatan', 0, 60), '_');

        return implode('_', [self::AWALAN[$jenis] ?? $jenis, $nama, ...($sumber ? [mb_strtoupper($sumber)] : []), (string) $tahun]).($draf ? '_'.StatusCetak::TANDA_AIR : '').'.'.$format;
    }

    /** @param  array<string, mixed>  $entri */
    public static function kunciVersi(array $entri): string
    {
        return implode('|', [$entri['kegiatan_id'] ?? 'x:'.mb_strtolower($entri['nama_kegiatan'] ?? ''), $entri['jenis'], $entri['sumber'] ?? '', $entri['format']]);
    }

    /**
     * Catatan siap tampil.
     *
     * @param  array<string, mixed>  $entri
     * @param  list<int>  $kegiatanAda  Id kegiatan yang masih tersimpan (untuk dibuka ulang).
     * @return array<string, mixed>
     */
    public static function tampil(array $entri, array $kegiatanAda = []): array
    {
        $status = StatusKegiatan::tryFrom((string) $entri['status']) ?? StatusKegiatan::Draf;

        return $entri + [
            'label_jenis' => DokumenKegiatan::JENIS[$entri['jenis']] ?? $entri['jenis'],
            'label_format' => self::FORMAT[$entri['format']][0] ?? strtoupper($entri['format']),
            'ikon_format' => self::FORMAT[$entri['format']][1] ?? 'file-text',
            'label_asal' => self::ASAL[$entri['asal']] ?? $entri['asal'],
            'label_status' => $status->label(),
            'nama_leading' => MasterKode::leading($entri['kode_leading'])['nama_unit'] ?? null,
            'ukuran_teks' => $entri['ukuran'] ? self::ukuran((int) $entri['ukuran']) : null,
            'tersedia' => $entri['kegiatan_id'] !== null && in_array((int) $entri['kegiatan_id'], $kegiatanAda, true),
        ];
    }

    /** "245 KB", "1,2 MB" (sama dengan klien `ukuranBerkas`). */
    public static function ukuran(int $bita): string
    {
        return $bita < 1024 * 1024 ? max(1, (int) round($bita / 1024)).' KB' : str_replace('.', ',', number_format($bita / (1024 * 1024), 1)).' MB';
    }

    /** @return list<array<string, mixed>> Semua catatan, terbaru dulu. */
    public static function semua(): array
    {
        return app(RiwayatDokumenRepository::class)->semua();
    }
}
