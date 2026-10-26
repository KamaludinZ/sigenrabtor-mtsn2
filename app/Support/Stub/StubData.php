<?php

namespace App\Support\Stub;

use App\Contracts\TahunAnggaranRepository;

/**
 * Data tiruan untuk tahap frontend.
 *
 * Seluruh layar dibangun di atas data ini lebih dulu (frontend-first); task backend
 * akan menggantinya dengan query Eloquent tanpa mengubah bentuk array yang dipakai view.
 */
class StubData
{
    /**
     * Tahun anggaran yang sedang aktif (dipilih admin di Pengaturan › Tahun Anggaran). Selama layar "belum ada tahun
     * aktif" belum dibuat, 2026 dipakai sebagai cadangan.
     *
     * @return array{id: int, tahun: int, is_locked: bool}
     */
    public static function tahunAktif(): array
    {
        $aktif = app(TahunAnggaranRepository::class)->aktif();

        return $aktif === null
            ? ['id' => 1, 'tahun' => 2026, 'is_locked' => false]
            : ['id' => $aktif['id'], 'tahun' => $aktif['tahun'], 'is_locked' => $aktif['is_locked']];
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
     * Pejabat tiap leading tahun aktif (peran `leading` di pejabat_penanda_tangan): jabatan dari master leading; nama & NIP
     * diisi admin — selama data tiruan kosong (dokumen sementara memakai nama akun leading pembuat kegiatan).
     *
     * @return array<string, array{jabatan: string, nama: string|null, nip: string|null}>
     */
    public static function pejabatLeading(): array
    {
        return array_map(fn (array $l) => ['jabatan' => $l['jabatan_penanda_tangan'], 'nama' => null, 'nip' => null], array_column(self::leadings(), null, 'kode'));
    }

    /**
     * Saran baku tempat & jenis peserta bagian Pelaksanaan (fasilitas dan warga madrasah). Isian tetap bebas;
     * backend nanti membacanya dari pengaturan madrasah.
     *
     * @return array{tempat: list<string>, peserta: list<string>}
     */
    public static function saranPelaksanaan(): array
    {
        return [
            'tempat' => [
                'Aula Madrasah', 'Lingkungan Madrasah', 'Ruang Kelas', 'Ruang Rapat Madrasah', 'Laboratorium IPA',
                'Laboratorium Komputer', 'Masjid Madrasah', 'Perpustakaan Madrasah', 'Lapangan Madrasah',
            ],
            'peserta' => [
                'Guru', 'Siswa', 'Guru dan tenaga kependidikan', 'Tenaga kependidikan', 'Siswa kelas VII', 'Siswa kelas VIII',
                'Siswa kelas IX', 'Orang tua/wali siswa', 'Pengurus komite',
            ],
        ];
    }

    /**
     * Contoh catatan konteks per jenis kegiatan (panduan pengisian; nanti diatur admin di Pengaturan AI).
     * Nama orang sengaja tidak dipakai — catatan konteks tidak boleh memuat nama/NIP pejabat.
     *
     * @return list<array{kunci: string, judul: string, kata: list<string>, teks: string}>
     */
    public static function contohKonteks(): array
    {
        return [
            ['kunci' => 'workshop', 'judul' => 'Workshop / pelatihan guru', 'kata' => ['workshop', 'pelatihan', 'bimtek', 'diklat', 'penyusunan', 'in house'],
                'teks' => 'Latar khusus: lanjutan workshop tahun lalu; hasil EDM menunjukkan perangkat ajar belum seragam.
Peserta: 49 guru dan 6 tenaga kependidikan.
Narasumber: pengawas madrasah dan satu praktisi dari MGMP kota.
Output: dokumen KTSP dan modul ajar tiap mata pelajaran yang disahkan kepala madrasah.', ],
            ['kunci' => 'supervisi', 'judul' => 'Supervisi / evaluasi / rapat kerja', 'kata' => ['supervisi', 'evaluasi', 'rapat', 'raker', 'monitoring', 'refleksi', 'audit'],
                'teks' => 'Latar khusus: tindak lanjut temuan supervisi semester ganjil.
Peserta: seluruh guru mata pelajaran, dibagi per rumpun.
Narasumber: tim supervisor internal (TPM) dan pengawas madrasah.
Output: laporan hasil supervisi, rencana tindak lanjut per guru, dan rekap praktik baik.', ],
            ['kunci' => 'lomba', 'judul' => 'Lomba / kompetisi siswa', 'kata' => ['lomba', 'olimpiade', 'kompetisi', 'festival', 'porseni', 'pentas'],
                'teks' => 'Latar khusus: seleksi wakil madrasah untuk kompetisi tingkat kota bulan berikutnya.
Peserta: siswa kelas VII–IX, sekitar 120 orang, didampingi wali kelas.
Narasumber: juri dari guru mapel dan alumni berprestasi.
Output: daftar juara, tim inti untuk kompetisi kota, dan dokumentasi kegiatan.', ],
            ['kunci' => 'siswa', 'judul' => 'Kegiatan kesiswaan / keagamaan', 'kata' => ['mpls', 'matsama', 'pesantren kilat', 'outbound', 'kemah', 'peringatan', 'phbi', 'kajian'],
                'teks' => 'Latar khusus: program rutin awal tahun untuk siswa baru.
Peserta: 320 siswa kelas VII dan 40 pengurus OSIM sebagai pendamping.
Narasumber: guru BK, pembina OSIM, dan penyuluh agama dari KUA.
Output: siswa mengenal lingkungan dan tata tertib madrasah; laporan kegiatan dan evaluasi peserta.', ],
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
