<?php

namespace App\Livewire\Kegiatan;

use App\Contracts\DrafDokumenRepository;
use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Support\Dokumen\CoverDokumen;
use App\Support\Dokumen\DrafDokumen;
use App\Support\Dokumen\IsianSusun;
use App\Support\Dokumen\JenisDokumen;
use App\Support\Dokumen\PemeriksaanCetak;
use App\Support\Dokumen\StatusCetak;
use App\Support\Dokumen\StatusDraf;
use App\Support\Dokumen\SusunanKak;
use App\Support\Dokumen\SusunanOtomatis;
use App\Support\Dokumen\SusunanRab;
use App\Support\Dokumen\TeksCover;
use App\Support\Format;
use App\Support\Kegiatan\AksesKegiatan;
use App\Support\Kegiatan\KunciKegiatan;
use App\Support\Kegiatan\TeksKhusus;
use App\Support\Kegiatan\UbahDataKegiatan;
use App\Support\Pengaturan\PengaturanBerubah;
use App\Support\Rab\RincianRab;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Tab ruang kerja "Susun KAK/TOR" (PRD F4 "Buat KAK/TOR Otomatis"): sembilan bagian KAK/TOR disusun otomatis dari data
 * kegiatan, master & pengaturan tahun aktif, dan RAB — tiap bagian dengan status, isian yang kurang, asal isinya, dan
 * cuplikan hasil susunannya (SusunanOtomatis) — beserta kepala RAB otomatis, master template yang dipakai, dan draf
 * dokumen (DrafDokumen: `simpanDraf()`/`hapusDraf()` untuk jenis × sumber yang tampil, keadaan sesuai/berubah sejak
 * draf, draf lain bertautan). Sumber dana di `?sumber=`
 * ('' = sumber pertama); jenis dokumen di `?jenis=` (JenisDokumen, pilihan <x-dokumen.pilih-jenis>) menentukan bagian yang
 * tampil (cover/KAK-TOR/RAB), master template, cap DRAF, dan pemeriksaan cetak (PemeriksaanCetak: nama berkas & butir cek).
 * Isian yang menentukan bagian bisa dilengkapi di tempat (`bukaIsian($nomor)` → `simpanIsian()`, kolom IsianSusun, lewat
 * UbahDataKegiatan dengan versi saat dibuka) dan rincian RAB ditambah dari kartu RAB (`tambahRincian()`, RincianRab) —
 * keduanya hanya selama kegiatan bisa diubah (KunciKegiatan). Tampilan `?tampilan=` (`TAMPILAN`): kartu per bagian
 * atau dokumen utuh hasil susunan (cover, KAK/TOR, RAB jenis terpilih seperti dicetak, cap DRAF sekali).
 */
#[Layout('layouts.app')]
class SusunDokumen extends Component
{
    #[Locked]
    public int $kegiatanId;

    #[Url(except: '')]
    public string $sumber = '';

    #[Url(except: JenisDokumen::BAWAAN)]
    public string $jenis = JenisDokumen::BAWAAN;

    /** Tampilan halaman → label: kartu per bagian (penyusunan) atau dokumen utuh (hasil susunan seperti dicetak). */
    public const TAMPILAN = ['bagian' => 'Per bagian', 'dokumen' => 'Dokumen utuh'];

    #[Url(except: 'bagian')]
    public string $tampilan = 'bagian';

    /** Nomor bagian yang isiannya sedang dilengkapi di tempat; null = tertutup. */
    public ?int $isianTerbuka = null;

    /** @var array<string, string> */
    public array $isian = [];

    /** Versi data kegiatan (UbahDataKegiatan::versi) saat isian dibuka. */
    #[Locked]
    public ?string $versiIsian = null;

    /** Kunci teks baku (TeksKhusus::BIDANG) yang sedang disesuaikan; null = tertutup. */
    public ?string $teksTerbuka = null;

    /** Isian teks yang disesuaikan (daftar: satu butir per baris). */
    public string $teksIsian = '';

    /** @var array{uraian: string, kategori: string, volume: string, satuan: string, harga_satuan: string} */
    public array $rincianBaru = ['uraian' => '', 'kategori' => '', 'volume' => '', 'satuan' => '', 'harga_satuan' => ''];

    public function mount(int $kegiatan): void
    {
        AksesKegiatan::pastikan($kegiatan);
        $this->kegiatanId = $kegiatan;
        $this->updatedSumber();
        $this->updatedTampilan();
    }

