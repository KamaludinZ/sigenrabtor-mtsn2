<?php

namespace App\Providers;

use App\Contracts\KegiatanRepository;
use App\Contracts\MasterKodeRepository;
use App\Contracts\MatrikJadwalRepository;
use App\Contracts\PenyusunUsulanJadwal;
use App\Contracts\RabRepository;
use App\Support\Stub\StubKegiatanRepository;
use App\Support\Stub\StubMasterKodeRepository;
use App\Support\Stub\StubMatrikJadwalRepository;
use App\Support\Stub\StubRabRepository;
use App\Support\Stub\StubUsulanJadwal;
use App\View\Composers\AppLayoutComposer;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Tahap frontend: data kegiatan tiruan (berkas JSON). Backend mengganti binding ini ke Eloquent.
        $this->app->singleton(KegiatanRepository::class, StubKegiatanRepository::class);
        $this->app->singleton(RabRepository::class, StubRabRepository::class);
        $this->app->singleton(MasterKodeRepository::class, StubMasterKodeRepository::class);
        $this->app->singleton(MatrikJadwalRepository::class, StubMatrikJadwalRepository::class);
        // Usulan AI tiruan; backend mengganti dengan driver Ollama/API cloud sesuai Pengaturan AI.
        $this->app->singleton(PenyusunUsulanJadwal::class, StubUsulanJadwal::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer(['layouts.app', 'layouts.partials.*'], AppLayoutComposer::class);

        if ($hot = config('sigenrabtor.vite_hot_file')) {
            Vite::useHotFile($hot);
        }
    }
}
