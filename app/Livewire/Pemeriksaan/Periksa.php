<?php

namespace App\Livewire\Pemeriksaan;

use App\Contracts\ArsipFinalRepository;
use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Support\Dokumen\SusunanKak;
use App\Support\Dokumen\SusunanRab;
use App\Support\Kegiatan\ArsipFinal;
use App\Support\Kegiatan\PerbaikanKegiatan;
use App\Support\Kegiatan\RiwayatStatus;
use App\Support\Kegiatan\TahunKegiatan;
use App\Support\Pemeriksaan\AntreanPemeriksaan;
use App\Support\Pemeriksaan\CatatanPemeriksaan;
use App\Support\Pemeriksaan\KeputusanPemeriksaan;
use App\Support\Pemeriksaan\RiwayatPemeriksaan;
use App\Support\Pengaturan\PengaturanBerubah;
use App\Support\Rab\AnggaranKegiatan;
use App\Support\Rab\AnggaranSumber;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Detail dokumen satu kegiatan untuk pemeriksaan admin (PRD §16, F17): pratinjau KAK/TOR atau RAB per sumber dana
 * (`?dokumen=kak|rab`, `?sumber=`), kelengkapan tiap dokumen, ringkasan anggaran & termin, riwayat status, dan posisi di
 * antrean Perlu diperiksa. Keputusan admin (PRD F17–F18): Setujui (catatan opsional), Kembalikan, atau Buka kunci kegiatan
 * Final (catatan/alasan wajib, RiwayatStatus::CATATAN_WAJIB) dengan konfirmasi di tempat; tercatat di riwayat_status
 * (RiwayatStatus::entri: siapa, kapan, catatan). Bila kegiatan berubah di tempat lain sejak halaman dimuat (mis. pengajuan
 * ditarik), keputusan ditolak dan tampilan dimuat ulang. Catatan yang sedang ditulis tersimpan sebagai draf saat kolomnya
 * ditinggalkan (CatatanPemeriksaan: dimuat lagi saat halaman dibuka, terlihat admin lain, dibuang setelah keputusan);
 * draf yang diubah admin lain sejak dimuat tidak ditimpa diam-diam — keduanya ditampilkan untuk dipilih. Khusus admin.
 */
#[Layout('layouts.app')]
class Periksa extends Component
{
    public const DOKUMEN = ['kak' => 'KAK/TOR', 'rab' => 'RAB'];

    /** Aksi alur status yang diputuskan di halaman ini. */
    public const KEPUTUSAN = KeputusanPemeriksaan::AKSI;

    #[Locked]
    public int $kegiatanId;

    #[Url(except: 'kak')]
    public string $dokumen = 'kak';

    /** '' = sumber dana pertama kegiatan. */
    #[Url(except: '')]
    public string $sumber = '';

    /** Catatan pemeriksa untuk leading (terkirim bersama aksi; tersimpan sebagai draf saat kolomnya ditinggalkan). */
    public string $catatan = '';

    /** Versi draf catatan yang terakhir dimuat/disimpan halaman ini (CatatanPemeriksaan::versi). */
    #[Locked]
    public string $versiCatatan = '';

    /** Draf diubah/dikosongkan di tempat lain saat menyimpan: true sampai admin memilih catatan yang dipakai. */
    #[Locked]
    public bool $bentrokCatatan = false;

    /** Draf terbaru dari tempat lain selama $bentrokCatatan (null = dikosongkan). */
    #[Locked]
    public ?array $catatanLain = null;

    /** Keputusan yang sedang menunggu konfirmasi: null, 'setujui', atau 'kembalikan'. */
    public ?string $konfirmasi = null;

    /** Sidik status & riwayat kegiatan saat halaman terakhir dirender (deteksi perubahan di tempat lain). */
    #[Locked]
    public string $versi = '';

    public function mount(KegiatanRepository $repo, int $kegiatan): void
    {
        $repo->temukan($kegiatan) ?? abort(404, 'Kegiatan tidak ditemukan.');
        $this->kegiatanId = $kegiatan;
        $this->rapikanPilihan();
        $draf = CatatanPemeriksaan::draf($kegiatan);
        $this->catatan = $draf['catatan'] ?? '';
        $this->versiCatatan = CatatanPemeriksaan::versi($draf);
    }

    /** Ketikan catatan dikirim saat kolomnya ditinggalkan (wire:model.live.blur) — langsung disimpan sebagai draf. */
    public function updatedCatatan(): void
    {
        $this->simpanCatatan();
    }

