<?php

namespace App\Livewire\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Livewire\Forms\KegiatanForm;
use App\Support\Kegiatan\RingkasanKode;
use App\Support\MasterKode;
use App\Support\Stub\StubData;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Halaman Kode & Klasifikasi satu kegiatan: leading, kode RKTM, SK, IKSK, EDM, dan 5 Budaya Mutu
 * dipilih dari master tahun anggaran aktif (PRD F7, aturan "Kode wajib valid").
 */
#[Layout('layouts.app')]
class Klasifikasi extends Component
{
    /** Isian halaman ini; isian kegiatan lainnya tidak disentuh. */
    public const ISIAN = RingkasanKode::ISIAN;

    /** Batas entri riwayat perubahan kode yang disimpan per kegiatan. */
    public const BATAS_RIWAYAT = 20;

    #[Locked]
    public int $kegiatanId;

    /** @var array<string, mixed> */
    #[Locked]
    public array $dataKegiatan = [];

    public KegiatanForm $form;

    public bool $adaPerubahan = false;

    /**
     * Kode tersimpan ketika halaman dimuat/terakhir disimpan; pembanding untuk perubahan dan bentrok.
     *
     * @var array<string, string|int|null>
     */
    #[Locked]
    public array $kodeDimuat = [];

    /**
     * Kode yang disimpan pihak lain sejak halaman dimuat (isian → [versi dimuat, versi tersimpan]); null bila tidak bentrok.
     *
     * @var array<string, array{0: string|int|null, 1: string|int|null}>|null
     */
    #[Locked]
    public ?array $bentrok = null;

    public function mount(KegiatanRepository $repo, int $kegiatan): void
    {
        $this->kegiatanId = $kegiatan;
        $this->muatDari($repo->temukan($kegiatan) ?? abort(404, 'Kegiatan tidak ditemukan.'));
    }

    public function status(): StatusKegiatan
    {
        return StatusKegiatan::tryFrom($this->dataKegiatan['status'] ?? '') ?? StatusKegiatan::Draf;
    }

    public function dapatDiedit(): bool
    {
        return $this->status()->dapatDiedit();
    }

    public function updating(string $property): void
    {
        if (str_starts_with($property, 'form.')) {
            $this->pastikanDapatDiedit();
            abort_unless(in_array(substr($property, 5), self::ISIAN, true), 403, 'Isian ini diubah di halaman Data Kegiatan.');
        }
    }

    public function updated(string $property): void
    {
        if (! str_starts_with($property, 'form.')) {
            return;
        }

        $field = substr($property, 5);

        if ($field === 'kode_sk') {
            $this->form->sinkronIksk();

            if ($this->form->pilihIkskTunggal()) {
                $this->dispatch('notify', message: "IKSK {$this->form->kode_iksk} dipilih otomatis — satu-satunya indikator {$this->form->kode_sk}.", type: 'info');
            }

            $this->validasiDiam('kode_iksk');
        }

        $this->adaPerubahan = $this->isianBerubah() !== [];
        $this->form->validateOnly($field);
    }

    /**
     * Isian yang berbeda dari versi tersimpan (lama → baru).
     *
     * @return array<string, array{0: string|int|null, 1: string|int|null}>
     */
    public function isianBerubah(): array
    {
        return RingkasanKode::selisih($this->kodeDimuat, RingkasanKode::nilaiBaku($this->form->dataUntukDisimpan()));
    }

    /**
     * Riwayat perubahan kode terbaru lebih dulu.
     *
     * @return list<array{pada: string, oleh: string, perubahan: array<string, array{0: string|int|null, 1: string|int|null}>}>
     */
    #[Computed]
    public function riwayatKode(): array
    {
        return array_reverse($this->dataKegiatan['riwayat_kode'] ?? []);
    }

    /**
     * @return array<string, array{label: string, nilai: string|null}>
     */
    #[Computed]
    public function pratinjauHeader(): array
    {
        $leading = MasterKode::cari(MasterKode::leadings(), $this->form->leading);
        $rktm = MasterKode::cari(MasterKode::kodeRktm(), $this->form->kode_rktm);
        $sk = MasterKode::cari(MasterKode::kodeSk(), $this->form->kode_sk);
        $iksk = MasterKode::cari(MasterKode::kodeIksk(), $this->form->kode_iksk);
        $edm = MasterKode::cari(MasterKode::kodeEdm(), $this->form->kode_edm);
        $budaya = MasterKode::cari(MasterKode::budayaMutu(), $this->form->budaya_mutu, 'nomor');

        return [
            'leading' => ['label' => 'Leading sektor', 'nilai' => $leading['nama_unit'] ?? null],
            'rktm' => ['label' => 'Nama kegiatan', 'nilai' => $rktm ? ($this->dataKegiatan['nama_kegiatan'] ?? '').' ('.$rktm['kode'].')' : null],
            'sk' => ['label' => 'Kode SK', 'nilai' => $sk['kode'] ?? null],
            'iksk' => ['label' => 'IKSK', 'nilai' => $iksk['kode'] ?? null],
            'edm' => ['label' => 'Kode EDM', 'nilai' => $edm['kode'] ?? null],
            'budaya' => ['label' => '5 Budaya Mutu', 'nilai' => $budaya ? $budaya['nomor'].'. '.$budaya['nama'] : null],
        ];
    }

