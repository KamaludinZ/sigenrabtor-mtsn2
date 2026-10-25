<?php

namespace App\Support\Stub;

use App\Contracts\KegiatanRepository;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Penyimpanan kegiatan tiruan di berkas JSON (disk "local") untuk tahap frontend.
 *
 * Saat berkas belum ada, contoh kegiatan disiapkan agar alur "muat draf" dan RAB per sumber dana langsung bisa dicoba.
 */
class StubKegiatanRepository implements KegiatanRepository
{
    public function simpan(array $data, ?int $id = null): int
    {
        $semua = $this->baca();

        if ($id === null || ! isset($semua[$id])) {
            $id = $semua === [] ? 1 : max(array_keys($semua)) + 1;
            $data['dibuat_pada'] = now()->toIso8601String();
        } else {
            $data['dibuat_pada'] = $semua[$id]['dibuat_pada'] ?? now()->toIso8601String();
        }

        $semua[$id] = ['id' => $id, 'status' => $data['status'] ?? 'draf', 'diperbarui_pada' => now()->toIso8601String()] + $data;
        $this->tulis($semua);

        return $id;
    }

    public function temukan(int $id): ?array
    {
        return $this->baca()[$id] ?? null;
    }

    public function terbaru(int $batas = 10): array
    {
        return array_map(fn (array $k) => [
            'id' => $k['id'],
            'nama_kegiatan' => $k['nama_kegiatan'] ?? '',
            'status' => $k['status'] ?? 'draf',
            'diperbarui_pada' => $k['diperbarui_pada'],
        ], array_slice($this->semua(), 0, $batas));
    }

    public function semua(): array
    {
        $semua = array_values($this->baca());
        usort($semua, fn ($a, $b) => [$b['diperbarui_pada'] ?? '', $b['id']] <=> [$a['diperbarui_pada'] ?? '', $a['id']]);

        return $semua;
    }

