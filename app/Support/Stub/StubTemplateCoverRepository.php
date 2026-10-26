<?php

namespace App\Support\Stub;

use App\Contracts\TemplateCoverRepository;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Cover tiruan: metadata di berkas JSON, gambar di folder senama (tanpa .json) pada disk "local" (privat; tampil lewat rute
 * pengaturan.template.cover.gambar). Belum ada contoh — dokumen memakai tata letak baku selama kosong.
 */
class StubTemplateCoverRepository implements TemplateCoverRepository
{
    public function semua(int $tahun): array
    {
        $daftar = array_values(array_filter($this->baca(), fn (array $c) => $c['tahun'] === $tahun));
        usort($daftar, fn (array $a, array $b) => [$b['is_default'], $b['diperbarui_pada']] <=> [$a['is_default'], $a['diperbarui_pada']]);

        return $daftar;
    }

    public function temukan(string $id): ?array
    {
        return collect($this->baca())->firstWhere('id', $id);
    }

    public function tambah(int $tahun, string $nama, UploadedFile $gambar, array $teksItems): string
    {
        $id = (string) Str::uuid();
        $ekstensi = strtolower($gambar->guessExtension() ?: $gambar->getClientOriginalExtension() ?: 'png');
        $path = $gambar->storeAs(Str::beforeLast($this->berkas(), '.json'), "{$id}.{$ekstensi}", 'local');
        [$lebar, $tinggi] = getimagesize($gambar->getRealPath()) ?: [0, 0];

        $semua = $this->baca();
        $semua[] = [
            'id' => $id,
            'tahun' => $tahun,
            'nama' => $nama,
            'gambar_path' => $path,
            'lebar' => (int) $lebar,
            'tinggi' => (int) $tinggi,
            'teks_items' => $teksItems,
            'is_default' => $this->semua($tahun) === [],
            'leading' => null,
            'sumber_dana' => null,
            'diperbarui_pada' => now()->toIso8601String(),
        ];
        $this->tulis($semua);

        return $id;
    }

    public function ubahTeks(string $id, array $teksItems): void
    {
        $this->tulis(array_map(
            fn (array $c) => $c['id'] === $id ? ['teks_items' => array_values($teksItems), 'diperbarui_pada' => now()->toIso8601String()] + $c : $c,
            $this->baca(),
        ));
    }

    public function hapus(string $id): void
    {
        $cover = $this->temukan($id);

        if ($cover === null) {
            return;
        }

        $this->disk()->delete($cover['gambar_path']);
        $sisa = array_values(array_filter($this->baca(), fn (array $c) => $c['id'] !== $id));

        if ($cover['is_default'] && ($pengganti = $this->terbaru($sisa, $cover['tahun'])) !== null) {
            $sisa = array_map(fn (array $c) => $c['id'] === $pengganti ? ['is_default' => true] + $c : $c, $sisa);
        }

        $this->tulis($sisa);
    }

    /**
     * @param  list<array<string, mixed>>  $daftar
     */
    private function terbaru(array $daftar, int $tahun): ?string
    {
        return collect($daftar)->where('tahun', $tahun)->sortByDesc('diperbarui_pada')->first()['id'] ?? null;
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
        return config('sigenrabtor.stub.cover_file');
    }

    private function disk(): Filesystem
    {
        return Storage::disk('local');
    }
}
