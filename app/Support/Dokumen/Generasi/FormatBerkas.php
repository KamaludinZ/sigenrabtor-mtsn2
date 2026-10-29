<?php

namespace App\Support\Dokumen\Generasi;

use App\Support\Dokumen\DokumenKegiatan;

/**
 * Daftar format berkas dokumen kegiatan dan kecocokannya dengan jenis dokumen (kontrak generasi dokumen server, PRD F12,
 * F16, F21): `FORMAT` (label, MIME, ekstensi, lembar yang bisa dimuat, perlu sumber dana, bisa disusun di peramban),
 * `LEMBAR_JENIS` (lembar tiap jenis DokumenKegiatan::JENIS saat dijadikan berkas — KAK/TOR selalu bersama cover-nya),
 * `cocok($jenis, $format)` (semua lembar jenis termuat di format), `untukJenis($jenis)`, dan `galat()` (pesan penolakan).
 */
final class FormatBerkas
{
    /** Format → [label, mime, ekstensi, lembar yang didukung, perlu sumber dana, bisa disusun di peramban]. */
    public const FORMAT = [
        'pdf' => ['PDF', 'application/pdf', 'pdf', ['cover', 'kak', 'rab'], false, false],
        'docx' => ['Word', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'docx', ['cover', 'kak'], false, true],
        'xlsx' => ['Excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'xlsx', ['rab'], true, true],
    ];

    /** Jenis dokumen → lembar berkasnya, urut cetak. */
    public const LEMBAR_JENIS = [
        'KAK+TOR' => ['cover', 'kak'],
        'KAK' => ['cover', 'kak'],
        'TOR' => ['cover', 'kak'],
        'RAB' => ['rab'],
        'lengkap' => ['cover', 'kak', 'rab'],
    ];

    public static function label(string $format): string
    {
        return self::FORMAT[$format][0] ?? strtoupper($format);
    }

    public static function mime(string $format): string
    {
        return self::FORMAT[$format][1] ?? 'application/octet-stream';
    }

    public static function ekstensi(string $format): string
    {
        return self::FORMAT[$format][2] ?? $format;
    }

    public static function perluSumber(string $format): bool
    {
        return self::FORMAT[$format][4] ?? false;
    }

    public static function klien(string $format): bool
    {
        return self::FORMAT[$format][5] ?? false;
    }

    /**
     * Lembar jenis itu; tanpa sumber dana RAB tidak ikut (kegiatan tanpa sumber dana hanya cover + KAK/TOR).
     *
     * @return list<string>
     */
    public static function lembar(string $jenis, ?string $sumber): array
    {
        $lembar = self::LEMBAR_JENIS[$jenis] ?? self::LEMBAR_JENIS['lengkap'];

        return $sumber === null ? (array_values(array_diff($lembar, ['rab'])) ?: ['cover', 'kak']) : $lembar;
    }

    public static function cocok(string $jenis, string $format): bool
    {
        return isset(self::FORMAT[$format], self::LEMBAR_JENIS[$jenis]) && array_diff(self::LEMBAR_JENIS[$jenis], self::FORMAT[$format][3]) === [];
    }

    /** @return list<string> Format yang bisa memuat jenis itu, urut FORMAT. */
    public static function untukJenis(string $jenis): array
    {
        return array_values(array_filter(array_keys(self::FORMAT), fn (string $f) => self::cocok($jenis, $f)));
    }

    /** Pesan penolakan jenis × format yang tidak cocok (null = cocok). */
    public static function galat(string $jenis, string $format): ?string
    {
        if (self::cocok($jenis, $format)) {
            return null;
        }
        $bisa = array_map(self::label(...), self::untukJenis($jenis));

        return (DokumenKegiatan::JENIS[$jenis] ?? $jenis).' tidak bisa dijadikan berkas '.self::label($format).' — pilih '.implode(' atau ', $bisa).'.';
    }
}
