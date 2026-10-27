<?php

namespace App\Livewire\Pengaturan;

use App\Contracts\DasarHukumRepository;
use App\Contracts\KegiatanRepository;
use App\Contracts\TahunAnggaranRepository;
use App\Support\Pengaturan\DaftarDasarHukum;
use App\Support\Pengaturan\KesiapanPengaturan;
use App\Support\Pengaturan\RiwayatPengaturan;
use App\Support\Pengaturan\TeksBaku;
use App\Support\Stub\StubData;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Pengaturan › Profil & Pejabat › Dasar hukum & latar belakang (PRD Fase 6 §13, F9): daftar dasar hukum per tahun
 * anggaran (tercetak bernomor di bagian 2 KAK) dan paragraf latar belakang baku (bagian 1) beserta kalimat penutup yang
 * menyisipkan nama kegiatan, tersimpan lewat DasarHukumRepository (teks mentah, penanda diisi TeksBaku). Tahun yang dilihat
 * di `?ta=` ('' = tahun aktif); pratinjau memakai susunan yang sama dengan dokumen (SusunanKak, KalimatKak). Butir dasar
 * hukum ditambah/diubah/dihapus satu per satu dan langsung tersimpan (aturan & tanda baca cetak di DaftarDasarHukum);
 * tahun terkunci hanya dilihat.
 */
#[Layout('layouts.app')]
class DasarHukum extends Component
{
    #[Url(as: 'ta', except: '')]
    public string $tahun = '';

    /** Butir yang sedang disunting: id butir, 'baru' (tambah), atau null. */
    public ?string $suntingButir = null;

    public string $teksButir = '';

    /** Id butir yang menunggu konfirmasi hapus. */
    public ?string $konfirmasiHapus = null;

    public function mount(): void
    {
        $this->rapikanTahun();
    }

    public function updatedTahun(): void
    {
        $this->rapikanTahun();
        $this->batalButir();
        unset($this->pengaturan, $this->dasarHukum, $this->latarBelakang);
    }

    public function tambahButir(): void
    {
        if ($this->tahunTerkunci()) {
            return;
        }
        if (count($this->pengaturan['dasar_hukum']) >= DaftarDasarHukum::MAKS_BUTIR) {
            $this->dispatch('notify', message: 'Dasar hukum paling banyak '.DaftarDasarHukum::MAKS_BUTIR.' butir.', type: 'error');

            return;
        }

        $this->batalButir();
        $this->suntingButir = 'baru';
    }

    public function ubahButir(string $id): void
    {
        $butir = collect($this->pengaturan['dasar_hukum'])->firstWhere('id', $id);
        if ($butir === null || $this->tahunTerkunci()) {
            return;
        }

        $this->batalButir();
        $this->suntingButir = $id;
        $this->teksButir = DaftarDasarHukum::rapikan($butir['teks']);
    }

    public function batalButir(): void
    {
        $this->reset('suntingButir', 'teksButir', 'konfirmasiHapus');
        $this->resetErrorBag('teksButir');
    }

    public function simpanButir(DasarHukumRepository $repo): void
    {
        $id = $this->suntingButir;
        if ($id === null || $this->tahunTerkunci()) {
            return;
        }

        $this->resetErrorBag('teksButir');
        $daftar = $this->pengaturan['dasar_hukum'];
        $this->teksButir = DaftarDasarHukum::rapikan($this->teksButir);
        $pesan = DaftarDasarHukum::galat($this->teksButir, $daftar, $id === 'baru' ? null : $id);
        if ($pesan !== null) {
            $this->addError('teksButir', $pesan);

            return;
        }

        $tahun = $this->tahunDilihat();
        if ($id === 'baru') {
            $daftar[] = ['id' => null, 'teks' => $this->teksButir];
            $keterangan = 'tambah butir '.count($daftar).': '.Str::limit($this->teksButir, 120);
        } else {
            $i = array_search($id, array_column($daftar, 'id'), true);
            if ($i === false) {
                return;
            }
            $lama = DaftarDasarHukum::rapikan($daftar[$i]['teks']);
            if ($lama === $this->teksButir) {
                $this->batalButir();
                $this->dispatch('notify', message: 'Tidak ada yang berubah.', type: 'info');

                return;
            }
            $daftar[$i]['teks'] = $this->teksButir;
            $keterangan = 'ubah butir '.($i + 1).': '.Str::limit($lama, 60).' → '.Str::limit($this->teksButir, 60);
        }

        $this->simpanDaftar($repo, $daftar, $keterangan);
        $this->dispatch('notify', message: ($id === 'baru' ? 'Butir dasar hukum ditambahkan' : 'Butir dasar hukum diperbarui')." untuk TA {$tahun}.", type: 'success');
    }

    public function mintaHapusButir(string $id): void
    {
        if ($this->tahunTerkunci() || ! in_array($id, array_column($this->pengaturan['dasar_hukum'], 'id'), true)) {
            return;
        }

        $this->batalButir();
        $this->konfirmasiHapus = $id;
    }

