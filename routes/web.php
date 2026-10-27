<?php

use App\Contracts\PenggunaRepository;
use App\Http\Controllers\EksporMonitoringExcel;
use App\Http\Controllers\EksporMonitoringPdf;
use App\Http\Controllers\GambarCover;
use App\Http\Controllers\LogoMadrasah;
use App\Http\Controllers\UnduhArsipFinal;
use App\Http\Controllers\UnduhFormatRincianRab;
use App\Http\Controllers\UnduhMasterTemplate;
use App\Http\Controllers\UnduhTemplateDataPagu;
use App\Http\Controllers\UnduhTemplateImporKegiatan;
use App\Http\Middleware\HanyaAdmin;
use App\Livewire\Dashboard;
use App\Livewire\Kegiatan\Daftar as KegiatanDaftar;
use App\Livewire\Kegiatan\Form as KegiatanForm;
use App\Livewire\Kegiatan\Klasifikasi as KegiatanKlasifikasi;
use App\Livewire\Kegiatan\MatrikJadwal as KegiatanMatrikJadwal;
use App\Livewire\Kegiatan\PratinjauDokumen as KegiatanPratinjauDokumen;
use App\Livewire\Kegiatan\Rab as KegiatanRab;
use App\Livewire\Master\Daftar as MasterKodeDaftar;
use App\Livewire\Monitoring\Anggaran as MonitoringAnggaran;
use App\Livewire\Pagu\Daftar as DataPaguDaftar;
use App\Livewire\Pemeriksaan\Daftar as PemeriksaanDaftar;
use App\Livewire\Pemeriksaan\Periksa as PemeriksaanPeriksa;
use App\Livewire\Pengaturan\DasarHukum as PengaturanDasarHukum;
use App\Livewire\Pengaturan\Ikhtisar as PengaturanIkhtisar;
use App\Livewire\Pengaturan\LeadingUser as PengaturanLeadingUser;
use App\Livewire\Pengaturan\ProfilMadrasah as PengaturanProfilMadrasah;
use App\Livewire\Pengaturan\Riwayat as PengaturanRiwayat;
use App\Livewire\Pengaturan\TahunAnggaran as PengaturanTahunAnggaran;
use App\Livewire\Pengaturan\TemplateDokumen as PengaturanTemplateDokumen;
use App\Support\Stub\StubData;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::get('/dashboard', Dashboard::class)->name('dashboard');

Route::get('/kegiatan', KegiatanDaftar::class)->name('kegiatan.index');
Route::get('/kegiatan/template-impor', UnduhTemplateImporKegiatan::class)->name('kegiatan.template');
Route::get('/kegiatan/format-rincian-rab', UnduhFormatRincianRab::class)->name('kegiatan.rab.template');
Route::get('/kegiatan/buat', KegiatanForm::class)->name('kegiatan.create');
Route::get('/data-pagu', DataPaguDaftar::class)->name('pagu.index');
Route::get('/data-pagu/template', UnduhTemplateDataPagu::class)->middleware(HanyaAdmin::class)->name('pagu.template');
Route::get('/kegiatan/{kegiatan}/edit', KegiatanForm::class)->whereNumber('kegiatan')->name('kegiatan.edit');
Route::get('/kegiatan/{kegiatan}/kode', KegiatanKlasifikasi::class)->whereNumber('kegiatan')->name('kegiatan.kode');
Route::get('/kegiatan/{kegiatan}/rab', KegiatanRab::class)->whereNumber('kegiatan')->name('kegiatan.rab');
Route::get('/kegiatan/{kegiatan}/matrik', KegiatanMatrikJadwal::class)->whereNumber('kegiatan')->name('kegiatan.matrik');
Route::get('/kegiatan/{kegiatan}/pratinjau', KegiatanPratinjauDokumen::class)->whereNumber('kegiatan')->name('kegiatan.pratinjau');
Route::get('/kegiatan/{kegiatan}/arsip/{sumber}', UnduhArsipFinal::class)->whereNumber('kegiatan')->name('kegiatan.arsip');
// Gambar desain cover untuk pratinjau dokumen semua pengguna (pengelolaannya tetap khusus admin).
Route::get('/dokumen/cover/{cover}/gambar', GambarCover::class)->whereUuid('cover')->name('dokumen.cover.gambar');

// Monitoring anggaran lintas kegiatan & leading (PRD Fase 5 §11), khusus admin.
Route::get('/monitoring', MonitoringAnggaran::class)->middleware(HanyaAdmin::class)->name('monitoring.index');
Route::get('/monitoring/ekspor/excel', EksporMonitoringExcel::class)->middleware(HanyaAdmin::class)->name('monitoring.ekspor.excel');
Route::get('/monitoring/ekspor/pdf', EksporMonitoringPdf::class)->middleware(HanyaAdmin::class)->name('monitoring.ekspor.pdf');

// Pemeriksaan & arsip final (PRD Fase 6 §16), khusus admin.
Route::get('/pemeriksaan', PemeriksaanDaftar::class)->middleware(HanyaAdmin::class)->name('pemeriksaan.index');
Route::get('/pemeriksaan/{kegiatan}', PemeriksaanPeriksa::class)->whereNumber('kegiatan')->middleware(HanyaAdmin::class)->name('pemeriksaan.periksa');

// Master kode tahunan (PRD Fase 6 §14), khusus admin.
Route::get('/master', MasterKodeDaftar::class)->middleware(HanyaAdmin::class)->name('master.index');

// Logo kop madrasah: tampil di kop & pratinjau dokumen semua pengguna.
Route::get('/madrasah/logo', LogoMadrasah::class)->name('madrasah.logo');

// Pengaturan admin (PRD Fase 5–7): seluruh halaman khusus admin.
Route::middleware(HanyaAdmin::class)->prefix('pengaturan')->name('pengaturan.')->group(function () {
    Route::get('/', PengaturanIkhtisar::class)->name('index');
    Route::get('/tahun', PengaturanTahunAnggaran::class)->name('tahun.index');
    Route::get('/madrasah', PengaturanProfilMadrasah::class)->name('madrasah.index');
    Route::get('/madrasah/dasar-hukum', PengaturanDasarHukum::class)->name('madrasah.dasar_hukum');
    Route::get('/leading', PengaturanLeadingUser::class)->name('leading.index');
    Route::get('/riwayat', PengaturanRiwayat::class)->name('riwayat.index');
    Route::get('/template', PengaturanTemplateDokumen::class)->name('template.index');
    Route::get('/template/cover/{cover}/gambar', GambarCover::class)->whereUuid('cover')->name('template.cover.gambar');
    Route::get('/template/master/{template}/unduh', UnduhMasterTemplate::class)->whereUuid('template')->name('template.master.unduh');
});

// Tahap data tiruan: sakelar peran untuk mencoba tampilan Admin/Leading sebelum autentikasi nyata dibangun.
Route::get('/demo/peran/{role}', function (string $role) {
    session(['stub_role' => $role]);
    // Padanan peristiwa masuk: waktu masuk terakhir akun persona tercatat di Pengaturan › Leading & User.
    try {
        app(PenggunaRepository::class)->catatMasuk(StubData::currentUser()['id']);
    } catch (RuntimeException $e) {
        report($e);
    }

    return back();
})->whereIn('role', ['admin', 'leading'])->name('demo.peran');
