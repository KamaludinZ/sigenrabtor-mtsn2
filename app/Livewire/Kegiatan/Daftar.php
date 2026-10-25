<?php

namespace App\Livewire\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Support\Kegiatan\Impor\PemeriksaImporKegiatan;
use App\Support\Kegiatan\JadwalPelaksanaan;
use App\Support\MasterKode;
use App\Support\Stub\StubData;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Halaman Daftar Kegiatan tahun anggaran aktif: cari, buka, buat baru, unduh template, dan impor dari Excel.
 */
#[Layout('layouts.app')]
class Daftar extends Component
{
    #[Url(as: 'cari', except: '')]
    public string $cari = '';

    #[Url(as: 'impor', except: false)]
    public bool $imporTerbuka = false;

    /**
     * @return list<array{id: int, nama: string, leading: string|null, sumber: list<array{sumber: string, pagu: int|null}>, bulan: string|null, status: StatusKegiatan, diperbarui: string|null, impor: bool}>
     */
    #[Computed]
    public function kegiatan(): array
    {
        $tahun = StubData::tahunAktif()['tahun'];
        $cari = PemeriksaImporKegiatan::kunciNama($this->cari);
        $leading = array_column(MasterKode::leadings(), 'nama_unit', 'kode');

        $baris = [];
        foreach (app(KegiatanRepository::class)->semua() as $k) {
            if ($cari !== '' && ! str_contains(PemeriksaImporKegiatan::kunciNama((string) ($k['nama_kegiatan'] ?? '')), $cari)) {
                continue;
            }

            $baris[] = [
                'id' => (int) $k['id'],
                'nama' => (string) ($k['nama_kegiatan'] ?? '') ?: 'Tanpa nama',
                'leading' => $leading[$k['leading'] ?? ''] ?? null,
                'sumber' => array_values(array_filter((array) ($k['sumber_dana'] ?? []), fn ($s) => is_array($s) && isset($s['sumber']))),
                'bulan' => JadwalPelaksanaan::teksBulan(array_map('intval', (array) ($k['bulan_pelaksanaan'] ?? [])), $tahun),
                'status' => StatusKegiatan::tryFrom($k['status'] ?? '') ?? StatusKegiatan::Draf,
                'diperbarui' => $k['diperbarui_pada'] ?? null,
                'impor' => ($k['asal'] ?? null) === 'impor_excel',
            ];
        }

        return $baris;
    }

    #[Computed]
    public function jumlahSemua(): int
    {
        return count(app(KegiatanRepository::class)->semua());
    }

    public function bukaImpor(): void
    {
        $this->imporTerbuka = ! $this->imporTerbuka;
    }

    #[On('kegiatan-diimpor')]
    public function segarkan(int $jumlah = 0): void
    {
        unset($this->kegiatan, $this->jumlahSemua);

        if ($jumlah > 0) {
            $this->dispatch('notify', message: "{$jumlah} kegiatan hasil impor ditambahkan ke daftar.", type: 'success');
        }
    }

    public function render()
    {
        return view('livewire.kegiatan.daftar', ['tahun' => StubData::tahunAktif()['tahun']])->title('Data Kegiatan');
    }
}