    public function riwayatIsian(string $kolom, int $batas = 20): array
    {
        if (! in_array($kolom, self::KOLOM_RIWAYAT, true)) {
            throw new InvalidArgumentException("Riwayat isian kolom [{$kolom}] tidak tersedia.");
        }

        $nilai = [];
        foreach ($this->semua() as $kegiatan) {
            $isi = is_string($kegiatan[$kolom] ?? null) ? trim($kegiatan[$kolom]) : '';
            $nilai[mb_strtolower($isi)] ??= $isi;
        }
        unset($nilai['']);

        return array_slice(array_values($nilai), 0, max(0, $batas));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function baca(): array
    {
        if (! $this->disk()->exists($this->berkas())) {
            $this->tulis(self::contohSemua());
        }

        $isi = json_decode((string) $this->disk()->get($this->berkas()), true);

        return is_array($isi) ? array_column($isi, null, 'id') : [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $semua
     */
    private function tulis(array $semua): void
    {
        $this->disk()->put($this->berkas(), json_encode(array_values($semua), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function berkas(): string
    {
        return config('sigenrabtor.stub.kegiatan_file');
    }

    private function disk(): Filesystem
    {
        return Storage::disk('local');
    }

    /**
     * Semua contoh kegiatan, berkunci id.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function contohSemua(): array
    {
        return [1 => self::contoh(), 2 => self::contohDuaSumber(), 3 => self::contohMelebihiPagu()];
    }

    /**
     * Contoh Batas Pagu (PRD F12): RAB Komite melebihi pagu, pagu DIPA belum diisi seperti draf dari Cascade Perkin.
     *
     * @return array<string, mixed>
     */
    public static function contohMelebihiPagu(): array
    {
        $tahun = StubData::tahunAktif()['tahun'];

        return [
            'id' => 3,
            'status' => 'draf',
            'dibuat_pada' => "{$tahun}-01-08T13:00:00+07:00",
            'diperbarui_pada' => "{$tahun}-01-08T13:00:00+07:00",
            'nama_kegiatan' => 'Review RKJM dan Penyusunan RKTM',
            'nama_kegiatan_cover' => 'REVIEW RKJM DAN PENYUSUNAN RKTM',
            'cover_manual' => false,
            'latar_belakang' => '',
            'tujuan' => 'meninjau capaian RKJM dan menyusun RKTM tahun berikutnya berdasarkan hasil evaluasi diri madrasah.',
            'sasaran' => 'dokumen RKTM yang disepakati warga madrasah dan komite madrasah.',
            'tanggal_dokumen' => "{$tahun}-01-08",
            'tanggal_laporan' => "{$tahun}-11-27",
            'laporan_otomatis' => true,
            'tanggal_mulai' => "{$tahun}-11-16",
            'lama_hari' => 2,
            'hari_kerja' => true,
            'bulan_pelaksanaan' => [11],
            'leading' => '1.6',
            'kode_rktm' => '6.17.13',
            'kode_sk' => 'SK.10',
            'kode_iksk' => 'IKSK 10.2',
            'kode_edm' => 'A7',
            'budaya_mutu' => 2,
            'tempat' => 'Ruang Rapat Madrasah',
            'peserta_jumlah' => 30,
            'peserta_jenis' => 'Tim pengembang madrasah dan perwakilan komite',
            'uraian_pelaksanaan' => 'rapat kerja dua hari: telaah capaian RKJM dan hasil EDM, dilanjutkan penyusunan program dan anggaran RKTM bersama narasumber.',
            'konteks' => 'Rapat kerja 2 hari diikuti 30 orang (tim pengembang madrasah dan perwakilan komite); narasumber dari Kankemenag; output dokumen RKTM yang disahkan kepala madrasah dan ketua komite.',
            'sumber_dana' => [['sumber' => 'DIPA', 'pagu' => null], ['sumber' => 'Komite', 'pagu' => 7_500_000]],
            // Nama fiktif untuk contoh.
            'panitia' => [
                ['nama' => 'Ahmad Fauzi, S.Pd.', 'jabatan' => 'Tim Penjamin Mutu', 'peran' => 'Ketua'],
                ['nama' => 'Nur Hidayah, S.Pd.', 'jabatan' => 'Guru IPA', 'peran' => 'Sekretaris'],
            ],
        ];
    }

    /**
     * Contoh kegiatan yang dibiayai DIPA dan Komite sekaligus (dua RAB terpisah, nama fiktif).
     *
     * @return array<string, mixed>
     */
    public static function contohDuaSumber(): array
    {
        $tahun = StubData::tahunAktif()['tahun'];

        return [
            'id' => 2,
            'status' => 'draf',
            'dibuat_pada' => "{$tahun}-01-05T09:00:00+07:00",
            'diperbarui_pada' => "{$tahun}-01-05T09:00:00+07:00",
            'nama_kegiatan' => 'Supervisi Akademik dan Refleksi Pembelajaran',
            'nama_kegiatan_cover' => 'SUPERVISI AKADEMIK DAN REFLEKSI PEMBELAJARAN',
            'cover_manual' => false,
            'latar_belakang' => '',
            'tujuan' => 'memetakan mutu proses pembelajaran setiap guru dan menyusun tindak lanjut peningkatannya.',
            'sasaran' => 'seluruh guru memperoleh umpan balik supervisi dan rencana tindak lanjut.',
            'tanggal_dokumen' => "{$tahun}-01-05",
            'tanggal_laporan' => "{$tahun}-09-14",
            'laporan_otomatis' => true,
            'tanggal_mulai' => "{$tahun}-08-31",
            'lama_hari' => 5,
            'hari_kerja' => true,
            'bulan_pelaksanaan' => [8, 9],
            'leading' => '1.6',
            'kode_rktm' => '6.19.1',
            'kode_sk' => 'SK.11',
            'kode_iksk' => 'IKSK 11.5',
            'kode_edm' => 'A3',
            'budaya_mutu' => 2,
            'tempat' => 'Ruang Kelas dan Ruang Rapat Madrasah',
            'peserta_jumlah' => 54,
            'peserta_jenis' => 'Guru',
            'uraian_pelaksanaan' => 'observasi kelas oleh tim supervisor, dilanjutkan sesi refleksi bersama narasumber.',
            'konteks' => 'Supervisi dibiayai DIPA; refleksi dan penghargaan guru terbaik dibiayai Komite.',
            'sumber_dana' => [['sumber' => 'DIPA', 'pagu' => 6_000_000], ['sumber' => 'Komite', 'pagu' => 2_500_000]],
            // Nama fiktif untuk contoh.
            'panitia' => [
                ['nama' => 'Ahmad Fauzi, S.Pd.', 'jabatan' => 'Tim Penjamin Mutu', 'peran' => 'Ketua'],
                ['nama' => 'Dewi Lestari, S.Pd.', 'jabatan' => 'Guru Matematika', 'peran' => 'Sekretaris'],
            ],
        ];
    }

    /**
     * Contoh kegiatan berdasarkan baris "data TOR" madrasah (tanpa nama/NIP pejabat).
     *
     * @return array<string, mixed>
     */
    public static function contoh(): array
    {
        $tahun = StubData::tahunAktif()['tahun'];

        return [
            'id' => 1,
            'status' => 'draf',
            'dibuat_pada' => "{$tahun}-01-03T08:00:00+07:00",
            'diperbarui_pada' => "{$tahun}-01-03T08:00:00+07:00",
            'nama_kegiatan' => 'Workshop Penyusunan Kurikulum Madrasah dan Perangkat Pembelajaran',
            'nama_kegiatan_cover' => 'WORKSHOP PENYUSUNAN KURIKULUM MADRASAH DAN PERANGKAT PEMBELAJARAN',
            'cover_manual' => false,
            'latar_belakang' => '',
            'tujuan' => 'menghasilkan kegiatan yang sesuai harapan guru dan warga madrasah serta memperoleh dokumen yang terlegitimasi sesuai prinsip partisipatif, transparan, dan akuntabel.',
            'sasaran' => 'dokumen perencanaan yang handal dan akurat sesuai kebutuhan riil madrasah.',
            'tanggal_dokumen' => "{$tahun}-01-03",
            'tanggal_laporan' => "{$tahun}-07-30",
            'laporan_otomatis' => false,
            'tanggal_mulai' => "{$tahun}-07-14",
            'lama_hari' => 1,
            'hari_kerja' => false,
            'bulan_pelaksanaan' => [7],
            'leading' => '1.6',
            'kode_rktm' => '6.17.12',
            'kode_sk' => 'SK.10',
            'kode_iksk' => 'IKSK 10.6',
            'kode_edm' => 'C1',
            'budaya_mutu' => 2,
            'tempat' => 'Aula Madrasah',
            'peserta_jumlah' => 56,
            'peserta_jenis' => 'Guru dan tenaga kependidikan',
            'uraian_pelaksanaan' => 'workshop tatap muka dengan narasumber pengawas madrasah, dilanjutkan kerja kelompok penyusunan perangkat pembelajaran.',
            'konteks' => 'Peserta 54 guru serta kepala madrasah dan kepala TU; narasumber pengawas madrasah; output berupa dokumen kurikulum madrasah dan perangkat pembelajaran yang disahkan.',
            'sumber_dana' => [['sumber' => 'Komite', 'pagu' => 10_000_000]],
            // Nama fiktif untuk contoh.
            'panitia' => [
                ['nama' => 'Ahmad Fauzi, S.Pd.', 'jabatan' => 'Tim Penjamin Mutu', 'peran' => 'Ketua'],
                ['nama' => 'Siti Aminah, S.Pd.', 'jabatan' => 'Guru Bahasa Indonesia', 'peran' => 'Sekretaris'],
                ['nama' => 'Rina Wulandari, S.E.', 'jabatan' => 'Staf Keuangan', 'peran' => 'Bendahara'],
            ],
        ];
    }
}
