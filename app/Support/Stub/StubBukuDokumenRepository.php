<?php

namespace App\Support\Stub;

use App\Contracts\BukuDokumenRepository;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Riwayat buku tiruan di berkas JSON (disk "local"). Selama berkas belum ada, riwayat berisi contoh dua penyusunan buku
 * Tim Penjamin Mutu TA 2026 (sesuai contoh kegiatan). Belum ada berkas PDF — penggabungannya menyusul di backend.
 */
class StubBukuDokumenRepository implements BukuDokumenRepository
{
    public function riwayat(int $tahun): array
    {
        $semua = array_values(array_filter($this->baca(), fn (array $b) => $b['tahun'] === $tahun));
        usort($semua, fn (array $a, array $b) => strcmp($b['dibuat_pada'], $a['dibuat_pada']));

        return $semua;
    }

    public function catat(array $buku): array
    {
        $tersimpan = ['id' => (string) Str::uuid(), 'file_pdf_path' => null, 'dibuat_pada' => now()->toIso8601String()] + $buku;
        Storage::disk('local')->put($this->berkas(), json_encode([...$this->baca(), $tersimpan], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $tersimpan;
    }

    /** @return list<array<string, mixed>> */
    public static function contoh(): array
    {
        return [
            [
                'id' => 'b1f0e8a2-6c1d-4c55-9a43-0d7c2f5e1a01', 'tahun' => 2026, 'leading' => '1.6', 'sumber' => 'Komite',
                'jumlah_kegiatan' => 3, 'halaman' => 26, 'ada_draf' => true, 'file_pdf_path' => null,
                'kegiatan' => [
                    ['id' => 1, 'nama' => 'Workshop Penyusunan Kurikulum Madrasah dan Perangkat Pembelajaran', 'halaman' => 3, 'hasil' => 'draf'],
                    ['id' => 3, 'nama' => 'Review RKJM dan Penyusunan RKTM', 'halaman' => 11, 'hasil' => 'draf'],
                    ['id' => 2, 'nama' => 'Supervisi Akademik dan Refleksi Pembelajaran', 'halaman' => 19, 'hasil' => 'draf'],
                ],
                'dibuat_oleh' => 'Muslimin, M.Pd.', 'dibuat_pada' => '2026-03-10T09:15:00+07:00',
            ],
            [
                'id' => 'b1f0e8a2-6c1d-4c55-9a43-0d7c2f5e1a02', 'tahun' => 2026, 'leading' => '1.6', 'sumber' => 'DIPA',
                'jumlah_kegiatan' => 2, 'halaman' => 18, 'ada_draf' => true, 'file_pdf_path' => null,
                'kegiatan' => [
                    ['id' => 3, 'nama' => 'Review RKJM dan Penyusunan RKTM', 'halaman' => 3, 'hasil' => 'draf'],
                    ['id' => 2, 'nama' => 'Supervisi Akademik dan Refleksi Pembelajaran', 'halaman' => 11, 'hasil' => 'draf'],
                ],
                'dibuat_oleh' => 'Koordinator PUSKOM', 'dibuat_pada' => '2026-02-20T14:00:00+07:00',
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function baca(): array
    {
        if (! Storage::disk('local')->exists($this->berkas())) {
            return self::contoh();
        }
        $isi = json_decode((string) Storage::disk('local')->get($this->berkas()), true);

        return is_array($isi) ? $isi : self::contoh();
    }

    private function berkas(): string
    {
        return config('sigenrabtor.stub.buku_file');
    }
}
