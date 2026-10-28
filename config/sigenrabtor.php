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
        'pdfunite' => env('SIRABTOR_PDFUNITE', 'pdfunite'),
        'pdfinfo' => env('SIRABTOR_PDFINFO', 'pdfinfo'),
        'batas_detik' => (int) env('SIRABTOR_PDF_BATAS_DETIK', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | Admin awal (instalasi baru)
    |--------------------------------------------------------------------------
    |
    | Akun admin yang dibuat AkunAdminSeeder bila database belum punya admin aktif.
    | Sandinya acak dan tidak ditampilkan — diatur ulang lewat alur lupa sandi saat
    | autentikasi nyata dipasang.
    |
    */

    'admin_awal' => [
        'nama' => env('SIRABTOR_ADMIN_AWAL_NAMA', 'Admin Aplikasi'),
        'email' => env('SIRABTOR_ADMIN_AWAL_EMAIL', 'admin@example.com'),
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
        'master_kode_file' => env('STUB_MASTER_KODE_FILE', 'stub/master-kode.json'),
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
        'notifikasi_file' => env('STUB_NOTIFIKASI_FILE', 'stub/notifikasi.json'),
        'buku_file' => env('STUB_BUKU_FILE', 'stub/buku-dokumen.json'),
        'pemakaian_ai_file' => env('STUB_PEMAKAIAN_AI_FILE', 'stub/pemakaian-ai.json'),
        'riwayat_saran_ai_file' => env('STUB_RIWAYAT_SARAN_AI_FILE', 'stub/riwayat-saran-ai.json'),
        'pengaturan_ai_file' => env('STUB_PENGATURAN_AI_FILE', 'stub/pengaturan-ai.json'),
        'prompt_ai_file' => env('STUB_PROMPT_AI_FILE', 'stub/prompt-ai.json'),
        'catatan_pemeriksaan_file' => env('STUB_CATATAN_PEMERIKSAAN_FILE', 'stub/catatan-pemeriksaan.json'),
        'riwayat_kunci_file' => env('STUB_RIWAYAT_KUNCI_FILE', 'stub/riwayat-kunci.json'),
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
