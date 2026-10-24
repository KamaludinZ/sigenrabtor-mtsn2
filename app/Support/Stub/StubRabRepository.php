<?php

namespace App\Support\Stub;

use App\Contracts\RabRepository;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Rincian RAB tiruan di berkas JSON (disk "local"), berstruktur { "<kegiatanId>": { "<sumber>": [item, …] } }.
 * Saat berkas belum ada, contoh RAB Komite kegiatan 1 disiapkan dari StubRab.
 */
class StubRabRepository implements RabRepository
{
    public function rincian(int $kegiatanId, string $sumber): array
    {
        return $this->baca()[$kegiatanId][$sumber] ?? [];
    }

    public function tambahItem(int $kegiatanId, string $sumber, array $item): string
    {
        $semua = $this->baca();
        $id = (string) Str::uuid();

        $semua[$kegiatanId][$sumber][] = ['id' => $id] + $item;
        $this->tulis($semua);

        return $id;
    }

    public function ubahItem(int $kegiatanId, string $sumber, string $id, array $item): bool
    {
        $semua = $this->baca();
        $index = $this->indeks($semua[$kegiatanId][$sumber] ?? [], $id);

        if ($index === null) {
            return false;
        }

        $semua[$kegiatanId][$sumber][$index] = ['id' => $id] + $item;
        $this->tulis($semua);

        return true;
    }

    public function hapusItem(int $kegiatanId, string $sumber, string $id): ?array
    {
        $semua = $this->baca();
        $daftar = $semua[$kegiatanId][$sumber] ?? [];
        $index = $this->indeks($daftar, $id);

        if ($index === null) {
            return null;
        }

        $item = $daftar[$index];
        array_splice($daftar, $index, 1);
        $semua[$kegiatanId][$sumber] = $daftar;
        $this->tulis($semua);

        return ['index' => $index, 'item' => $item];
    }

    public function sisipkanItem(int $kegiatanId, string $sumber, int $index, array $item): void
    {
        $semua = $this->baca();
        $daftar = $semua[$kegiatanId][$sumber] ?? [];

        array_splice($daftar, max(0, min($index, count($daftar))), 0, [$item]);
        $semua[$kegiatanId][$sumber] = $daftar;
        $this->tulis($semua);
    }

    /**
     * @param  list<array<string, mixed>>  $daftar
     */
    private function indeks(array $daftar, string $id): ?int
    {
        $index = array_search($id, array_column($daftar, 'id'), true);

        return $index === false ? null : $index;
    }

    /**
     * @return array<int|string, array<string, list<array<string, mixed>>>>
     */
    private function baca(): array
    {
        if (! $this->disk()->exists($this->berkas())) {
            $contoh = array_map(fn (array $item) => ['id' => (string) Str::uuid()] + $item, StubRab::rincian(1, 'Komite'));
            $this->tulis([1 => ['Komite' => $contoh]]);
        }

        $isi = json_decode((string) $this->disk()->get($this->berkas()), true);

        return is_array($isi) ? $isi : [];
    }

    /**
     * @param  array<int|string, array<string, list<array<string, mixed>>>>  $semua
     */
    private function tulis(array $semua): void
    {
        $this->disk()->put($this->berkas(), json_encode($semua, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function berkas(): string
    {
        return config('sigenrabtor.stub.rab_file');
    }

    private function disk(): Filesystem
    {
        return Storage::disk('local');
    }
}
