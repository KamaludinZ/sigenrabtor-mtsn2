<?php

namespace App\Support\Pengaturan;

use App\Contracts\DaftarIsiRepository;
use App\Contracts\TahunAnggaranRepository;
use App\Enums\SumberDana;
use App\Support\Dokumen\DaftarIsi;
use App\Support\MasterKode;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Kelola template halaman daftar isi buku di server (PRD §7A, tabel daftar_isi_templates; hanya admin, tahun terkunci
 * hanya bisa dilihat) — dipakai tab Daftar isi Pengaturan › Template Dokumen: pengaturan bawaan per tahun (leading &
 * sumber null) atau khusus satu buku leading × sumber dana (leading aktif, SumberDana) yang menimpanya. Konfigurasi
 * selalu DaftarIsi::rapikan; riwayat `ubah_daftar_isi` (dengan ringkasan perubahan) dan `hapus_daftar_isi_khusus`.
 */
final class KelolaDaftarIsi
{
    /** Label ringkasan perubahan di riwayat. */
    private const LABEL = ['judul' => 'Judul', 'subjudul' => 'Subjudul', 'kolom' => 'Kolom', 'urutan' => 'Urutan', 'tampilkan_jumlah' => 'Baris jumlah', 'catatan' => 'Catatan'];

    /**
     * Konfigurasi yang berlaku untuk buku itu (khusus bila ada, selain itu bawaan tahun).
     *
     * @return array<string, mixed>
     */
    public static function tersimpan(int $tahun, ?string $leading = null, ?string $sumber = null): array
    {
        $repo = app(DaftarIsiRepository::class);

        return $leading === null
            ? $repo->bawaan($tahun)
            : ($repo->khusus($tahun)["{$leading}|{$sumber}"] ?? $repo->bawaan($tahun));
    }

    /** "Tim Penjamin Mutu · DIPA". */
    public static function namaBuku(string $leading, string $sumber): string
    {
        return (MasterKode::leading($leading)['nama_unit'] ?? $leading).' · '.$sumber;
    }

    /**
     * @param  array<string, mixed>  $konfigurasi  Isian editor (dirapikan di sini).
     * @param  array{role: string}  $pengguna
     * @return array<string, mixed> Konfigurasi yang tersimpan.
     *
     * @throws ValidationException Buku tidak dikenal (`buku`).
     * @throws AuthorizationException Bukan admin atau tahun terkunci.
     */
    public static function simpan(int $tahun, ?string $leading, ?string $sumber, array $konfigurasi, array $pengguna): array
    {
        self::pastikanBoleh($tahun, $pengguna);
        self::pastikanBuku($leading, $sumber);

        $lama = self::tersimpan($tahun, $leading, $sumber);
        $rapi = DaftarIsi::rapikan($konfigurasi);
        $perubahan = RiwayatPengaturan::perubahan(self::tampilan($lama), self::tampilan($rapi), self::LABEL);
        $repo = app(DaftarIsiRepository::class);

        if ($leading === null) {
            $repo->simpanBawaan($tahun, $rapi);
            RiwayatPengaturan::catat('ubah_daftar_isi', 'Pengaturan bawaan daftar isi diubah'.($perubahan ? " — {$perubahan}" : '').'.');
        } else {
            $repo->simpanKhusus($tahun, $leading, $sumber, $rapi);
            RiwayatPengaturan::catat('ubah_daftar_isi', 'Pengaturan khusus daftar isi '.self::namaBuku($leading, $sumber).' disimpan'.($perubahan ? " — {$perubahan}" : ' (sama dengan bawaan)').'.');
        }

        return $rapi;
    }

    /**
     * Buku leading × sumber kembali memakai pengaturan bawaan (pengaturan khususnya dihapus).
     *
     * @param  array{role: string}  $pengguna
     */
    public static function hapusKhusus(int $tahun, string $leading, string $sumber, array $pengguna): void
    {
        self::pastikanBoleh($tahun, $pengguna);
        self::pastikanBuku($leading, $sumber);

        app(DaftarIsiRepository::class)->hapusKhusus($tahun, $leading, $sumber);
        RiwayatPengaturan::catat('hapus_daftar_isi_khusus', 'Daftar isi '.self::namaBuku($leading, $sumber).' kembali memakai pengaturan bawaan.');
    }

    /**
     * Konfigurasi dengan label terbaca (kolom & urutan) untuk ringkasan riwayat.
     *
     * @param  array<string, mixed>  $konfigurasi
     * @return array<string, mixed>
     */
    private static function tampilan(array $konfigurasi): array
    {
        return ['kolom' => array_map(fn (string $k) => DaftarIsi::KOLOM[$k][0], $konfigurasi['kolom']), 'urutan' => DaftarIsi::URUTAN[$konfigurasi['urutan']]] + $konfigurasi;
    }

    private static function pastikanBuku(?string $leading, ?string $sumber): void
    {
        $bawaan = $leading === null && $sumber === null;
        $dikenal = $leading !== null && MasterKode::cari(MasterKode::leadings(), $leading) !== null && in_array($sumber, SumberDana::urutan(), true);
        if (! $bawaan && ! $dikenal) {
            throw ValidationException::withMessages(['buku' => 'Pilih buku leading aktif × sumber dana DIPA/Komite, atau pengaturan bawaan.']);
        }
    }

    /** @param  array{role: string}  $pengguna */
    private static function pastikanBoleh(int $tahun, array $pengguna): void
    {
        if ($pengguna['role'] !== 'admin') {
            throw new AuthorizationException('Template dokumen diatur admin.');
        }
        if (collect(app(TahunAnggaranRepository::class)->semua())->firstWhere('tahun', $tahun)['is_locked'] ?? false) {
            throw new AuthorizationException("Tahun Anggaran {$tahun} terkunci — templatenya hanya bisa dilihat.");
        }
    }
}
