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
     * Paragraf baku Latar Belakang KAK (master per tahun, diatur admin).
     */
    public static function latarBelakangBaku(int $tahun): string
    {
        return 'Madrasah Tsanawiyah Negeri 2 Kota Malang, sebagai lembaga pendidikan Islam, berkomitmen memberikan kontribusi yang besar bagi masyarakat '
            .'sebagai penerus perjuangan dalam rangka syiar agama Islam. Dengan memperhatikan kebutuhan tersebut, MTsN 2 Kota Malang terus berusaha '
            .'memberikan layanan terbaik kepada peserta didik, masyarakat, dan pengguna layanan lainnya sesuai dengan visi madrasah. Peningkatan '
            .'pelayanan dan kualitas pendidikan perlu terus diupayakan melalui pembinaan dan evaluasi yang berkesinambungan, sehingga penyelenggaraan '
            ."pendidikan pada Tahun Anggaran {$tahun} memerlukan kegiatan-kegiatan yang selaras dengan situasi dan kondisi madrasah.";
    }

    /**
     * Dasar hukum KAK (master per tahun, diatur admin).
     *
     * @return list<string>
     */
    public static function dasarHukum(int $tahun): array
    {
        return [
            'Undang-Undang Republik Indonesia Nomor 20 Tahun 2003 tentang Sistem Pendidikan Nasional;',
            'Peraturan Menteri Agama Nomor 10 Tahun 2010 tentang Organisasi dan Tata Kerja Kementerian Agama;',
            'Peraturan Menteri Agama Nomor 90 Tahun 2013 tentang Penyelenggaraan Pendidikan Madrasah;',
            "Surat Keputusan Kepala MTsN 2 Kota Malang tentang Rencana Kerja Tahunan Madrasah (RKTM) MTsN 2 Kota Malang Tahun {$tahun}.",
        ];
    }

    /**
     * Pejabat penanda tangan tahun aktif. Nama & NIP diisi admin di master pejabat; selama data tiruan dibiarkan kosong.
     *
     * @return array{kepala: array{jabatan: string, nama: string|null, nip: string|null}, komite: array{jabatan: string, nama: string|null, nip: string|null}}
     */
    public static function pejabat(): array
    {
        return [
            'kepala' => ['jabatan' => 'Kepala MTsN 2 Kota Malang', 'nama' => null, 'nip' => null],
            'komite' => ['jabatan' => 'Ketua Komite MTsN 2 Kota Malang', 'nama' => null, 'nip' => null],
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
