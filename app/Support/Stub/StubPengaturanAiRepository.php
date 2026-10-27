<?php

namespace App\Support\Stub;

use App\Contracts\PengaturanAiRepository;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Pengaturan AI tiruan di berkas JSON (disk "local"); selama belum diisi: tanpa penyedia (saran tiruan), Ollama dengan
 * alamat bawaan, batas 20 permintaan per akun per hari.
 */
class StubPengaturanAiRepository implements PengaturanAiRepository
{
    public function ambil(): array
    {
        $isi = Storage::disk('local')->exists($this->berkas()) ? json_decode((string) Storage::disk('local')->get($this->berkas()), true) : null;
        $bawaan = self::bawaan();
        if (! is_array($isi)) {
            return $bawaan;
        }

        $penyedia = $bawaan['penyedia'];
        foreach ((array) ($isi['penyedia'] ?? []) as $kunci => $p) {
            $penyedia[$kunci] = array_merge($penyedia[$kunci] ?? [], (array) $p);
        }

        return ['penyedia' => $penyedia] + array_intersect_key($isi, $bawaan) + $bawaan;
    }

    public function simpan(array $data): void
    {
        $tersimpan = Storage::disk('local')->put($this->berkas(), json_encode(['diperbarui_pada' => now()->format('Y-m-d\TH:i:s.uP')] + $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        if (! $tersimpan) {
            throw new RuntimeException('Pengaturan AI gagal disimpan. Coba lagi.');
        }
    }

    /** @return array{provider_aktif: null, provider_cadangan: null, penyedia: array<string, array<string, mixed>>, batas_per_user_per_hari: int, diperbarui_pada: null} */
    public static function bawaan(): array
    {
        return [
            'provider_aktif' => null,
            'provider_cadangan' => null,
            'penyedia' => ['ollama' => ['base_url' => 'http://localhost:11434', 'model' => '', 'batas_waktu' => 60, 'ada_kunci' => false]],
            'batas_per_user_per_hari' => 20,
            'diperbarui_pada' => null,
        ];
    }

    private function berkas(): string
    {
        return config('sigenrabtor.stub.pengaturan_ai_file');
    }
}
