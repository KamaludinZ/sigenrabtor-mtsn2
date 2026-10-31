<?php

namespace App\Livewire\Pengaturan;

use App\Contracts\KegiatanRepository;
use App\Contracts\TemplateCoverRepository;
use App\Contracts\TemplateDokumenRepository;
use App\Support\Dokumen\AnalisisTemplate;
use App\Support\Dokumen\NilaiVariabel;
use App\Support\Dokumen\PemakaianTemplate;
use App\Support\Dokumen\PembacaTemplateDocx;
use App\Support\Dokumen\PratinjauTemplate;
use App\Support\Dokumen\TeksCover;
use App\Support\Pengaturan\SimpanTemplateCover;
use App\Support\Pengaturan\TemplateMasihDipakai;
use App\Support\Pengaturan\UnggahMasterTemplate;
use App\Support\Rab\AnggaranKegiatan;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
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
 * Pengaturan › Template Dokumen (PRD Fase 5): tab Cover — unggah desain cover JPG/PNG, lihat hasilnya dengan kotak teks
 * (TeksCover) berisi contoh kegiatan, atur tata letaknya, dan kelola cover tersimpan; tab KAK/TOR — unggah master .docx,
 * periksa variabel ${…} terhadap daftar terdaftar (VariabelTemplate) sebelum disimpan; tab Daftar Isi — komponen
 * TemplateDaftarIsi; tab Nomor Halaman — komponen TemplateNomorHalaman.
 */
#[Layout('layouts.app')]
class TemplateDokumen extends Component
{
    use WithFileUploads;

    public const TAB = ['cover' => 'Cover', 'kak' => 'KAK/TOR', 'daftar_isi' => 'Daftar Isi', 'nomor_halaman' => 'Nomor Halaman'];

    /** Tab yang sudah tersedia. */
    public const TAB_TERSEDIA = ['cover', 'kak', 'daftar_isi', 'nomor_halaman'];

    /** Batas ukuran gambar cover (KB) — SimpanTemplateCover::MAKS_KB. */
    public const MAKS_KB = SimpanTemplateCover::MAKS_KB;

    /** Batas ukuran master .docx (KB) — UnggahMasterTemplate::MAKS_KB. */
    public const MAKS_KB_MASTER = UnggahMasterTemplate::MAKS_KB;

    #[Url(except: 'cover')]
    public string $tab = 'cover';

    /** @var TemporaryUploadedFile|null Gambar cover yang baru dipilih (belum disimpan). */
    public $gambar = null;

    public string $namaCover = '';

    /** Cover tersimpan yang sedang dipratinjau; null = gambar baru (bila ada) atau cover default. */
    #[Locked]
    public ?string $coverDipilih = null;

    /** Contoh isi pratinjau: id kegiatan & sumber dana. */
    public ?int $contohKegiatan = null;

    public ?string $contohSumber = null;

    public bool $tampilkanKotak = true;

    /** Cover yang tata letak kotak teksnya sedang diatur di editor; null = editor tertutup. */
    #[Locked]
    public ?string $coverDiatur = null;

    /**
     * Kotak teks yang sedang diatur (state lokal editor: diseret/diubah di klien, terkirim saat disimpan).
     *
     * @var list<array<string, mixed>>
     */
    public array $kotak = [];

    /** Cover yang menunggu konfirmasi hapus. */
    #[Locked]
    public ?string $akanDihapus = null;

    /** @var TemporaryUploadedFile|null Master KAK/TOR .docx yang baru dipilih (belum disimpan). */
    public $berkasMaster = null;

    public string $namaMaster = '';

    public string $jenisMaster = 'KAK+TOR';

    /** @var array<string, int>|null Variabel hasil baca berkas master (null = belum ada berkas sah). */
    #[Locked]
    public ?array $temuanMaster = null;

    /** Template master yang menunggu konfirmasi hapus. */
    #[Locked]
    public ?string $masterAkanDihapus = null;

