<?php

namespace App\Support\Pengaturan;

use Illuminate\Support\Facades\Route;

/**
 * Peta bagian Pengaturan admin (PRD Fase 5–7) untuk halaman Ikhtisar dan kepala tiap halaman pengaturan. Seperti menu
 * samping, bagian yang rutenya belum terdaftar tampil "Segera" dan otomatis aktif begitu halamannya dibuat.
 */
final class BagianPengaturan
{
    /** Kelompok tampilan: kunci → judul. */
    public const KELOMPOK = [
        'dokumen' => 'Tahun & dokumen',
        'madrasah' => 'Data madrasah',
        'kode' => 'Master kode & AI',
    ];

    /**
     * @var list<array{kunci: string, kelompok: string, judul: string, ikon: string, route: string, ringkas: string, butir: list<string>}>
     */
    private const BAGIAN = [
        ['kunci' => 'tahun', 'kelompok' => 'dokumen', 'judul' => 'Tahun Anggaran', 'ikon' => 'calendar', 'route' => 'pengaturan.tahun.index',
            'ringkas' => 'Tahun aktif menentukan tahun di seluruh dokumen, dasar hukum, pejabat, dan master kode.',
            'butir' => ['Buat & aktifkan tepat satu tahun', 'Nomor & tanggal SK RKTM/RKAM', 'Salin master dari tahun lalu', 'Kunci tahun lama']],
        ['kunci' => 'template', 'kelompok' => 'dokumen', 'judul' => 'Template Dokumen', 'ikon' => 'file-text', 'route' => 'pengaturan.template.index',
            'ringkas' => 'Tampilan cover, isi KAK/TOR, daftar isi buku, dan nomor halaman yang dipakai semua kegiatan.',
            'butir' => ['Desain cover + posisi teks', 'Master KAK/TOR .docx ber-variabel', 'Daftar isi per leading & sumber dana', 'Format nomor halaman buku']],
        ['kunci' => 'madrasah', 'kelompok' => 'madrasah', 'judul' => 'Profil & Pejabat', 'ikon' => 'building', 'route' => 'pengaturan.madrasah.index',
            'ringkas' => 'Kop madrasah dan pejabat penanda tangan yang berlaku pada tahun anggaran aktif.',
            'butir' => ['Profil & logo madrasah', 'Kepala Madrasah, pejabat leading, Ketua Komite', 'Dasar hukum & latar belakang baku']],
        ['kunci' => 'leading', 'kelompok' => 'madrasah', 'judul' => 'Leading & User', 'ikon' => 'users', 'route' => 'pengaturan.leading.index',
            'ringkas' => 'Unit penerima mandat beserta jabatan penanda tangannya, dan akun yang tertaut ke leading.',
            'butir' => ['Kode, nama unit, jabatan penanda tangan', 'Akun admin & leading', 'Tautan akun ke leading']],
        ['kunci' => 'master', 'kelompok' => 'kode', 'judul' => 'Master Kode', 'ikon' => 'database', 'route' => 'master.index',
            'ringkas' => 'Kode SK, IKSK, RKTM, EDM, dan 5 Budaya Mutu per tahun dari unggahan Excel, dipakai sebagai pilihan.',
            'butir' => ['Unggah Excel dengan pratinjau', 'Per tahun anggaran']],
        ['kunci' => 'ai', 'kelompok' => 'kode', 'judul' => 'Pengaturan AI', 'ikon' => 'sparkles', 'route' => 'pengaturan.ai.index',
            'ringkas' => 'Penyedia AI untuk tombol "Buat dengan AI"; NIP dan nama pejabat tidak pernah dikirim.',
            'butir' => ['Penyedia aktif & cadangan', 'Model, kunci API, prompt per bagian', 'Batas permintaan per user per hari']],
    ];

    /**
     * @return list<array{kunci: string, kelompok: string, judul: string, ikon: string, route: string, ringkas: string, butir: list<string>, tersedia: bool, url: string|null}>
     */
    public static function semua(): array
    {
        return array_map(function (array $bagian) {
            $tersedia = Route::has($bagian['route']);

            return $bagian + ['tersedia' => $tersedia, 'url' => $tersedia ? route($bagian['route']) : null];
        }, self::BAGIAN);
    }

    /**
     * @return array{kunci: string, kelompok: string, judul: string, ikon: string, route: string, ringkas: string, butir: list<string>, tersedia: bool, url: string|null}|null
     */
    public static function cari(string $kunci): ?array
    {
        return collect(self::semua())->firstWhere('kunci', $kunci);
    }
}
