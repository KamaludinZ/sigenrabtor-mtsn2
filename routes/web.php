<?php

use App\Livewire\Kegiatan\Form as KegiatanForm;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/kegiatan/buat');

Route::get('/kegiatan/buat', KegiatanForm::class)->name('kegiatan.create');

// Tahap data tiruan: sakelar peran untuk mencoba tampilan Admin/Leading sebelum autentikasi nyata dibangun.
Route::get('/demo/peran/{role}', function (string $role) {
    session(['stub_role' => $role]);

    return back();
})->whereIn('role', ['admin', 'leading'])->name('demo.peran');
