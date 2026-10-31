<?php

namespace App\Support\Kegiatan;

use App\Contracts\KlienLlm;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

/**
 * Klien HTTP LLM (Laravel HTTP client, sinkron) di atas SambunganPenyediaAi: Ollama `POST {alamat}/api/chat` (format
 * json), Claude Messages API `POST {alamat}/messages`, OpenAI `POST {alamat}/chat/completions` (json_object), Gemini
 * `POST {alamat}/models/{model}:generateContent` (application/json).
 *
 * Bentuk permintaan mengikuti KemampuanModelAi: suhu hanya untuk model yang menerimanya; model yang selalu berpikir diberi
 * CADANGAN_BERPIKIR token + upaya berpikir rendah (Claude `output_config.effort`, OpenAI `reasoning_effort`). Parameter
 * opsional yang tetap ditolak model (galat 400 yang menyebut namanya) dibuang lalu permintaan dikirim ulang. Galat
 * sementara (COBA_ULANG_STATUS, koneksi putus) dicoba ulang paling banyak MAKS_COBA_ULANG kali dengan jeda berlipat
 * (Retry-After dihormati sampai JEDA_MAKS_DETIK) selama masih dalam batas waktu penyedia — kuota yang benar-benar habis
 * dan waktu tunggu yang sudah habis tidak dicoba ulang. Jawaban sukses yang ditolak model/filter konten →
 * `permintaan_ditolak`; jawaban terpotong batas token dicatat agar admin bisa menaikkan Panjang jawaban.
 *
 * Kegagalan dipetakan ke KegagalanAi::JENIS (`jenisGagal()`) dan dicatat tanpa isi permintaan maupun kunci (id permintaan
 * penyedia ikut dicatat untuk penelusuran ke dukungan penyedia).
 */
final class KlienLlmHttp implements KlienLlm
{
    /** Status HTTP sementara yang dicoba ulang (529 = Anthropic overloaded). */
    public const COBA_ULANG_STATUS = [408, 409, 429, 500, 502, 503, 504, 529];

    public const MAKS_COBA_ULANG = 2;

    /** Jeda dasar percobaan ulang (milidetik), berlipat dua tiap percobaan. */
    public const JEDA_DASAR_MS = 1000;

    /** Retry-After yang lebih lama dari ini tidak ditunggu (langsung gagal, penyedia cadangan bisa mengambil alih). */
    public const JEDA_MAKS_DETIK = 20;

    /** Sisa waktu minimal (detik) untuk mengirim permintaan berikutnya. */
    public const SISA_MIN_DETIK = 3;

    /** Token tambahan untuk model yang selalu berpikir: token berpikir ikut dihitung dalam batas token jawaban. */
    public const CADANGAN_BERPIKIR = 4096;

    /** Upaya berpikir model yang selalu berpikir — menyusun kalimat pendek tidak butuh penalaran panjang. */
    public const UPAYA_BERPIKIR = 'low';

    /** Isyarat di badan galat 429/402 bahwa kuota/saldo benar-benar habis (mencoba ulang tidak membantu). */
    private const KUOTA_HABIS = ['insufficient_quota', 'credit balance', 'billing'];

    public function tanya(string $penyedia, string $sistem, string $pesan, array $parameter): string
    {
        $sambungan = SambunganPenyediaAi::untuk($penyedia);
        [$jalur, $isi, $opsional] = self::permintaan($sambungan, $sistem, $pesan, $parameter);
        $jawaban = $this->kirim($sambungan, $jalur, $isi, $opsional);

        if (($tolak = self::penolakan($penyedia, $jawaban)) !== null) {
            throw SambunganPenyediaAi::gagal('permintaan_ditolak', $penyedia, $tolak, self::idPermintaan($jawaban));
        }
        $teks = self::teksJawaban($penyedia, $jawaban);
        $terpotong = self::terpotong($penyedia, $jawaban);
        if (trim($teks) === '') {
            throw SambunganPenyediaAi::gagal('jawaban_rusak', $penyedia, $terpotong ? 'batas token habis sebelum ada teks jawaban' : 'jawaban tanpa teks', self::idPermintaan($jawaban));
        }
        if ($terpotong) {
            Log::notice('Jawaban AI terpotong batas token — naikkan Panjang jawaban di Pengaturan AI bila jawaban sering tidak terbaca.', ['penyedia' => $penyedia, 'model' => $sambungan->model]);
        }

        return $teks;
    }

