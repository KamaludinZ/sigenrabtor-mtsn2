<?php

namespace App\Livewire\Pengaturan;

use App\Contracts\DaftarModelAi;
use App\Contracts\KegiatanRepository;
use App\Contracts\PengaturanAiRepository;
use App\Contracts\PromptAiRepository;
use App\Contracts\RiwayatPengaturanRepository;
use App\Support\Kegiatan\BagianAi;
use App\Support\Kegiatan\BatasAi;
use App\Support\Kegiatan\KegagalanAi;
use App\Support\Kegiatan\RiwayatSaranAi;
use App\Support\Pengaturan\AturanPenyediaAi;
use App\Support\Pengaturan\KelolaBatasAi;
use App\Support\Pengaturan\KelolaModelAi;
use App\Support\Pengaturan\KelolaPenyediaAi;
use App\Support\Pengaturan\KelolaPromptAi;
use App\Support\Pengaturan\ParameterAi;
use App\Support\Pengaturan\PengaturanBerubah;
use App\Support\Pengaturan\PenyediaAi;
use App\Support\Pengaturan\PromptAi;
use App\Support\Pengaturan\RiwayatPengaturan;
use App\Support\Stub\StubData;
use App\Support\Stub\StubPengaturan;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use RuntimeException;

/**
 * Pengaturan › Pengaturan AI (PRD §21, F24; `/pengaturan/ai`, rute `pengaturan.ai.index`): tab `?tab=penyedia` (penyedia
 * aktif & cadangan, model, alamat, kunci API, perbandingan server sekolah vs API cloud, data yang dikirim), `prompt`
 * (prompt per bagian & gaya bahasa baku — PromptAi), dan `pemakaian` (batas harian BatasAi, pemakaian hari ini per akun,
 * ringkasan riwayat saran AI). Konfigurasi server sekolah (Ollama) lewat formulir bukaLokal() → simpanLokal()
 * (AturanPenyediaAi, PengaturanAiRepository, riwayat `ubah_penyedia_ai`); pemilihan penyedia aktif & cadangan lewat
 * bukaPilihan() → simpanPilihan() (hanya penyedia yang siap); penyedia cloud lewat bukaCloud() → simpanCloud() (kunci API
 * terenkripsi, tidak pernah ditampilkan lagi); tab `model` — daftar model per penyedia (DaftarModelAi) & parameter jawaban
 * (ParameterAi, riwayat `ubah_model_ai`); tab `prompt` — gaya bahasa baku & template per bagian (PromptAi,
 * PromptAiRepository, riwayat `ubah_prompt_ai`, pratinjau prompt contoh kegiatan); tab `pemakaian` — batas umum &
 * batas khusus per akun (BatasAi, riwayat `ubah_batas_ai`).
 */
#[Layout('layouts.app')]
class PengaturanAi extends Component
{
    public const TAB = ['penyedia' => 'Penyedia', 'model' => 'Model & parameter', 'prompt' => 'Prompt per bagian', 'pemakaian' => 'Batas pemakaian'];

    #[Url(except: 'penyedia')]
    public string $tab = 'penyedia';

    /** Formulir konfigurasi server sekolah (Ollama) terbuka. */
    public bool $lokalTerbuka = false;

    /** @var array{base_url: string, model: string, batas_waktu: int|string|null, peran: string} */
    public array $isianLokal = ['base_url' => '', 'model' => '', 'batas_waktu' => 60, 'peran' => 'tidak'];

    /** Penyedia cloud yang formulirnya terbuka (kunci PenyediaAi). */
    #[Locked]
    public ?string $cloudTerbuka = null;

    /** @var array{model: string, kunci: string, hapus_kunci: bool, base_url: string, peran: string} Kunci API tidak pernah diisi dari data tersimpan. */
    public array $isianCloud = ['model' => '', 'kunci' => '', 'hapus_kunci' => false, 'base_url' => '', 'peran' => 'tidak'];

    /** @var list<string> Peringatan isian cloud yang perlu dikonfirmasi ("Tetap simpan"). */
    #[Locked]
    public array $peringatanCloud = [];

    /** @var array{suhu: float|string, maks_token: int|string, jumlah_versi: int|string} Parameter jawaban yang sedang disunting. */
    public array $isianParameter = ParameterAi::BAWAAN;

