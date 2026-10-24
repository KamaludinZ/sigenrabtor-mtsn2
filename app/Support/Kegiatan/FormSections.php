<?php

namespace App\Support\Kegiatan;

/**
 * Susunan baku bagian form Data Kegiatan (PRD Fitur 1 "Isi Data Kegiatan").
 */
class FormSections
{
    /**
     * @return list<array{id: string, title: string, description: string, icon: string, fields: list<string>}>
     */
    public static function all(): array
    {
        return [
            [
                'id' => 'identitas',
                'title' => 'Identitas Kegiatan',
                'description' => 'Nama kegiatan, nama versi cover, latar belakang, tujuan, sasaran, dan tanggal dokumen.',
                'icon' => 'file-text',
                'fields' => ['Nama kegiatan', 'Nama kegiatan (cover)', 'Latar belakang', 'Tujuan', 'Sasaran', 'Tanggal dokumen', 'Tanggal pelaporan'],
            ],
            [
                'id' => 'kode',
                'title' => 'Kode & Klasifikasi',
                'description' => 'Leading penanggung jawab dan kode dari master tahun aktif.',
                'icon' => 'tag',
                'fields' => ['Leading', 'Kode RKTM', 'Kode SK', 'Kode IKSK', 'Kode EDM', '5 Budaya Mutu'],
            ],
            [
                'id' => 'pelaksanaan',
                'title' => 'Pelaksanaan',
                'description' => 'Tempat, lama kegiatan, peserta, bulan, dan bentuk pelaksanaan.',
                'icon' => 'map-pin',
                'fields' => ['Tempat', 'Lama kegiatan (hari)', 'Jumlah peserta', 'Jenis peserta', 'Bulan pelaksanaan', 'Uraian pelaksanaan'],
            ],
            [
                'id' => 'konteks',
                'title' => 'Konteks Kegiatan',
                'description' => 'Catatan bebas leading sebagai bahan bantuan AI.',
                'icon' => 'note',
                'fields' => ['Latar khusus', 'Peserta', 'Narasumber', 'Output yang diharapkan'],
            ],
            [
                'id' => 'panitia',
                'title' => 'Pelaksana & Panitia',
                'description' => 'Penanggung jawab dan susunan tim pelaksana kegiatan.',
                'icon' => 'users',
                'fields' => ['Penanggung jawab', 'Susunan tim (nama, jabatan, peran)'],
            ],
            [
                'id' => 'sumber-dana',
                'title' => 'Sumber Dana',
                'description' => 'DIPA, Komite, atau keduanya beserta pagu masing-masing.',
                'icon' => 'wallet',
                'fields' => ['Sumber dana', 'Pagu DIPA', 'Pagu Komite'],
            ],
        ];
    }
}