    /** Jenis kegagalan (KegagalanAi::JENIS) dari respons HTTP gagal penyedia mana pun. */
    public static function jenisGagal(Response $jawaban): string
    {
        $status = $jawaban->status();
        $isi = mb_strtolower($jawaban->body());
        $memuat = fn (array $kata) => collect($kata)->contains(fn (string $k) => str_contains($isi, $k));

        return match (true) {
            in_array($status, [401, 403], true), $memuat(['api_key_invalid', 'api key not valid', 'invalid x-api-key', 'invalid_api_key']) => 'kunci_ditolak',
            $status === 402, $memuat(self::KUOTA_HABIS) => 'kuota_penyedia',
            $status === 404, str_contains($isi, 'model_not_found'), $status === 400 && (bool) preg_match('/model\b[^.]*\b(not found|does not exist|tidak ditemukan)/u', $isi) => 'model_tidak_ada',
            $status === 429 => 'kuota_penyedia',
            in_array($status, [408, 504], true) => 'waktu_habis',
            $status >= 500 => 'penyedia_sibuk',
            in_array($status, [400, 409, 413, 422], true) => 'permintaan_ditolak',
            default => 'jawaban_rusak',
        };
    }

    /**
     * Jalur, isi, dan parameter opsional (kata di pesan galat → jalur di isi) satu permintaan.
     *
     * @param  array{suhu: float, maks_token: int}  $parameter
     * @return array{0: string, 1: array<string, mixed>, 2: array<string, string>}
     */
    public static function permintaan(SambunganPenyediaAi $sambungan, string $sistem, string $pesan, array $parameter): array
    {
        $penyedia = $sambungan->penyedia;
        $model = $sambungan->model;
        $berpikir = KemampuanModelAi::selaluBerpikir($penyedia, $model);
        $suhu = KemampuanModelAi::terimaSuhu($penyedia, $model) ? (float) $parameter['suhu'] : null;
        $token = (int) $parameter['maks_token'] + ($berpikir ? self::CADANGAN_BERPIKIR : 0);
        $tanpaKosong = fn (array $isi) => array_filter($isi, fn (mixed $nilai) => $nilai !== null);

        return match ($penyedia) {
            'ollama' => ['/api/chat', [
                'model' => $model, 'stream' => false, 'format' => 'json',
                'messages' => [['role' => 'system', 'content' => $sistem], ['role' => 'user', 'content' => $pesan]],
                'options' => ['temperature' => (float) $parameter['suhu'], 'num_predict' => $token],
            ], []],
            'claude' => ['/messages', $tanpaKosong([
                'model' => $model, 'max_tokens' => $token, 'temperature' => $suhu,
                'output_config' => $berpikir ? ['effort' => self::UPAYA_BERPIKIR] : null,
                'system' => $sistem,
                'messages' => [['role' => 'user', 'content' => $pesan]],
            ]), ['temperature' => 'temperature', 'output_config' => 'output_config', 'effort' => 'output_config']],
            'openai' => ['/chat/completions', $tanpaKosong([
                'model' => $model, 'temperature' => $suhu, 'max_completion_tokens' => $token,
                'reasoning_effort' => $berpikir ? self::UPAYA_BERPIKIR : null,
                'response_format' => ['type' => 'json_object'],
                'messages' => [['role' => 'system', 'content' => $sistem], ['role' => 'user', 'content' => $pesan]],
            ]), ['temperature' => 'temperature', 'reasoning_effort' => 'reasoning_effort', 'response_format' => 'response_format']],
            'gemini' => ['/models/'.rawurlencode($model).':generateContent', [
                'systemInstruction' => ['parts' => [['text' => $sistem]]],
                'contents' => [['role' => 'user', 'parts' => [['text' => $pesan]]]],
                'generationConfig' => $tanpaKosong(['temperature' => $suhu, 'maxOutputTokens' => $token, 'responseMimeType' => 'application/json']),
            ], ['temperature' => 'generationConfig.temperature', 'responsemimetype' => 'generationConfig.responseMimeType', 'response_mime_type' => 'generationConfig.responseMimeType']],
        };
    }

