<?php

namespace App\Support\Pengaturan;

use App\Contracts\KlienLlm;
use App\Contracts\PengaturanAiRepository;
use App\Support\Kegiatan\GagalPenyediaAi;
use App\Support\Kegiatan\KegagalanAi;
use App\Support\Kegiatan\KesehatanPenyediaAi;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Uji koneksi satu penyedia AI dari Pengaturan AI (PRD F24; khusus admin). Konfigurasi tersimpan diperiksa dulu
 * (AturanPenyediaAi::siap — belum siap → hasil gagal berisi kekurangannya tanpa menghubungi penyedia), lalu satu
 * permintaan kecil berisi pesan uji tanpa data kegiatan lewat jalur yang sama dengan permintaan pengguna (KlienLlm::tanya:
 * header, parameter per model, percobaan ulang, pemetaan galat). Hasil {berhasil, penyedia, nama, model, waktu_ms, pada,
 * jenis, judul, pesan, kurang} disimpan di cache per penyedia untuk versi Pengaturan AI saat diuji (`terakhir()`: begitu
 * pengaturan berubah, kembali "belum diuji"). Berhasil → catatan kegagalan KesehatanPenyediaAi dihapus (failover kembali
 * ke urutan biasa). Tidak memakai kuota harian pengguna (BatasAi) dan tidak terpengaruh simulasi kegagalan; dibatasi
 * MAKS_PER_MENIT per admin. Kunci API tidak pernah ikut hasil maupun log.
 */
final class UjiKoneksiAi
{
    public const MAKS_PER_MENIT = 6;

    /** Token jawaban uji (model yang selalu berpikir mendapat cadangan dari KlienLlmHttp). */
    public const TOKEN_UJI = 64;

    public const SISTEM = 'Ini uji koneksi aplikasi SIRABTOR. Jawab hanya dengan objek JSON {"status":"ok"} tanpa teks lain.';

    public const PESAN = 'Uji koneksi.';

    /** Lama hasil uji disimpan (selama pengaturan tidak berubah). */
    public const SIMPAN_HARI = 7;

    /**
     * @param  array{id?: int, role: string}  $pengguna
     * @return array{berhasil: bool, penyedia: string, nama: string, model: string|null, waktu_ms: int|null, pada: string, jenis: string|null, judul: string, pesan: string, kurang: list<string>}
     *
     * @throws AuthorizationException
     * @throws ValidationException Berkunci `penyedia` (tidak dikenal) atau `uji` (terlalu sering).
     */
    public static function uji(string $penyedia, array $pengguna): array
    {
        if (($pengguna['role'] ?? null) !== 'admin') {
            throw new AuthorizationException('Hanya admin yang bisa menguji koneksi AI.');
        }
        if (! isset(PenyediaAi::DAFTAR[$penyedia])) {
            throw ValidationException::withMessages(['penyedia' => 'Penyedia AI tidak dikenal.']);
        }
        if (($tunggu = self::tunggu($pengguna)) > 0) {
            throw ValidationException::withMessages(['uji' => "Uji koneksi terlalu sering — coba lagi dalam {$tunggu} detik."]);
        }

        $ai = app(PengaturanAiRepository::class)->ambil();
        $model = filled($ai['penyedia'][$penyedia]['model'] ?? null) ? (string) $ai['penyedia'][$penyedia]['model'] : null;
        $hasil = ['berhasil' => false, 'penyedia' => $penyedia, 'nama' => (string) PenyediaAi::nama($penyedia), 'model' => $model, 'waktu_ms' => null, 'pada' => now()->toIso8601String(), 'jenis' => null, 'judul' => '', 'pesan' => '', 'kurang' => []];
        $siap = AturanPenyediaAi::siap($ai, $penyedia);
        if (! $siap['siap']) {
            return ['judul' => 'Konfigurasi belum lengkap', 'pesan' => 'Lengkapi dulu: '.implode(', ', $siap['kurang']).'.', 'kurang' => $siap['kurang']] + $hasil;
        }

        RateLimiter::hit(self::kunciBatas($pengguna), 60);
        $mulai = hrtime(true);
        try {
            app(KlienLlm::class)->tanya($penyedia, self::SISTEM, self::PESAN, ['suhu' => ParameterAi::dari($ai)['suhu'], 'maks_token' => self::TOKEN_UJI]);
            $ms = intdiv(hrtime(true) - $mulai, 1_000_000);
            $hasil = ['berhasil' => true, 'waktu_ms' => $ms, 'judul' => 'Terhubung', 'pesan' => "Model {$model} menjawab dalam ".self::lama($ms).'.'] + $hasil;
            KesehatanPenyediaAi::catatBerhasil($penyedia);
        } catch (GagalPenyediaAi $e) {
            $hasil = ['jenis' => $e->jenis, 'waktu_ms' => intdiv(hrtime(true) - $mulai, 1_000_000), 'judul' => KegagalanAi::judul($e->jenis), 'pesan' => KegagalanAi::saranAdmin($e->jenis)] + $hasil;
        }

        Cache::put(self::kunciCache($penyedia), ['versi' => $ai['diperbarui_pada'], 'hasil' => $hasil], now()->addDays(self::SIMPAN_HARI));
        Log::info('Uji koneksi penyedia AI.', ['penyedia' => $penyedia, 'model' => $model, 'berhasil' => $hasil['berhasil'], 'jenis' => $hasil['jenis'], 'waktu_ms' => $hasil['waktu_ms']]);

        return $hasil;
    }

    /**
     * Hasil uji terakhir penyedia untuk pengaturan yang berlaku sekarang; null = belum diuji sejak pengaturan berubah.
     *
     * @return array<string, mixed>|null
     */
    public static function terakhir(string $penyedia): ?array
    {
        $catatan = Cache::get(self::kunciCache($penyedia));
        if (! is_array($catatan) || ($catatan['versi'] ?? null) !== app(PengaturanAiRepository::class)->ambil()['diperbarui_pada']) {
            return null;
        }

        return $catatan['hasil'];
    }

    /** Detik sampai admin boleh menguji lagi (0 = boleh). */
    public static function tunggu(array $pengguna): int
    {
        $kunci = self::kunciBatas($pengguna);

        return RateLimiter::tooManyAttempts($kunci, self::MAKS_PER_MENIT) ? max(1, RateLimiter::availableIn($kunci)) : 0;
    }

    /** 850 → "0,9 detik", 12400 → "12 detik". */
    public static function lama(int $ms): string
    {
        return $ms < 10_000 ? str_replace('.', ',', number_format($ms / 1000, 1)).' detik' : (int) round($ms / 1000).' detik';
    }

    private static function kunciBatas(array $pengguna): string
    {
        return 'uji-koneksi-ai:'.($pengguna['id'] ?? 'admin');
    }

    private static function kunciCache(string $penyedia): string
    {
        return 'sirabtor:uji-koneksi-ai:'.$penyedia;
    }
}
