<?php

namespace App\Livewire\Pengaturan;

use App\Contracts\DaftarIsiRepository;
use App\Contracts\KegiatanRepository;
use App\Enums\SumberDana;
use App\Support\Dokumen\DaftarIsi;
use App\Support\MasterKode;
use App\Support\Pengaturan\RiwayatPengaturan;
use App\Support\Rab\AnggaranKegiatan;
use App\Support\Stub\StubData;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Tab Daftar Isi di Pengaturan › Template Dokumen: konfigurasi halaman "Daftar Kegiatan" — bawaan untuk semua buku dan
 * pengaturan khusus per leading × sumber dana — dengan pratinjau A4 berisi kegiatan sebenarnya dari buku itu.
 */
class TemplateDaftarIsi extends Component
{
    /** Buku yang sedang diatur; keduanya null = pengaturan bawaan. */
    #[Locked]
    public ?string $leading = null;

    #[Locked]
    public ?string $sumber = null;

    /**
     * Isian editor (dirapikan saat pratinjau & disimpan).
     *
     * @var array<string, mixed>
     */
    public array $konfigurasi = [];

    public function mount(): void
    {
        $this->pilih(null, null);
    }

    public function pilih(?string $leading, ?string $sumber): void
    {
        $bawaan = $leading === null && $sumber === null;
        $dikenal = $leading !== null && MasterKode::cari(MasterKode::leadings(), $leading) && in_array($sumber, SumberDana::urutan(), true);

        if (! $bawaan && ! $dikenal) {
            return;
        }

        [$this->leading, $this->sumber] = [$leading, $sumber];
        $this->konfigurasi = $this->tersimpan();
        $this->resetValidation();
    }

    public function simpan(DaftarIsiRepository $repo): void
    {
        $konfigurasi = DaftarIsi::rapikan($this->konfigurasi);
        $perubahan = RiwayatPengaturan::perubahan(self::tampilan($this->tersimpan()), self::tampilan($konfigurasi), [
            'judul' => 'Judul', 'subjudul' => 'Subjudul', 'kolom' => 'Kolom', 'urutan' => 'Urutan', 'tampilkan_jumlah' => 'Baris jumlah', 'catatan' => 'Catatan',
        ]);

        if ($this->leading === null) {
            $repo->simpanBawaan($this->tahun(), $konfigurasi);
            $pesan = 'Pengaturan bawaan daftar isi tersimpan.';
            RiwayatPengaturan::catat('ubah_daftar_isi', 'Pengaturan bawaan daftar isi diubah'.($perubahan ? " — {$perubahan}" : '').'.');
        } else {
            $repo->simpanKhusus($this->tahun(), $this->leading, $this->sumber, $konfigurasi);
            $pesan = "Daftar isi {$this->namaBuku()} tersimpan.";
            RiwayatPengaturan::catat('ubah_daftar_isi', "Pengaturan khusus daftar isi {$this->namaBuku()} disimpan".($perubahan ? " — {$perubahan}" : ' (sama dengan bawaan)').'.');
        }

        $this->konfigurasi = $konfigurasi;
        unset($this->khusus);
        $this->dispatch('notify', message: $pesan, type: 'success');
    }

    /** Buku ini kembali memakai pengaturan bawaan (pengaturan khususnya dihapus). */
    public function kembalikanBawaan(DaftarIsiRepository $repo): void
    {
        if ($this->leading === null) {
            return;
        }

        $repo->hapusKhusus($this->tahun(), $this->leading, $this->sumber);
        RiwayatPengaturan::catat('hapus_daftar_isi_khusus', "Daftar isi {$this->namaBuku()} kembali memakai pengaturan bawaan.");
        unset($this->khusus);
        $this->konfigurasi = $this->tersimpan();
        $this->dispatch('notify', message: "Daftar isi {$this->namaBuku()} kembali memakai pengaturan bawaan.", type: 'info');
    }

    public function batalkan(): void
    {
        $this->konfigurasi = $this->tersimpan();
    }

