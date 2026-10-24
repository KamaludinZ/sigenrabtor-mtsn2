<?php

namespace App\Support\Stub;

use App\Contracts\KegiatanRepository;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Penyimpanan kegiatan tiruan di berkas JSON (disk "local") untuk tahap frontend.
 *
 * Saat berkas belum ada, satu contoh kegiatan disiapkan agar alur "muat draf" langsung bisa dicoba.
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
        $semua = array_values($this->baca());
        usort($semua, fn ($a, $b) => [$b['diperbarui_pada'], $b['id']] <=> [$a['diperbarui_pada'], $a['id']]);

        return array_map(fn (array $k) => [
            'id' => $k['id'],
            'nama_kegiatan' => $k['nama_kegiatan'] ?? '',
            'status' => $k['status'] ?? 'draf',
            'diperbarui_pada' => $k['diperbarui_pada'],
        ], array_slice($semua, 0, $batas));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function baca(): array
    {
        if (! $this->disk()->exists($this->berkas())) {
            $this->tulis([1 => self::contoh()]);
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
            // Nama fiktif untuk contoh.
            'panitia' => [
                ['nama' => 'Ahmad Fauzi, S.Pd.', 'jabatan' => 'Tim Penjamin Mutu', 'peran' => 'Ketua'],
                ['nama' => 'Siti Aminah, S.Pd.', 'jabatan' => 'Guru Bahasa Indonesia', 'peran' => 'Sekretaris'],
                ['nama' => 'Rina Wulandari, S.E.', 'jabatan' => 'Staf Keuangan', 'peran' => 'Bendahara'],
            ],
        ];
    }
}
