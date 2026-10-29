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
