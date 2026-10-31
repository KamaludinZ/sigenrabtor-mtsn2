<?php

namespace App\Support\Dokumen;

use App\Contracts\TemplateCoverRepository;
use App\Contracts\TemplateDokumenRepository;
use App\Support\Rab\AnggaranKegiatan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Detail satu template Format Seragam (PRD §9; endpoint admin `pengaturan.format.template.detail`) — satu-satunya penyusunnya:
 * `untuk($id, $kegiatan?, $sumber?)` mencari master KAK/TOR/RAB (`TemplateDokumenRepository`) lalu cover
 * (`TemplateCoverRepository`); null = tidak ada.
 * - master: identitas, default, berkas (ada, ukuran, nama), `variabel` dibaca langsung dari berkas (`PembacaTemplateDocx::
 *   variabel` → `AnalisisTemplate`: terdaftar, saran salah tulis, keterangan, kelompok) + `tidak_terdaftar`, `paragraf`
 *   (`PembacaTemplateDocx::paragraf`; berkas hilang/rusak → null + `galat_berkas`), `bisa_dipilih` (keluarga KAK/TOR untuk
 *   pilihan template), `dipakai` (`DaftarTemplate::pemakaian`), tautan unduh;
 * - cover: identitas, default, khusus leading/sumber, ukuran gambar & `rasio_a4` (tinggi/lebar ≈ √2), `kotak`
 *   (`TeksCover::rapikan` + label `TeksCover::FIELD`), berkas gambar ada, `dipakai`, tautan gambar;
 * - `pratinjau` (hanya bila `$kegiatan` diberikan; sumber harus dipilih kegiatan itu → ValidationException `sumber`): master
 *   = paragraf terisi `PratinjauTemplate::susun(…, NilaiVariabel::untuk)` + `kosong` (variabel tanpa nilai); cover = nilai
 *   tiap kotak dari `TeksCover::nilai`.
 */
final class DetailTemplate
{
    /** Selisih rasio tinggi/lebar yang masih dianggap A4 (√2 ≈ 1,414). */
    public const TOLERANSI_A4 = 0.02;