    /** Tampilan tak dikenal → per bagian; isian yang sedang dilengkapi ditutup saat berganti tampilan. */
    public function updatedTampilan(): void
    {
        if (! isset(self::TAMPILAN[$this->tampilan])) {
            $this->tampilan = 'bagian';
        }
        $this->isianTerbuka = null;
        $this->teksTerbuka = null;
        $this->resetErrorBag();
    }

    public function updatedSumber(): void
    {
        $this->lupakan();
        if ($this->sumber !== '' && ! in_array($this->sumber, $this->kak->daftarSumber, true)) {
            $this->sumber = '';
            $this->lupakan();
        }
        $this->updatedJenis();
    }

    /** Jenis tak dikenal → bawaan; jenis per sumber dana pada kegiatan tanpa sumber dana → KAK/TOR. */
    public function updatedJenis(): void
    {
        $jenis = JenisDokumen::rapikan($this->jenis);
        $this->jenis = JenisDokumen::perluSumber($jenis) && $this->kak->sumber === null ? JenisDokumen::TANPA_SUMBER : $jenis;
        unset($this->pemeriksaan, $this->pilihanJenis, $this->keadaanDraf);
    }

    /** Kegiatan masih bisa diubah (status Draf/Dikembalikan & tahun anggaran terbuka). */
    public function dapatDiubah(): bool
    {
        return KunciKegiatan::dapatDiubah($this->dataKegiatan);
    }

    public function bukaIsian(int $nomor): void
    {
        abort_unless(IsianSusun::untuk($nomor) !== [], 404);
        abort_unless($this->dapatDiubah(), 403, 'Kegiatan ini terkunci — isiannya hanya bisa dilihat.');
        $this->isian = IsianSusun::isian($this->dataKegiatan, $nomor);
        $this->versiIsian = UbahDataKegiatan::versi($this->dataKegiatan);
        $this->resetErrorBag();
        $this->teksTerbuka = null;
        $this->isianTerbuka = $nomor;
    }

    public function tutupIsian(): void
    {
        $this->isianTerbuka = null;
        $this->resetErrorBag();
    }

    /** Simpan isian bagian yang dibuka; galat tampil di kolomnya, perubahan dari tempat lain memuat ulang isian. */
    public function simpanIsian(): void
    {
        $nomor = $this->isianTerbuka;
        abort_unless($nomor !== null, 404);
        $this->resetErrorBag();

        try {
            $hasil = UbahDataKegiatan::ubah($this->kegiatanId, IsianSusun::kiriman($nomor, $this->isian), StubData::currentUser(), $this->versiIsian);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $kolom => $pesan) {
                $this->addError("isian.{$kolom}", $pesan[0]);
            }

            return;
        } catch (PengaturanBerubah $e) {
            $this->muatUlang();
            $this->bukaIsian($nomor);
            $this->dispatch('notify', message: $e->getMessage(), type: 'error');

            return;
        } catch (AuthorizationException $e) {
            $this->muatUlang();
            $this->isianTerbuka = null;
            $this->dispatch('notify', message: $e->getMessage(), type: 'error');

            return;
        }

