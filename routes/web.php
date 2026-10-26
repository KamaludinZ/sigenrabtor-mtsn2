<?php

use App\Http\Controllers\EksporMonitoringExcel;
use App\Http\Controllers\EksporMonitoringPdf;
use App\Http\Controllers\GambarCover;
use App\Http\Controllers\UnduhFormatRincianRab;
use App\Http\Controllers\UnduhMasterTemplate;
use App\Http\Controllers\UnduhTemplateImporKegiatan;
use App\Http\Middleware\HanyaAdmin;
use App\Livewire\Dashboard;
use App\Livewire\Kegiatan\Daftar as KegiatanDaftar;
use App\Livewire\Kegiatan\Form as KegiatanForm;
use App\Livewire\Kegiatan\Klasifikasi as KegiatanKlasifikasi;
use App\Livewire\Kegiatan\MatrikJadwal as KegiatanMatrikJadwal;
use App\Livewire\Kegiatan\Rab as KegiatanRab;
use App\Livewire\Monitoring\Anggaran as MonitoringAnggaran;
use App\Livewire\Pengaturan\Ikhtisar as PengaturanIkhtisar;
use App\Livewire\Pengaturan\ProfilMadrasah as PengaturanProfilMadrasah;
use App\Livewire\Pengaturan\Riwayat as PengaturanRiwayat;
use App\Livewire\Pengaturan\TahunAnggaran as PengaturanTahunAnggaran;
use App\Livewire\Pengaturan\TemplateDokumen as PengaturanTemplateDokumen;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::get('/dashboard', Dashboard::class)->name('dashboard');

Route::get('/kegiatan', KegiatanDaftar::class)->name('kegiatan.index');
Route::get('/kegiatan/template-impor', UnduhTemplateImporKegiatan::class)->name('kegiatan.template');
Route::get('/kegiatan/format-rincian-rab', UnduhFormatRincianRab::class)->name('kegiatan.rab.template');
Route::get('/kegiatan/buat', KegiatanForm::class)->name('kegiatan.create');
Route::get('/kegiatan/{kegiatan}/edit', KegiatanForm::class)->whereNumber('kegiatan')->name('kegiatan.edit');
Route::get('/kegiatan/{kegiatan}/kode', KegiatanKlasifikasi::class)->whereNumber('kegiatan')->name('kegiatan.kode');
Route::get('/kegiatan/{kegiatan}/rab', KegiatanRab::class)->whereNumber('kegiatan')->name('kegiatan.rab');
Route::get('/kegiatan/{kegiatan}/matrik', KegiatanMatrikJadwal::class)->whereNumber('kegiatan')->name('kegiatan.matrik');

// Monitoring anggaran lintas kegiatan & leading (PRD Fase 5 §11), khusus admin.
Route::get('/monitoring', MonitoringAnggaran::class)->middleware(HanyaAdmin::class)->name('monitoring.index');
Route::get('/monitoring/ekspor/excel', EksporMonitoringExcel::class)->middleware(HanyaAdmin::class)->name('monitoring.ekspor.excel');
Route::get('/monitoring/ekspor/pdf', EksporMonitoringPdf::class)->middleware(HanyaAdmin::class)->name('monitoring.ekspor.pdf');

// Pengaturan admin (PRD Fase 5–7): seluruh halaman khusus admin.
Route::middleware(HanyaAdmin::class)->prefix('pengaturan')->name('pengaturan.')->group(function () {
    Route::get('/', PengaturanIkhtisar::class)->name('index');
    Route::get('/tahun', PengaturanTahunAnggaran::class)->name('tahun.index');
    Route::get('/madrasah', PengaturanProfilMadrasah::class)->name('madrasah.index');
    Route::get('/riwayat', PengaturanRiwayat::class)->name('riwayat.index');
    Route::get('/template', PengaturanTemplateDokumen::class)->name('template.index');
    Route::get('/template/cover/{cover}/gambar', GambarCover::class)->whereUuid('cover')->name('template.cover.gambar');
    Route::get('/template/master/{template}/unduh', UnduhMasterTemplate::class)->whereUuid('template')->name('template.master.unduh');
});

// Tahap data tiruan: sakelar peran untuk mencoba tampilan Admin/Leading sebelum autentikasi nyata dibangun.
Route::get('/demo/peran/{role}', function (string $role) {
    session(['stub_role' => $role]);

    return back();
})->whereIn('role', ['admin', 'leading'])->name('demo.peran');
