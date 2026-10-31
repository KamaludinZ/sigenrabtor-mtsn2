<?php

namespace App\Support\Kegiatan;

/**
 * Jenis kegagalan penyedia AI dan pesannya (PRD §20–21): judul, pesan untuk pengguna, saran untuk admin, dan apakah
 * mencoba lagi masuk akal. Dipakai GagalPenyediaAi, rantai penyedia (RantaiPenyediaAi), dan simulasi kegagalan di
 * Pengaturan AI.
 */
final class KegagalanAi
{
    /** Jenis → [judul, pesan pengguna, saran admin, bisa coba lagi]. */
    public const JENIS = [
        'tidak_terhubung' => ['Server AI tidak bisa dihubungi', 'Server AI tidak bisa dihubungi. Coba lagi beberapa saat lagi.', 'Periksa alamat server dan jaringan madrasah di tab Penyedia.', true],
        'waktu_habis' => ['Waktu tunggu habis', 'AI terlalu lama menjawab. Coba lagi, atau persingkat instruksi.', 'Naikkan batas waktu server sekolah atau pakai model yang lebih kecil.', true],
        'kunci_ditolak' => ['Kunci API ditolak', 'Penyedia AI menolak permintaan ini. Hubungi admin madrasah.', 'Kunci API tidak sah atau kedaluwarsa — ganti kunci di tab Penyedia.', false],
        'kuota_penyedia' => ['Kuota penyedia habis', 'Penyedia AI sedang membatasi permintaan. Coba lagi nanti.', 'Saldo/kuota akun penyedia cloud habis atau permintaan terlalu rapat.', true],
        'penyedia_sibuk' => ['Penyedia AI sedang sibuk', 'Layanan AI sedang sibuk atau terganggu. Coba lagi beberapa saat lagi.', 'Gangguan di server penyedia (beban tinggi atau galat internal; di server sekolah bisa karena memori tidak cukup untuk model). Biasanya pulih sendiri — pasang penyedia cadangan agar permintaan tetap terlayani.', true],
        'model_tidak_ada' => ['Model tidak ditemukan', 'Model AI yang diatur tidak tersedia. Hubungi admin madrasah.', 'Model belum diunduh di server (ollama pull) atau namanya salah — periksa tab Model & parameter.', false],
        'permintaan_ditolak' => ['Permintaan ditolak penyedia', 'Penyedia AI menolak permintaan ini. Coba ubah instruksi, atau hubungi admin madrasah.', 'Penyedia menolak isi atau parameter permintaan (mis. filter konten, parameter yang tidak didukung model, atau pengaturan akun penyedia). Lihat kode galat di log aplikasi, lalu coba model lain.', false],
        'jawaban_rusak' => ['Jawaban tidak terbaca', 'AI memberi jawaban yang tidak bisa dibaca. Coba lagi.', 'Model mungkin terlalu kecil untuk format jawaban; coba model lain.', true],
    ];

    public static function judul(string $jenis): string
    {
        return self::JENIS[$jenis][0] ?? 'Kegagalan AI';
    }

    public static function pesan(string $jenis): string
    {
        return self::JENIS[$jenis][1] ?? 'AI gagal menjawab. Coba lagi.';
    }

    public static function saranAdmin(string $jenis): string
    {
        return self::JENIS[$jenis][2] ?? 'Periksa Pengaturan AI.';
    }

    public static function bisaCobaLagi(string $jenis): bool
    {
        return self::JENIS[$jenis][3] ?? true;
    }
}
