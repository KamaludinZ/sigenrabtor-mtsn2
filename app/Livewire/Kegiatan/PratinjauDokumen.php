<?php

namespace App\Livewire\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Support\Dokumen\AngkaDokumen;
use App\Support\Dokumen\ChecklistIsian;
use App\Support\Dokumen\CoverDokumen;
use App\Support\Dokumen\KaidahDokumen;
use App\Support\Dokumen\KelengkapanDokumen;
use App\Support\Dokumen\PenampilDokumen;
use App\Support\Dokumen\PenandaKosong;
use App\Support\Dokumen\StatusCetak;
use App\Support\Dokumen\StatusDraf;
use App\Support\Dokumen\SuntingDokumen;
use App\Support\Dokumen\SusunanKak;
use App\Support\Dokumen\SusunanRab;
use App\Support\Dokumen\TahunDokumen;
use App\Support\Dokumen\TanggalDokumen;
use App\Support\Dokumen\TeksCover;
use App\Support\Kegiatan\AksesKegiatan;
use App\Support\Kegiatan\KunciKegiatan;
use App\Support\Kegiatan\UbahDataKegiatan;
use App\Support\Pengaturan\PengaturanBerubah;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Tab ruang kerja "Pratinjau Dokumen" (PRD F15 & §17): satu set dokumen per sumber dana seperti hasil cetak — cover dari
 * template cover default tahun aktif, KAK/TOR sembilan bagian, dan RAB — berdampingan dengan panel kaidah dokumen
 * otomatis (KaidahDokumen). Sumber dana di `?sumber=` ('' = sumber pertama). Dokumen tampil di penampil lembar A4
 * (PenampilDokumen: perbesaran, perkiraan batas halaman, penunjuk halaman, daftar isi `isiDokumen`). Mode `?mode=`
 * (`MODE`): baca, atau sunting teks — kalimat KAK/TOR dari isian/teks khusus (SuntingDokumen) disunting langsung di
 * lembar (`bukaSunting($kunci)` → `simpanSunting()`, versi data saat dibuka) selama kegiatan bisa diubah. Isian kosong
 * ditandai (PenandaKosong: tingkat, tempat melengkapi, "Isi di sini" `isiDiSini()`) dan bisa dijelajahi satu per satu
 * dari kendali penampil. Cetak & unduh
 * menyusul.
 */
#[Layout('layouts.app')]
class PratinjauDokumen extends Component
{
    #[Locked]
    public int $kegiatanId;

    #[Url(except: '')]
    public string $sumber = '';

    /** Mode pratinjau → label. */
    public const MODE = ['baca' => 'Baca', 'sunting' => 'Sunting teks'];

    #[Url(except: 'baca')]
    public string $mode = 'baca';

    /** Kunci SuntingDokumen::BIDANG yang sedang disunting; null = tertutup. */
    public ?string $suntingTerbuka = null;

    public string $teksSunting = '';

    /** Sunting terakhir yang tersimpan di halaman ini: {label, pada "21.05"}; null = belum ada. */
    public ?array $terakhirDisimpan = null;

    /** Versi data kegiatan (UbahDataKegiatan::versi) saat penyunting dibuka. */
    #[Locked]
    public ?string $versiSunting = null;

    public function mount(KegiatanRepository $repo, int $kegiatan): void
    {
        AksesKegiatan::pastikan($kegiatan);
        $this->kegiatanId = $kegiatan;
        $this->updatedSumber();
        $this->updatedMode();
    }

    /** Mode tak dikenal → baca; mode sunting hanya selama kegiatan bisa diubah. */
    public function updatedMode(): void
    {
        if (! isset(self::MODE[$this->mode])) {
            $this->mode = 'baca';
        }
        if ($this->mode === 'sunting' && ! $this->dapatDiubah()) {
            $this->mode = 'baca';
            $this->dispatch('notify', message: $this->alasanTerkunci(), type: 'error');
        }
        $this->tutupSunting();
    }

