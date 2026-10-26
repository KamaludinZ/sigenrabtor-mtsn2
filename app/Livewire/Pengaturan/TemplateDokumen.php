<?php

namespace App\Livewire\Pengaturan;

use App\Contracts\KegiatanRepository;
use App\Contracts\TemplateCoverRepository;
use App\Support\Dokumen\TeksCover;
use App\Support\Rab\AnggaranKegiatan;
use App\Support\Stub\StubData;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Pengaturan › Template Dokumen (PRD Fase 5): tab Cover — unggah desain cover JPG/PNG, lihat hasilnya dengan kotak teks
 * bawaan (TeksCover) berisi contoh kegiatan sebelum disimpan, dan kelola cover tersimpan tahun aktif. Tab KAK/TOR,
 * Daftar Isi, dan Nomor Halaman menyusul.
 */
#[Layout('layouts.app')]
class TemplateDokumen extends Component
{
    use WithFileUploads;

    public const TAB = ['cover' => 'Cover', 'kak' => 'KAK/TOR', 'daftar_isi' => 'Daftar Isi', 'nomor_halaman' => 'Nomor Halaman'];

    /** Tab yang sudah tersedia. */
    public const TAB_TERSEDIA = ['cover'];

    /** Batas ukuran gambar cover (KB). */
    public const MAKS_KB = 5120;

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

    public function mount(KegiatanRepository $kegiatan): void
    {
        if (! in_array($this->tab, self::TAB_TERSEDIA, true)) {
            $this->tab = 'cover';
        }

        $this->contohKegiatan = $kegiatan->semua()[0]['id'] ?? null;
        $this->contohSumber = $this->sumberContoh()[0] ?? null;
        $this->namaCover = $this->namaBaku();
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

    public function simpanCover(TemplateCoverRepository $repo): void
    {
        $this->validate();

        $id = $repo->tambah($this->tahun(), trim($this->namaCover), $this->gambar, TeksCover::bawaan());
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
    public function simpanTataLetak(TemplateCoverRepository $repo): void
    {
        $cover = collect($this->covers)->firstWhere('id', $this->coverDiatur);

        if ($cover === null) {
            return;
        }

        $kotak = TeksCover::rapikan($this->kotak);

        if ($kotak === []) {
            $this->addError('kotak', 'Tampilkan minimal satu kotak teks di cover.');

            return;
        }

        $repo->ubahTeks($cover['id'], $kotak);
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

    public function hapusCover(TemplateCoverRepository $repo): void
    {
        $cover = collect($this->covers)->firstWhere('id', $this->akanDihapus);

        if ($cover === null) {
            return;
        }

        $repo->hapus($cover['id']);
        $this->akanDihapus = null;
        if ($this->coverDiatur === $cover['id']) {
            $this->batalAtur();
        }
        $this->coverDipilih = $this->coverDipilih === $cover['id'] ? null : $this->coverDipilih;
        unset($this->covers);
        $this->namaCover = $this->gambar ? $this->namaCover : $this->namaBaku();
        $this->dispatch('notify', message: "Cover “{$cover['nama']}” dihapus.", type: 'info');
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
            'gambar' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:'.self::MAKS_KB, 'dimensions:min_width=600,min_height=600'],
            'namaCover' => ['required', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'gambar.required' => 'Pilih gambar desain cover.',
            'gambar.image' => 'Berkas harus berupa gambar JPG atau PNG.',
            'gambar.mimes' => 'Berkas harus berupa gambar JPG atau PNG.',
            'gambar.max' => 'Ukuran gambar maksimal '.(self::MAKS_KB / 1024).' MB.',
            'gambar.dimensions' => 'Gambar terlalu kecil — minimal 600 piksel di setiap sisi agar tajam saat dicetak.',
            'namaCover.required' => 'Beri nama cover, mis. “Cover default 2026”.',
            'namaCover.max' => 'Nama cover maksimal 100 karakter.',
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

    private function gambarBisaDitampilkan(): bool
    {
        return in_array(strtolower($this->gambar->getClientOriginalExtension()), ['jpg', 'jpeg', 'png'], true)
            && str_starts_with((string) $this->gambar->getMimeType(), 'image/');
    }
}
