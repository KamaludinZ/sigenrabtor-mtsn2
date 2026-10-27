<?php

namespace App\Support\Stub;

use App\Contracts\DaftarModelAi;
use RuntimeException;

/**
 * Daftar model tiruan: Ollama seolah sudah mengunduh beberapa model kecil (butuh alamat server), Claude memakai model
 * yang dikenal; OpenAI & Gemini belum punya daftar tiruan (nama model diketik dari dasbor penyedia).
 */
class StubDaftarModelAi implements DaftarModelAi
{
    public function daftar(string $penyedia, array $konfigurasi): array
    {
        return match ($penyedia) {
            'ollama' => blank($konfigurasi['base_url'] ?? null)
                ? throw new RuntimeException('Alamat server sekolah belum diisi — atur di tab Penyedia.')
                : [
                    ['nama' => 'qwen2.5:7b', 'ukuran' => '4,7 GB', 'keterangan' => 'Bahasa Indonesia cukup baik; butuh RAM ± 8 GB.', 'disarankan' => true],
                    ['nama' => 'qwen2.5:3b', 'ukuran' => '1,9 GB', 'keterangan' => 'Lebih cepat di server tanpa GPU; kalimat lebih sederhana.', 'disarankan' => false],
                    ['nama' => 'llama3.1:8b', 'ukuran' => '4,9 GB', 'keterangan' => 'Umum; kadang mencampur bahasa Inggris.', 'disarankan' => false],
                    ['nama' => 'gemma2:9b', 'ukuran' => '5,4 GB', 'keterangan' => 'Kalimat rapi; paling lambat di antara pilihan ini.', 'disarankan' => false],
                ],
            'claude' => ! ($konfigurasi['ada_kunci'] ?? false)
                ? throw new RuntimeException('Kunci API Claude belum diisi — atur di tab Penyedia.')
                : [
                    ['nama' => 'claude-sonnet-5-5', 'ukuran' => null, 'keterangan' => 'Seimbang: kalimat rapi dengan biaya sedang.', 'disarankan' => true],
                    ['nama' => 'claude-haiku-5-5', 'ukuran' => null, 'keterangan' => 'Paling cepat dan hemat.', 'disarankan' => false],
                    ['nama' => 'claude-opus-5-5', 'ukuran' => null, 'keterangan' => 'Paling cermat; biaya tertinggi.', 'disarankan' => false],
                ],
            default => [],
        };
    }
}
