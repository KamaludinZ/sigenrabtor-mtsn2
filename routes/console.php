<?php

use App\Support\Basisdata\PindahStrukturAnggaran;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('sigenrabtor:pindah-anggaran {--uji : Jalankan & verifikasi lalu batalkan, tanpa menyimpan}', function () {
    try {
        $hasil = PindahStrukturAnggaran::jalankan((bool) $this->option('uji'));
    } catch (RuntimeException $e) {
        $this->error($e->getMessage());

        return 1;
    }

    $this->table(['Data', 'Jumlah'], collect($hasil)->map(fn (int $n, string $k) => [str_replace('_', ' ', $k), $n])->values()->all());
    $this->info($this->option('uji') ? 'Uji pemindahan lolos verifikasi; tidak ada yang disimpan.' : 'Struktur anggaran awal dipindahkan ke database dan lolos verifikasi.');

    return 0;
})->purpose('Pindahkan tahun, leading, akun, Data Pagu, kegiatan, RAB, termin, matrik & jadwal dari penyimpanan tiruan ke database kosong');
