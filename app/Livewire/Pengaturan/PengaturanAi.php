<?php

namespace App\Livewire\Pengaturan;

use App\Contracts\DaftarModelAi;
use App\Contracts\KegiatanRepository;
use App\Contracts\PengaturanAiRepository;
use App\Contracts\PromptAiRepository;
use App\Support\Kegiatan\BagianAi;
use App\Support\Kegiatan\BatasAi;
use App\Support\Kegiatan\RiwayatSaranAi;
use App\Support\Pengaturan\AturanPenyediaAi;
use App\Support\Pengaturan\ParameterAi;
use App\Support\Pengaturan\PenyediaAi;
use App\Support\Pengaturan\PromptAi;
use App\Support\Pengaturan\RiwayatPengaturan;
use App\Support\Stub\StubData;
use App\Support\Stub\StubPengaturan;
use Illuminate\Support\Facades\Crypt;
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
        $this->resetErrorBag();
        ['data' => $data, 'galat' => $galat] = AturanPenyediaAi::lokal($this->isianLokal);
        if ($galat !== []) {
            foreach ($galat as $kolom => $pesan) {
                $this->addError("isianLokal.{$kolom}", $pesan);
            }

            return;
        }

        $lama = app(PengaturanAiRepository::class)->ambil();
        $baru = AturanPenyediaAi::terapkanPeran($lama, 'ollama', $data['peran']);
        $baru['penyedia']['ollama'] = ['base_url' => $data['base_url'], 'model' => $data['model'], 'batas_waktu' => $data['batas_waktu']] + ($lama['penyedia']['ollama'] ?? []);

        $ringkas = fn (array $p) => [
            'base_url' => $p['penyedia']['ollama']['base_url'] ?? '', 'model' => $p['penyedia']['ollama']['model'] ?? '', 'batas_waktu' => $p['penyedia']['ollama']['batas_waktu'] ?? null,
        ] + self::ringkasPeran($p);
        if (! $this->simpanPengaturan($lama, $baru, $ringkas, ['base_url' => 'Alamat server', 'model' => 'Model', 'batas_waktu' => 'Batas waktu (detik)'], 'Server sekolah (Ollama) — ')) {
            return;
        }

        $this->lokalTerbuka = false;
        $naik = $data['peran'] === 'cadangan' && $baru['provider_aktif'] === 'ollama';
        $this->dispatch('notify', message: $naik ? 'Konfigurasi server sekolah tersimpan — belum ada penyedia aktif, jadi server sekolah dipakai sebagai penyedia aktif.' : 'Konfigurasi server sekolah tersimpan.', type: 'success');
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

    /** Simpan gaya bahasa baku semua bagian (kosong = bawaan). */
    public function simpanGaya(): void
    {
        $this->resetErrorBag();
        $gaya = trim(preg_replace('/\s+/u', ' ', $this->isianGaya));
        if ($gaya === '' || mb_strlen($gaya) > PromptAi::MAKS_GAYA) {
            $this->addError('isianGaya', 'Gaya bahasa 1–'.PromptAi::MAKS_GAYA.' karakter.');

            return;
        }

        $lama = PromptAi::gayaBahasa();
        if (! $this->simpanPrompt(fn (array $d) => ['gaya_bahasa' => $gaya === PromptAi::GAYA_BAHASA ? null : $gaya] + $d, $lama !== $gaya ? 'Gaya bahasa baku diubah' : null)) {
            return;
        }
        $this->gayaTerbuka = false;
        $this->dispatch('notify', message: 'Gaya bahasa baku tersimpan.', type: 'success');
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
        $bagian = $this->promptTerbuka;
        abort_unless($bagian !== null, 404);
        $this->resetErrorBag();

        $instruksi = trim(preg_replace('/[ \t]+/u', ' ', $this->isianPrompt['instruksi'] ?? ''));
        $gaya = ($this->isianPrompt['gaya_khusus'] ?? false) ? trim(preg_replace('/\s+/u', ' ', $this->isianPrompt['gaya_bahasa'] ?? '')) : null;
        $galat = PromptAi::galat($instruksi, $gaya);
        if ($gaya === '') {
            $galat['gaya_bahasa'] = 'Tulis gaya bahasa khusus, atau matikan pilihan gaya khusus.';
        }
        if ($galat !== []) {
            foreach ($galat as $kolom => $pesan) {
                $this->addError("isianPrompt.{$kolom}", $pesan);
            }

            return;
        }

        $lama = PromptAi::untuk($bagian);
        $aktif = (bool) ($this->isianPrompt['aktif'] ?? true);
        $baru = ['instruksi' => $instruksi, 'gaya_bahasa' => $gaya, 'aktif' => $aktif];
        $ringkas = fn (array $t) => ['instruksi' => $t['instruksi'] === PromptAi::BAWAAN[$bagian] ? 'bawaan' : 'diubah', 'gaya' => $t['gaya_bahasa'] ?? 'gaya baku', 'aktif' => $t['aktif'] ? 'aktif' : 'nonaktif'];
        $perubahan = RiwayatPengaturan::perubahan($ringkas($lama), $ringkas($baru), ['instruksi' => 'Instruksi', 'gaya' => 'Gaya bahasa', 'aktif' => 'Status']);
        if ($perubahan === null && $lama['instruksi'] !== $instruksi) {
            $perubahan = 'Instruksi diubah';
        }

        $bawaan = $instruksi === PromptAi::BAWAAN[$bagian] && $gaya === null && $aktif;
        $simpan = function (array $d) use ($bagian, $baru, $bawaan) {
            if ($bawaan) {
                unset($d['bagian'][$bagian]);
            } else {
                $d['bagian'][$bagian] = $baru;
            }

            return $d;
        };
        if (! $this->simpanPrompt($simpan, $perubahan !== null ? BagianAi::BAGIAN[$bagian]['judul'].' — '.$perubahan : null)) {
            return;
        }
        $this->promptTerbuka = null;
        $this->dispatch('notify', message: 'Prompt '.mb_strtolower(BagianAi::BAGIAN[$bagian]['judul']).' tersimpan'.($aktif ? '.' : ' — tombol “Buat dengan AI” bagian ini dinonaktifkan.'), type: 'success');
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
        $this->resetErrorBag();
        if ($galat = BatasAi::galat($this->isianBatas)) {
            $this->addError('isianBatas', $galat);

            return;
        }

        $batas = (int) $this->isianBatas;
        $lama = app(PengaturanAiRepository::class)->ambil();
        $this->versi = $this->versiBatas;
        $ringkas = fn (array $p) => ['batas' => $p['batas_per_user_per_hari'].' per akun per hari'];
        if (! $this->simpanPengaturan($lama, ['batas_per_user_per_hari' => $batas] + $lama, $ringkas, ['batas' => 'Batas umum'], '', null, 'ubah_batas_ai')) {
            $this->versiBatas = $this->versi;

            return;
        }
        $this->isianBatas = $batas;
        $this->dispatch('notify', message: "Batas umum tersimpan: {$batas} permintaan AI per akun per hari.", type: 'success');
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
        if ($galat = BatasAi::galat($this->isianKhusus)) {
            $this->addError('isianKhusus', $galat);

            return;
        }

        $batas = (int) $this->isianKhusus;
        $this->ubahBatasKhusus($id, $batas === BatasAi::batas() ? null : $batas);
        $this->khususTerbuka = null;
    }

    /** Kembalikan akun ke batas umum. */
    public function hapusKhusus(int $id): void
    {
        $this->ubahBatasKhusus($id, null);
        $this->khususTerbuka = null;
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
        abort_unless(in_array($model, array_column($this->daftarModel[$penyedia]['model'] ?? [], 'nama'), true), 404);

        $lama = app(PengaturanAiRepository::class)->ambil();
        $baru = $lama;
        $baru['penyedia'][$penyedia] = ['model' => $model] + ($lama['penyedia'][$penyedia] ?? []);
        $this->versi = $lama['diperbarui_pada'];
        $ringkas = fn (array $p) => ['model' => ($p['penyedia'][$penyedia]['model'] ?? '') ?: 'belum diisi'];
        if ($this->simpanPengaturan($lama, $baru, $ringkas, ['model' => 'Model'], PenyediaAi::nama($penyedia).' — ', null, 'ubah_model_ai')) {
            $this->dispatch('notify', message: "Model {$model} dipakai untuk ".PenyediaAi::nama($penyedia).'.', type: 'success');
        }
    }

    /** Simpan parameter jawaban (ParameterAi). */
    public function simpanParameter(): void
    {
        $this->resetErrorBag();
        ['data' => $data, 'galat' => $galat] = ParameterAi::rapikan($this->isianParameter);
        if ($galat !== []) {
            foreach ($galat as $kolom => $pesan) {
                $this->addError("isianParameter.{$kolom}", $pesan);
            }

            return;
        }

        $lama = app(PengaturanAiRepository::class)->ambil();
        $baru = ['parameter' => $data] + $lama;
        $this->versi = $this->versiParameter;
        $ringkas = fn (array $p) => [
            'suhu' => number_format(ParameterAi::dari($p)['suhu'], 1, ',', ''), 'maks_token' => ParameterAi::MAKS_TOKEN[ParameterAi::dari($p)['maks_token']] ?? ParameterAi::dari($p)['maks_token'],
            'jumlah_versi' => ParameterAi::dari($p)['jumlah_versi'].' versi',
        ];
        if (! $this->simpanPengaturan($lama, $baru, $ringkas, ['suhu' => 'Kreativitas', 'maks_token' => 'Panjang jawaban', 'jumlah_versi' => 'Jumlah versi'], 'Parameter jawaban — ', null, 'ubah_model_ai')) {
            $this->versiParameter = $this->versi;

            return;
        }

        $this->isianParameter = $data;
        $this->versiParameter = $this->versi;
        $this->dispatch('notify', message: 'Parameter jawaban AI tersimpan.', type: 'success');
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
        $penyedia = $this->cloudTerbuka;
        abort_unless($penyedia !== null, 404);
        $this->resetErrorBag();

        $lama = app(PengaturanAiRepository::class)->ambil();
        $tersimpan = $lama['penyedia'][$penyedia] ?? [];
        ['data' => $data, 'galat' => $galat, 'peringatan' => $peringatan] = AturanPenyediaAi::cloud($penyedia, $this->isianCloud, $tersimpan);
        if ($galat !== []) {
            foreach ($galat as $kolom => $pesan) {
                $this->addError("isianCloud.{$kolom}", $pesan);
            }

            return;
        }
        if ($peringatan !== [] && $this->peringatanCloud === []) {
            $this->peringatanCloud = $peringatan;

            return;
        }

        $konfigurasi = ['model' => $data['model'], 'base_url' => $data['base_url']] + $tersimpan;
        $catatanKunci = null;
        if ($data['kunci'] !== null) {
            $konfigurasi = ['ada_kunci' => true, 'kunci_akhir' => mb_substr($data['kunci'], -4), 'kunci_terenkripsi' => Crypt::encryptString($data['kunci'])] + $konfigurasi;
            $catatanKunci = 'Kunci API: '.(($tersimpan['ada_kunci'] ?? false) ? 'diganti' : 'diisi');
        } elseif ($data['hapus_kunci'] && ($tersimpan['ada_kunci'] ?? false)) {
            $konfigurasi = ['ada_kunci' => false, 'kunci_akhir' => null, 'kunci_terenkripsi' => null] + $konfigurasi;
            $catatanKunci = 'Kunci API: dihapus';
        }

        $baru = AturanPenyediaAi::terapkanPeran($lama, $penyedia, $data['peran']);
        $baru['penyedia'][$penyedia] = $konfigurasi;
        $ringkas = fn (array $p) => ['model' => $p['penyedia'][$penyedia]['model'] ?? '', 'base_url' => ($p['penyedia'][$penyedia]['base_url'] ?? '') ?: 'alamat resmi'] + self::ringkasPeran($p);
        if (! $this->simpanPengaturan($lama, $baru, $ringkas, ['model' => 'Model', 'base_url' => 'Alamat API'], PenyediaAi::nama($penyedia).' — ', $catatanKunci)) {
            return;
        }

        $this->batalCloud();
        $naik = $data['peran'] === 'cadangan' && $baru['provider_aktif'] === $penyedia;
        $this->dispatch('notify', message: 'Konfigurasi '.PenyediaAi::nama($penyedia).' tersimpan'.($naik ? ' — belum ada penyedia aktif, jadi dipakai sebagai penyedia aktif.' : '.'), type: 'success');
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
        $this->resetErrorBag();
        $lama = app(PengaturanAiRepository::class)->ambil();
        ['pengaturan' => $baru, 'galat' => $galat] = AturanPenyediaAi::pilih($lama, $this->pilihan['aktif'] ?? null, $this->pilihan['cadangan'] ?? null);
        if ($galat !== []) {
            foreach ($galat as $peran => $pesan) {
                $this->addError("pilihan.{$peran}", $pesan);
            }

            return;
        }

        if (! $this->simpanPengaturan($lama, $baru, fn (array $p) => self::ringkasPeran($p), [], '')) {
            return;
        }

        $this->pilihTerbuka = false;
        $this->dispatch('notify', message: $baru['provider_aktif'] === null
            ? 'Tanpa penyedia AI — tombol “Buat dengan AI” memakai saran contoh (tiruan).'
            : 'Penyedia AI tersimpan: '.PenyediaAi::nama($baru['provider_aktif']).($baru['provider_cadangan'] ? ', cadangan '.PenyediaAi::nama($baru['provider_cadangan']) : ', tanpa cadangan').'.', type: 'success');
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
        if ($lama['diperbarui_pada'] !== $this->versi) {
            $this->versi = $lama['diperbarui_pada'];
            $this->dispatch('notify', message: 'Pengaturan AI baru diubah di tempat lain — tampilan dimuat ulang, isian Anda tetap. Periksa lalu simpan lagi.', type: 'error');

            return false;
        }

        unset($baru['diperbarui_pada']);
        $perubahan = RiwayatPengaturan::perubahan($ringkas($lama), $ringkas($baru), $label + ['aktif' => 'Penyedia aktif', 'cadangan' => 'Penyedia cadangan']);
        // Kunci API tidak pernah ikut dicatat; hanya keterangan diisi/diganti/dihapus.
        $perubahan = implode('; ', array_filter([$perubahan, $tambahan])) ?: null;
        try {
            app(PengaturanAiRepository::class)->simpan($baru);
        } catch (RuntimeException $e) {
            $this->dispatch('notify', message: $e->getMessage(), type: 'error');

            return false;
        }

        if ($perubahan !== null) {
            RiwayatPengaturan::catat($aksi, $awalan.$perubahan);
        }
        $this->versi = app(PengaturanAiRepository::class)->ambil()['diperbarui_pada'];
        // Simpan dari halaman ini sendiri tidak membuat isian parameter yang terbuka dianggap bentrok.
        if ($this->versiParameter === $lama['diperbarui_pada']) {
            $this->versiParameter = $this->versi;
        }
        if ($this->versiBatas === $lama['diperbarui_pada']) {
            $this->versiBatas = $this->versi;
        }

        return true;
    }

    /** @return array{aktif: string, cadangan: string} */
    private static function ringkasPeran(array $pengaturan): array
    {
        return ['aktif' => PenyediaAi::nama($pengaturan['provider_aktif']) ?? 'belum dipilih', 'cadangan' => PenyediaAi::nama($pengaturan['provider_cadangan']) ?? 'belum dipilih'];
    }

    /**
     * Pemakaian AI hari ini per akun aktif (terbanyak dulu).
     *
     * @return list<array{id: int, nama: string, peran: string, terpakai: int, batas: int, khusus: int|null, sisa: int}>
     */
    public function pemakaianHariIni(): array
    {
        $baris = array_map(fn (array $p) => [
            'id' => (int) $p['id'], 'nama' => $p['name'], 'peran' => $p['role'],
            'terpakai' => BatasAi::terpakai($p), 'batas' => BatasAi::batas($p), 'khusus' => BatasAi::khusus($p), 'sisa' => BatasAi::sisa($p),
        ], array_values(array_filter(StubPengaturan::pengguna(), fn (array $p) => $p['is_active'])));
        usort($baris, fn (array $a, array $b) => [$b['terpakai'], $a['nama']] <=> [$a['terpakai'], $b['nama']]);

        return $baris;
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

    /**
     * Simpan template prompt bila tidak berubah di tempat lain sejak penyunting dibuka; riwayat `ubah_prompt_ai` bila ada
     * $keterangan.
     *
     * @param  \Closure(array<string, mixed>): array<string, mixed>  $ubah
     */
    private function simpanPrompt(\Closure $ubah, ?string $keterangan): bool
    {
        $repo = app(PromptAiRepository::class);
        $lama = $repo->ambil();
        if ($lama['diperbarui_pada'] !== $this->versiPrompt) {
            $this->versiPrompt = $lama['diperbarui_pada'];
            $this->dispatch('notify', message: 'Prompt AI baru diubah di tempat lain — tampilan dimuat ulang, isian Anda tetap. Periksa lalu simpan lagi.', type: 'error');

            return false;
        }

        try {
            $repo->simpan(array_diff_key($ubah($lama), ['diperbarui_pada' => 1]));
        } catch (RuntimeException $e) {
            $this->dispatch('notify', message: $e->getMessage(), type: 'error');

            return false;
        }
        if ($keterangan !== null) {
            RiwayatPengaturan::catat('ubah_prompt_ai', $keterangan);
        }
        $this->versiPrompt = $repo->ambil()['diperbarui_pada'];

        return true;
    }

    /** Simpan/buang batas khusus satu akun beserta riwayatnya. */
    private function ubahBatasKhusus(int $id, ?int $batas): void
    {
        $akun = collect(StubPengaturan::pengguna())->firstWhere('id', $id);
        abort_unless($akun !== null, 404);

        $lama = app(PengaturanAiRepository::class)->ambil();
        $baru = $lama;
        $baru['batas_khusus'] = array_filter(array_replace((array) ($lama['batas_khusus'] ?? []), [(string) $id => $batas]), fn ($n) => $n !== null);
        $this->versi = $lama['diperbarui_pada'];
        $ringkas = fn (array $p) => ['khusus' => isset($p['batas_khusus'][(string) $id]) ? $p['batas_khusus'][(string) $id].' per hari' : 'batas umum'];
        if ($this->simpanPengaturan($lama, $baru, $ringkas, ['khusus' => 'Batas'], $akun['name'].' — ', null, 'ubah_batas_ai')) {
            $this->dispatch('notify', message: $batas === null ? "{$akun['name']} kembali memakai batas umum." : "Batas khusus {$akun['name']}: {$batas} permintaan per hari.", type: 'success');
        }
    }

    private function muatParameter(): void
    {
        $ai = app(PengaturanAiRepository::class)->ambil();
        $this->isianParameter = ParameterAi::dari($ai);
        $this->versiParameter = $ai['diperbarui_pada'];
        $this->isianBatas = (int) $ai['batas_per_user_per_hari'];
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