    /**
     * Status tiap kartu: lengkap bila semua isiannya terpilih.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function kelengkapan(): array
    {
        $isi = fn (array $nilai) => count(array_filter($nilai, 'filled'));
        $status = fn (array $nilai) => match ($isi($nilai)) {
            count($nilai) => 'lengkap',
            0 => 'kosong',
            default => 'sebagian',
        };

        return [
            'leading' => $status([$this->form->leading]),
            'rktm' => $status([$this->form->kode_rktm]),
            'perkin' => $status([$this->form->kode_sk, $this->form->kode_iksk]),
            'edm' => $status([$this->form->kode_edm]),
            'budaya' => $status([$this->form->budaya_mutu]),
        ];
    }

    /**
     * Ringkasan kode terpilih (dari isian yang sedang diubah, belum tentu tersimpan).
     *
     * @return list<array{kunci: string, label: string, kode: string|null, uraian: string|null, detail: string|null}>
     */
    #[Computed]
    public function ringkasanKode(): array
    {
        return RingkasanKode::susun($this->form->only(RingkasanKode::ISIAN));
    }

    /**
     * @return array{status: string, terisi: int, total: int, kosong: list<array{kunci: string, label: string, kartu: string}>, pesan: string}
     */
    #[Computed]
    public function statusKode(): array
    {
        return RingkasanKode::status($this->ringkasanKode);
    }

    /**
     * Simpan kode ke penyimpanan. Bila kode tersimpan telah diubah pihak lain sejak halaman dimuat, penyimpanan
     * ditahan (bentrok) sampai pengguna memuat versi terbaru atau memilih menimpa ($timpa).
     */
    public function simpan(bool $lanjutKeRab = false, bool $timpa = false): void
    {
        $this->pastikanDapatDiedit();

        try {
            foreach (self::ISIAN as $field) {
                $this->form->validateOnly($field);
            }

            if ($wajib = $this->form->kodeWajibKosong()) {
                throw ValidationException::withMessages($wajib);
            }
        } catch (ValidationException $e) {
            $this->dispatch('notify', message: 'Periksa kembali kode yang ditandai merah.', type: 'error');

            throw $e;
        }

        $repo = app(KegiatanRepository::class);
        $tersimpan = $repo->temukan($this->kegiatanId) ?? $this->dataKegiatan;
        $kodeTersimpan = RingkasanKode::nilaiBaku($tersimpan);
        $kodeBaru = RingkasanKode::nilaiBaku($this->form->dataUntukDisimpan());

        if (! $timpa && ($bentrok = RingkasanKode::selisih($this->kodeDimuat, $kodeTersimpan))) {
            $this->bentrok = $bentrok;
            $this->dispatch('notify', message: 'Kode kegiatan ini baru diubah di tempat lain — pilih muat versi terbaru atau timpa.', type: 'error');

            return;
        }

        if ($perubahan = RingkasanKode::selisih($kodeTersimpan, $kodeBaru)) {
            $riwayat = $tersimpan['riwayat_kode'] ?? [];
            $riwayat[] = ['pada' => now()->toIso8601String(), 'oleh' => StubData::currentUser()['name'], 'perubahan' => $perubahan];
            $repo->simpan(array_merge($tersimpan, $kodeBaru, ['riwayat_kode' => array_slice($riwayat, -self::BATAS_RIWAYAT)]), $this->kegiatanId);
        }

        $this->muatDari($repo->temukan($this->kegiatanId) ?? $tersimpan);

        $notifikasi = $perubahan
            ? RingkasanKode::notifikasiSimpan($this->statusKode)
            : ['message' => 'Tidak ada perubahan kode untuk disimpan.', 'type' => 'info'];

        if ($lanjutKeRab && Route::has('kegiatan.rab')) {
            session()->flash('notify', $notifikasi);
            $this->redirectRoute('kegiatan.rab', ['kegiatan' => $this->kegiatanId], navigate: true);

            return;
        }

        $this->dispatch('notify', ...$notifikasi);
    }

    /** Buang pilihan yang belum disimpan dan kembali ke kode tersimpan. */
    public function batalkanPerubahan(): void
    {
        $this->muatDari(app(KegiatanRepository::class)->temukan($this->kegiatanId) ?? $this->dataKegiatan);
        $this->dispatch('notify', message: 'Perubahan kode dibatalkan.', type: 'info');
    }

    /** Saat bentrok: ambil kode yang disimpan pihak lain (pilihan di layar ini dibuang). */
    public function muatVersiTerbaru(): void
    {
        $this->muatDari(app(KegiatanRepository::class)->temukan($this->kegiatanId) ?? $this->dataKegiatan);
        $this->dispatch('notify', message: 'Kode terbaru dimuat.', type: 'info');
    }

    /**
     * @return array{id: int, tahun: int, is_locked: bool}
     */
    #[Computed]
    public function tahunAktif(): array
    {
        return StubData::tahunAktif();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function muatDari(array $data): void
    {
        $this->dataKegiatan = $data;
        $this->form->isiDari($data);
        $this->kodeDimuat = RingkasanKode::nilaiBaku($data);
        $this->adaPerubahan = false;
        $this->bentrok = null;
        $this->resetValidation();
        unset($this->riwayatKode, $this->ringkasanKode, $this->statusKode, $this->kelengkapan, $this->pratinjauHeader);
    }

    private function validasiDiam(string $field): void
    {
        try {
            $this->form->validateOnly($field);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());
        }
    }

    private function pastikanDapatDiedit(): void
    {
        if (! $this->dapatDiedit()) {
            abort(403, 'Kegiatan berstatus '.$this->status()->label().' sehingga kodenya terkunci.');
        }
    }

    public function render()
    {
        return view('livewire.kegiatan.klasifikasi')
            ->title('Kode & Klasifikasi · '.($this->dataKegiatan['nama_kegiatan'] ?? 'Kegiatan'));
    }
}