    public function hapusButir(DasarHukumRepository $repo): void
    {
        $daftar = $this->pengaturan['dasar_hukum'];
        $i = array_search($this->konfirmasiHapus, array_column($daftar, 'id'), true);
        if ($i === false || $this->tahunTerkunci()) {
            $this->batalButir();

            return;
        }

        $teks = DaftarDasarHukum::rapikan($daftar[$i]['teks']);
        array_splice($daftar, $i, 1);
        $this->simpanDaftar($repo, $daftar, 'hapus butir '.($i + 1).': '.Str::limit($teks, 120));
        $this->dispatch('notify', message: 'Butir '.($i + 1).' dihapus dari dasar hukum TA '.$this->tahunDilihat().'.', type: 'success');
    }

    /**
     * Tahun anggaran terdaftar (terbaru lebih dulu).
     *
     * @return list<array{tahun: int, is_active: bool, is_locked: bool}>
     */
    public function daftarTahun(): array
    {
        return app(TahunAnggaranRepository::class)->semua();
    }

    public function tahunDilihat(): int
    {
        return $this->tahun !== '' ? (int) $this->tahun : StubData::tahunAktif()['tahun'];
    }

    public function tahunTerkunci(): bool
    {
        return (bool) (collect($this->daftarTahun())->firstWhere('tahun', $this->tahunDilihat())['is_locked'] ?? false);
    }

    /**
     * Pengaturan tersimpan tahun yang dilihat (teks mentah).
     *
     * @return array{dasar_hukum: list<array{id: string, teks: string}>, latar_belakang: string, kalimat_penutup: string, diatur: bool, diperbarui_pada: string|null}
     */
    #[Computed]
    public function pengaturan(): array
    {
        return app(DasarHukumRepository::class)->untukTahun($this->tahunDilihat());
    }

    /**
     * Butir dasar hukum tahun yang dilihat seperti tercetak (DaftarDasarHukum::cetak), urut cetak.
     *
     * @return list<array{id: string, teks: string}>
     */
    #[Computed]
    public function dasarHukum(): array
    {
        $butir = $this->pengaturan['dasar_hukum'];
        $cetak = DaftarDasarHukum::cetak(array_column($butir, 'teks'), $this->tahunDilihat());

        return array_map(fn (array $b, string $teks) => ['id' => $b['id'], 'teks' => $teks], $butir, $cetak);
    }

    /** Paragraf latar belakang baku tahun yang dilihat (tahun anggaran sudah tersisip). */
    #[Computed]
    public function latarBelakang(): string
    {
        return TeksBaku::isi($this->pengaturan['latar_belakang'], $this->tahunDilihat());
    }

    /**
     * Kalimat penutup latar belakang per kegiatan tahun yang dilihat, dipecah di tempat sisip nama kegiatan (`sesudah`
     * null bila templatenya tanpa penanda).
     *
     * @return array{sebelum: string, sesudah: string|null}
     */
    public function kalimatSisip(): array
    {
        $bagian = explode(TeksBaku::NAMA_KEGIATAN, TeksBaku::isi($this->pengaturan['kalimat_penutup'], $this->tahunDilihat()), 2);

        return ['sebelum' => $bagian[0], 'sesudah' => $bagian[1] ?? null];
    }

    /** Nama contoh kegiatan untuk pratinjau (kegiatan terbaru), atau contoh baku bila belum ada. */
    public function contohKegiatan(): string
    {
        $kegiatan = collect(app(KegiatanRepository::class)->semua())->first(fn (array $k) => filled($k['nama_kegiatan'] ?? null));

        return $kegiatan['nama_kegiatan'] ?? 'Workshop Penyusunan Kurikulum Madrasah';
    }

    /**
     * Butir kesiapan tahun aktif untuk dasar hukum & latar belakang.
     *
     * @return array{kunci: string, judul: string, siap: bool, wajib: bool, keterangan: string}|null
     */
    public function kesiapan(): ?array
    {
        return collect(KesiapanPengaturan::tahunAktif()->butir)->firstWhere('kunci', 'dasar_hukum');
    }

    /**
     * Simpan daftar butir tahun yang dilihat (paragraf & kalimat penutup tetap), catat riwayat, lalu tutup isian.
     *
     * @param  list<array{id: string|null, teks: string}>  $daftar
     */
    private function simpanDaftar(DasarHukumRepository $repo, array $daftar, string $keterangan): void
    {
        $tahun = $this->tahunDilihat();
        $repo->simpan($tahun, ['dasar_hukum' => $daftar] + $this->pengaturan);
        RiwayatPengaturan::catat('ubah_dasar_hukum', "Dasar hukum TA {$tahun} — {$keterangan}");

        unset($this->pengaturan, $this->dasarHukum);
        $this->batalButir();
    }

    private function rapikanTahun(): void
    {
        $terdaftar = in_array((int) $this->tahun, array_column($this->daftarTahun(), 'tahun'), true);
        if (! $terdaftar || (int) $this->tahun === StubData::tahunAktif()['tahun']) {
            $this->tahun = '';
        }
    }

    public function render()
    {
        return view('livewire.pengaturan.dasar-hukum')->title('Dasar Hukum & Latar Belakang');
    }
}
