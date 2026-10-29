<?php

namespace App\Livewire\Dokumen;

use App\Support\Dokumen\RiwayatDokumen;
use App\Support\Stub\StubData;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Halaman Riwayat Dokumen (PRD §7, F28; `/dokumen`, rute `dokumen.index`, semua pengguna): semua berkas KAK/TOR, RAB, dan
 * set dokumen yang pernah dicetak, diunduh, atau diarsipkan dalam cakupan akun (RiwayatDokumen::untuk), dikelompokkan per
 * tahun anggaran (terbaru dulu), dengan ringkasan jumlah dokumen, kegiatan, arsip final, dan dokumen bercap DRAF.
 * Kata kunci `?cari=` menyaring kedua tampilan (RiwayatDokumen::cari, sorotan RiwayatDokumen::sorot). Tampilan `?tampilan=` (TAMPILAN): `kegiatan` (bawaan) = kartu per kegiatan dengan versi terbaru tiap jenis × sumber ×
 * format dan versi lama yang bisa dibuka (RiwayatDokumen::perKegiatan); `kronologis` = tabel per tahun berurutan `?urut=`
 * (RiwayatDokumen::URUT). Daftar dibatasi `$batas` (PER_HALAMAN, "Tampilkan lebih banyak"); berganti tampilan/urutan
 * mengembalikan batasnya.
 */
#[Layout('layouts.app')]
class Riwayat extends Component
{
    public const TAMPILAN = ['kegiatan' => 'Per kegiatan', 'kronologis' => 'Kronologis'];

    public const PER_HALAMAN = 20;

    #[Url(except: 'kegiatan')]
    public string $tampilan = 'kegiatan';

    #[Url(except: 'terbaru')]
    public string $urut = 'terbaru';

    /** Kata kunci (RiwayatDokumen::cari). */
    #[Url(except: '')]
    public string $cari = '';

    /** Jumlah kegiatan (tampilan per kegiatan) atau dokumen (kronologis) yang ditampilkan. */
    public int $batas = self::PER_HALAMAN;

    public function mount(): void
    {
        $this->rapikan();
    }

    public function updated(string $properti): void
    {
        if (in_array($properti, ['tampilan', 'urut', 'cari'], true)) {
            $this->rapikan();
            $this->batas = self::PER_HALAMAN;
        }
    }

    public function tampilkanLebih(): void
    {
        $this->batas += self::PER_HALAMAN;
    }

    private function rapikan(): void
    {
        $this->tampilan = array_key_exists($this->tampilan, self::TAMPILAN) ? $this->tampilan : 'kegiatan';
        $this->urut = array_key_exists($this->urut, RiwayatDokumen::URUT) ? $this->urut : 'terbaru';
        $this->cari = mb_substr(trim($this->cari), 0, 100);
    }

    /**
     * Isi tampilan aktif: [kelompok per tahun (dipotong `$batas`), jumlah butir seluruhnya].
     *
     * @return array{0: array<int, list<array<string, mixed>>>, 1: int}
     */
    /** @return list<array<string, mixed>> Catatan yang cocok dengan kata kunci. */
    #[Computed]
    public function cocok(): array
    {
        return RiwayatDokumen::cari($this->semua, $this->cari);
    }

    public function isi(): array
    {
        $butir = $this->tampilan === 'kegiatan'
            ? RiwayatDokumen::perKegiatan($this->cocok)
            : ($this->urut === 'terlama' ? array_reverse($this->cocok) : $this->cocok);
        $kelompok = [];
        foreach (array_slice($butir, 0, $this->batas) as $b) {
            $kelompok[(int) $b['tahun_anggaran']][] = $b;
        }
        $this->urut === 'terlama' && $this->tampilan === 'kronologis' ? ksort($kelompok) : krsort($kelompok);

        return [$kelompok, count($butir)];
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function semua(): array
    {
        return RiwayatDokumen::untuk(StubData::currentUser());
    }

    public function render()
    {
        return view('livewire.dokumen.riwayat')->title('Riwayat Dokumen');
    }
}
