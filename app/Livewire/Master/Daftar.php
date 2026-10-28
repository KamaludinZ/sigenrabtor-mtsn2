<?php

namespace App\Livewire\Master;

use App\Contracts\KegiatanRepository;
use App\Contracts\MasterKodeRepository;
use App\Contracts\TahunAnggaranRepository;
use App\Support\Kegiatan\TahunKegiatan;
use App\Support\Master\JenisKode;
use App\Support\Master\SalinMasterKode;
use App\Support\MasterKode;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use RuntimeException;

/**
 * Master Kode Tahunan (PRD Fase 6 §14, F7): kode SK, IKSK, RKTM, EDM, dan 5 Budaya Mutu per tahun anggaran — tab per
 * jenis dengan jumlah baris & pemakaian oleh kegiatan tahun itu. Tahun di `?ta=` ('' = tahun aktif), jenis di `?jenis=`.
 * Unggah Excel lewat panel anak Master\UnggahMaster (pratinjau sebelum simpan menyusul).
 */
#[Layout('layouts.app')]
class Daftar extends Component
{
    #[Url(as: 'ta', except: '')]
    public string $tahun = '';

    #[Url(except: 'sk')]
    public string $jenis = 'sk';

    /** Panel unggah master terbuka (tahun yang dilihat, bukan tahun terkunci). */
    public bool $unggahTerbuka = false;

    public function mount(): void
    {
        $this->rapikan();
    }

    public function updatedTahun(): void
    {
        $this->rapikan();
        $this->unggahTerbuka = false;
    }

    public function bukaUnggah(): void
    {
        $this->unggahTerbuka = ! $this->tahunTerkunci();
    }

    #[On('unggah-master-ditutup')]
    public function tutupUnggah(bool $dibatalkan = false): void
    {
        $this->unggahTerbuka = false;
        if ($dibatalkan) {
            $this->dispatch('notify', message: 'Unggahan dibatalkan — master kode TA '.$this->tahunDilihat().' tidak berubah.', type: 'info');
        }
    }

    public function updatedJenis(): void
    {
        $this->rapikan();
    }

    /**
     * Tahun anggaran terdaftar (terbaru lebih dulu) beserta ketersediaan masternya.
     *
     * @return list<array{tahun: int, is_active: bool, is_locked: bool, tersedia: bool}>
     */
    #[Computed]
    public function daftarTahun(): array
    {
        return array_map(fn (array $t) => [
            'tahun' => $t['tahun'],
            'is_active' => $t['is_active'],
            'is_locked' => $t['is_locked'],
            'tersedia' => MasterKode::tersedia($t['tahun']),
        ], app(TahunAnggaranRepository::class)->semua());
    }

    /** Tahun sumber yang disarankan untuk menyalin master ke tahun yang dilihat (SalinMasterKode::sumber). */
    public function sumberSalin(): ?int
    {
        return $this->tahunTerkunci() ? null : SalinMasterKode::sumber($this->tahunDilihat());
    }

    /** Salin master tahun $dari ke tahun yang dilihat — hanya jenis yang masih kosong. */
    public function salinMaster(int $dari): void
    {
        $ke = $this->tahunDilihat();
        try {
            $hasil = SalinMasterKode::salin($dari, $ke, StubData::currentUser());
        } catch (AuthorizationException $e) {
            $this->dispatch('notify', message: $e->getMessage(), type: 'error');

            return;
        } catch (ValidationException $e) {
            $this->dispatch('notify', message: $e->validator->errors()->first(), type: 'error');

            return;
        } catch (RuntimeException $e) {
            report($e);
            $this->dispatch('notify', message: "Master kode TA {$ke} gagal disalin — coba lagi.", type: 'error');

            return;
        }

        unset($this->jumlah, $this->baris, $this->pemakaian, $this->daftarTahun);
        $this->dispatch('notify', message: $hasil['disalin'] === []
            ? "Master kode TA {$ke} sudah terisi — tidak ada yang disalin."
            : "Master kode TA {$dari} disalin ke TA {$ke} (".array_sum($hasil['disalin']).' kode).', type: $hasil['disalin'] === [] ? 'info' : 'success');
    }

    /**
     * Unggahan Excel terakhir yang disimpan untuk tahun yang dilihat (MasterKodeRepository::unggahan).
     *
     * @return array<string, mixed>|null
     */
    public function unggahanTerakhir(): ?array
    {
        return app(MasterKodeRepository::class)->unggahan($this->tahunDilihat())[0] ?? null;
    }

    public function tahunDilihat(): int
    {
        return $this->tahun !== '' ? (int) $this->tahun : StubData::tahunAktif()['tahun'];
    }

    public function tahunTerkunci(): bool
    {
        return (bool) (collect($this->daftarTahun)->firstWhere('tahun', $this->tahunDilihat())['is_locked'] ?? false);
    }

    public function tersedia(): bool
    {
        return MasterKode::tersedia($this->tahunDilihat());
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function jumlah(): array
    {
        return JenisKode::jumlah($this->tahunDilihat());
    }

    /**
     * Baris jenis yang dipilih.
     *
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function baris(): array
    {
        return JenisKode::baris($this->jenis, $this->tahunDilihat());
    }

    /**
     * Kode jenis terpilih → jumlah kegiatan tahun yang dilihat yang memakainya.
     *
     * @return array<string, int>
     */
    #[Computed]
    public function pemakaian(): array
    {
        $tahun = $this->tahunDilihat();
        $kegiatan = array_filter(app(KegiatanRepository::class)->semua(), fn (array $k) => TahunKegiatan::dari($k) === $tahun);

        return JenisKode::pemakaian($this->jenis, $kegiatan);
    }

    /**
     * Uraian SK per kode (kolom SK di tab IKSK) dan jumlah IKSK per SK (tab SK).
     *
     * @return array{uraian_sk: array<string, string>, iksk_per_sk: array<string, int>}
     */
    public function relasiSk(): array
    {
        $tahun = $this->tahunDilihat();

        return [
            'uraian_sk' => array_column(MasterKode::kodeSk($tahun), 'uraian', 'kode'),
            'iksk_per_sk' => array_count_values(array_column(MasterKode::kodeIksk(null, $tahun), 'kode_sk')),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function komponenEdm(): array
    {
        return MasterKode::komponenEdm($this->tahunDilihat());
    }

    private function rapikan(): void
    {
        if (! array_key_exists($this->jenis, JenisKode::DAFTAR)) {
            $this->jenis = 'sk';
        }

        $terdaftar = in_array((int) $this->tahun, array_column($this->daftarTahun, 'tahun'), true);
        if (! $terdaftar || (int) $this->tahun === StubData::tahunAktif()['tahun']) {
            $this->tahun = '';
        }

        unset($this->jumlah, $this->baris, $this->pemakaian);
    }

    public function render()
    {
        return view('livewire.master.daftar')->title('Master Kode');
    }
}
