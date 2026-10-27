<?php

namespace App\Support\Stub;

use App\Contracts\NotifikasiRepository;
use Illuminate\Support\Facades\Storage;

/** Penanda baca notifikasi tiruan: berkas JSON di disk "local" berisi id akun → waktu terakhir dibaca. */
class StubNotifikasiRepository implements NotifikasiRepository
{
    public function dibacaSampai(int $penggunaId): ?string
    {
        return $this->baca()[(string) $penggunaId] ?? null;
    }

    public function tandaiDibaca(int $penggunaId, string $pada): void
    {
        $semua = $this->baca();
        $semua[(string) $penggunaId] = $pada;
        Storage::disk('local')->put($this->berkas(), json_encode($semua, JSON_PRETTY_PRINT));
    }

    /** @return array<string, string> */
    private function baca(): array
    {
        $isi = Storage::disk('local')->exists($this->berkas()) ? json_decode((string) Storage::disk('local')->get($this->berkas()), true) : null;

        return is_array($isi) ? $isi : [];
    }

    private function berkas(): string
    {
        return config('sigenrabtor.stub.notifikasi_file');
    }
}
