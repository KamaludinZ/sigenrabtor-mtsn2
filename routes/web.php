<?php

use App\Contracts\PenggunaRepository;
use App\Http\Controllers\AmbilMasterKode;
use App\Http\Controllers\DataAgregasiMonitoring;
use App\Http\Controllers\DataDasarHukum;
use App\Http\Controllers\DataDashboard;
use App\Http\Controllers\DataKegiatanMonitoring;
use App\Http\Controllers\DataLatarBelakang;
use App\Http\Controllers\DataLeading;
use App\Http\Controllers\DataMonitoringAnggaran;
use App\Http\Controllers\DataPaguMonitoring;
use App\Http\Controllers\DataPejabat;
use App\Http\Controllers\DataProfilMadrasah;
use App\Http\Controllers\DataRiwayatPengaturan;
use App\Http\Controllers\DataSkRktm;
use App\Http\Controllers\DataTahunAnggaran;
use App\Http\Controllers\EksporMonitoringExcel;
use App\Http\Controllers\EksporMonitoringPdf;
use App\Http\Controllers\EksporRabKegiatan;
use App\Http\Controllers\EksporRabPdfKegiatan;
use App\Http\Controllers\GambarCover;
use App\Http\Controllers\KonteksKegiatan;
use App\Http\Controllers\LogoMadrasah;
use App\Http\Controllers\MatrikJadwalKegiatan;
use App\Http\Controllers\PaguDanRabTahunan;
use App\Http\Controllers\RabLengkapKegiatan;
use App\Http\Controllers\SalinMasterKodeTahun;
use App\Http\Controllers\StatusCetakKegiatan;
use App\Http\Controllers\SumberDanaKegiatan;
use App\Http\Controllers\TerminKegiatan;
use App\Http\Controllers\TotalAnggaranKegiatan;
use App\Http\Controllers\UnduhArsipFinal;
use App\Http\Controllers\UnduhBuku;
use App\Http\Controllers\UnduhFormatRincianRab;
use App\Http\Controllers\UnduhMasterTemplate;
use App\Http\Controllers\UnduhTemplateDataPagu;
use App\Http\Controllers\UnduhTemplateImporKegiatan;
use App\Http\Controllers\UnggahMasterKode;
use App\Http\Middleware\HanyaAdmin;
use App\Livewire\Buku\Daftar as BukuDaftar;
use App\Livewire\Dashboard;
use App\Livewire\Kegiatan\Daftar as KegiatanDaftar;
use App\Livewire\Kegiatan\Form as KegiatanForm;
use App\Livewire\Kegiatan\KalimatAi as KegiatanKalimatAi;
use App\Livewire\Kegiatan\Klasifikasi as KegiatanKlasifikasi;
use App\Livewire\Kegiatan\MatrikJadwal as KegiatanMatrikJadwal;
use App\Livewire\Kegiatan\PratinjauDokumen as KegiatanPratinjauDokumen;
use App\Livewire\Kegiatan\Rab as KegiatanRab;
use App\Livewire\Kegiatan\SalinImpor as KegiatanSalinImpor;
use App\Livewire\Master\Daftar as MasterKodeDaftar;
use App\Livewire\Monitoring\Anggaran as MonitoringAnggaran;
use App\Livewire\Pagu\Daftar as DataPaguDaftar;
use App\Livewire\Pemeriksaan\Daftar as PemeriksaanDaftar;
use App\Livewire\Pemeriksaan\Periksa as PemeriksaanPeriksa;
use App\Livewire\Pengaturan\DasarHukum as PengaturanDasarHukum;
use App\Livewire\Pengaturan\Ikhtisar as PengaturanIkhtisar;
use App\Livewire\Pengaturan\LeadingUser as PengaturanLeadingUser;
use App\Livewire\Pengaturan\PengaturanAi;
use App\Livewire\Pengaturan\ProfilMadrasah as PengaturanProfilMadrasah;
use App\Livewire\Pengaturan\Riwayat as PengaturanRiwayat;
use App\Livewire\Pengaturan\TahunAnggaran as PengaturanTahunAnggaran;
use App\Livewire\Pengaturan\TemplateDokumen as PengaturanTemplateDokumen;
use App\Livewire\SaranAi\Riwayat as SaranAiRiwayat;
use App\Support\Master\JenisKode;
use App\Support\Stub\StubData;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::get('/dashboard', Dashboard::class)->name('dashboard');
Route::get('/dashboard/data', DataDashboard::class)->name('dashboard.data');

