<?php

namespace App\Support\Pengaturan;

/**
 * Penyedia AI yang bisa dipilih admin (PRD §9 & §21, F24): Ollama di server sekolah atau API cloud. Tiap penyedia membawa
 * jenis (lokal/cloud), perlu kunci API atau tidak, alamat bawaan, contoh model, serta kelebihan & kekurangannya.
 */
final class PenyediaAi
{
    public const DAFTAR = [
        'ollama' => [
            'nama' => 'Ollama (server sekolah)', 'jenis' => 'lokal', 'butuh_kunci' => false, 'base_url' => 'http://localhost:11434',
            'contoh_model' => 'mis. qwen2.5:7b', 'kelebihan' => 'Gratis; data tidak keluar dari madrasah.', 'kekurangan' => 'Tanpa GPU lebih lambat; kualitas bahasa di bawah model besar.',
        ],
        'claude' => [
            'nama' => 'Claude (Anthropic)', 'jenis' => 'cloud', 'butuh_kunci' => true, 'base_url' => null,
            'contoh_model' => 'mis. claude-sonnet-5-5', 'kelebihan' => 'Cepat; kalimat lebih rapi.', 'kekurangan' => 'Berbayar per pemakaian; data kegiatan dikirim ke penyedia.',
        ],
        'openai' => [
            'nama' => 'OpenAI', 'jenis' => 'cloud', 'butuh_kunci' => true, 'base_url' => null,
            'contoh_model' => 'nama model dari dasbor penyedia', 'kelebihan' => 'Cepat; kalimat lebih rapi.', 'kekurangan' => 'Berbayar per pemakaian; data kegiatan dikirim ke penyedia.',
        ],
        'gemini' => [
            'nama' => 'Gemini (Google)', 'jenis' => 'cloud', 'butuh_kunci' => true, 'base_url' => null,
            'contoh_model' => 'mis. gemini-2.5-flash', 'kelebihan' => 'Cepat; ada tingkat gratis.',
            'kekurangan' => 'Data kegiatan dikirim ke penyedia; di tingkat gratis dipakai Google untuk meningkatkan produk — aktifkan penagihan untuk data madrasah.',
        ],
    ];

    public const JENIS = ['lokal' => 'Server sekolah', 'cloud' => 'API cloud'];

    /** Nama tampil penyedia; null = belum dipilih. */
    public static function nama(?string $kunci): ?string
    {
        return $kunci === null ? null : (self::DAFTAR[$kunci]['nama'] ?? $kunci);
    }
}
