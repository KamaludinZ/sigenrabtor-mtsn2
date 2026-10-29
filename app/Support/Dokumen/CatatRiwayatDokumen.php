<?php

namespace App\Support\Dokumen;

use App\Contracts\RiwayatDokumenRepository;
use App\Support\Dokumen\Generasi\BerkasDokumen;
use App\Support\Dokumen\Generasi\FormatBerkas;
use App\Support\Dokumen\Generasi\GeneratorBerkasServer;
use App\Support\Dokumen\Generasi\PermintaanBerkas;
use App\Support\Rab\AnggaranKegiatan;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pencatat satu berkas ke Riwayat Dokumen (PRD §7 "semua dokumen yang pernah dibuat tersimpan"): `catat()` menyusun entri
 * RiwayatDokumen::entri dari data kegiatan saat ini — nama berkas baku (GeneratorBerkasServer::nama), cap DRAF lembar itu,
 * ringkas {total_rab sumber dana, bagian KAK/TOR lengkap / jumlah}, dan cuplikan isi (IsiRiwayatDokumen::cuplikan) — lalu
 * RiwayatDokumenRepository::catat (versi berikutnya diisi repositori). Dipakai PUT `dokumen.perbarui` (terbitkan ulang) dan,
 * lewat `dariBerkas()`/`dariLembar()`, unduhan server (`kegiatan.berkas.unduh`, `kegiatan.kak.word`, `kegiatan.cetak.pdf`,
 * `kegiatan.rab.excel` satu sumber) selama config `sigenrabtor.riwayat.catat_unduhan` (env `SIRABTOR_CATAT_UNDUHAN`,
 * bawaan ya; E2E mematikannya agar data riwayat bersama tetap) — kegagalan mencatat tidak pernah menggagalkan unduhan
 * (dicatat di log). Lembar cetak → jenis lewat `JENIS_LEMBAR` (pilihan lembar lain tidak dicatat).
 */
final class CatatRiwayatDokumen
{
    /** Lembar berkas (urut cetak) → jenis dokumen yang dicatat. */
    public const JENIS_LEMBAR = ['cover,kak,rab' => 'lengkap', 'cover,kak' => 'KAK+TOR', 'kak' => 'KAK+TOR', 'rab' => 'RAB'];

    /**
     * @param  array<string, mixed>  $kegiatan
     * @param  array{id?: int|null, name: string, role?: string}  $pengguna
     * @return array<string, mixed> Catatan tersimpan.
     */
    public static function catat(array $kegiatan, string $jenis, ?string $sumber, string $format, string $asal, array $pengguna, ?int $ukuran = null, ?string $nomor = null): array
    {
        $lembar = FormatBerkas::lembar($jenis, $sumber);
        $permintaan = new PermintaanBerkas($kegiatan, $jenis, $sumber, $format, $lembar, $pengguna);
        $cetak = StatusCetak::dari($kegiatan, $sumber);
        $isi = IsiRiwayatDokumen::cuplikan($kegiatan, $jenis, $sumber, $pengguna);
        $anggaran = $sumber !== null ? (AnggaranKegiatan::perSumber($kegiatan)[$sumber] ?? null) : null;

        return app(RiwayatDokumenRepository::class)->catat(RiwayatDokumen::entri(
            $kegiatan, $jenis, $sumber, $format, $asal, GeneratorBerkasServer::nama($permintaan, $cetak), $ukuran,
            CetakDokumen::statusDraf($cetak, $lembar)->draf, $pengguna,
            [
                'total_rab' => in_array('rab', $lembar, true) ? $anggaran?->total() : null,
                'bagian_lengkap' => $isi['bagian'] !== [] ? count(array_filter($isi['bagian'], fn (array $b) => $b['status'] === 'lengkap')) : null,
                'jumlah_bagian' => $isi['bagian'] !== [] ? count($isi['bagian']) : null,
            ],
            $nomor,
            $isi,
        ));
    }

    /**
     * Catat berkas yang baru diunduh dari server (GeneratorBerkasDokumen) — null bila pencatatan dimatikan atau gagal.
     *
     * @param  array{id?: int|null, name: string}  $pengguna
     * @return array<string, mixed>|null
     */
    public static function dariBerkas(BerkasDokumen $berkas, array $pengguna): ?array
    {
        $p = $berkas->permintaan;

        return self::aman(fn () => self::catat($p->kegiatan, $p->jenis, $p->sumber, $p->format, 'unduh', $pengguna, $berkas->ukuran()));
    }

    /**
     * Catat berkas berlembar bebas (PDF `kegiatan.cetak.pdf`, Excel RAB satu sumber) — dilewati bila lembarnya bukan jenis.
     *
     * @param  array<string, mixed>  $kegiatan
     * @param  list<string>  $lembar
     * @return array<string, mixed>|null
     */
    public static function dariLembar(array $kegiatan, ?string $sumber, array $lembar, string $format, int $ukuran, array $pengguna): ?array
    {
        $jenis = self::JENIS_LEMBAR[implode(',', CetakDokumen::pilihan($lembar))] ?? null;
        if ($jenis === null || (JenisDokumen::perluSumber($jenis) && $sumber === null)) {
            return null;
        }

        return self::aman(fn () => self::catat($kegiatan, $jenis, $sumber, $format, 'unduh', $pengguna, $ukuran));
    }

    /** @return array<string, mixed>|null */
    private static function aman(callable $catat): ?array
    {
        if (! config('sigenrabtor.riwayat.catat_unduhan', true)) {
            return null;
        }
        try {
            return $catat();
        } catch (Throwable $e) {
            Log::warning('Riwayat dokumen gagal dicatat', ['galat' => $e->getMessage()]);

            return null;
        }
    }
}
