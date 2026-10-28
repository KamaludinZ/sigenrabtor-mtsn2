<?php

namespace App\Support\Pengaturan;

use App\Contracts\DasarHukumRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Kelola daftar dasar hukum per tahun anggaran di server (PRD §13, F9; ubah hanya admin, tahun terkunci hanya dilihat) —
 * dipakai Pengaturan › Dasar hukum & latar belakang (`Pengaturan\DasarHukum`) dan endpoint
 * `pengaturan.madrasah.dasar_hukum.data`. Tiap aksi menyimpan seluruh pengaturan tahun itu lewat DasarHukumRepository
 * (latar belakang baku tetap) dan mencatat riwayat `ubah_dasar_hukum`:
 * - `tambah` (di akhir atau di `$posisi`), `ubah` (null = teks sama), `hapus` (mengembalikan posisi & butir untuk
 *   Urungkan), `kembalikan` (butir terhapus ke posisi semula), `pindah` (urutan = urutan cetak).
 * - Teks dirapikan DaftarDasarHukum::rapikan lalu diperiksa DaftarDasarHukum::galat (wajib, ≤ MAKS_KARAKTER, penanda
 *   `boleh()`, tidak dobel); paling banyak MAKS_BUTIR butir.
 * - `$versi` (diperbarui_pada yang terakhir dilihat; null = tanpa pemeriksaan) berbeda dengan yang tersimpan →
 *   PengaturanBerubah. Kegagalan penyimpanan (RuntimeException repositori) diteruskan tanpa mencatat riwayat.
 */
final class KelolaDasarHukum
{
    /**
     * @param  array{role: string}  $pengguna
     * @return array{butir: array{id: string, teks: string}, nomor: int}
     *
     * @throws ValidationException Berkunci `teks` atau `jumlah`.
     */
    public static function tambah(int $tahun, string $teks, array $pengguna, ?string $versi = null, ?int $posisi = null): array
    {
        $daftar = self::siapkan($tahun, $pengguna, $versi);
        if (count($daftar) >= DaftarDasarHukum::MAKS_BUTIR) {
            throw ValidationException::withMessages(['jumlah' => 'Dasar hukum paling banyak '.DaftarDasarHukum::MAKS_BUTIR.' butir.']);
        }
        $teks = self::periksa($teks, $daftar);

        $posisi = max(0, min($posisi ?? count($daftar), count($daftar)));
        $butir = ['id' => (string) Str::uuid(), 'teks' => $teks];
        array_splice($daftar, $posisi, 0, [$butir]);
        self::simpan($tahun, $daftar, 'tambah butir '.($posisi + 1).': '.Str::limit($teks, 120));

        return ['butir' => $butir, 'nomor' => $posisi + 1];
    }

    /**
     * @param  array{role: string}  $pengguna
     * @return array{butir: array{id: string, teks: string}, nomor: int}|null Null bila teksnya sama.
     *
     * @throws ValidationException Berkunci `id` atau `teks`.
     */
    public static function ubah(int $tahun, string $id, string $teks, array $pengguna, ?string $versi = null): ?array
    {
        $daftar = self::siapkan($tahun, $pengguna, $versi);
        $i = self::indeks($daftar, $id);
        $teks = self::periksa($teks, $daftar, $id);
        $lama = DaftarDasarHukum::rapikan($daftar[$i]['teks']);
        if ($lama === $teks) {
            return null;
        }

        $daftar[$i]['teks'] = $teks;
        self::simpan($tahun, $daftar, 'ubah butir '.($i + 1).': '.Str::limit($lama, 60).' → '.Str::limit($teks, 60));

        return ['butir' => $daftar[$i], 'nomor' => $i + 1];
    }

    /**
     * @param  array{role: string}  $pengguna
     * @return array{posisi: int, butir: array{id: string, teks: string}} Posisi (indeks) & isi butir terhapus untuk Urungkan.
     *
     * @throws ValidationException Berkunci `id`.
     */
    public static function hapus(int $tahun, string $id, array $pengguna, ?string $versi = null): array
    {
        $daftar = self::siapkan($tahun, $pengguna, $versi);
        $i = self::indeks($daftar, $id);
        [$butir] = array_splice($daftar, $i, 1);
        self::simpan($tahun, $daftar, 'hapus butir '.($i + 1).': '.Str::limit(DaftarDasarHukum::rapikan($butir['teks']), 120));

        return ['posisi' => $i, 'butir' => $butir];
    }

    /**
     * Kembalikan butir yang baru dihapus ke posisinya (atau ke akhir bila daftar sudah lebih pendek).
     *
     * @param  array{id: string, teks: string}  $butir
     * @param  array{role: string}  $pengguna
     * @return int Nomor butir setelah dikembalikan.
     *
     * @throws ValidationException Berkunci `id` (butir masih ada) atau `jumlah`.
     */
    public static function kembalikan(int $tahun, array $butir, int $posisi, array $pengguna, ?string $versi = null): int
    {
        $daftar = self::siapkan($tahun, $pengguna, $versi);
        if (in_array($butir['id'], array_column($daftar, 'id'), true)) {
            throw ValidationException::withMessages(['id' => 'Butir ini masih ada di daftar.']);
        }
        if (count($daftar) >= DaftarDasarHukum::MAKS_BUTIR) {
            throw ValidationException::withMessages(['jumlah' => 'Dasar hukum paling banyak '.DaftarDasarHukum::MAKS_BUTIR.' butir.']);
        }
        $teks = self::periksa((string) $butir['teks'], $daftar);

        $posisi = max(0, min($posisi, count($daftar)));
        array_splice($daftar, $posisi, 0, [['id' => (string) $butir['id'], 'teks' => $teks]]);
        self::simpan($tahun, $daftar, 'kembalikan butir '.($posisi + 1).': '.Str::limit($teks, 120));

        return $posisi + 1;
    }

