<?php

namespace App\Support\Dokumen;

use App\Contracts\KegiatanRepository;

/**
 * Isi satu catatan Riwayat Dokumen untuk endpoint `dokumen.isi` (`GET /dokumen/{id}/isi`; PRD §7 "Buka Ulang"). Berkas
 * cetak & unduhan tidak disimpan, jadi isi diambil menurut `SUMBER`, berurutan:
 * 1. `tersimpan` — cuplikan isi yang dicatat bersama berkasnya (`isi` catatan, disusun `cuplikan()`), persis seperti saat
 *    itu; `sama_dengan_terbaru` membandingkan sidiknya dengan susunan sekarang (null bila kegiatan tidak bisa disusun ulang);
 * 2. `arsip` — catatan arsip final: `berkas` = PDF bertanda tangan (rute `kegiatan.arsip`, hanya versi arsip terbaru &
 *    selama OtorisasiDokumen::unduhArsip), isi struktural dari data terbaru sebagai rujukan bila boleh dibuka ulang;
 * 3. `terbaru` — disusun ulang dari data kegiatan sekarang (OtorisasiDokumen::bukaUlang) beserta `perbedaan` dengan catatan;
 * 4. `tidak_ada` — tidak bisa disusun (kegiatan tidak tersimpan/di luar cakupan/sumber dana tidak dipilih) + alasan.
 * Isi = {sidik, cover (nilai kotak teks cover) | null, bagian (KAK/TOR per bagian HasilKakTor: nomor, judul, isi blok, status,
 * kurang) , rab (cuplikan PembuatDokumen::rab) | null} menurut JenisDokumen::ISI jenis catatan. Otorisasi lewat
 * AksiRiwayatDokumen::lihat (OtorisasiDokumen).
 */
final class IsiRiwayatDokumen
{
    public const SUMBER = [
        'tersimpan' => 'Isi saat berkas dibuat',
        'arsip' => 'PDF arsip final bertanda tangan',
        'terbaru' => 'Disusun dari data kegiatan terbaru',
        'tidak_ada' => 'Isi tidak tersedia',
    ];

    /**
     * Cuplikan isi dokumen jenis × sumber dana kegiatan saat ini — untuk disimpan bersama catatan (RiwayatDokumen::entri `isi`).
     *
     * @param  array<string, mixed>  $kegiatan
     * @param  array{id?: int|null, name?: string, role?: string}  $pengguna
     * @return array{sidik: string, cover: array<string, string|null>|null, bagian: list<array<string, mixed>>, rab: array<string, mixed>|null}
     */
    public static function cuplikan(array $kegiatan, string $jenis, ?string $sumber, array $pengguna): array
    {
        $rekam = PembuatDokumen::rekam($kegiatan, $jenis, $sumber, $pengguna + ['name' => '']);

        return ['sidik' => $rekam['sidik_isi'], 'cover' => $rekam['cover'], 'bagian' => $rekam['bagian'], 'rab' => $rekam['rab']];
    }

    /**
     * @param  array{id?: int|null, name?: string, role: string, leadings?: list<string>}  $pengguna
     * @return array<string, mixed>
     */
    public static function untuk(string $id, array $pengguna): array
    {
        $rincian = AksiRiwayatDokumen::lihat($id, $pengguna);
        $c = AksiRiwayatDokumen::temukan($id, $pengguna);
        $kegiatan = $rincian['izin']['buka_ulang'] ? app(KegiatanRepository::class)->temukan((int) $c['kegiatan_id']) : null;
        $terbaru = $kegiatan !== null ? self::cuplikan($kegiatan, $c['jenis'], $c['sumber'], $pengguna) : null;

        [$sumber, $isi, $catatan] = match (true) {
            ! empty($c['isi']) => ['tersimpan', $c['isi'], 'Isi seperti saat berkas v'.$c['versi'].' dibuat.'],
            $c['asal'] === 'arsip' => ['arsip', $terbaru, $rincian['izin']['unduh_arsip']
                ? 'Berkas aslinya PDF arsip final bertanda tangan; isi di bawah disusun dari data terbaru sebagai rujukan.'
                : (string) $rincian['alasan']['unduh_arsip']],
            $terbaru !== null => ['terbaru', $terbaru, 'Berkas v'.$c['versi'].' yang dicetak/diunduh tidak disimpan — isi disusun ulang dari data kegiatan terbaru.'],
            default => ['tidak_ada', null, (string) $rincian['alasan']['buka_ulang']],
        };

        return [
            'dokumen' => DataRiwayatDokumen::baris($c) + ['izin' => $rincian['izin']],
            'sumber_isi' => $sumber,
            'label_sumber' => self::SUMBER[$sumber],
            'catatan' => $catatan,
            'perbedaan' => $sumber === 'tersimpan' ? [] : $rincian['perbedaan'],
            'sama_dengan_terbaru' => $sumber === 'tersimpan' && $terbaru !== null ? ($c['isi']['sidik'] ?? null) === $terbaru['sidik'] : null,
            'berkas' => $sumber === 'arsip' && $rincian['buka']['arsip'] !== null ? ['tautan' => $rincian['buka']['arsip'], 'nama' => $c['nama_berkas'], 'ukuran_teks' => $c['ukuran_teks']] : null,
            'isi' => $isi,
        ];
    }
}
