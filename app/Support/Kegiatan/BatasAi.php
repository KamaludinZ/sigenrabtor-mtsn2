<?php

namespace App\Support\Kegiatan;

use App\Contracts\PemakaianAiRepository;
use App\Support\Stub\StubPengaturan;

/**
 * Batas permintaan AI per akun per hari (PRD §21, Pengaturan AI › Batas pemakaian): batas umum `batas_per_user_per_hari`
 * dan batas khusus per akun `batas_khusus` {userId: n}; berlaku untuk semua fitur AI (Kalimat KAK & usulan jadwal).
 * Satu jatah diambil atomik tepat sebelum permintaan dikirim (`ambil()` → GagalUsulanAi bila habis) dan dikembalikan bila
 * penyedia gagal (`kembalikan()`), sehingga permintaan bersamaan tidak melewati batas dan kegagalan tidak dihitung.
 */
final class BatasAi
{
    public const RENTANG = [1, 200];

    /** Pilihan cepat batas umum. */
    public const PRESET = [10, 20, 50];

    /** Batas umum (tanpa $pengguna) atau batas berlaku untuk satu akun (khusus bila ada). */
    public static function batas(?array $pengguna = null): int
    {
        $ai = StubPengaturan::pengaturanAi();
        $khusus = $pengguna !== null ? ($ai['batas_khusus'][(string) $pengguna['id']] ?? null) : null;

        return (int) ($khusus ?? $ai['batas_per_user_per_hari']);
    }

    /** Batas khusus akun ini; null = memakai batas umum. */
    public static function khusus(array $pengguna): ?int
    {
        $nilai = StubPengaturan::pengaturanAi()['batas_khusus'][(string) $pengguna['id']] ?? null;

        return $nilai === null ? null : (int) $nilai;
    }

    /** @param  array{id: int}  $pengguna */
    public static function terpakai(array $pengguna): int
    {
        return app(PemakaianAiRepository::class)->jumlah((int) $pengguna['id'], now()->toDateString());
    }

    /** @param  array{id: int}  $pengguna */
    public static function sisa(array $pengguna): int
    {
        return max(0, self::batas($pengguna) - self::terpakai($pengguna));
    }

    /** @param  array{id: int}  $pengguna */
    public static function catat(array $pengguna, string $fitur): void
    {
        app(PemakaianAiRepository::class)->catat((int) $pengguna['id'], $fitur);
    }

    /**
     * Ambil satu jatah sebelum permintaan AI dikirim ($fitur: kunci BagianAi atau "usul_jadwal").
     *
     * @param  array{id: int}  $pengguna
     *
     * @throws GagalUsulanAi Batas harian akun sudah tercapai (pesanHabis).
     */
    public static function ambil(array $pengguna, string $fitur): void
    {
        if (! app(PemakaianAiRepository::class)->ambilJatah((int) $pengguna['id'], $fitur, self::batas($pengguna))) {
            throw new GagalUsulanAi(self::pesanHabis($pengguna));
        }
    }

    /**
     * Kembalikan jatah yang diambil ambil() bila permintaan gagal.
     *
     * @param  array{id: int}  $pengguna
     */
    public static function kembalikan(array $pengguna, string $fitur): void
    {
        app(PemakaianAiRepository::class)->kembalikan((int) $pengguna['id'], $fitur);
    }

    public static function pesanHabis(?array $pengguna = null): string
    {
        return 'Batas '.self::batas($pengguna).' permintaan AI hari ini sudah tercapai — coba lagi besok.';
    }

    /** Galat nilai batas (umum atau khusus); null bila sah. */
    public static function galat(mixed $nilai): ?string
    {
        [$min, $maks] = self::RENTANG;
        $angka = filter_var($nilai, FILTER_VALIDATE_INT);

        return $angka === false || $angka < $min || $angka > $maks ? "Batas {$min}–{$maks} permintaan per hari." : null;
    }
}
