<?php

namespace App\Support\Stub;

use App\Contracts\PengaturanAiRepository;
use App\Contracts\PenggunaRepository;

/**
 * Data tiruan pengaturan admin (PRD Fase 5–7) yang belum punya layar sendiri: akun, template dokumen, dan pengaturan
 * AI. Task backend menggantinya dengan tabel users, cover_templates,
 * document_templates, daftar_isi_templates, page_number_settings, dan ai_settings tanpa mengubah bentuk array.
 */
class StubPengaturan
{
    /**
     * Akun aplikasi (PenggunaRepository, aktif & nonaktif): akun 1 & 2 = persona sakelar "lihat sebagai".
     *
     * @return list<array{id: int, name: string, email: string, role: string, nip: string|null, jabatan: string|null, leadings: list<string>, is_active: bool, terakhir_masuk: string|null, dibuat_pada: string|null}>
     */
    public static function pengguna(): array
    {
        return app(PenggunaRepository::class)->semua();
    }

    /**
     * Template dokumen tahun aktif: belum ada yang diunggah, dokumen memakai susunan baku aplikasi.
     *
     * @return array{cover: int, kak: int, daftar_isi: int, nomor_halaman: array{format: string, sampul_tanpa_nomor: bool}}
     */
    public static function templateDokumen(): array
    {
        return ['cover' => 0, 'kak' => 0, 'daftar_isi' => 0, 'nomor_halaman' => ['format' => 'Halaman {x} dari {y}', 'sampul_tanpa_nomor' => true]];
    }

    /**
     * Pengaturan AI (satu baris) dari PengaturanAiRepository; `model`, `base_url`, `ada_kunci` = konfigurasi penyedia aktif
     * (null bila belum dipilih — "Buat dengan AI" memakai usulan tiruan).
     *
     * @return array{provider_aktif: string|null, provider_cadangan: string|null, model: string|null, base_url: string|null, ada_kunci: bool, penyedia: array<string, array<string, mixed>>, batas_per_user_per_hari: int, diperbarui_pada: string|null}
     */
    public static function pengaturanAi(): array
    {
        $ai = app(PengaturanAiRepository::class)->ambil();
        $aktif = $ai['provider_aktif'] !== null ? ($ai['penyedia'][$ai['provider_aktif']] ?? []) : [];

        return [
            'model' => filled($aktif['model'] ?? null) ? $aktif['model'] : null,
            'base_url' => filled($aktif['base_url'] ?? null) ? $aktif['base_url'] : null,
            'ada_kunci' => (bool) ($aktif['ada_kunci'] ?? false),
        ] + $ai;
    }
}