    /**
     * Simpan draf catatan. Ketikan tidak dirapikan di tempat agar respons tidak menimpa ketikan yang sedang berjalan;
     * bentuk rapinya dipakai saat keputusan dikirim.
     */
    public function simpanCatatan(): void
    {
        $this->resetErrorBag('catatan');
        try {
            $hasil = CatatanPemeriksaan::simpan($this->kegiatanId, $this->catatan, StubData::currentUser(), $this->versiCatatan);
        } catch (PengaturanBerubah $e) {
            $this->catatanLain = CatatanPemeriksaan::draf($this->kegiatanId);
            $this->versiCatatan = CatatanPemeriksaan::versi($this->catatanLain);
            $this->bentrokCatatan = true;
            unset($this->drafCatatan);
            $this->dispatch('notify', message: $e->getMessage(), type: 'error');

            return;
        } catch (ValidationException $e) {
            $this->addError('catatan', $e->errors()['catatan'][0] ?? $e->getMessage());

            return;
        } catch (AuthorizationException $e) {
            $this->addError('catatan', $e->getMessage());

            return;
        }

        $this->versiCatatan = $hasil['versi'];
        $this->bentrokCatatan = false;
        $this->catatanLain = null;
        unset($this->drafCatatan);
    }

    /** Selesaikan bentrok draf dengan memakai catatan dari tempat lain (ketikan sendiri dibuang). */
    public function pakaiCatatanLain(): void
    {
        $this->catatan = $this->catatanLain['catatan'] ?? '';
        $this->bentrokCatatan = false;
        $this->catatanLain = null;
        $this->resetErrorBag('catatan');
    }

    /** @return array{catatan: string, user_id: int|null, oleh: string, diperbarui_pada: string}|null */
    #[Computed]
    public function drafCatatan(): ?array
    {
        return CatatanPemeriksaan::draf($this->kegiatanId);
    }

    public function updatedDokumen(): void
    {
        $this->rapikanPilihan();
    }

    public function updatedSumber(): void
    {
        $this->rapikanPilihan();
    }

