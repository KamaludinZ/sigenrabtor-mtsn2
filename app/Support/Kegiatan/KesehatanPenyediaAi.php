<?php

namespace App\Support\Kegiatan;

use App\Contracts\PengaturanAiRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Kesehatan penyedia AI untuk failover (PRD §21 "Penyedia Aktif & Cadangan"; dipakai RantaiPenyediaAi): kegagalan nyata
 * (bukan simulasi) dicatat per penyedia di cache, dan selama `JEDA` jenis kegagalannya berjalan penyedia itu dianggap
 * bermasalah — permintaan berikutnya langsung mencoba cadangan lebih dulu tanpa menunggu waktu habis penyedia aktif
 * (penyedia aktif tetap dicoba terakhir). Jawaban berhasil menghapus catatannya; catatan berlaku hanya untuk versi
 * Pengaturan AI saat gagal (`diperbarui_pada`), jadi admin yang mengubah pengaturan langsung memulihkannya.
 */
final class KesehatanPenyediaAi
{
    /** Lama jeda (detik) per jenis kegagalan; jenis tanpa jeda (jawaban rusak, permintaan ditolak — bergantung isi permintaan) tidak menunda penyedia. */
    public const JEDA = [
        'tidak_terhubung' => 120,
        'waktu_habis' => 120,
        'kuota_penyedia' => 300,
        'penyedia_sibuk' => 60,
        'kunci_ditolak' => 600,
        'model_tidak_ada' => 600,
    ];

    public static function catatGagal(string $penyedia, string $jenis): void
    {
        $detik = self::JEDA[$jenis] ?? 0;
        $lama = self::baca($penyedia);
        $catatan = [
            'jenis' => $jenis,
            'pada' => now()->toIso8601String(),
            'jeda_sampai' => $detik > 0 ? now()->addSeconds($detik)->toIso8601String() : null,
            'beruntun' => ($lama !== null && ($lama['versi'] ?? null) === self::versi() ? (int) $lama['beruntun'] : 0) + 1,
            'versi' => self::versi(),
        ];
        Cache::put(self::kunci($penyedia), $catatan, now()->addDay());
        Log::warning('Penyedia AI ditandai bermasalah.', ['penyedia' => $penyedia, 'jenis' => $jenis, 'jeda_detik' => $detik, 'beruntun' => $catatan['beruntun']]);
    }

    public static function catatBerhasil(string $penyedia): void
    {
        Cache::forget(self::kunci($penyedia));
    }

    /**
     * Jeda yang sedang berjalan untuk penyedia; null = sehat (atau jeda sudah lewat / pengaturan sudah berubah).
     *
     * @return array{jenis: string, judul: string, sampai: string}|null
     */
    public static function jeda(string $penyedia): ?array
    {
        $catatan = self::status($penyedia);
        if ($catatan === null || $catatan['jeda_sampai'] === null || CarbonImmutable::parse($catatan['jeda_sampai'])->isPast()) {
            return null;
        }

        return ['jenis' => $catatan['jenis'], 'judul' => KegagalanAi::judul($catatan['jenis']), 'sampai' => $catatan['jeda_sampai']];
    }

    /**
     * Kegagalan terakhir penyedia (untuk Pengaturan AI); null = belum ada atau pengaturan sudah berubah sejak itu.
     *
     * @return array{jenis: string, judul: string, pada: string, jeda_sampai: string|null, beruntun: int}|null
     */
    public static function status(string $penyedia): ?array
    {
        $catatan = self::baca($penyedia);
        if ($catatan === null || ($catatan['versi'] ?? null) !== self::versi()) {
            return null;
        }

        return ['jenis' => $catatan['jenis'], 'judul' => KegagalanAi::judul($catatan['jenis']), 'pada' => $catatan['pada'], 'jeda_sampai' => $catatan['jeda_sampai'], 'beruntun' => (int) $catatan['beruntun']];
    }

    /** @return array<string, mixed>|null */
    private static function baca(string $penyedia): ?array
    {
        $catatan = Cache::get(self::kunci($penyedia));

        return is_array($catatan) ? $catatan : null;
    }

    private static function versi(): ?string
    {
        return app(PengaturanAiRepository::class)->ambil()['diperbarui_pada'];
    }

    private static function kunci(string $penyedia): string
    {
        return 'sirabtor:kesehatan-ai:'.$penyedia;
    }
}
