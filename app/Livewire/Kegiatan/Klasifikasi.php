<?php

namespace App\Livewire\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Livewire\Forms\KegiatanForm;
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
    public const ISIAN = ['leading', 'kode_rktm', 'kode_sk', 'kode_iksk', 'kode_edm', 'budaya_mutu'];

    #[Locked]
    public int $kegiatanId;

    /** @var array<string, mixed> */
    #[Locked]
    public array $dataKegiatan = [];

    public KegiatanForm $form;

    public bool $adaPerubahan = false;

    public function mount(KegiatanRepository $repo, int $kegiatan): void
    {
        $this->dataKegiatan = $repo->temukan($kegiatan) ?? abort(404, 'Kegiatan tidak ditemukan.');
        $this->kegiatanId = $kegiatan;
        $this->form->isiDari($this->dataKegiatan);
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
        $this->adaPerubahan = true;

        if ($field === 'kode_sk') {
            $this->form->sinkronIksk();

            if ($this->form->pilihIkskTunggal()) {
                $this->dispatch('notify', message: "IKSK {$this->form->kode_iksk} dipilih otomatis — satu-satunya indikator {$this->form->kode_sk}.", type: 'info');
            }

            $this->validasiDiam('kode_iksk');
        }

        $this->form->validateOnly($field);
    }

    /**
     * @return array<string, array{label: string, nilai: string|null, detail: string|null}>
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
            'leading' => ['label' => 'Leading sektor', 'nilai' => $leading['nama_unit'] ?? null, 'detail' => $leading['jabatan_penanda_tangan'] ?? null],
            'rktm' => ['label' => 'Nama kegiatan (kode RKTM)', 'nilai' => $rktm ? ($this->dataKegiatan['nama_kegiatan'] ?? '').' ('.$rktm['kode'].')' : null, 'detail' => $rktm['sub_kegiatan'] ?? null],
            'sk' => ['label' => 'Kode SK', 'nilai' => $sk['kode'] ?? null, 'detail' => $sk['uraian'] ?? null],
            'iksk' => ['label' => 'IKSK', 'nilai' => $iksk['kode'] ?? null, 'detail' => $iksk['uraian'] ?? null],
            'edm' => ['label' => 'Kode EDM', 'nilai' => $edm['kode'] ?? null, 'detail' => $edm['uraian'] ?? null],
            'budaya' => ['label' => '5 Budaya Mutu', 'nilai' => $budaya ? $budaya['nomor'].'. '.$budaya['nama'] : null, 'detail' => null],
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

    public function simpan(bool $lanjutKeRab = false): void
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
        $simpanan = $this->form->dataUntukDisimpan();
        $data = array_merge($repo->temukan($this->kegiatanId) ?? $this->dataKegiatan, array_intersect_key($simpanan, array_flip(self::ISIAN)));
        $repo->simpan($data, $this->kegiatanId);

        $this->dataKegiatan = $repo->temukan($this->kegiatanId) ?? $data;
        $this->adaPerubahan = false;

        if ($lanjutKeRab && Route::has('kegiatan.rab')) {
            session()->flash('notify', ['message' => 'Kode & klasifikasi tersimpan.', 'type' => 'success']);
            $this->redirectRoute('kegiatan.rab', ['kegiatan' => $this->kegiatanId], navigate: true);

            return;
        }

        $this->dispatch('notify', message: 'Kode & klasifikasi tersimpan.');
    }

    /**
     * @return array{id: int, tahun: int, is_locked: bool}
     */
    #[Computed]
    public function tahunAktif(): array
    {
        return StubData::tahunAktif();
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
