<?php

namespace App\Support\Stub;

use App\Contracts\NomorHalamanRepository;
use App\Support\Dokumen\NomorHalaman;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/** Aturan nomor halaman tiruan di berkas JSON (disk "local"): { "<tahun>": {...} }. */
class StubNomorHalamanRepository implements NomorHalamanRepository
{
    public function untuk(int $tahun): array
    {
        return NomorHalaman::rapikan($this->baca()[$tahun] ?? NomorHalaman::BAWAAN);
    }

    public function simpan(int $tahun, array $aturan): void
    {
        $semua = $this->baca();
        $semua[$tahun] = $aturan;
        $this->disk()->put($this->berkas(), json_encode($semua, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return array<int|string, array<string, mixed>>
     */
    private function baca(): array
    {
        if (! $this->disk()->exists($this->berkas())) {
            return [];
        }

        $isi = json_decode((string) $this->disk()->get($this->berkas()), true);

        return is_array($isi) ? $isi : [];
    }

    private function berkas(): string
    {
        return config('sigenrabtor.stub.nomor_halaman_file');
    }

    private function disk(): Filesystem
    {
        return Storage::disk('local');
    }
}
