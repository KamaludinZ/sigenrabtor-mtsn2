<?php

namespace App\Support\Stub;

use App\Contracts\PenomoranDokumenRepository;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Penomoran dokumen tiruan di berkas JSON (disk "local"): { "<tahun>": {aturan, terakhir, diperbarui_pada} }. Selama berkas
 * belum ada, isinya `contoh()`: nomor terakhir TA 2026 per jenis (dokumen contoh yang sudah bernomor).
 */
class StubPenomoranDokumenRepository implements PenomoranDokumenRepository
{
    /** @return array<int, array<string, mixed>> */
    public static function contoh(): array
    {
        return [2026 => ['aturan' => [], 'terakhir' => ['KAK+TOR' => 31, 'KAK' => 4, 'TOR' => 0, 'RAB' => 12, 'lengkap' => 7], 'diperbarui_pada' => null]];
    }

    public function aturan(int $tahun): array
    {
        return $this->baca()[$tahun]['aturan'] ?? [];
    }

    public function meta(int $tahun): array
    {
        return ['diperbarui_pada' => $this->baca()[$tahun]['diperbarui_pada'] ?? null];
    }

    public function simpanAturan(int $tahun, array $aturan): void
    {
        $semua = $this->baca();
        $semua[$tahun] = ['aturan' => $aturan, 'diperbarui_pada' => now()->format('Y-m-d\TH:i:s.uP')] + ($semua[$tahun] ?? []);
        $this->simpanSemua($semua);
    }

    public function terakhir(int $tahun): array
    {
        return array_map('intval', $this->baca()[$tahun]['terakhir'] ?? []);
    }

    public function catatPakai(int $tahun, string $jenis, int $urut): void
    {
        $semua = $this->baca();
        $semua[$tahun]['terakhir'][$jenis] = max((int) ($semua[$tahun]['terakhir'][$jenis] ?? 0), $urut);
        $this->simpanSemua($semua);
    }

    /** @param  array<int|string, array<string, mixed>>  $semua */
    private function simpanSemua(array $semua): void
    {
        if (! $this->tulis($semua)) {
            throw new RuntimeException('Penomoran dokumen gagal disimpan — coba lagi.');
        }
    }

    /** @return array<int|string, array<string, mixed>> */
    private function baca(): array
    {
        if (! $this->disk()->exists($this->berkas())) {
            return self::contoh();
        }
        $isi = json_decode((string) $this->disk()->get($this->berkas()), true);

        return is_array($isi) ? $isi : [];
    }

    /** @param  array<int|string, array<string, mixed>>  $semua */
    protected function tulis(array $semua): bool
    {
        return $this->disk()->put($this->berkas(), json_encode($semua, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function berkas(): string
    {
        return config('sigenrabtor.stub.penomoran_dokumen_file', 'stub/penomoran-dokumen.json');
    }

    private function disk(): Filesystem
    {
        return Storage::disk('local');
    }
}
