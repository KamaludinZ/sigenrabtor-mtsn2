<?php

namespace App\Contracts;

use RuntimeException;

/**
 * Pengaturan AI satu baris (PRD tabel ai_settings, F24): penyedia aktif & cadangan, konfigurasi per penyedia
 * (`penyedia.{kunci}`: base_url, model, batas_waktu, ada_kunci, kunci_akhir, kunci_terenkripsi), parameter jawaban
 * (`parameter`: suhu, maks_token, jumlah_versi — ParameterAi), dan batas permintaan per akun per hari; `diperbarui_pada`
 * untuk mengenali perubahan dari tab/admin lain.
 */
interface PengaturanAiRepository
{
    /**
     * @return array{provider_aktif: string|null, provider_cadangan: string|null, penyedia: array<string, array<string, mixed>>, parameter: array<string, mixed>, batas_per_user_per_hari: int, diperbarui_pada: string|null}
     */
    public function ambil(): array;

    /**
     * Simpan seluruh pengaturan (bentuk sama dengan ambil(), tanpa diperbarui_pada).
     *
     * @param  array<string, mixed>  $data
     *
     * @throws RuntimeException Penyimpanan gagal.
     */
    public function simpan(array $data): void;
}
