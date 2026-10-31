<?php

namespace App\Repositories;

use App\Contracts\TemplateDokumenRepository;
use App\Models\TemplateDokumen;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Master .docx di PostgreSQL (tabel document_templates) + berkas di disk "local" (folder FOLDER, privat; diunduh lewat
 * rute pengaturan.template.master.unduh), padanan StubTemplateDokumenRepository: default lebih dulu lalu terbaru,
 * template pertama pemilik (`kode_leading`, null = umum) × jenis pada tahun itu — atau yang diminta `default` — menjadi
 * default, catatan versi & `aktif` (migrasi 000050), menghapus default memindahkan default ke template terbaru berikutnya
 * pemilik × jenis itu (indeks unik document_templates_satu_default tidak pernah dilanggar). Variabel hanya
 * yang terdaftar — dijaga juga trigger periksa_variabel_template.
 */
class EloquentTemplateDokumenRepository implements TemplateDokumenRepository
{
    public const FOLDER = 'template/master';

    public function semua(int $tahun): array
    {
        return TemplateDokumen::query()->where('tahun_anggaran', $tahun)
            ->orderByDesc('is_default')->orderByDesc('updated_at')->orderBy('id')
            ->get()->map($this->bentuk(...))->all();
    }

    public function temukan(string $id): ?array
    {
        $template = Str::isUuid($id) ? TemplateDokumen::query()->find($id) : null;

        return $template ? $this->bentuk($template) : null;
    }

    public function tambah(int $tahun, string $nama, string $jenis, UploadedFile $berkas, array $variabel, array $atribut = []): string
    {
        $id = (string) Str::uuid();
        $path = $berkas->storeAs(self::FOLDER, "{$id}.docx", 'local');
        $leading = filled($atribut['leading'] ?? null) ? (string) $atribut['leading'] : null;

        DB::transaction(function () use ($id, $tahun, $nama, $jenis, $berkas, $variabel, $path, $leading, $atribut) {
            $pemilik = fn () => TemplateDokumen::query()->where('tahun_anggaran', $tahun)->where('jenis', $jenis)
                ->when($leading === null, fn ($q) => $q->whereNull('kode_leading'), fn ($q) => $q->where('kode_leading', $leading));
            $adaDefault = $pemilik()->where('is_default', true)->lockForUpdate()->exists();
            $default = ($atribut['default'] ?? false) === true || ! $adaDefault;
            if ($default && $adaDefault) {
                $pemilik()->where('is_default', true)->update(['is_default' => false]);
            }
            TemplateDokumen::query()->create([
                'id' => $id, 'tahun_anggaran' => $tahun, 'kode_leading' => $leading, 'nama' => $nama, 'jenis' => $jenis, 'file_path' => $path,
                'nama_berkas' => $berkas->getClientOriginalName(), 'variabel' => array_values($variabel), 'is_default' => $default,
                'catatan' => filled($atribut['catatan'] ?? null) ? (string) $atribut['catatan'] : null,
            ]);
        });

        return $id;
    }

    public function ubah(string $id, string $nama, ?string $catatan): void
    {
        $template = (Str::isUuid($id) ? TemplateDokumen::query()->find($id) : null) ?? throw new RuntimeException('Template tidak ditemukan.');
        $template->update(['nama' => $nama, 'catatan' => $catatan]);
    }

    public function jadikanDefault(string $id): void
    {
        $template = (Str::isUuid($id) ? TemplateDokumen::query()->find($id) : null) ?? throw new RuntimeException('Template tidak ditemukan.');
        DB::transaction(function () use ($template) {
            // Lepas default lama dulu: indeks unik document_templates_satu_default tidak tertunda.
            TemplateDokumen::query()->where('tahun_anggaran', $template->tahun_anggaran)->where('jenis', $template->jenis)
                ->when($template->kode_leading === null, fn ($q) => $q->whereNull('kode_leading'), fn ($q) => $q->where('kode_leading', $template->kode_leading))
                ->where('is_default', true)->whereKeyNot($template->id)->update(['is_default' => false]);
            $template->update(['is_default' => true]);
        });
    }

    public function ubahStatus(string $id, bool $aktif): void
    {
        $template = (Str::isUuid($id) ? TemplateDokumen::query()->find($id) : null) ?? throw new RuntimeException('Template tidak ditemukan.');
        DB::transaction(function () use ($template, $aktif) {
            $pemilik = fn () => TemplateDokumen::query()->where('tahun_anggaran', $template->tahun_anggaran)->where('jenis', $template->jenis)
                ->when($template->kode_leading === null, fn ($q) => $q->whereNull('kode_leading'), fn ($q) => $q->where('kode_leading', $template->kode_leading));
            $template->update(['aktif' => $aktif, 'is_default' => $aktif && $template->is_default]);
            if (! $pemilik()->where('is_default', true)->lockForUpdate()->exists()) {
                $pemilik()->where('aktif', true)->orderByRaw('id = ? DESC', [$template->id])->orderByDesc('updated_at')->first()?->update(['is_default' => true]);
            }
        });
    }

    public function hapus(string $id): void
    {
        $template = Str::isUuid($id) ? TemplateDokumen::query()->find($id) : null;
        if ($template === null) {
            return;
        }

        DB::transaction(function () use ($template) {
            $template->delete();
            if ($template->is_default) {
                TemplateDokumen::query()->where('tahun_anggaran', $template->tahun_anggaran)->where('jenis', $template->jenis)
                    ->when($template->kode_leading === null, fn ($q) => $q->whereNull('kode_leading'), fn ($q) => $q->where('kode_leading', $template->kode_leading))
                    ->orderByDesc('updated_at')->first()?->update(['is_default' => true]);
            }
        });
        Storage::disk('local')->delete($template->file_path);
    }

    /** @return array<string, mixed> */
    private function bentuk(TemplateDokumen $t): array
    {
        return [
            'id' => $t->id,
            'tahun' => $t->tahun_anggaran,
            'nama' => $t->nama,
            'jenis' => $t->jenis,
            'file_path' => $t->file_path,
            'nama_berkas' => $t->nama_berkas,
            'variabel' => $t->variabel,
            'is_default' => $t->is_default,
            'leading' => $t->kode_leading,
            'catatan' => $t->catatan,
            'aktif' => (bool) ($t->aktif ?? true),
            'diperbarui_pada' => $t->updated_at->setTimezone(config('app.timezone'))->toIso8601String(),
        ];
    }
}