    /** `diperbarui_pada` saat parameter dimuat (pembanding bentrok). */
    #[Locked]
    public ?string $versiParameter = null;

    /** @var array<string, array{model: list<array<string, mixed>>, galat: string|null}> Daftar model yang sudah dimuat per penyedia. */
    #[Locked]
    public array $daftarModel = [];

    /** Penyunting gaya bahasa baku terbuka. */
    public bool $gayaTerbuka = false;

    public string $isianGaya = '';

    /** Bagian yang template prompt-nya sedang disunting. */
    #[Locked]
    public ?string $promptTerbuka = null;

    /** @var array{instruksi: string, gaya_khusus: bool, gaya_bahasa: string, aktif: bool} */
    public array $isianPrompt = ['instruksi' => '', 'gaya_khusus' => false, 'gaya_bahasa' => '', 'aktif' => true];

    /** Batas umum per akun per hari yang sedang disunting. */
    public int|string $isianBatas = 20;

    /** `diperbarui_pada` saat batas umum dimuat (pembanding bentrok). */
    #[Locked]
    public ?string $versiBatas = null;

    /** Akun yang batas khususnya sedang disunting. */
    #[Locked]
    public ?int $khususTerbuka = null;

    public int|string $isianKhusus = '';

    /** @var array{jenis: string, cakupan: string} Simulasi kegagalan penyedia ('' = tidak ada). */
    public array $isianSimulasi = ['jenis' => '', 'cakupan' => 'aktif'];

    /** @var array{bagian: string, pesan: string}|null Kegagalan simpan terakhir, tampil di formulir bagiannya (Coba lagi). */
    #[Locked]
    public ?array $gagalSimpan = null;

    /** Bagian formulir yang sedang disimpan dalam permintaan ini (event `pengaturan-ai-tersimpan`, $gagalSimpan). */
    private string $bagianSimpan = '';

    /** `diperbarui_pada` template prompt saat penyunting dibuka. */
    #[Locked]
    public ?string $versiPrompt = null;

    /** Pemilihan penyedia aktif & cadangan terbuka. */
    public bool $pilihTerbuka = false;

    /** @var array{aktif: string, cadangan: string} Kunci PenyediaAi ('' = tanpa). */
    public array $pilihan = ['aktif' => '', 'cadangan' => ''];

    /** `diperbarui_pada` pengaturan saat formulir dibuka; pembanding perubahan dari tab/admin lain. */
    #[Locked]
    public ?string $versi = null;

    public function mount(): void
    {
        $this->rapikanTab();
        $this->muatParameter();
    }

    public function updatedTab(): void
    {
        $this->rapikanTab();
    }

    /**
     * Pengaturan tersimpan dengan nama penyedia terbaca.
     *
     * @return array<string, mixed>
     */
    public function pengaturan(): array
    {
        $ai = StubPengaturan::pengaturanAi();

        return $ai + ['nama_aktif' => PenyediaAi::nama($ai['provider_aktif']), 'nama_cadangan' => PenyediaAi::nama($ai['provider_cadangan'])];
    }

    /** Buka formulir server sekolah berisi konfigurasi tersimpan. */
    public function bukaLokal(): void
    {
        $ai = app(PengaturanAiRepository::class)->ambil();
        $lokal = $ai['penyedia']['ollama'] ?? [];
        $this->isianLokal = [
            'base_url' => (string) ($lokal['base_url'] ?? ''),
            'model' => (string) ($lokal['model'] ?? ''),
            'batas_waktu' => (int) ($lokal['batas_waktu'] ?? 60),
            'peran' => AturanPenyediaAi::peranDari($ai, 'ollama'),
        ];
        $this->versi = $ai['diperbarui_pada'];
        $this->resetErrorBag();
        $this->pilihTerbuka = false;
        $this->cloudTerbuka = null;
        $this->lokalTerbuka = true;
    }

    public function batalLokal(): void
    {
        $this->lokalTerbuka = false;
        $this->resetErrorBag();
    }

