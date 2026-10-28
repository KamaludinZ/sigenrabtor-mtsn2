<?php

namespace App\Livewire\Kegiatan;

use App\Contracts\ArsipFinalRepository;
use App\Contracts\DataPaguRepository;
use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Enums\SumberDana;
use App\Livewire\Forms\KegiatanForm;
use App\Support\Dokumen\SusunanKak;
use App\Support\Format;
use App\Support\Kegiatan\AksesKegiatan;
use App\Support\Kegiatan\ArsipFinal;
use App\Support\Kegiatan\FormSections;
use App\Support\Kegiatan\KalimatKak;
use App\Support\Kegiatan\KodeHilang;
use App\Support\Kegiatan\KunciFinal;
use App\Support\Kegiatan\KunciKegiatan;
use App\Support\Kegiatan\PerbaikanKegiatan;
use App\Support\Kegiatan\RiwayatStatus;
use App\Support\Kegiatan\SaranPelaksanaan;
use App\Support\Kegiatan\SimpanKonteks;
use App\Support\Kegiatan\UbahDataKegiatan;
use App\Support\MasterKode;
use App\Support\Pagu\DataPagu;
use App\Support\Pengaturan\TeksBaku;
use App\Support\Rab\AnggaranKegiatan;
use App\Support\Rab\AnggaranSumber;
use App\Support\Rab\BatasPagu;
use App\Support\Rab\HasilRab;
use App\Support\Rab\MekanismePembayaran;
use App\Support\Stub\StubData;
use App\Support\Teks;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use RuntimeException;

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

    /** @var array<string, array{kode: string, asal: string, tahun: int|null}> Catatan kode master yang dikosongkan saat diduplikat/diimpor (KodeHilang). */
    #[Locked]
    public array $kodeHilang = [];

    /** Id kegiatan tersimpan; null = kegiatan baru yang belum pernah disimpan. */
    #[Locked]
    public ?int $kegiatanId = null;

    #[Locked]
    public ?string $diperbaruiPada = null;

    /** Ada perubahan sejak terakhir disimpan/dimuat (untuk penanda & peringatan saat meninggalkan halaman). */
    public bool $adaPerubahan = false;

    /** Panel pratinjau KAK/TOR; isi pratinjau hanya dirender selama panel terbuka. */
    public bool $pratinjauTerbuka = false;

    /** Sumber dana dokumen yang dipratinjau (KAK dibuat per sumber dana); kosong = sumber pertama. */
    public string $pratinjauSumber = '';

    /** Catatan/alasan aksi status yang mewajibkannya (kembalikan, buka kunci — RiwayatStatus::CATATAN_WAJIB). */
    public string $catatanStatus = '';

    /** @var array{index: int, anggota: array{key: string, nama: string, jabatan: string, peran: string}}|null Untuk "Urungkan" hapus anggota. */
    #[Locked]
    public ?array $panitiaTerhapus = null;

    /** Isi catatan konteks sebelum dikosongkan (untuk "Urungkan"); null bila tidak ada yang dikosongkan. */
    #[Locked]
    public ?string $konteksDikosongkan = null;

    /** Jam (H.i) catatan konteks terakhir tersimpan otomatis; null = belum ada simpan otomatis di sesi ini. */
    #[Locked]
    public ?string $konteksTersimpanPada = null;

    /** Pilihan "Ambil dari Data Pagu" (id); diproses lalu dikosongkan lagi. */
    public string $pilihanPagu = '';

    /** @var array<string, string> Pagu sumber dana yang centangnya dilepas (kunci "dipa"/"komite"), dipakai lagi bila dicentang ulang. */
    #[Locked]
    public array $paguDilepas = [];

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
            // Dari tombol "Buat kegiatan" di Data Pagu: nama & pagu langsung terisi.
            if (is_string($pagu = request()->query('pagu'))) {
                $this->pakaiDataPagu($pagu);
                $this->adaPerubahan = false;
            }

            return;
        }

        $data = AksesKegiatan::pastikan($kegiatan);

        $this->form->isiDari($data);
        $this->kegiatanId = $kegiatan;
        $this->status = StatusKegiatan::tryFrom($data['status'] ?? '')?->value ?? StatusKegiatan::Draf->value;
        $this->riwayatStatus = array_values((array) ($data['riwayat_status'] ?? []));
        $this->kodeHilang = KodeHilang::sisa((array) ($data['kode_hilang'] ?? []), $data);
        $this->diperbaruiPada = $data['diperbarui_pada'] ?? null;
    }

    public function statusKegiatan(): StatusKegiatan
    {
        return StatusKegiatan::from($this->status);
    }

    public function dapatDiedit(): bool
    {
        return $this->statusKegiatan()->dapatDiedit() && $this->tahunTerkunci === null;
    }

    /** Tahun anggaran kegiatan bila terkunci (KunciKegiatan) — isian hanya bisa dilihat. */
    #[Computed]
    public function tahunTerkunci(): ?int
    {
        return KunciKegiatan::tahunTerkunci($this->kegiatanId ? (app(KegiatanRepository::class)->temukan($this->kegiatanId) ?? []) : []);
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

    public function updating(string $property, mixed $nilai = null): void
    {
        if (str_starts_with($property, 'form.')) {
            $this->pastikanDapatDiedit();
        }

        // Catatan konteks yang tadinya berisi dikosongkan: simpan isinya untuk Urungkan dan beri tahu dampaknya pada AI.
        if ($property === 'form.konteks') {
            $sebelum = trim($this->form->konteks);

            if ($sebelum !== '' && trim((string) $nilai) === '') {
                $this->konteksDikosongkan = $this->form->konteks;
                $this->dispatch('notify', message: 'Catatan konteks dikosongkan. “Buat dengan AI” hanya memakai isian lain sehingga hasilnya lebih umum.', type: 'info');
            } elseif (trim((string) $nilai) !== '') {
                $this->konteksDikosongkan = null;
            }
        }
    }

    public function urungkanKosongkanKonteks(): void
    {
        $this->pastikanDapatDiedit();

        if ($this->konteksDikosongkan === null) {
            return;
        }

        $this->form->konteks = $this->konteksDikosongkan;
        $this->konteksDikosongkan = null;
        $this->adaPerubahan = true;
        // Pengosongan tadi ikut tersimpan otomatis, jadi isi yang dipulihkan juga disimpan kembali.
        $this->simpanKonteksOtomatis();
    }

    /**
     * Susunan KAK/TOR dari isian saat ini (termasuk yang belum disimpan) untuk panel pratinjau.
     */
    #[Computed]
    public function susunanKak(): SusunanKak
    {
        return SusunanKak::dari(
            $this->form->dataUntukDisimpan() + ['id' => $this->kegiatanId, 'status' => $this->status, 'riwayat_status' => $this->riwayatStatus],
            $this->pratinjauSumber ?: null,
        );
    }

    /**
     * Susunan bagian 1 (Latar Belakang) dari isian saat ini: paragraf baku & kalimat penutup tahun aktif (diatur admin di
     * Pengaturan › Dasar hukum) yang mengapit paragraf khusus, serta variabel kegiatan penutup yang belum diisi.
     *
     * @return array{baku: string, penutup: string, kosong: list<string>}
     */
    #[Computed]
    public function susunanLatar(): array
    {
        $data = $this->form->dataUntukDisimpan();
        $tahun = StubData::tahunAktif()['tahun'];

        return [
            'baku' => StubData::latarBelakangBaku($tahun),
            'penutup' => KalimatKak::latarBelakangKegiatan($data, $tahun),
            'kosong' => TeksBaku::kosong(StubData::kalimatPenutupLatar($tahun), TeksBaku::nilaiKegiatan($data)),
        ];
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
            'bulan_pelaksanaan' => $this->form->rapikanBulan(),
            'kode_sk' => $this->form->sinkronIksk(),
            'pagu_dipa', 'pagu_komite' => $this->form->rapikanPagu($field),
            'dana_dipa', 'dana_komite' => $this->gantiPilihanSumber(substr($field, 5)),
            default => null,
        };

        // Konteks tidak dirapikan di tempat: nilainya dikirim saat leading masih menulis (simpan otomatis), dan perubahan
        // dari server akan menimpa ketikan. Spasinya dirapikan saat disimpan (dataUntukDisimpan / simpanKonteksOtomatis).
        if ($field !== 'konteks') {
            $this->form->rapikanUraian($field);
        }

        // Isian terkait divalidasi lebih dulu (errornya disimpan), lalu isian utama dengan alur Livewire biasa.
        $this->validasiIsianTerkait(self::ISIAN_TERKAIT[$field] ?? []);
        $this->form->validateOnly($field);

        if ($field === 'konteks') {
            $this->simpanKonteksOtomatis();
        }
    }

    /**
     * RAB kegiatan ini berubah di tab lain: render ulang menghitung ulang ringkasan RAB, status pagu, dan termin. Isian form
     * yang sedang disunting (termasuk pagu) tidak ditimpa.
     */
    #[On('anggaran-diperbarui')]
    public function anggaranDiperbarui(int $kegiatan): void
    {
        if ($kegiatan === $this->kegiatanId) {
            $this->dispatch('notify', message: 'Total RAB diperbarui dari tab lain.', type: 'info');
        }
    }

    /**
     * Mencentang/melepas sumber dana ("dipa"/"komite"). Pagu sumber yang dilepas dikosongkan (tidak ikut disimpan) tetapi
     * diingat, lalu dipakai lagi bila sumber itu dicentang ulang sebelum pagunya diisi baru — salah klik tidak menghapus pagu.
     */
    private function gantiPilihanSumber(string $kunci): void
    {
        $paguField = 'pagu_'.$kunci;
        $label = SumberDana::dariKunci($kunci)?->value;

        if (! $this->form->{'dana_'.$kunci}) {
            if (filled($this->form->{$paguField})) {
                $this->paguDilepas[$kunci] = $this->form->{$paguField};
            }
            $this->form->{$paguField} = '';
            $this->resetErrorBag('form.'.$paguField);

            return;
        }

        if (blank($this->form->{$paguField}) && isset($this->paguDilepas[$kunci])) {
            $this->form->{$paguField} = $this->paguDilepas[$kunci];
            $this->dispatch('notify', message: "Pagu {$label} Rp{$this->paguDilepas[$kunci]} dipakai lagi.", type: 'info');
        }
        unset($this->paguDilepas[$kunci]);
    }

    /** Pilih lagi sumber dana yang dilepas (tombol pada peringatan RAB yang tidak ikut dicetak). */
    public function pilihSumberLagi(string $kunci): void
    {
        $this->pastikanDapatDiedit();

        if (SumberDana::dariKunci($kunci) === null || $this->form->{'dana_'.$kunci}) {
            return;
        }

        $this->form->{'dana_'.$kunci} = true;
        $this->adaPerubahan = true;
        $this->gantiPilihanSumber($kunci);
        $this->validasiIsianTerkait(['pagu_'.$kunci]);
    }

    /**
     * Simulasi persistence catatan konteks: kegiatan yang sudah tersimpan menyimpan catatannya sendiri (tanpa menunggu
     * "Simpan Draf"), sehingga catatan tidak hilang walau isian lain belum disimpan. Kegiatan baru cukup draf lokal.
     */
    private function simpanKonteksOtomatis(): void
    {
        if ($this->kegiatanId === null || ! $this->dapatDiedit()) {
            return;
        }

        // Hanya kolom konteks yang ditulis (SimpanKonteks); isian lain yang belum disimpan tetap di layar.
        try {
            SimpanKonteks::simpan($this->kegiatanId, $this->form->konteks);
        } catch (RuntimeException) {
            return;
        }

        $this->konteksTersimpanPada = now()->format('H.i');
        // Teks yang baru disimpan ikut dikirim: klien tidak mengirim ulang nilai yang sama dari pewaktu simpan otomatis.
        $this->dispatch('konteks-tersimpan', pada: $this->konteksTersimpanPada, teks: $this->form->konteks);
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

    /**
     * Saran tempat & jenis peserta: riwayat isian kegiatan lain lalu saran baku madrasah.
     *
     * @return array{tempat: list<array{nilai: string, riwayat: bool}>, peserta: list<array{nilai: string, riwayat: bool}>}
     */
    #[Computed]
    public function saranPelaksanaan(): array
    {
        return SaranPelaksanaan::untuk(app(KegiatanRepository::class));
    }

    public function saveDraft(): void
    {
        $this->pastikanDapatDiedit();
        $baru = $this->kegiatanId === null;
        $id = $this->simpan();
        if ($id === null) {
            return;
        }

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
        if ($id === null) {
            return;
        }

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
     * Validasi & simpan data kegiatan; mengembalikan id kegiatan, atau null bila kegiatan terkunci di tempat lain sejak
     * halaman dimuat (mis. diajukan dari tab lain — status & riwayat tersimpan tidak ditimpa). Kegiatan tersimpan diubah
     * lewat UbahDataKegiatan (hanya isian data kegiatan; status, riwayat, dan kolom lain tersimpan tetap).
     */
    private function simpan(): ?int
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
            $id = $repo->simpan($this->dataUntukDisimpan());
        } else {
            try {
                UbahDataKegiatan::ubah($id = $this->kegiatanId, $this->form->dataUntukDisimpan(), StubData::currentUser());
            } catch (AuthorizationException $e) {
                $tersimpan = $repo->temukan($this->kegiatanId) ?? [];
                $this->status = StatusKegiatan::tryFrom($tersimpan['status'] ?? '')?->value ?? $this->status;
                $this->riwayatStatus = array_values((array) ($tersimpan['riwayat_status'] ?? $this->riwayatStatus));
                unset($this->aksiStatus, $this->tahunTerkunci);
                $this->dispatch('notify', message: $e->getMessage(), type: 'error');

                return null;
            } catch (ValidationException $e) {
                $this->dispatch('notify', message: 'Periksa kembali isian yang ditandai merah.', type: 'error');

                throw ValidationException::withMessages(collect($e->errors())->mapWithKeys(fn (array $pesan, string $isian) => ['form.'.$isian => $pesan])->all());
            }
        }

        $this->adaPerubahan = false;
        $this->form->rapikanPanitia();
        $this->form->rapikanPelaksanaan();
        $this->diperbaruiPada = $repo->temukan($id)['diperbarui_pada'] ?? now()->toIso8601String();
        // Draf lokal di browser (mis. catatan konteks) boleh dibuang: datanya sudah aman di server.
        $this->dispatch('kegiatan-tersimpan', id: $id);
        // Pagu mungkin berubah: tab lain (RAB, daftar) memuat ulang status pagunya.
        $this->dispatch('anggaran-berubah', kegiatan: $id);
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

    /** Pilihan pada combobox "Ambil dari Data Pagu". */
    public function updatedPilihanPagu(): void
    {
        if ($this->pilihanPagu !== '') {
            $this->pakaiDataPagu($this->pilihanPagu);
        }
        $this->pilihanPagu = '';
    }

    /**
     * Isi nama kegiatan, leading (bila boleh dipakai akun ini), dan sumber dana + pagu dari satu Data Pagu, lalu tautkan
     * kegiatan ke data itu. Sumber dana mengikuti pagu yang terisi di Data Pagu; semuanya tetap bisa diubah.
     */
    public function pakaiDataPagu(string $id): void
    {
        $this->pastikanDapatDiedit();
        $data = collect(DataPagu::untuk(StubData::currentUser()))->firstWhere('id', $id);
        if ($data === null) {
            return;
        }
        $dipakai = DataPagu::pemakaian([$data])[$data['id']];
        if ($dipakai !== null && $dipakai['id'] !== $this->kegiatanId) {
            $this->dispatch('notify', message: "\"{$data['nama_kegiatan']}\" sudah menjadi kegiatan; buka kegiatan itu dari Data Kegiatan.", type: 'error');

            return;
        }

        $this->form->nama_kegiatan = KegiatanForm::tanpaAwalanKegiatan($data['nama_kegiatan']);
        $this->form->syncNamaCover();
        $bolehLeading = collect(MasterKode::leadingsUntuk(StubData::currentUser()))->contains('kode', $data['leading']);
        if ($data['leading'] && $bolehLeading) {
            $this->form->leading = $data['leading'];
        }
        foreach (SumberDana::cases() as $sumber) {
            $pagu = $data['pagu'][$sumber->value] ?? null;
            $this->form->{'dana_'.$sumber->kunci()} = $pagu !== null;
            $this->form->{'pagu_'.$sumber->kunci()} = $pagu !== null ? Format::angka($pagu) : '';
        }
        $this->paguDilepas = [];
        $this->form->data_pagu_id = $data['id'];
        $this->adaPerubahan = true;
        $this->resetErrorBag(['form.nama_kegiatan', 'form.nama_kegiatan_cover', 'form.pagu_dipa', 'form.pagu_komite']);
        $this->dispatch('notify', message: 'Nama & pagu diisi dari Data Pagu: '.$data['nama_kegiatan'].'.', type: 'success');
    }

    /** Lepas tautan ke Data Pagu (isian nama & pagu tetap). */
    public function lepasDataPagu(): void
    {
        $this->pastikanDapatDiedit();
        $this->form->data_pagu_id = null;
        $this->adaPerubahan = true;
    }

    /**
     * Pilihan Data Pagu untuk combobox: yang terlihat akun ini dan belum menjadi kegiatan lain.
     *
     * @return list<array{nilai: string, label: string, keterangan: string, grup: string}>
     */
    #[Computed]
    public function opsiDataPagu(): array
    {
        $data = DataPagu::untuk(StubData::currentUser());
        $dipakai = DataPagu::pemakaian($data);

        return array_values(array_map(fn (array $p) => [
            'nilai' => $p['id'],
            'label' => $p['nama_kegiatan'],
            'keterangan' => DataPagu::keterangan($p),
            'grup' => $p['leading'] ? 'Leading '.$p['leading'] : 'Tanpa leading',
        ], array_filter($data, fn (array $p) => $dipakai[$p['id']] === null || $dipakai[$p['id']]['id'] === $this->kegiatanId)));
    }

    /**
     * Data Pagu yang tertaut ke kegiatan ini (null bila tidak ada / sudah dihapus).
     *
     * @return array<string, mixed>|null
     */
    public function dataPaguTertaut(): ?array
    {
        return $this->form->data_pagu_id ? app(DataPaguRepository::class)->temukan($this->form->data_pagu_id) : null;
    }

    /**
     * Anggaran tiap sumber dana terpilih: pagu dari isian yang sedang diubah (belum tentu tersimpan),
     * rincian dari RAB tersimpan.
     *
     * @return array<string, AnggaranSumber>
     */
    #[Computed]
    public function anggaranRab(): array
    {
        if ($this->kegiatanId === null) {
            return [];
        }

        return AnggaranKegiatan::perSumber(['id' => $this->kegiatanId, 'sumber_dana' => $this->form->sumberDana()]);
    }

    /**
     * RAB tersimpan milik sumber dana yang sedang tidak dipilih (tidak dicetak), berkunci nama sumber.
     *
     * @return array<string, AnggaranSumber>
     */
    #[Computed]
    public function rabTerlepas(): array
    {
        if ($this->kegiatanId === null) {
            return [];
        }

        $anggaran = AnggaranKegiatan::untuk(['id' => $this->kegiatanId, 'sumber_dana' => $this->form->sumberDana()], sertakanTerlepas: true);

        return array_column(array_map(
            fn (AnggaranSumber $a) => ['sumber' => $a->sumber, 'anggaran' => $a],
            array_filter($anggaran, fn (AnggaranSumber $a) => ! $a->dipilih && $a->jumlahItem() > 0),
        ), 'anggaran', 'sumber');
    }

    /**
     * Hasil hitung RAB yang sudah tersusun untuk tiap sumber dana terpilih (total KAK = total RAB).
     *
     * @return array<string, HasilRab>
     */
    #[Computed]
    public function rekapRab(): array
    {
        return array_map(fn (AnggaranSumber $anggaran) => $anggaran->hasil, $this->anggaranRab);
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

        if ($this->tahunTerkunci !== null) {
            $this->dispatch('notify', message: KunciKegiatan::pesanTahun($this->tahunTerkunci), type: 'error');

            return;
        }

        if ($aksi === 'finalkan' && ($kurang = $this->arsipKurang()) !== []) {
            $this->dispatch('notify', message: ArsipFinal::pesanKurang($kurang), type: 'error');

            return;
        }

        $wajibCatatan = isset(RiwayatStatus::CATATAN_WAJIB[$aksi]);
        if ($wajibCatatan) {
            $this->resetErrorBag('catatanStatus');
            $this->catatanStatus = RiwayatStatus::rapikanCatatan($this->catatanStatus);
            $this->validate(...RiwayatStatus::validasiCatatan($aksi, 'catatanStatus'));
        }

        if ($aksi === 'ajukan') {
            try {
                $this->form->validate();

                if ($wajib = $this->form->wajibPengajuanKosong() + $this->terminTidakPas() + $this->paguTerlampaui()) {
                    throw ValidationException::withMessages($wajib);
                }
            } catch (ValidationException $e) {
                $jumlah = count($e->errors());
                $this->dispatch('notify', message: "Lengkapi {$jumlah} isian yang ditandai merah sebelum mengajukan.", type: 'error');

                throw $e;
            }
        }

        if (in_array($aksi, KunciFinal::AKSI, true)) {
            $this->ubahKunci($aksi);

            return;
        }

        $this->status = $definisi['ke']->value;
        $this->catatRiwayat($aksi, $definisi['ke'], $wajibCatatan ? $this->catatanStatus : null);
        $this->catatanStatus = '';
        if (in_array($aksi, PerbaikanKegiatan::AKSI, true)) {
            // Titik awal perbaikan di data (sama dengan keputusan di halaman Pemeriksaan).
            $this->riwayatStatus[array_key_last($this->riwayatStatus)]['sidik'] = PerbaikanKegiatan::sidik(['id' => $this->kegiatanId] + $this->dataUntukDisimpan());
        }

        $repo = app(KegiatanRepository::class);
        $repo->simpan($this->dataUntukDisimpan(), $this->kegiatanId);

        $this->adaPerubahan = false;
        $this->diperbaruiPada = $repo->temukan($this->kegiatanId)['diperbarui_pada'] ?? now()->toIso8601String();
        unset($this->aksiStatus, $this->drafTerbaru);

        $this->dispatch('notify', message: 'Status kegiatan: '.$definisi['ke']->label().'.');
    }

    /**
     * Kunci final / buka kunci lewat KunciFinal — jalur yang sama dengan endpoint `kegiatan.kunci.pasang`/`buka`; status &
     * riwayat diambil dari data tersimpan (isian form tidak ikut disimpan: selama Disetujui/Final isian terkunci).
     */
    private function ubahKunci(string $aksi): void
    {
        $pengguna = StubData::currentUser();
        try {
            $hasil = $aksi === 'finalkan' ? KunciFinal::kunci($this->kegiatanId, $pengguna) : KunciFinal::buka($this->kegiatanId, $this->catatanStatus, $pengguna);
        } catch (ValidationException $e) {
            if (isset($e->errors()['catatan'])) {
                throw ValidationException::withMessages(['catatanStatus' => $e->errors()['catatan']]);
            }
            $this->dispatch('notify', message: collect($e->errors())->flatten()->first(), type: 'error');

            return;
        } catch (AuthorizationException $e) {
            $this->arsipFinalBerubah();
            $this->dispatch('notify', message: $e->getMessage(), type: 'error');

            return;
        }

        $this->status = $hasil['kegiatan']['status'];
        $this->riwayatStatus = array_values((array) $hasil['kegiatan']['riwayat_status']);
        $this->catatanStatus = '';
        $this->adaPerubahan = false;
        $this->diperbaruiPada = $hasil['kegiatan']['diperbarui_pada'] ?? now()->toIso8601String();
        unset($this->aksiStatus, $this->drafTerbaru);

        $this->dispatch('notify', message: 'Status kegiatan: '.StatusKegiatan::from($this->status)->label().'.');
    }

    /**
     * Sumber dana yang arsip finalnya belum diunggah (syarat "Tandai final", PRD F18).
     *
     * @return list<string>
     */
    public function arsipKurang(): array
    {
        if ($this->kegiatanId === null) {
            return [];
        }

        return ArsipFinal::kurang(['id' => $this->kegiatanId] + $this->form->dataUntukDisimpan(), app(ArsipFinalRepository::class)->untukKegiatan($this->kegiatanId));
    }

    /** Panel arsip final berubah: render ulang agar syarat "Tandai final" di panel status ikut diperbarui. */
    #[On('arsip-final-berubah')]
    public function arsipFinalBerubah(): void
    {
        // Unggahan yang melengkapi arsip memfinalkan kegiatan otomatis (FinalOtomatis): ikuti status & riwayat tersimpan.
        $tersimpan = app(KegiatanRepository::class)->temukan($this->kegiatanId) ?? [];
        $this->status = StatusKegiatan::tryFrom($tersimpan['status'] ?? '')?->value ?? $this->status;
        $this->riwayatStatus = array_values((array) ($tersimpan['riwayat_status'] ?? $this->riwayatStatus));
        $this->diperbaruiPada = $tersimpan['diperbarui_pada'] ?? $this->diperbaruiPada;
        unset($this->aksiStatus, $this->drafTerbaru);
    }

    /**
     * Perbaikan yang sedang berjalan (status Dikembalikan): catatan pemeriksa & bagian data tersimpan yang sudah berubah
     * sejak dikembalikan. Dibaca dari data tersimpan, jadi isian yang belum disimpan belum terhitung.
     */
    public function perbaikan(): ?PerbaikanKegiatan
    {
        if ($this->kegiatanId === null || $this->status !== StatusKegiatan::Dikembalikan->value) {
            return null;
        }

        $perbaikan = PerbaikanKegiatan::dari(app(KegiatanRepository::class)->temukan($this->kegiatanId) ?? []);

        return $perbaikan && ! $perbaikan->diajukanUlang ? $perbaikan : null;
    }

    /**
     * Termin tersimpan yang jumlahnya tidak lagi sama dengan total RAB (mis. rincian berubah setelah termin dibuat),
     * berkunci error per sumber dana: form.termin_dipa, form.termin_komite.
     *
     * @return array<string, string>
     */
    public function terminTidakPas(): array
    {
        $galat = [];

        foreach ($this->anggaranRab as $sumber => $anggaran) {
            if ($pesan = TerminPembayaran::pesanTidakPas(MekanismePembayaran::dari($anggaran), $sumber)) {
                $galat['form.termin_'.mb_strtolower($sumber)] = $pesan.' Sesuaikan di halaman RAB › Termin pembayaran.';
            }
        }

        return $galat;
    }

    /**
     * RAB yang melebihi pagu menghalangi pengajuan (PRD aturan 5, BatasPagu); galat di isian pagu sumber dananya.
     *
     * @return array<string, string>
     */
    public function paguTerlampaui(): array
    {
        $galat = [];
        foreach (BatasPagu::terlampaui($this->anggaranRab) as $sumber => $pesan) {
            $galat['form.pagu_'.mb_strtolower($sumber)] = $pesan;
        }

        return $galat;
    }

    private function catatRiwayat(string $aksi, StatusKegiatan $ke, ?string $catatan = null): void
    {
        $this->riwayatStatus[] = RiwayatStatus::entri($aksi, $ke, StubData::currentUser(), $catatan);
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * Penanda kode master yang hilang (KodeHilang) untuk kegiatan tersimpan; kode dipilih di halaman Kode & Klasifikasi.
     *
     * @return list<array<string, mixed>>
     */
    public function penandaKodeHilang(): array
    {
        $tersimpan = $this->kegiatanId ? app(KegiatanRepository::class)->temukan($this->kegiatanId) : null;

        return $tersimpan === null ? [] : KodeHilang::dari(['kode_hilang' => $this->kodeHilang] + $this->form->dataUntukDisimpan() + $tersimpan);
    }

    private function dataUntukDisimpan(): array
    {
        $data = $this->form->dataUntukDisimpan();

        // Penanda kode hilang ikut tersimpan selama kolomnya belum diisi lagi.
        return ['status' => $this->status, 'riwayat_status' => $this->riwayatStatus, 'kode_hilang' => KodeHilang::sisa($this->kodeHilang, $data)] + $data;
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
