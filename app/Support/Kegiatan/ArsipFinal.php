<?php

namespace App\Support\Kegiatan;

use App\Enums\StatusKegiatan;
use App\Support\Format;
use App\Support\Rab\AnggaranKegiatan;
use Illuminate\Http\UploadedFile;

/**
 * Aturan arsip final (PRD F18): setelah disetujui, KAK/TOR & RAB dicetak, ditandatangani, lalu PDF-nya diunggah per
 * sumber dana yang dipilih kegiatan. "Tandai final" baru bisa setelah semua sumber dana punya arsip; selama final
 * arsip tidak bisa diganti (kunci dibuka admin dulu).
 */
final class ArsipFinal
{
    public const MAKS_KB = 10240;

    /**
     * Sumber dana yang wajib punya arsip: sumber yang masih dipilih kegiatan (urutan enum).
     *
     * @param  array<string, mixed>  $kegiatan
     * @return list<string>
     */
    public static function sumberWajib(array $kegiatan): array
    {
        return array_map(fn ($a) => $a->sumber, AnggaranKegiatan::untuk($kegiatan));
    }

    /**
     * Sumber dana wajib yang belum punya arsip.
     *
     * @param  array<string, mixed>  $kegiatan
     * @param  array<string, mixed>  $arsip  Hasil ArsipFinalRepository::untukKegiatan().
     * @return list<string>
     */
    public static function kurang(array $kegiatan, array $arsip): array
    {
        return array_values(array_filter(self::sumberWajib($kegiatan), fn (string $s) => ! isset($arsip[$s])));
    }

    /** Arsip hanya diunggah/diganti selama status Disetujui. */
    public static function bolehUnggah(StatusKegiatan $status): bool
    {
        return $status === StatusKegiatan::Disetujui;
    }

    /** @param  list<string>  $sumber */
    public static function pesanKurang(array $sumber): string
    {
        $daftar = array_map(fn (string $s) => 'RAB '.$s, $sumber);
        $teks = count($daftar) > 1 ? implode(', ', array_slice($daftar, 0, -1)).' dan '.end($daftar) : ($daftar[0] ?? '');

        return "Unggah arsip final bertanda tangan untuk {$teks} sebelum menandai final.";
    }

    /** Berkas benar-benar PDF (diawali penanda %PDF-), bukan sekadar berekstensi .pdf. */
    public static function pdfSah(UploadedFile $berkas): bool
    {
        $isi = @file_get_contents($berkas->getRealPath(), false, null, 0, 5);

        return $isi === '%PDF-';
    }

    /** "245 KB", "1,2 MB". */
    public static function ukuran(int $byte): string
    {
        return $byte < 1024 * 1024
            ? Format::angka(max(1, (int) round($byte / 1024))).' KB'
            : str_replace('.', ',', (string) round($byte / 1024 / 1024, 1)).' MB';
    }
}
