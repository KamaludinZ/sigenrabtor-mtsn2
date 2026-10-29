<?php

namespace App\Support\Stub;

use App\Contracts\PilihanTemplateRepository;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/** Pilihan template tiruan di berkas JSON (disk "local"): { "<tahun>": {jenis, leading, diperbarui_pada} }; tahun belum diatur = semua bawaan. */
class StubPilihanTemplateRepository implements PilihanTemplateRepository
{
    public function untuk(int $tahun): array
    {
        $data = $this->baca()[$tahun] ?? [];

        return ['jenis' => $data['jenis'] ?? [], 'leading' => $data['leading'] ?? [], 'diperbarui_pada' => $data['diperbarui_pada'] ?? null];
    }

    public function simpan(int $tahun, array $pilihan): void
    {
        $semua = $this->baca();
        $semua[$tahun] = ['jenis' => $pilihan['jenis'], 'leading' => array_values($pilihan['leading']), 'diperbarui_pada' => now()->format('Y-m-d\TH:i:s.uP')];
        if (! $this->tulis($semua)) {
            throw new RuntimeException('Pilihan template gagal disimpan — coba lagi.');
        }
    }

    /** @return array<int|string, array<string, mixed>> */
    private function baca(): array
    {
        if (! $this->disk()->exists($this->berkas())) {
            return [];
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
        return config('sigenrabtor.stub.pilihan_template_file', 'stub/pilihan-template.json');
    }

    private function disk(): Filesystem
    {
        return Storage::disk('local');
    }
}
