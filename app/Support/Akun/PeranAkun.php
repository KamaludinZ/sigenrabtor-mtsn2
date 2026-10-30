<?php

namespace App\Support\Akun;

/**
 * Peran akun (PRD §8 "Peran Pengguna", §15) — sumber tunggal daftar peran: `DAFTAR` kode → label, keterangan hak, dan
 * apakah akun wajib tertaut ke leading. Diisikan ke tabel rujukan `peran` oleh migrasi 000048 (users.role FK ke sana;
 * peran baru wajib lewat migrasi — SkemaAkunTest membandingkannya); `LABEL` dipakai DataAkun::LABEL_PERAN.
 */
final class PeranAkun
{
    public const DAFTAR = [
        'admin' => [
            'label' => 'Admin',
            'keterangan' => 'Mengatur tahun anggaran, master kode, template, akun & leading; memeriksa, menyetujui, dan membuka kunci dokumen semua leading.',
            'perlu_leading' => false,
        ],
        'leading' => [
            'label' => 'Leading / penanggung jawab',
            'keterangan' => 'Menyusun kegiatan, RAB, dan KAK/TOR untuk leading yang tertaut; mengajukan dan mengunggah arsip final.',
            'perlu_leading' => true,
        ],
    ];

    public const LABEL = ['admin' => 'Admin', 'leading' => 'Leading / penanggung jawab'];
}