    /**
     * Simpan konfigurasi server sekolah beserta perannya (aktif/cadangan/tidak dipakai). Perubahan dari tab/admin lain
     * sejak formulir dibuka membatalkan simpan tanpa membuang isian; kegagalan penyimpanan dilaporkan tanpa menutup.
     */
    public function simpanLokal(): void
    {
        $this->bagianSimpan = 'lokal';
        $this->resetErrorBag();
        // Aturan & penulisan bersama endpoint pengaturan.ai.penyedia.simpan (KelolaPenyediaAi).
        $hasil = $this->jalankanSimpan(fn () => KelolaPenyediaAi::simpanLokal($this->isianLokal, StubData::currentUser(), $this->versi), 'isianLokal');
        if ($hasil === false) {
            return;
        }

        $this->lokalTerbuka = false;
        $this->dispatch('notify', message: $hasil['pesan'], type: 'success');
    }

    public function ubahGaya(): void
    {
        $this->isianGaya = PromptAi::gayaBahasa();
        $this->versiPrompt = app(PromptAiRepository::class)->ambil()['diperbarui_pada'];
        $this->promptTerbuka = null;
        $this->resetErrorBag();
        $this->gayaTerbuka = true;
    }

    public function batalGaya(): void
    {
        $this->gayaTerbuka = false;
        $this->resetErrorBag();
    }

    public function pakaiGayaBawaan(): void
    {
        $this->isianGaya = PromptAi::GAYA_BAHASA;
    }

    /** Simpan gaya bahasa baku semua bagian (sama dengan bawaan = tidak disimpan). */
    public function simpanGaya(): void
    {
        $this->bagianSimpan = 'gaya';
        $this->resetErrorBag();
        // Aturan & penulisan bersama endpoint pengaturan.ai.prompt.gaya (KelolaPromptAi).
        $hasil = $this->jalankanSimpanPrompt(fn () => KelolaPromptAi::simpanGaya($this->isianGaya, StubData::currentUser(), $this->versiPrompt), ['gaya_bahasa' => 'isianGaya']);
        if ($hasil === false) {
            return;
        }
        $this->gayaTerbuka = false;
        $this->dispatch('notify', message: $hasil['pesan'], type: 'success');
    }

    /** Buka penyunting template prompt satu bagian. */
    public function ubahPrompt(string $bagian): void
    {
        abort_unless(isset(BagianAi::BAGIAN[$bagian]), 404);
        $t = PromptAi::untuk($bagian);
        $this->isianPrompt = ['instruksi' => $t['instruksi'], 'gaya_khusus' => $t['gaya_bahasa'] !== null, 'gaya_bahasa' => (string) $t['gaya_bahasa'], 'aktif' => $t['aktif']];
        $this->versiPrompt = app(PromptAiRepository::class)->ambil()['diperbarui_pada'];
        $this->gayaTerbuka = false;
        $this->resetErrorBag();
        $this->promptTerbuka = $bagian;
    }

    public function batalPrompt(): void
    {
        $this->promptTerbuka = null;
        $this->resetErrorBag();
    }

    /** Isi penyunting dengan prompt bawaan bagian ini (tersimpan setelah Simpan). */
    public function pakaiPromptBawaan(): void
    {
        abort_unless($this->promptTerbuka !== null, 404);
        $this->isianPrompt = ['instruksi' => PromptAi::BAWAAN[$this->promptTerbuka], 'gaya_khusus' => false, 'gaya_bahasa' => '', 'aktif' => true];
        $this->resetErrorBag();
    }

    /** Simpan template prompt bagian yang disunting (PromptAi::galat). */
    public function simpanPromptBagian(): void
    {
        $this->bagianSimpan = 'prompt';
        $bagian = $this->promptTerbuka;
        abort_unless($bagian !== null, 404);
        $this->resetErrorBag();

        $isian = [
            'instruksi' => $this->isianPrompt['instruksi'] ?? '',
            'gaya_bahasa' => ($this->isianPrompt['gaya_khusus'] ?? false) ? (string) ($this->isianPrompt['gaya_bahasa'] ?? '') : null,
            'aktif' => (bool) ($this->isianPrompt['aktif'] ?? true),
        ];
        $hasil = $this->jalankanSimpanPrompt(fn () => KelolaPromptAi::simpanBagian($bagian, $isian, StubData::currentUser(), $this->versiPrompt), 'isianPrompt');
        if ($hasil === false) {
            return;
        }
        $this->promptTerbuka = null;
        $this->dispatch('notify', message: $hasil['pesan'], type: 'success');
    }

