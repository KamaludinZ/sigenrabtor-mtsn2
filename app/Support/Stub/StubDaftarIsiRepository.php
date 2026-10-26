<?php

namespace App\Support\Stub;

use App\Contracts\DaftarIsiRepository;
use App\Support\Dokumen\DaftarIsi;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Konfigurasi daftar isi tiruan di berkas JSON (disk "local"): { "<tahun>": { "bawaan": {...}|null, "khusus": { "leading|sumber": {...} } } }.
 */
class StubDaftarIsiRepository implements DaftarIsiRepository
{
    public function bawaan(int $tahun): array
    {
        return DaftarIsi::rapikan($this->baca()[$tahun]['bawaan'] ?? DaftarIsi::BAWAAN);
    }

    public function khusus(int $tahun): array
    {
        return array_map(DaftarIsi::rapikan(...), (array) ($this->baca()[$tahun]['khusus'] ?? []));
    }

    public function simpanBawaan(int $tahun, array $konfigurasi): void
    {
        $semua = $this->baca();
        $semua[$tahun]['bawaan'] = $konfigurasi;
        $this->tulis($semua);
    }

    public function simpanKhusus(int $tahun, string $leading, string $sumber, array $konfigurasi): void
    {
        $semua = $this->baca();
        $semua[$tahun]['khusus']["{$leading}|{$sumber}"] = $konfigurasi;
        $this->tulis($semua);
    }

    public function hapusKhusus(int $tahun, string $leading, string $sumber): void
    {
        $semua = $this->baca();
        unset($semua[$tahun]['khusus']["{$leading}|{$sumber}"]);
        $this->tulis($semua);
    }

    /**
     * @return array<int|string, array{bawaan?: array<string, mixed>|null, khusus?: array<string, array<string, mixed>>}>
     */
    private function baca(): array
    {
        if (! $this->disk()->exists($this->berkas())) {
            return [];
        }

        $isi = json_decode((string) $this->disk()->get($this->berkas()), true);

        return is_array($isi) ? $isi : [];
    }

    /**
     * @param  array<int|string, mixed>  $semua
     */
    private function tulis(array $semua): void
    {
        $this->disk()->put($this->berkas(), json_encode($semua, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function berkas(): string
    {
        return config('sigenrabtor.stub.daftar_isi_file');
    }

    private function disk(): Filesystem
    {
        return Storage::disk('local');
    }
}