Route::get('/kegiatan', KegiatanDaftar::class)->name('kegiatan.index');
Route::get('/kegiatan/template-impor', UnduhTemplateImporKegiatan::class)->name('kegiatan.template');
Route::get('/kegiatan/format-rincian-rab', UnduhFormatRincianRab::class)->name('kegiatan.rab.template');
Route::get('/kegiatan/buat', KegiatanForm::class)->name('kegiatan.create');
Route::get('/kegiatan/salin-impor', KegiatanSalinImpor::class)->name('kegiatan.salin_impor');
Route::get('/data-pagu', DataPaguDaftar::class)->name('pagu.index');
Route::get('/buku', BukuDaftar::class)->name('buku.index');
Route::get('/buku/{buku}/unduh', UnduhBuku::class)->whereUuid('buku')->name('buku.unduh');
Route::get('/data-pagu/template', UnduhTemplateDataPagu::class)->middleware(HanyaAdmin::class)->name('pagu.template');
Route::get('/data-pagu/pagu-rab', PaguDanRabTahunan::class)->name('pagu.rab');
Route::get('/kegiatan/{kegiatan}/edit', KegiatanForm::class)->whereNumber('kegiatan')->name('kegiatan.edit');
Route::get('/kegiatan/{kegiatan}/kode', KegiatanKlasifikasi::class)->whereNumber('kegiatan')->name('kegiatan.kode');
Route::get('/kegiatan/{kegiatan}/kalimat', KegiatanKalimatAi::class)->whereNumber('kegiatan')->name('kegiatan.kalimat');
Route::get('/riwayat-saran-ai', SaranAiRiwayat::class)->name('saran_ai.index');
Route::get('/kegiatan/{kegiatan}/rab', KegiatanRab::class)->whereNumber('kegiatan')->name('kegiatan.rab');
Route::get('/kegiatan/{kegiatan}/rab/excel', EksporRabKegiatan::class)->whereNumber('kegiatan')->name('kegiatan.rab.excel');
Route::get('/kegiatan/{kegiatan}/rab/pdf', EksporRabPdfKegiatan::class)->whereNumber('kegiatan')->name('kegiatan.rab.pdf');
Route::get('/kegiatan/{kegiatan}/matrik', KegiatanMatrikJadwal::class)->whereNumber('kegiatan')->name('kegiatan.matrik');
Route::get('/kegiatan/{kegiatan}/pratinjau', KegiatanPratinjauDokumen::class)->whereNumber('kegiatan')->name('kegiatan.pratinjau');
Route::get('/kegiatan/{kegiatan}/arsip/{sumber}', UnduhArsipFinal::class)->whereNumber('kegiatan')->name('kegiatan.arsip');
Route::get('/kegiatan/{kegiatan}/total-anggaran', TotalAnggaranKegiatan::class)->whereNumber('kegiatan')->name('kegiatan.total_anggaran');
Route::get('/kegiatan/{kegiatan}/rab-lengkap', RabLengkapKegiatan::class)->whereNumber('kegiatan')->name('kegiatan.rab_lengkap');
Route::get('/kegiatan/{kegiatan}/konteks', KonteksKegiatan::class)->whereNumber('kegiatan')->name('kegiatan.konteks');
Route::get('/kegiatan/{kegiatan}/termin', TerminKegiatan::class)->whereNumber('kegiatan')->name('kegiatan.termin');
Route::get('/kegiatan/{kegiatan}/sumber-dana', SumberDanaKegiatan::class)->whereNumber('kegiatan')->name('kegiatan.sumber_dana');
Route::get('/kegiatan/{kegiatan}/status-cetak', StatusCetakKegiatan::class)->whereNumber('kegiatan')->name('kegiatan.status_cetak');
Route::get('/kegiatan/{kegiatan}/matrik-jadwal', MatrikJadwalKegiatan::class)->whereNumber('kegiatan')->name('kegiatan.matrik_jadwal');
// Gambar desain cover untuk pratinjau dokumen semua pengguna (pengelolaannya tetap khusus admin).
Route::get('/dokumen/cover/{cover}/gambar', GambarCover::class)->whereUuid('cover')->name('dokumen.cover.gambar');

