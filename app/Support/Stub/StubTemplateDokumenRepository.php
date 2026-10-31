<?php

namespace App\Support\Stub;

use App\Contracts\TemplateDokumenRepository;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

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

    public function tambah(int $tahun, string $nama, string $jenis, UploadedFile $berkas, array $variabel, array $atribut = []): string
    {
        $id = (string) Str::uuid();
        $path = $berkas->storeAs(Str::beforeLast($this->berkas(), '.json'), "{$id}.docx", 'local');
        $leading = filled($atribut['leading'] ?? null) ? (string) $atribut['leading'] : null;
        $semua = $this->baca();
        $sama = fn (array $t) => $t['tahun'] === $tahun && $t['jenis'] === $jenis && ($t['leading'] ?? null) === $leading;
        $default = ($atribut['default'] ?? false) === true || ! collect($semua)->contains(fn (array $t) => $sama($t) && $t['is_default']);
        if ($default) {
            $semua = array_map(fn (array $t) => $sama($t) ? ['is_default' => false] + $t : $t, $semua);
        }
        $semua[] = [
            'id' => $id,
            'tahun' => $tahun,
            'nama' => $nama,
            'jenis' => $jenis,
            'file_path' => $path,
            'nama_berkas' => $berkas->getClientOriginalName(),
            'variabel' => array_values($variabel),
            'jumlah' => array_map(fn (string $v) => max(1, (int) ($atribut['jumlah'][$v] ?? 1)), array_combine(array_values($variabel), array_values($variabel))),
            'is_default' => $default,
            'leading' => $leading,
            'catatan' => filled($atribut['catatan'] ?? null) ? (string) $atribut['catatan'] : null,
            'aktif' => true,
            'dibuat_oleh' => $atribut['oleh']['id'] ?? null,
            'pengunggah' => $atribut['oleh']['nama'] ?? null,
            'diperbarui_pada' => now()->toIso8601String(),
        ];
        $this->tulis($semua);

        return $id;
    }

    public function ubah(string $id, string $nama, ?string $catatan): void
    {
        $semua = $this->baca();
        if (! collect($semua)->contains('id', $id)) {
            throw new RuntimeException('Template tidak ditemukan.');
        }
        $this->tulis(array_map(fn (array $t) => $t['id'] === $id ? ['nama' => $nama, 'catatan' => $catatan, 'diperbarui_pada' => now()->toIso8601String()] + $t : $t, $semua));
    }

    public function jadikanDefault(string $id): void
    {
        $template = $this->temukan($id) ?? throw new RuntimeException('Template tidak ditemukan.');
        $this->tulis(array_map(function (array $t) use ($template) {
            $sama = $t['tahun'] === $template['tahun'] && $t['jenis'] === $template['jenis'] && $t['leading'] === $template['leading'];

            return $sama ? ['is_default' => $t['id'] === $template['id']] + $t : $t;
        }, $this->baca()));
    }

    public function ubahStatus(string $id, bool $aktif): void
    {
        $template = $this->temukan($id) ?? throw new RuntimeException('Template tidak ditemukan.');
        $sama = fn (array $t) => $t['tahun'] === $template['tahun'] && $t['jenis'] === $template['jenis'] && $t['leading'] === $template['leading'];
        $semua = array_map(fn (array $t) => $t['id'] === $id ? ['aktif' => $aktif, 'is_default' => $aktif && $t['is_default'], 'diperbarui_pada' => now()->toIso8601String()] + $t : $t, $this->baca());
        if (! collect($semua)->contains(fn (array $t) => $sama($t) && $t['is_default'])) {
            $calon = collect($semua)->filter(fn (array $t) => $sama($t) && $t['aktif'] && ($aktif || $t['id'] !== $id))
                ->sortByDesc(fn (array $t) => [$t['id'] === $id, $t['diperbarui_pada']])->first();
            if ($calon !== null) {
                $semua = array_map(fn (array $t) => $t['id'] === $calon['id'] ? ['is_default' => true] + $t : $t, $semua);
            }
        }
        $this->tulis($semua);
    }

    public function hapus(string $id): void
    {
        $template = $this->temukan($id);

        if ($template === null) {
            return;
        }

        $this->disk()->delete($template['file_path']);
        $sisa = array_values(array_filter($this->baca(), fn (array $t) => $t['id'] !== $id));
        $pengganti = collect($sisa)->where('tahun', $template['tahun'])->where('jenis', $template['jenis'])
            ->filter(fn (array $t) => $t['leading'] === $template['leading'])->sortByDesc('diperbarui_pada')->first()['id'] ?? null;

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

        // Catatan lama tanpa pemilik/catatan/status = umum, tanpa catatan, aktif.
        return is_array($isi) ? array_map(fn (array $t) => $t + ['leading' => null, 'catatan' => null, 'aktif' => true, 'dibuat_oleh' => null, 'pengunggah' => null,
            'jumlah' => array_fill_keys((array) ($t['variabel'] ?? []), 1)], array_values($isi)) : [];
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
