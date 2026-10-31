<?php

namespace App\Livewire\Pengaturan;

use App\Contracts\TemplateDokumenRepository;
use App\Support\Dokumen\AnalisisTemplate;
use App\Support\Dokumen\DaftarTemplateMaster;
use App\Support\Dokumen\PembacaTemplateDocx;
use App\Support\Dokumen\PratinjauTemplate;
use App\Support\Dokumen\ValidasiVariabelTemplate;
use App\Support\MasterKode;
use App\Support\Pengaturan\KelolaTemplateMaster;
use App\Support\Pengaturan\UnggahMasterTemplate;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use RuntimeException;

/**
 * Pengaturan › Template Master (PRD Fase 5 "Template KAK/TOR Variabel (Multi per Leading)"; `/pengaturan/template/master`,
 * rute `pengaturan.template.master.index`, HanyaAdmin): halaman utama semua master .docx ber-variabel tahun aktif —
 * ringkasan, saringan di URL (`cari`, `leading` ''|umum|kode, `jenis`, `status` aktif|nonaktif), kelompok per pemilik
 * (umum lalu per leading) dengan default per jenis, status, catatan versi, variabel, berkas, dan pemakaian
 * (DaftarTemplateMaster). Unggah template Word berpenanda lewat panel `form-unggah-master` (`bukaUnggah($pemilik?)` →
 * berkas dibaca seketika: penanda `${…}` terdaftar/tidak + saran (`analisis`, AnalisisTemplate) dan isi berpenanda
 * (`penanda`, PratinjauTemplate mode penanda) → `simpanUnggah()` lewat UnggahMasterTemplate dengan pemilik, catatan versi,
 * dan jadikan default pemilik × jenis — template pertama pemilik × jenis otomatis default, `adaDefault`). Template tersimpan
 * diubah lewat formulir di barisnya (`ubahTemplate($id)` → `isianUbah` nama, catatan versi, default → `simpanUbah()`) atau
 * langsung `jadikanTemplateDefault($id)` — keduanya lewat KelolaTemplateMaster.
 */
#[Layout('layouts.app')]
class TemplateMaster extends Component
{
    use WithFileUploads;

    #[Url(except: '')]
    public string $cari = '';

    #[Url(except: '')]
    public string $leading = '';

    #[Url(except: '')]
    public string $jenis = '';

    #[Url(except: '')]
    public string $status = '';

    /** Panel unggah template terbuka. */
    public bool $unggahTerbuka = false;

    /** @var TemporaryUploadedFile|null Berkas master .docx yang sedang diunggah. */
    public $berkas = null;

    public string $nama = '';

    /** Nama yang terakhir diisikan otomatis dari nama berkas (ikut berganti selama belum diketik ulang). */
    #[Locked]
    public string $namaDariBerkas = '';

    public string $jenisBaru = 'KAK+TOR';

    /** Pemilik template: '' = umum (semua leading), selain itu kode leading aktif. */
    public string $pemilik = '';

    public string $catatan = '';

    public bool $jadikanDefault = false;

    /** Template yang baru disimpan (disorot di daftar). */
    #[Locked]
    public ?string $baru = null;

    /** Template yang sedang diubah nama/catatan/default-nya; null = tertutup. */
    #[Locked]
    public ?string $diubah = null;

    /** Template yang menunggu konfirmasi dinonaktifkan (peringatan dampak di barisnya). */
    #[Locked]
    public ?string $akanNonaktif = null;

    /** @var array{nama: string, catatan: string, default: bool} Isian formulir ubah. */
    public array $isianUbah = ['nama' => '', 'catatan' => '', 'default' => false];

    public function mount(): void
    {
        $this->rapikan();
    }

    public function updated(string $properti): void
    {
        // Kata kunci tidak dirapikan di tempat (ketikan yang belum selesai tetap); saringan lain dikembalikan ke nilai sah.
        if ($properti !== 'cari') {
            $this->rapikan();
        }
    }

    public function hapusSaringan(): void
    {
        $this->reset('cari', 'leading', 'jenis', 'status');
    }

