<?php

namespace App\Support\Akun;

use App\Contracts\PenggunaRepository;
use App\Support\MasterKode;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Hak akses per peran (PRD §8 "Peran Pengguna", §15; peran dari PeranAkun) — satu-satunya pemeriksa hak umum yang tidak
 * bergantung pada satu kegiatan (hak atas kegiatan tertentu tetap AksesKegiatan/KunciKegiatan; atas dokumen
 * OtorisasiDokumen). `HAK` kunci → [label, peran yang boleh]; syarat tambahan: akun di sesi harus aktif (`nonaktif`),
 * `buat_kegiatan` bagi akun leading butuh ≥ 1 leading aktif tertaut.
 * - `alasan($pengguna, $hak)` = null (boleh) atau pesan siap tampil (`PESAN_*`); `boleh()`, `pastikan()` →
 *   AuthorizationException; `daftar($pengguna)` = semua hak {label, boleh, alasan} (endpoint `akun.hak`).
 * Dipakai middleware HanyaAdmin (`area_admin`), menu samping (Navigation), dan KeputusanPendaftaran (`putuskan_permohonan`).
 */
final class HakAkses
{
    public const HAK = [
        'area_admin' => ['Membuka halaman admin (pengaturan, master kode, pemeriksaan, monitoring)', ['admin']],
        'kelola_pengaturan' => ['Mengubah pengaturan madrasah, tahun anggaran, template & AI', ['admin']],
        'kelola_master' => ['Mengunggah & menyalin master kode', ['admin']],
        'kelola_akun' => ['Menambah & mengubah akun dan leading', ['admin']],
        'putuskan_permohonan' => ['Menyetujui/menolak permohonan akun', ['admin']],
        'periksa_dokumen' => ['Menyetujui, mengembalikan, & membuka kunci dokumen', ['admin']],
        'monitoring' => ['Melihat & mengekspor monitoring anggaran', ['admin']],
        'buat_kegiatan' => ['Membuat kegiatan baru', ['admin', 'leading']],
        'susun_dokumen' => ['Menyusun, mencetak, & mengunduh dokumen kegiatan', ['admin', 'leading']],
        'pakai_ai' => ['Memakai bantuan AI', ['admin', 'leading']],
    ];

    public const PESAN_TAMU = 'Masuk dulu untuk melanjutkan.';

    public const PESAN_NONAKTIF = 'Akun Anda nonaktif — hubungi admin madrasah.';

    public const PESAN_ADMIN = 'Halaman ini khusus admin.';

    public const PESAN_TANPA_LEADING = 'Akun Anda belum tertaut ke leading aktif — minta admin menautkannya.';

    /** @param  array{id?: int|null, role?: string, leadings?: list<string>}|null  $pengguna */
    public static function alasan(?array $pengguna, string $hak): ?string
    {
        $aturan = self::HAK[$hak] ?? throw new \InvalidArgumentException("Hak {$hak} tidak dikenal.");
        if ($pengguna === null || ! isset($pengguna['role'])) {
            return self::PESAN_TAMU;
        }
        if (isset($pengguna['id']) && (app(PenggunaRepository::class)->temukan((int) $pengguna['id'])['is_active'] ?? true) === false) {
            return self::PESAN_NONAKTIF;
        }
        if (! in_array($pengguna['role'], $aturan[1], true)) {
            return $aturan[1] === ['admin'] ? self::PESAN_ADMIN : 'Peran Anda tidak boleh: '.mb_strtolower($aturan[0]).'.';
        }
        if ($hak === 'buat_kegiatan' && $pengguna['role'] === 'leading'
            && array_intersect($pengguna['leadings'] ?? [], array_column(MasterKode::leadings(), 'kode')) === []) {
            return self::PESAN_TANPA_LEADING;
        }

        return null;
    }

    /** @param  array<string, mixed>|null  $pengguna */
    public static function boleh(?array $pengguna, string $hak): bool
    {
        return self::alasan($pengguna, $hak) === null;
    }

    /**
     * @param  array<string, mixed>|null  $pengguna
     *
     * @throws AuthorizationException
     */
    public static function pastikan(?array $pengguna, string $hak): void
    {
        if (($alasan = self::alasan($pengguna, $hak)) !== null) {
            throw new AuthorizationException($alasan);
        }
    }

    /**
     * @param  array<string, mixed>|null  $pengguna
     * @return array<string, array{label: string, boleh: bool, alasan: string|null}>
     */
    public static function daftar(?array $pengguna): array
    {
        $hasil = [];
        foreach (self::HAK as $hak => [$label]) {
            $alasan = self::alasan($pengguna, $hak);
            $hasil[$hak] = ['label' => $label, 'boleh' => $alasan === null, 'alasan' => $alasan];
        }

        return $hasil;
    }
}