// Monitoring anggaran lintas kegiatan & leading (PRD Fase 5 §11), khusus admin.
Route::get('/monitoring', MonitoringAnggaran::class)->middleware(HanyaAdmin::class)->name('monitoring.index');
Route::get('/monitoring/data', DataMonitoringAnggaran::class)->middleware(HanyaAdmin::class)->name('monitoring.data');
Route::get('/monitoring/pagu', DataPaguMonitoring::class)->middleware(HanyaAdmin::class)->name('monitoring.pagu');
Route::get('/monitoring/kegiatan', DataKegiatanMonitoring::class)->middleware(HanyaAdmin::class)->name('monitoring.kegiatan');
Route::get('/monitoring/agregasi', DataAgregasiMonitoring::class)->middleware(HanyaAdmin::class)->name('monitoring.agregasi');
Route::get('/monitoring/ekspor/excel', EksporMonitoringExcel::class)->middleware(HanyaAdmin::class)->name('monitoring.ekspor.excel');
Route::get('/monitoring/ekspor/pdf', EksporMonitoringPdf::class)->middleware(HanyaAdmin::class)->name('monitoring.ekspor.pdf');

// Pemeriksaan & arsip final (PRD Fase 6 §16), khusus admin.
Route::get('/pemeriksaan', PemeriksaanDaftar::class)->middleware(HanyaAdmin::class)->name('pemeriksaan.index');
Route::get('/pemeriksaan/{kegiatan}', PemeriksaanPeriksa::class)->whereNumber('kegiatan')->middleware(HanyaAdmin::class)->name('pemeriksaan.periksa');

// Master kode tahunan (PRD Fase 6 §14), khusus admin.
Route::get('/master', MasterKodeDaftar::class)->middleware(HanyaAdmin::class)->name('master.index');
Route::post('/master/{tahun}/unggah/{jenis}', UnggahMasterKode::class)->middleware(HanyaAdmin::class)->whereNumber('tahun')->name('master.unggah');
Route::post('/master/{tahun}/salin', SalinMasterKodeTahun::class)->middleware(HanyaAdmin::class)->whereNumber('tahun')->name('master.salin');
Route::get('/master-kode/{jenis}', AmbilMasterKode::class)->whereIn('jenis', array_keys(JenisKode::DAFTAR))->name('master.kode');

// Logo kop madrasah: tampil di kop & pratinjau dokumen semua pengguna.
Route::get('/madrasah/logo', LogoMadrasah::class)->name('madrasah.logo');
Route::get('/madrasah/profil', DataProfilMadrasah::class)->name('madrasah.profil');

// Pengaturan admin (PRD Fase 5–7): seluruh halaman khusus admin.
Route::middleware(HanyaAdmin::class)->prefix('pengaturan')->name('pengaturan.')->group(function () {
    Route::get('/', PengaturanIkhtisar::class)->name('index');
    Route::get('/tahun', PengaturanTahunAnggaran::class)->name('tahun.index');
    Route::get('/tahun/data', DataTahunAnggaran::class)->name('tahun.data');
    Route::get('/madrasah', PengaturanProfilMadrasah::class)->name('madrasah.index');
    Route::get('/madrasah/dasar-hukum', PengaturanDasarHukum::class)->name('madrasah.dasar_hukum');
    Route::get('/madrasah/dasar-hukum/data', DataDasarHukum::class)->name('madrasah.dasar_hukum.data');
    Route::get('/madrasah/latar-belakang/data', DataLatarBelakang::class)->name('madrasah.latar_belakang.data');
    Route::get('/madrasah/sk-rktm/data', DataSkRktm::class)->name('madrasah.sk_rktm.data');
    Route::get('/madrasah/pejabat', DataPejabat::class)->name('madrasah.pejabat');
    Route::get('/leading', PengaturanLeadingUser::class)->name('leading.index');
    Route::get('/leading/data', DataLeading::class)->name('leading.data');
    Route::get('/riwayat', PengaturanRiwayat::class)->name('riwayat.index');
    Route::get('/riwayat/data', DataRiwayatPengaturan::class)->name('riwayat.data');
    Route::get('/ai', PengaturanAi::class)->name('ai.index');
    Route::get('/template', PengaturanTemplateDokumen::class)->name('template.index');
    Route::get('/template/cover/{cover}/gambar', GambarCover::class)->whereUuid('cover')->name('template.cover.gambar');
    Route::get('/template/master/{template}/unduh', UnduhMasterTemplate::class)->whereUuid('template')->name('template.master.unduh');
});

// Tahap data tiruan: sakelar peran untuk mencoba tampilan Admin/Leading sebelum autentikasi nyata dibangun.
Route::get('/demo/peran/{role}', function (string $role) {
    session(['stub_role' => $role]);
    // Padanan peristiwa masuk: waktu masuk terakhir akun persona tercatat di Pengaturan › Leading & User.
    try {
        app(PenggunaRepository::class)->catatMasuk(StubData::currentUser()['id'], request()->ip(), request()->userAgent());
    } catch (RuntimeException $e) {
        report($e);
    }

    return back();
})->whereIn('role', ['admin', 'leading'])->name('demo.peran');
