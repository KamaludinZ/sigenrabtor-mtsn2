<?php

namespace App\Livewire\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Contracts\RabRepository;
use App\Enums\StatusKegiatan;
use App\Livewire\Forms\KegiatanForm;
use App\Support\Kegiatan\FormSections;
use App\Support\MasterKode;
use App\Support\Rab\HasilRab;
use App\Support\Rab\KalkulatorRab;
use App\Support\Stub\StubData;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Layar utama Data Kegiatan: satu form panjang berisi semua bagian sumber KAK/TOR & RAB.
 */
#[Layout('layouts.app')]
class Form extends Component
{
    public KegiatanForm $form;

    #[Locked]
    public string $status = 'draf';

    /** @var list<array{aksi: string, status: string, oleh: string, peran: string, pada: string}> Jejak perubahan status (PRD N7). */
    #[Locked]
    public array $riwayatStatus = [];

    /** Id kegiatan tersimpan; null = kegiatan baru yang belum pernah disimpan. */
    #[Locked]
    public ?int $kegiatanId = null;

    #[Locked]
    public ?string $diperbaruiPada = null;

    /** Ada perubahan sejak terakhir disimpan/dimuat (untuk penanda & peringatan saat meninggalkan halaman). */
    public bool $adaPerubahan = false;

    /** @var array{index: int, anggota: array{key: string, nama: string, jabatan: string, peran: string}}|null Untuk "Urungkan" hapus anggota. */
    #[Locked]
    public ?array $panitiaTerhapus = null;

    /** Isian yang ikut divalidasi ulang ketika isian kunci berubah. */
    private const ISIAN_TERKAIT = [
        'tanggal_dokumen' => ['tanggal_laporan'],
        'tanggal_mulai' => ['tanggal_dokumen', 'lama_hari', 'tanggal_laporan', 'bulan_pelaksanaan'],
        'lama_hari' => ['tanggal_laporan', 'bulan_pelaksanaan'],
        'hari_kerja' => ['lama_hari', 'tanggal_laporan', 'bulan_pelaksanaan'],
        'kode_sk' => ['kode_iksk'],
        'dana_dipa' => ['pagu_dipa'],
        'dana_komite' => ['pagu_komite'],
    ];

    public function mount(KegiatanRepository $repo, ?int $kegiatan = null): void
    {
        if ($kegiatan === null) {
            // Akun leading dengan satu leading langsung terisi leading-nya.
            $pilihan = MasterKode::leadingsUntuk(StubData::currentUser());
            $this->form->leading = count($pilihan) === 1 ? $pilihan[0]['kode'] : '';

            return;
        }

        $data = $repo->temukan($kegiatan) ?? abort(404, 'Kegiatan tidak ditemukan.');

        $this->form->isiDari($data);
        $this->kegiatanId = $kegiatan;
        $this->status = StatusKegiatan::tryFrom($data['status'] ?? '')?->value ?? StatusKegiatan::Draf->value;
        $this->riwayatStatus = array_values((array) ($data['riwayat_status'] ?? []));
        $this->diperbaruiPada = $data['diperbarui_pada'] ?? null;
    }

    public function statusKegiatan(): StatusKegiatan
    {
        return StatusKegiatan::from($this->status);
    }

    public function dapatDiedit(): bool
    {
        return $this->statusKegiatan()->dapatDiedit();
    }

    /**
     * Penjaga sisi server: isian terkunci di luar status Draf/Dikembalikan, walau HTML-nya diakali.
     */
    private function pastikanDapatDiedit(): void
    {
        if (! $this->dapatDiedit()) {
            abort(403, 'Kegiatan berstatus '.$this->statusKegiatan()->label().' sehingga isiannya terkunci.');
        }
    }

    public function updating(string $property): void
    {
        if (str_starts_with($property, 'form.')) {
            $this->pastikanDapatDiedit();
        }
    }

