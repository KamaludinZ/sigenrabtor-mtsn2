<?php

namespace App\View\Composers;

use App\Support\Akun\SesiAkun;
use App\Support\Stub\StubData;
use Illuminate\View\View;

/**
 * Menyediakan konteks global layout aplikasi: tahun anggaran aktif, pengguna, dan madrasah.
 */
class AppLayoutComposer
{
    public function compose(View $view): void
    {
        $view->with([
            'tahunAktif' => StubData::tahunAktif(),
            'currentUser' => StubData::currentUser(),
            // Sesi akun (surel & waktu masuk) untuk menu akun bilah atas.
            'sesiAkun' => SesiAkun::pengguna(),
            'madrasah' => StubData::madrasah(),
        ]);
    }
}
