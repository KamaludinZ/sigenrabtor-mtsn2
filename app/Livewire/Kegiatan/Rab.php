<?php

namespace App\Livewire\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Support\Stub\StubData;
use App\Support\Stub\StubMaster;
use App\Support\Stub\StubRab;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Halaman RAB satu kegiatan: satu RAB per sumber dana (DIPA / Komite).
 */
#[Layout('layouts.app')]
class Rab extends Component
{
    #[Locked]
    public int $kegiatanId;

    /** @var array<string, mixed> */
    #[Locked]
    public array $dataKegiatan = [];

    /** Sumber dana yang sedang ditampilkan; tersimpan di URL (?sumber=Komite). */
    #[Url]
    public string $sumber = '';

    public function mount(KegiatanRepository $repo, int $kegiatan): void
    {
        $this->dataKegiatan = $repo->temukan($kegiatan) ?? abort(404, 'Kegiatan tidak ditemukan.');
        $this->kegiatanId = $kegiatan;

        if (! in_array($this->sumber, $this->daftarSumber(), true)) {
            $this->sumber = $this->daftarSumber()[0] ?? '';
        }
    }

    public function pilihSumber(string $sumber): void
    {
        if (in_array($sumber, $this->daftarSumber(), true)) {
            $this->sumber = $sumber;
        }
    }

    /**
     * @return list<string>
     */
    public function daftarSumber(): array
    {
        return array_column($this->dataKegiatan['sumber_dana'] ?? [], 'sumber');
    }

    public function pagu(): ?int
    {
        foreach ($this->dataKegiatan['sumber_dana'] ?? [] as $baris) {
            if ($baris['sumber'] === $this->sumber) {
                return $baris['pagu'] !== null ? (int) $baris['pagu'] : null;
            }
        }

        return null;
    }

    /**
     * Rincian belanja sumber dana aktif; jumlah = volume × harga satuan.
     *
     * @return list<array{kode: string, uraian: string, kategori: string, volume: float|int, satuan: string, harga_satuan: int, jumlah: int}>
     */
    #[Computed]
    public function rincian(): array
    {
        return array_map(
            fn (array $item) => $item + ['jumlah' => (int) round($item['volume'] * $item['harga_satuan'])],
            $this->sumber === '' ? [] : StubRab::rincian($this->kegiatanId, $this->sumber),
        );
    }

    #[Computed]
    public function total(): int
    {
        return array_sum(array_column($this->rincian, 'jumlah'));
    }

    /**
     * Isi header RAB sesuai format resmi madrasah.
     *
     * @return array{leading: string, program: string, kode_anggaran: string, tahun: int}
     */
    #[Computed]
    public function header(): array
    {
        $leading = StubMaster::cari(StubMaster::leadings(), $this->dataKegiatan['leading'] ?? null);

        return [
            'leading' => $leading['nama_unit'] ?? '—',
            'program' => $this->dataKegiatan['nama_kegiatan'] ?? '—',
            'kode_anggaran' => $this->dataKegiatan['kode_rktm'] ?? '—',
            'tahun' => StubData::tahunAktif()['tahun'],
        ];
    }

    public function status(): StatusKegiatan
    {
        return StatusKegiatan::tryFrom($this->dataKegiatan['status'] ?? '') ?? StatusKegiatan::Draf;
    }

    public function render()
    {
        return view('livewire.kegiatan.rab')
            ->title('RAB · '.($this->dataKegiatan['nama_kegiatan'] ?? 'Kegiatan'));
    }
}
