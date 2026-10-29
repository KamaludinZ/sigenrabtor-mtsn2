<?php

namespace App\Livewire\Dokumen;

use App\Support\Dokumen\PenelusuranRiwayat;
use App\Support\Dokumen\RiwayatDokumen;
use App\Support\Dokumen\RiwayatKosong;
use App\Support\Stub\StubData;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Halaman Riwayat Dokumen (PRD §7, F28; `/dokumen`, rute `dokumen.index`, semua pengguna): semua berkas KAK/TOR, RAB, dan
 * set dokumen yang pernah dicetak, diunduh, atau diarsipkan dalam cakupan akun (RiwayatDokumen::untuk), dikelompokkan per
 * tahun anggaran (terbaru dulu), dengan ringkasan jumlah dokumen, kegiatan, arsip final, dan dokumen bercap DRAF.
 * Saringan `?jenis=&format=&dari=&sampai=` (RiwayatDokumen::saring — rentang terbalik tidak diterapkan; chip `lepasSaringan`,
 * `hapusSaringan`, `pintasanTanggal` 30 hari/TA aktif) dan kata kunci `?cari=` (RiwayatDokumen::cari, sorotan
 * RiwayatDokumen::sorot) diterapkan bersama lewat satu PenelusuranRiwayat (`penelusuran`): daftar kedua tampilan, ringkasan
 * "dari …", hitungan tiap pilihan jenis/format, dan keterangan hasil. Tampilan `?tampilan=` (TAMPILAN): `kegiatan`
 * (bawaan) = kartu per kegiatan dengan versi terbaru tiap jenis × sumber × format, versi lama yang bisa dibuka, dan penanda
 * versi lebih baru yang tersaring (PenelusuranRiwayat::perKegiatan); `kronologis` = tabel per tahun berurutan `?urut=`
 * (RiwayatDokumen::URUT). Daftar dibatasi `$batas` (PER_HALAMAN, "Tampilkan lebih banyak"); berganti tampilan/urutan/
 * kriteria mengembalikan batasnya. Keadaan kosong: tanpa catatan sama sekali = cara dokumen tercatat + dokumen tahun aktif
 * yang bisa dicetak / tautan membuat kegiatan (RiwayatKosong, `kosong`); tanpa hasil = saran melepas satu kriteria
 * beserta jumlah dokumennya (PenelusuranRiwayat::saranLonggar).
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

    /** Saringan RiwayatDokumen::SARINGAN (jenis dokumen, format berkas, tanggal dibuat Y-m-d). */
    #[Url(except: '')]
    public string $jenis = '';

    #[Url(except: '')]
    public string $format = '';

    #[Url(except: '')]
    public string $dari = '';

    #[Url(except: '')]
    public string $sampai = '';

    /** Jumlah kegiatan (tampilan per kegiatan) atau dokumen (kronologis) yang ditampilkan. */
    public int $batas = self::PER_HALAMAN;

    public function mount(): void
    {
        $this->rapikan();
    }

    public function updated(string $properti): void
    {
        if (in_array($properti, ['tampilan', 'urut', ...PenelusuranRiwayat::KRITERIA], true)) {
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
        $this->cari = PenelusuranRiwayat::rapikanCari($this->cari);
        $saringan = RiwayatDokumen::rapikanSaringan($this->saringan());
        foreach (RiwayatDokumen::SARINGAN as $kunci) {
            $this->{$kunci} = $saringan[$kunci];
        }
    }

    /** @return array<string, string> */
    public function saringan(): array
    {
        return ['jenis' => $this->jenis, 'format' => $this->format, 'dari' => $this->dari, 'sampai' => $this->sampai];
    }

    /** Kata kunci & saringan atas catatan dalam cakupan akun. */
    #[Computed]
    public function penelusuran(): PenelusuranRiwayat
    {
        return PenelusuranRiwayat::dari($this->semua, ['cari' => $this->cari] + $this->saringan());
    }

    /** @return list<array<string, mixed>> Catatan yang cocok dengan kata kunci & saringan. */
    #[Computed]
    public function cocok(): array
    {
        return $this->penelusuran->cocok;
    }

    /** Lepas satu saringan (chip): jenis, format, atau tanggal (dari & sampai). */
    public function lepasSaringan(string $kunci): void
    {
        match ($kunci) {
            'tanggal' => $this->reset('dari', 'sampai'),
            'jenis', 'format' => $this->reset($kunci),
            default => null,
        };
        $this->batas = self::PER_HALAMAN;
    }

    public function hapusSaringan(): void
    {
        $this->reset('cari', 'jenis', 'format', 'dari', 'sampai');
        $this->batas = self::PER_HALAMAN;
    }

    /** Pintasan rentang tanggal: `30hari` (30 hari terakhir sampai hari ini) atau `ta` (1 Januari–31 Desember TA aktif). */
    public function pintasanTanggal(string $pintasan): void
    {
        $hariIni = now()->toImmutable()->setTimezone(config('app.timezone'));
        [$this->dari, $this->sampai] = match ($pintasan) {
            '30hari' => [$hariIni->subDays(29)->format('Y-m-d'), $hariIni->format('Y-m-d')],
            'ta' => [StubData::tahunAktif()['tahun'].'-01-01', StubData::tahunAktif()['tahun'].'-12-31'],
            default => [$this->dari, $this->sampai],
        };
        $this->batas = self::PER_HALAMAN;
    }

    /**
     * Isi tampilan aktif: [kelompok per tahun (dipotong `$batas`), jumlah butir seluruhnya].
     *
     * @return array{0: array<int, list<array<string, mixed>>>, 1: int}
     */
    public function isi(): array
    {
        return $this->penelusuran->halaman($this->tampilan, $this->urut, $this->batas);
    }

    /** @return array<string, mixed> Langkah berikutnya selama belum ada dokumen tercatat (RiwayatKosong). */
    #[Computed]
    public function kosong(): array
    {
        return RiwayatKosong::untuk(StubData::currentUser());
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
