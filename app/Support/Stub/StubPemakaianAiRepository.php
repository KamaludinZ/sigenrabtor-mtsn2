<?php

namespace App\Support\Stub;

use App\Contracts\PemakaianAiRepository;
use Illuminate\Support\Facades\Storage;

/**
 * Hitungan pemakaian AI tiruan di berkas JSON (disk "local"): {tanggal: {userId: {fitur: jumlah}}}; hanya 7 hari
 * terakhir yang disimpan.
 */
class StubPemakaianAiRepository implements PemakaianAiRepository
{
    public function jumlah(int $userId, string $tanggal): int
    {
        return array_sum($this->baca()[$tanggal][$userId] ?? []);
    }

    public function rincian(int $userId, string $tanggal): array
    {
        return array_map('intval', $this->baca()[$tanggal][$userId] ?? []);
    }

    public function catat(int $userId, string $fitur): void
    {
        $this->ubah($userId, $fitur, 1);
    }

    public function ambilJatah(int $userId, string $fitur, int $batas): bool
    {
        // Tiruan satu proses: periksa lalu catat (database memakai kunci per akun).
        if ($this->jumlah($userId, now()->toDateString()) >= $batas) {
            return false;
        }
        $this->catat($userId, $fitur);

        return true;
    }

    public function kembalikan(int $userId, string $fitur): void
    {
        $this->ubah($userId, $fitur, -1);
    }

    private function ubah(int $userId, string $fitur, int $selisih): void
    {
        $semua = $this->baca();
        $hari = now()->toDateString();
        $jumlah = ($semua[$hari][$userId][$fitur] ?? 0) + $selisih;
        if ($jumlah > 0) {
            $semua[$hari][$userId][$fitur] = $jumlah;
        } else {
            unset($semua[$hari][$userId][$fitur]);
        }
        $batas = now()->subDays(7)->toDateString();
        $semua = array_filter($semua, fn (string $tanggal) => $tanggal > $batas, ARRAY_FILTER_USE_KEY);
        Storage::disk('local')->put($this->berkas(), json_encode($semua, JSON_PRETTY_PRINT));
    }

    /** @return array<string, array<int|string, array<string, int>>> */
    private function baca(): array
    {
        $isi = Storage::disk('local')->exists($this->berkas()) ? json_decode((string) Storage::disk('local')->get($this->berkas()), true) : null;

        return is_array($isi) ? $isi : [];
    }

    private function berkas(): string
    {
        return config('sigenrabtor.stub.pemakaian_ai_file');
    }
}
