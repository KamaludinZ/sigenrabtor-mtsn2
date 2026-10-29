<?php

namespace App\Support\Akun;

use App\Contracts\ArsipFinalRepository;
use App\Contracts\KegiatanRepository;
use App\Contracts\PermohonanAkunRepository;
use App\Enums\StatusKegiatan;
use App\Support\Dokumen\RiwayatDokumen;
use App\Support\Format;
use App\Support\Kegiatan\AksesKegiatan;
use App\Support\Kegiatan\AlurStatus;
use App\Support\Kegiatan\ArsipFinal;
use App\Support\Kegiatan\PerbaikanKegiatan;
use App\Support\Kegiatan\TahunKegiatan;
use App\Support\Pengaturan\BagianPengaturan;
use App\Support\Pengaturan\KesiapanPengaturan;
use App\Support\Rab\AnggaranKegiatan;
use App\Support\Stub\StubData;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;

/**
 * Bagian pribadi Dashboard utama (PRD §8 Masuk & Akun: dokumen tersimpan atas nama pengguna & leading-nya; §11) untuk
 * akun yang sedang masuk — satu-satunya penyusun isinya, dari kegiatan tahun aktif dalam cakupan `AksesKegiatan::saring`:
 * - `tindakan` = hal yang menunggu akun ini (`TINDAKAN`, urut `URUTAN`): leading — perbaiki (Dikembalikan + catatan
 *   pemeriksa `PerbaikanKegiatan`), unggah arsip (Disetujui dengan `ArsipFinal::kurang`), ajukan (Draf yang lolos
 *   `AlurStatus::syaratAjukan`), lengkapi (Draf dengan n syarat kurang, paling sedikit dulu); admin — periksa (Diperiksa,
 *   pengajuan terlama dulu), permohonan akun menunggu (`PermohonanAkunRepository`), pengaturan wajib belum siap
 *   (`KesiapanPengaturan`) — maks. `BATAS_TINDAKAN` + `jumlah_tindakan`;
 * - `terakhir` = kegiatan yang terakhir diubah (maks. `BATAS_TERAKHIR`);
 * - `aktivitas` = riwayat status kegiatan + dokumen yang dicetak/diunduh/diarsipkan (`RiwayatDokumen::untuk`), terbaru
 *   dulu (maks. `BATAS_AKTIVITAS`);
 * - `pintasan` per peran (hanya rute yang terdaftar), `akun` (`DataAkun::untuk`: masuk sebelumnya dari riwayat masuk).
 */
final class BerandaAkun
{
    public const BATAS_TINDAKAN = 6;

    public const BATAS_TERAKHIR = 4;

    public const BATAS_AKTIVITAS = 5;

    /** Jenis tindakan → [label lencana, nada]. */
    public const TINDAKAN = [
        'perbaiki' => ['Perlu diperbaiki', 'bahaya'],
        'periksa' => ['Perlu diperiksa', 'perlu'],
        'arsip' => ['Unggah arsip final', 'perlu'],
        'permohonan' => ['Permohonan akun', 'perlu'],
        'pengaturan' => ['Pengaturan', 'info'],
        'ajukan' => ['Siap diajukan', 'siap'],
        'lengkapi' => ['Lengkapi isian', 'info'],
    ];

    public const URUTAN = ['perbaiki', 'periksa', 'arsip', 'permohonan', 'pengaturan', 'ajukan', 'lengkapi'];

