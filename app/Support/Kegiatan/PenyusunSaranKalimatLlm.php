<?php

namespace App\Support\Kegiatan;

use App\Contracts\KlienLlm;
use App\Contracts\PenyusunSaranKalimat;
use App\Support\Stub\StubSaranKalimat;

/**
 * Penyusun saran kalimat KAK lewat LLM (PRD §20 F23): penyedia dari RantaiPenyediaAi — "tiruan" (belum ada penyedia
 * aktif) dijawab StubSaranKalimat; penyedia lain lewat KlienLlm dengan pesan sistem PromptLlm::sistemKalimat dan pesan
 * pengguna RiwayatSaranAi::prompt (template Prompt per bagian Pengaturan AI), suhu & anggaran token PromptLlm::parameter.
 * Jawaban JSON dibaca PromptLlm::versi; SaranKalimatAi tetap merapikan dan memeriksa hasilnya.
 */
final class PenyusunSaranKalimatLlm implements PenyusunSaranKalimat
{
    public function __construct(private readonly KlienLlm $klien, private readonly StubSaranKalimat $tiruan) {}

    public function sarankan(PermintaanSaranKalimat $permintaan, string $penyedia = 'tiruan'): array
    {
        if ($penyedia === 'tiruan') {
            return $this->tiruan->sarankan($permintaan);
        }

        $parameter = PromptLlm::parameterBerlaku();
        $jawaban = $this->klien->tanya($penyedia, PromptLlm::sistemKalimat($permintaan, $parameter['jumlah_versi']), RiwayatSaranAi::prompt($permintaan), PromptLlm::parameter($parameter));

        return array_map(fn (array $versi) => [
            'judul' => PromptLlm::teks($versi['judul'] ?? null),
            'ciri' => PromptLlm::teks($versi['ciri'] ?? null),
            'teks' => PromptLlm::teks($versi['teks'] ?? null),
        ], PromptLlm::versi($jawaban, $penyedia));
    }
}