    /**
     * Pratinjau prompt lengkap bagian yang disunting untuk contoh kegiatan (kegiatan bernama dengan data terlengkap).
     */
    public function pratinjauPrompt(): string
    {
        if ($this->promptTerbuka === null) {
            return '';
        }
        $contoh = collect(app(KegiatanRepository::class)->semua())->filter(fn (array $k) => filled($k['nama_kegiatan'] ?? null))
            ->sortByDesc(fn (array $k) => count(array_filter(BagianAi::bahan($this->promptTerbuka, $k), fn ($b) => $b['nilai'] !== null)))->first()
            ?? ['nama_kegiatan' => 'Workshop Penyusunan Kurikulum Madrasah'];

        return PromptAi::susun($this->promptTerbuka, $contoh, [
            'instruksi' => (string) ($this->isianPrompt['instruksi'] ?? ''),
            'gaya_bahasa' => ($this->isianPrompt['gaya_khusus'] ?? false) ? (string) ($this->isianPrompt['gaya_bahasa'] ?? '') : null,
        ]);
    }

    /** Simpan batas umum per akun per hari (BatasAi::RENTANG). */
    public function simpanBatas(): void
    {
        $this->bagianSimpan = 'batas';
        $this->resetErrorBag();
        // Aturan & penulisan bersama endpoint pengaturan.ai.batas.simpan (KelolaBatasAi).
        $hasil = $this->jalankanSimpan(fn () => KelolaBatasAi::simpanUmum($this->isianBatas, StubData::currentUser(), $this->versiBatas), ['batas' => 'isianBatas'], 'versiBatas');
        if ($hasil === false) {
            return;
        }
        $this->isianBatas = $hasil['batas'];
        $this->versiBatas = $this->versi;
        $this->dispatch('notify', message: $hasil['pesan'], type: 'success');
    }

    public function pilihPresetBatas(int $batas): void
    {
        abort_unless(in_array($batas, BatasAi::PRESET, true), 404);
        $this->isianBatas = $batas;
        $this->resetErrorBag('isianBatas');
    }

    /** Buka isian batas khusus satu akun (kosong = memakai batas umum). */
    public function ubahKhusus(int $id): void
    {
        $akun = collect(StubPengaturan::pengguna())->firstWhere('id', $id);
        abort_unless($akun !== null && $akun['is_active'], 404);
        $this->isianKhusus = BatasAi::khusus($akun) ?? BatasAi::batas();
        $this->resetErrorBag();
        $this->khususTerbuka = $id;
    }

    public function batalKhusus(): void
    {
        $this->khususTerbuka = null;
        $this->resetErrorBag();
    }

    /** Simpan batas khusus akun yang dibuka; sama dengan batas umum = khusus dibuang. */
    public function simpanKhusus(): void
    {
        $id = $this->khususTerbuka;
        abort_unless($id !== null, 404);
        $this->resetErrorBag();
        if ($this->ubahBatasKhusus($id, $this->isianKhusus, ['batas' => 'isianKhusus', 'akun' => 'isianKhusus'])) {
            $this->khususTerbuka = null;
        }
    }

    /** Kembalikan akun ke batas umum. */
    public function hapusKhusus(int $id): void
    {
        $this->ubahBatasKhusus($id, null, ['akun' => 'isianKhusus']);
        $this->khususTerbuka = null;
    }

