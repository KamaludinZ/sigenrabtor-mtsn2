<?php

namespace App\Support\Stub;

use App\Contracts\HasilDokumenRepository;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/** Hasil pembuatan dokumen tiruan: berkas JSON di disk "local" berisi daftar hasil (kosong selama belum ada berkas). */
class StubHasilDokumenRepository implements HasilDokumenRepository
{
    public function temukan(int $kegiatanId, string $jenis, ?string $sumber): ?array
    {
        return collect($this->baca())->first(fn (array $h) => $this->sama($h, $kegiatanId, $jenis, $sumber));
    }

    public function untukKegiatan(int $kegiatanId): array
    {
        $milik = array_values(array_filter($this->baca(), fn (array $h) => $h['kegiatan_id'] === $kegiatanId));
        usort($milik, fn (array $a, array $b) => strcmp($b['disusun_pada'], $a['disusun_pada']));

        return $milik;
    }

    public function simpan(int $kegiatanId, array $hasil): array
    {
        $semua = $this->baca();
        $lama = collect($semua)->search(fn (array $h) => $this->sama($h, $kegiatanId, $hasil['jenis'], $hasil['sumber']));
        $baru = ['id' => $lama !== false ? $semua[$lama]['id'] : (string) Str::uuid(), 'kegiatan_id' => $kegiatanId] + $hasil;
        $baru['disusun_pada'] = now()->format('Y-m-d\TH:i:s.uP');
        if ($lama !== false) {
            $semua[$lama] = $baru;
        } else {
            $semua[] = $baru;
        }
        if (! Storage::disk('local')->put($this->berkas(), json_encode(array_values($semua), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            throw new RuntimeException('Hasil dokumen gagal disimpan — coba lagi.');
        }

        return $this->temukan($kegiatanId, $hasil['jenis'], $hasil['sumber']);
    }

    /** @param  array<string, mixed>  $h */
    private function sama(array $h, int $kegiatanId, string $jenis, ?string $sumber): bool
    {
        return $h['kegiatan_id'] === $kegiatanId && $h['jenis'] === $jenis && $h['sumber'] === $sumber;
    }

    /** @return list<array<string, mixed>> */
    private function baca(): array
    {
        if (! Storage::disk('local')->exists($this->berkas())) {
            return [];
        }
        $isi = json_decode((string) Storage::disk('local')->get($this->berkas()), true);

        return is_array($isi) ? $isi : [];
    }

    private function berkas(): string
    {
        return config('sigenrabtor.stub.hasil_dokumen_file');
    }
}
