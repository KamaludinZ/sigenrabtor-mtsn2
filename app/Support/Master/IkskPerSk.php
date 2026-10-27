<?php

namespace App\Support\Master;

use App\Support\MasterKode;

/**
 * Aturan IKSK berdasarkan SK (PRD F7: IKSK terfilter per SK) — satu-satunya tempat pasangan SK–IKSK ditentukan dari
 * master tahun anggaran: pilihan IKSK untuk SK terpilih, SK induk IKSK, pesan bila pasangannya tidak sesuai, dan IKSK
 * sesudah SK diganti (dikosongkan bila bukan indikator SK baru; satu-satunya indikator dipilih otomatis). Dipakai
 * Data Kegiatan & Kode & Klasifikasi (KegiatanForm), ValidasiKegiatan, impor cascade Perkin, dan endpoint master.kode.
 */
final class IkskPerSk
{
    /**
     * IKSK di bawah SK (urutan master); SK kosong → tidak ada pilihan.
     *
     * @return list<array{kode: string, kode_sk: string, uraian: string}>
     */
    public static function pilihan(?string $kodeSk, ?int $tahun = null): array
    {
        return blank($kodeSk) ? [] : MasterKode::kodeIksk($kodeSk, $tahun);
    }

    /** SK induk IKSK di master tahun itu; null bila IKSK kosong atau tidak ada di master. */
    public static function induk(?string $kodeIksk, ?int $tahun = null): ?string
    {
        return blank($kodeIksk) ? null : (MasterKode::cari(MasterKode::kodeIksk(null, $tahun), $kodeIksk)['kode_sk'] ?? null);
    }

    /**
     * Pesan bila pasangan SK–IKSK tidak sesuai; null bila sesuai, IKSK kosong, atau IKSK tidak ada di master (kode di luar
     * master ditangani aturan pilihan master tersendiri).
     */
    public static function galat(?string $kodeSk, ?string $kodeIksk, ?int $tahun = null): ?string
    {
        if (blank($kodeIksk)) {
            return null;
        }
        if (blank($kodeSk)) {
            return 'Pilih kode SK terlebih dahulu.';
        }

        $induk = self::induk($kodeIksk, $tahun);

        return $induk !== null && $induk !== $kodeSk ? "{$kodeIksk} bukan indikator dari {$kodeSk}." : null;
    }

    /**
     * IKSK sesudah SK diganti: tetap bila masih indikator SK baru, kosong bila tidak; bila kosong dan SK baru hanya punya
     * satu indikator, indikator itu dipilih (`otomatis`).
     *
     * @return array{iksk: string, otomatis: bool}
     */
    public static function sesudahGantiSk(string $kodeSk, string $kodeIksk, ?int $tahun = null): array
    {
        if ($kodeIksk !== '' && self::induk($kodeIksk, $tahun) === $kodeSk) {
            return ['iksk' => $kodeIksk, 'otomatis' => false];
        }

        $pilihan = self::pilihan($kodeSk, $tahun);

        return count($pilihan) === 1 ? ['iksk' => $pilihan[0]['kode'], 'otomatis' => true] : ['iksk' => '', 'otomatis' => false];
    }
}
