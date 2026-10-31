<?php

namespace App\Support\Kegiatan;

/**
 * Kemampuan model per penyedia yang menentukan bentuk permintaan KlienLlmHttp:
 * - suhu (`temperature`) hanya dikirim ke model yang menerimanya — Claude generasi 4.7 ke atas (Opus 4.7/4.8/5/5.5,
 *   Sonnet 5/5.5, Haiku 5.5, Fable) menolaknya dengan galat 400, begitu pula model penalaran OpenAI (o-series, gpt-5);
 * - model yang selalu berpikir sebelum menjawab (Claude generasi 5 ke atas, penalaran OpenAI, Gemini 2.5 ke atas,
 *   gpt-oss di Ollama) diberi cadangan token & upaya berpikir rendah agar token tidak habis sebelum JSON jawaban selesai:
 *   Claude `output_config.effort`, OpenAI `reasoning_effort`, Gemini `thinkingConfig` (2.5: `thinkingBudget`
 *   ANGGARAN_BERPIKIR_GEMINI — sah untuk 2.5 Pro/Flash/Flash-Lite; 3 ke atas: `thinkingLevel` LOW), Ollama `think`
 *   (`berpikirOllama()`: false = mode berpikir dimatikan untuk qwen3/deepseek-r1/…; gpt-oss tidak bisa dimatikan → "low").
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
    public const BERPIKIR_GEMINI = '/^(?:models\/)?gemini-(?:2\.5|[3-9]|[1-9]\d)/i';

    /** Model Gemini generasi 3 ke atas: kedalaman berpikir lewat `thinkingLevel` (2.5 lewat `thinkingBudget`). */
    public const LEVEL_GEMINI = '/^(?:models\/)?gemini-(?:[3-9]|[1-9]\d)/i';

    /** Batas token berpikir Gemini 2.5 (dalam rentang sah 2.5 Pro 128–32768, Flash 0–24576, Flash-Lite 512–24576). */
    public const ANGGARAN_BERPIKIR_GEMINI = 1024;

    /** Model Ollama yang bisa berpikir (boleh berawalan namespace, mis. `library/qwen3:8b`). */
    public const BERPIKIR_OLLAMA = '/^(?:[\w.-]+\/)*(?:qwen3|deepseek-r1|magistral|phi4-reasoning|qwq|cogito|gpt-oss)/i';

    /** Model Ollama yang selalu berpikir — hanya kedalamannya yang bisa diatur. */
    public const SELALU_BERPIKIR_OLLAMA = '/^(?:[\w.-]+\/)*gpt-oss/i';

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
            'ollama' => (bool) preg_match(self::SELALU_BERPIKIR_OLLAMA, $model),
            default => false,
        };
    }

    /** Nilai `think` Ollama: null = model biasa (tanpa parameter), false = mode berpikir dimatikan, 'low' = gpt-oss. */
    public static function berpikirOllama(string $model): false|string|null
    {
        return match (true) {
            (bool) preg_match(self::SELALU_BERPIKIR_OLLAMA, $model) => 'low',
            (bool) preg_match(self::BERPIKIR_OLLAMA, $model) => false,
            default => null,
        };
    }

    /**
     * `thinkingConfig` Gemini untuk model yang berpikir bawaan; null = tanpa pengaturan.
     *
     * @return array{thinkingLevel: string}|array{thinkingBudget: int}|null
     */
    public static function berpikirGemini(string $model): ?array
    {
        return match (true) {
            (bool) preg_match(self::LEVEL_GEMINI, $model) => ['thinkingLevel' => 'LOW'],
            (bool) preg_match(self::BERPIKIR_GEMINI, $model) => ['thinkingBudget' => self::ANGGARAN_BERPIKIR_GEMINI],
            default => null,
        };
    }
}
