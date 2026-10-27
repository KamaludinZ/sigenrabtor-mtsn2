<?php

namespace App\Livewire\Buku;

use App\Contracts\BukuDokumenRepository;
use App\Contracts\KegiatanRepository;
use App\Contracts\PembuatPdfBuku;
use App\Enums\SumberDana;
use App\Support\Dokumen\BukuDokumen;
use App\Support\Dokumen\PengambilanBuku;
use App\Support\Dokumen\ProsesPdfBuku;
use App\Support\Stub\StubData;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Buku Dokumen (PRD §18, F19): pilih leading & sumber dana (`?leading=&sumber=`; kosong = buku pertama yang berisi
 * kegiatan) lalu lihat susunan bukunya — cover buku, daftar kegiatan urut kode RKTM dengan halaman awal, status & cap
 * DRAF tiap kegiatan — serta peta semua buku tahun aktif. Akun leading hanya melihat buku leading-nya. "Susun buku"
 * mengambil kegiatan bertahap (PengambilanBuku, dipanggil berulang dari klien) lalu mencatatnya di riwayat
 * (BukuDokumenRepository); dokumen satu kegiatan bisa dilihat di dalam buku (`?kegiatan=`, <x-dokumen.kegiatan-buku>).
 * "Gabung PDF" memulai pembuatan PDF (PembuatPdfBuku) yang dipantau berkala sampai selesai (unduh) atau gagal (coba lagi).
 */
#[Layout('layouts.app')]
class Daftar extends Component
{
    #[Url(except: '')]
    public string $leading = '';

    #[Url(except: '')]
    public string $sumber = '';

    /** Kegiatan yang dokumennya sedang dilihat di dalam buku (0 = tidak ada). */
    #[Url(as: 'kegiatan', except: 0)]
    public int $lihat = 0;

    /**
     * Penyusunan buku yang sedang/selesai berjalan (simulasi pengambilan kegiatan; dikelola server).
     *
     * @var array{leading: string, sumber: string, antrean: list<int>, halaman: list<int|null>, total_halaman: int, posisi: int, hasil: list<array<string, mixed>>, tahap: string}|null
     */
    #[Locked]
    public ?array $proses = null;

    public function mount(): void
    {
        $this->rapikan();
    }

    public function updatedLeading(): void
    {
        $this->rapikan();
    }

    public function updatedSumber(): void
    {
        $this->rapikan();
    }

    public function updatedLihat(): void
    {
        $this->rapikan();
    }

    public function pilih(string $leading, string $sumber): void
    {
        $this->leading = $leading;
        $this->sumber = $sumber;
        $this->rapikan();
    }

    /** Mulai membuat PDF buku yang dipilih (PembuatPdfBuku); statusnya dipantau lewat pantauPdf(). */
    public function gabungPdf(): void
    {
        if ($this->isi === null || $this->isi['baris'] === [] || ProsesPdfBuku::berjalan($this->pekerjaanPdf['pdf'] ?? null)) {
            return;
        }

        app(PembuatPdfBuku::class)->mulai([
            'tahun' => StubData::tahunAktif()['tahun'],
            'leading' => $this->terpilih['leading'],
            'sumber' => $this->terpilih['sumber'],
            'jumlah_kegiatan' => count($this->isi['baris']),
            'halaman' => $this->isi['simulasi']['terakhir'],
            'ada_draf' => collect($this->isi['baris'])->contains('draf', true),
            'kegiatan' => array_map(fn (array $b) => ['id' => $b['id'], 'nama' => $b['nama_kegiatan'], 'halaman' => $b['halaman'], 'hasil' => $b['draf'] ? 'draf' : 'siap'], $this->isi['baris']),
            'dibuat_oleh' => StubData::currentUser()['name'],
        ]);
        unset($this->riwayat, $this->pekerjaanPdf);
    }

