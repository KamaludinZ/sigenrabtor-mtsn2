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
 * rincian kegiatan, kelompok belanja, dan operator.
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

    /** Saringan yang tidak dikenal (mis. dari URL lama) dilepas. */
    public function mount(): void
    {
        $this->leading = MasterKode::cari(MasterKode::leadings(), $this->leading) ? $this->leading : '';
        $this->sumber = in_array($this->sumber, SumberDana::urutan(), true) ? $this->sumber : '';
        $this->status = StatusKegiatan::tryFrom($this->status) ? $this->status : '';
    }

    public function hapusSaringan(?string $saringan = null): void
    {
        in_array($saringan, ['leading', 'sumber', 'status'], true) ? $this->reset($saringan) : $this->reset('leading', 'sumber', 'status');
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
        return view('livewire.monitoring.anggaran')->title('Monitoring Anggaran');
    }
}
