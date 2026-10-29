<?php

namespace App\Livewire\Cetak;

use App\Contracts\KegiatanRepository;
use App\Support\Dokumen\CetakDokumen;
use App\Support\Dokumen\CoverDokumen;
use App\Support\Dokumen\DaftarCetak;
use App\Support\Dokumen\StatusCetak;
use App\Support\Dokumen\SusunanKak;
use App\Support\Dokumen\SusunanRab;
use App\Support\Dokumen\TeksCover;
use App\Support\Stub\StubData;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Halaman Cetak & Unduh (PRD §6 "Cetak per Kegiatan", F12, F16; `/cetak`, rute `cetak.index`, semua pengguna): daftar
 * dokumen tahun aktif per kegiatan × sumber dana dalam cakupan akun (DaftarCetak — keadaan siap/DRAF, nama berkas, lembar)
 * dengan saringan di URL (`cari`, `leading`, `sumber`, `keadaan`), lalu panel dokumen terpilih (`pilih` = kegiatan|sumber,
 * bawaan baris pertama hasil saringan): pilihan lembar (`dokumen`, CetakDokumen::pilihan), pratinjau kecil tiap lembar
 * (komponen dokumen yang sama dengan halaman cetak), alasan DRAF + cara menghilangkannya (StatusCetak::saran), dan aksi —
 * buka halaman cetak (`kegiatan.cetak`, dialog cetak otomatis), pratinjau lengkap, unduh RAB Excel/PDF selama RAB berisi;
 * Word masih Segera.
 */
#[Layout('layouts.app')]
class Daftar extends Component
{
    #[Url(except: '')]
    public string $cari = '';

    #[Url(except: '')]
    public string $leading = '';

    #[Url(except: '')]
    public string $sumber = '';

    #[Url(except: '')]
    public string $keadaan = '';

    /** Dokumen terpilih: "{kegiatan}|{sumber}" (DaftarCetak `kunci`). */
    #[Url(as: 'pilih', except: '')]
    public string $pilihan = '';

    /** Lembar yang dicetak, dipisah koma (kosong = semua yang tersedia). */
    #[Url(as: 'dokumen', except: '')]
    public string $lembar = '';

    public function mount(): void
    {
        if (! array_key_exists($this->keadaan, DaftarCetak::KEADAAN)) {
            $this->keadaan = '';
        }
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function semua(): array
    {
        return DaftarCetak::baris(StubData::currentUser());
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function baris(): array
    {
        return DaftarCetak::saring($this->semua, ['cari' => $this->cari, 'leading' => $this->leading, 'sumber' => $this->sumber, 'keadaan' => $this->keadaan]);
    }

    /** @return array<string, mixed>|null Baris terpilih (di luar hasil saringan tetap boleh selama dalam cakupan). */
    #[Computed]
    public function terpilih(): ?array
    {
        return collect($this->semua)->firstWhere('kunci', $this->pilihan) ?? ($this->baris[0] ?? null);
    }

    /** @return list<string> Lembar terpilih yang tersedia untuk dokumen terpilih. */
    public function lembarTerpilih(): array
    {
        $tersedia = $this->terpilih['lembar'] ?? array_keys(CetakDokumen::LEMBAR);
        $diminta = array_values(array_intersect(CetakDokumen::pilihan($this->lembar), $tersedia));

        return $diminta === [] ? $tersedia : $diminta;
    }

    /**
     * Susunan dokumen terpilih untuk panel pratinjau.
     *
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function dokumen(): ?array
    {
        $baris = $this->terpilih;
        $kegiatan = $baris ? app(KegiatanRepository::class)->temukan($baris['kegiatan_id']) : null;
        if ($kegiatan === null) {
            return null;
        }
        $kak = SusunanKak::dari($kegiatan, $baris['sumber']);
        $rab = SusunanRab::dari($kegiatan, $baris['sumber']);
        $cetak = StatusCetak::dari($kegiatan, $baris['sumber'], $kak, $rab);
        $lembar = $this->lembarTerpilih();
        $pratinjau = route('kegiatan.pratinjau', array_filter(['kegiatan' => $baris['kegiatan_id'], 'sumber' => $baris['sumber']]));

        return [
            'baris' => $baris,
            'kak' => $kak,
            'rab' => $rab,
            'cetak' => $cetak,
            'cover' => CoverDokumen::untukKegiatan($kegiatan, $baris['sumber']) + ['nilai' => TeksCover::nilai($kegiatan, $baris['sumber'])],
            'lembar' => $lembar,
            'nama_berkas' => CetakDokumen::namaBerkas($cetak, $lembar),
            'tautan_cetak' => CetakDokumen::tautan($baris['kegiatan_id'], $baris['sumber'], $lembar, otomatis: true),
            'pratinjau' => $pratinjau,
            'saran' => array_map(fn (array $s) => ['tautan' => str_starts_with($s['tautan'], '#') ? $pratinjau.$s['tautan'] : $s['tautan']] + $s, $cetak->saran()),
        ];
    }

    public function pilih(string $kunci): void
    {
        if (collect($this->semua)->contains('kunci', $kunci)) {
            $this->pilihan = $kunci;
            $this->lembar = '';
            unset($this->terpilih, $this->dokumen);
            $this->dispatch('dokumen-cetak-dipilih');
        }
    }

    /** Hidupkan/matikan satu lembar; minimal satu lembar tetap terpilih. */
    public function aturLembar(string $lembar): void
    {
        $sekarang = $this->lembarTerpilih();
        $berikut = in_array($lembar, $sekarang, true) ? array_values(array_diff($sekarang, [$lembar])) : [...$sekarang, $lembar];
        // pilihan([]) berarti semua lembar — lembar terakhir tidak bisa dilepas.
        $berikut = $berikut === [] ? [] : array_values(array_intersect(CetakDokumen::pilihan($berikut), $this->terpilih['lembar'] ?? []));
        if ($berikut !== []) {
            $this->lembar = $berikut === ($this->terpilih['lembar'] ?? []) ? '' : implode(',', $berikut);
            unset($this->dokumen);
        }
    }

    public function hapusSaringan(): void
    {
        $this->reset('cari', 'leading', 'sumber', 'keadaan');
        unset($this->baris, $this->terpilih, $this->dokumen);
    }

    public function updated(string $properti): void
    {
        if (in_array($properti, ['cari', 'leading', 'sumber', 'keadaan'], true)) {
            unset($this->baris, $this->terpilih, $this->dokumen);
        }
    }

    public function render()
    {
        return view('livewire.cetak.daftar')->title('Cetak & Unduh');
    }
}
