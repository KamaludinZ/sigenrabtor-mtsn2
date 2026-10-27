<?php

namespace App\Support\Dokumen;

use App\Contracts\TemplateCoverRepository;
use App\Support\Stub\StubData;

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
        $tahun ??= StubData::tahunAktif()['tahun'];
        $cover = collect(app(TemplateCoverRepository::class)->semua($tahun))->firstWhere('is_default', true);

        return [
            'gambar' => $cover ? route('dokumen.cover.gambar', ['cover' => $cover['id'], 'v' => $cover['diperbarui_pada']]) : null,
            'teks' => TeksCover::rapikan($cover['teks_items'] ?? TeksCover::bawaan()),
        ];
    }
}
