<?php

namespace App\Support\Kegiatan;

use App\Contracts\PengaturanAiRepository;
use App\Support\Pengaturan\AturanPenyediaAi;
use App\Support\Pengaturan\PenyediaAi;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sambungan ke satu penyedia AI dari konfigurasi Pengaturan AI (`penyedia.{kunci}`): alamat (kosong = ALAMAT_RESMI
 * untuk cloud / alamat bawaan Ollama), model, batas waktu (AturanPenyediaAi::BATAS_WAKTU, dipotong sisa waktu permintaan
 * web — `batasEfektif()`), dan kunci API yang dibuka dari `kunci_terenkripsi` hanya untuk header permintaan: tidak pernah
 * dikembalikan, dicatat, ditampilkan di jejak galat (#[SensitiveParameter]), maupun di var_dump (`__debugInfo`).
 * Dipakai KlienLlmHttp (menyusun kalimat), DaftarModelAiHttp (daftar model), dan uji koneksi (UjiKoneksiAi lewat
 * KlienLlm). Konfigurasi belum lengkap → GagalPenyediaAi tanpa menghubungi penyedia.
 */
final class SambunganPenyediaAi
{
    /** Alamat resmi API cloud bila alamat API di Pengaturan AI dikosongkan. */
    public const ALAMAT_RESMI = [
        'claude' => 'https://api.anthropic.com/v1',
        'openai' => 'https://api.openai.com/v1',
        'gemini' => 'https://generativelanguage.googleapis.com/v1beta',
    ];

    public const VERSI_ANTHROPIC = '2023-06-01';

    /** Batas waktu membuka koneksi (detik). */
    public const BATAS_SAMBUNG = 10;

    private function __construct(
        public readonly string $penyedia,
        public readonly string $model,
        public readonly string $alamat,
        public readonly int $batas,
        #[\SensitiveParameter] private readonly ?string $kunci,
    ) {}

    /**
     * @param  array<string, mixed>|null  $konfigurasi  null = dibaca dari Pengaturan AI.
     *
     * @throws GagalPenyediaAi Penyedia tidak dikenal, model belum diatur ($perluModel), atau kunci API tidak tersedia.
     */
    public static function untuk(string $penyedia, ?array $konfigurasi = null, bool $perluModel = true): self
    {
        if (! isset(PenyediaAi::DAFTAR[$penyedia])) {
            throw self::gagal('tidak_terhubung', $penyedia, 'penyedia tidak dikenal');
        }
        $konfigurasi ??= (array) (app(PengaturanAiRepository::class)->ambil()['penyedia'][$penyedia] ?? []);
        $model = preg_replace('#^models/#', '', trim((string) ($konfigurasi['model'] ?? ''))) ?? '';
        if ($perluModel && $model === '') {
            throw self::gagal('model_tidak_ada', $penyedia, 'model belum diatur');
        }
        $kunci = PenyediaAi::DAFTAR[$penyedia]['butuh_kunci'] ? self::kunci($penyedia, $konfigurasi) : null;
        $alamat = rtrim(trim((string) ($konfigurasi['base_url'] ?? '')), '/') ?: (self::ALAMAT_RESMI[$penyedia] ?? (string) PenyediaAi::DAFTAR[$penyedia]['base_url']);
        [$min, $maks] = AturanPenyediaAi::BATAS_WAKTU;

        return new self($penyedia, $model, $alamat, max($min, min($maks, (int) ($konfigurasi['batas_waktu'] ?? 60))), $kunci);
    }

    /**
     * Permintaan HTTP berkepala autentikasi penyedia ini, dengan batas waktu $detik (bawaan: batas penyedia).
     */
    public function http(?float $detik = null): PendingRequest
    {
        $detik = max(1, (int) ceil($detik ?? $this->batas));
        $http = Http::acceptJson()->asJson()->timeout($detik)->connectTimeout(min(self::BATAS_SAMBUNG, $detik))
            ->withUserAgent('SIRABTOR/'.config('sigenrabtor.aplikasi.versi', '1'));

        return match ($this->penyedia) {
            'claude' => $http->withHeaders(['x-api-key' => (string) $this->kunci, 'anthropic-version' => self::VERSI_ANTHROPIC]),
            'openai' => $http->withToken((string) $this->kunci),
            'gemini' => $http->withHeaders(['x-goog-api-key' => (string) $this->kunci]),
            default => $http,
        };
    }

    /**
     * Batas waktu satu permintaan AI (detik): batas penyedia, dipotong sisa waktu permintaan web agar jawaban (atau
     * pesan galatnya) sampai sebelum PHP/nginx memutus permintaan — termasuk saat penyedia cadangan dicoba sesudah
     * penyedia aktif gagal.
     */
    public function batasEfektif(): float
    {
        $sisa = self::sisaPermintaan();

        return $sisa === null ? (float) $this->batas : max(0.0, min((float) $this->batas, $sisa));
    }

    /** Sisa waktu permintaan web (detik) menurut `sigenrabtor.ai.batas_permintaan_detik`; null di konsol/antrean. */
    public static function sisaPermintaan(): ?float
    {
        if (app()->runningInConsole() || ! defined('LARAVEL_START')) {
            return null;
        }

        return (float) config('sigenrabtor.ai.batas_permintaan_detik', 330) - (microtime(true) - LARAVEL_START);
    }

    /**
     * Kegagalan berjenis (KegagalanAi::JENIS) yang dicatat di log tanpa isi permintaan maupun kunci.
     *
     * @param  array<string, string>  $tambahan  Mis. id permintaan penyedia.
     */
    public static function gagal(string $jenis, string $penyedia, string $rincian, array $tambahan = []): GagalPenyediaAi
    {
        Log::warning('Permintaan ke penyedia AI gagal.', ['penyedia' => $penyedia, 'jenis' => $jenis, 'rincian' => trim($rincian)] + $tambahan);

        return new GagalPenyediaAi($jenis, $penyedia);
    }

    /** @return array<string, mixed> */
    public function __debugInfo(): array
    {
        return ['penyedia' => $this->penyedia, 'model' => $this->model, 'alamat' => $this->alamat, 'batas' => $this->batas, 'kunci' => $this->kunci === null ? null : '[disembunyikan]'];
    }

    /** @param  array<string, mixed>  $konfigurasi */
    private static function kunci(string $penyedia, array $konfigurasi): string
    {
        try {
            $kunci = filled($konfigurasi['kunci_terenkripsi'] ?? null) ? Crypt::decryptString((string) $konfigurasi['kunci_terenkripsi']) : '';
        } catch (DecryptException) {
            $kunci = '';
        }
        if ($kunci === '') {
            throw self::gagal('kunci_ditolak', $penyedia, 'kunci API belum tersimpan atau tidak bisa dibuka');
        }

        return $kunci;
    }
}