    /**
     * @param  array<string, mixed>|null  $kegiatan
     * @return array<string, mixed>|null
     *
     * @throws ValidationException Sumber dana tidak dipilih kegiatan itu.
     */
    public static function untuk(string $id, ?array $kegiatan = null, ?string $sumber = null): ?array
    {
        if ($kegiatan !== null && $sumber !== null && ! array_key_exists($sumber, AnggaranKegiatan::perSumber($kegiatan))) {
            throw ValidationException::withMessages(['sumber' => "Sumber dana {$sumber} tidak dipilih untuk kegiatan contoh ini."]);
        }
        if (($master = app(TemplateDokumenRepository::class)->temukan($id)) !== null) {
            return self::master($master, $kegiatan, $sumber);
        }
        if (($cover = app(TemplateCoverRepository::class)->temukan($id)) !== null) {
            return self::cover($cover, $kegiatan, $sumber);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $m
     * @param  array<string, mixed>|null  $kegiatan
     * @return array<string, mixed>
     */
    private static function master(array $m, ?array $kegiatan, ?string $sumber): array
    {
        $disk = Storage::disk('local');
        $ada = $disk->exists((string) $m['file_path']);
        $variabel = $paragraf = $galat = null;
        if ($ada) {
            try {
                $path = $disk->path((string) $m['file_path']);
                $variabel = AnalisisTemplate::dari(PembacaTemplateDocx::variabel($path))->variabel;
                $paragraf = PembacaTemplateDocx::paragraf($path);
            } catch (RuntimeException $e) {
                $galat = $e->getMessage();
            }
        } else {
            $galat = 'Berkas master tidak ditemukan di penyimpanan — unggah ulang master ini.';
        }
        // Berkas tidak terbaca: daftar variabel tersimpan saat unggah tetap ditampilkan.
        $variabel ??= AnalisisTemplate::dari(array_fill_keys(array_map('strval', $m['variabel'] ?? []), 1))->variabel;
        $nilai = $kegiatan !== null ? NilaiVariabel::untuk($kegiatan, $sumber) : null;

        return [
            'jenis_template' => 'master',
            'id' => (string) $m['id'],
            'tahun' => (int) $m['tahun'],
            'nama' => (string) $m['nama'],
            'jenis' => (string) $m['jenis'],
            'label_jenis' => DokumenKegiatan::JENIS[$m['jenis']] ?? (string) $m['jenis'],
            'default' => (bool) $m['is_default'],
            'aktif' => (bool) ($m['aktif'] ?? true),
            // Siklus hidup: hanya master aktif berjenis KAK/TOR yang bisa dipilih baru (kegiatan, default, Format Seragam).
            'bisa_dipilih' => in_array($m['jenis'], PilihanTemplate::JENIS_KAK, true) && ($m['aktif'] ?? true),
            'berkas' => [
                'nama' => $m['nama_berkas'] ?? null,
                'ada' => $ada,
                'ukuran' => $ada ? (int) $disk->size((string) $m['file_path']) : null,
                'tautan_unduh' => route('pengaturan.template.master.unduh', (string) $m['id']),
            ],
            'galat_berkas' => $galat,
            'variabel' => $variabel,
            'tidak_terdaftar' => count(array_filter($variabel, fn (array $v) => ! $v['terdaftar'])),
            'paragraf' => $paragraf,
            'dipakai' => DaftarTemplate::pemakaian(PilihanTemplate::data((int) $m['tahun']))['kak'][(string) $m['id']] ?? [],
            'pratinjau' => $nilai === null || $paragraf === null ? null : [
                'kegiatan_id' => (int) $kegiatan['id'],
                'paragraf' => PratinjauTemplate::susun($paragraf, $nilai)->paragraf,
                'kosong' => array_values(array_filter(array_column($variabel, 'nama'), fn (string $n) => array_key_exists($n, $nilai) && blank($nilai[$n]))),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $c
     * @param  array<string, mixed>|null  $kegiatan
     * @return array<string, mixed>
     */
    private static function cover(array $c, ?array $kegiatan, ?string $sumber): array
    {
        $lebar = (int) ($c['lebar'] ?? 0);
        $tinggi = (int) ($c['tinggi'] ?? 0);
        $nilai = $kegiatan !== null ? TeksCover::nilai($kegiatan, $sumber) : null;

        return [
            'jenis_template' => 'cover',
            'id' => (string) $c['id'],
            'tahun' => (int) $c['tahun'],
            'nama' => (string) $c['nama'],
            'default' => (bool) $c['is_default'],
            'leading' => $c['leading'] ?? null,
            'sumber_dana' => $c['sumber_dana'] ?? null,
            'gambar' => [
                'lebar' => $lebar,
                'tinggi' => $tinggi,
                'rasio_a4' => $lebar > 0 && abs($tinggi / $lebar - M_SQRT2) <= self::TOLERANSI_A4,
                'ada' => Storage::disk('local')->exists((string) ($c['gambar_path'] ?? '')),
                'tautan' => route('pengaturan.template.cover.gambar', (string) $c['id']),
            ],
            'kotak' => array_map(fn (array $k) => $k + ['label' => TeksCover::FIELD[$k['field']] ?? $k['field']], TeksCover::rapikan($c['teks_items'] ?? [])),
            'dipakai' => DaftarTemplate::pemakaian(PilihanTemplate::data((int) $c['tahun']))['cover'][(string) $c['id']] ?? [],
            'pratinjau' => $nilai === null ? null : [
                'kegiatan_id' => (int) $kegiatan['id'],
                'kotak' => array_map(fn (array $k) => ['field' => $k['field'], 'nilai' => $nilai[$k['field']] ?? null], TeksCover::rapikan($c['teks_items'] ?? [])),
            ],
        ];
    }
}
