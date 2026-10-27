<?php

namespace App\Support\Kegiatan;

use App\Contracts\PemakaianAiRepository;
use App\Support\Stub\StubPengaturan;

/**
 * Batas permintaan AI per pengguna per hari (PRD §21, StubPengaturan::pengaturanAi() `batas_per_user_per_hari`), berlaku
 * untuk semua fitur AI (Kalimat KAK & usulan jadwal). Permintaan dicatat setelah penyusun menjawab.
 */
final class BatasAi
{
    public static function batas(): int
    {
        return (int) StubPengaturan::pengaturanAi()['batas_per_user_per_hari'];
    }

    /** @param  array{id: int}  $pengguna */
    public static function terpakai(array $pengguna): int
    {
        return app(PemakaianAiRepository::class)->jumlah((int) $pengguna['id'], now()->toDateString());
    }

    /** @param  array{id: int}  $pengguna */
    public static function sisa(array $pengguna): int
    {
        return max(0, self::batas() - self::terpakai($pengguna));
    }

    /** @param  array{id: int}  $pengguna */
    public static function catat(array $pengguna, string $fitur): void
    {
        app(PemakaianAiRepository::class)->catat((int) $pengguna['id'], $fitur);
    }

    public static function pesanHabis(): string
    {
        return 'Batas '.self::batas().' permintaan AI hari ini sudah tercapai — coba lagi besok.';
    }
}