    /** Kegiatan masih bisa diubah (status Draf/Dikembalikan & tahun anggaran terbuka). */
    public function dapatDiubah(): bool
    {
        return KunciKegiatan::dapatDiubah($this->dataKegiatan);
    }

    public function alasanTerkunci(): string
    {
        $tahun = KunciKegiatan::tahunTerkunci($this->dataKegiatan);

        return $tahun !== null ? KunciKegiatan::pesanTahun($tahun) : 'Kegiatan berstatus '.$this->status()->label().' sehingga teksnya hanya bisa dibaca.';
    }

    /** Buka penyunting satu teks di lembar KAK/TOR (mode sunting). */
    public function bukaSunting(string $kunci): void
    {
        abort_unless(isset(SuntingDokumen::BIDANG[$kunci]), 404);
        abort_unless($this->mode === 'sunting' && $this->dapatDiubah(), 403, 'Teks dokumen ini hanya bisa dibaca.');
        $this->teksSunting = SuntingDokumen::teks($this->dataKegiatan, $kunci);
        $this->versiSunting = UbahDataKegiatan::versi($this->dataKegiatan);
        $this->resetErrorBag();
        $this->suntingTerbuka = $kunci;
    }

    /** Penanda kosong "Isi di sini": masuk mode sunting dan buka penyunting isian itu. */
    public function isiDiSini(string $kunci): void
    {
        abort_unless(isset(SuntingDokumen::BIDANG[$kunci]), 404);
        if (! $this->dapatDiubah()) {
            $this->dispatch('notify', message: $this->alasanTerkunci(), type: 'error');

            return;
        }
        $this->mode = 'sunting';
        $this->bukaSunting($kunci);
    }

    public function tutupSunting(): void
    {
        $this->suntingTerbuka = null;
        $this->resetErrorBag();
    }

    /** Simpan teks yang disunting ke data kegiatan; galat di bawah isian, perubahan dari tempat lain tidak tertimpa. */
    public function simpanSunting(): void
    {
        $kunci = $this->suntingTerbuka;
        abort_unless($kunci !== null, 404);
        $this->resetErrorBag();

        try {
            $hasil = SuntingDokumen::simpan($this->kegiatanId, $this->dataKegiatan, $kunci, $this->teksSunting, StubData::currentUser(), $this->versiSunting);
        } catch (ValidationException $e) {
            $this->addError('teksSunting', collect($e->errors())->flatten()->first());

            return;
        } catch (PengaturanBerubah $e) {
            $this->muatUlang();
            $this->versiSunting = UbahDataKegiatan::versi($this->dataKegiatan);
            $this->dispatch('notify', message: $e->getMessage().' Teks Anda masih di penyunting — periksa lalu simpan lagi.', type: 'error');

            return;
        } catch (AuthorizationException $e) {
            $this->muatUlang();
            $this->mode = 'baca';
            $this->suntingTerbuka = null;
            $this->dispatch('notify', message: $e->getMessage(), type: 'error');

            return;
        }

        $this->muatUlang();
        $this->suntingTerbuka = null;
        $this->terakhirDisimpan = ['label' => SuntingDokumen::label($kunci), 'pada' => now()->format('H.i')];
        $this->dispatch('notify', message: $hasil['pesan'], type: $hasil['diubah'] === [] ? 'info' : 'success');
        // Draf lokal ketikan bidang ini di peramban dibuang (resources/js/penyunting-teks.js).
        $this->dispatch('sunting-tersimpan', kunci: $kunci);
    }

    /** Buang data & susunan yang dihitung agar tampilan memakai data tersimpan terbaru. */
    private function muatUlang(): void
    {
        unset($this->dataKegiatan, $this->checklist);
        $this->updatedSumber();
    }