    /**
     * Kirim dengan percobaan ulang untuk galat sementara dan tanpa parameter opsional yang ditolak model.
     *
     * @param  array<string, mixed>  $isi
     * @param  array<string, string>  $opsional
     *
     * @throws GagalPenyediaAi
     */
    private function kirim(SambunganPenyediaAi $sambungan, string $jalur, array $isi, array $opsional): Response
    {
        $penyedia = $sambungan->penyedia;
        $batas = $sambungan->batasEfektif();
        $mulai = microtime(true);
        $percobaan = 0;

        while (true) {
            $sisa = $batas - (microtime(true) - $mulai);
            if ($sisa < self::SISA_MIN_DETIK) {
                throw SambunganPenyediaAi::gagal('waktu_habis', $penyedia, 'batas waktu permintaan habis'.($percobaan > 0 ? " setelah {$percobaan} percobaan ulang" : ''));
            }

            try {
                $jawaban = $sambungan->http($sisa)->post($sambungan->alamat.$jalur, $isi);
            } catch (ConnectionException $e) {
                $habis = (bool) preg_match('/timed?\s?out|cURL error 28/i', $e->getMessage());
                if (! $habis && $this->tungguCobaLagi($percobaan, null, $mulai, $batas)) {
                    $percobaan++;

                    continue;
                }

                throw SambunganPenyediaAi::gagal($habis ? 'waktu_habis' : 'tidak_terhubung', $penyedia, 'koneksi gagal'.($percobaan > 0 ? " setelah {$percobaan} percobaan ulang" : ''));
            }

            if ($jawaban->successful()) {
                return $jawaban;
            }
            if ($jawaban->status() === 400 && ($kata = self::parameterDitolak($jawaban, $opsional, $isi)) !== null) {
                Arr::forget($isi, $opsional[$kata]);
                Log::info('Parameter permintaan AI ditolak model — dikirim ulang tanpa parameter itu.', ['penyedia' => $penyedia, 'model' => $sambungan->model, 'parameter' => $kata]);

                continue;
            }
            if (self::sementara($jawaban) && $this->tungguCobaLagi($percobaan, $jawaban->header('Retry-After'), $mulai, $batas)) {
                $percobaan++;

                continue;
            }

            throw SambunganPenyediaAi::gagal(self::jenisGagal($jawaban), $penyedia, 'HTTP '.$jawaban->status().' '.self::kodeGalat($jawaban).($percobaan > 0 ? " setelah {$percobaan} percobaan ulang" : ''), self::idPermintaan($jawaban));
        }
    }

    /** Tunggu sebelum mencoba lagi bila masih ada jatah percobaan & waktu; false = jangan coba lagi. */
    private function tungguCobaLagi(int $percobaan, ?string $retryAfter, float $mulai, float $batas): bool
    {
        if ($percobaan >= self::MAKS_COBA_ULANG) {
            return false;
        }
        $jeda = is_numeric($retryAfter) ? max(0.0, (float) $retryAfter) : self::JEDA_DASAR_MS / 1000 * (2 ** $percobaan);
        if ($jeda > self::JEDA_MAKS_DETIK || (microtime(true) - $mulai) + $jeda + self::SISA_MIN_DETIK > $batas) {
            return false;
        }
        Sleep::for((int) round($jeda * 1000))->milliseconds();

        return true;
    }

    /** Galat sementara yang layak dicoba ulang (bukan kuota/saldo yang benar-benar habis). */
    private static function sementara(Response $jawaban): bool
    {
        $isi = mb_strtolower($jawaban->body());

        return in_array($jawaban->status(), self::COBA_ULANG_STATUS, true)
            && ! collect(self::KUOTA_HABIS)->contains(fn (string $kata) => str_contains($isi, $kata));
    }

