<?php

namespace App\Livewire\Monitoring;

use App\Contracts\KegiatanRepository;
use App\Support\Monitoring\DaftarKegiatanMonitoring;
use App\Support\Monitoring\DataMonitoring;
use App\Support\Monitoring\RingkasanAnggaran;
use App\Support\Monitoring\SaringanMonitoring;
use App\Support\Stub\StubPengaturan;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Monitoring Anggaran (/monitoring, khusus admin; PRD Fase 5 §11): saringan tahun / leading / sumber dana / status /
 * rentang tanggal pelaksanaan di URL,
 * ringkasan tahun aktif (kegiatan per status, pagu & RAB per sumber dana, selisih), lalu bagian rekap per leading,
 * rincian kegiatan (urutan & halaman di URL), kelompok belanja, dan operator.
 */
#[Layout('layouts.app')]
class Anggaran extends Component
{
    /** Tahun anggaran yang dipantau; '' = tahun aktif. */
    #[Url(except: '')]
    public string $tahun = '';

    #[Url(except: '')]
    public string $leading = '';

    #[Url(except: '')]
    public string $sumber = '';

    #[Url(except: '')]
    public string $status = '';

    /** Rentang tanggal pelaksanaan (Y-m-d; boleh salah satu saja). */
    #[Url(except: '')]
    public string $dari = '';

    #[Url(except: '')]
    public string $sampai = '';

    /** Urutan tabel rincian per kegiatan (kunci RingkasanAnggaran::URUT_RINCIAN). */
    #[Url(except: 'total')]
    public string $urut = 'total';

    #[Url(except: 'desc')]
    public string $arah = 'desc';

    #[Url(as: 'hal', except: 1)]
    public int $halaman = 1;

    /** Urutan tabel Total per leading (kunci RingkasanAnggaran::urutLeading()). */
    #[Url(as: 'urut_leading', except: 'kode')]
    public string $urutLeading = 'kode';

    #[Url(as: 'arah_leading', except: 'asc')]
    public string $arahLeading = 'asc';

    public const PER_HALAMAN = DaftarKegiatanMonitoring::PER_HALAMAN;

    /** Saringan & urutan yang tidak dikenal (mis. dari URL lama) dilepas. */
    public function mount(): void
    {
        $this->rapikanSaringan();
        if (! isset(RingkasanAnggaran::URUT_RINCIAN[$this->urut])) {
            $this->reset('urut', 'arah');
        }
        $this->arah = in_array($this->arah, ['asc', 'desc'], true) ? $this->arah : RingkasanAnggaran::URUT_RINCIAN[$this->urut][1];
        $this->halaman = max(1, $this->halaman);
        $pilihan = RingkasanAnggaran::urutLeading();
        if (! isset($pilihan[$this->urutLeading]) || ! $this->urutLeadingTampil()) {
            $this->reset('urutLeading', 'arahLeading');
        }
        $this->arahLeading = in_array($this->arahLeading, ['asc', 'desc'], true) ? $this->arahLeading : $pilihan[$this->urutLeading][1];
    }

    /** Saringan berubah → rincian kembali ke halaman pertama; urutan dari pilihan memakai arah bawaannya. */
    public function updated(string $properti): void
    {
        if (in_array($properti, SaringanMonitoring::KUNCI, true)) {
            $this->halaman = 1;
            $this->rapikanSaringan();
        }
        if ($properti === 'sumber' && ! $this->urutLeadingTampil()) {
            $this->reset('urutLeading', 'arahLeading');
        }
        if ($properti === 'urut') {
            $this->urutkan($this->urut, ulang: false);
        }
        if ($properti === 'urutLeading') {
            $this->urutkanLeading($this->urutLeading, ulang: false);
        }
    }

    /** Lepas satu saringan (tanggal = kedua batas rentang) atau semuanya. */
    public function hapusSaringan(?string $saringan = null): void
    {
        match ($saringan) {
            'tahun', 'leading', 'sumber', 'status' => $this->reset($saringan),
            'tanggal' => $this->reset('dari', 'sampai'),
            default => $this->reset('tahun', 'leading', 'sumber', 'status', 'dari', 'sampai'),
        };
        $this->halaman = 1;
    }

    /** Saringan halaman sebagai objek bersama (aturan sama dengan berkas ekspor). */
    public function saringan(): SaringanMonitoring
    {
        return new SaringanMonitoring($this->tahun, $this->leading, $this->sumber, $this->status, $this->dari, $this->sampai);
    }

    /** Saringan yang tidak dikenal (mis. dari URL lama) dilepas; tahun aktif ditulis ''. */
    private function rapikanSaringan(): void
    {
        $rapi = SaringanMonitoring::dari($this->only(SaringanMonitoring::KUNCI));
        foreach (SaringanMonitoring::KUNCI as $kunci) {
            $this->{$kunci} = $rapi->{$kunci};
        }
    }

    /**
     * @return list<array{tahun: int, is_active: bool, is_locked: bool}>
     */
    public function daftarTahun(): array
    {
        return SaringanMonitoring::daftarTahun();
    }

    public function rentangTerbalik(): bool
    {
        return $this->saringan()->rentangTerbalik();
    }

    /**
     * Parameter tautan ekspor: saringan + urutan tabel yang bukan bawaan, sehingga berkas sama dengan layar.
     *
     * @return array<string, string>
     */
    public function queryEkspor(): array
    {
        return $this->saringan()->query() + array_filter([
            'urut' => $this->urut !== 'total' ? $this->urut : '',
            'arah' => $this->arah !== RingkasanAnggaran::URUT_RINCIAN[$this->urut][1] ? $this->arah : '',
            'urut_leading' => $this->urutLeading !== 'kode' ? $this->urutLeading : '',
            'arah_leading' => $this->arahLeading !== (RingkasanAnggaran::urutLeading()[$this->urutLeading][1] ?? 'asc') ? $this->arahLeading : '',
        ]);
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

    /** Judul kolom tabel Total per leading (perilaku sama dengan urutkan()). */
    public function urutkanLeading(string $kolom, bool $ulang = true): void
    {
        $pilihan = RingkasanAnggaran::urutLeading();
        if (! isset($pilihan[$kolom])) {
            return;
        }

        $this->arahLeading = $ulang && $kolom === $this->urutLeading ? ($this->arahLeading === 'asc' ? 'desc' : 'asc') : $pilihan[$kolom][1];
        $this->urutLeading = $kolom;
    }

    /** Urutan per leading memakai kolom yang tampil (saringan satu sumber dana menyembunyikan kolom sumber lain & Jumlah). */
    private function urutLeadingTampil(): bool
    {
        return DataMonitoring::urutLeadingSah($this->urutLeading, $this->sumber);
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
        return RingkasanAnggaran::dari(app(KegiatanRepository::class)->semua(), $this->saringan()->untukRingkasan());
    }

    /**
     * Rekap per operator (pembuat kegiatan) dari akun aplikasi.
     *
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function operator(): array
    {
        return $this->ringkasan->perOperator(StubPengaturan::pengguna());
    }

    /**
     * Saringan aktif sebagai chip: properti → label.
     *
     * @return array<string, string>
     */
    public function saringanAktif(): array
    {
        return $this->saringan()->label();
    }

    /** Tahun yang sedang dipantau (saringan tahun atau tahun aktif). */
    public function tahun(): int
    {
        return $this->saringan()->tahunDipantau();
    }

    public function render()
    {
        $this->halaman = min($this->halaman, $this->jumlahHalaman());

        return view('livewire.monitoring.anggaran')->title('Monitoring Anggaran');
    }
}
