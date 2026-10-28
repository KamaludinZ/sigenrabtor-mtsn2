<?php

namespace App\Livewire\Pagu;

use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Enums\SumberDana;
use App\Support\Format;
use App\Support\Kegiatan\KunciKegiatan;
use App\Support\Pagu\DataPagu;
use App\Support\Pagu\SimpanDataPagu;
use App\Support\Stub\StubData;
use Illuminate\Validation\ValidationException;
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

    /** Saat mengubah data yang sudah menjadi kegiatan: pagu RAB kegiatan itu ikut diganti (SimpanDataPagu terapkan). */
    public bool $terapkan = false;

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
     * Stats overview dari data tersaring: jumlah data, yang belum/sudah jadi kegiatan, total pagu & banyaknya data berpagu
     * per sumber dana.
     *
     * @return array{jumlah: int, belum: int, sudah: int, pagu: array<string, int>, berpagu: array<string, int>}
     */
    public function ringkasan(): array
    {
        $pagu = array_fill_keys(SumberDana::urutan(), 0);
        $berpagu = $pagu;
        foreach ($this->terfilter as $p) {
            foreach ($p['pagu'] as $s => $v) {
                $pagu[$s] += (int) $v;
                $berpagu[$s] += (int) $v > 0 ? 1 : 0;
            }
        }
        $belum = count(array_filter($this->terfilter, fn ($p) => $this->dipakai[$p['id']] === null));

        return ['jumlah' => count($this->terfilter), 'belum' => $belum, 'sudah' => count($this->terfilter) - $belum, 'pagu' => $pagu, 'berpagu' => $berpagu];
    }

    public function tambah(): void
    {
        abort_unless($this->bolehKelola(), 403);
        $this->isian = ['nama_kegiatan' => '', 'leading' => $this->leading !== 'tanpa' ? $this->leading : '', 'pagu_dipa' => '', 'pagu_komite' => ''];
        $this->resetValidation();
        $this->terapkan = false;
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
        $this->terapkan = false;
        $this->ubahId = $id;
    }

    public function batal(): void
    {
        $this->reset('ubahId', 'isian', 'terapkan');
        $this->resetValidation();
    }

    /**
     * Simpan lewat SimpanDataPagu (aturan sama dengan server): isian ditampilkan rapi lagi, galat dipetakan ke isian.*,
     * lalu notifikasi menyebut dampaknya pada kegiatan pemakai (pagu RAB ikut diperbarui bila $terapkan).
     */
    public function simpan(): void
    {
        abort_unless($this->bolehKelola() && $this->ubahId !== null, 403);
        $rapi = SimpanDataPagu::rapikan($this->isian);
        $this->isian['nama_kegiatan'] = $rapi['nama_kegiatan'];
        foreach (SumberDana::cases() as $sumber) {
            $this->isian['pagu_'.$sumber->kunci()] = $rapi['pagu'][$sumber->value] === null ? '' : Format::angka($rapi['pagu'][$sumber->value]);
        }
        $this->resetValidation();

        $id = $this->ubahId === 'baru' ? null : $this->ubahId;
        try {
            $hasil = SimpanDataPagu::simpan($this->isian, $id, StubData::currentUser(), $id !== null && $this->terapkan);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $kolom => $pesan) {
                $sumber = str_starts_with($kolom, 'pagu.') ? SumberDana::tryFrom(substr($kolom, 5)) : null;
                $this->addError('isian.'.($sumber ? 'pagu_'.$sumber->kunci() : $kolom), $pesan[0]);
            }

            return;
        }

        $this->dispatch('notify', message: $id ? 'Data pagu diperbarui.' : 'Data pagu ditambahkan.', type: 'success');
        foreach (array_filter([$hasil['alasan'], SimpanDataPagu::ringkasDampak($hasil), ...array_values($hasil['terlepas'])]) as $pesan) {
            $this->dispatch('notify', message: $pesan, type: 'info');
        }
        $this->batal();
        unset($this->semua, $this->dipakai, $this->terfilter);
    }

    /**
     * Kegiatan yang memakai data pagu yang sedang diubah (untuk pilihan "Perbarui juga pagu RAB kegiatan").
     *
     * @return array{id: int, nama: string, dapat_diubah: bool, status: string}|null
     */
    #[Computed]
    public function pemakaiUbah(): ?array
    {
        $pemakai = $this->ubahId !== null && $this->ubahId !== 'baru' ? ($this->dipakai[$this->ubahId] ?? null) : null;
        if ($pemakai === null) {
            return null;
        }
        $status = StatusKegiatan::tryFrom((string) (app(KegiatanRepository::class)->temukan($pemakai['id'])['status'] ?? '')) ?? StatusKegiatan::Draf;

        return $pemakai + ['dapat_diubah' => KunciKegiatan::dapatDiubah(app(KegiatanRepository::class)->temukan($pemakai['id']) ?? []), 'status' => $status->label()];
    }

    public function mintaHapus(string $id): void
    {
        abort_unless($this->bolehKelola(), 403);
        $this->akanDihapus = collect($this->semua)->contains('id', $id) ? $id : null;
    }

    public function hapus(): void
    {
        abort_unless($this->bolehKelola() && $this->akanDihapus !== null, 403);
        SimpanDataPagu::hapus($this->akanDihapus, StubData::currentUser());
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