    /**
     * Parameter opsional yang disebut pesan galat 400 dan masih ada di isi permintaan; null = bukan galat parameter.
     *
     * @param  array<string, string>  $opsional
     * @param  array<string, mixed>  $isi
     */
    private static function parameterDitolak(Response $jawaban, array $opsional, array $isi): ?string
    {
        $galat = $jawaban->json('error');
        $pesan = mb_strtolower(is_array($galat) ? (string) json_encode($galat) : (is_string($galat) ? $galat : $jawaban->body()));

        foreach ($opsional as $kata => $jalur) {
            if (Arr::has($isi, $jalur) && str_contains($pesan, mb_strtolower($kata))) {
                return $kata;
            }
        }

        return null;
    }

    /** Alasan model/filter penyedia menolak menjawab pada respons sukses; null = tidak ditolak. */
    private static function penolakan(string $penyedia, Response $jawaban): ?string
    {
        $alasanGemini = $jawaban->json('promptFeedback.blockReason') ?? (in_array($jawaban->json('candidates.0.finishReason'), ['SAFETY', 'RECITATION', 'BLOCKLIST', 'PROHIBITED_CONTENT', 'SPII'], true) ? $jawaban->json('candidates.0.finishReason') : null);

        return match ($penyedia) {
            'claude' => $jawaban->json('stop_reason') === 'refusal' ? trim('refusal '.self::skalar($jawaban->json('stop_details.category'))) : null,
            'openai' => $jawaban->json('choices.0.finish_reason') === 'content_filter' || filled($jawaban->json('choices.0.message.refusal')) ? 'content_filter' : null,
            'gemini' => filled($alasanGemini) ? 'diblokir '.self::skalar($alasanGemini) : null,
            default => null,
        };
    }

    /** Jawaban berhenti karena batas token. */
    private static function terpotong(string $penyedia, Response $jawaban): bool
    {
        return match ($penyedia) {
            'claude' => $jawaban->json('stop_reason') === 'max_tokens',
            'openai' => $jawaban->json('choices.0.finish_reason') === 'length',
            'gemini' => $jawaban->json('candidates.0.finishReason') === 'MAX_TOKENS',
            'ollama' => $jawaban->json('done_reason') === 'length',
            default => false,
        };
    }

    /** Teks jawaban model dari respons sukses tiap penyedia ('' bila tidak ada; blok berpikir dilewati). */
    private static function teksJawaban(string $penyedia, Response $jawaban): string
    {
        $teks = fn (mixed $nilai) => is_string($nilai) ? $nilai : '';

        return match ($penyedia) {
            'ollama' => $teks($jawaban->json('message.content')),
            'openai' => $teks($jawaban->json('choices.0.message.content')),
            'claude' => implode('', array_map(fn (mixed $blok) => is_array($blok) && ($blok['type'] ?? '') === 'text' ? $teks($blok['text'] ?? null) : '', (array) $jawaban->json('content', []))),
            'gemini' => implode('', array_map(fn (mixed $bagian) => is_array($bagian) && ! ($bagian['thought'] ?? false) ? $teks($bagian['text'] ?? null) : '', (array) $jawaban->json('candidates.0.content.parts', []))),
            default => '',
        };
    }

    /** Kode/jenis galat penyedia untuk log (bukan pesannya: pesan galat kunci bisa memuat sebagian kunci). */
    private static function kodeGalat(Response $jawaban): string
    {
        $galat = $jawaban->json('error');
        $kode = is_array($galat) ? ($galat['type'] ?? $galat['code'] ?? $galat['status'] ?? '') : '';

        return mb_substr(self::skalar($kode), 0, 60);
    }

    /** Id permintaan dari penyedia (Anthropic `request-id`, OpenAI `x-request-id`) untuk log. */
    private static function idPermintaan(Response $jawaban): array
    {
        $id = $jawaban->header('request-id') ?: $jawaban->header('x-request-id');

        return $id !== '' ? ['permintaan' => mb_substr($id, 0, 80)] : [];
    }

    private static function skalar(mixed $nilai): string
    {
        return is_scalar($nilai) ? (string) $nilai : '';
    }
}
