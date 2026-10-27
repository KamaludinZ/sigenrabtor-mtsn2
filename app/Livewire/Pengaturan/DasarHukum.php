<?php

namespace App\Livewire\Pengaturan;

use App\Contracts\DasarHukumRepository;
use App\Contracts\KegiatanRepository;
use App\Contracts\TahunAnggaranRepository;
use App\Support\Pengaturan\DaftarDasarHukum;
use App\Support\Pengaturan\KesiapanPengaturan;
use App\Support\Pengaturan\LatarBelakangBaku;
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
 * hukum ditambah/diubah/dihapus (bisa diurungkan) dan diurutkan (seret atau naik/turun) satu per satu dan langsung
 * tersimpan (aturan & tanda baca cetak di DaftarDasarHukum); paragraf baku & kalimat penutup disunting bersama di editor
 * berpratinjau (aturan LatarBelakangBaku). Tahun terkunci hanya dilihat.
 */
#[Layout('layouts.app')]
class DasarHukum extends Component
{
    #[Url(as: 'ta', except: '')]
    public string $tahun = '';

    /** Butir yang sedang disunting: id butir, 'baru' (tambah), atau null. */
    public ?string $suntingButir = null;

    public string $teksButir = '';

    /** @var array{tahun: int, posisi: int, butir: array{id: string, teks: string}}|null Butir yang baru dihapus (bisa diurungkan). */
    public ?array $butirTerhapus = null;

    /** Pengumuman pembaca layar setelah butir dipindah. */
    public string $pengumuman = '';

    /** Editor latar belakang baku terbuka. */
    public bool $suntingLatar = false;

    /** @var array{latar_belakang: string, kalimat_penutup: string} Teks mentah (berpenanda) yang sedang disunting. */
    public array $isianLatar = ['latar_belakang' => '', 'kalimat_penutup' => ''];

    public function mount(): void
    {
        $this->rapikanTahun();
    }

    public function updatedTahun(): void
    {
        $this->rapikanTahun();
        $this->batalButir();
        $this->batalLatar();
        $this->butirTerhapus = null;
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
        $this->butirTerhapus = null;
        $this->suntingButir = 'baru';
    }

    public function ubahButir(string $id): void
    {
        $butir = collect($this->pengaturan['dasar_hukum'])->firstWhere('id', $id);
        if ($butir === null || $this->tahunTerkunci()) {
            return;
        }

        $this->batalButir();
        $this->butirTerhapus = null;
        $this->suntingButir = $id;
        $this->teksButir = DaftarDasarHukum::rapikan($butir['teks']);
    }

