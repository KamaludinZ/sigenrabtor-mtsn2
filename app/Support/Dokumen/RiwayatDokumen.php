<?php

namespace App\Support\Dokumen;

use App\Contracts\KegiatanRepository;
use App\Contracts\RiwayatDokumenRepository;
use App\Enums\StatusKegiatan;
use App\Support\Kegiatan\TahunKegiatan;
use App\Support\MasterKode;
use App\Support\Pengaturan\TautanAkunLeading;

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

    /**
     * Catatan dalam cakupan akun: admin semua; akun leading dokumen leading-nya (TautanAkunLeading) atau buatannya.
     *
     * @param  array<string, mixed>  $entri
     * @param  array{id?: int|null, role: string, leadings?: list<string>}  $pengguna
     */
    public static function boleh(array $entri, array $pengguna): bool
    {
        return TautanAkunLeading::bolehAkses($pengguna, ($entri['kode_leading'] ?? '') ?: null)
            || ($entri['user_id'] !== null && $entri['user_id'] === ($pengguna['id'] ?? null));
    }

    /**
     * Catatan siap tampil dalam cakupan akun, terbaru dulu (+ `tautan_kegiatan` pratinjau bila kegiatannya tersimpan).
     *
     * @param  array{id?: int|null, role: string, leadings?: list<string>}  $pengguna
     * @return list<array<string, mixed>>
     */
    public static function untuk(array $pengguna): array
    {
        $ada = array_map(fn (array $k) => (int) $k['id'], app(KegiatanRepository::class)->semua());

        return array_values(array_map(function (array $c) use ($ada) {
            $t = self::tampil($c, $ada);

            return $t + ['tautan_kegiatan' => $t['tersedia'] ? route('kegiatan.pratinjau', array_filter(['kegiatan' => $c['kegiatan_id'], 'sumber' => $c['sumber']])) : null];
        }, array_filter(self::semua(), fn (array $c) => self::boleh($c, $pengguna))));
    }

    /**
     * Kelompok per tahun anggaran, tahun terbaru dulu (urutan catatan di dalamnya tetap).
     *
     * @param  list<array<string, mixed>>  $baris
     * @return array<int, list<array<string, mixed>>>
     */
    public static function perTahun(array $baris): array
    {
        $kelompok = [];
        foreach ($baris as $b) {
            $kelompok[(int) $b['tahun_anggaran']][] = $b;
        }
        krsort($kelompok);

        return $kelompok;
    }

    /**
     * @param  list<array<string, mixed>>  $baris
     * @return array{jumlah: int, kegiatan: int, tahun: int, arsip: int, draf: int, format: array<string, int>}
     */
    public static function ringkasan(array $baris): array
    {
        return [
            'jumlah' => count($baris),
            'kegiatan' => count(array_unique(array_map(fn (array $b) => $b['kegiatan_id'] ?? 'x:'.mb_strtolower($b['nama_kegiatan']), $baris))),
            'tahun' => count(array_unique(array_column($baris, 'tahun_anggaran'))),
            'arsip' => count(array_filter($baris, fn (array $b) => $b['asal'] === 'arsip')),
            'draf' => count(array_filter($baris, fn (array $b) => $b['bertanda_draf'])),
            'format' => array_map(fn (string $f) => count(array_filter($baris, fn (array $b) => $b['format'] === $f)), array_combine(array_keys(self::FORMAT), array_keys(self::FORMAT))),
        ];
    }
}