    /** @return array<string, mixed> DaftarTemplateMaster::data tahun aktif dengan saringan saat ini. */
    #[Computed]
    public function data(): array
    {
        return DaftarTemplateMaster::data((int) StubData::tahunAktif()['tahun'], ['cari' => $this->cari, 'leading' => $this->leading, 'jenis' => $this->jenis, 'status' => $this->status]);
    }

    /** Buka panel unggah; pemilik bawaan = leading yang sedang disaring. */
    public function bukaUnggah(?string $pemilik = null): void
    {
        if (StubData::tahunAktif()['is_locked']) {
            $this->dispatch('notify', message: 'Tahun Anggaran '.StubData::tahunAktif()['tahun'].' terkunci — templatenya hanya bisa dilihat.', type: 'error');

            return;
        }
        $this->tutupUnggah();
        $pemilik ??= $this->leading !== DaftarTemplateMaster::UMUM ? $this->leading : '';
        $this->pemilik = collect(MasterKode::leadings())->contains('kode', $pemilik) ? $pemilik : '';
        $this->unggahTerbuka = true;
    }

    public function tutupUnggah(): void
    {
        $this->berkas?->delete();
        $this->reset('unggahTerbuka', 'berkas', 'nama', 'namaDariBerkas', 'jenisBaru', 'pemilik', 'catatan', 'jadikanDefault');
        $this->resetErrorBag();
        unset($this->analisis, $this->penanda, $this->adaDefault);
    }

    /** Berkas baru langsung dibaca; nama bawaan dari nama berkas. */
    public function updatedBerkas(): void
    {
        unset($this->analisis, $this->penanda, $this->validasi, $this->peringatanPenanda);
        $this->resetErrorBag('berkas');
        $this->validateOnly('berkas');
        if (($this->nama === '' || $this->nama === $this->namaDariBerkas) && $this->berkas !== null) {
            $this->nama = $this->namaDariBerkas = mb_substr(pathinfo($this->berkas->getClientOriginalName(), PATHINFO_FILENAME), 0, 100);
        }
    }

    public function updatedPemilik(): void
    {
        unset($this->adaDefault);
    }

    public function updatedJenisBaru(): void
    {
        unset($this->adaDefault, $this->validasi, $this->peringatanPenanda);
    }

    /** Penanda ${…} di berkas dicocokkan dengan variabel terdaftar; null selama belum ada berkas sah. */
    #[Computed]
    public function analisis(): ?AnalisisTemplate
    {
        if ($this->berkas === null || $this->getErrorBag()->has('berkas')) {
            return null;
        }

        try {
            return UnggahMasterTemplate::periksa($this->berkas);
        } catch (ValidationException $e) {
            $this->addError('berkas', $e->errors()['berkas'][0]);

            return null;
        }
    }

    /**
     * Hasil ValidasiVariabelTemplate berkas yang dipilih (galat yang menahan simpan — penanda tak terdaftar/tak tertutup —
     * dan peringatan); null selama belum ada berkas sah.
     *
     * @return array{galat: array<string, string>, peringatan: list<array{kunci: string, pesan: string}>, bisa_disimpan: bool}|null
     */
    #[Computed]
    public function validasi(): ?array
    {
        if ($this->analisis === null) {
            return null;
        }

        try {
            return array_intersect_key(ValidasiVariabelTemplate::periksa($this->berkas->getRealPath(), $this->jenisBaru), array_flip(['galat', 'peringatan', 'bisa_disimpan']));
        } catch (RuntimeException) {
            return null;
        }
    }

    /** @return list<array{kunci: string, pesan: string}> Peringatan penanda (tidak menahan simpan). */
    #[Computed]
    public function peringatanPenanda(): array
    {
        return $this->validasi['peringatan'] ?? [];
    }

    /** Isi dokumen dengan penanda disorot (mode penanda). */
    #[Computed]
    public function penanda(): ?PratinjauTemplate
    {
        if ($this->analisis === null) {
            return null;
        }

        try {
            return PratinjauTemplate::susun(PembacaTemplateDocx::paragraf($this->berkas->getRealPath()), []);
        } catch (RuntimeException) {
            return null;
        }
    }