    /**
     * Simpan simulasi kegagalan penyedia: jenis KegagalanAi + cakupan (`aktif` = hanya penyedia aktif, menguji cadangan;
     * `semua` = semua penyedia gagal). Jenis kosong = simulasi dimatikan.
     */
    public function simpanSimulasi(): void
    {
        $this->bagianSimpan = 'simulasi';
        $this->resetErrorBag();
        $jenis = (string) ($this->isianSimulasi['jenis'] ?? '');
        $cakupan = (string) ($this->isianSimulasi['cakupan'] ?? 'aktif');
        if ($jenis !== '' && ! isset(KegagalanAi::JENIS[$jenis])) {
            $this->addError('isianSimulasi.jenis', 'Pilih jenis kegagalan.');

            return;
        }
        if (! in_array($cakupan, ['aktif', 'semua'], true)) {
            $this->addError('isianSimulasi.cakupan', 'Pilih cakupan simulasi.');

            return;
        }

        $lama = app(PengaturanAiRepository::class)->ambil();
        $baru = ['simulasi_gagal' => $jenis === '' ? null : ['jenis' => $jenis, 'cakupan' => $cakupan]] + $lama;
        $this->versi = $lama['diperbarui_pada'];
        $ringkas = fn (array $p) => ['simulasi' => ($p['simulasi_gagal'] ?? null) ? KegagalanAi::judul($p['simulasi_gagal']['jenis']).' ('.($p['simulasi_gagal']['cakupan'] === 'semua' ? 'semua penyedia' : 'penyedia aktif').')' : 'mati'];
        if (! $this->simpanPengaturan($lama, $baru, $ringkas, ['simulasi' => 'Simulasi kegagalan'], '', null, 'ubah_simulasi_ai')) {
            return;
        }
        $this->dispatch('notify', message: $jenis === '' ? 'Simulasi kegagalan dimatikan — permintaan AI kembali normal.' : 'Simulasi kegagalan aktif: '.KegagalanAi::judul($jenis).'. Coba tombol “Buat dengan AI” lalu matikan setelah selesai.', type: $jenis === '' ? 'success' : 'info');
    }

    public function matikanSimulasi(): void
    {
        $this->isianSimulasi['jenis'] = '';
        $this->simpanSimulasi();
    }

    /** Muat daftar model penyedia (DaftarModelAi); kegagalan tampil di kartu modelnya. */
    public function muatModel(string $penyedia, DaftarModelAi $daftar): void
    {
        abort_unless(isset(PenyediaAi::DAFTAR[$penyedia]), 404);
        $konfigurasi = app(PengaturanAiRepository::class)->ambil()['penyedia'][$penyedia] ?? [];
        try {
            $this->daftarModel[$penyedia] = ['model' => array_values($daftar->daftar($penyedia, $konfigurasi)), 'galat' => null];
        } catch (RuntimeException $e) {
            $this->daftarModel[$penyedia] = ['model' => [], 'galat' => $e->getMessage()];
        }
    }

    /** Pakai satu model dari daftar yang dimuat untuk penyedia itu. */
    public function pakaiModel(string $penyedia, string $model): void
    {
        $this->bagianSimpan = 'model';
        $dimuat = array_column($this->daftarModel[$penyedia]['model'] ?? [], 'nama');
        abort_unless(in_array($model, $dimuat, true), 404);

        // Model dipilih dari daftar yang baru dimuat: tanpa pemeriksaan versi formulir.
        $this->versi = app(PengaturanAiRepository::class)->ambil()['diperbarui_pada'];
        $hasil = $this->jalankanSimpan(fn () => KelolaModelAi::pakai($penyedia, $model, StubData::currentUser(), null, false, $dimuat));
        if ($hasil !== false) {
            $this->dispatch('notify', message: $hasil['pesan'], type: 'success');
        }
    }

    /** Simpan parameter jawaban (ParameterAi). */
    public function simpanParameter(): void
    {
        $this->bagianSimpan = 'parameter';
        $this->resetErrorBag();
        $hasil = $this->jalankanSimpan(fn () => KelolaModelAi::simpanParameter($this->isianParameter, StubData::currentUser(), $this->versiParameter), 'isianParameter', 'versiParameter');
        if ($hasil === false) {
            return;
        }

        $this->isianParameter = $hasil['parameter'];
        $this->versiParameter = $this->versi;
        $this->dispatch('notify', message: $hasil['pesan'], type: 'success');
    }

    /** Isi kembali parameter bawaan (tersimpan setelah Simpan). */
    public function kembalikanParameter(): void
    {
        $this->isianParameter = ParameterAi::BAWAAN;
        $this->resetErrorBag();
    }

    /** Pakai preset kreativitas. */
    public function pilihPresetSuhu(string $preset): void
    {
        abort_unless(isset(ParameterAi::PRESET_SUHU[$preset]), 404);
        $this->isianParameter['suhu'] = ParameterAi::PRESET_SUHU[$preset][0];
    }

