<?php

namespace App\Support\Stub;

use App\Contracts\RiwayatPengaturanRepository;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Riwayat tiruan di berkas JSON (disk "local"). Saat berkas belum ada, contoh aktivasi tahun 2025 & 2026 disiapkan. */
class StubRiwayatPengaturanRepository implements RiwayatPengaturanRepository
{
    public function catat(string $aksi, string $keterangan): void
    {
        $pengguna = StubData::currentUser();
        $semua = $this->baca();
        $semua[] = [
            'id' => (string) Str::uuid(),
            'waktu' => now()->toIso8601String(),
            'user_id' => $pengguna['id'],
            'pengguna' => $pengguna['name'],
            'aksi' => $aksi,
            'keterangan' => $keterangan,
            'tahun' => StubData::tahunAktif()['tahun'],
        ];
        $this->disk()->put($this->berkas(), json_encode($semua, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public function semua(): array
    {
        // Dibalik dulu: entri dengan waktu sama (detik yang sama) tetap terbaru-dicatat lebih dulu karena usort stabil.
        $semua = array_reverse($this->baca());
        usort($semua, fn (array $a, array $b) => strcmp($b['waktu'], $a['waktu']));

        return $semua;
    }

    /**
     * @return list<array{id: string, waktu: string, user_id: int, pengguna: string, aksi: string, keterangan: string, tahun: int}>
     */
    public static function contoh(): array
    {
        return [
            ['id' => 'contoh-1', 'waktu' => '2025-01-02T08:00:00+07:00', 'user_id' => 2, 'pengguna' => 'Koordinator PUSKOM', 'aksi' => 'ganti_tahun', 'keterangan' => 'Tahun Anggaran 2025 diaktifkan.', 'tahun' => 2025],
            ['id' => 'contoh-2', 'waktu' => '2026-01-02T08:00:00+07:00', 'user_id' => 2, 'pengguna' => 'Koordinator PUSKOM', 'aksi' => 'ganti_tahun', 'keterangan' => 'Tahun Anggaran 2026 diaktifkan (sebelumnya 2025); 2025 dikunci.', 'tahun' => 2026],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function baca(): array
    {
        if (! $this->disk()->exists($this->berkas())) {
            return self::contoh();
        }

        $isi = json_decode((string) $this->disk()->get($this->berkas()), true);

        return is_array($isi) ? array_values($isi) : [];
    }

    private function berkas(): string
    {
        return config('sigenrabtor.stub.riwayat_file');
    }

    private function disk(): Filesystem
    {
        return Storage::disk('local');
    }
}