    /** Isi editor dengan susunan bawaan aplikasi (belum disimpan). */
    public function pakaiSusunanAplikasi(): void
    {
        $this->konfigurasi = DaftarIsi::BAWAAN;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    #[Computed]
    public function khusus(): array
    {
        return app(DaftarIsiRepository::class)->khusus($this->tahun());
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function semuaKegiatan(): array
    {
        return app(KegiatanRepository::class)->semua();
    }

    /**
     * Peta buku: tiap leading × sumber dana beserta jumlah kegiatan dan apakah memakai pengaturan khusus.
     *
     * @return list<array{kode: string, nama_unit: string, buku: array<string, array{jumlah: int, khusus: bool}>}>
     */
    #[Computed]
    public function peta(): array
    {
        $jumlah = [];
        foreach ($this->semuaKegiatan as $kegiatan) {
            foreach (array_keys(AnggaranKegiatan::perSumber($kegiatan)) as $s) {
                $kunci = ($kegiatan['leading'] ?? '').'|'.$s;
                $jumlah[$kunci] = ($jumlah[$kunci] ?? 0) + 1;
            }
        }

        return array_map(fn (array $l) => [
            'kode' => $l['kode'],
            'nama_unit' => $l['nama_unit'],
            'buku' => collect(SumberDana::urutan())->mapWithKeys(fn (string $s) => [$s => [
                'jumlah' => $jumlah["{$l['kode']}|{$s}"] ?? 0,
                'khusus' => isset($this->khusus["{$l['kode']}|{$s}"]),
            ]])->all(),
        ], MasterKode::leadings());
    }

    /**
     * Buku yang dipratinjau: buku terpilih, atau untuk pengaturan bawaan buku pertama yang berisi kegiatan.
     *
     * @return array{leading: string, sumber: string}
     */
    #[Computed]
    public function bukuPratinjau(): array
    {
        if ($this->leading !== null) {
            return ['leading' => $this->leading, 'sumber' => $this->sumber];
        }

        foreach ($this->peta as $baris) {
            foreach ($baris['buku'] as $sumber => $buku) {
                if ($buku['jumlah'] > 0) {
                    return ['leading' => $baris['kode'], 'sumber' => $sumber];
                }
            }
        }

        return ['leading' => MasterKode::leadings()[0]['kode'], 'sumber' => SumberDana::urutan()[0]];
    }

    /**
     * @return array{judul: string, subjudul: string, kolom: list<string>, baris: list<array<string, mixed>>, jumlah: int|null, catatan: string}
     */
    #[Computed]
    public function pratinjau(): array
    {
        $k = DaftarIsi::rapikan($this->konfigurasi);
        ['leading' => $leading, 'sumber' => $sumber] = $this->bukuPratinjau;
        $baris = DaftarIsi::baris($this->semuaKegiatan, $leading, $sumber, $k['urutan']);

        return [
            'judul' => $k['judul'],
            'subjudul' => DaftarIsi::subjudul($k['subjudul'], $leading, $sumber),
            'kolom' => $k['kolom'],
            'baris' => $baris,
            'jumlah' => $k['tampilkan_jumlah'] ? array_sum(array_column($baris, 'total')) : null,
            'catatan' => $k['catatan'],
        ];
    }

    public function berubah(): bool
    {
        return DaftarIsi::rapikan($this->konfigurasi) !== $this->tersimpan();
    }

    public function adaKhusus(): bool
    {
        return $this->leading !== null && isset($this->khusus["{$this->leading}|{$this->sumber}"]);
    }

    public function namaBuku(?string $leading = null, ?string $sumber = null): string
    {
        $leading ??= $this->leading;
        $sumber ??= $this->sumber;

        return (MasterKode::cari(MasterKode::leadings(), $leading)['nama_unit'] ?? $leading).' · '.$sumber;
    }

    public function tahun(): int
    {
        return StubData::tahunAktif()['tahun'];
    }

    public function render()
    {
        return view('livewire.pengaturan.template-daftar-isi');
    }

    /**
     * Konfigurasi dengan label terbaca (kolom & urutan) untuk ringkasan riwayat.
     *
     * @param  array<string, mixed>  $konfigurasi
     * @return array<string, mixed>
     */
    private static function tampilan(array $konfigurasi): array
    {
        return ['kolom' => array_map(fn (string $k) => DaftarIsi::KOLOM[$k][0], $konfigurasi['kolom']), 'urutan' => DaftarIsi::URUTAN[$konfigurasi['urutan']]] + $konfigurasi;
    }

    /**
     * @return array{judul: string, subjudul: string, kolom: list<string>, urutan: string, tampilkan_jumlah: bool, catatan: string}
     */
    private function tersimpan(): array
    {
        $repo = app(DaftarIsiRepository::class);

        return $this->leading === null
            ? $repo->bawaan($this->tahun())
            : ($repo->khusus($this->tahun())["{$this->leading}|{$this->sumber}"] ?? $repo->bawaan($this->tahun()));
    }
}