    /** Pantau pembuatan PDF yang sedang berjalan (dipanggil berkala selama berjalan). */
    public function pantauPdf(): void
    {
        $pekerjaan = $this->pekerjaanPdf;
        if (! ProsesPdfBuku::berjalan($pekerjaan['pdf'] ?? null)) {
            return;
        }

        $hasil = app(PembuatPdfBuku::class)->pantau($pekerjaan['id']);
        unset($this->riwayat, $this->pekerjaanPdf);

        match ($hasil['pdf']['status']) {
            'selesai' => $this->dispatch('notify', message: 'PDF buku siap diunduh.', type: 'success'),
            'gagal' => $this->dispatch('notify', message: 'Pembuatan PDF gagal: '.$hasil['pdf']['pesan'], type: 'error'),
            default => null,
        };
    }

    /**
     * Pembuatan PDF terakhir buku yang ditampilkan (catatan riwayat yang punya status `pdf`), null bila belum pernah.
     *
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function pekerjaanPdf(): ?array
    {
        return collect($this->riwayat)->first(fn (array $b) => isset($b['pdf']));
    }

    /** Lihat dokumen satu kegiatan di dalam buku ini (urutan & halaman buku). */
    public function lihatKegiatan(int $id): void
    {
        $this->lihat = $id;
        $this->rapikan();
    }

    public function tutupLihat(): void
    {
        $this->lihat = 0;
    }

    /**
     * Kegiatan yang sedang dilihat: data, urutan di buku, rentang halaman, dan tetangganya.
     *
     * @return array{data: array<string, mixed>, indeks: int, jumlah: int, rentang: array<string, mixed>, sebelum: int|null, sesudah: int|null}|null
     */
    #[Computed]
    public function dilihat(): ?array
    {
        $id = array_column($this->isi['baris'] ?? [], 'id');
        $i = array_search($this->lihat, $id, true);
        if ($i === false) {
            return null;
        }

        return [
            'data' => array_column($this->kegiatan, null, 'id')[$this->lihat],
            'indeks' => $i,
            'jumlah' => count($id),
            'rentang' => BukuDokumen::rentangKegiatan($this->isi['simulasi'], $i),
            'sebelum' => $id[$i - 1] ?? null,
            'sesudah' => $id[$i + 1] ?? null,
        ];
    }

    /** Mulai menyusun buku yang dipilih: kegiatan diambil bertahap lewat lanjutkanSusun(). */
    public function susun(): void
    {
        if ($this->isi === null || $this->isi['baris'] === [] || ($this->proses['tahap'] ?? null) === 'ambil') {
            return;
        }

        $this->proses = [
            'leading' => $this->terpilih['leading'],
            'sumber' => $this->terpilih['sumber'],
            'antrean' => array_column($this->isi['baris'], 'id'),
            'halaman' => array_column($this->isi['baris'], 'halaman'),
            'total_halaman' => $this->isi['simulasi']['terakhir'],
            'posisi' => 0,
            'hasil' => [],
            'tahap' => 'ambil',
        ];
    }

    /** Ambil kegiatan berikutnya; true selama masih ada yang tersisa (dipanggil berulang dari klien). */
    public function lanjutkanSusun(): bool
    {
        if (($this->proses['tahap'] ?? null) !== 'ambil') {
            return false;
        }

        $perId = array_column($this->kegiatan, null, 'id');
        for ($n = 0; $n < PengambilanBuku::PER_LANGKAH && $this->proses['posisi'] < count($this->proses['antrean']); $n++) {
            $i = $this->proses['posisi']++;
            $id = $this->proses['antrean'][$i];
            $this->proses['hasil'][] = isset($perId[$id])
                ? PengambilanBuku::ambil($perId[$id], $this->proses['sumber'], $this->proses['halaman'][$i])
                : ['id' => $id, 'nama' => "Kegiatan #{$id}", 'halaman' => null, 'hasil' => 'gagal', 'pesan' => 'Kegiatan tidak ditemukan lagi — mungkin dihapus atau pindah tahun.'];
        }

        if ($this->proses['posisi'] < count($this->proses['antrean'])) {
            return true;
        }

        $this->selesaiSusun();

        return false;
    }

    public function hentikanSusun(): void
    {
        if (($this->proses['tahap'] ?? null) === 'ambil') {
            $this->proses['tahap'] = 'dihentikan';
        }
    }

    public function tutupSusun(): void
    {
        $this->proses = null;
    }

