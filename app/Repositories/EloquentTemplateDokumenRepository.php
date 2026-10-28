<?php

namespace App\Repositories;

use App\Contracts\TemplateDokumenRepository;
use App\Models\TemplateDokumen;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Master .docx di PostgreSQL (tabel document_templates) + berkas di disk "local" (folder FOLDER, privat; diunduh lewat
 * rute pengaturan.template.master.unduh), padanan StubTemplateDokumenRepository: default lebih dulu lalu terbaru,
 * template pertama suatu jenis pada tahun itu menjadi default, menghapus default memindahkan default ke template
 * terbaru berikutnya jenis itu (indeks unik document_templates_satu_default tidak pernah dilanggar). Variabel hanya
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

    public function tambah(int $tahun, string $nama, string $jenis, UploadedFile $berkas, array $variabel): string
    {
        $id = (string) Str::uuid();
        $path = $berkas->storeAs(self::FOLDER, "{$id}.docx", 'local');

        DB::transaction(function () use ($id, $tahun, $nama, $jenis, $berkas, $variabel, $path) {
            $adaDefault = TemplateDokumen::query()->where('tahun_anggaran', $tahun)->where('jenis', $jenis)->whereNull('kode_leading')->lockForUpdate()->exists();
            TemplateDokumen::query()->create([
                'id' => $id, 'tahun_anggaran' => $tahun, 'nama' => $nama, 'jenis' => $jenis, 'file_path' => $path,
                'nama_berkas' => $berkas->getClientOriginalName(), 'variabel' => array_values($variabel), 'is_default' => ! $adaDefault,
            ]);
        });

        return $id;
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
            'diperbarui_pada' => $t->updated_at->setTimezone(config('app.timezone'))->toIso8601String(),
        ];
    }
}
