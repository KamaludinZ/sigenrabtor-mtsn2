<?php

namespace App\Support\Stub;

use App\Contracts\DrafSuntingRepository;
use App\Support\Dokumen\SuntingDokumen;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/** Draf sunting tiruan: berkas JSON di disk "local" berisi daftar draf (kosong selama belum ada berkas). */
class StubDrafSuntingRepository implements DrafSuntingRepository
{
    public function untuk(int $kegiatanId, int $userId): array
    {
        $milik = array_values(array_filter($this->baca(), fn (array $d) => $d['kegiatan_id'] === $kegiatanId && $d['user_id'] === $userId));
        $urutan = array_flip(array_keys(SuntingDokumen::BIDANG));
        usort($milik, fn (array $a, array $b) => ($urutan[$a['bidang']] ?? 99) <=> ($urutan[$b['bidang']] ?? 99));

        return $milik;
    }

    public function temukan(int $kegiatanId, int $userId, string $bidang): ?array
    {
        return collect($this->baca())->first(fn (array $d) => $this->sama($d, $kegiatanId, $userId, $bidang));
    }

    public function simpan(int $kegiatanId, int $userId, string $bidang, string $teks, ?string $teksAwal): array
    {
        $semua = $this->baca();
        $lama = collect($semua)->search(fn (array $d) => $this->sama($d, $kegiatanId, $userId, $bidang));
        $sekarang = now()->format('Y-m-d\TH:i:s.uP');
        $draf = [
            'id' => $lama !== false ? $semua[$lama]['id'] : (string) (max([0, ...array_map(fn (array $d) => (int) $d['id'], $semua)]) + 1),
            'kegiatan_id' => $kegiatanId,
            'user_id' => $userId,
            'bidang' => $bidang,
            'teks' => $teks,
            'teks_awal' => $lama !== false ? $semua[$lama]['teks_awal'] : $teksAwal,
            'dibuat_pada' => $lama !== false ? $semua[$lama]['dibuat_pada'] : $sekarang,
            'diperbarui_pada' => $sekarang,
        ];
        if ($lama !== false) {
            $semua[$lama] = $draf;
        } else {
            $semua[] = $draf;
        }
        $this->tulis($semua);

        return $draf;
    }

    public function hapus(int $kegiatanId, int $userId, string $bidang): bool
    {
        $semua = $this->baca();
        $sisa = array_values(array_filter($semua, fn (array $d) => ! $this->sama($d, $kegiatanId, $userId, $bidang)));
        if (count($sisa) === count($semua)) {
            return false;
        }
        $this->tulis($sisa);

        return true;
    }

    /** @param  array<string, mixed>  $d */
    private function sama(array $d, int $kegiatanId, int $userId, string $bidang): bool
    {
        return $d['kegiatan_id'] === $kegiatanId && $d['user_id'] === $userId && $d['bidang'] === $bidang;
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

    /** @param  list<array<string, mixed>>  $semua */
    private function tulis(array $semua): void
    {
        if (! Storage::disk('local')->put($this->berkas(), json_encode(array_values($semua), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            throw new RuntimeException('Draf sunting gagal disimpan — coba lagi.');
        }
    }

    private function berkas(): string
    {
        return config('sigenrabtor.stub.draf_sunting_file');
    }
}
