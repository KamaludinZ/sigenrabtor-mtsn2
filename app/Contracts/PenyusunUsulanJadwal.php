<?php

namespace App\Contracts;

use App\Support\Kegiatan\GagalUsulanAi;
use App\Support\Kegiatan\PermintaanUsulanJadwal;

/**
 * Penyusun usulan jadwal kegiatan oleh AI (PRD F10 "Usul Jadwal AI", F23): 2–3 versi berisi 4–6 uraian beserta bulannya.
 * AI hanya menyusun uraian & bulan — tidak pernah angka, kode, atau pejabat; bahannya sudah tanpa nama/NIP pejabat
 * (KonteksAi). Tahap frontend memakai data tiruan; backend menggantinya dengan driver Ollama/API cloud (lewat antrean).
 * Komponen tetap merapikan hasilnya, jadi implementasi boleh mengembalikan teks apa adanya dari model.
 */
interface PenyusunUsulanJadwal
{
    /**
     * @return list<array{judul: string, ciri: string, keterangan: string, baris: list<array{uraian: string, mulai: int|null, sampai: int|null}>}>
     *
     * @throws GagalUsulanAi Penyedia tidak bisa dihubungi, batas harian habis, atau jawaban tidak terbaca.
     */
    public function usulkan(PermintaanUsulanJadwal $permintaan): array;
}
