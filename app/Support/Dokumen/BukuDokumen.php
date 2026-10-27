<?php

namespace App\Support\Dokumen;

use App\Contracts\DaftarIsiRepository;
use App\Contracts\NomorHalamanRepository;
use App\Enums\StatusKegiatan;
use App\Enums\SumberDana;
use App\Support\Kegiatan\TahunKegiatan;
use App\Support\MasterKode;
use App\Support\Pengaturan\PenandaTangan;
use App\Support\Rab\AnggaranKegiatan;
use App\Support\Stub\StubData;

/**
 * Buku dokumen per leading × sumber dana (PRD §18, F19; calon tabel `buku_dokumen`): cover buku, daftar kegiatan
 * (DaftarIsi, urut kode RKTM, nomor halaman dari simulasi NomorHalaman), lalu KAK/TOR & RAB tiap kegiatan tahun aktif
 * yang dibiayai sumber dana itu. Peta buku menurut cakupan akun: admin semua leading, akun leading leading-nya saja.
 */
final class BukuDokumen
{
    /**
     * Leading yang bukunya boleh disusun akun ini (aktif & nonaktif yang masih punya kegiatan tahun ini).
     *
     * @param  array{role: string, leadings: list<string>}  $pengguna
     * @param  list<array<string, mixed>>  $kegiatan  Kegiatan tahun aktif.
     * @return list<array{kode: string, nama_unit: string, is_active: bool}>
     */
    public static function leadingUntuk(array $pengguna, array $kegiatan): array
    {
        $dipakai = array_unique(array_filter(array_column($kegiatan, 'leading')));

        return array_values(array_filter(
            MasterKode::semuaLeading(),
            fn (array $l) => ($pengguna['role'] === 'admin' || in_array($l['kode'], $pengguna['leadings'], true))
                && ($l['is_active'] || in_array($l['kode'], $dipakai, true)),
        ));
    }

    /**
     * Peta buku: tiap leading × sumber dana dengan jumlah kegiatan, total RAB, dan jumlah per status.
     *
     * @param  list<array{kode: string, nama_unit: string, is_active: bool}>  $leadings
     * @param  list<array<string, mixed>>  $kegiatan  Kegiatan tahun aktif.
     * @return list<array{leading: string, nama_leading: string, sumber: string, jumlah: int, total: int, status: array<string, int>}>
     */
    public static function peta(array $leadings, array $kegiatan): array
    {
        $peta = [];
        foreach ($leadings as $l) {
            foreach (SumberDana::cases() as $sumber) {
                $isi = array_values(array_filter($kegiatan, fn (array $k) => ($k['leading'] ?? null) === $l['kode'] && isset(AnggaranKegiatan::perSumber($k)[$sumber->value])));
                $peta[] = [
                    'leading' => $l['kode'],
                    'nama_leading' => $l['nama_unit'],
                    'sumber' => $sumber->value,
                    'jumlah' => count($isi),
                    'total' => array_sum(array_map(fn (array $k) => AnggaranKegiatan::perSumber($k)[$sumber->value]->total(), $isi)),
                    'status' => array_merge(array_fill_keys(array_column(StatusKegiatan::cases(), 'value'), 0), array_count_values(array_map(fn (array $k) => $k['status'] ?? 'draf', $isi))),
                ];
            }
        }

        return $peta;
    }

    /**
     * Isi satu buku: konfigurasi daftar isi (khusus buku ini atau bawaan), baris kegiatan berurutan beserta status, cap
     * DRAF, dan halaman awalnya, serta simulasi nomor halaman.
     *
     * @param  list<array<string, mixed>>  $kegiatan  Kegiatan tahun aktif.
     * @return array{daftar_isi: array<string, mixed>, baris: list<array<string, mixed>>, simulasi: array<string, mixed>, total: int}
     */
    public static function isi(array $kegiatan, string $leading, string $sumber): array
    {
        $tahun = StubData::tahunAktif()['tahun'];
        $repo = app(DaftarIsiRepository::class);
        $konfigurasi = DaftarIsi::rapikan($repo->khusus($tahun)["{$leading}|{$sumber}"] ?? $repo->bawaan($tahun));
        $perId = array_column($kegiatan, null, 'id');

        $baris = DaftarIsi::baris($kegiatan, $leading, $sumber, $konfigurasi['urutan']);
        $simulasi = NomorHalaman::simulasi(NomorHalaman::rapikan(app(NomorHalamanRepository::class)->untuk($tahun)), array_column($baris, 'nama_kegiatan'));

        $baris = array_map(function (array $b, int $i) use ($perId, $sumber, $simulasi) {
            $data = $perId[$b['id']];
            $status = StatusKegiatan::tryFrom($data['status'] ?? '') ?? StatusKegiatan::Draf;

            return $b + [
                'status' => $status,
                'draf' => SusunanKak::dari($data, $sumber)->draf || SusunanRab::dari($data, $sumber)->draf,
                'halaman' => $simulasi['mulai'][$i] ?? null,
            ];
        }, $baris, array_keys($baris));

        return ['daftar_isi' => $konfigurasi, 'baris' => $baris, 'simulasi' => $simulasi, 'total' => array_sum(array_column($baris, 'total'))];
    }

    /**
     * Isi cover buku (PRD §7A.1): "Kegiatan", sumber anggaran, nama leading, tahun, "Disusun oleh" pejabat leading, kop, kota.
     *
     * @return array<string, string>
     */
    public static function cover(string $leading, string $sumber): array
    {
        $tahun = StubData::tahunAktif()['tahun'];
        $madrasah = StubData::madrasah();
        $unit = MasterKode::leading($leading);
        $pembuat = PenandaTangan::pembuat(['leading' => $leading]);

        return [
            'kop' => mb_strtoupper($madrasah['nama']),
            'judul' => 'KEGIATAN',
            'sumber' => 'SUMBER ANGGARAN '.mb_strtoupper($sumber),
            'leading' => mb_strtoupper($unit['nama_unit'] ?? $leading),
            'disusun' => 'Disusun oleh: '.($pembuat['jabatan'] ?? ($unit['jabatan_penanda_tangan'] ?? 'Pejabat leading')),
            'kota' => mb_strtoupper($madrasah['kota']),
            'tahun' => "TAHUN ANGGARAN {$tahun}",
        ];
    }

    /** Nama berkas buku, mis. "TOR_RAB_PMM_DIPA_2026.pdf" (PRD §7). */
    public static function namaBerkas(string $leading, string $sumber): string
    {
        $unit = MasterKode::leading($leading)['nama_unit'] ?? $leading;
        $singkat = preg_replace('/[^A-Za-z0-9]+/', '_', trim($unit));

        return 'TOR_RAB_'.trim($singkat, '_').'_'.mb_strtoupper($sumber).'_'.StubData::tahunAktif()['tahun'].'.pdf';
    }

    /**
     * Kegiatan tahun aktif.
     *
     * @param  list<array<string, mixed>>  $kegiatan
     * @return list<array<string, mixed>>
     */
    public static function tahunAktif(array $kegiatan): array
    {
        $tahun = StubData::tahunAktif()['tahun'];

        return array_values(array_filter($kegiatan, fn (array $k) => TahunKegiatan::dari($k) === $tahun));
    }
}