    /** Pemilik × jenis terpilih sudah punya default tahun ini (bila belum, template baru otomatis default). */
    #[Computed]
    public function adaDefault(): bool
    {
        $pemilik = $this->pemilik === '' ? null : $this->pemilik;

        return collect(app(TemplateDokumenRepository::class)->semua((int) StubData::tahunAktif()['tahun']))
            ->contains(fn (array $t) => $t['jenis'] === $this->jenisBaru && ($t['leading'] ?? null) === $pemilik && $t['is_default'] && ($t['aktif'] ?? true));
    }

    public function simpanUnggah(): void
    {
        $this->validate();

        try {
            $master = UnggahMasterTemplate::simpan((int) StubData::tahunAktif()['tahun'], $this->berkas, $this->nama, $this->jenisBaru, StubData::currentUser(), [
                'leading' => $this->pemilik, 'catatan' => $this->catatan, 'default' => $this->jadikanDefault || ! $this->adaDefault,
            ]);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $kolom => $pesan) {
                $this->addError(['jenis' => 'jenisBaru', 'leading' => 'pemilik'][$kolom] ?? $kolom, $pesan[0]);
            }

            return;
        } catch (AuthorizationException $e) {
            $this->dispatch('notify', message: $e->getMessage(), type: 'error');

            return;
        }

