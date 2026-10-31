<?php

namespace App\Contracts;

use RuntimeException;

/**
 * Daftar model yang tersedia di satu penyedia AI (PRD F24 "Model"): Ollama — model yang sudah diunduh di server sekolah
 * (/api/tags); API cloud — model yang boleh dipakai akun penyedia (/models). Implementasi: DaftarModelAiHttp (server
 * penyedia) atau StubDaftarModelAi (tiruan untuk tes; config `sigenrabtor.ai.daftar_model`).
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