    /**
     * Validasi per isian begitu nilainya berubah, plus aturan turunan (nama cover, jadwal).
     */
    public function updated(string $property): void
    {
        if (! str_starts_with($property, 'form.') || $property === 'form.cover_manual') {
            return;
        }

        $this->adaPerubahan = true;

        $field = substr($property, 5);

        match ($field) {
            'nama_kegiatan' => $this->form->syncNamaCover(),
            'nama_kegiatan_cover' => $this->form->setNamaCoverManual(),
            'tanggal_mulai', 'lama_hari', 'hari_kerja' => $this->form->sinkronJadwal(),
            'tanggal_laporan' => $this->form->setLaporanManual(),
            'kode_sk' => $this->form->sinkronIksk(),
            'pagu_dipa', 'pagu_komite' => $this->form->rapikanPagu($field),
            'dana_dipa' => $this->form->dana_dipa ?: $this->form->pagu_dipa = '',
            'dana_komite' => $this->form->dana_komite ?: $this->form->pagu_komite = '',
            default => null,
        };

        $this->form->rapikanUraian($field);

        // Isian terkait divalidasi lebih dulu (errornya disimpan), lalu isian utama dengan alur Livewire biasa.
        $this->validasiIsianTerkait(self::ISIAN_TERKAIT[$field] ?? []);
        $this->form->validateOnly($field);
    }

    /**
     * @param  list<string>  $fields
     */
    private function validasiIsianTerkait(array $fields): void
    {
        foreach ($fields as $field) {
            try {
                $this->form->validateOnly($field);
            } catch (ValidationException $e) {
                $this->setErrorBag($e->validator->errors());
            }
        }
    }

    public function toggleBulan(int $bulan): void
    {
        $this->pastikanDapatDiedit();
        $this->form->toggleBulan($bulan);
        $this->form->validateOnly('bulan_pelaksanaan');
    }

    public function sesuaikanBulan(): void
    {
        $this->pastikanDapatDiedit();
        $this->form->sesuaikanBulanDenganTanggal();
        $this->form->validateOnly('bulan_pelaksanaan');
    }

    public function resetNamaCover(): void
    {
        $this->pastikanDapatDiedit();
        $this->form->resetNamaCover();
        $this->form->validateOnly('nama_kegiatan_cover');
    }

    public function tambahPanitia(): void
    {
        $this->pastikanDapatDiedit();
        $this->panitiaTerhapus = null;

        if (! $this->form->tambahPanitia()) {
            $this->dispatch('notify', message: 'Susunan tim sudah mencapai batas '.KegiatanForm::MAKS_PANITIA.' orang.', type: 'error');

            return;
        }

        $this->dispatch('panitia-ditambah');
    }

    public function hapusPanitia(int $index): void
    {
        $this->pastikanDapatDiedit();
        if (! isset($this->form->panitia[$index])) {
            return;
        }

        $this->panitiaTerhapus = ['index' => $index, 'anggota' => $this->form->panitia[$index]];
        $this->form->hapusPanitia($index);
        $this->resetErrorPanitia();
    }

    public function urungkanHapusPanitia(): void
    {
        $this->pastikanDapatDiedit();
        if ($this->panitiaTerhapus === null) {
            return;
        }

        $this->form->sisipkanPanitia($this->panitiaTerhapus['index'], $this->panitiaTerhapus['anggota']);
        $this->panitiaTerhapus = null;
        $this->resetErrorPanitia();
    }

    public function geserPanitia(int $index, int $arah): void
    {
        $this->pastikanDapatDiedit();
        $this->panitiaTerhapus = null;
        $this->form->geserPanitia($index, $arah > 0 ? 1 : -1);
        $this->resetErrorPanitia();
    }

    /**
     * Handler seret-lepas (wire:sort): $key anggota yang dipindah, $posisi indeks tujuannya.
     */
    public function pindahkanPanitia(string $key, int $posisi): void
    {
        $this->pastikanDapatDiedit();
        $this->panitiaTerhapus = null;
        $this->form->pindahkanPanitia($key, $posisi);
        $this->resetErrorPanitia();
    }