    /**
     * Riwayat penyusunan buku yang sedang ditampilkan, terbaru dulu.
     *
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function riwayat(): array
    {
        $terpilih = $this->terpilih;

        return $terpilih === null ? [] : array_values(array_filter(
            app(BukuDokumenRepository::class)->riwayat(StubData::tahunAktif()['tahun']),
            fn (array $b) => $b['leading'] === $terpilih['leading'] && $b['sumber'] === $terpilih['sumber'],
        ));
    }

    private function selesaiSusun(): void
    {
        $hasil = $this->proses['hasil'];
        $draf = count(array_filter($hasil, fn (array $h) => $h['hasil'] !== 'siap'));
        $this->proses['tahap'] = 'selesai';

        app(BukuDokumenRepository::class)->catat([
            'tahun' => StubData::tahunAktif()['tahun'],
            'leading' => $this->proses['leading'],
            'sumber' => $this->proses['sumber'],
            'jumlah_kegiatan' => count($hasil),
            'halaman' => $this->proses['total_halaman'],
            'ada_draf' => $draf > 0,
            'kegiatan' => array_map(fn (array $h) => array_intersect_key($h, array_flip(['id', 'nama', 'halaman', 'hasil'])), $hasil),
            'dibuat_oleh' => StubData::currentUser()['name'],
        ]);
        unset($this->riwayat);

        $this->dispatch('notify', message: 'Buku tersusun: '.count($hasil).' kegiatan'.($draf > 0 ? ", {$draf} masih DRAF" : ', semua siap dicetak').'. Berkas PDF digabung setelah mesin dokumen tersedia.', type: $draf > 0 ? 'info' : 'success');
    }

    /** @return list<array<string, mixed>> Kegiatan tahun aktif. */
    #[Computed]
    public function kegiatan(): array
    {
        return BukuDokumen::tahunAktif(app(KegiatanRepository::class)->semua());
    }

    /** @return list<array{kode: string, nama_unit: string, is_active: bool}> */
    #[Computed]
    public function leadings(): array
    {
        return BukuDokumen::leadingUntuk(StubData::currentUser(), $this->kegiatan);
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function peta(): array
    {
        return BukuDokumen::peta($this->leadings, $this->kegiatan);
    }

    /**
     * Buku yang ditampilkan: pilihan di URL, atau buku pertama yang berisi kegiatan (lalu buku pertama).
     *
     * @return array{leading: string, sumber: string}|null
     */
    #[Computed]
    public function terpilih(): ?array
    {
        if ($this->peta === []) {
            return null;
        }

        $cocok = collect($this->peta)->filter(fn (array $b) => ($this->leading === '' || $b['leading'] === $this->leading) && ($this->sumber === '' || $b['sumber'] === $this->sumber));
        $buku = $cocok->firstWhere('jumlah', '>', 0) ?? $cocok->first() ?? $this->peta[0];

        return ['leading' => $buku['leading'], 'sumber' => $buku['sumber']];
    }

    /** @return array<string, mixed>|null */
    #[Computed]
    public function isi(): ?array
    {
        return $this->terpilih ? BukuDokumen::isi($this->kegiatan, $this->terpilih['leading'], $this->terpilih['sumber']) : null;
    }

    private function rapikan(): void
    {
        if ($this->leading !== '' && ! in_array($this->leading, array_column($this->leadings, 'kode'), true)) {
            $this->leading = '';
        }
        if ($this->sumber !== '' && SumberDana::tryFrom($this->sumber) === null) {
            $this->sumber = '';
        }
        unset($this->terpilih, $this->isi, $this->riwayat, $this->dilihat, $this->pekerjaanPdf);
        if ($this->lihat !== 0 && $this->dilihat === null) {
            $this->lihat = 0;
        }
        // Ganti buku: hasil penyusunan buku lain tidak ditampilkan lagi.
        if ($this->proses && ($this->terpilih === null || $this->proses['leading'] !== $this->terpilih['leading'] || $this->proses['sumber'] !== $this->terpilih['sumber'])) {
            $this->proses = null;
        }
    }

    public function render()
    {
        return view('livewire.buku.daftar')->title('Buku Dokumen');
    }
}
