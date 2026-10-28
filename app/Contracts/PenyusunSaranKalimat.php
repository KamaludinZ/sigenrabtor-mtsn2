<?php

namespace App\Contracts;

use App\Support\Kegiatan\GagalUsulanAi;
use App\Support\Kegiatan\PermintaanSaranKalimat;

/**
 * Penyusun saran kalimat KAK oleh AI (PRD §20, F23 "Buat dengan AI"): 2–3 versi teks untuk satu bagian BagianAi
 * (nama cover, latar belakang, tujuan, sasaran, pelaksanaan). AI hanya menyarankan kalimat — tidak pernah angka, kode,
 * atau pejabat; bahannya sudah tanpa nama/NIP pejabat (KonteksAi). $penyedia = penyedia yang sedang dicoba
 * RantaiPenyediaAi ("tiruan" = belum ada penyedia aktif); terpasang PenyusunSaranKalimatLlm (tiruan → StubSaranKalimat,
 * lainnya → KlienLlm). Komponen tetap merapikan hasilnya.
 */
interface PenyusunSaranKalimat
{
    /**
     * @param  string  $penyedia  Kunci PenyediaAi::DAFTAR atau "tiruan".
     * @return list<array{judul: string, ciri: string, teks: string}>
     *
     * @throws GagalUsulanAi Penyedia tidak bisa dihubungi atau jawaban tidak terbaca (GagalPenyediaAi berjenis).
     */
    public function sarankan(PermintaanSaranKalimat $permintaan, string $penyedia = 'tiruan'): array;
}
