<?php

namespace App\Support\Stub;

use App\Contracts\TemplateDokumenRepository;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Master .docx tiruan: metadata di berkas JSON, berkas di folder senama (tanpa .json) pada disk "local" (privat; diunduh
 * lewat rute pengaturan.template.master.unduh). Belum ada contoh — dokumen memakai susunan baku selama kosong.
 */
class StubTemplateDokumenRepository implements TemplateDokumenRepository
{
    public function semua(int $tahun): array
    {
        $daftar = array_values(array_filter($this->baca(), fn (array $t) => $t['tahun'] === $tahun));
        usort($daftar, fn (array $a, array $b) => [$b['is_default'], $b['diperbarui_pada']] <=> [$a['is_default'], $a['diperbarui_pada']]);

        return $daftar;
    }

    public function temukan(string $id): ?array
    {
        return collect($this->baca())->firstWhere('id', $id);
    }

    public function tambah(int $tahun, string $nama, string $jenis, UploadedFile $berkas, array $variabel): string
    {
        $id = (string) Str::uuid();
        $path = $berkas->storeAs(Str::beforeLast($this->berkas(), '.json'), "{$id}.docx", 'local');
        $semua = $this->baca();
        $semua[] = [
            'id' => $id,
            'tahun' => $tahun,
            'nama' => $nama,
            'jenis' => $jenis,
            'file_path' => $path,
            'nama_berkas' => $berkas->getClientOriginalName(),
            'variabel' => array_values($variabel),
            'is_default' => ! collect($semua)->contains(fn (array $t) => $t['tahun'] === $tahun && $t['jenis'] === $jenis),
            'leading' => null,
            'diperbarui_pada' => now()->toIso8601String(),
        ];
        $this->tulis($semua);

        return $id;
    }

    public function hapus(string $id): void
    {
        $template = $this->temukan($id);

        if ($template === null) {
            return;
        }

        $this->disk()->delete($template['file_path']);
        $sisa = array_values(array_filter($this->baca(), fn (array $t) => $t['id'] !== $id));
        $pengganti = collect($sisa)->where('tahun', $template['tahun'])->where('jenis', $template['jenis'])->sortByDesc('diperbarui_pada')->first()['id'] ?? null;

        if ($template['is_default'] && $pengganti !== null) {
            $sisa = array_map(fn (array $t) => $t['id'] === $pengganti ? ['is_default' => true] + $t : $t, $sisa);
        }

        $this->tulis($sisa);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function baca(): array
    {
        if (! $this->disk()->exists($this->berkas())) {
            return [];
        }

        $isi = json_decode((string) $this->disk()->get($this->berkas()), true);

        return is_array($isi) ? array_values($isi) : [];
    }

    /**
     * @param  list<array<string, mixed>>  $semua
     */
    private function tulis(array $semua): void
    {
        $this->disk()->put($this->berkas(), json_encode(array_values($semua), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function berkas(): string
    {
        return config('sigenrabtor.stub.master_file');
    }

    private function disk(): Filesystem
    {
        return Storage::disk('local');
    }
}
