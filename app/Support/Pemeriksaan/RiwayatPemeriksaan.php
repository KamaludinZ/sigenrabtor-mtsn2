<?php

namespace App\Support\Pemeriksaan;

use App\Support\Kegiatan\TahunKegiatan;
use App\Support\MasterKode;
use Illuminate\Support\Carbon;

/**
 * Riwayat pemeriksaan (PRD F17–F18, N7; calon tabel `pemeriksaan_dokumen` + `activity_logs`) yang disusun dari
 * riwayat_status kegiatan dan arsip final: per kegiatan dikelompokkan menjadi putaran — tiap pengajuan membuka putaran
 * baru, hasilnya keputusan terakhir di putaran itu — dan, untuk halaman Pemeriksaan, log semua peristiwa pemeriksaan.
 */
final class RiwayatPemeriksaan
{
    /** Aksi riwayat yang termasuk peristiwa pemeriksaan → [ikon, nada]. */
    public const AKSI = [
        'ajukan' => ['check-badge', 'info'],
        'tarik' => ['history', 'netral'],
        'setujui' => ['check-circle', 'baik'],
        'kembalikan' => ['alert', 'peringatan'],
        'finalkan' => ['lock', 'baik'],
        'buka_kunci' => ['lock', 'peringatan'],
    ];

    /** Hasil putaran → label. */
    public const HASIL = [
        'penyusunan' => 'Penyusunan',
        'berjalan' => 'Sedang diperiksa',
        'ditarik' => 'Ditarik leading',
        'dikembalikan' => 'Dikembalikan',
        'disetujui' => 'Disetujui',
        'final' => 'Final',
        'dibuka' => 'Kunci dibuka',
    ];

    /**
     * Putaran pemeriksaan satu kegiatan, terbaru dulu; peristiwa di tiap putaran juga terbaru dulu. Putaran 0 =
     * penyusunan sebelum pengajuan pertama (hanya bila ada riwayatnya). Unggahan arsip final masuk putaran tempat terjadinya.
     *
     * @param  array<string, mixed>  $kegiatan
     * @param  array<string, array<string, mixed>>  $arsip  ArsipFinalRepository::untukKegiatan().
     * @return list<array{nomor: int, hasil: string, diajukan: string|null, diputuskan: string|null, lama_hari: int|null, peristiwa: list<array{jenis: string, aksi: string, oleh: string, peran: string|null, pada: string, catatan: string|null, sumber: string|null}>}>
     */
    public static function putaran(array $kegiatan, array $arsip = []): array
    {
        $putaran = [];
        $nomor = 0;

        foreach (array_values((array) ($kegiatan['riwayat_status'] ?? [])) as $r) {
            $aksi = (string) ($r['aksi'] ?? '');
            if ($aksi === 'ajukan') {
                $putaran[] = ['nomor' => ++$nomor, 'diajukan' => (string) ($r['pada'] ?? ''), 'peristiwa' => []];
            } elseif ($putaran === []) {
                $putaran[] = ['nomor' => 0, 'diajukan' => null, 'peristiwa' => []];
            }
            $putaran[array_key_last($putaran)]['peristiwa'][] = self::peristiwa('status', $aksi, $r);
        }

        foreach ($arsip as $sumber => $a) {
            $indeks = self::putaranPada($putaran, (string) $a['diunggah_pada']);
            if ($indeks !== null) {
                $putaran[$indeks]['peristiwa'][] = self::peristiwa('arsip', 'unggah_arsip', ['oleh' => $a['diunggah_oleh'], 'pada' => $a['diunggah_pada']], (string) $sumber);
            }
        }

        $hasil = array_map(function (array $p) {
            usort($p['peristiwa'], fn (array $a, array $b) => strtotime($a['pada']) <=> strtotime($b['pada']));
            $keputusan = collect($p['peristiwa'])->first(fn (array $e) => in_array($e['aksi'], ['setujui', 'kembalikan', 'tarik'], true));
            $p['hasil'] = $p['nomor'] === 0 ? 'penyusunan' : self::hasil($p['peristiwa']);
            $p['diputuskan'] = $keputusan['pada'] ?? null;
            $p['lama_hari'] = $p['diajukan'] ? (int) Carbon::parse($p['diajukan'])->startOfDay()->diffInDays(Carbon::parse($p['diputuskan'] ?? now())->startOfDay()) : null;
            $p['peristiwa'] = array_reverse($p['peristiwa']);

            return $p;
        }, $putaran);

        return array_reverse($hasil);
    }

