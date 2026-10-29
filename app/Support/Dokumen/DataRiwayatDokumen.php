<?php

namespace App\Support\Dokumen;

use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Livewire\Dokumen\Riwayat;
use App\Support\MasterKode;

/**
 * Data daftar Riwayat Dokumen untuk endpoint `dokumen.data` (`GET /dokumen/data`, semua pengguna; PRD §7 "Daftar Dokumen",
 * "Cari & Saring") — jalur yang sama dengan halaman `Dokumen\Riwayat`: catatan dalam cakupan akun (RiwayatDokumen::untuk)
 * ditelusuri PenelusuranRiwayat (kata kunci `cari` + saringan RiwayatDokumen::SARINGAN). Parameter `PARAMETER`, semuanya
 * dirapikan (nilai tak sah = diabaikan, bukan galat): `tampilan` kronologis (bawaan, daftar dokumen) | kegiatan (per
 * kegiatan, versi terbaru + lama + `lebih_baru`), `urut` terbaru|terlama (kronologis), `hal` ≥ 1 (dijepit ke halaman
 * terakhir), `per` ∈ `PER_HALAMAN` (bawaan Riwayat::PER_HALAMAN). Jawaban: saringan rapi, label chip, keterangan, total &
 * jumlah cocok, ringkasan (+ seluruh catatan), pilihan saringan berhitungan {nilai, label, jumlah}, halaman, butir
 * (`dokumen` atau `kegiatan`, baris `baris()` + `izin` OtorisasiDokumen per catatan — lihat/buka_ulang/unduh_arsip/
 * duplikat), dan `saran_longgar` bila tanpa hasil.
 */
final class DataRiwayatDokumen
{
    public const PARAMETER = [...PenelusuranRiwayat::KRITERIA, 'tampilan', 'urut', 'hal', 'per'];

    public const PER_HALAMAN = [10, 20, 50, 100];

    /**
     * @param  array{id?: int|null, role: string, leadings?: list<string>}  $pengguna
     * @param  array<string, mixed>  $nilai
     * @return array<string, mixed>
     */
    public static function untuk(array $pengguna, array $nilai): array
    {
        $tampilan = in_array($nilai['tampilan'] ?? null, array_keys(Riwayat::TAMPILAN), true) ? (string) $nilai['tampilan'] : 'kronologis';
        $urut = isset(RiwayatDokumen::URUT[$nilai['urut'] ?? '']) ? (string) $nilai['urut'] : 'terbaru';
        $per = in_array((int) ($nilai['per'] ?? 0), self::PER_HALAMAN, true) ? (int) $nilai['per'] : Riwayat::PER_HALAMAN;

        $catatan = RiwayatDokumen::untuk($pengguna);
        $telusur = PenelusuranRiwayat::dari($catatan, $nilai);
        $izin = self::penilaiIzin($catatan, $pengguna);
        $butir = $tampilan === 'kegiatan'
            ? array_map(fn (array $k) => self::kegiatan($k, $izin), $telusur->perKegiatan())
            : array_map(fn (array $c) => self::baris($c) + ['izin' => $izin($c)], $urut === 'terlama' ? array_reverse($telusur->cocok) : $telusur->cocok);
        $jumlahButir = count($butir);
        $halamanAkhir = max(1, (int) ceil($jumlahButir / $per));
        $hal = min(max(1, (int) ($nilai['hal'] ?? 1)), $halamanAkhir);

        return [
            'saringan' => $telusur->kriteria + ['tampilan' => $tampilan, 'urut' => $urut, 'hal' => $hal, 'per' => $per],
            'label' => $telusur->label(),
            'keterangan' => $telusur->keterangan(),
            'aktif' => $telusur->aktif(),
            'total' => $telusur->total,
            'jumlah' => count($telusur->cocok),
            'ringkasan' => $telusur->ringkasan(),
            'pilihan' => self::pilihan($telusur),
            'halaman' => ['ke' => $hal, 'dari' => $halamanAkhir, 'per' => $per, 'jumlah' => $jumlahButir],
            'tampilan' => $tampilan,
            $tampilan === 'kegiatan' ? 'kegiatan' : 'dokumen' => array_slice($butir, ($hal - 1) * $per, $per),
            'saran_longgar' => $telusur->cocok === [] && $telusur->total > 0 ? $telusur->saranLonggar() : [],
        ];
    }

