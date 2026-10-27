<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Identitas aplikasi
    |--------------------------------------------------------------------------
    |
    | Nama & versi yang tampil di layar (judul tab, sidebar). Sengaja tidak bergantung
    | pada APP_NAME di .env agar tampilan seragam di semua lingkungan.
    |
    */

    'aplikasi' => [
        'nama' => 'SIRABTOR',
        'versi' => '1.0.0',
    ],

    /*
    |--------------------------------------------------------------------------
    | Pengubah PDF
    |--------------------------------------------------------------------------
    |
    | LibreOffice headless mengubah .xlsx/.docx menjadi PDF (PRD "Mesin Dokumen").
    | Isi lokasi program bila `soffice` tidak ada di PATH server.
    |
    */

    'pdf' => [
        'soffice' => env('SIRABTOR_SOFFICE', 'soffice'),
        'batas_detik' => (int) env('SIRABTOR_PDF_BATAS_DETIK', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | Data tiruan (tahap frontend)
    |--------------------------------------------------------------------------
    |
    | Lokasi berkas JSON kegiatan tiruan pada disk "local". Tes E2E memakai berkas
    | terpisah agar data demo pengembangan tidak tercampur.
    |
    */

    'stub' => [
        'kegiatan_file' => env('STUB_KEGIATAN_FILE', 'stub/kegiatan.json'),
        'rab_file' => env('STUB_RAB_FILE', 'stub/rab.json'),
        'matrik_file' => env('STUB_MATRIK_FILE', 'stub/matrik-jadwal.json'),
        'tahun_file' => env('STUB_TAHUN_FILE', 'stub/tahun-anggaran.json'),
        'cover_file' => env('STUB_COVER_FILE', 'stub/cover-template.json'),
        'master_file' => env('STUB_MASTER_FILE', 'stub/master-template.json'),
        'daftar_isi_file' => env('STUB_DAFTAR_ISI_FILE', 'stub/daftar-isi.json'),
        'nomor_halaman_file' => env('STUB_NOMOR_HALAMAN_FILE', 'stub/nomor-halaman.json'),
        'riwayat_file' => env('STUB_RIWAYAT_FILE', 'stub/riwayat-pengaturan.json'),
        'profil_file' => env('STUB_PROFIL_FILE', 'stub/profil-madrasah.json'),
        'pejabat_file' => env('STUB_PEJABAT_FILE', 'stub/pejabat.json'),
        'data_pagu_file' => env('STUB_DATA_PAGU_FILE', 'stub/data-pagu.json'),
        'dasar_hukum_file' => env('STUB_DASAR_HUKUM_FILE', 'stub/dasar-hukum.json'),
        'leading_file' => env('STUB_LEADING_FILE', 'stub/leading.json'),
        'pengguna_file' => env('STUB_PENGGUNA_FILE', 'stub/pengguna.json'),
        'arsip_file' => env('STUB_ARSIP_FILE', 'stub/arsip-final.json'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Berkas "hot" Vite
    |--------------------------------------------------------------------------
    |
    | Tes E2E mengarahkan ini ke berkas yang tidak ada agar halaman selalu memakai
    | aset hasil `npm run build`, walau `npm run dev` sedang berjalan (public/hot ada).
    |
    */

    'vite_hot_file' => env('VITE_HOT_FILE'),

];