    /** Panel pratinjau isi master: 'baru' (berkas yang baru diunggah) atau id master tersimpan; null = tertutup. */
    #[Locked]
    public ?string $pratinjauMaster = null;

    /** Mode pratinjau master: 'penanda' (variabel sebagai penanda) atau 'isi' (diganti nilai contoh kegiatan). */
    public string $modeMaster = 'penanda';

    public function mount(KegiatanRepository $kegiatan): void
    {
        if (! in_array($this->tab, self::TAB_TERSEDIA, true)) {
            $this->tab = 'cover';
        }

        $this->contohKegiatan = $kegiatan->semua()[0]['id'] ?? null;
        $this->contohSumber = $this->sumberContoh()[0] ?? null;
        $this->namaCover = $this->namaBaku();
        $this->namaMaster = $this->namaMasterBaku();
    }

    /** Gambar baru diperiksa begitu dipilih; pratinjau beralih ke gambar itu (editor tata letak ditutup). */
    public function updatedGambar(): void
    {
        $this->batalAtur();
        $this->coverDipilih = null;
        $this->validateOnly('gambar');
    }

    /** Ganti contoh kegiatan: sumber dana ikut sumber kegiatan itu. */
    public function updatedContohKegiatan(): void
    {
        $sumber = $this->sumberContoh();
        $this->contohSumber = in_array($this->contohSumber, $sumber, true) ? $this->contohSumber : ($sumber[0] ?? null);
    }

    public function simpanCover(): void
    {
        $this->validate(Arr::only($this->rules(), ['gambar', 'namaCover']));

        try {
            $id = SimpanTemplateCover::tambah($this->tahun(), $this->gambar, $this->namaCover, StubData::currentUser())['id'];
        } catch (ValidationException $e) {
            foreach ($e->errors() as $kolom => $pesan) {
                $this->addError($kolom === 'nama' ? 'namaCover' : $kolom, $pesan[0]);
            }

            return;
        } catch (AuthorizationException $e) {
            $this->dispatch('notify', message: $e->getMessage(), type: 'error');

            return;
        }
        $this->gambar->delete();
        $this->reset('gambar');
        $this->coverDipilih = $id;
        $this->namaCover = $this->namaBaku();
        unset($this->covers);
        $this->dispatch('notify', message: 'Cover tersimpan.', type: 'success');
    }

    public function batalUnggah(): void
    {
        $this->gambar?->delete();
        $this->reset('gambar');
        $this->resetValidation(['gambar', 'namaCover']);
    }

    public function pilihCover(string $id): void
    {
        if (collect($this->covers)->contains('id', $id)) {
            $this->batalUnggah();
            $this->coverDipilih = $id;
        }
    }

    /** Buka editor tata letak untuk cover tersimpan. */
    public function aturKotak(string $id): void
    {
        $cover = collect($this->covers)->firstWhere('id', $id);

        if ($cover === null) {
            return;
        }

        $this->batalUnggah();
        $this->akanDihapus = null;
        $this->coverDipilih = $id;
        $this->coverDiatur = $id;
        $this->kotak = TeksCover::rapikan($cover['teks_items']);
    }

    public function batalAtur(): void
    {
        $this->coverDiatur = null;
        $this->kotak = [];
        $this->resetValidation('kotak');
    }

    /** Simpan susunan dari editor; nilai dari klien selalu dirapikan (kolom dikenal, posisi & ukuran dalam batas). */
    public function simpanTataLetak(): void
    {
        $cover = collect($this->covers)->firstWhere('id', $this->coverDiatur);

        if ($cover === null) {
            return;
        }

        try {
            SimpanTemplateCover::simpanTataLetak($cover['id'], $this->kotak, StubData::currentUser());
        } catch (ValidationException $e) {
            $this->addError('kotak', $e->errors()['kotak'][0]);

            return;
        } catch (AuthorizationException $e) {
            $this->dispatch('notify', message: $e->getMessage(), type: 'error');

            return;
        }
        $this->batalAtur();
        unset($this->covers);
        $this->dispatch('notify', message: "Tata letak “{$cover['nama']}” tersimpan.", type: 'success');
    }

