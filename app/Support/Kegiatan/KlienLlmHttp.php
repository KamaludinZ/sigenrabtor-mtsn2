<?php

namespace App\Support\Kegiatan;

use App\Contracts\KlienLlm;
use App\Contracts\PengaturanAiRepository;
use App\Support\Pengaturan\AturanPenyediaAi;
use App\Support\Pengaturan\PenyediaAi;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Klien HTTP LLM (Laravel HTTP client, sinkron): Ollama `POST {alamat}/api/chat` (format json), Claude Messages API
 * `POST {alamat}/messages`, OpenAI `POST {alamat}/chat/completions` (json_object), Gemini
 * `POST {alamat}/models/{model}:generateContent` (application/json). Alamat cloud kosong = ALAMAT_RESMI; kunci API
 * dibuka dari `kunci_terenkripsi` (Crypt) dan tidak pernah dicatat; batas waktu = `batas_waktu` penyedia (BATAS_WAKTU).
 * Kegagalan dipetakan ke KegagalanAi::JENIS (`jenisGagal()`; koneksi putus → tidak_terhubung, waktu tunggu → waktu_habis)
 * dan dicatat di log tanpa isi permintaan maupun kunci.
 */
final class KlienLlmHttp implements KlienLlm
{
    /** Alamat resmi API cloud bila alamat API di Pengaturan AI dikosongkan. */
    public const ALAMAT_RESMI = [
        'claude' => 'https://api.anthropic.com/v1',
        'openai' => 'https://api.openai.com/v1',
        'gemini' => 'https://generativelanguage.googleapis.com/v1beta',
    ];

    public const VERSI_ANTHROPIC = '2023-06-01';

    public function tanya(string $penyedia, string $sistem, string $pesan, array $parameter): string
    {
        if (! isset(PenyediaAi::DAFTAR[$penyedia])) {
            throw $this->gagal('tidak_terhubung', $penyedia, 'penyedia tidak dikenal');
        }
        $konfigurasi = (array) (app(PengaturanAiRepository::class)->ambil()['penyedia'][$penyedia] ?? []);
        $model = trim((string) ($konfigurasi['model'] ?? ''));
        if ($model === '') {
            throw $this->gagal('model_tidak_ada', $penyedia, 'model belum diatur');
        }
        $kunci = PenyediaAi::DAFTAR[$penyedia]['butuh_kunci'] ? $this->kunci($penyedia, $konfigurasi) : null;
        $alamat = rtrim(trim((string) ($konfigurasi['base_url'] ?? '')), '/') ?: (self::ALAMAT_RESMI[$penyedia] ?? PenyediaAi::DAFTAR[$penyedia]['base_url']);
        [$min, $maks] = AturanPenyediaAi::BATAS_WAKTU;
        $batas = max($min, min($maks, (int) ($konfigurasi['batas_waktu'] ?? 60)));
        $http = Http::acceptJson()->asJson()->timeout($batas)->connectTimeout(min(10, $batas));
        $suhu = (float) $parameter['suhu'];
        $token = (int) $parameter['maks_token'];

        try {
            $jawaban = match ($penyedia) {
                'ollama' => $http->post("{$alamat}/api/chat", [
                    'model' => $model, 'stream' => false, 'format' => 'json',
                    'messages' => [['role' => 'system', 'content' => $sistem], ['role' => 'user', 'content' => $pesan]],
                    'options' => ['temperature' => $suhu, 'num_predict' => $token],
                ]),
                'claude' => $http->withHeaders(['x-api-key' => $kunci, 'anthropic-version' => self::VERSI_ANTHROPIC])->post("{$alamat}/messages", [
                    'model' => $model, 'max_tokens' => $token, 'temperature' => $suhu, 'system' => $sistem,
                    'messages' => [['role' => 'user', 'content' => $pesan]],
                ]),
                'openai' => $http->withToken((string) $kunci)->post("{$alamat}/chat/completions", [
                    'model' => $model, 'temperature' => $suhu, 'max_completion_tokens' => $token, 'response_format' => ['type' => 'json_object'],
                    'messages' => [['role' => 'system', 'content' => $sistem], ['role' => 'user', 'content' => $pesan]],
                ]),
                'gemini' => $http->withHeaders(['x-goog-api-key' => $kunci])->post("{$alamat}/models/".rawurlencode((string) preg_replace('#^models/#', '', $model)).':generateContent', [
                    'systemInstruction' => ['parts' => [['text' => $sistem]]],
                    'contents' => [['role' => 'user', 'parts' => [['text' => $pesan]]]],
                    'generationConfig' => ['temperature' => $suhu, 'maxOutputTokens' => $token, 'responseMimeType' => 'application/json'],
                ]),
            };
        } catch (ConnectionException $e) {
            throw $this->gagal(preg_match('/timed?\s?out|cURL error 28/i', $e->getMessage()) ? 'waktu_habis' : 'tidak_terhubung', $penyedia, 'koneksi gagal');
        }

        if ($jawaban->failed()) {
            throw $this->gagal(self::jenisGagal($jawaban), $penyedia, 'HTTP '.$jawaban->status().' '.self::kodeGalat($jawaban));
        }
        $teks = self::teksJawaban($penyedia, $jawaban);
        if (trim($teks) === '') {
            throw $this->gagal('jawaban_rusak', $penyedia, 'jawaban tanpa teks');
        }

        return $teks;
    }

    /** Jenis kegagalan (KegagalanAi::JENIS) dari respons HTTP gagal penyedia mana pun. */
    public static function jenisGagal(Response $jawaban): string
    {
        $status = $jawaban->status();
        $isi = mb_strtolower($jawaban->body());

        return match (true) {
            in_array($status, [401, 403], true), str_contains($isi, 'api_key_invalid'), str_contains($isi, 'api key not valid') => 'kunci_ditolak',
            $status === 404, str_contains($isi, 'model_not_found'), $status === 400 && (bool) preg_match('/model\b[^.]*\b(not found|does not exist|tidak ditemukan)/u', $isi) => 'model_tidak_ada',
            in_array($status, [402, 429, 529], true) => 'kuota_penyedia',
            in_array($status, [408, 504], true) => 'waktu_habis',
            $status >= 500 => 'tidak_terhubung',
            default => 'jawaban_rusak',
        };
    }

    /** Teks jawaban model dari respons sukses tiap penyedia ('' bila tidak ada). */
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

        return mb_substr(is_scalar($kode) ? (string) $kode : '', 0, 60);
    }

    /** @param  array<string, mixed>  $konfigurasi */
    private function kunci(string $penyedia, array $konfigurasi): string
    {
        try {
            $kunci = filled($konfigurasi['kunci_terenkripsi'] ?? null) ? Crypt::decryptString((string) $konfigurasi['kunci_terenkripsi']) : '';
        } catch (DecryptException) {
            $kunci = '';
        }
        if ($kunci === '') {
            throw $this->gagal('kunci_ditolak', $penyedia, 'kunci API belum tersimpan atau tidak bisa dibuka');
        }

        return $kunci;
    }

    private function gagal(string $jenis, string $penyedia, string $rincian): GagalPenyediaAi
    {
        Log::warning('Permintaan ke penyedia AI gagal.', ['penyedia' => $penyedia, 'jenis' => $jenis, 'rincian' => trim($rincian)]);

        return new GagalPenyediaAi($jenis, $penyedia);
    }
}
