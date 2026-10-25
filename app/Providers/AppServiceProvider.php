<?php

namespace App\Providers;

use App\Contracts\KegiatanRepository;
use App\Contracts\MasterKodeRepository;
use App\Contracts\RabRepository;
use App\Support\Stub\StubKegiatanRepository;
use App\Support\Stub\StubMasterKodeRepository;
use App\Support\Stub\StubRabRepository;
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
