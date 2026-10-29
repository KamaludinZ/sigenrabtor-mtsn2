<?php

namespace App\Contracts;

/**
 * Pilihan template per tahun anggaran (PRD §9 "Pilihan Template — variasi template sesuai jenis kegiatan/leading"; diatur
 * di Pengaturan › Format Seragam lewat App\Support\Dokumen\PilihanTemplate). Bentuk: {jenis: {kunci jenis kegiatan
 * KonteksAi::jenisKegiatan: {kak: id master KAK/TOR|null, cover: id cover|null}}, leading: list {leading, jenis, kak, cover},
 * diperbarui_pada: string|null}; null = susunan baku / cover default.
 */
interface PilihanTemplateRepository
{
    /** @return array{jenis: array<string, array{kak: string|null, cover: string|null}>, leading: list<array{leading: string, jenis: string, kak: string|null, cover: string|null}>, diperbarui_pada: string|null} */
    public function untuk(int $tahun): array;

    /**
     * Ganti seluruh pilihan tahun itu (sudah dirapikan).
     *
     * @param  array{jenis: array<string, array{kak: string|null, cover: string|null}>, leading: list<array{leading: string, jenis: string, kak: string|null, cover: string|null}>}  $pilihan
     *
     * @throws \RuntimeException Gagal ditulis.
     */
    public function simpan(int $tahun, array $pilihan): void;
}
