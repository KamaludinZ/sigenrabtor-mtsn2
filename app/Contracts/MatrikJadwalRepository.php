<?php

namespace App\Contracts;

use App\Support\Kegiatan\JadwalKegiatan;
use App\Support\Kegiatan\MatrikTahapan;

/**
 * Penyimpanan matrik tahapan & jadwal kegiatan (PRD tabel matrik_tahapan & schedules), terpisah dari data kegiatan
 * sehingga menyimpan Data Kegiatan tidak menyentuhnya. Tahap frontend memakai implementasi tiruan (berkas JSON);
 * backend menggantinya dengan Eloquent tanpa mengubah komponen yang memakainya.
 */
interface MatrikJadwalRepository
{
    /** Matrik tersimpan; kegiatan yang belum pernah menyimpan matrik mendapat butir baku tanpa tanda. */
    public function matrik(int $kegiatanId): MatrikTahapan;

    /** Jadwal tersimpan; kegiatan yang belum pernah menyimpan jadwal mendapat 6 baris baku kosong. */
    public function jadwal(int $kegiatanId): JadwalKegiatan;

    /**
     * Simpan matrik & jadwal sekaligus (satu transaksi di backend).
     *
     * @throws \RuntimeException Penyimpanan gagal; isian di layar tetap utuh agar bisa disimpan ulang.
     */
    public function simpan(int $kegiatanId, MatrikTahapan $matrik, JadwalKegiatan $jadwal): void;
}
