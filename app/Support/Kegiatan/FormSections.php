<?php

namespace App\Support\Kegiatan;

/**
 * Susunan baku bagian form Data Kegiatan (PRD Fitur 1 "Isi Data Kegiatan").
 */
class FormSections
{
    /**
     * @return list<array{id: string, title: string, description: string, icon: string, opsional?: bool}>
     */
    public static function all(): array
    {
        return [
            [
                'id' => 'identitas',
                'title' => 'Identitas Kegiatan',
                'description' => 'Nama kegiatan, nama versi cover, latar belakang, tujuan, sasaran, dan tanggal dokumen.',
                'icon' => 'file-text',
            ],
            [
                'id' => 'kode',
                'title' => 'Kode & Klasifikasi',
                'description' => 'Leading dan kode master tahun aktif; diatur di halaman Kode & Klasifikasi.',
                'icon' => 'tag',
            ],
            [
                'id' => 'pelaksanaan',
                'title' => 'Pelaksanaan',
                'description' => 'Tempat, lama kegiatan, peserta, bulan, dan bentuk pelaksanaan.',
                'icon' => 'map-pin',
            ],
            [
                'id' => 'konteks',
                'title' => 'Konteks Kegiatan',
                'description' => 'Catatan bebas leading sebagai bahan bantuan AI.',
                'icon' => 'note',
                'opsional' => true,
            ],
            [
                'id' => 'panitia',
                'title' => 'Pelaksana & Panitia',
                'description' => 'Penanggung jawab dan susunan tim pelaksana kegiatan.',
                'icon' => 'users',
            ],
            [
                'id' => 'sumber-dana',
                'title' => 'Sumber Dana',
                'description' => 'DIPA, Komite, atau keduanya beserta pagu masing-masing.',
                'icon' => 'wallet',
            ],
        ];
    }

    /**
     * Id bagian yang wajib lengkap sebelum dokumen dianggap lengkap.
     *
     * @return list<string>
     */
    public static function wajib(): array
    {
        return array_column(array_filter(self::all(), fn (array $bagian) => ! ($bagian['opsional'] ?? false)), 'id');
    }
}
