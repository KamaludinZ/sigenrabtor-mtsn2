<?php

namespace App\Support\Stub;

use App\Contracts\DataPaguRepository;
use App\Enums\SumberDana;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Data Pagu tiruan di berkas JSON (disk "local"). Saat berkas belum ada, contoh tahun aktif disiapkan: pagu tiga contoh
 * kegiatan (sudah menjadi kegiatan) dan beberapa nama yang belum dipakai.
 */
class StubDataPaguRepository implements DataPaguRepository
{
    public function semua(int $tahun): array
    {
        $daftar = array_values(array_filter($this->baca(), fn (array $p) => (int) $p['tahun'] === $tahun));
        usort($daftar, fn (array $a, array $b) => strcasecmp($a['nama_kegiatan'], $b['nama_kegiatan']));

        return $daftar;
    }

    public function temukan(string $id): ?array
    {
        return collect($this->baca())->firstWhere('id', $id);
    }

    public function simpan(array $data, ?string $id = null): string
    {
        $semua = $this->baca();
        $lama = $id !== null ? collect($semua)->firstWhere('id', $id) : null;
        $id = $lama['id'] ?? (string) Str::uuid();
        $baris = [
            'id' => $id,
            'tahun' => (int) $data['tahun'],
            'nama_kegiatan' => (string) $data['nama_kegiatan'],
            'leading' => ($data['leading'] ?? null) ?: null,
            'pagu' => array_map(fn ($v) => $v === null ? null : (int) $v, array_merge(array_fill_keys(SumberDana::urutan(), null), array_intersect_key((array) $data['pagu'], array_flip(SumberDana::urutan())))),
            'asal' => $data['asal'] ?? ($lama['asal'] ?? 'manual'),
            'dibuat_pada' => $lama['dibuat_pada'] ?? now()->toIso8601String(),
            'diperbarui_pada' => now()->toIso8601String(),
        ];

        $semua = $lama ? array_map(fn (array $p) => $p['id'] === $id ? $baris : $p, $semua) : [...$semua, $baris];
        $this->tulis($semua);

        return $id;
    }

    public function hapus(string $id): void
    {
        $this->tulis(array_values(array_filter($this->baca(), fn (array $p) => $p['id'] !== $id)));
    }

    /**
     * Contoh data pagu tahun aktif (nama & nominal rekaan kecuali tiga contoh kegiatan).
     *
     * @return list<array<string, mixed>>
     */
    public static function contoh(): array
    {
        $tahun = StubData::tahunAktif()['tahun'];
        $waktu = "{$tahun}-01-02T08:00:00+07:00";
        $baris = fn (string $id, string $nama, ?string $leading, ?int $dipa, ?int $komite) => [
            'id' => $id, 'tahun' => $tahun, 'nama_kegiatan' => $nama, 'leading' => $leading,
            'pagu' => ['DIPA' => $dipa, 'Komite' => $komite], 'asal' => 'impor', 'dibuat_pada' => $waktu, 'diperbarui_pada' => $waktu,
        ];

        return [
            $baris('9b2f1c3e-0001-4a51-8c11-000000000001', 'Workshop Penyusunan Kurikulum Madrasah dan Perangkat Pembelajaran', '1.6', null, 10_000_000),
            $baris('9b2f1c3e-0002-4a51-8c11-000000000002', 'Supervisi Akademik dan Refleksi Pembelajaran', '1.6', 6_000_000, 2_500_000),
            $baris('9b2f1c3e-0003-4a51-8c11-000000000003', 'Review RKJM dan Penyusunan RKTM', '1.6', null, 7_500_000),
            $baris('9b2f1c3e-0004-4a51-8c11-000000000004', 'Pelatihan Penulisan Karya Ilmiah Guru', '1.6', 4_500_000, null),
            $baris('9b2f1c3e-0005-4a51-8c11-000000000005', 'Lomba Kreativitas Siswa Tingkat Kota', '1.2', null, 12_000_000),
            $baris('9b2f1c3e-0006-4a51-8c11-000000000006', 'Pengadaan Buku Referensi Perpustakaan', '1.3', 15_000_000, null),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function baca(): array
    {
        if (! $this->disk()->exists($this->berkas())) {
            return self::contoh();
        }

        $isi = json_decode((string) $this->disk()->get($this->berkas()), true);

        return is_array($isi) ? array_values($isi) : [];
    }

    /**
     * @param  list<array<string, mixed>>  $semua
     */
    private function tulis(array $semua): void
    {
        $this->disk()->put($this->berkas(), json_encode(array_values($semua), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function berkas(): string
    {
        return config('sigenrabtor.stub.data_pagu_file');
    }

    private function disk(): Filesystem
    {
        return Storage::disk('local');
    }
}
