<?php

namespace App\Support\Stub;

use App\Contracts\LeadingRepository;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Leading tiruan di berkas JSON (disk "local"). Saat berkas belum ada, contoh disiapkan: delapan leading madrasah
 * (1.1 Waka Kurikulum … 1.8 PMM) aktif, dan satu leading nonaktif (1.9) untuk mencoba tampilan status.
 */
class StubLeadingRepository implements LeadingRepository
{
    public function semua(): array
    {
        $semua = array_map(fn (array $l) => [
            'kode' => (string) $l['kode'],
            'nama_unit' => (string) $l['nama_unit'],
            'jabatan_penanda_tangan' => (string) $l['jabatan_penanda_tangan'],
            'urutan' => (int) ($l['urutan'] ?? 0),
            'is_active' => (bool) ($l['is_active'] ?? true),
        ], $this->baca());
        usort($semua, fn (array $a, array $b) => [$a['urutan'], $a['kode']] <=> [$b['urutan'], $b['kode']]);

        return $semua;
    }

    public function simpan(array $data, ?string $kodeLama = null): void
    {
        $semua = $this->baca();
        $indeks = $kodeLama === null ? false : array_search($kodeLama, array_column($semua, 'kode'), true);
        $lama = $indeks === false ? [] : $semua[$indeks];
        $baris = [
            'kode' => (string) $data['kode'],
            'nama_unit' => (string) $data['nama_unit'],
            'jabatan_penanda_tangan' => (string) $data['jabatan_penanda_tangan'],
            'urutan' => (int) ($data['urutan'] ?? $lama['urutan'] ?? (max([0, ...array_column($semua, 'urutan')]) + 1)),
            'is_active' => (bool) ($data['is_active'] ?? $lama['is_active'] ?? true),
        ];
        if ($indeks === false) {
            $semua[] = $baris;
        } else {
            $semua[$indeks] = $baris;
        }
        $this->tulis($semua);
    }

    /**
     * @return list<array{kode: string, nama_unit: string, jabatan_penanda_tangan: string, urutan: int, is_active: bool}>
     */
    public static function contoh(): array
    {
        $aktif = [
            ['1.1', 'Waka Kurikulum', 'Wakil Kepala Bidang Kurikulum'],
            ['1.2', 'Waka Kesiswaan', 'Wakil Kepala Bidang Kesiswaan'],
            ['1.3', 'Waka Sarpras', 'Wakil Kepala Bidang Sarana dan Prasarana'],
            ['1.4', 'Waka Humas', 'Wakil Kepala Bidang Hubungan Masyarakat'],
            ['1.5', 'Ka. TU', 'Kepala Tata Usaha'],
            ['1.6', 'Tim Penjamin Mutu', 'Ketua Tim Penjamin Mutu'],
            ['1.7', 'Mahad', 'Pengasuh Mahad'],
            ['1.8', 'PMM', 'Koordinator PMM'],
        ];
        $hasil = array_map(fn (array $l, int $i) => ['kode' => $l[0], 'nama_unit' => $l[1], 'jabatan_penanda_tangan' => $l[2], 'urutan' => $i + 1, 'is_active' => true], $aktif, array_keys($aktif));
        $hasil[] = ['kode' => '1.9', 'nama_unit' => 'Laboratorium Terpadu', 'jabatan_penanda_tangan' => 'Kepala Laboratorium', 'urutan' => 9, 'is_active' => false];

        return $hasil;
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
        if (! $this->disk()->put($this->berkas(), json_encode(array_values($semua), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            throw new RuntimeException('Data leading gagal ditulis.');
        }
    }

    private function berkas(): string
    {
        return config('sigenrabtor.stub.leading_file');
    }

    private function disk(): Filesystem
    {
        return Storage::disk('local');
    }
}
