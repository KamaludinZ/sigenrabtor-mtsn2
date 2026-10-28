<?php

namespace App\Support\Pengaturan;

use App\Contracts\NomorHalamanRepository;
use App\Contracts\TahunAnggaranRepository;
use App\Support\Dokumen\NomorHalaman;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Simpan & perbarui aturan nomor halaman buku per tahun anggaran di server (PRD §7A, tabel page_number_settings; hanya
 * admin, tahun terkunci hanya bisa dilihat) — dipakai tab Nomor halaman Pengaturan › Template Dokumen. Format diperiksa
 * NomorHalaman::galatFormat (galat `format`), aturan selalu NomorHalaman::rapikan; riwayat `ubah_nomor_halaman` dengan
 * ringkasan perubahan (posisi berlabel).
 */
final class KelolaNomorHalaman
{
    /** Label ringkasan perubahan di riwayat. */
    private const LABEL = [
        'format' => 'Format', 'posisi' => 'Posisi', 'mulai_dari' => 'Nomor pertama', 'sampul_tanpa_nomor' => 'Sampul tanpa nomor',
        'daftar_isi_tanpa_nomor' => 'Daftar isi tanpa nomor', 'hitung_tanpa_nomor' => 'Hitung halaman tanpa nomor', 'urut_mengikuti_daftar' => 'Urut mengikuti daftar isi',
    ];

    /**
     * @param  array<string, mixed>  $aturan  Isian (dirapikan di sini).
     * @param  array{role: string}  $pengguna
     * @return array<string, mixed> Aturan yang tersimpan.
     *
     * @throws ValidationException Format tidak sah (`format`).
     * @throws AuthorizationException Bukan admin atau tahun terkunci.
     */
    public static function simpan(int $tahun, array $aturan, array $pengguna): array
    {
        if ($pengguna['role'] !== 'admin') {
            throw new AuthorizationException('Template dokumen diatur admin.');
        }
        if (collect(app(TahunAnggaranRepository::class)->semua())->firstWhere('tahun', $tahun)['is_locked'] ?? false) {
            throw new AuthorizationException("Tahun Anggaran {$tahun} terkunci — templatenya hanya bisa dilihat.");
        }
        if (($galat = NomorHalaman::galatFormat((string) ($aturan['format'] ?? ''))) !== null) {
            throw ValidationException::withMessages(['format' => $galat]);
        }

        $repo = app(NomorHalamanRepository::class);
        $lama = $repo->untuk($tahun);
        $baru = NomorHalaman::rapikan($aturan);
        $posisi = fn (array $a) => ['posisi' => NomorHalaman::POSISI[$a['posisi']]] + $a;
        $perubahan = RiwayatPengaturan::perubahan($posisi($lama), $posisi($baru), self::LABEL);

        $repo->simpan($tahun, $baru);
        RiwayatPengaturan::catat('ubah_nomor_halaman', 'Aturan nomor halaman diubah'.($perubahan ? " — {$perubahan}" : '').'.');

        return $baru;
    }
}
