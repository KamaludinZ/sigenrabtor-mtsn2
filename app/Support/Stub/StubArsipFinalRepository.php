<?php

namespace App\Support\Stub;

use App\Contracts\ArsipFinalRepository;
use App\Enums\SumberDana;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Arsip final tiruan: metadata di berkas JSON (disk "local", berkunci id kegiatan lalu sumber dana), PDF di folder
 * senama berkas tanpa `.json` (`{folder}/{kegiatan}/{sumber}-{acak}.pdf`); dibaca urut SumberDana. Belum ada contoh arsip.
 */
class StubArsipFinalRepository implements ArsipFinalRepository
{
    public function untukKegiatan(int $kegiatanId): array
    {
        $arsip = $this->baca()[(string) $kegiatanId] ?? [];
        $urut = array_flip(SumberDana::urutan());
        uksort($arsip, fn (string $a, string $b) => ($urut[$a] ?? PHP_INT_MAX) <=> ($urut[$b] ?? PHP_INT_MAX));

        return $arsip;
    }

    public function simpan(int $kegiatanId, string $sumber, UploadedFile $berkas, array $pengguna): array
    {
        $semua = $this->baca();
        $lama = $semua[(string) $kegiatanId][$sumber]['path'] ?? null;
        $path = $berkas->storeAs(Str::beforeLast($this->berkas(), '.json')."/{$kegiatanId}", Str::slug($sumber).'-'.Str::random(8).'.pdf', 'local');

        if ($lama) {
            $this->disk()->delete($lama);
        }

        $arsip = [
            'sumber' => $sumber,
            'nama_berkas' => $berkas->getClientOriginalName(),
            'ukuran' => (int) $berkas->getSize(),
            'path' => $path,
            'diunggah_oleh' => $pengguna['name'],
            'diunggah_oleh_id' => $pengguna['id'] ?? null,
            'diunggah_pada' => now()->toIso8601String(),
        ];
        $semua[(string) $kegiatanId][$sumber] = $arsip;
        $this->tulis($semua);

        return $arsip;
    }

    public function hapus(int $kegiatanId, string $sumber): void
    {
        $semua = $this->baca();
        if (! isset($semua[(string) $kegiatanId][$sumber])) {
            return;
        }

        $this->disk()->delete($semua[(string) $kegiatanId][$sumber]['path']);
        unset($semua[(string) $kegiatanId][$sumber]);
        if ($semua[(string) $kegiatanId] === []) {
            unset($semua[(string) $kegiatanId]);
        }
        $this->tulis($semua);
    }

    /** @return array<string, array<string, array<string, mixed>>> */
    private function baca(): array
    {
        $isi = $this->disk()->exists($this->berkas()) ? json_decode((string) $this->disk()->get($this->berkas()), true) : null;

        return is_array($isi) ? $isi : [];
    }

    /** @param  array<string, array<string, array<string, mixed>>>  $semua */
    private function tulis(array $semua): void
    {
        $this->disk()->put($this->berkas(), json_encode($semua, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function berkas(): string
    {
        return config('sigenrabtor.stub.arsip_file');
    }

    private function disk(): Filesystem
    {
        return Storage::disk('local');
    }
}
