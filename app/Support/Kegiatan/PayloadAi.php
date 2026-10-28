<?php

namespace App\Support\Kegiatan;

use App\Contracts\PejabatRepository;
use App\Support\Stub\StubData;
use App\Support\Stub\StubPengaturan;

/**
 * Satu-satunya tempat menyiapkan muatan (payload) permintaan AI dari data kegiatan (PRD §9 & N8), sesuai janji di
 * Pengaturan AI "Tidak pernah dikirim: NIP, nama pejabat, pagu dan rincian RAB": bahan umum KonteksAi::teks() (isian
 * kegiatan — sumber dana tanpa pagu — dan catatan konteks), bahan bagian BagianAi::bahan(), teks acuan, dan instruksi
 * semuanya lewat `bersihkan()` — NIP (KonteksAi::redaksi), nama pejabat penanda tangan tahun aktif (Kepala, Ketua
 * Komite, pejabat leading), lalu data pribadi lain (SanitasiPii: surel, rekening, NIK, telepon, nama akun pengguna &
 * panitia kegiatan) diganti penanda — sebelum dirakit menjadi PermintaanSaranKalimat/PermintaanUsulanJadwal.
 * Riwayat saran AI mencatat muatan yang sama.
 */
final class PayloadAi
{
    public const PENANDA_PEJABAT = '[nama pejabat disembunyikan]';

    /** Data yang dikirim ke penyedia AI (tampil di Pengaturan AI & API konfigurasi). */
    public const DIKIRIM = [
        'Nama kegiatan, program RKTM, IKSK, EDM, waktu, tempat, peserta, dan catatan konteks.',
        'Teks bagian yang sedang dibuat atau diperbaiki.',
    ];

    /** Data yang tidak pernah dikirim ke penyedia AI. */
    public const TIDAK_PERNAH = ['NIP', 'nama pejabat', 'pagu dan rincian RAB'];

    /** Bahan umum permintaan AI: isian kegiatan terisi lalu catatan konteks, sudah dibersihkan. */
    public static function bahan(array $kegiatan): string
    {
        return self::bersihkan(KonteksAi::teks($kegiatan), $kegiatan);
    }

    /**
     * Sembunyikan NIP, nama pejabat penanda tangan tahun aktif, dan data pribadi lain (SanitasiPii) dari teks apa pun yang
     * akan dikirim ke AI; $kegiatan menambahkan nama panitia kegiatan itu.
     *
     * @param  array<string, mixed>  $kegiatan
     */
    public static function bersihkan(string $teks, array $kegiatan = []): string
    {
        $teks = KonteksAi::redaksi($teks);

        foreach (self::namaPejabat() as $nama) {
            $teks = str_ireplace($nama, self::PENANDA_PEJABAT, $teks);
        }

        return SanitasiPii::bersihkan($teks, self::namaOrang($kegiatan));
    }

    /**
     * Permintaan saran kalimat satu bagian (KalimatAi::susunSaran).
     *
     * @param  array<string, mixed>  $kegiatan
     */
    public static function saranKalimat(int $kegiatanId, string $bagian, array $kegiatan, string $teks, string $instruksi): PermintaanSaranKalimat
    {
        $bahanBagian = array_column(array_filter(BagianAi::bahan($bagian, $kegiatan), fn (array $b) => $b['nilai'] !== null), 'nilai', 'label');
        $konteks = KetersediaanKonteks::untuk($bagian, $kegiatan);

        return new PermintaanSaranKalimat(
            kegiatanId: $kegiatanId,
            bagian: $bagian,
            teks: self::bersihkan($teks, $kegiatan),
            instruksi: self::bersihkan($instruksi, $kegiatan),
            bahanBagian: array_map(fn (string $nilai) => self::bersihkan($nilai, $kegiatan), $bahanBagian),
            bahan: self::bahan($kegiatan),
            namaKegiatan: (string) ($kegiatan['nama_kegiatan'] ?? ''),
            aspekKurang: $konteks['kurang'],
            arahanKonteks: $konteks['arahan'],
        );
    }

    /**
     * Permintaan usulan jadwal (MatrikJadwal::buatUsulJadwal).
     *
     * @param  array<string, mixed>  $kegiatan
     * @param  array{persiapan: int, pelaksanaan: list<int>, laporan: int}|null  $acuan
     */
    public static function usulanJadwal(int $kegiatanId, array $kegiatan, string $jenis, string $instruksi, ?array $acuan): PermintaanUsulanJadwal
    {
        return new PermintaanUsulanJadwal(
            kegiatanId: $kegiatanId,
            jenis: $jenis,
            instruksi: self::bersihkan($instruksi, $kegiatan),
            acuan: $acuan,
            bahan: self::bahan($kegiatan),
            namaKegiatan: (string) ($kegiatan['nama_kegiatan'] ?? ''),
        );
    }

    /**
     * Nama orang yang dikenal aplikasi selain pejabat: akun pengguna dan susunan panitia kegiatan.
     *
     * @param  array<string, mixed>  $kegiatan
     * @return list<string>
     */
    private static function namaOrang(array $kegiatan): array
    {
        return array_values(array_filter([
            ...array_map(fn (array $akun) => (string) ($akun['name'] ?? ''), StubPengaturan::pengguna()),
            ...array_map(fn ($anggota) => is_array($anggota) ? (string) ($anggota['nama'] ?? '') : '', (array) ($kegiatan['panitia'] ?? [])),
        ]));
    }

    /**
     * Nama pejabat penanda tangan tahun aktif yang sudah diisi, terpanjang dulu agar nama bergelar lengkap diganti utuh.
     *
     * @return list<string>
     */
    private static function namaPejabat(): array
    {
        $pejabat = app(PejabatRepository::class)->untukTahun(StubData::tahunAktif()['tahun']);
        $nama = array_filter(array_map(
            fn (array $p) => trim((string) ($p['nama'] ?? '')),
            [$pejabat['kepala'], $pejabat['komite'], ...array_values($pejabat['leading'])],
        ), fn (string $n) => mb_strlen($n) >= 3);
        usort($nama, fn (string $a, string $b) => mb_strlen($b) <=> mb_strlen($a));

        return array_values(array_unique($nama));
    }
}
