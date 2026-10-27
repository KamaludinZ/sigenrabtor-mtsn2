<?php

namespace App\Contracts;

use RuntimeException;

/**
 * Template prompt AI (PRD tabel prompt_templates: bagian, instruksi, gaya_bahasa, is_active): gaya bahasa baku untuk
 * semua bagian dan per bagian {instruksi, gaya_bahasa (null = memakai gaya baku), aktif}. Bagian yang belum pernah diubah
 * tidak tersimpan (PromptAi memakai bawaan). `diperbarui_pada` untuk mengenali perubahan dari tab/admin lain.
 */
interface PromptAiRepository
{
    /**
     * @return array{gaya_bahasa: string|null, bagian: array<string, array{instruksi?: string, gaya_bahasa?: string|null, aktif?: bool}>, diperbarui_pada: string|null}
     */
    public function ambil(): array;

    /**
     * @param  array{gaya_bahasa: string|null, bagian: array<string, array<string, mixed>>}  $data
     *
     * @throws RuntimeException Penyimpanan gagal.
     */
    public function simpan(array $data): void;
}