    public function mintaHapus(string $id): void
    {
        $this->akanDihapus = collect($this->covers)->contains('id', $id) ? $id : null;
    }

    public function batalHapus(): void
    {
        $this->akanDihapus = null;
    }

    public function hapusCover(): void
    {
        $cover = collect($this->covers)->firstWhere('id', $this->akanDihapus);

        if ($cover === null) {
            return;
        }

        try {
            SimpanTemplateCover::hapus($cover['id'], StubData::currentUser());
        } catch (AuthorizationException $e) {
            $this->dispatch('notify', message: $e->getMessage(), type: 'error');

            return;
        }
        $this->akanDihapus = null;
        if ($this->coverDiatur === $cover['id']) {
            $this->batalAtur();
        }
        $this->coverDipilih = $this->coverDipilih === $cover['id'] ? null : $this->coverDipilih;
        unset($this->covers);
        $this->namaCover = $this->gambar ? $this->namaCover : $this->namaBaku();
        $this->dispatch('notify', message: "Cover “{$cover['nama']}” dihapus.", type: 'info');
    }

    /** Master baru langsung dibaca: variabel ${…} dicocokkan dengan daftar terdaftar. */
    public function updatedBerkasMaster(): void
    {
        $this->temuanMaster = null;
        $this->validateOnly('berkasMaster');

        try {
            $this->temuanMaster = PembacaTemplateDocx::variabel($this->berkasMaster->getRealPath());
        } catch (RuntimeException $e) {
            $this->addError('berkasMaster', $e->getMessage());
        }
    }

    /** Simpan lewat UnggahMasterTemplate (aturan & pemeriksaan variabel yang sama dengan server). */
    public function simpanMaster(): void
    {
        $this->validate(Arr::only($this->rules(), ['berkasMaster', 'namaMaster', 'jenisMaster']));

        try {
            UnggahMasterTemplate::simpan($this->tahun(), $this->berkasMaster, $this->namaMaster, $this->jenisMaster, StubData::currentUser());
        } catch (ValidationException $e) {
            foreach ($e->errors() as $kolom => $pesan) {
                $this->addError(['berkas' => 'berkasMaster', 'nama' => 'namaMaster', 'jenis' => 'jenisMaster'][$kolom] ?? $kolom, $pesan[0]);
            }

            return;
        } catch (AuthorizationException $e) {
            $this->dispatch('notify', message: $e->getMessage(), type: 'error');

            return;
        }

        $this->batalUnggahMaster();
        unset($this->masters);
        $this->namaMaster = $this->namaMasterBaku();
        $this->dispatch('notify', message: 'Master KAK/TOR tersimpan.', type: 'success');
    }

    public function batalUnggahMaster(): void
    {
        $this->pratinjauMaster = $this->pratinjauMaster === 'baru' ? null : $this->pratinjauMaster;
        $this->berkasMaster?->delete();
        $this->reset('berkasMaster', 'temuanMaster');
        $this->resetValidation(['berkasMaster', 'namaMaster', 'jenisMaster']);
    }

    public function bukaPratinjauMaster(string $sumber): void
    {
        $ada = $sumber === 'baru' ? $this->temuanMaster !== null : collect($this->masters)->contains('id', $sumber);
        $this->pratinjauMaster = $ada ? $sumber : null;
    }

    public function tutupPratinjauMaster(): void
    {
        $this->pratinjauMaster = null;
    }

