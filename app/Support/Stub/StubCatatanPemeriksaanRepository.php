<?php

namespace App\Support\Stub;

use App\Contracts\CatatanPemeriksaanRepository;
use Illuminate\Support\Facades\Storage;

/** Draf catatan pemeriksaan tiruan: berkas JSON di disk "local" berisi id kegiatan → draf. */
class StubCatatanPemeriksaanRepository implements CatatanPemeriksaanRepository
{
    public function draf(int $kegiatanId): ?array
    {
        return $this->baca()[(string) $kegiatanId] ?? null;
    }

    public function simpan(int $kegiatanId, string $catatan, array $pengguna): array
    {
        $semua = $this->baca();
        $semua[(string) $kegiatanId] = $draf = [
            'catatan' => $catatan,
            'user_id' => $pengguna['id'] ?? null,
            'oleh' => $pengguna['name'],
            'diperbarui_pada' => now()->format('Y-m-d\TH:i:s.uP'),
        ];
        $this->tulis($semua);

        return $draf;
    }

    public function hapus(int $kegiatanId): void
    {
        $semua = $this->baca();
        if (isset($semua[(string) $kegiatanId])) {
            unset($semua[(string) $kegiatanId]);
            $this->tulis($semua);
        }
    }

    /** @return array<string, array{catatan: string, user_id: int|null, oleh: string, diperbarui_pada: string}> */
    private function baca(): array
    {
        $isi = Storage::disk('local')->exists($this->berkas()) ? json_decode((string) Storage::disk('local')->get($this->berkas()), true) : null;

        return is_array($isi) ? $isi : [];
    }

    /** @param  array<string, mixed>  $semua */
    private function tulis(array $semua): void
    {
        Storage::disk('local')->put($this->berkas(), json_encode($semua, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function berkas(): string
    {
        return config('sigenrabtor.stub.catatan_pemeriksaan_file');
    }
}
