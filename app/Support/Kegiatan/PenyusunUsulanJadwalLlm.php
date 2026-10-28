<?php

namespace App\Support\Kegiatan;

use App\Contracts\KlienLlm;
use App\Contracts\PenyusunUsulanJadwal;
use App\Support\Stub\StubUsulanJadwal;

/**
 * Penyusun usulan jadwal lewat LLM (PRD F10 "Usul Jadwal AI", F23): "tiruan" (belum ada penyedia aktif) dijawab
 * StubUsulanJadwal; penyedia lain lewat KlienLlm dengan pesan sistem PromptLlm::sistemJadwal dan pesan pengguna
 * RiwayatSaranAi::promptJadwal (jenis, acuan bulan, instruksi, bahan, template Prompt "Jadwal kegiatan"). Bulan dari
 * model dibaca PromptLlm::bulan (angka atau nama bulan); SaranJadwalAi tetap merapikan hasilnya.
 */
final class PenyusunUsulanJadwalLlm implements PenyusunUsulanJadwal
{
    public function __construct(private readonly KlienLlm $klien, private readonly StubUsulanJadwal $tiruan) {}

    public function usulkan(PermintaanUsulanJadwal $permintaan, string $penyedia = 'tiruan'): array
    {
        if ($penyedia === 'tiruan') {
            return $this->tiruan->usulkan($permintaan);
        }

        $parameter = PromptLlm::parameterBerlaku();
        $jawaban = $this->klien->tanya($penyedia, PromptLlm::sistemJadwal($parameter['jumlah_versi']), RiwayatSaranAi::promptJadwal($permintaan), PromptLlm::parameter($parameter));

        return array_map(fn (array $versi) => [
            'judul' => PromptLlm::teks($versi['judul'] ?? null),
            'ciri' => PromptLlm::teks($versi['ciri'] ?? null),
            'keterangan' => PromptLlm::teks($versi['keterangan'] ?? null),
            'baris' => array_map(fn (array $baris) => [
                'uraian' => PromptLlm::teks($baris['uraian'] ?? null),
                'mulai' => PromptLlm::bulan($baris['mulai'] ?? null),
                'sampai' => PromptLlm::bulan($baris['sampai'] ?? null),
            ], array_values(array_filter(is_array($versi['baris'] ?? null) ? $versi['baris'] : [], 'is_array'))),
        ], PromptLlm::versi($jawaban, $penyedia));
    }
}
