<?php

namespace App\Support\Dokumen;

/**
 * Dokumen hasil cetak per kegiatan × sumber dana (PRD §6 `documents`, §7B): jenis dokumen & aturannya bersama untuk
 * skema database (migrasi `create_documents_tables`) dan pembuat dokumen. Jenis = jenis master template
 * (TemplateDokumenRepository::JENIS) + `lengkap` (cover, KAK/TOR, dan RAB satu sumber dana dalam satu berkas); RAB dan set
 * lengkap selalu per sumber dana. Penomoran per jenis × tahun anggaran lewat tabel `number_sequences` (fungsi database
 * `nomor_dokumen_berikutnya`).
 */
final class DokumenKegiatan
{
    public const JENIS = [
        'KAK+TOR' => 'KAK/TOR (gabungan)',
        'KAK' => 'KAK',
        'TOR' => 'TOR',
        'RAB' => 'RAB',
        'lengkap' => 'Set lengkap (cover, KAK/TOR, RAB)',
    ];

    /** Jenis yang wajib bersumber dana (RAB disusun per sumber dana). */
    public const PER_SUMBER = ['RAB', 'lengkap'];

    public const MAKS_NOMOR = 100;
}
