<?php

namespace App\Support\Stub;

/**
 * Data tiruan pengaturan admin (PRD Fase 5–7) yang belum punya layar sendiri: daftar tahun anggaran, akun, template
 * dokumen, dan pengaturan AI. Task backend menggantinya dengan tabel tahun_anggaran, users, cover_templates,
 * document_templates, daftar_isi_templates, page_number_settings, dan ai_settings tanpa mengubah bentuk array.
 */
class StubPengaturan
{
    /**
     * Tahun anggaran yang pernah dibuat (tepat satu aktif); tahun lalu dikunci. SK RKTM tahun aktif belum diisi.
     *
     * @return list<array{id: int, tahun: int, is_active: bool, is_locked: bool, no_sk_rktm: string|null, tanggal_sk_rktm: string|null}>
     */
    public static function tahunAnggaran(): array
    {
        $aktif = StubData::tahunAktif();

        return [
            ['id' => $aktif['id'], 'tahun' => $aktif['tahun'], 'is_active' => true, 'is_locked' => $aktif['is_locked'], 'no_sk_rktm' => null, 'tanggal_sk_rktm' => null],
            ['id' => 2, 'tahun' => $aktif['tahun'] - 1, 'is_active' => false, 'is_locked' => true, 'no_sk_rktm' => 'Contoh 001/SK/RKTM/'.($aktif['tahun'] - 1), 'tanggal_sk_rktm' => ($aktif['tahun'] - 1).'-01-06'],
        ];
    }

    /**
     * Akun aplikasi (nama fiktif): persona yang sama dengan sakelar "lihat sebagai".
     *
     * @return list<array{id: int, name: string, role: string, jabatan: string, leadings: list<string>, is_active: bool}>
     */
    public static function pengguna(): array
    {
        return [
            ['id' => 1, 'name' => 'Muslimin, M.Pd.', 'role' => 'leading', 'jabatan' => 'Tim Penjamin Mutu', 'leadings' => ['1.6'], 'is_active' => true],
            ['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin', 'jabatan' => 'Admin Aplikasi', 'leadings' => [], 'is_active' => true],
        ];
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
     * Pengaturan AI (satu baris): penyedia belum dipilih, sehingga "Buat dengan AI" memakai usulan tiruan.
     *
     * @return array{provider_aktif: string|null, provider_cadangan: string|null, model: string|null, batas_per_user_per_hari: int}
     */
    public static function pengaturanAi(): array
    {
        return ['provider_aktif' => null, 'provider_cadangan' => null, 'model' => null, 'batas_per_user_per_hari' => 20];
    }
}