        $this->tutupUnggah();
        $this->baru = $master['id'];
        unset($this->data);
        $this->dispatch('notify', message: "Template “{$master['nama']}” tersimpan untuk ".mb_lcfirst(DaftarTemplateMaster::labelPemilik($master['leading'])).($master['is_default'] ? ' dan menjadi default '.$master['jenis'].'.' : '.'), type: 'success');
    }

    /** Buka formulir ubah nama, catatan versi, & default satu template. */
    public function ubahTemplate(string $id): void
    {
        $master = app(TemplateDokumenRepository::class)->temukan($id);
        if ($master === null || StubData::tahunAktif()['is_locked']) {
            return;
        }
        $this->resetErrorBag();
        $this->diubah = $id;
        $this->isianUbah = ['nama' => (string) $master['nama'], 'catatan' => (string) ($master['catatan'] ?? ''), 'default' => (bool) $master['is_default']];
    }

    public function batalUbah(): void
    {
        $this->reset('diubah', 'isianUbah');
        $this->resetErrorBag();
    }

    /** Simpan nama & catatan versi (KelolaTemplateMaster::ubah), lalu jadikan default bila dicentang. */
    public function simpanUbah(): void
    {
        if ($this->diubah === null) {
            return;
        }
        $this->resetErrorBag();

        try {
            $hasil = KelolaTemplateMaster::ubah($this->diubah, $this->isianUbah, StubData::currentUser());
            $default = $this->isianUbah['default'] ? KelolaTemplateMaster::jadikanDefault($this->diubah, StubData::currentUser()) : ['berubah' => false];
        } catch (ValidationException $e) {
            foreach ($e->errors() as $kolom => $pesan) {
                $this->addError("isianUbah.{$kolom}", $pesan[0]);
            }

            return;
        } catch (AuthorizationException|RuntimeException $e) {
            $this->batalUbah();
            unset($this->data);
            $this->dispatch('notify', message: $e->getMessage(), type: 'error');

            return;
        }

        $nama = $hasil['master']['nama'];
        $this->batalUbah();
        unset($this->data);
        $this->dispatch('notify', ...match (true) {
            $hasil['ringkasan'] === null && ! $default['berubah'] => ['message' => "Template “{$nama}” tidak berubah.", 'type' => 'info'],
            default => ['message' => "Template “{$nama}” diperbarui".($default['berubah'] ? ' dan menjadi default '.$hasil['master']['jenis'].'.' : '.'), 'type' => 'success'],
        });
    }

    /** Jadikan default pemilik × jenis langsung dari daftar (bukan `jadikanDefault`: nama itu properti formulir unggah). */
    public function jadikanTemplateDefault(string $id): void
    {
        try {
            $hasil = KelolaTemplateMaster::jadikanDefault($id, StubData::currentUser());
        } catch (ValidationException $e) {
            $this->dispatch('notify', message: collect($e->errors())->flatten()->first(), type: 'error');

            return;
        } catch (AuthorizationException|RuntimeException $e) {
            unset($this->data);
            $this->dispatch('notify', message: $e->getMessage(), type: 'error');

            return;
        }

        unset($this->data);
        if ($hasil['berubah']) {
            $this->dispatch('notify', message: "“{$hasil['master']['nama']}” kini default {$hasil['master']['jenis']} ".mb_lcfirst(DaftarTemplateMaster::labelPemilik($hasil['master']['leading'] ?? null)).($hasil['sebelumnya'] ? " menggantikan “{$hasil['sebelumnya']}”." : '.'), type: 'success');
        }
    }

    /** Tampilkan peringatan dampak sebelum menonaktifkan template (KelolaTemplateMaster::dampak). */
    public function mintaNonaktif(string $id): void
    {
        $this->akanNonaktif = app(TemplateDokumenRepository::class)->temukan($id) !== null ? $id : null;
        $this->reset('diubah');
        unset($this->dampakNonaktif);
    }

    public function batalNonaktif(): void
    {
        $this->akanNonaktif = null;
    }

    /** @return array<string, mixed>|null */
    #[Computed]
    public function dampakNonaktif(): ?array
    {
        try {
            return $this->akanNonaktif !== null ? KelolaTemplateMaster::dampak($this->akanNonaktif) : null;
        } catch (RuntimeException) {
            return null;
        }
    }

    public function nonaktifkan(): void
    {
        if ($this->akanNonaktif === null) {
            return;
        }
        $id = $this->akanNonaktif;
        $this->akanNonaktif = null;
        $this->ubahStatusTemplate($id, false);
    }

    public function aktifkan(string $id): void
    {
        $this->ubahStatusTemplate($id, true);
    }

    private function ubahStatusTemplate(string $id, bool $aktif): void
    {
        try {
            $hasil = KelolaTemplateMaster::ubahStatus($id, $aktif, StubData::currentUser());
        } catch (AuthorizationException|RuntimeException $e) {
            unset($this->data);
            $this->dispatch('notify', message: $e->getMessage(), type: 'error');

            return;
        }

        unset($this->data);
        if (! $hasil['berubah']) {
            return;
        }
        $nama = $hasil['master']['nama'];
        $this->dispatch('notify', message: $aktif
            ? "“{$nama}” aktif kembali".($hasil['master']['is_default'] ? ' dan menjadi default.' : ' — muncul lagi di pilihan template.')
            : "“{$nama}” dinonaktifkan — tidak muncul di pilihan baru".($hasil['pengganti'] ? "; default kini “{$hasil['pengganti']}”." : '.'), type: 'success');
    }

    /** @return array<string, list<mixed>> */
    protected function rules(): array
    {
        $aturan = UnggahMasterTemplate::aturan();

        return ['berkas' => $aturan['berkas'], 'nama' => $aturan['nama'], 'jenisBaru' => $aturan['jenis'], 'pemilik' => $aturan['leading'], 'catatan' => $aturan['catatan']];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        $pesan = UnggahMasterTemplate::pesan();

        return [
            'berkas.required' => $pesan['berkas.required'], 'berkas.extensions' => $pesan['berkas.extensions'], 'berkas.max' => $pesan['berkas.max'],
            'nama.required' => $pesan['nama.required'], 'nama.max' => $pesan['nama.max'], 'jenisBaru.in' => $pesan['jenis.in'],
            'pemilik.in' => $pesan['leading.in'], 'catatan.max' => $pesan['catatan.max'],
        ];
    }

    private function rapikan(): void
    {
        $rapi = DaftarTemplateMaster::saringan(['leading' => $this->leading, 'jenis' => $this->jenis, 'status' => $this->status]);
        $this->leading = $rapi['leading'];
        $this->jenis = $rapi['jenis'];
        $this->status = $rapi['status'];
    }

    public function render()
    {
        return view('livewire.pengaturan.template-master')->title('Template Master · Pengaturan');
    }
}