    /**
     * @param  array{id: int, name: string, role: string, leadings: list<string>}  $pengguna
     * @return array<string, mixed>
     */
    public static function untuk(array $pengguna): array
    {
        $tahun = (int) StubData::tahunAktif()['tahun'];
        $kegiatan = array_values(array_filter(
            AksesKegiatan::saring(app(KegiatanRepository::class)->semua(), $pengguna),
            fn (array $k) => TahunKegiatan::dari($k) === $tahun,
        ));
        AnggaranKegiatan::muat($kegiatan);
        $tindakan = $pengguna['role'] === 'admin' ? self::tindakanAdmin($kegiatan) : self::tindakanLeading($kegiatan);
        usort($tindakan, fn (array $a, array $b) => [array_search($a['jenis'], self::URUTAN, true), $a['urut']] <=> [array_search($b['jenis'], self::URUTAN, true), $b['urut']]);

        return [
            'tahun' => $tahun,
            'jumlah_kegiatan' => count($kegiatan),
            'jumlah_tindakan' => count($tindakan),
            'tindakan' => array_map(fn (array $t) => array_diff_key($t, ['urut' => true]), array_slice($tindakan, 0, self::BATAS_TINDAKAN)),
            'terakhir' => self::terakhir($kegiatan),
            'aktivitas' => self::aktivitas($kegiatan, $pengguna),
            'pintasan' => self::pintasan($pengguna['role']),
            'akun' => DataAkun::untuk($pengguna),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $kegiatan
     * @return list<array<string, mixed>>
     */
    private static function tindakanLeading(array $kegiatan): array
    {
        $hasil = [];
        foreach ($kegiatan as $k) {
            $status = StatusKegiatan::tryFrom((string) ($k['status'] ?? '')) ?? StatusKegiatan::Draf;
            $id = (int) $k['id'];
            if ($status === StatusKegiatan::Dikembalikan) {
                $perbaikan = PerbaikanKegiatan::dari($k);
                $hasil[] = self::butir('perbaiki', $k, $perbaikan?->catatan ?: 'Dikembalikan admin — perbaiki datanya lalu ajukan ulang.', route('kegiatan.edit', $id), 'Perbaiki', -strtotime($perbaikan->pada ?? 'now'), $perbaikan?->oleh ? 'Dari '.$perbaikan->oleh : null);
            } elseif ($status === StatusKegiatan::Disetujui && ($kurang = ArsipFinal::kurang($k, app(ArsipFinalRepository::class)->untukKegiatan($id))) !== []) {
                $hasil[] = self::butir('arsip', $k, ArsipFinal::pesanKurang($kurang), route('kegiatan.edit', $id).'#arsip-final', 'Unggah arsip', 0);
            } elseif ($status === StatusKegiatan::Draf) {
                $syarat = AlurStatus::syaratAjukan($k);
                $hasil[] = $syarat === []
                    ? self::butir('ajukan', $k, 'Semua syarat pengajuan terpenuhi — ajukan agar diperiksa admin.', route('kegiatan.edit', $id), 'Ajukan', 0)
                    : self::butir('lengkapi', $k, (count($syarat) === 1 ? 'Belum bisa diajukan: ' : count($syarat).' syarat pengajuan belum terpenuhi, mis. ').array_values($syarat)[0], route('kegiatan.pratinjau', $id).'#checklist-isian', 'Lengkapi', count($syarat));
            }
        }

        return $hasil;
    }

    /**
     * @param  list<array<string, mixed>>  $kegiatan
     * @return list<array<string, mixed>>
     */
    private static function tindakanAdmin(array $kegiatan): array
    {
        $hasil = [];
        foreach ($kegiatan as $k) {
            if (($k['status'] ?? '') !== StatusKegiatan::Diperiksa->value) {
                continue;
            }
            $ajukan = collect($k['riwayat_status'] ?? [])->filter(fn (array $r) => ($r['aksi'] ?? null) === 'ajukan')->last();
            $hasil[] = self::butir('periksa', $k, $ajukan ? 'Diajukan '.$ajukan['oleh'].' · '.self::waktu((string) $ajukan['pada']) : 'Menunggu keputusan admin.', route('pemeriksaan.periksa', (int) $k['id']), 'Periksa', strtotime($ajukan['pada'] ?? 'now'));
        }
        $menunggu = array_filter(app(PermohonanAkunRepository::class)->semua(), fn (array $p) => ($p['status'] ?? '') === 'menunggu');
        if ($menunggu !== [] && Route::has('pengaturan.leading.index')) {
            $terlama = collect($menunggu)->sortBy('diajukan_pada')->first();
            $hasil[] = ['jenis' => 'permohonan', 'label' => self::TINDAKAN['permohonan'][0], 'nada' => self::TINDAKAN['permohonan'][1],
                'judul' => count($menunggu).' permohonan akun menunggu persetujuan', 'keterangan' => 'Terlama: '.$terlama['nama'].' · '.self::waktu((string) $terlama['diajukan_pada']),
                'tautan' => route('pengaturan.leading.index', ['tab' => 'akun']), 'label_tautan' => 'Tinjau', 'kegiatan_id' => null, 'status' => null, 'subjudul' => null, 'urut' => 0];
        }
        foreach (KesiapanPengaturan::tahunAktif()->butir as $i => $b) {
            if ($b['wajib'] && ! $b['siap'] && ($bagian = BagianPengaturan::cari($b['bagian'])) !== null && $bagian['url'] !== null) {
                $hasil[] = ['jenis' => 'pengaturan', 'label' => self::TINDAKAN['pengaturan'][0], 'nada' => self::TINDAKAN['pengaturan'][1],
                    'judul' => $b['judul'].' belum siap', 'keterangan' => $b['keterangan'], 'tautan' => $bagian['url'], 'label_tautan' => 'Atur',
                    'kegiatan_id' => null, 'status' => null, 'subjudul' => null, 'urut' => $i];
            }
        }

        return $hasil;
    }

    /** @param  array<string, mixed>  $k */
    private static function butir(string $jenis, array $k, string $keterangan, string $tautan, string $labelTautan, int|float $urut, ?string $subjudul = null): array
    {
        return [
            'jenis' => $jenis,
            'label' => self::TINDAKAN[$jenis][0],
            'nada' => self::TINDAKAN[$jenis][1],
            'judul' => trim((string) ($k['nama_kegiatan'] ?? '')) ?: 'Kegiatan tanpa nama',
            'keterangan' => $keterangan,
            'subjudul' => $subjudul,
            'tautan' => $tautan,
            'label_tautan' => $labelTautan,
            'kegiatan_id' => (int) $k['id'],
            'status' => (string) ($k['status'] ?? 'draf'),
            'urut' => $urut,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $kegiatan
     * @return list<array{id: int, nama: string, status: string, leading: string|null, diperbarui_pada: string|null, waktu: string|null, tautan: string}>
     */
    private static function terakhir(array $kegiatan): array
    {
        usort($kegiatan, fn (array $a, array $b) => strcmp((string) ($b['diperbarui_pada'] ?? $b['dibuat_pada'] ?? ''), (string) ($a['diperbarui_pada'] ?? $a['dibuat_pada'] ?? '')));

        return array_map(fn (array $k) => [
            'id' => (int) $k['id'],
            'nama' => trim((string) ($k['nama_kegiatan'] ?? '')) ?: 'Kegiatan tanpa nama',
            'status' => (string) ($k['status'] ?? 'draf'),
            'leading' => $k['leading'] ?? null,
            'diperbarui_pada' => $k['diperbarui_pada'] ?? $k['dibuat_pada'] ?? null,
            'waktu' => self::waktu($k['diperbarui_pada'] ?? $k['dibuat_pada'] ?? null),
            'tautan' => route('kegiatan.edit', (int) $k['id']),
        ], array_slice($kegiatan, 0, self::BATAS_TERAKHIR));
    }

    /**
     * @param  list<array<string, mixed>>  $kegiatan
     * @param  array<string, mixed>  $pengguna
     * @return list<array{jenis: string, teks: string, nama: string, oleh: string|null, pada: string, waktu: string|null, tautan: string|null, ikon: string}>
     */
    private static function aktivitas(array $kegiatan, array $pengguna): array
    {
        $hasil = [];
        $ids = [];
        foreach ($kegiatan as $k) {
            $ids[(int) $k['id']] = true;
            foreach ($k['riwayat_status'] ?? [] as $r) {
                $hasil[] = ['jenis' => 'status', 'teks' => StatusKegiatan::labelRiwayat((string) $r['aksi']), 'nama' => trim((string) ($k['nama_kegiatan'] ?? '')) ?: 'Kegiatan tanpa nama',
                    'oleh' => $r['oleh'] ?? null, 'pada' => (string) $r['pada'], 'waktu' => self::waktu((string) $r['pada']), 'tautan' => route('kegiatan.edit', (int) $k['id']), 'ikon' => 'history'];
            }
        }
        $tahun = (int) StubData::tahunAktif()['tahun'];
        foreach (RiwayatDokumen::untuk($pengguna) as $d) {
            if ((int) $d['tahun_anggaran'] !== $tahun || ($d['kegiatan_id'] !== null && ! isset($ids[(int) $d['kegiatan_id']]))) {
                continue;
            }
            $hasil[] = ['jenis' => 'dokumen', 'teks' => $d['label_jenis'].' '.$d['label_format'].' '.mb_strtolower($d['label_asal']), 'nama' => $d['nama_kegiatan'],
                'oleh' => $d['dibuat_oleh'] ?? null, 'pada' => (string) $d['dibuat_pada'], 'waktu' => self::waktu((string) $d['dibuat_pada']), 'tautan' => $d['tautan_kegiatan'] ?? null, 'ikon' => 'file-text'];
        }
        usort($hasil, fn (array $a, array $b) => strtotime($b['pada']) <=> strtotime($a['pada']));

        return array_slice($hasil, 0, self::BATAS_AKTIVITAS);
    }

    /** @return list<array{kunci: string, label: string, keterangan: string, ikon: string, tautan: string}> */
    private static function pintasan(string $peran): array
    {
        $daftar = $peran === 'admin'
            ? [['pemeriksaan', 'Pemeriksaan', 'Antrean dokumen yang diajukan', 'check-badge', 'pemeriksaan.index'],
                ['monitoring', 'Monitoring anggaran', 'Pagu DIPA vs Komite & rekap', 'chart', 'monitoring.index'],
                ['akun', 'Leading & User', 'Akun & permohonan pendaftaran', 'users', 'pengaturan.leading.index'],
                ['pengaturan', 'Pengaturan', 'Tahun, madrasah, template, AI', 'settings', 'pengaturan.index']]
            : [['buat', 'Buat kegiatan', 'Mulai KAK/TOR & RAB baru', 'plus', 'kegiatan.create'],
                ['kegiatan', 'Data Kegiatan', 'Semua kegiatan leading Anda', 'clipboard', 'kegiatan.index'],
                ['cetak', 'Cetak & Unduh', 'PDF, Word, dan Excel', 'printer', 'cetak.index'],
                ['dokumen', 'Riwayat Dokumen', 'Dokumen yang pernah dibuat', 'history', 'dokumen.index']];

        return array_values(array_map(
            fn (array $p) => ['kunci' => $p[0], 'label' => $p[1], 'keterangan' => $p[2], 'ikon' => $p[3], 'tautan' => route($p[4])],
            array_filter($daftar, fn (array $p) => Route::has($p[4])),
        ));
    }

    private static function waktu(?string $iso): ?string
    {
        if ($iso === null || $iso === '') {
            return null;
        }
        $waktu = Carbon::parse($iso)->timezone(config('app.timezone'));

        return Format::tanggal($waktu->toDateString()).' · '.$waktu->format('H.i');
    }
}
