<?php

namespace App\Support\Dokumen;

use App\Contracts\PenggunaRepository;
use App\Support\Kegiatan\AksesKegiatan;
use App\Support\Kegiatan\KunciKegiatan;
use App\Support\MasterKode;
use App\Support\Pengaturan\TautanAkunLeading;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Otorisasi aksi atas catatan Riwayat Dokumen per akun (PRD §7, §15 peran; dipakai RiwayatDokumen::untuk, AksiRiwayatDokumen,
 * DataRiwayatDokumen, endpoint `dokumen.detail`, dan modal halaman Riwayat Dokumen). Setiap aksi `AKSI` mengembalikan null
 * (boleh) atau alasan penolakan siap tampil; `izin()` = peta boolean semua aksi, `pastikan()` = AuthorizationException.
 *
 * - `lihat`: akun aktif (`akunAktif`; `$aktif` boleh dihitung sekali oleh pemanggil), lalu cakupan RiwayatDokumen::boleh —
 *   admin semua catatan; akun leading catatan leading yang tertaut ke akunnya (TautanAkunLeading::bolehAkses, termasuk
 *   leading yang kini nonaktif) atau yang dibuatnya sendiri (`user_id`).
 * - `buka_ulang` (pratinjau, cetak ulang, unduh dengan data terbaru): `lihat` + kegiatannya masih tersimpan + kegiatan itu
 *   masih dalam cakupan akun (AksesKegiatan::bolehLihat) + sumber dananya masih dipilih.
 * - `unduh_arsip`: `buka_ulang` + catatan arsip final versi terbaru (arsip lama sudah diganti).
 * - `duplikat` (salin sebagai dasar kegiatan baru): `lihat` + tahun anggaran aktif terbuka + kegiatannya tersimpan & dalam
 *   cakupan + admin, atau akun leading yang tertaut ke leading kegiatan itu (pembuat yang tidak lagi tertaut tidak boleh
 *   membuat kegiatan untuk leading tersebut) + leading masih aktif.
 */
final class OtorisasiDokumen
{
    public const AKSI = ['lihat' => 'Lihat', 'buka_ulang' => 'Buka ulang', 'unduh_arsip' => 'Unduh arsip final', 'duplikat' => 'Duplikat'];

    public const PESAN_NONAKTIF = 'Akun Anda nonaktif — hubungi admin untuk membuka riwayat dokumen.';

    public const PESAN_TOLAK = 'Dokumen ini di luar cakupan akun Anda.';

    public const PESAN_KEGIATAN_LUAR = 'Kegiatan dokumen ini kini di luar cakupan akun Anda, sehingga dokumennya tidak bisa disusun ulang atau disalin.';

    /** Akun tercatat dan aktif (akun tiruan tanpa catatan dianggap aktif). */
    public static function akunAktif(array $pengguna): bool
    {
        $akun = isset($pengguna['id']) ? app(PenggunaRepository::class)->temukan((int) $pengguna['id']) : null;

        return ($akun['is_active'] ?? true) !== false;
    }

    /** @param  array<string, mixed>  $catatan */
    public static function lihat(array $catatan, array $pengguna, ?bool $aktif = null): ?string
    {
        if (! ($aktif ?? self::akunAktif($pengguna))) {
            return self::PESAN_NONAKTIF;
        }

        return RiwayatDokumen::boleh($catatan, $pengguna) ? null : self::PESAN_TOLAK;
    }

