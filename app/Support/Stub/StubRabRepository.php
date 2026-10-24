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