    /** Tampilkan satu dokumen (kak/rab) sumber dana tertentu — dari panel kelengkapan. */
    public function tampilkan(string $dokumen, string $sumber): void
    {
        $this->dokumen = $dokumen;
        $this->sumber = $sumber;
        $this->rapikanPilihan();
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function dataKegiatan(): array
    {
        return app(KegiatanRepository::class)->temukan($this->kegiatanId) ?? abort(404, 'Kegiatan tidak ditemukan.');
    }

    /** @return array<string, mixed> Baris antrean kegiatan ini (pembuat, waktu pengajuan, anggaran, jumlah kurang). */
    #[Computed]
    public function ringkasan(): array
    {
        return AntreanPemeriksaan::baris([$this->dataKegiatan])[0];
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

    /**
     * Kelengkapan setiap dokumen (sumber × KAK/TOR, RAB) untuk panel samping sekaligus navigasi dokumen.
     *
     * @return list<array{sumber: string, kak: int, rab: int}>
     */
    #[Computed]
    public function kelengkapan(): array
    {
        return array_map(fn (string $sumber) => [
            'sumber' => $sumber,
            'kak' => count(SusunanKak::dari($this->dataKegiatan, $sumber)->kurang),
            'rab' => count(SusunanRab::dari($this->dataKegiatan, $sumber)->kurang),
        ], $this->kak->daftarSumber);
    }

    /**
     * Arsip final per sumber dana wajib (null = belum diunggah), untuk status Disetujui & Final.
     *
     * @return array<string, array<string, mixed>|null>
     */
    #[Computed]
    public function arsip(): array
    {
        $ada = app(ArsipFinalRepository::class)->untukKegiatan($this->kegiatanId);

        return array_combine($sumber = ArsipFinal::sumberWajib($this->dataKegiatan), array_map(fn (string $s) => $ada[$s] ?? null, $sumber));
    }

    /** Perbaikan terakhir (dikembalikan/dibuka kuncinya) beserta bagian yang berubah sesudahnya; null bila belum pernah. */
    #[Computed]
    public function perbaikan(): ?PerbaikanKegiatan
    {
        return PerbaikanKegiatan::dari($this->dataKegiatan);
    }

    /** @return list<AnggaranSumber> */
    #[Computed]
    public function anggaran(): array
    {
        return AnggaranKegiatan::untuk($this->dataKegiatan);
    }

    /**
     * Riwayat pemeriksaan per putaran pengajuan (terbaru dulu), termasuk unggahan arsip final.
     *
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function putaran(): array
    {
        return RiwayatPemeriksaan::putaran($this->dataKegiatan, app(ArsipFinalRepository::class)->untukKegiatan($this->kegiatanId));
    }

    /**
     * Posisi kegiatan ini di antrean Perlu diperiksa tahun aktif beserta tetangganya; null bila tidak sedang diperiksa.
     *
     * @return array{posisi: int, jumlah: int, sebelum: int|null, sesudah: int|null}|null
     */
    public function antrean(): ?array
    {
        if ($this->ringkasan['status'] !== StatusKegiatan::Diperiksa) {
            return null;
        }

        $tahun = StubData::tahunAktif()['tahun'];
        $kegiatan = array_values(array_filter(app(KegiatanRepository::class)->semua(), fn (array $k) => ($k['status'] ?? null) === 'diperiksa' && TahunKegiatan::dari($k) === $tahun));
        $id = array_column(AntreanPemeriksaan::untukTab(AntreanPemeriksaan::baris($kegiatan), 'diperiksa'), 'id');
        $i = array_search($this->kegiatanId, $id, true);

        return $i === false ? null : ['posisi' => $i + 1, 'jumlah' => count($id), 'sebelum' => $id[$i - 1] ?? null, 'sesudah' => $id[$i + 1] ?? null];
    }

    /**
     * Keputusan yang tersedia dari status kegiatan saat ini untuk peran pengguna (KeputusanPemeriksaan::tersedia).
     *
     * @return array<string, array{label: string, ke: StatusKegiatan, konfirmasi: string}>
     */
    public function keputusan(): array
    {
        return KeputusanPemeriksaan::tersedia($this->dataKegiatan, StubData::currentUser());
    }

    /** Buka konfirmasi keputusan; aturannya diperiksa lebih dulu agar kesalahan tampil sebelum konfirmasi. */
    public function minta(string $aksi): void
    {
        $this->resetErrorBag();
        if (! isset($this->keputusan()[$aksi])) {
            $this->dispatch('notify', message: 'Keputusan ini tidak tersedia untuk status kegiatan saat ini.', type: 'error');

            return;
        }

        $this->catatan = KeputusanPemeriksaan::periksa($this->dataKegiatan, $aksi, $this->catatan, StubData::currentUser());
        $this->konfirmasi = $aksi;
    }

    public function batalKonfirmasi(): void
    {
        $this->konfirmasi = null;
    }

    public function putuskan(string $aksi): void
    {
        $this->konfirmasi = null;
        $this->resetErrorBag();
        if ($this->berubahDiTempatLain()) {
            return;
        }
        if (! isset($this->keputusan()[$aksi])) {
            $this->dispatch('notify', message: 'Keputusan ini tidak tersedia untuk status kegiatan saat ini.', type: 'error');

            return;
        }

        try {
            $hasil = KeputusanPemeriksaan::putuskan($this->kegiatanId, $aksi, $this->catatan, StubData::currentUser(), $this->versi);
        } catch (PengaturanBerubah $e) {
            unset($this->dataKegiatan, $this->ringkasan, $this->kak, $this->rab, $this->kelengkapan, $this->perbaikan, $this->putaran);
            $this->dispatch('notify', message: $e->getMessage(), type: 'error');

            return;
        }

        $this->catatan = '';
        $this->versiCatatan = '';
        $this->bentrokCatatan = false;
        $this->catatanLain = null;
        unset($this->dataKegiatan, $this->ringkasan, $this->kak, $this->rab, $this->kelengkapan, $this->perbaikan, $this->putaran, $this->drafCatatan);
        $this->dispatch('notify', message: $hasil['pesan']);
    }

    /** Pengajuan terlama yang masih menunggu diperiksa selain kegiatan ini (tautan setelah memutuskan). */
    public function berikutnya(): ?int
    {
        $tahun = StubData::tahunAktif()['tahun'];
        $kegiatan = array_values(array_filter(app(KegiatanRepository::class)->semua(), fn (array $k) => ($k['status'] ?? null) === 'diperiksa' && (int) $k['id'] !== $this->kegiatanId && TahunKegiatan::dari($k) === $tahun));

        return AntreanPemeriksaan::untukTab(AntreanPemeriksaan::baris($kegiatan), 'diperiksa')[0]['id'] ?? null;
    }

    private function berubahDiTempatLain(): bool
    {
        unset($this->dataKegiatan, $this->ringkasan, $this->kak, $this->rab, $this->kelengkapan, $this->perbaikan, $this->putaran);
        if ($this->sidik() === $this->versi) {
            return false;
        }

        $this->dispatch('notify', message: 'Kegiatan ini baru saja berubah di tempat lain (mis. pengajuan ditarik). Tampilan sudah dimuat ulang — periksa lagi sebelum memutuskan.', type: 'error');

        return true;
    }

    private function sidik(): string
    {
        return KeputusanPemeriksaan::versi($this->dataKegiatan);
    }

    private function rapikanPilihan(): void
    {
        if (! array_key_exists($this->dokumen, self::DOKUMEN)) {
            $this->dokumen = 'kak';
        }
        if ($this->sumber !== '' && ! in_array($this->sumber, $this->kak->daftarSumber, true)) {
            $this->sumber = '';
        }
        unset($this->kak, $this->rab);
    }

    public function rendering(): void
    {
        $this->versi = $this->sidik();
    }

    public function render()
    {
        return view('livewire.pemeriksaan.periksa')->title('Periksa · '.$this->ringkasan['nama']);
    }
}
