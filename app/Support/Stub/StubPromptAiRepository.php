<?php

namespace App\Support\Stub;

use App\Contracts\PromptAiRepository;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/** Template prompt AI tiruan di berkas JSON (disk "local"); kosong = semua bagian memakai prompt bawaan. */
class StubPromptAiRepository implements PromptAiRepository
{
    public function ambil(): array
    {
        $isi = Storage::disk('local')->exists($this->berkas()) ? json_decode((string) Storage::disk('local')->get($this->berkas()), true) : null;

        return [
            'gaya_bahasa' => is_array($isi) ? ($isi['gaya_bahasa'] ?? null) : null,
            'bagian' => is_array($isi) && is_array($isi['bagian'] ?? null) ? $isi['bagian'] : [],
            'diperbarui_pada' => is_array($isi) ? ($isi['diperbarui_pada'] ?? null) : null,
        ];
    }

    public function simpan(array $data): void
    {
        $isi = ['gaya_bahasa' => $data['gaya_bahasa'] ?? null, 'bagian' => (object) ($data['bagian'] ?? []), 'diperbarui_pada' => now()->format('Y-m-d\TH:i:s.uP')];
        if (! Storage::disk('local')->put($this->berkas(), json_encode($isi, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            throw new RuntimeException('Prompt AI gagal disimpan. Coba lagi.');
        }
    }

    private function berkas(): string
    {
        return config('sigenrabtor.stub.prompt_ai_file');
    }
}