    public function updatedSumber(): void
    {
        unset($this->kak, $this->rab, $this->kaidah, $this->angka, $this->tanggal, $this->tahunCetak, $this->cover, $this->statusCetak, $this->isiDokumen, $this->penandaKosong, $this->kelengkapan);
        if ($this->sumber !== '' && ! in_array($this->sumber, $this->kak->daftarSumber, true)) {
            $this->sumber = '';
            unset($this->kak, $this->rab, $this->kaidah, $this->angka, $this->tanggal, $this->tahunCetak, $this->cover, $this->statusCetak, $this->isiDokumen, $this->penandaKosong, $this->kelengkapan);
        }
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

    /** Angka dokumen (nominal & terbilang tiap tempat cetak) RAB sumber dana yang ditampilkan; null tanpa sumber dana. */
    #[Computed]
    public function angka(): ?AngkaDokumen
    {
        $anggaran = $this->rab->anggaran;

        return $anggaran ? AngkaDokumen::dari($anggaran->hasil, $anggaran->sumber, StubData::tahunAktif()['tahun'], StubData::madrasah()['nama_singkat']) : null;
    }

    /** Keputusan cetak set dokumen (cover + KAK/TOR + RAB): cap DRAF, alasan, dan saran menghilangkannya. */
    #[Computed]
    public function statusCetak(): StatusCetak
    {
        return StatusCetak::dari($this->dataKegiatan, $this->sumber ?: null, $this->kak, $this->rab);
    }

    /** Cap DRAF set dokumen (cover + KAK/TOR + RAB) beserta alasannya. */
    public function statusDraf(): StatusDraf
    {
        return $this->statusCetak->set;
    }

    /** @return list<array{kunci: string, lembar: string, label: string, jangkar: string, kurang: int}> Daftar isi penampil. */
    #[Computed]
    public function isiDokumen(): array
    {
        return PenampilDokumen::isi($this->kak, $this->rab);
    }

    /** @return list<array{dokumen: string, bagian: int|null, pesan: string, tingkat: string, asal: string|null, sunting: string|null}> Penanda kosong KAK/TOR & RAB (urut lembar). */
    #[Computed]
    public function penandaKosong(): array
    {
        return PenandaKosong::daftar($this->kak, $this->rab);
    }

    /** Ringkasan kelengkapan set dokumen yang ditampilkan (panel samping, navigasi per bagian). */
    #[Computed]
    public function kelengkapan(): KelengkapanDokumen
    {
        return KelengkapanDokumen::dari($this->kak, $this->rab, $this->dataKegiatan, StubData::currentUser(), $this->statusCetak->set);
    }

    /** @return list<array<string, mixed>> Checklist isian wajib per tempat mengisinya (ChecklistIsian). */
    #[Computed]
    public function checklist(): array
    {
        return ChecklistIsian::untuk($this->dataKegiatan);
    }

    /** @return list<array<string, mixed>> Tempat tahun tercetak/terketik (TahunDokumen) dokumen sumber dana yang ditampilkan. */
    #[Computed]
    public function tahunCetak(): array
    {
        return TahunDokumen::dari($this->dataKegiatan, $this->kak, $this->rab, $this->cover);
    }

    /** @return list<array<string, mixed>> Tempat tanggal tercetak (TanggalDokumen) dokumen sumber dana yang ditampilkan. */
    #[Computed]
    public function tanggal(): array
    {
        return TanggalDokumen::dari($this->dataKegiatan, $this->kak->sumber);
    }

    /** @return list<array{kunci: string, nomor: int, judul: string, status: string, pesan: string}> */
    #[Computed]
    public function kaidah(): array
    {
        return KaidahDokumen::periksa($this->dataKegiatan, $this->kak, $this->rab, $this->cover);
    }

    /**
     * Cover default tahun aktif (gambar + kotak teks); tanpa template: bingkai polos dengan susunan teks bawaan.
     *
     * @return array{gambar: string|null, teks: list<array<string, mixed>>, nilai: array<string, string>}
     */
    #[Computed]
    public function cover(): array
    {
        return CoverDokumen::desain() + ['nilai' => TeksCover::nilai($this->dataKegiatan, $this->kak->sumber)];
    }

    public function render()
    {
        return view('livewire.kegiatan.pratinjau-dokumen')->title('Pratinjau Dokumen · '.($this->dataKegiatan['nama_kegiatan'] ?? 'Kegiatan'));
    }
}