    /**
     * Log peristiwa pemeriksaan semua kegiatan tahun $tahun, terbaru dulu, tersaring aksi/leading/cari (nama kegiatan,
     * pelaku, catatan).
     *
     * @param  list<array<string, mixed>>  $kegiatan
     * @param  array{aksi?: string, leading?: string, cari?: string}  $saringan
     * @return list<array{kegiatan_id: int, kegiatan: string, leading: string|null, nama_leading: string|null, aksi: string, oleh: string, peran: string|null, pada: string, catatan: string|null}>
     */
    public static function log(array $kegiatan, int $tahun, array $saringan = []): array
    {
        $cari = mb_strtolower(trim($saringan['cari'] ?? ''));
        $log = [];

        foreach ($kegiatan as $k) {
            if (TahunKegiatan::dari($k) !== $tahun || (($saringan['leading'] ?? '') !== '' && ($k['leading'] ?? null) !== $saringan['leading'])) {
                continue;
            }
            $nama = trim((string) ($k['nama_kegiatan'] ?? '')) ?: 'Kegiatan tanpa nama';

            foreach ((array) ($k['riwayat_status'] ?? []) as $r) {
                $aksi = (string) ($r['aksi'] ?? '');
                if (! isset(self::AKSI[$aksi]) || (($saringan['aksi'] ?? '') !== '' && $aksi !== $saringan['aksi'])) {
                    continue;
                }
                $baris = ['kegiatan_id' => (int) $k['id'], 'kegiatan' => $nama, 'leading' => $k['leading'] ?? null, 'nama_leading' => MasterKode::leading($k['leading'] ?? null)['nama_unit'] ?? null] + self::peristiwa('status', $aksi, $r);
                if ($cari !== '' && ! str_contains(mb_strtolower($nama.' '.$baris['oleh'].' '.$baris['catatan']), $cari)) {
                    continue;
                }
                unset($baris['jenis'], $baris['sumber']);
                $log[] = $baris;
            }
        }

        usort($log, fn (array $a, array $b) => strtotime($b['pada']) <=> strtotime($a['pada']));

        return $log;
    }

    /** "Pengajuan diputuskan dalam 2 hari" / "Menunggu 3 hari" / "Diputuskan di hari yang sama". */
    public static function teksLama(array $putaran): ?string
    {
        if ($putaran['lama_hari'] === null || $putaran['hasil'] === 'penyusunan') {
            return null;
        }

        if ($putaran['diputuskan'] === null) {
            return $putaran['lama_hari'] === 0 ? 'Diajukan hari ini' : "Menunggu {$putaran['lama_hari']} hari";
        }

        return $putaran['lama_hari'] === 0 ? 'Diputuskan di hari yang sama' : "Diputuskan dalam {$putaran['lama_hari']} hari";
    }

    /**
     * @param  array<string, mixed>  $r
     * @return array{jenis: string, aksi: string, oleh: string, peran: string|null, pada: string, catatan: string|null, sumber: string|null}
     */
    private static function peristiwa(string $jenis, string $aksi, array $r, ?string $sumber = null): array
    {
        return [
            'jenis' => $jenis,
            'aksi' => $aksi,
            'oleh' => (string) ($r['oleh'] ?? ''),
            'peran' => $r['peran'] ?? null,
            'pada' => (string) ($r['pada'] ?? ''),
            'catatan' => filled($r['catatan'] ?? null) ? (string) $r['catatan'] : null,
            'sumber' => $sumber,
        ];
    }

    /** @param  list<array{aksi: string}>  $peristiwa  Urut lama → baru. */
    private static function hasil(array $peristiwa): string
    {
        $hasil = 'berjalan';
        foreach ($peristiwa as $e) {
            $hasil = match ($e['aksi']) {
                'setujui' => 'disetujui',
                'kembalikan' => 'dikembalikan',
                'tarik' => 'ditarik',
                'finalkan' => 'final',
                'buka_kunci' => 'dibuka',
                default => $hasil,
            };
        }

        return $hasil;
    }

    /** Indeks putaran terakhir yang dimulai sebelum $pada (atau putaran pertama). */
    private static function putaranPada(array $putaran, string $pada): ?int
    {
        $indeks = $putaran === [] ? null : 0;
        foreach ($putaran as $i => $p) {
            $mulai = $p['diajukan'] ?? ($p['peristiwa'][0]['pada'] ?? null);
            if ($mulai !== null && strtotime($mulai) <= strtotime($pada)) {
                $indeks = $i;
            }
        }

        return $indeks;
    }
}
