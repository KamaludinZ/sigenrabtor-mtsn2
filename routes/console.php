<?php

use App\Support\Basisdata\PindahLeading;
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

Artisan::command('sigenrabtor:pindah-leading {--uji : Jalankan & verifikasi lalu batalkan, tanpa menyimpan}', function () {
    try {
        $hasil = PindahLeading::jalankan((bool) $this->option('uji'));
    } catch (RuntimeException $e) {
        $this->error($e->getMessage());

        return 1;
    }

    $this->table(['Hasil', 'Jumlah', 'Kode'], collect(['tambah' => 'Ditambahkan', 'ubah' => 'Diperbarui', 'tetap' => 'Sudah sama', 'hanya_database' => 'Hanya di database (dibiarkan)'])
        ->map(fn (string $label, string $k) => [$label, count($hasil[$k]), implode(', ', $hasil[$k]) ?: '—'])->values()->all());
    $this->info($this->option('uji') ? 'Uji sinkron leading lolos verifikasi; tidak ada yang disimpan.' : 'Leading disinkronkan ke database dan lolos verifikasi.');

    return 0;
})->purpose('Sinkronkan leading dari penyimpanan tiruan ke database (tambah & perbarui per kode, lalu verifikasi)');