    /** Buka formulir satu penyedia cloud; kunci API tersimpan tidak pernah dimuat ke formulir. */
    public function bukaCloud(string $penyedia): void
    {
        abort_unless((PenyediaAi::DAFTAR[$penyedia]['jenis'] ?? null) === 'cloud', 404);
        $ai = app(PengaturanAiRepository::class)->ambil();
        $konfigurasi = $ai['penyedia'][$penyedia] ?? [];
        $this->isianCloud = [
            'model' => (string) ($konfigurasi['model'] ?? ''), 'kunci' => '', 'hapus_kunci' => false,
            'base_url' => (string) ($konfigurasi['base_url'] ?? ''), 'peran' => AturanPenyediaAi::peranDari($ai, $penyedia),
        ];
        $this->versi = $ai['diperbarui_pada'];
        $this->peringatanCloud = [];
        $this->resetErrorBag();
        $this->lokalTerbuka = false;
        $this->pilihTerbuka = false;
        $this->cloudTerbuka = $penyedia;
    }

    public function batalCloud(): void
    {
        $this->cloudTerbuka = null;
        $this->isianCloud['kunci'] = '';
        $this->peringatanCloud = [];
        $this->resetErrorBag();
    }

    /** Kunci yang diketik berubah → peringatan sebelumnya perlu dinilai ulang. */
    public function updatedIsianCloud(mixed $nilai, ?string $kunci = null): void
    {
        if ($kunci === 'kunci') {
            $this->peringatanCloud = [];
        }
    }

    /**
     * Simpan konfigurasi penyedia cloud: model, alamat API opsional, peran, dan kunci API (baru → disimpan terenkripsi,
     * hanya 4 karakter terakhir yang tampil; kosong → kunci lama tetap; hapus → dibuang). Peringatan (mis. awalan kunci
     * tidak lazim) harus dikonfirmasi dengan menyimpan sekali lagi.
     */
    public function simpanCloud(): void
    {
        $this->bagianSimpan = 'cloud';
        $penyedia = $this->cloudTerbuka;
        abort_unless($penyedia !== null, 404);
        $this->resetErrorBag();

        // Peringatan awalan kunci tampil sekali; simpan berikutnya (tanpa mengubah kunci) dianggap "Tetap simpan".
        $hasil = $this->jalankanSimpan(fn () => KelolaPenyediaAi::simpanCloud($penyedia, $this->isianCloud, StubData::currentUser(), $this->versi, tetapSimpan: $this->peringatanCloud !== []), 'isianCloud');
        if ($hasil === false) {
            return;
        }
        if (! $hasil['tersimpan']) {
            $this->peringatanCloud = $hasil['peringatan'];

            return;
        }

        $this->batalCloud();
        $this->dispatch('notify', message: $hasil['pesan'], type: 'success');
    }

    /** Buka pemilihan penyedia aktif & cadangan berisi pilihan tersimpan. */
    public function bukaPilihan(): void
    {
        $ai = app(PengaturanAiRepository::class)->ambil();
        $this->pilihan = ['aktif' => (string) $ai['provider_aktif'], 'cadangan' => (string) $ai['provider_cadangan']];
        $this->versi = $ai['diperbarui_pada'];
        $this->resetErrorBag();
        $this->lokalTerbuka = false;
        $this->cloudTerbuka = null;
        $this->pilihTerbuka = true;
    }

    public function batalPilihan(): void
    {
        $this->pilihTerbuka = false;
        $this->resetErrorBag();
    }

    /** Tukar pilihan aktif ↔ cadangan (belum tersimpan). */
    public function tukarPilihan(): void
    {
        $this->pilihan = ['aktif' => $this->pilihan['cadangan'] ?? '', 'cadangan' => $this->pilihan['aktif'] ?? ''];
        $this->resetErrorBag();
    }

    /** Simpan penyedia aktif & cadangan (AturanPenyediaAi::pilih — keduanya harus siap). */
    public function simpanPilihan(): void
    {
        $this->bagianSimpan = 'pilihan';
        $this->resetErrorBag();
        $hasil = $this->jalankanSimpan(fn () => KelolaPenyediaAi::pilih($this->pilihan['aktif'] ?? null, $this->pilihan['cadangan'] ?? null, StubData::currentUser(), $this->versi), 'pilihan');
        if ($hasil === false) {
            return;
        }

        $this->pilihTerbuka = false;
        $this->dispatch('notify', message: $hasil['pesan'], type: 'success');
    }

