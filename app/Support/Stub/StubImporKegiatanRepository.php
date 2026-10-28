<?php

namespace App\Support\Stub;

use App\Contracts\ImporKegiatanRepository;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/** Impor kegiatan tiruan: berkas JSON disk "local" berkunci id impor → {impor, baris}. */
class StubImporKegiatanRepository implements ImporKegiatanRepository
{
    public function mulai(array $impor, array $baris): array
    {
        $semua = $this->baca();
        $id = (string) Str::uuid();
        $jumlah = fn (string $status) => count(array_filter($baris, fn (array $b) => $b['status'] === $status));
        $semua[$id] = [
            'impor' => ['id' => $id] + $impor + [
                'status' => 'berjalan',
                'jumlah_baris' => count($baris), 'jumlah_siap' => $jumlah('siap'), 'jumlah_dilewati' => $jumlah('dilewati'),
                'jumlah_gagal' => $jumlah('gagal'), 'jumlah_dibuat' => 0,
                'dibuat_pada' => now()->format('Y-m-d\TH:i:s.uP'), 'selesai_pada' => null,
            ],
            'baris' => array_map(fn (array $b) => $b + ['kegiatan_id' => null, 'diproses_pada' => null], array_values($baris)),
        ];
        usort($semua[$id]['baris'], fn (array $a, array $b) => $a['nomor_baris'] <=> $b['nomor_baris']);
        $this->tulis($semua);

        return $semua[$id]['impor'];
    }

    public function temukan(string $id): ?array
    {
        return $this->baca()[$id]['impor'] ?? null;
    }

    public function baris(string $id): array
    {
        return $this->baca()[$id]['baris'] ?? [];
    }

    public function tandaiDibuat(string $id, int $nomor, int $kegiatanId): void
    {
        $semua = $this->baca();
        $i = collect($semua[$id]['baris'] ?? [])->search(fn (array $b) => $b['nomor_baris'] === $nomor && $b['status'] === 'siap');
        if ($i === false) {
            throw new InvalidArgumentException("Baris siap {$nomor} impor {$id} tidak ditemukan.");
        }
        if ($semua[$id]['baris'][$i]['kegiatan_id'] === null) {
            $semua[$id]['impor']['jumlah_dibuat']++;
        }
        $semua[$id]['baris'][$i] = ['kegiatan_id' => $kegiatanId, 'diproses_pada' => now()->format('Y-m-d\TH:i:s.uP')] + $semua[$id]['baris'][$i];
        $this->tulis($semua);
    }

    public function tandaiBaris(string $id, int $nomor, string $status, array $pesan): void
    {
        $semua = $this->baca();
        $i = collect($semua[$id]['baris'] ?? [])->search(fn (array $b) => $b['nomor_baris'] === $nomor && $b['kegiatan_id'] === null);
        if ($i === false || ! in_array($status, ['siap', 'dilewati', 'gagal'], true)) {
            throw new InvalidArgumentException("Baris {$nomor} impor {$id} tidak bisa ditandai {$status}.");
        }
        $lama = $semua[$id]['baris'][$i]['status'];
        $semua[$id]['baris'][$i] = ['status' => $status, 'pesan' => array_values($pesan)] + $semua[$id]['baris'][$i];
        $semua[$id]['impor']["jumlah_{$lama}"]--;
        $semua[$id]['impor']["jumlah_{$status}"]++;
        $this->tulis($semua);
    }

    public function akhiri(string $id, string $status): array
    {
        $semua = $this->baca();
        if (! isset($semua[$id]) || ! in_array($status, ['selesai', 'dibatalkan'], true)) {
            throw new InvalidArgumentException("Impor {$id} tidak bisa diakhiri sebagai {$status}.");
        }
        $semua[$id]['impor'] = ['status' => $status, 'selesai_pada' => now()->format('Y-m-d\TH:i:s.uP')] + $semua[$id]['impor'];
        $this->tulis($semua);

        return $semua[$id]['impor'];
    }

    /** @return array<string, array{impor: array<string, mixed>, baris: list<array<string, mixed>>}> */
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
        return config('sigenrabtor.stub.impor_file');
    }
}