    /**
     * Pindahkan butir $id ke indeks $ke (dibatasi ke rentang daftar).
     *
     * @param  array{role: string}  $pengguna
     * @return array{dari: int, ke: int}|null Nomor asal & tujuan; null bila tidak berpindah.
     *
     * @throws ValidationException Berkunci `id`.
     */
    public static function pindah(int $tahun, string $id, int $ke, array $pengguna, ?string $versi = null): ?array
    {
        $daftar = self::siapkan($tahun, $pengguna, $versi);
        $dari = self::indeks($daftar, $id);
        $ke = max(0, min($ke, count($daftar) - 1));
        if ($dari === $ke) {
            return null;
        }

        [$butir] = array_splice($daftar, $dari, 1);
        array_splice($daftar, $ke, 0, [$butir]);
        self::simpan($tahun, $daftar, 'pindah butir '.($dari + 1).' ke '.($ke + 1).': '.Str::limit(DaftarDasarHukum::rapikan($butir['teks']), 120));

        return ['dari' => $dari + 1, 'ke' => $ke + 1];
    }

    /**
     * Daftar dasar hukum satu tahun untuk endpoint: butir mentah & siap cetak, versi, batas, penanda yang boleh, SK kurang.
     *
     * @return array<string, mixed>
     */
    public static function data(int $tahun): array
    {
        $pengaturan = app(DasarHukumRepository::class)->untukTahun($tahun);
        $mentah = array_column($pengaturan['dasar_hukum'], 'teks');
        $cetak = DaftarDasarHukum::cetak($mentah, $tahun);

        return [
            'tahun' => $tahun,
            'terkunci' => self::terkunci($tahun),
            'diatur' => $pengaturan['diatur'],
            'diperbarui_pada' => $pengaturan['diperbarui_pada'],
            'butir' => array_map(fn (array $b, int $i) => ['id' => $b['id'], 'nomor' => $i + 1, 'teks' => $b['teks'], 'cetak' => $cetak[$i]], $pengaturan['dasar_hukum'], array_keys($pengaturan['dasar_hukum'])),
            'maks_butir' => DaftarDasarHukum::MAKS_BUTIR,
            'maks_karakter' => DaftarDasarHukum::MAKS_KARAKTER,
            'penanda' => DaftarDasarHukum::boleh(),
            'sk_kurang' => DaftarDasarHukum::skKurang($mentah, $tahun),
        ];
    }

    public static function terkunci(int $tahun): bool
    {
        return TahunDipilih::terkunci($tahun);
    }

    /**
     * Izin + versi, lalu daftar butir tersimpan.
     *
     * @param  array{role: string}  $pengguna
     * @return list<array{id: string, teks: string}>
     */
    private static function siapkan(int $tahun, array $pengguna, ?string $versi): array
    {
        if ($pengguna['role'] !== 'admin') {
            throw new AuthorizationException('Dasar hukum diatur admin.');
        }
        if (self::terkunci($tahun)) {
            throw new AuthorizationException("Tahun Anggaran {$tahun} terkunci — dasar hukumnya hanya bisa dilihat.");
        }
        $pengaturan = app(DasarHukumRepository::class)->untukTahun($tahun);
        if ($versi !== null && $pengaturan['diperbarui_pada'] !== $versi) {
            throw new PengaturanBerubah("Dasar hukum & latar belakang TA {$tahun} baru saja diubah di tempat lain.");
        }

        return $pengaturan['dasar_hukum'];
    }

    /** @param  list<array{id: string, teks: string}>  $daftar */
    private static function indeks(array $daftar, string $id): int
    {
        $i = array_search($id, array_column($daftar, 'id'), true);
        if ($i === false) {
            throw ValidationException::withMessages(['id' => 'Butir dasar hukum tidak ditemukan — mungkin sudah dihapus.']);
        }

        return $i;
    }

    /** @param  list<array{id: string, teks: string}>  $daftar */
    private static function periksa(string $teks, array $daftar, ?string $kecuali = null): string
    {
        $teks = DaftarDasarHukum::rapikan($teks);
        if (($pesan = DaftarDasarHukum::galat($teks, $daftar, $kecuali)) !== null) {
            throw ValidationException::withMessages(['teks' => $pesan]);
        }

        return $teks;
    }

    /** @param  list<array{id: string, teks: string}>  $daftar */
    private static function simpan(int $tahun, array $daftar, string $keterangan): void
    {
        $repo = app(DasarHukumRepository::class);
        $repo->simpan($tahun, ['dasar_hukum' => array_values($daftar)] + $repo->untukTahun($tahun));
        RiwayatPengaturan::catat('ubah_dasar_hukum', "Dasar hukum TA {$tahun} — {$keterangan}");
    }
}