    /**
     * Kesiapan tiap penyedia (pemilihan penyedia).
     *
     * @return array<string, array{siap: bool, kurang: list<string>}>
     */
    public function kesiapanPenyedia(): array
    {
        $ai = app(PengaturanAiRepository::class)->ambil();

        return array_map(fn (string $p) => AturanPenyediaAi::siap($ai, $p), array_combine(array_keys(PenyediaAi::DAFTAR), array_keys(PenyediaAi::DAFTAR)));
    }

    /**
     * Simpan pengaturan bila tidak berubah di tempat lain sejak formulir dibuka; riwayat `ubah_penyedia_ai` hanya bila ada
     * perubahan. Mengembalikan false bila bentrok atau gagal (isian tetap, pesan lewat notify).
     *
     * @param  array<string, mixed>  $lama
     * @param  array<string, mixed>  $baru
     * @param  \Closure(array<string, mixed>): array<string, mixed>  $ringkas
     * @param  array<string, string>  $label
     * @param  string|null  $tambahan  Catatan riwayat tambahan (mis. "Kunci API: diganti").
     */
    private function simpanPengaturan(array $lama, array $baru, \Closure $ringkas, array $label, string $awalan, ?string $tambahan = null, string $aksi = 'ubah_penyedia_ai'): bool
    {
        return $this->jalankanSimpan(function () use ($lama, $baru, $ringkas, $label, $awalan, $tambahan, $aksi) {
            KelolaPenyediaAi::pastikanVersi($lama, $this->versi);
            KelolaPenyediaAi::tulis($lama, $baru, $ringkas, $label, $awalan, $tambahan, $aksi);

            return true;
        }) !== false;
    }

    /**
     * Jalankan satu simpan Pengaturan AI: galat isian → error berawalan $awalanGalat; bentrok (PengaturanBerubah) →
     * versi dimuat ulang + notify, isian tetap; gagal simpan → `$gagalSimpan` + notify. Hasil `tersimpan: false`
     * (peringatan kunci) bukan simpan. Mengembalikan hasil $simpan atau false.
     */
    private function jalankanSimpan(\Closure $simpan, string|array|null $galatKe = null, string $versiProp = 'versi'): mixed
    {
        $versiLama = $this->{$versiProp};
        $this->gagalSimpan = null;
        try {
            $hasil = $simpan();
        } catch (ValidationException $e) {
            $this->petakanGalat($e, $galatKe);

            return false;
        } catch (PengaturanBerubah $e) {
            $this->{$versiProp} = app(PengaturanAiRepository::class)->ambil()['diperbarui_pada'];
            $this->dispatch('notify', message: $e->getMessage(), type: 'error');

            return false;
        } catch (RuntimeException $e) {
            $this->gagalSimpan = ['bagian' => $this->bagianSimpan, 'pesan' => $e->getMessage()];
            $this->dispatch('notify', message: $e->getMessage(), type: 'error');

            return false;
        }
        if (is_array($hasil) && ($hasil['tersimpan'] ?? true) === false) {
            return $hasil;
        }

        $this->dispatch('pengaturan-ai-tersimpan', bagian: $this->bagianSimpan);
        $this->versi = app(PengaturanAiRepository::class)->ambil()['diperbarui_pada'];
        // Simpan dari halaman ini sendiri tidak membuat isian parameter/batas yang terbuka dianggap bentrok.
        if ($this->versiParameter === $versiLama) {
            $this->versiParameter = $this->versi;
        }
        if ($this->versiBatas === $versiLama) {
            $this->versiBatas = $this->versi;
        }

        return $hasil;
    }

    /** Seperti jalankanSimpan untuk template prompt (versi `$versiPrompt` dari PromptAiRepository). */
    private function jalankanSimpanPrompt(\Closure $simpan, string|array $galatKe): mixed
    {
        $this->gagalSimpan = null;
        try {
            $hasil = $simpan();
        } catch (ValidationException $e) {
            $this->petakanGalat($e, $galatKe);

            return false;
        } catch (PengaturanBerubah $e) {
            $this->versiPrompt = app(PromptAiRepository::class)->ambil()['diperbarui_pada'];
            $this->dispatch('notify', message: $e->getMessage(), type: 'error');

            return false;
        } catch (RuntimeException $e) {
            $this->gagalSimpan = ['bagian' => $this->bagianSimpan, 'pesan' => $e->getMessage()];
            $this->dispatch('notify', message: $e->getMessage(), type: 'error');

            return false;
        }
        $this->dispatch('pengaturan-ai-tersimpan', bagian: $this->bagianSimpan);
        $this->versiPrompt = app(PromptAiRepository::class)->ambil()['diperbarui_pada'];

        return $hasil;
    }

