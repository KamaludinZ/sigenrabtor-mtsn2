<?php

namespace App\Support\Pengaturan;

use App\Support\Kegiatan\BagianAi;

/**
 * Prompt bawaan per bagian AI (PRD tabel prompt_templates: bagian, instruksi, gaya_bahasa, is_active). Dipakai selama admin
 * belum mengubahnya di Pengaturan AI; semuanya menegaskan Batas Aman (tanpa angka, kode, nama pejabat baru).
 */
final class PromptAi
{
    public const GAYA_BAHASA = 'Bahasa Indonesia baku dan kalimat efektif bergaya dokumen dinas madrasah; tanpa singkatan tidak baku dan tanpa sapaan.';

    public const BATAS_AMAN = 'Jangan menambah angka, kode, atau nama pejabat yang tidak ada di bahan; jangan menyebut NIP.';

    public const BAWAAN = [
        'nama_cover' => 'Rapikan nama kegiatan menjadi judul cover yang baku dan ringkas, ditulis kapital, tanpa kata "Kegiatan" di awal.',
        'latar_belakang' => 'Tulis 1–2 paragraf alasan kegiatan yang menyambung paragraf baku visi madrasah, berdasarkan program RKTM, IKSK, dan catatan konteks.',
        'tujuan' => 'Tulis satu frasa tujuan spesifik yang melanjutkan kalimat "Kegiatan {nama} bertujuan …", diawali kata kerja.',
        'sasaran' => 'Tulis satu frasa sasaran terukur yang melanjutkan kalimat "Terwujudnya …", menyebut kelompok sasaran dan hasil yang diharapkan.',
        'pelaksanaan' => 'Tulis 1–2 kalimat bentuk pelaksanaan (metode, narasumber, alur) yang menyambung kalimat waktu dan peserta.',
        'jadwal' => 'Usulkan 4–6 uraian jadwal beserta bulannya berdasarkan bulan pelaksanaan dan jenis kegiatan.',
    ];

    /**
     * Prompt per bagian dalam urutan BagianAi::BAGIAN.
     *
     * @return list<array{bagian: string, judul: string, instruksi: string, aktif: bool, bawaan: bool}>
     */
    public static function semua(): array
    {
        return array_map(fn (string $b) => ['bagian' => $b, 'judul' => BagianAi::BAGIAN[$b]['judul'], 'instruksi' => self::BAWAAN[$b], 'aktif' => true, 'bawaan' => true], array_keys(BagianAi::BAGIAN));
    }
}
