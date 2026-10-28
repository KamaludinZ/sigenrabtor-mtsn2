<?php

use App\Support\Basisdata\PindahAkun;
use App\Support\Basisdata\PindahLeading;
use App\Support\Basisdata\PindahMasterKode;
use App\Support\Basisdata\PindahPengaturanAi;
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

Artisan::command('sigenrabtor:pindah-akun {--uji : Jalankan & verifikasi lalu batalkan, tanpa menyimpan}', function () {
    try {
        $hasil = PindahAkun::jalankan((bool) $this->option('uji'));
    } catch (RuntimeException $e) {
        $this->error($e->getMessage());

        return 1;
    }

    $this->table(['Hasil', 'Jumlah', 'Id akun'], collect(['tambah' => 'Ditambahkan', 'ubah' => 'Diperbarui', 'tetap' => 'Sudah sama', 'hanya_database' => 'Hanya di database (dibiarkan)'])
        ->map(fn (string $label, string $k) => [$label, count($hasil[$k]), implode(', ', $hasil[$k]) ?: '—'])->values()->all());
    $this->line("Riwayat masuk disalin: {$hasil['riwayat_masuk']}");
    $this->info($this->option('uji') ? 'Uji sinkron akun lolos verifikasi; tidak ada yang disimpan.' : 'Akun disinkronkan ke database dan lolos verifikasi.');

    return 0;
})->purpose('Sinkronkan akun, tautan leading, dan riwayat masuk dari penyimpanan tiruan ke database (per id, lalu verifikasi)');

Artisan::command('sigenrabtor:pindah-master {--uji : Jalankan & verifikasi lalu batalkan, tanpa menyimpan} {--tahun=* : Hanya tahun ini (boleh berulang)}', function () {
    try {
        $tahun = array_map('intval', (array) $this->option('tahun')) ?: null;
        $hasil = PindahMasterKode::jalankan((bool) $this->option('uji'), $tahun);
    } catch (RuntimeException $e) {
        $this->error($e->getMessage());

        return 1;
    }

    $this->table(['Hasil', 'Tahun'], [
        ['Diganti', collect($hasil['ganti'])->map(fn (array $bagian, int $t) => "{$t} (".implode(', ', $bagian).')')->implode('; ') ?: '—'],
        ['Sudah sama', implode(', ', $hasil['tetap']) ?: '—'],
        ['Tahun anggaran belum ada (dilewati)', implode(', ', $hasil['tanpa_tahun']) ?: '—'],
    ]);
    $this->line("Jejak unggahan disalin: {$hasil['unggahan']}");
    $this->info($this->option('uji') ? 'Uji sinkron master kode lolos verifikasi; tidak ada yang disimpan.' : 'Master kode disinkronkan ke database dan lolos verifikasi.');

    return 0;
})->purpose('Sinkronkan master kode tiruan per tahun anggaran ke database (ganti bagian yang berbeda, lalu verifikasi)');

Artisan::command('sigenrabtor:pindah-pengaturan-ai {--uji : Jalankan & verifikasi lalu batalkan, tanpa menyimpan}', function () {
    try {
        $hasil = PindahPengaturanAi::jalankan((bool) $this->option('uji'));
    } catch (RuntimeException $e) {
        $this->error($e->getMessage());

        return 1;
    }

    $this->table(['Hasil', 'Isi'], [
        ['Pengaturan', $hasil['status'] === 'tetap' ? 'Sudah sama' : 'Diganti'],
        ['Penyedia aktif', $hasil['provider_aktif'] ?? '— (AI tiruan)'],
        ['Penyedia cadangan', $hasil['provider_cadangan'] ?? '—'],
        ['Konfigurasi penyedia', implode(', ', $hasil['penyedia']) ?: '—'],
    ]);
    $this->info($this->option('uji') ? 'Uji sinkron pengaturan AI lolos verifikasi; tidak ada yang disimpan.' : 'Pengaturan AI disinkronkan ke database dan lolos verifikasi.');

    return 0;
})->purpose('Sinkronkan pengaturan & penyedia AI dari penyimpanan tiruan ke database (lalu verifikasi)');
