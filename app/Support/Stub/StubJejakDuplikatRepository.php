<?php

namespace App\Support\Stub;

use App\Contracts\JejakDuplikatRepository;
use Illuminate\Support\Facades\Storage;

/** Jejak duplikat tiruan: daftar entri di berkas JSON disk "local" (hanya ditambah, id berurutan). */
class StubJejakDuplikatRepository implements JejakDuplikatRepository
{
    public function catat(array $jejak): array
    {
        $semua = $this->baca();
        $semua[] = $tersimpan = ['id' => count($semua) + 1] + $jejak + ['dibuat_pada' => now()->format('Y-m-d\TH:i:s.uP')];
        Storage::disk('local')->put($this->berkas(), json_encode($semua, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $tersimpan;
    }

    public function untukHasil(int $hasilId): ?array
    {
        return collect($this->baca())->firstWhere('hasil_id', $hasilId);
    }

    public function semua(int $tahun): array
    {
        return array_values(array_reverse(array_filter($this->baca(), fn (array $j) => $j['tahun'] === $tahun)));
    }

    /** @return list<array<string, mixed>> */
    private function baca(): array
    {
        $isi = Storage::disk('local')->exists($this->berkas()) ? json_decode((string) Storage::disk('local')->get($this->berkas()), true) : null;

        return is_array($isi) ? array_values($isi) : [];
    }

    private function berkas(): string
    {
        return config('sigenrabtor.stub.duplikat_file');
    }
}
