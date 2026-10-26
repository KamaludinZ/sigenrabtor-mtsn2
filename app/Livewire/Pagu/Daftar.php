<?php

namespace App\Livewire\Pagu;

use App\Contracts\DataPaguRepository;
use App\Enums\SumberDana;
use App\Livewire\Forms\KegiatanForm;
use App\Support\Format;
use App\Support\MasterKode;
use App\Support\Pagu\DataPagu;
use App\Support\Stub\StubData;
use Closure;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Data Pagu (/data-pagu): daftar nama kegiatan beserta pagu DIPA/Komite tahun aktif yang dipilih saat membuat kegiatan
 * (nama & pagu terisi otomatis di /kegiatan/buat). Admin menambah/mengubah/menghapus dan mengimpor dari Excel (nama yang
 * sama dikenali); akun leading melihat pagu leading-nya dan membuat kegiatan dari sini. Status "sudah jadi kegiatan" dari
 * DataPagu::pemakaian().
 */
#[Layout('layouts.app')]
class Daftar extends Component
{
    #[Url(except: '')]
    public string $cari = '';

    #[Url(except: '')]
    public string $leading = '';

    /** '' semua, 'belum' belum jadi kegiatan, 'dipakai' sudah jadi kegiatan. */
    #[Url(except: '')]
    public string $pemakaian = '';

    #[Url(as: 'impor', except: false)]
    public bool $imporTerbuka = false;

    /** Data yang sedang disunting: id, 'baru' untuk tambah, null = formulir tertutup. */
    public ?string $ubahId = null;

    /**
     * @var array{nama_kegiatan: string, leading: string, pagu_dipa: string, pagu_komite: string}
     */
    public array $isian = ['nama_kegiatan' => '', 'leading' => '', 'pagu_dipa' => '', 'pagu_komite' => ''];

    public ?string $akanDihapus = null;

    public function mount(): void
    {
        $this->imporTerbuka = $this->imporTerbuka && $this->bolehKelola();
        if (! in_array($this->pemakaian, ['', 'belum', 'dipakai'], true)) {
            $this->pemakaian = '';
        }
    }