    /**
     * @param  array<string, mixed>  $catatan
     * @param  array<string, mixed>|null  $kegiatan  Kegiatan catatan itu (null = tidak tersimpan).
     */
    public static function bukaUlang(array $catatan, ?array $kegiatan, array $pengguna, ?bool $aktif = null): ?string
    {
        return self::lihat($catatan, $pengguna, $aktif) ?? match (true) {
            $kegiatan === null => 'Data kegiatan TA '.$catatan['tahun_anggaran'].' tidak lagi tersimpan — hanya catatan dokumennya yang tersisa, sehingga dokumen tidak bisa disusun ulang.',
            ! AksesKegiatan::bolehLihat($kegiatan, $pengguna) => self::PESAN_KEGIATAN_LUAR,
            ($catatan['sumber'] ?? null) !== null && ! in_array($catatan['sumber'], array_column($kegiatan['sumber_dana'] ?? [], 'sumber'), true) => 'Sumber dana '.$catatan['sumber'].' tidak lagi dipilih di kegiatan ini, sehingga dokumen sumber dana itu tidak bisa disusun ulang.',
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $catatan
     * @param  array<string, mixed>|null  $kegiatan
     */
    public static function unduhArsip(array $catatan, ?array $kegiatan, array $pengguna, bool $terbaru, ?bool $aktif = null): ?string
    {
        return self::bukaUlang($catatan, $kegiatan, $pengguna, $aktif) ?? match (true) {
            ($catatan['asal'] ?? null) !== 'arsip' => 'Catatan ini bukan arsip final.',
            ! $terbaru => 'Arsip final versi ini sudah diganti versi yang lebih baru.',
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $catatan
     * @param  array<string, mixed>|null  $kegiatan
     */
    public static function duplikat(array $catatan, ?array $kegiatan, array $pengguna, ?bool $aktif = null): ?string
    {
        if (($alasan = self::lihat($catatan, $pengguna, $aktif)) !== null) {
            return $alasan;
        }
        if (($terkunci = KunciKegiatan::tahunTerkunci(['tahun_anggaran' => StubData::tahunAktif()['tahun']])) !== null) {
            return KunciKegiatan::pesanTahun($terkunci);
        }
        if ($kegiatan === null) {
            return 'Data kegiatan TA '.$catatan['tahun_anggaran'].' tidak lagi tersimpan — hanya catatan dokumennya yang tersisa, sehingga isinya tidak bisa disalin. Buat kegiatan baru dari awal.';
        }
        if (! AksesKegiatan::bolehLihat($kegiatan, $pengguna)) {
            return self::PESAN_KEGIATAN_LUAR;
        }
        $kode = ($kegiatan['leading'] ?? '') ?: null;
        if (! TautanAkunLeading::bolehAkses($pengguna, $kode)) {
            return 'Kegiatan baru hanya bisa dibuat untuk leading yang tertaut ke akun Anda'.($kode ? " — leading {$kode} tidak lagi tertaut" : '').'.';
        }
        $leading = MasterKode::leading($kode);

        return $leading !== null && ! $leading['is_active'] ? 'Leading '.$leading['kode'].' kini nonaktif — kegiatannya tidak bisa disalin.' : null;
    }

    /**
     * @param  array<string, mixed>  $catatan
     * @param  array<string, mixed>|null  $kegiatan
     * @return array{izin: array<string, bool>, alasan: array<string, string|null>}
     */
    public static function izin(array $catatan, ?array $kegiatan, array $pengguna, bool $terbaru, ?bool $aktif = null): array
    {
        $aktif ??= self::akunAktif($pengguna);
        $alasan = [
            'lihat' => self::lihat($catatan, $pengguna, $aktif),
            'buka_ulang' => self::bukaUlang($catatan, $kegiatan, $pengguna, $aktif),
            'unduh_arsip' => self::unduhArsip($catatan, $kegiatan, $pengguna, $terbaru, $aktif),
            'duplikat' => self::duplikat($catatan, $kegiatan, $pengguna, $aktif),
        ];

        return ['izin' => array_map(fn (?string $a) => $a === null, $alasan), 'alasan' => $alasan];
    }

    /**
     * @param  array<string, mixed>  $catatan
     * @param  array<string, mixed>|null  $kegiatan
     *
     * @throws AuthorizationException
     */
    public static function pastikan(string $aksi, array $catatan, ?array $kegiatan, array $pengguna, bool $terbaru = true): void
    {
        $alasan = match ($aksi) {
            'lihat' => self::lihat($catatan, $pengguna),
            'buka_ulang' => self::bukaUlang($catatan, $kegiatan, $pengguna),
            'unduh_arsip' => self::unduhArsip($catatan, $kegiatan, $pengguna, $terbaru),
            'duplikat' => self::duplikat($catatan, $kegiatan, $pengguna),
            default => throw new \InvalidArgumentException("Aksi {$aksi} tidak dikenal."),
        };
        if ($alasan !== null) {
            throw new AuthorizationException($alasan);
        }
    }
}
