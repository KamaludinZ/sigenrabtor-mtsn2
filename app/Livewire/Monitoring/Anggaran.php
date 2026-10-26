<?php

namespace App\Livewire\Monitoring;

use App\Contracts\KegiatanRepository;
use App\Contracts\TahunAnggaranRepository;
use App\Enums\StatusKegiatan;
use App\Enums\SumberDana;
use App\Support\Format;
use App\Support\MasterKode;
use App\Support\Monitoring\RingkasanAnggaran;
use App\Support\Stub\StubData;
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

    public const PER_HALAMAN = 20;

    /** Saringan & urutan yang tidak dikenal (mis. dari URL lama) dilepas. */
    public function mount(): void
    {
        $this->leading = MasterKode::cari(MasterKode::leadings(), $this->leading) ? $this->leading : '';
        $this->sumber = in_array($this->sumber, SumberDana::urutan(), true) ? $this->sumber : '';
        $this->status = StatusKegiatan::tryFrom($this->status) ? $this->status : '';
        $this->tahun = $this->tahunLain($this->tahun) ? $this->tahun : '';
        $this->dari = self::tanggalSah($this->dari) ? $this->dari : '';
        $this->sampai = self::tanggalSah($this->sampai) ? $this->sampai : '';
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
        if (in_array($properti, ['tahun', 'leading', 'sumber', 'status', 'dari', 'sampai'], true)) {
            $this->halaman = 1;
        }
        if ($properti === 'tahun' && ! $this->tahunLain($this->tahun)) {
            $this->tahun = '';
        }
        if (in_array($properti, ['dari', 'sampai'], true) && ! self::tanggalSah($this->{$properti})) {
            $this->{$properti} = '';
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

    /**
     * Tahun anggaran yang bisa dipantau (terbaru lebih dulu).
     *
     * @return list<array{tahun: int, is_active: bool, is_locked: bool}>
     */
    public function daftarTahun(): array
    {
        return app(TahunAnggaranRepository::class)->semua();
    }

    /** $tahun adalah tahun anggaran terdaftar selain tahun aktif. */
    private function tahunLain(string $tahun): bool
    {
        return in_array((int) $tahun, array_column($this->daftarTahun(), 'tahun'), true) && (int) $tahun !== StubData::tahunAktif()['tahun'];
    }

    /** Rentang terbalik (tanggal akhir sebelum tanggal awal) tidak diterapkan sampai diperbaiki. */
    public function rentangTerbalik(): bool
    {
        return $this->dari !== '' && $this->sampai !== '' && $this->dari > $this->sampai;
    }

    private static function tanggalSah(string $teks): bool
    {
        return $teks === '' || (preg_match('/^\d{4}-\d{2}-\d{2}$/', $teks) && checkdate((int) substr($teks, 5, 2), (int) substr($teks, 8, 2), (int) substr($teks, 0, 4)));
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
        $grup = explode('_', $this->urutLeading)[0];

        return in_array($grup, ['kode', 'jumlah'], true) || $this->sumber === '' || $grup === mb_strtolower($this->sumber);
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
        $rentang = $this->rentangTerbalik() ? [] : ['dari' => $this->dari, 'sampai' => $this->sampai];

        return RingkasanAnggaran::dari(app(KegiatanRepository::class)->semua(), ['tahun' => $this->tahun(), 'leading' => $this->leading, 'sumber' => $this->sumber, 'status' => $this->status] + $rentang);
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
        $tanggal = fn (string $t) => Format::tanggal($t);

        return array_filter([
            'tahun' => $this->tahun !== '' ? "TA {$this->tahun}" : null,
            'leading' => MasterKode::cari(MasterKode::leadings(), $this->leading)['nama_unit'] ?? null,
            'sumber' => $this->sumber !== '' ? "Dana {$this->sumber}" : null,
            'status' => StatusKegiatan::tryFrom($this->status)?->label(),
            'tanggal' => match (true) {
                $this->rentangTerbalik() => null,
                $this->dari !== '' && $this->sampai !== '' => 'Pelaksanaan '.$tanggal($this->dari).' – '.$tanggal($this->sampai),
                $this->dari !== '' => 'Pelaksanaan sejak '.$tanggal($this->dari),
                $this->sampai !== '' => 'Pelaksanaan s.d. '.$tanggal($this->sampai),
                default => null,
            },
        ]);
    }

    /** Tahun yang sedang dipantau (saringan tahun atau tahun aktif). */
    public function tahun(): int
    {
        return $this->tahun !== '' ? (int) $this->tahun : StubData::tahunAktif()['tahun'];
    }

    public function render()
    {
        $this->halaman = min($this->halaman, $this->jumlahHalaman());

        return view('livewire.monitoring.anggaran')->title('Monitoring Anggaran');
    }
}