    /** Galat layanan → error Livewire: awalan ("isianLokal" → isianLokal.model) atau peta kolom → nama error. */
    private function petakanGalat(ValidationException $e, string|array|null $galatKe): void
    {
        foreach ($e->errors() as $kolom => $pesan) {
            $nama = match (true) {
                is_array($galatKe) => $galatKe[$kolom] ?? $kolom,
                is_string($galatKe) => "{$galatKe}.{$kolom}",
                default => $kolom,
            };
            $this->addError($nama, $pesan[0]);
        }
    }

    /** @return array{aktif: string, cadangan: string} */
    private static function ringkasPeran(array $pengaturan): array
    {
        return KelolaPenyediaAi::ringkasPeran($pengaturan);
    }

    /**
     * Perubahan Pengaturan AI terakhir dari riwayat (aksi bagian `ai`): waktu, pengguna, dan jenis perubahan.
     *
     * @return array{waktu: string, pengguna: string, label: string}|null
     */
    public function terakhirDisimpan(): ?array
    {
        $entri = collect(app(RiwayatPengaturanRepository::class)->semua())->first(fn (array $r) => (RiwayatPengaturan::AKSI[$r['aksi']][1] ?? null) === 'ai');

        return $entri === null ? null : ['waktu' => $entri['waktu'], 'pengguna' => $entri['pengguna'], 'label' => RiwayatPengaturan::AKSI[$entri['aksi']][0]];
    }

    /**
     * Pemakaian AI hari ini per akun aktif (terbanyak dulu).
     *
     * @return list<array{id: int, nama: string, peran: string, terpakai: int, batas: int, khusus: int|null, sisa: int}>
     */
    public function pemakaianHariIni(): array
    {
        return KelolaBatasAi::data()['akun'];
    }

    /** @return list<array<string, mixed>> */
    public function prompt(): array
    {
        return PromptAi::semua();
    }

    /** @return array{permintaan: int, versi: int, status: array<string, int>, dipakai: int} */
    public function ringkasanSaran(): array
    {
        return RiwayatSaranAi::ringkasan(RiwayatSaranAi::untuk(StubData::currentUser()));
    }

    /** Simpan/buang batas khusus satu akun lewat KelolaBatasAi; true bila tersimpan. */
    private function ubahBatasKhusus(int $id, mixed $batas, array $galatKe): bool
    {
        $this->bagianSimpan = 'khusus';
        abort_unless(collect(StubPengaturan::pengguna())->firstWhere('id', $id) !== null, 404);

        // Batas khusus disunting per baris tanpa formulir bervers: memakai versi terbaru.
        $this->versi = app(PengaturanAiRepository::class)->ambil()['diperbarui_pada'];
        $hasil = $this->jalankanSimpan(fn () => KelolaBatasAi::simpanKhusus($id, $batas, StubData::currentUser(), $this->versi), $galatKe);
        if ($hasil === false) {
            return false;
        }
        $this->dispatch('notify', message: $hasil['pesan'], type: 'success');

        return true;
    }

    private function muatParameter(): void
    {
        $ai = app(PengaturanAiRepository::class)->ambil();
        $this->isianParameter = ParameterAi::dari($ai);
        $this->versiParameter = $ai['diperbarui_pada'];
        $this->isianBatas = (int) $ai['batas_per_user_per_hari'];
        $this->isianSimulasi = ['jenis' => (string) ($ai['simulasi_gagal']['jenis'] ?? ''), 'cakupan' => (string) ($ai['simulasi_gagal']['cakupan'] ?? 'aktif')];
        $this->versiBatas = $ai['diperbarui_pada'];
    }

    private function rapikanTab(): void
    {
        if (! array_key_exists($this->tab, self::TAB)) {
            $this->tab = 'penyedia';
        }
    }

    public function render()
    {
        return view('livewire.pengaturan.pengaturan-ai')->title('Pengaturan AI');
    }
}
