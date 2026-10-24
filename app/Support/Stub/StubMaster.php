<?php

namespace App\Support\Stub;

/**
 * Master kode & leading tiruan untuk dropdown tahap frontend (PRD F7: kode dipilih dari master, tidak diketik bebas).
 *
 * Master kode (SK, IKSK, RKTM, EDM) berlaku per tahun anggaran dan isinya berubah tiap tahun; di sini dibaca dari
 * data/master-{tahun}.php milik tahun anggaran aktif. Bila belum ada, daftarnya kosong (master belum diunggah).
 * Fitur Master Kode menggantikannya dengan hasil unggah Excel per tahun.
 */
class StubMaster
{
    /** @var array<int, array<string, list<array<string, string>>>> */
    private static array $cache = [];

    /**
     * @return array<string, list<array<string, string>>>
     */
    private static function data(?int $tahun = null): array
    {
        $tahun ??= StubData::tahunAktif()['tahun'];
        $berkas = __DIR__."/data/master-{$tahun}.php";

        return self::$cache[$tahun] ??= is_file($berkas)
            ? require $berkas
            : ['kode_sk' => [], 'kode_iksk' => [], 'kode_rktm' => [], 'kode_edm' => []];
    }

    /**
     * Apakah master kode tahun anggaran aktif sudah tersedia (sudah diunggah).
     */
    public static function tersedia(?int $tahun = null): bool
    {
        return self::data($tahun)['kode_rktm'] !== [];
    }

    /**
     * Unit penerima mandat (leading) beserta jabatan penanda tangannya.
     *
     * @return list<array{kode: string, nama_unit: string, jabatan_penanda_tangan: string}>
     */
    public static function leadings(): array
    {
        return [
            ['kode' => '1.1', 'nama_unit' => 'Waka Kurikulum', 'jabatan_penanda_tangan' => 'Wakil Kepala Bidang Kurikulum'],
            ['kode' => '1.2', 'nama_unit' => 'Waka Kesiswaan', 'jabatan_penanda_tangan' => 'Wakil Kepala Bidang Kesiswaan'],
            ['kode' => '1.3', 'nama_unit' => 'Waka Sarpras', 'jabatan_penanda_tangan' => 'Wakil Kepala Bidang Sarana dan Prasarana'],
            ['kode' => '1.4', 'nama_unit' => 'Waka Humas', 'jabatan_penanda_tangan' => 'Wakil Kepala Bidang Hubungan Masyarakat'],
            ['kode' => '1.5', 'nama_unit' => 'Ka. TU', 'jabatan_penanda_tangan' => 'Kepala Tata Usaha'],
            ['kode' => '1.6', 'nama_unit' => 'Tim Penjamin Mutu', 'jabatan_penanda_tangan' => 'Ketua Tim Penjamin Mutu'],
            ['kode' => '1.7', 'nama_unit' => 'Mahad', 'jabatan_penanda_tangan' => 'Pengasuh Mahad'],
            ['kode' => '1.8', 'nama_unit' => 'PMM', 'jabatan_penanda_tangan' => 'Koordinator PMM'],
        ];
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
    public static function kodeSk(): array
    {
        return self::data()['kode_sk'];
    }

    /**
     * IKSK, opsional hanya yang berada di bawah SK tertentu.
     *
     * @return list<array{kode: string, kode_sk: string, uraian: string}>
     */
    public static function kodeIksk(?string $kodeSk = null): array
    {
        $semua = self::data()['kode_iksk'];

        return $kodeSk === null ? $semua : array_values(array_filter($semua, fn ($i) => $i['kode_sk'] === $kodeSk));
    }

    /**
     * @return list<array{kode: string, snp: string, kegiatan: string, sub_kegiatan: string}>
     */
    public static function kodeRktm(): array
    {
        return self::data()['kode_rktm'];
    }

    /**
     * @return list<array{kode: string, uraian: string}>
     */
    public static function kodeEdm(): array
    {
        return self::data()['kode_edm'];
    }

    /**
     * 5 Budaya Mutu (Nilai Budaya Kerja Kementerian Agama).
     *
     * @return list<array{nomor: int, nama: string}>
     */
    public static function budayaMutu(): array
    {
        return [
            ['nomor' => 1, 'nama' => 'Integritas'],
            ['nomor' => 2, 'nama' => 'Profesionalitas'],
            ['nomor' => 3, 'nama' => 'Inovasi'],
            ['nomor' => 4, 'nama' => 'Tanggung Jawab'],
            ['nomor' => 5, 'nama' => 'Keteladanan'],
        ];
    }

    /**
     * Cari satu baris master berdasarkan kode.
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
