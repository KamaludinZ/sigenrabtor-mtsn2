<?php

namespace App\Support\Dokumen;

use App\Contracts\TemplateCoverRepository;
use App\Support\Stub\StubData;
use Illuminate\Support\Facades\Storage;

/**
 * Desain cover yang dipakai pratinjau & cetak (PRD Template Cover): cover default tahun aktif — gambar lewat rute
 * non-admin `dokumen.cover.gambar` dan kotak teks yang sudah dirapikan — atau, tanpa template, bingkai polos dengan
 * susunan teks bawaan. Isinya diisi pemanggil: cover kegiatan (TeksCover::nilai) atau cover buku (BukuDokumen::nilaiCover).
 */
final class CoverDokumen
{
    /**
     * @return array{gambar: string|null, teks: list<array<string, mixed>>}
     */
    public static function desain(?int $tahun = null): array
    {
        $cover = self::bawaan($tahun);

        return [
            'gambar' => $cover ? route('dokumen.cover.gambar', ['cover' => $cover['id'], 'v' => $cover['diperbarui_pada']]) : null,
            'teks' => TeksCover::rapikan($cover['teks_items'] ?? TeksCover::bawaan()),
        ];
    }

    /**
     * Desain cover untuk dokumen satu kegiatan (PRD §9 Format Seragam & §10 Template Cover): cover pilihan template
     * (PilihanTemplate::untukKegiatan — khusus leading lalu per jenis kegiatan), lalu cover tahun itu yang khusus untuk
     * leading × sumber dana kegiatan (field leading/sumber_dana template cover; leading saja atau sumber saja juga cocok),
     * lalu cover default / bingkai bawaan (`desain()`). `cover_id`/`nama` = cover yang dipakai (null = bawaan).
     *
     * @param  array<string, mixed>  $kegiatan
     * @return array{gambar: string|null, teks: list<array<string, mixed>>, cover_id: string|null, nama: string|null}
     */
    public static function untukKegiatan(array $kegiatan, ?string $sumber = null, ?int $tahun = null): array
    {
        $tahun ??= (int) StubData::tahunAktif()['tahun'];
        $repo = app(TemplateCoverRepository::class);
        $pilihan = PilihanTemplate::untukKegiatan($kegiatan, $tahun)['cover'];
        $cover = $pilihan !== null ? $repo->temukan($pilihan) : null;
        if ($cover === null) {
            $leading = $kegiatan['leading'] ?? null;
            $cocok = array_filter($repo->semua($tahun), fn (array $c) => ($c['leading'] ?? null) !== null || ($c['sumber_dana'] ?? null) !== null);
            $nilai = fn (array $c) => (($c['leading'] ?? null) === $leading ? 2 : (($c['leading'] ?? null) === null ? 0 : -9)) + (($c['sumber_dana'] ?? null) === $sumber ? 1 : (($c['sumber_dana'] ?? null) === null ? 0 : -9));
            usort($cocok, fn (array $a, array $b) => $nilai($b) <=> $nilai($a));
            $cover = ($cocok[0] ?? null) !== null && $nilai($cocok[0]) > 0 ? $cocok[0] : null;
        }
        if ($cover === null) {
            $bawaan = self::bawaan($tahun);

            return self::desain($tahun) + ['cover_id' => $bawaan['id'] ?? null, 'nama' => $bawaan['nama'] ?? null];
        }

        return [
            'gambar' => route('dokumen.cover.gambar', ['cover' => $cover['id'], 'v' => $cover['diperbarui_pada']]),
            'teks' => TeksCover::rapikan($cover['teks_items'] ?? TeksCover::bawaan()),
            'cover_id' => $cover['id'],
            'nama' => $cover['nama'],
        ];
    }

    /** Data URI gambar satu template cover (null bila tidak ada / berkas hilang). */
    public static function gambarDataCover(?string $id): ?string
    {
        $cover = $id !== null ? app(TemplateCoverRepository::class)->temukan($id) : null;
        $path = $cover['gambar_path'] ?? null;
        if ($path === null || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        return 'data:'.(Storage::disk('local')->mimeType($path) ?: 'image/png').';base64,'.base64_encode((string) Storage::disk('local')->get($path));
    }

    /**
     * Data URI gambar cover default tahun itu (untuk dokumen yang dirender di luar peramban, mis. PDF server); null bila
     * tanpa template atau berkasnya hilang.
     */
    public static function gambarData(?int $tahun = null): ?string
    {
        $path = self::bawaan($tahun)['gambar_path'] ?? null;
        if ($path === null || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        return 'data:'.(Storage::disk('local')->mimeType($path) ?: 'image/png').';base64,'.base64_encode((string) Storage::disk('local')->get($path));
    }

    /** @return array<string, mixed>|null Cover default tahun itu (tahun aktif bila null). */
    private static function bawaan(?int $tahun): ?array
    {
        $tahun ??= StubData::tahunAktif()['tahun'];

        return collect(app(TemplateCoverRepository::class)->semua($tahun))->firstWhere('is_default', true);
    }
}
