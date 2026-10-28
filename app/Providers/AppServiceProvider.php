<?php

namespace App\Providers;

use App\Contracts\ArsipFinalRepository;
use App\Contracts\BukuDokumenRepository;
use App\Contracts\CatatanPemeriksaanRepository;
use App\Contracts\DaftarIsiRepository;
use App\Contracts\DaftarModelAi;
use App\Contracts\DasarHukumRepository;
use App\Contracts\DataPaguRepository;
use App\Contracts\JejakDuplikatRepository;
use App\Contracts\KegiatanRepository;
use App\Contracts\LeadingRepository;
use App\Contracts\MasterKodeRepository;
use App\Contracts\MatrikJadwalRepository;
use App\Contracts\NomorHalamanRepository;
use App\Contracts\NotifikasiRepository;
use App\Contracts\PejabatRepository;
use App\Contracts\PemakaianAiRepository;
use App\Contracts\PembuatPdfBuku;
use App\Contracts\PengaturanAiRepository;
use App\Contracts\PenggabungPdf;
use App\Contracts\PenggunaRepository;
use App\Contracts\PengubahPdf;
use App\Contracts\PenyusunSaranKalimat;
use App\Contracts\PenyusunUsulanJadwal;
use App\Contracts\ProfilMadrasahRepository;
use App\Contracts\PromptAiRepository;
use App\Contracts\RabRepository;
use App\Contracts\RiwayatKunciRepository;
use App\Contracts\RiwayatPengaturanRepository;
use App\Contracts\RiwayatSaranAiRepository;
use App\Contracts\TahunAnggaranRepository;
use App\Contracts\TemplateCoverRepository;
use App\Contracts\TemplateDokumenRepository;
use App\Support\Dokumen\PenggabungPdfPoppler;
use App\Support\Dokumen\PengubahPdfLibreOffice;
use App\Support\Stub\StubArsipFinalRepository;
use App\Support\Stub\StubBukuDokumenRepository;
use App\Support\Stub\StubCatatanPemeriksaanRepository;
use App\Support\Stub\StubDaftarIsiRepository;
use App\Support\Stub\StubDaftarModelAi;
use App\Support\Stub\StubDasarHukumRepository;
use App\Support\Stub\StubDataPaguRepository;
use App\Support\Stub\StubJejakDuplikatRepository;
use App\Support\Stub\StubKegiatanRepository;
use App\Support\Stub\StubLeadingRepository;
use App\Support\Stub\StubMasterKodeRepository;
use App\Support\Stub\StubMatrikJadwalRepository;
use App\Support\Stub\StubNomorHalamanRepository;
use App\Support\Stub\StubNotifikasiRepository;
use App\Support\Stub\StubPejabatRepository;
use App\Support\Stub\StubPemakaianAiRepository;
use App\Support\Stub\StubPembuatPdfBuku;
use App\Support\Stub\StubPengaturanAiRepository;
use App\Support\Stub\StubPenggunaRepository;
use App\Support\Stub\StubProfilMadrasahRepository;
use App\Support\Stub\StubPromptAiRepository;
use App\Support\Stub\StubRabRepository;
use App\Support\Stub\StubRiwayatKunciRepository;
use App\Support\Stub\StubRiwayatPengaturanRepository;
use App\Support\Stub\StubRiwayatSaranAiRepository;
use App\Support\Stub\StubSaranKalimat;
use App\Support\Stub\StubTahunAnggaranRepository;
use App\Support\Stub\StubTemplateCoverRepository;
use App\Support\Stub\StubTemplateDokumenRepository;
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
        $this->app->singleton(TahunAnggaranRepository::class, StubTahunAnggaranRepository::class);
        $this->app->singleton(TemplateCoverRepository::class, StubTemplateCoverRepository::class);
        $this->app->singleton(TemplateDokumenRepository::class, StubTemplateDokumenRepository::class);
        $this->app->singleton(DaftarIsiRepository::class, StubDaftarIsiRepository::class);
        $this->app->singleton(NomorHalamanRepository::class, StubNomorHalamanRepository::class);
        $this->app->singleton(RiwayatPengaturanRepository::class, StubRiwayatPengaturanRepository::class);
        $this->app->singleton(ProfilMadrasahRepository::class, StubProfilMadrasahRepository::class);
        $this->app->singleton(ArsipFinalRepository::class, StubArsipFinalRepository::class);
        $this->app->singleton(NotifikasiRepository::class, StubNotifikasiRepository::class);
        $this->app->singleton(CatatanPemeriksaanRepository::class, StubCatatanPemeriksaanRepository::class);
        $this->app->singleton(RiwayatKunciRepository::class, StubRiwayatKunciRepository::class);
        $this->app->singleton(JejakDuplikatRepository::class, StubJejakDuplikatRepository::class);
        $this->app->singleton(BukuDokumenRepository::class, StubBukuDokumenRepository::class);
        $this->app->singleton(PembuatPdfBuku::class, StubPembuatPdfBuku::class);
        $this->app->singleton(PejabatRepository::class, StubPejabatRepository::class);
        $this->app->singleton(DataPaguRepository::class, StubDataPaguRepository::class);
        $this->app->singleton(DasarHukumRepository::class, StubDasarHukumRepository::class);
        $this->app->singleton(LeadingRepository::class, StubLeadingRepository::class);
        $this->app->singleton(PenggunaRepository::class, StubPenggunaRepository::class);
        // Usulan AI tiruan; backend mengganti dengan driver Ollama/API cloud sesuai Pengaturan AI.
        $this->app->singleton(PenyusunUsulanJadwal::class, StubUsulanJadwal::class);
        $this->app->singleton(PenyusunSaranKalimat::class, StubSaranKalimat::class);
        $this->app->singleton(PemakaianAiRepository::class, StubPemakaianAiRepository::class);
        $this->app->singleton(RiwayatSaranAiRepository::class, StubRiwayatSaranAiRepository::class);
        $this->app->singleton(PengaturanAiRepository::class, StubPengaturanAiRepository::class);
        $this->app->singleton(DaftarModelAi::class, StubDaftarModelAi::class);
        $this->app->singleton(PromptAiRepository::class, StubPromptAiRepository::class);

        // Konversi PDF (ekspor Monitoring; kelak cetak KAK/RAB & buku) lewat LibreOffice headless.
        $this->app->singleton(PengubahPdf::class, PengubahPdfLibreOffice::class);
        $this->app->singleton(PenggabungPdf::class, PenggabungPdfPoppler::class);
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
