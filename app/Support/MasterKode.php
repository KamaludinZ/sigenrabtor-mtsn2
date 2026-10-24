<?php

namespace App\Support;

use App\Contracts\MasterKodeRepository;
use App\Support\Stub\StubData;

/**
 * Akses ringkas master kode & leading untuk layar dan aturan validasi.
 *
 * Master kode dibaca dari tahun anggaran aktif (bisa ditentukan lain lewat $tahun) melalui MasterKodeRepository,
 * sehingga pergantian sumber data (berkas tiruan → hasil unggah Excel) cukup dengan mengganti binding.
 */
class MasterKode
{
    private static function repo(): MasterKodeRepository
    {
        return app(MasterKodeRepository::class);
    }

    private static function tahun(?int $tahun): int
    {
        return $tahun ?? StubData::tahunAktif()['tahun'];
    }

    /** Master kode tahun ini sudah diunggah (minimal ada kode RKTM). */
    public static function tersedia(?int $tahun = null): bool
    {
        return self::repo()->kodeRktm(self::tahun($tahun)) !== [];
    }

    /**
     * @return list<array{kode: string, nama_unit: string, jabatan_penanda_tangan: string}>
     */
    public static function leadings(): array
    {
        return StubData::leadings();
    }

    /**
     * Leading yang boleh dipilih pengguna: admin semua, akun leading hanya leading-nya.
     *
     * @param  array{role: string, leadings: list<string>}  $pengguna
     * @return list<array{kode: string, nama_unit: string, jabatan_penanda_tangan: string}>
     */
    public static function leadingsUntuk(array $pengguna): array
    {
        if ($pengguna['role'] === 'admin') {
            return self::leadings();
        }

        return array_values(array_filter(self::leadings(), fn ($l) => in_array($l['kode'], $pengguna['leadings'], true)));
    }

    /**
     * @return list<array{kode: string, uraian: string}>
     */
    public static function kodeSk(?int $tahun = null): array
    {
        return self::repo()->kodeSk(self::tahun($tahun));
    }

    /**
     * IKSK, opsional hanya yang berada di bawah SK tertentu.
     *
     * @return list<array{kode: string, kode_sk: string, uraian: string}>
     */
    public static function kodeIksk(?string $kodeSk = null, ?int $tahun = null): array
    {
        $semua = self::repo()->kodeIksk(self::tahun($tahun));

        return $kodeSk === null ? $semua : array_values(array_filter($semua, fn ($i) => $i['kode_sk'] === $kodeSk));
    }

    /**
     * @return list<array{kode: string, snp: string, kegiatan: string, sub_kegiatan: string}>
     */
    public static function kodeRktm(?int $tahun = null): array
    {
        return self::repo()->kodeRktm(self::tahun($tahun));
    }

    /**
     * @return list<array{kode: string, komponen: string, uraian: string}>
     */
    public static function kodeEdm(?int $tahun = null): array
    {
        return self::repo()->kodeEdm(self::tahun($tahun));
    }

    /**
     * @return array<string, string>
     */
    public static function komponenEdm(?int $tahun = null): array
    {
        return self::repo()->komponenEdm(self::tahun($tahun));
    }

    /**
     * @return list<array{nomor: int, nama: string}>
     */
    public static function budayaMutu(?int $tahun = null): array
    {
        return self::repo()->budayaMutu(self::tahun($tahun));
    }

    /**
     * Cari satu baris daftar berdasarkan kode.
     *
     * @param  list<array<string, mixed>>  $daftar
     * @return array<string, mixed>|null
     */
    public static function cari(array $daftar, string|int|null $kode, string $kunci = 'kode'): ?array
    {
        foreach ($daftar as $baris) {
            if ((string) $baris[$kunci] === (string) $kode) {
                return $baris;
            }
        }

        return null;
    }
}
