<?php

namespace App\Support\Stub;

use App\Contracts\RiwayatSuntingRepository;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/** Jejak sunting tiruan: berkas JSON di disk "local" berisi daftar catatan (kosong selama belum ada berkas). */
class StubRiwayatSuntingRepository implements RiwayatSuntingRepository
{
    public function catat(int $kegiatanId, array $entri): array
    {
        $semua = $this->baca();
        $catatan = [
            'id' => (string) (max([0, ...array_map(fn (array $c) => (int) $c['id'], $semua)]) + 1),
            'kegiatan_id' => $kegiatanId,
            'bidang' => $entri['bidang'],
            'nomor_bagian' => (int) $entri['nomor_bagian'],
            'asal' => $entri['asal'],
            'teks_lama' => $entri['teks_lama'],
            'teks_baru' => $entri['teks_baru'],
            'user_id' => $entri['user_id'] ?? null,
            'pengguna' => $entri['pengguna'],
            'dibuat_pada' => now()->format('Y-m-d\TH:i:s.uP'),
        ];
        $semua[] = $catatan;
        if (! Storage::disk('local')->put($this->berkas(), json_encode($semua, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            throw new RuntimeException('Riwayat sunting gagal disimpan — coba lagi.');
        }

        return $catatan;
    }

    public function untukKegiatan(int $kegiatanId, ?string $bidang = null, int $batas = 50): array
    {
        $milik = array_values(array_filter($this->baca(), fn (array $c) => $c['kegiatan_id'] === $kegiatanId && ($bidang === null || $c['bidang'] === $bidang)));
        usort($milik, fn (array $a, array $b) => [$b['dibuat_pada'], (int) $b['id']] <=> [$a['dibuat_pada'], (int) $a['id']]);

        return array_slice($milik, 0, max(0, $batas));
    }

    /** @return list<array<string, mixed>> */
    private function baca(): array
    {
        if (! Storage::disk('local')->exists($this->berkas())) {
            return [];
        }
        $isi = json_decode((string) Storage::disk('local')->get($this->berkas()), true);

        return is_array($isi) ? array_values($isi) : [];
    }

    private function berkas(): string
    {
        return config('sigenrabtor.stub.riwayat_sunting_file');
    }
}