        $this->muatUlang();
        $this->isianTerbuka = null;
        $this->dispatch('notify', message: $hasil['diubah'] === [] ? $hasil['pesan'] : 'Bagian '.$nomor.' diperbarui — susunan KAK/TOR mengikuti isian baru.', type: $hasil['diubah'] === [] ? 'info' : 'success');
    }

    /** Buka penyunting satu teks baku: teks tersimpan atau teks baku berpenanda {nama_kegiatan}. */
    public function bukaTeks(string $kunci): void
    {
        abort_unless(isset(TeksKhusus::BIDANG[$kunci]), 404);
        abort_unless($this->dapatDiubah(), 403, 'Kegiatan ini terkunci — teksnya hanya bisa dilihat.');
        $teks = TeksKhusus::dari($this->dataKegiatan)[$kunci] ?? TeksKhusus::bakuMentah($kunci);
        $this->teksIsian = is_array($teks) ? implode("\n", $teks) : $teks;
        $this->versiIsian = UbahDataKegiatan::versi($this->dataKegiatan);
        $this->isianTerbuka = null;
        $this->resetErrorBag();
        $this->teksTerbuka = $kunci;
    }

    public function tutupTeks(): void
    {
        $this->teksTerbuka = null;
        $this->resetErrorBag();
    }

    public function simpanTeks(): void
    {
        abort_unless($this->teksTerbuka !== null, 404);
        $this->simpanTeksKhusus($this->teksTerbuka, $this->teksIsian);
    }

    /** Buang penyesuaian satu teks: kembali ke teks baku. */
    public function kembalikanTeks(string $kunci): void
    {
        abort_unless(isset(TeksKhusus::BIDANG[$kunci]), 404);
        abort_unless($this->dapatDiubah(), 403, 'Kegiatan ini terkunci — teksnya hanya bisa dilihat.');
        $this->simpanTeksKhusus($kunci, null);
    }

    /** Simpan teks khusus kegiatan (teks lain yang sudah disesuaikan tetap) lewat UbahDataKegiatan; null = dibuang. */
    private function simpanTeksKhusus(string $kunci, ?string $teks): void
    {
        $semua = TeksKhusus::dari($this->dataKegiatan);
        if ($teks === null) {
            unset($semua[$kunci]);
        } else {
            $semua[$kunci] = $teks;
        }
        $sunting = $this->teksTerbuka === $kunci;
        $this->resetErrorBag();

        try {
            $hasil = UbahDataKegiatan::ubah($this->kegiatanId, ['teks_khusus' => $semua], StubData::currentUser(), $sunting ? $this->versiIsian : UbahDataKegiatan::versi($this->dataKegiatan));
        } catch (ValidationException $e) {
            $this->addError('teksIsian', collect($e->errors())->flatten()->first());

            return;
        } catch (PengaturanBerubah $e) {
            $this->muatUlang();
            if ($sunting) {
                $this->versiIsian = UbahDataKegiatan::versi($this->dataKegiatan);
            }
            $this->dispatch('notify', message: $e->getMessage(), type: 'error');

            return;
        } catch (AuthorizationException $e) {
            $this->muatUlang();
            $this->teksTerbuka = null;
            $this->dispatch('notify', message: $e->getMessage(), type: 'error');

            return;
        }

        $this->muatUlang();
        $this->teksTerbuka = null;
        $label = TeksKhusus::BIDANG[$kunci][1];
        $this->dispatch('notify', ...match (true) {
            $hasil['diubah'] === [] => ['message' => "{$label} tidak berubah.", 'type' => 'info'],
            TeksKhusus::disesuaikan($kunci, $this->dataKegiatan) => ['message' => "{$label} disesuaikan untuk kegiatan ini.", 'type' => 'success'],
            default => ['message' => "{$label} kembali memakai teks baku.", 'type' => 'success'],
        });
    }

    /** Tambah satu rincian ke RAB sumber dana yang ditampilkan (RincianRab: dirapikan & diperiksa seperti halaman RAB). */
    public function tambahRincian(): void
    {
        $sumber = $this->kak->sumber;
        abort_unless($sumber !== null, 404);
        $this->resetErrorBag();

        try {
            $hasil = RincianRab::tambah($this->kegiatanId, $sumber, $this->rincianBaru);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $kolom => $pesan) {
                $this->addError("rincianBaru.{$kolom}", $pesan[0]);
            }

            return;
        } catch (AuthorizationException $e) {
            $this->dispatch('notify', message: $e->getMessage(), type: 'error');

            return;
        }

        $this->reset('rincianBaru');
        $this->muatUlang();
        $this->dispatch('notify', message: "Rincian ditambahkan ke RAB {$sumber}: ".Format::rupiah($hasil['jumlah']).'.', type: 'success');
        if ($hasil['termin'] !== null) {
            $this->dispatch('notify', message: $hasil['termin']['pesan'], type: $hasil['termin']['tipe']);
        }
    }

    /** Simpan (atau perbarui) draf dokumen jenis × sumber dana yang sedang disusun (DrafDokumen). */
    public function simpanDraf(): void
    {
        try {
            $draf = DrafDokumen::simpan($this->kegiatanId, $this->jenis, $this->kak->sumber, StubData::currentUser());
        } catch (ValidationException $e) {
            $this->dispatch('notify', message: collect($e->errors())->flatten()->first(), type: 'error');

            return;
        } catch (AuthorizationException $e) {
            $this->muatUlang();
            $this->dispatch('notify', message: $e->getMessage(), type: 'error');

            return;
        }

        $this->muatUlang();
        $this->dispatch('notify', message: 'Draf '.DrafDokumen::nama($draf['jenis'], $draf['sumber']).' tersimpan — lanjutkan kapan saja dari tab ini.', type: 'success');
    }

    /** Hapus draf jenis × sumber yang tampil; isi dokumen & data kegiatan tidak berubah. */
    public function hapusDraf(): void
    {
        $nama = DrafDokumen::nama($this->jenis, $this->kak->sumber);
        try {
            $ada = DrafDokumen::hapus($this->kegiatanId, $this->jenis, $this->kak->sumber, StubData::currentUser());
        } catch (AuthorizationException $e) {
            $this->muatUlang();
            $this->dispatch('notify', message: $e->getMessage(), type: 'error');

            return;
        }

        $this->muatUlang();
        $this->dispatch('notify', message: $ada ? "Draf {$nama} dihapus." : "Draf {$nama} sudah tidak ada.", type: 'info');
    }

    /** Buang data & susunan yang dihitung agar tampilan memakai data tersimpan terbaru. */
    private function muatUlang(): void
    {
        unset($this->dataKegiatan);
        $this->lupakan();
    }

    private function lupakan(): void
    {
        unset($this->kak, $this->rab, $this->susunan, $this->statusCetak, $this->pemeriksaan, $this->pilihanJenis, $this->cover, $this->drafSemua, $this->keadaanDraf);
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function dataKegiatan(): array
    {
        return app(KegiatanRepository::class)->temukan($this->kegiatanId) ?? abort(404, 'Kegiatan tidak ditemukan.');
    }

    public function status(): StatusKegiatan
    {
        return StatusKegiatan::tryFrom($this->dataKegiatan['status'] ?? '') ?? StatusKegiatan::Draf;
    }

    #[Computed]
    public function kak(): SusunanKak
    {
        return SusunanKak::dari($this->dataKegiatan, $this->sumber ?: null);
    }

    #[Computed]
    public function rab(): SusunanRab
    {
        return SusunanRab::dari($this->dataKegiatan, $this->sumber ?: null);
    }

    #[Computed]
    public function susunan(): SusunanOtomatis
    {
        return SusunanOtomatis::dari($this->kak, $this->rab, $this->dataKegiatan, StubData::currentUser());
    }

    /** @return list<array<string, mixed>> Pilihan jenis dokumen (JenisDokumen::pilihan). */
    #[Computed]
    public function pilihanJenis(): array
    {
        return JenisDokumen::pilihan($this->statusCetak, $this->kak->sumber, StubData::tahunAktif()['tahun']);
    }

    /** @return list<string> Bagian dokumen jenis terpilih (cover, kak, rab). */
    public function isi(): array
    {
        return JenisDokumen::ISI[$this->jenis];
    }

    /** Cap DRAF dokumen jenis terpilih. */
    public function statusDraf(): StatusDraf
    {
        return JenisDokumen::statusDraf($this->jenis, $this->statusCetak);
    }

    /** @return array<string, mixed> Pemeriksaan cetak jenis terpilih (nama berkas, butir aturan 8, siap). */
    #[Computed]
    public function pemeriksaan(): array
    {
        return PemeriksaanCetak::periksa($this->dataKegiatan, $this->jenis, $this->kak->sumber);
    }

    /** @return array<string, array<string, mixed>|null> Master template per bagian jenis terpilih. */
    public function template(): array
    {
        return JenisDokumen::template($this->jenis, StubData::tahunAktif()['tahun']);
    }

    /**
     * Cover kegiatan (set lengkap): desain default tahun aktif berisi kotak teks kegiatan.
     *
     * @return array{gambar: string|null, teks: list<array<string, mixed>>, nilai: array<string, string>}
     */
    #[Computed]
    public function cover(): array
    {
        return CoverDokumen::desain() + ['nilai' => TeksCover::nilai($this->dataKegiatan, $this->kak->sumber)];
    }

    /** Keadaan cetak set dokumen sumber dana ini (cap DRAF + alasan). */
    #[Computed]
    public function statusCetak(): StatusCetak
    {
        return StatusCetak::dari($this->dataKegiatan, $this->sumber ?: null, $this->kak, $this->rab);
    }

    /** @return list<array<string, mixed>> Draf dokumen kegiatan ini, terbaru dulu. */
    #[Computed]
    public function drafSemua(): array
    {
        return app(DrafDokumenRepository::class)->untukKegiatan($this->kegiatanId);
    }

    /** @return array<string, mixed>|null Draf jenis × sumber dana yang sedang tampil. */
    public function drafAktif(): ?array
    {
        return collect($this->drafSemua)->first(fn (array $d) => $d['jenis'] === $this->jenis && $d['sumber'] === $this->kak->sumber);
    }

    /** @return array{keadaan: string, label: string, keterangan: string, berubah: list<array{kunci: string, label: string, jangkar: string}>} */
    #[Computed]
    public function keadaanDraf(): array
    {
        return DrafDokumen::keadaan($this->drafAktif(), DrafDokumen::sidik($this->jenis, $this->kak, $this->rab, $this->cover['nilai']), $this->kak);
    }

    public function render()
    {
        return view('livewire.kegiatan.susun-dokumen')->title('Susun KAK/TOR · '.($this->dataKegiatan['nama_kegiatan'] ?? 'Kegiatan'));
    }
}
