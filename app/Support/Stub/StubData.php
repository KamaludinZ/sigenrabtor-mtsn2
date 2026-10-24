<?php

namespace App\Support\Stub;

/**
 * Data tiruan untuk tahap frontend.
 *
 * Seluruh layar dibangun di atas data ini lebih dulu (frontend-first); task backend
 * akan menggantinya dengan query Eloquent tanpa mengubah bentuk array yang dipakai view.
 */
class StubData
{
    /**
     * Tahun anggaran yang sedang aktif.
     *
     * @return array{id: int, tahun: int, is_locked: bool}
     */
    public static function tahunAktif(): array
    {
        return ['id' => 1, 'tahun' => 2026, 'is_locked' => false];
    }

    /**
     * Pengguna yang sedang masuk.
     *
     * @return array{id: int, name: string, role: string, jabatan: string, leadings: list<string>}
     */
    public static function currentUser(): array
    {
        if (session('stub_role') === 'admin') {
            return [
                'id' => 2,
                'name' => 'Koordinator PUSKOM',
                'role' => 'admin',
                'jabatan' => 'Admin Aplikasi',
                'leadings' => [],
            ];
        }

        return [
            'id' => 1,
            'name' => 'Muslimin, M.Pd.',
            'role' => 'leading',
            'jabatan' => 'Tim Penjamin Mutu',
            'leadings' => ['1.6'],
        ];
    }

    /**
     * Unit penerima mandat (leading) beserta jabatan penanda tangannya (PRD F30, diatur admin).
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
     * Profil madrasah untuk kop dan identitas aplikasi.
     *
     * @return array{nama: string, nama_singkat: string, kota: string}
     */
    public static function madrasah(): array
    {
        return [
            'nama' => 'Madrasah Tsanawiyah Negeri 2 Kota Malang',
            'nama_singkat' => 'MTsN 2 Kota Malang',
            'kota' => 'Malang',
        ];
    }
}