    public function bolehKelola(): bool
    {
        return StubData::currentUser()['role'] === 'admin';
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function semua(): array
    {
        return DataPagu::untuk(StubData::currentUser());
    }

    /**
     * @return array<string, array{id: int, nama: string}|null>
     */
    #[Computed]
    public function dipakai(): array
    {
        return DataPagu::pemakaian($this->semua);
    }

    /**
     * Data setelah saringan cari / leading / pemakaian.
     *
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function terfilter(): array
    {
        $cari = DataPagu::kunci($this->cari);

        return array_values(array_filter($this->semua, fn (array $p) => ($cari === '' || str_contains(DataPagu::kunci($p['nama_kegiatan']), $cari))
            && ($this->leading === '' || ($this->leading === 'tanpa' ? $p['leading'] === null : $p['leading'] === $this->leading))
            && match ($this->pemakaian) {
                'belum' => $this->dipakai[$p['id']] === null,
                'dipakai' => $this->dipakai[$p['id']] !== null,
                default => true,
            }));
    }

    /**
     * Jumlah & total pagu per sumber dana dari data tersaring.
     *
     * @return array{jumlah: int, belum: int, pagu: array<string, int>}
     */
    public function ringkasan(): array
    {
        $pagu = array_fill_keys(SumberDana::urutan(), 0);
        foreach ($this->terfilter as $p) {
            foreach ($p['pagu'] as $s => $v) {
                $pagu[$s] += (int) $v;
            }
        }

        return ['jumlah' => count($this->terfilter), 'belum' => count(array_filter($this->terfilter, fn ($p) => $this->dipakai[$p['id']] === null)), 'pagu' => $pagu];
    }

    public function tambah(): void
    {
        abort_unless($this->bolehKelola(), 403);
        $this->isian = ['nama_kegiatan' => '', 'leading' => $this->leading !== 'tanpa' ? $this->leading : '', 'pagu_dipa' => '', 'pagu_komite' => ''];
        $this->resetValidation();
        $this->ubahId = 'baru';
    }

    public function ubah(string $id): void
    {
        abort_unless($this->bolehKelola(), 403);
        $data = collect($this->semua)->firstWhere('id', $id);
        if ($data === null) {
            return;
        }

        $this->isian = [
            'nama_kegiatan' => $data['nama_kegiatan'],
            'leading' => (string) $data['leading'],
            'pagu_dipa' => $data['pagu']['DIPA'] !== null ? Format::angka($data['pagu']['DIPA']) : '',
            'pagu_komite' => $data['pagu']['Komite'] !== null ? Format::angka($data['pagu']['Komite']) : '',
        ];
        $this->resetValidation();
        $this->ubahId = $id;
    }

    public function batal(): void
    {
        $this->reset('ubahId', 'isian');
        $this->resetValidation();
    }

    /** Rapikan nama & pagu, tolak nama yang sudah ada (tanpa beda huruf besar/spasi), lalu simpan untuk tahun aktif. */
    public function simpan(DataPaguRepository $repo): void
    {
        abort_unless($this->bolehKelola() && $this->ubahId !== null, 403);
        $this->isian['nama_kegiatan'] = trim(preg_replace('/\s+/u', ' ', KegiatanForm::tanpaAwalanKegiatan((string) $this->isian['nama_kegiatan'])));
        foreach (['pagu_dipa', 'pagu_komite'] as $kolom) {
            $nominal = Format::parseRupiah($this->isian[$kolom] ?? '');
            $this->isian[$kolom] = $nominal === null ? '' : Format::angka($nominal);
        }

        $id = $this->ubahId === 'baru' ? null : $this->ubahId;
        $kembar = collect($this->semua)->first(fn (array $p) => $p['id'] !== $id && DataPagu::kunci($p['nama_kegiatan']) === DataPagu::kunci($this->isian['nama_kegiatan']));
        $pagu = fn (string $kolom) => Format::parseRupiah($this->isian[$kolom]);

        $this->validate([
            'isian.nama_kegiatan' => ['required', 'string', 'max:255', fn (string $a, mixed $v, Closure $gagal) => $kembar ? $gagal('Nama ini sudah ada di Data Pagu.') : null],
            'isian.leading' => ['nullable', fn (string $a, mixed $v, Closure $gagal) => filled($v) && ! MasterKode::cari(MasterKode::leadings(), $v) ? $gagal('Leading tidak dikenal.') : null],
            'isian.pagu_dipa' => ['nullable', fn (string $a, mixed $v, Closure $gagal) => ($pagu('pagu_dipa') ?? 0) > KegiatanForm::MAKS_PAGU ? $gagal('Pagu melebihi batas wajar.') : null],
            'isian.pagu_komite' => ['nullable', fn (string $a, mixed $v, Closure $gagal) => ($pagu('pagu_komite') ?? 0) > KegiatanForm::MAKS_PAGU ? $gagal('Pagu melebihi batas wajar.') : null],
        ], ['isian.nama_kegiatan.required' => 'Nama kegiatan wajib diisi.', 'isian.nama_kegiatan.max' => 'Nama kegiatan maksimal 255 karakter.']);
        // Aturan closure tidak dijalankan untuk nilai kosong, jadi "minimal satu pagu" diperiksa sendiri.
        if (($pagu('pagu_dipa') ?? 0) < 1 && ($pagu('pagu_komite') ?? 0) < 1) {
            $this->addError('isian.pagu_komite', 'Isi pagu DIPA atau Komite.');

            return;
        }

        $repo->simpan([
            'tahun' => StubData::tahunAktif()['tahun'],
            'nama_kegiatan' => $this->isian['nama_kegiatan'],
            'leading' => $this->isian['leading'] ?: null,
            'pagu' => ['DIPA' => ($pagu('pagu_dipa') ?? 0) > 0 ? $pagu('pagu_dipa') : null, 'Komite' => ($pagu('pagu_komite') ?? 0) > 0 ? $pagu('pagu_komite') : null],
        ], $id);

        $this->dispatch('notify', message: $id ? 'Data pagu diperbarui.' : 'Data pagu ditambahkan.', type: 'success');
        $this->batal();
        unset($this->semua, $this->dipakai, $this->terfilter);
    }

    public function mintaHapus(string $id): void
    {
        abort_unless($this->bolehKelola(), 403);
        $this->akanDihapus = collect($this->semua)->contains('id', $id) ? $id : null;
    }

    public function hapus(DataPaguRepository $repo): void
    {
        abort_unless($this->bolehKelola() && $this->akanDihapus !== null, 403);
        $repo->hapus($this->akanDihapus);
        $this->akanDihapus = null;
        unset($this->semua, $this->dipakai, $this->terfilter);
        $this->dispatch('notify', message: 'Data pagu dihapus. Kegiatan yang sudah dibuat tidak berubah.', type: 'info');
    }

    public function bukaImpor(): void
    {
        abort_unless($this->bolehKelola(), 403);
        $this->imporTerbuka = ! $this->imporTerbuka;
    }

    #[On('pagu-diimpor')]
    public function segarkan(): void
    {
        unset($this->semua, $this->dipakai, $this->terfilter);
    }

    public function hapusSaringan(): void
    {
        $this->reset('cari', 'leading', 'pemakaian');
    }

    public function render()
    {
        return view('livewire.pagu.daftar')->title('Data Pagu');
    }
}