    public function urutkanPanitiaSesuaiPeran(): void
    {
        $this->pastikanDapatDiedit();
        $this->panitiaTerhapus = null;
        $this->form->urutkanSesuaiPeran();
        $this->resetErrorPanitia();
        $this->dispatch('notify', message: 'Susunan tim diurutkan sesuai peran.', type: 'info');
    }

    public function isiSusunanBaku(): void
    {
        $this->pastikanDapatDiedit();
        $this->form->isiSusunanBaku();
        $this->dispatch('panitia-ditambah', posisi: 'awal');
    }

    /**
     * Pesan error baris panitia terikat ke indeks; buang setelah urutan berubah agar tidak salah tempat.
     */
    private function resetErrorPanitia(): void
    {
        $keys = array_filter(
            array_keys($this->getErrorBag()->messages()),
            fn (string $key) => str_starts_with($key, 'form.panitia.'),
        );

        if ($keys !== []) {
            $this->resetErrorBag($keys);
        }
    }

    /**
     * @return list<array{id: string, title: string, description: string, icon: string}>
     */
    #[Computed]
    public function sections(): array
    {
        return FormSections::all();
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
     * Draf kegiatan lain untuk menu "Buka draf".
     *
     * @return list<array{id: int, nama_kegiatan: string, status: string, diperbarui_pada: string}>
     */
    #[Computed]
    public function drafTerbaru(): array
    {
        return app(KegiatanRepository::class)->terbaru(8);
    }

    public function saveDraft(): void
    {
        $this->pastikanDapatDiedit();
        $baru = $this->kegiatanId === null;
        $id = $this->simpan();

        if ($baru) {
            session()->flash('notify', ['message' => 'Kegiatan baru tersimpan sebagai draf.', 'type' => 'success']);
            $this->redirectRoute('kegiatan.edit', ['kegiatan' => $id], navigate: true);

            return;
        }

        $this->dispatch('notify', message: 'Draf tersimpan pukul '.now()->format('H.i').'.');
    }

    /**
     * Simpan (bila masih bisa diedit) lalu buka RAB. RAB disusun per sumber dana, jadi minimal satu sumber dana wajib dipilih.
     */
    public function lanjutKeRab(): void
    {
        if ($this->form->sumberDana() === []) {
            $this->addError('form.sumber_dana', 'Pilih minimal satu sumber dana sebelum menyusun RAB.');
            $this->dispatch('notify', message: 'Pilih sumber dana terlebih dahulu.', type: 'error');

            return;
        }

        $id = $this->dapatDiedit() ? $this->simpan() : $this->kegiatanId;

        if (! Route::has('kegiatan.rab')) {
            $this->dispatch('notify', message: 'Data tersimpan. Halaman RAB sedang disiapkan pada tahap berikutnya.', type: 'info');

            if ($this->kegiatanId === null || $this->kegiatanId !== $id) {
                $this->redirectRoute('kegiatan.edit', ['kegiatan' => $id], navigate: true);
            }

            return;
        }

        $this->redirectRoute('kegiatan.rab', ['kegiatan' => $id], navigate: true);
    }

    /**
     * Validasi & simpan data kegiatan; mengembalikan id kegiatan.
     */
    private function simpan(): int
    {
        try {
            $this->form->validate();
        } catch (ValidationException $e) {
            $this->dispatch('notify', message: 'Periksa kembali isian yang ditandai merah.', type: 'error');

            throw $e;
        }

        $repo = app(KegiatanRepository::class);

        if ($this->kegiatanId === null) {
            $this->catatRiwayat('dibuat', StatusKegiatan::Draf);
        }

        $id = $repo->simpan($this->dataUntukDisimpan(), $this->kegiatanId);

        $this->adaPerubahan = false;
        $this->diperbaruiPada = $repo->temukan($id)['diperbarui_pada'] ?? now()->toIso8601String();
        unset($this->drafTerbaru);

        return $id;
    }

    /**
     * Isi nama kegiatan dari sub kegiatan RKTM terpilih (nama cover ikut otomatis).
     */
    public function pakaiNamaDariRktm(): void
    {
        $this->pastikanDapatDiedit();
        $rktm = MasterKode::cari(MasterKode::kodeRktm(), $this->form->kode_rktm);

        if ($rktm === null) {
            return;
        }

        $this->form->nama_kegiatan = KegiatanForm::tanpaAwalanKegiatan($rktm['sub_kegiatan']);
        $this->form->syncNamaCover();
        $this->adaPerubahan = true;
        $this->form->validateOnly('nama_kegiatan');
    }

    /**
     * Hasil hitung RAB yang sudah tersusun untuk tiap sumber dana terpilih (total KAK = total RAB).
     *
     * @return array<string, HasilRab>
     */
    #[Computed]
    public function rekapRab(): array
    {
        if ($this->kegiatanId === null) {
            return [];
        }

        $rekap = [];
        foreach ($this->form->sumberDana() as $sumber) {
            $rincian = app(RabRepository::class)->rincian($this->kegiatanId, $sumber['sumber']);
            $rekap[$sumber['sumber']] = KalkulatorRab::hitung($rincian, $sumber['pagu']);
        }

        return $rekap;
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function kelengkapan(): array
    {
        return $this->form->kelengkapan();
    }

    /**
     * @return array<string, array{label: string, dari: list<StatusKegiatan>, ke: StatusKegiatan, peran: list<string>, gaya: string, ikon: string, konfirmasi: string}>
     */
    #[Computed]
    public function aksiStatus(): array
    {
        return $this->kegiatanId === null ? [] : $this->statusKegiatan()->aksiUntuk($this->peran());
    }

    /**
     * Ubah status lewat aksi alur (ajukan, setujui, kembalikan, finalkan, buka kunci, tarik).
     */
    public function ubahStatus(string $aksi): void
    {
        $definisi = $this->aksiStatus[$aksi] ?? null;

        if ($definisi === null) {
            $this->dispatch('notify', message: 'Aksi tidak tersedia untuk status atau peran ini.', type: 'error');

            return;
        }

        if ($aksi === 'ajukan') {
            try {
                $this->form->validate();

                if ($wajib = $this->form->kodeWajibKosong()) {
                    throw ValidationException::withMessages($wajib);
                }
            } catch (ValidationException $e) {
                $this->dispatch('notify', message: 'Lengkapi isian yang ditandai merah sebelum mengajukan.', type: 'error');

                throw $e;
            }
        }

        $this->status = $definisi['ke']->value;
        $this->catatRiwayat($aksi, $definisi['ke']);

        $repo = app(KegiatanRepository::class);
        $repo->simpan($this->dataUntukDisimpan(), $this->kegiatanId);

        $this->adaPerubahan = false;
        $this->diperbaruiPada = $repo->temukan($this->kegiatanId)['diperbarui_pada'] ?? now()->toIso8601String();
        unset($this->aksiStatus, $this->drafTerbaru);

        $this->dispatch('notify', message: 'Status kegiatan: '.$definisi['ke']->label().'.');
    }

    private function catatRiwayat(string $aksi, StatusKegiatan $ke): void
    {
        $pengguna = StubData::currentUser();

        $this->riwayatStatus[] = [
            'aksi' => $aksi,
            'status' => $ke->value,
            'oleh' => $pengguna['name'],
            'peran' => $pengguna['role'],
            'pada' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function dataUntukDisimpan(): array
    {
        return ['status' => $this->status, 'riwayat_status' => $this->riwayatStatus] + $this->form->dataUntukDisimpan();
    }

    private function peran(): string
    {
        return StubData::currentUser()['role'];
    }

    public function render()
    {
        return view('livewire.kegiatan.form')
            ->title($this->kegiatanId ? ($this->form->nama_kegiatan ?: 'Kegiatan') : 'Kegiatan Baru');
    }
}
