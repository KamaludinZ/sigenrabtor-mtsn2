<?php

namespace App\Support\Stub;

use App\Contracts\TahunAnggaranRepository;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;

/**
 * Tahun anggaran tiruan di berkas JSON (disk "local"). Saat berkas belum ada, contoh disiapkan: 2026 aktif (SK RKTM
 * belum diisi) dan 2025 terkunci (master kodenya fiktif).
 */
class StubTahunAnggaranRepository implements TahunAnggaranRepository
{
    public function semua(): array
    {
        $semua = $this->baca();
        usort($semua, fn (array $a, array $b) => $b['tahun'] <=> $a['tahun']);

        return $semua;
    }

    public function aktif(): ?array
    {
        return collect($this->baca())->firstWhere('is_active', true);
    }

    public function aktifkan(int $tahun, string $oleh): void
    {
        $semua = $this->baca();

        if (! collect($semua)->contains('tahun', $tahun)) {
            throw new InvalidArgumentException("Tahun anggaran {$tahun} belum dibuat.");
        }

        $semua = array_map(fn (array $t) => $t['tahun'] === $tahun
            ? ['is_active' => true, 'diaktifkan_oleh' => $oleh, 'diaktifkan_pada' => now()->toIso8601String()] + $t
            : ['is_active' => false] + $t, $semua);
        $this->tulis($semua);
    }

    public function simpanSk(int $tahun, ?string $nomor, ?string $tanggal): void
    {
        $semua = $this->baca();

        if (! collect($semua)->contains('tahun', $tahun)) {
            throw new InvalidArgumentException("Tahun anggaran {$tahun} belum dibuat.");
        }

        $this->tulis(array_map(fn (array $t) => $t['tahun'] === $tahun ? ['no_sk_rktm' => $nomor, 'tanggal_sk_rktm' => $tanggal] + $t : $t, $semua));
    }

    /**
     * @return list<array{id: int, tahun: int, is_active: bool, is_locked: bool, no_sk_rktm: string|null, tanggal_sk_rktm: string|null, diaktifkan_oleh: string|null, diaktifkan_pada: string|null}>
     */
    public static function contoh(): array
    {
        return [
            ['id' => 1, 'tahun' => 2026, 'is_active' => true, 'is_locked' => false, 'no_sk_rktm' => null, 'tanggal_sk_rktm' => null,
                'diaktifkan_oleh' => 'Koordinator PUSKOM', 'diaktifkan_pada' => '2026-01-02T08:00:00+07:00'],
            ['id' => 2, 'tahun' => 2025, 'is_active' => false, 'is_locked' => true, 'no_sk_rktm' => 'Contoh 001/SK/RKTM/2025', 'tanggal_sk_rktm' => '2025-01-06',
                'diaktifkan_oleh' => 'Koordinator PUSKOM', 'diaktifkan_pada' => '2025-01-02T08:00:00+07:00'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function baca(): array
    {
        if (! $this->disk()->exists($this->berkas())) {
            $this->tulis(self::contoh());
        }

        $isi = json_decode((string) $this->disk()->get($this->berkas()), true);

        return is_array($isi) ? array_values($isi) : [];
    }

    /**
     * @param  list<array<string, mixed>>  $semua
     */
    private function tulis(array $semua): void
    {
        if (! $this->disk()->put($this->berkas(), json_encode(array_values($semua), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            throw new RuntimeException('Data tahun anggaran gagal ditulis.');
        }
    }

    private function berkas(): string
    {
        return config('sigenrabtor.stub.tahun_file');
    }

    private function disk(): Filesystem
    {
        return Storage::disk('local');
    }
}
