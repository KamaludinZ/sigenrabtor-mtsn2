<?php

use App\Http\Controllers\UnduhTemplateImporKegiatan;
use App\Livewire\Kegiatan\Daftar as KegiatanDaftar;
use App\Livewire\Kegiatan\Form as KegiatanForm;
use App\Livewire\Kegiatan\Klasifikasi as KegiatanKlasifikasi;
use App\Livewire\Kegiatan\Rab as KegiatanRab;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/kegiatan');

Route::get('/kegiatan', KegiatanDaftar::class)->name('kegiatan.index');
Route::get('/kegiatan/template-impor', UnduhTemplateImporKegiatan::class)->name('kegiatan.template');
Route::get('/kegiatan/buat', KegiatanForm::class)->name('kegiatan.create');
Route::get('/kegiatan/{kegiatan}/edit', KegiatanForm::class)->whereNumber('kegiatan')->name('kegiatan.edit');
Route::get('/kegiatan/{kegiatan}/kode', KegiatanKlasifikasi::class)->whereNumber('kegiatan')->name('kegiatan.kode');
Route::get('/kegiatan/{kegiatan}/rab', KegiatanRab::class)->whereNumber('kegiatan')->name('kegiatan.rab');

// Tahap data tiruan: sakelar peran untuk mencoba tampilan Admin/Leading sebelum autentikasi nyata dibangun.
Route::get('/demo/peran/{role}', function (string $role) {
    session(['stub_role' => $role]);

    return back();
})->whereIn('role', ['admin', 'leading'])->name('demo.peran');