    public function batalButir(): void
    {
        $this->reset('suntingButir', 'teksButir');
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

    /** Hapus langsung; bilah "Urungkan" mengembalikannya ke posisi semula. */
    public function hapusButir(string $id, DasarHukumRepository $repo): void
    {
        $daftar = $this->pengaturan['dasar_hukum'];
        $i = array_search($id, array_column($daftar, 'id'), true);
        if ($i === false || $this->tahunTerkunci()) {
            return;
        }

        [$butir] = array_splice($daftar, $i, 1);
        $this->simpanDaftar($repo, $daftar, 'hapus butir '.($i + 1).': '.Str::limit(DaftarDasarHukum::rapikan($butir['teks']), 120));
        $this->butirTerhapus = ['tahun' => $this->tahunDilihat(), 'posisi' => $i, 'butir' => $butir];
    }

    public function urungkanHapusButir(DasarHukumRepository $repo): void
    {
        $terhapus = $this->butirTerhapus;
        $this->butirTerhapus = null;
        $daftar = $this->pengaturan['dasar_hukum'];
        if ($terhapus === null || $terhapus['tahun'] !== $this->tahunDilihat() || $this->tahunTerkunci()
            || in_array($terhapus['butir']['id'], array_column($daftar, 'id'), true) || count($daftar) >= DaftarDasarHukum::MAKS_BUTIR) {
            return;
        }

        $posisi = min($terhapus['posisi'], count($daftar));
        array_splice($daftar, $posisi, 0, [$terhapus['butir']]);
        $this->simpanDaftar($repo, $daftar, 'kembalikan butir '.($posisi + 1).': '.Str::limit(DaftarDasarHukum::rapikan($terhapus['butir']['teks']), 120));
        $this->dispatch('notify', message: 'Butir '.($posisi + 1).' dikembalikan.', type: 'success');
    }

    public function ubahLatar(): void
    {
        if ($this->tahunTerkunci()) {
            return;
        }

        $this->batalLatar();
        $this->isianLatar = ['latar_belakang' => $this->pengaturan['latar_belakang'], 'kalimat_penutup' => $this->pengaturan['kalimat_penutup']];
        $this->suntingLatar = true;
    }

    public function batalLatar(): void
    {
        $this->reset('suntingLatar', 'isianLatar');
        $this->resetErrorBag(['isianLatar.latar_belakang', 'isianLatar.kalimat_penutup']);
    }

    /** Isi editor dengan teks bawaan (belum tersimpan sampai Simpan). */
    public function pakaiBawaanLatar(DasarHukumRepository $repo): void
    {
        if (! $this->suntingLatar) {
            return;
        }

        $this->isianLatar = $repo->teksBawaan();
        $this->resetErrorBag(['isianLatar.latar_belakang', 'isianLatar.kalimat_penutup']);
    }

    public function simpanLatar(DasarHukumRepository $repo): void
    {
        if (! $this->suntingLatar || $this->tahunTerkunci()) {
            return;
        }

        $this->resetErrorBag(['isianLatar.latar_belakang', 'isianLatar.kalimat_penutup']);
        $this->isianLatar = [
            'latar_belakang' => LatarBelakangBaku::rapikan((string) ($this->isianLatar['latar_belakang'] ?? '')),
            'kalimat_penutup' => LatarBelakangBaku::rapikan((string) ($this->isianLatar['kalimat_penutup'] ?? '')),
        ];
        $galat = array_filter([
            'isianLatar.latar_belakang' => LatarBelakangBaku::galatParagraf($this->isianLatar['latar_belakang']),
            'isianLatar.kalimat_penutup' => LatarBelakangBaku::galatPenutup($this->isianLatar['kalimat_penutup']),
        ]);
        foreach ($galat as $kolom => $pesan) {
            $this->addError($kolom, $pesan);
        }
        if ($galat !== []) {
            return;
        }

        $lama = $this->pengaturan;
        $berubah = array_keys(array_filter([
            'paragraf baku' => $this->isianLatar['latar_belakang'] !== $lama['latar_belakang'],
            'kalimat penutup' => $this->isianLatar['kalimat_penutup'] !== $lama['kalimat_penutup'],
        ]));
        if ($berubah === []) {
            $this->batalLatar();
            $this->dispatch('notify', message: 'Tidak ada yang berubah.', type: 'info');

            return;
        }

        $tahun = $this->tahunDilihat();
        $repo->simpan($tahun, $this->isianLatar + ['dasar_hukum' => $lama['dasar_hukum']]);
        $keterangan = "Latar belakang TA {$tahun} — ubah ".implode(' & ', $berubah);
        if (in_array('kalimat penutup', $berubah, true)) {
            $keterangan .= ': '.Str::limit($this->isianLatar['kalimat_penutup'], 120);
        }
        RiwayatPengaturan::catat('ubah_latar_belakang', $keterangan);

        unset($this->pengaturan, $this->dasarHukum, $this->latarBelakang);
        $this->batalLatar();
        $this->dispatch('notify', message: "Latar belakang baku TA {$tahun} tersimpan.", type: 'success');
    }

    /** Tombol naik/turun: geser satu posisi ($arah -1/1), fokus tetap di tombol yang sama. */
    public function geserButir(string $id, int $arah, DasarHukumRepository $repo): void
    {
        $dari = array_search($id, array_column($this->pengaturan['dasar_hukum'], 'id'), true);
        if ($dari !== false && $this->pindah($repo, $dari, $dari + ($arah > 0 ? 1 : -1))) {
            $this->dispatch('butir-dipindah', id: $id, arah: $arah > 0 ? 'turun' : 'naik');
        }
    }

    /** Handler seret-lepas (wire:sort): $id butir yang dipindah, $posisi indeks tujuannya. */
    public function pindahkanButir(string $id, int $posisi, DasarHukumRepository $repo): void
    {
        $dari = array_search($id, array_column($this->pengaturan['dasar_hukum'], 'id'), true);
        if ($dari !== false) {
            $this->pindah($repo, $dari, $posisi);
        }
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

    /** Pindahkan butir indeks $dari ke $ke lalu simpan; false bila tidak ada yang berpindah. */
    private function pindah(DasarHukumRepository $repo, int $dari, int $ke): bool
    {
        $daftar = $this->pengaturan['dasar_hukum'];
        $ke = max(0, min($ke, count($daftar) - 1));
        if ($dari === $ke || ! isset($daftar[$dari]) || $this->tahunTerkunci() || $this->suntingButir !== null) {
            return false;
        }

        $this->butirTerhapus = null;
        [$butir] = array_splice($daftar, $dari, 1);
        array_splice($daftar, $ke, 0, [$butir]);
        $this->simpanDaftar($repo, $daftar, 'pindah butir '.($dari + 1).' ke '.($ke + 1).': '.Str::limit(DaftarDasarHukum::rapikan($butir['teks']), 120));
        $this->pengumuman = 'Butir '.($dari + 1).' dipindah ke nomor '.($ke + 1).'.';

        return true;
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
