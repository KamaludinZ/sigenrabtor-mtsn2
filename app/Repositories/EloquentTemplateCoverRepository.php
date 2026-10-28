<?php

namespace App\Repositories;

use App\Contracts\TemplateCoverRepository;
use App\Models\TemplateCover;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Cover di PostgreSQL (tabel cover_templates) + gambar di disk "local" (folder FOLDER, privat; dilayani rute gambar
 * cover), padanan StubTemplateCoverRepository: default lebih dulu lalu terbaru, cover pertama tahun itu menjadi default,
 * menghapus default memindahkan default ke cover terbaru berikutnya (indeks cover_templates_satu_default tidak pernah
 * dilanggar). Dimensi acuan (lebar × tinggi piksel) dibaca dari gambar; kotak teks dijaga trigger periksa_teks_cover.
 */
class EloquentTemplateCoverRepository implements TemplateCoverRepository
{
    public const FOLDER = 'template/cover';

    /** Urutan kunci satu kotak teks (TeksCover::rapikan). */
    private const KUNCI_KOTAK = ['field' => null, 'x' => null, 'y' => null, 'width' => null, 'font_size' => null, 'align' => null, 'weight' => null, 'color' => null];

    public function semua(int $tahun): array
    {
        return TemplateCover::query()->where('tahun_anggaran', $tahun)
            ->orderByDesc('is_default')->orderByDesc('updated_at')->orderBy('id')
            ->get()->map($this->bentuk(...))->all();
    }

    public function temukan(string $id): ?array
    {
        $cover = Str::isUuid($id) ? TemplateCover::query()->find($id) : null;

        return $cover ? $this->bentuk($cover) : null;
    }

    public function tambah(int $tahun, string $nama, UploadedFile $gambar, array $teksItems): string
    {
        $id = (string) Str::uuid();
        $ekstensi = strtolower($gambar->guessExtension() ?: $gambar->getClientOriginalExtension() ?: 'png');
        $path = $gambar->storeAs(self::FOLDER, "{$id}.{$ekstensi}", 'local');
        [$lebar, $tinggi] = getimagesize($gambar->getRealPath()) ?: [0, 0];

        DB::transaction(function () use ($id, $tahun, $nama, $path, $lebar, $tinggi, $teksItems) {
            $adaDefault = TemplateCover::query()->where('tahun_anggaran', $tahun)->whereNull('kode_leading')->whereNull('sumber_dana')->lockForUpdate()->exists();
            TemplateCover::query()->create([
                'id' => $id, 'tahun_anggaran' => $tahun, 'nama' => $nama, 'gambar_path' => $path,
                'lebar' => (int) $lebar, 'tinggi' => (int) $tinggi, 'teks_items' => array_values($teksItems), 'is_default' => ! $adaDefault,
            ]);
        });

        return $id;
    }

    public function ubahTeks(string $id, array $teksItems): void
    {
        if (Str::isUuid($id)) {
            TemplateCover::query()->find($id)?->update(['teks_items' => array_values($teksItems)]);
        }
    }

    public function hapus(string $id): void
    {
        $cover = Str::isUuid($id) ? TemplateCover::query()->find($id) : null;
        if ($cover === null) {
            return;
        }

        DB::transaction(function () use ($cover) {
            $cover->delete();
            if ($cover->is_default) {
                TemplateCover::query()->where('tahun_anggaran', $cover->tahun_anggaran)
                    ->when($cover->kode_leading === null, fn ($q) => $q->whereNull('kode_leading'), fn ($q) => $q->where('kode_leading', $cover->kode_leading))
                    ->when($cover->sumber_dana === null, fn ($q) => $q->whereNull('sumber_dana'), fn ($q) => $q->where('sumber_dana', $cover->sumber_dana))
                    ->orderByDesc('updated_at')->first()?->update(['is_default' => true]);
            }
        });
        Storage::disk('local')->delete($cover->gambar_path);
    }

    /** @return array<string, mixed> */
    private function bentuk(TemplateCover $c): array
    {
        return [
            'id' => $c->id,
            'tahun' => $c->tahun_anggaran,
            'nama' => $c->nama,
            'gambar_path' => $c->gambar_path,
            'lebar' => $c->lebar,
            'tinggi' => $c->tinggi,
            // jsonb mengurutkan ulang kunci objek: kembalikan urutan baku kotak (field, x, y, width, …) seperti tiruan.
            'teks_items' => array_map(fn (array $k) => array_merge(array_intersect_key(self::KUNCI_KOTAK, $k), $k), $c->teks_items ?? []),
            'is_default' => $c->is_default,
            'leading' => $c->kode_leading,
            'sumber_dana' => $c->sumber_dana,
            'diperbarui_pada' => $c->updated_at->setTimezone(config('app.timezone'))->toIso8601String(),
        ];
    }
}
