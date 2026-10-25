<?php

return [

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
