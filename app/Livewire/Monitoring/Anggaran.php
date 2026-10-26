<?php

namespace App\Livewire\Monitoring;

use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Enums\SumberDana;
use App\Support\MasterKode;
use App\Support\Monitoring\RingkasanAnggaran;
use App\Support\Stub\StubData;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Monitoring Anggaran (/monitoring, khusus admin; PRD Fase 5 §11): saringan leading / sumber dana / status di URL,
 * ringkasan tahun aktif (kegiatan per status, pagu & RAB per sumber dana, selisih), lalu bagian rekap per leading,
 * rincian kegiatan (urutan & halaman di URL), kelompok belanja, dan operator.
 */
#[Layout('layouts.app')]
class Anggaran extends Component
{
    #[Url(except: '')]
    public string $leading = '';

    #[Url(except: '')]
    public string $sumber = '';

    #[Url(except: '')]
    public string $status = '';

    /** Urutan tabel rincian per kegiatan (kunci RingkasanAnggaran::URUT_RINCIAN). */
    #[Url(except: 'total')]
    public string $urut = 'total';

    #[Url(except: 'desc')]
    public string $arah = 'desc';

    #[Url(as: 'hal', except: 1)]
    public int $halaman = 1;

    public const PER_HALAMAN = 20;

    /** Saringan & urutan yang tidak dikenal (mis. dari URL lama) dilepas. */
    public function mount(): void
    {
        $this->leading = MasterKode::cari(MasterKode::leadings(), $this->leading) ? $this->leading : '';
        $this->sumber = in_array($this->sumber, SumberDana::urutan(), true) ? $this->sumber : '';
        $this->status = StatusKegiatan::tryFrom($this->status) ? $this->status : '';
        if (! isset(RingkasanAnggaran::URUT_RINCIAN[$this->urut])) {
            $this->reset('urut', 'arah');
        }
        $this->arah = in_array($this->arah, ['asc', 'desc'], true) ? $this->arah : RingkasanAnggaran::URUT_RINCIAN[$this->urut][1];
        $this->halaman = max(1, $this->halaman);
    }

    /** Saringan berubah → rincian kembali ke halaman pertama; urutan dari pilihan memakai arah bawaannya. */
    public function updated(string $properti): void
    {
        if (in_array($properti, ['leading', 'sumber', 'status'], true)) {
            $this->halaman = 1;
        }
        if ($properti === 'urut') {
            $this->urutkan($this->urut, ulang: false);
        }
    }

    public function hapusSaringan(?string $saringan = null): void
    {
        in_array($saringan, ['leading', 'sumber', 'status'], true) ? $this->reset($saringan) : $this->reset('leading', 'sumber', 'status');
        $this->halaman = 1;
    }

    /** Judul kolom rincian: kolom yang sama membalik arah ($ulang), kolom lain mulai dari arah bawaannya. */
    public function urutkan(string $kolom, bool $ulang = true): void
    {
        if (! isset(RingkasanAnggaran::URUT_RINCIAN[$kolom])) {
            return;
        }

        $this->arah = $ulang && $kolom === $this->urut ? ($this->arah === 'asc' ? 'desc' : 'asc') : RingkasanAnggaran::URUT_RINCIAN[$kolom][1];
        $this->urut = $kolom;
        $this->halaman = 1;
    }

    public function keHalaman(int $halaman): void
    {
        $this->halaman = max(1, min($halaman, $this->jumlahHalaman()));
    }

    /**
     * Rincian per kegiatan terurut (semua halaman).
     *
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function rincian(): array
    {
        return $this->ringkasan->rincianKegiatan($this->urut, $this->arah);
    }

    public function jumlahHalaman(): int
    {
        return max(1, (int) ceil(count($this->rincian) / self::PER_HALAMAN));
    }

    #[Computed]
    public function ringkasan(): RingkasanAnggaran
    {
        return RingkasanAnggaran::dari(app(KegiatanRepository::class)->semua(), ['leading' => $this->leading, 'sumber' => $this->sumber, 'status' => $this->status]);
    }

    /**
     * Saringan aktif sebagai chip: properti → label.
     *
     * @return array<string, string>
     */
    public function saringanAktif(): array
    {
        return array_filter([
            'leading' => MasterKode::cari(MasterKode::leadings(), $this->leading)['nama_unit'] ?? null,
            'sumber' => $this->sumber !== '' ? "Dana {$this->sumber}" : null,
            'status' => StatusKegiatan::tryFrom($this->status)?->label(),
        ]);
    }

    public function tahun(): int
    {
        return StubData::tahunAktif()['tahun'];
    }

    public function render()
    {
        $this->halaman = min($this->halaman, $this->jumlahHalaman());

        return view('livewire.monitoring.anggaran')->title('Monitoring Anggaran');
    }
}