    /**
     * Satu catatan sebagai baris JSON.
     *
     * @param  array<string, mixed>  $c  Catatan RiwayatDokumen::untuk.
     * @return array<string, mixed>
     */
    public static function baris(array $c): array
    {
        return array_intersect_key($c, array_flip([
            'id', 'kegiatan_id', 'nama_kegiatan', 'tahun_anggaran', 'kode_leading', 'nama_leading', 'jenis', 'label_jenis', 'sumber',
            'format', 'label_format', 'asal', 'label_asal', 'nama_berkas', 'ukuran', 'ukuran_teks', 'status', 'label_status',
            'bertanda_draf', 'nomor_dokumen', 'versi', 'ringkas', 'dibuat_oleh', 'dibuat_pada', 'tersedia', 'tautan_kegiatan',
        ]));
    }

    /**
     * Penilai izin per catatan (OtorisasiDokumen::izin): kegiatan & versi terbaru tiap kunci versi dibaca sekali.
     *
     * @param  list<array<string, mixed>>  $catatan
     * @return \Closure(array<string, mixed>): array<string, bool>
     */
    private static function penilaiIzin(array $catatan, array $pengguna): \Closure
    {
        $kegiatan = array_column(app(KegiatanRepository::class)->semua(), null, 'id');
        $terbaru = [];
        foreach ($catatan as $c) {
            $kunci = RiwayatDokumen::kunciVersi($c);
            if (! isset($terbaru[$kunci]) || $c['dibuat_pada'] > $terbaru[$kunci]['dibuat_pada']) {
                $terbaru[$kunci] = $c;
            }
        }
        $aktif = OtorisasiDokumen::akunAktif($pengguna);

        return fn (array $c) => OtorisasiDokumen::izin($c, $c['tersedia'] ? ($kegiatan[$c['kegiatan_id']] ?? null) : null, $pengguna, ($terbaru[RiwayatDokumen::kunciVersi($c)]['id'] ?? null) === $c['id'], $aktif)['izin'];
    }

    /**
     * @param  array<string, mixed>  $k  Kegiatan PenelusuranRiwayat::perKegiatan.
     * @param  \Closure(array<string, mixed>): array<string, bool>  $izin
     * @return array<string, mixed>
     */
    private static function kegiatan(array $k, \Closure $izin): array
    {
        $baris = fn (array $c) => self::baris($c) + ['izin' => $izin($c)];

        return array_diff_key($k, ['dokumen' => true]) + ['dokumen' => array_map(fn (array $d) => [
            'kunci' => $d['kunci'],
            'terbaru' => $baris($d['terbaru']),
            'lama' => array_map($baris, $d['lama']),
            'lebih_baru' => $d['lebih_baru'] !== null ? $baris($d['lebih_baru']) : null,
        ], $k['dokumen'])];
    }

    /** @return array<string, list<array{nilai: string, label: string, jumlah: int}>> */
    private static function pilihan(PenelusuranRiwayat $telusur): array
    {
        $label = [
            'jenis' => fn (string $v) => DokumenKegiatan::JENIS[$v] ?? $v,
            'format' => fn (string $v) => RiwayatDokumen::FORMAT[$v][0] ?? $v,
            'leading' => fn (string $v) => trim($v.' · '.(MasterKode::leading($v)['nama_unit'] ?? ''), ' ·'),
            'sumber' => fn (string $v) => $v,
            'status' => fn (string $v) => StatusKegiatan::from($v)->label(),
        ];
        $hasil = [];
        foreach (PenelusuranRiwayat::PILIHAN as $kunci) {
            $hasil[$kunci] = array_map(fn (string $v, int $n) => ['nilai' => $v, 'label' => $label[$kunci]($v), 'jumlah' => $n], array_map('strval', array_keys($telusur->pilihan($kunci))), array_values($telusur->pilihan($kunci)));
        }

        return $hasil;
    }
}
