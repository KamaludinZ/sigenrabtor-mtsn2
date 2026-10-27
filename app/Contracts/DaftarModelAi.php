<?php

namespace App\Contracts;

use RuntimeException;

/**
 * Daftar model yang tersedia di satu penyedia AI (PRD F24 "Model"): Ollama — model yang sudah diunduh di server sekolah
 * (/api/tags); API cloud — model yang disarankan untuk menyusun kalimat. Tahap frontend memakai daftar tiruan; backend
 * menggantinya dengan permintaan ke penyedia.
 */
interface DaftarModelAi
{
    /**
     * @param  array<string, mixed>  $konfigurasi  Konfigurasi penyedia tersimpan (base_url, kunci, …).
     * @return list<array{nama: string, ukuran: string|null, keterangan: string, disarankan: bool}>
     *
     * @throws RuntimeException Penyedia belum bisa dihubungi (mis. alamat/kunci belum diisi).
     */
    public function daftar(string $penyedia, array $konfigurasi): array;
}
