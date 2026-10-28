<?php

namespace App\Contracts;

use App\Support\Kegiatan\GagalPenyediaAi;

/**
 * Sambungan ke layanan LLM satu penyedia (PRD §9 & §21: Ollama di server sekolah atau API cloud Claude/OpenAI/Gemini):
 * satu pesan sistem + satu pesan pengguna → teks jawaban model, diminta berbentuk objek JSON. Alamat, model, batas waktu,
 * dan kunci API dibaca dari Pengaturan AI (PengaturanAiRepository `penyedia.{kunci}`); parameter jawaban dari ParameterAi.
 * Dipakai penyusun saran kalimat & usulan jadwal (PenyusunSaranKalimatLlm, PenyusunUsulanJadwalLlm).
 */
interface KlienLlm
{
    /**
     * @param  string  $penyedia  Kunci PenyediaAi::DAFTAR.
     * @param  array{suhu: float, maks_token: int}  $parameter
     *
     * @throws GagalPenyediaAi Berjenis KegagalanAi::JENIS (tidak terhubung, waktu habis, kunci ditolak, kuota, model, jawaban).
     */
    public function tanya(string $penyedia, string $sistem, string $pesan, array $parameter): string;
}
