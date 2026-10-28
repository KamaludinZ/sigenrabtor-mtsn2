<?php

namespace App\Support\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Contracts\RiwayatKunciRepository;
use App\Enums\StatusKegiatan;
use App\Support\MasterKode;

/**
 * Riwayat kunci & pembukaan data final (PRD F18, N7: pengunggah arsip final dan pembuka kunci tercatat) — log hanya-tambah
 * lewat RiwayatKunciRepository, dicatat oleh KunciFinal::kunci & FinalOtomatis (`kunci_final`),
 * KeputusanPemeriksaan::putuskan buka kunci (`buka_kunci`, keterangan = alasan), dan KelolaArsipFinal (`unggah_final`,
 * `ganti_final`, `hapus_final`, keterangan = `keteranganArsip()`). Dibaca endpoint `kegiatan.riwayat_kunci` (per kegiatan)
 * dan admin `pemeriksaan.riwayat_kunci` (semua kegiatan satu tahun anggaran):
 * - `untuk($kegiatan)` = log + entri riwayat_status `finalkan`/`buka_kunci` lama yang belum tercatat di log (sebelum
 *   log ada), terbaru dulu;
 * - `semua($tahun, $aksi, $pengguna)` = gabungan semua kegiatan tahun itu (admin), tiap entri membawa kegiatan.
 */
final class RiwayatKunci
{
    public const AKSI = [
        'kunci_final' => 'Dikunci final',
        'buka_kunci' => 'Kunci dibuka',
        'unggah_final' => 'Arsip final diunggah',
        'ganti_final' => 'Arsip final diganti',
        'hapus_final' => 'Arsip final dihapus',
    ];

    public const PER_HALAMAN = 50;

    /** Aksi riwayat_status → aksi log. */
    private const DARI_RIWAYAT = ['finalkan' => 'kunci_final', 'buka_kunci' => 'buka_kunci'];

    /**
     * @param  array{id?: int|null, name: string}  $pengguna
     * @param  string|null  $pada  Waktu peristiwa (entri riwayat_status); null = sekarang.
     */
    public static function catat(int $kegiatanId, string $aksi, string $keterangan, array $pengguna, ?string $pada = null): void
    {
        $kegiatan = app(KegiatanRepository::class)->temukan($kegiatanId);
        app(RiwayatKunciRepository::class)->catat($kegiatanId, $aksi, $keterangan, $pengguna, $pada ?? now()->toIso8601String(), $kegiatan === null ? null : TahunKegiatan::dari($kegiatan));
    }

    /**
     * "RAB DIPA — dipa ttd.pdf (245 KB)".
     *
     * @param  array{nama_berkas: string, ukuran: int}  $arsip
     */
    public static function keteranganArsip(string $sumber, array $arsip): string
    {
        return "RAB {$sumber} — {$arsip['nama_berkas']} (".ArsipFinal::ukuran((int) $arsip['ukuran']).')';
    }

    /**
     * @param  array<string, mixed>  $kegiatan
     * @return list<array{aksi: string, label: string, keterangan: string, oleh: string, pada: string, asal: string}>
     */
    public static function untuk(array $kegiatan): array
    {
        $log = app(RiwayatKunciRepository::class)->untukKegiatan((int) $kegiatan['id']);
        $tercatat = array_flip(array_map(fn (array $e) => $e['aksi'].'|'.$e['pada'], $log));

        $baris = array_map(fn (array $e) => [
            'aksi' => $e['aksi'],
            'label' => self::AKSI[$e['aksi']] ?? $e['aksi'],
            'keterangan' => $e['keterangan'],
            'oleh' => $e['pengguna'],
            'pada' => $e['pada'],
            'asal' => 'log',
        ], $log);
        foreach ((array) ($kegiatan['riwayat_status'] ?? []) as $r) {
            $aksi = self::DARI_RIWAYAT[$r['aksi'] ?? ''] ?? null;
            if ($aksi !== null && ! isset($tercatat[$aksi.'|'.$r['pada']])) {
                $baris[] = ['aksi' => $aksi, 'label' => self::AKSI[$aksi], 'keterangan' => (string) ($r['catatan'] ?? ''), 'oleh' => $r['oleh'], 'pada' => $r['pada'], 'asal' => 'riwayat_status'];
            }
        }

        // Terbaru dulu; urutan pencatatan dipertahankan untuk waktu yang sama (sort stabil).
        usort($baris, fn (array $a, array $b) => strtotime($b['pada']) <=> strtotime($a['pada']));

        return $baris;
    }

    /**
     * Data endpoint `kegiatan.riwayat_kunci`.
     *
     * @param  array<string, mixed>  $kegiatan
     * @return array<string, mixed>
     */
    public static function data(array $kegiatan): array
    {
        $status = StatusKegiatan::tryFrom((string) ($kegiatan['status'] ?? '')) ?? StatusKegiatan::Draf;
        $jejak = KunciDokumen::dari($kegiatan);

        return [
            'kegiatan_id' => (int) $kegiatan['id'],
            'status' => $status->value,
            'terkunci' => $status === StatusKegiatan::Final,
            'jejak' => $jejak === null ? null : ['pada' => $jejak->pada, 'oleh' => $jejak->oleh, 'kalimat' => $jejak->kalimat()],
            'riwayat' => $riwayat = self::untuk($kegiatan),
            'jumlah' => count($riwayat),
        ];
    }

    /**
     * Riwayat semua kegiatan satu tahun anggaran (admin), terbaru dulu, tersaring aksi (''/tak dikenal = semua).
     *
     * @return list<array<string, mixed>>
     */
    public static function semua(int $tahun, string $aksi = ''): array
    {
        $baris = [];
        foreach (app(KegiatanRepository::class)->semua() as $k) {
            if (TahunKegiatan::dari($k) !== $tahun) {
                continue;
            }
            $leading = filled($k['leading'] ?? null) ? MasterKode::leading($k['leading']) : null;
            foreach (self::untuk($k) as $e) {
                $baris[] = $e + ['kegiatan' => ['id' => (int) $k['id'], 'nama' => (string) ($k['nama_kegiatan'] ?? ''), 'leading' => $k['leading'] ?? null, 'nama_leading' => $leading['nama_unit'] ?? null]];
            }
        }
        if (isset(self::AKSI[$aksi])) {
            $baris = array_values(array_filter($baris, fn (array $e) => $e['aksi'] === $aksi));
        }
        usort($baris, fn (array $a, array $b) => strtotime($b['pada']) <=> strtotime($a['pada']));

        return $baris;
    }
}
