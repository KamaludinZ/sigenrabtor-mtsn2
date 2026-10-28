<?php

namespace App\Support\Stub;

use App\Contracts\RiwayatKunciRepository;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Log kunci tiruan: daftar entri di berkas JSON disk "local" (hanya ditambah). */
class StubRiwayatKunciRepository implements RiwayatKunciRepository
{
    public function catat(int $kegiatanId, string $aksi, string $keterangan, array $pengguna, string $pada, ?int $tahun): array
    {
        $semua = $this->baca();
        $semua[] = $entri = [
            'id' => (string) Str::uuid(),
            'kegiatan_id' => $kegiatanId,
            'aksi' => $aksi,
            'keterangan' => $keterangan,
            'user_id' => $pengguna['id'] ?? null,
            'pengguna' => $pengguna['name'],
            'tahun' => $tahun,
            'pada' => $pada,
        ];
        Storage::disk('local')->put($this->berkas(), json_encode($semua, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $entri;
    }

    public function untukKegiatan(int $kegiatanId): array
    {
        return array_values(array_reverse(array_filter($this->baca(), fn (array $e) => $e['kegiatan_id'] === $kegiatanId)));
    }

    /** @return list<array<string, mixed>> */
    private function baca(): array
    {
        $isi = Storage::disk('local')->exists($this->berkas()) ? json_decode((string) Storage::disk('local')->get($this->berkas()), true) : null;

        return is_array($isi) ? array_values($isi) : [];
    }

    private function berkas(): string
    {
        return config('sigenrabtor.stub.riwayat_kunci_file');
    }
}
