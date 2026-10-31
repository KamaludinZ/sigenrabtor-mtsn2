<?php

namespace App\Support\Kegiatan;

/**
 * Kemampuan model per penyedia yang menentukan bentuk permintaan KlienLlmHttp:
 * - suhu (`temperature`) hanya dikirim ke model yang menerimanya — Claude generasi 4.7 ke atas (Opus 4.7/4.8/5/5.5,
 *   Sonnet 5/5.5, Haiku 5.5, Fable) menolaknya dengan galat 400, begitu pula model penalaran OpenAI (o-series, gpt-5);
 * - model yang selalu berpikir sebelum menjawab (Claude generasi 5 ke atas, penalaran OpenAI, Gemini 2.5 ke atas) diberi
 *   cadangan token & upaya berpikir rendah agar token tidak habis sebelum JSON jawaban selesai ditulis.
 * Nama model yang tidak dikenal diperlakukan seperti model terbaru (tanpa suhu). Bila penyedia tetap menolak parameter
 * opsional, KlienLlmHttp membuangnya lalu mengirim ulang (`parameterDitolak`).
 */
final class KemampuanModelAi
{
    /** Model Claude yang masih menerima `temperature`: Claude 3, dan Opus/Sonnet/Haiku 4.0–4.6 (alias maupun ID bertanggal). */
    public const SUHU_CLAUDE = '/^claude-(?:3|(?:opus|sonnet|haiku)-4-(?:[0-6](?:-|$)|20\d{6}))/';

    /** Model Claude yang selalu berpikir (thinking menyala bawaan): generasi 5 ke atas. */
    public const BERPIKIR_CLAUDE = '/^claude-[a-z]+-(?:[5-9]|[1-9]\d)(?:-|$)/';

    /** Model penalaran OpenAI: tanpa `temperature`, memakai `reasoning_effort`. */
    public const PENALARAN_OPENAI = '/^(?:o\d|gpt-5)/i';

    /** Model Gemini yang berpikir bawaan (token berpikir ikut `maxOutputTokens`). */
    public const BERPIKIR_GEMINI = '/^(?:models\/)?gemini-(?:2\.5|[3-9])/i';

    public static function terimaSuhu(string $penyedia, string $model): bool
    {
        return match ($penyedia) {
            'claude' => (bool) preg_match(self::SUHU_CLAUDE, $model),
            'openai' => ! preg_match(self::PENALARAN_OPENAI, $model),
            default => true,
        };
    }

    public static function selaluBerpikir(string $penyedia, string $model): bool
    {
        return match ($penyedia) {
            'claude' => (bool) preg_match(self::BERPIKIR_CLAUDE, $model),
            'openai' => (bool) preg_match(self::PENALARAN_OPENAI, $model),
            'gemini' => (bool) preg_match(self::BERPIKIR_GEMINI, $model),
            default => false,
        };
    }
}