    /** Isi master yang dipratinjau, dengan nilai variabel dari contoh kegiatan × sumber dana. */
    #[Computed]
    public function pratinjauTemplate(): ?PratinjauTemplate
    {
        $path = match (true) {
            $this->pratinjauMaster === null => null,
            $this->pratinjauMaster === 'baru' => $this->berkasMaster instanceof TemporaryUploadedFile ? $this->berkasMaster->getRealPath() : null,
            default => ($master = collect($this->masters)->firstWhere('id', $this->pratinjauMaster)) ? Storage::disk('local')->path($master['file_path']) : null,
        };

        if ($path === null) {
            return null;
        }

        $kegiatan = $this->contohKegiatan ? app(KegiatanRepository::class)->temukan($this->contohKegiatan) : null;

        try {
            return PratinjauTemplate::susun(PembacaTemplateDocx::paragraf($path), NilaiVariabel::untuk($kegiatan ?? [], $this->contohSumber));
        } catch (RuntimeException) {
            return null;
        }
    }

    public function mintaHapusMaster(string $id): void
    {
        $this->masterAkanDihapus = collect($this->masters)->contains('id', $id) ? $id : null;
    }

    public function batalHapusMaster(): void
    {
        $this->masterAkanDihapus = null;
    }

    public function hapusMaster(): void
    {
        $template = collect($this->masters)->firstWhere('id', $this->masterAkanDihapus);

        if ($template === null) {
            return;
        }

        try {
            UnggahMasterTemplate::hapus($template['id'], StubData::currentUser());
        } catch (AuthorizationException|TemplateMasihDipakai $e) {
            $this->masterAkanDihapus = null;
            $this->dispatch('notify', message: $e->getMessage(), type: 'error');

            return;
        }
        $this->masterAkanDihapus = null;
        $this->pratinjauMaster = $this->pratinjauMaster === $template['id'] ? null : $this->pratinjauMaster;
        unset($this->masters);
        $this->namaMaster = $this->berkasMaster ? $this->namaMaster : $this->namaMasterBaku();
        $this->dispatch('notify', message: "Master “{$template['nama']}” dihapus.", type: 'info');
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function masters(): array
    {
        return app(TemplateDokumenRepository::class)->semua($this->tahun());
    }

    /**
     * Pemakaian tiap master (PemakaianTemplate::jumlah) — yang dipilih kegiatan/dipakai dokumen tidak bisa dihapus.
     *
     * @return array<string, array{kegiatan: int, dokumen: int}>
     */
    #[Computed]
    public function pemakaianMaster(): array
    {
        return PemakaianTemplate::jumlah(array_map(fn (array $m) => (string) $m['id'], $this->masters));
    }

    #[Computed]
    public function analisisMaster(): ?AnalisisTemplate
    {
        return $this->temuanMaster === null ? null : AnalisisTemplate::dari($this->temuanMaster);
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function covers(): array
    {
        return app(TemplateCoverRepository::class)->semua($this->tahun());
    }

    /**
     * Sumber pratinjau: gambar baru (belum disimpan), cover yang dipilih, cover default, atau tata letak baku.
     *
     * @return array{jenis: 'baru'|'tersimpan'|'baku', judul: string, url: string|null, teks: list<array<string, mixed>>, cover: array<string, mixed>|null}
     */
    #[Computed]
    public function pratinjau(): array
    {
        if ($this->gambar instanceof TemporaryUploadedFile && ! $this->getErrorBag()->has('gambar') && $this->gambarBisaDitampilkan()) {
            return ['jenis' => 'baru', 'judul' => 'Gambar baru (belum disimpan)', 'url' => $this->gambar->temporaryUrl(), 'teks' => TeksCover::bawaan(), 'cover' => null];
        }

        $cover = collect($this->covers)->firstWhere('id', $this->coverDipilih) ?? ($this->covers[0] ?? null);

        if ($cover !== null) {
            return ['jenis' => 'tersimpan', 'judul' => $cover['nama'], 'url' => route('pengaturan.template.cover.gambar', $cover['id']), 'teks' => TeksCover::rapikan($cover['teks_items']), 'cover' => $cover];
        }

        return ['jenis' => 'baku', 'judul' => 'Tata letak baku (belum ada desain)', 'url' => null, 'teks' => TeksCover::bawaan(), 'cover' => null];
    }

    /**
     * Ukuran & kesesuaian A4 potret gambar baru (null bila belum ada gambar sah).
     *
     * @return array{nama: string, lebar: int, tinggi: int, kb: int, a4: bool, potret: bool}|null
     */
    #[Computed]
    public function infoGambar(): ?array
    {
        if (! $this->gambar instanceof TemporaryUploadedFile || ! $this->gambarBisaDitampilkan()) {
            return null;
        }

        [$lebar, $tinggi] = getimagesize($this->gambar->getRealPath()) ?: [0, 0];
        $rasio = $lebar > 0 ? $tinggi / $lebar : 0;

        return [
            'nama' => $this->gambar->getClientOriginalName(),
            'lebar' => (int) $lebar,
            'tinggi' => (int) $tinggi,
            'kb' => (int) ceil($this->gambar->getSize() / 1024),
            'a4' => abs($rasio - M_SQRT2) <= 0.05,
            'potret' => $tinggi >= $lebar,
        ];
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function nilaiContoh(): array
    {
        $kegiatan = $this->contohKegiatan ? app(KegiatanRepository::class)->temukan($this->contohKegiatan) : null;

        return TeksCover::nilai($kegiatan ?? [], $this->contohSumber);
    }

    /**
     * @return list<array{id: int, nama: string}>
     */
    #[Computed]
    public function daftarContoh(): array
    {
        return array_map(fn (array $k) => ['id' => $k['id'], 'nama' => $k['nama_kegiatan'] ?: 'Kegiatan tanpa nama'], app(KegiatanRepository::class)->semua());
    }

    /**
     * @return list<string>
     */
    public function sumberContoh(): array
    {
        $kegiatan = $this->contohKegiatan ? app(KegiatanRepository::class)->temukan($this->contohKegiatan) : null;

        return $kegiatan ? array_keys(AnggaranKegiatan::perSumber($kegiatan)) : [];
    }

    public function tahun(): int
    {
        return StubData::tahunAktif()['tahun'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'gambar' => SimpanTemplateCover::aturan()['gambar'],
            'namaCover' => SimpanTemplateCover::aturan()['nama'],
            'berkasMaster' => UnggahMasterTemplate::aturan()['berkas'],
            'namaMaster' => UnggahMasterTemplate::aturan()['nama'],
            'jenisMaster' => UnggahMasterTemplate::aturan()['jenis'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            ...collect(SimpanTemplateCover::pesan())->mapWithKeys(fn (string $pesan, string $kunci) => [preg_replace('/^nama\./', 'namaCover.', $kunci) => $pesan])->all(),
            ...collect(UnggahMasterTemplate::pesan())->mapWithKeys(fn (string $pesan, string $kunci) => [preg_replace('/^(berkas|nama|jenis)\./', '$1Master.', $kunci) => $pesan])->all(),
        ];
    }

    public function render()
    {
        return view('livewire.pengaturan.template-dokumen')->title('Template Dokumen · Pengaturan');
    }

    private function namaBaku(): string
    {
        return $this->covers === [] ? 'Cover default '.$this->tahun() : 'Cover '.(count($this->covers) + 1).' '.$this->tahun();
    }

    private function namaMasterBaku(): string
    {
        return 'Master KAK/TOR '.$this->tahun().($this->masters === [] ? '' : ' ('.(count($this->masters) + 1).')');
    }

    private function gambarBisaDitampilkan(): bool
    {
        return in_array(strtolower($this->gambar->getClientOriginalExtension()), ['jpg', 'jpeg', 'png'], true)
            && str_starts_with((string) $this->gambar->getMimeType(), 'image/');
    }
}
