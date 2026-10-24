<?php

namespace App\View\Composers;

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
            'madrasah' => StubData::madrasah(),
        ]);
    }
}
