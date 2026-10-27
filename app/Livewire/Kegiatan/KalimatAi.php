<?php

namespace App\Livewire\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Contracts\MatrikJadwalRepository;
use App\Contracts\PenyusunSaranKalimat;
use App\Enums\StatusKegiatan;
use App\Livewire\Forms\KegiatanForm;
use App\Support\Dokumen\SusunanKak;
use App\Support\Kegiatan\BagianAi;
use App\Support\Kegiatan\BatasAi;
use App\Support\Kegiatan\GagalUsulanAi;
use App\Support\Kegiatan\JadwalKegiatan;
use App\Support\Kegiatan\KonteksAi;
use App\Support\Kegiatan\PermintaanSaranKalimat;
use App\Support\Kegiatan\TombolAi;
use App\Support\Stub\StubData;
use App\Support\Stub\StubPengaturan;
use App\Support\Teks;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Halaman Kalimat KAK satu kegiatan (PRD §20, F23; tab ruang kerja "Kalimat KAK"): enam bagian yang bisa dibantu AI
 * (BagianAi::BAGIAN — nama cover, latar belakang, tujuan, sasaran, pelaksanaan, jadwal) dalam satu formulir: isian teks,
 * bahan dari data kegiatan untuk AI, tempat tercetak, dan pratinjau bagian KAK. Kolom yang disunting hanya
 * BagianAi::KOLOM (+ cover_manual) dan disimpan dengan menggabungkan record tersimpan; jadwal disunting di Matrik & Jadwal.
 * Tombol "Buat dengan AI" (<x-ai.tombol-buat>, keadaan TombolAi) meminta 2–3 versi ke PenyusunSaranKalimat dalam batas
 * harian BatasAi; versi tampil di modal saran (pilih versi, sunting, pakai → isian belum tersimpan + Urungkan di kartu,
 * atau tolak).
 */
#[Layout('layouts.app')]
class KalimatAi extends Component
{
    #[Locked]
    public int $kegiatanId;

    /** @var array<string, mixed> */
    #[Locked]
    public array $dataKegiatan = [];

    public KegiatanForm $form;

    public bool $adaPerubahan = false;

    /** @var array<string, string> Teks tersimpan ketika halaman dimuat/terakhir disimpan (pembanding perubahan & bentrok). */
    #[Locked]
    public array $teksDimuat = [];

    /** @var array<string, array{0: string, 1: string}>|null Kolom yang disimpan pihak lain sejak dimuat → [dimuat, tersimpan]. */
    #[Locked]
    public ?array $bentrok = null;

    /** @var array<string, list<array{judul: string, ciri: string, teks: string}>> Saran AI terakhir per bagian (BagianAi). */
    #[Locked]
    public array $saran = [];

    /** @var array<string, string> Pesan gagal permintaan AI terakhir per bagian. */
    #[Locked]
    public array $galatAi = [];

    /** Bagian yang modal sarannya terbuka. */
    #[Locked]
    public ?string $saranTerbuka = null;

    /** Indeks versi saran yang dipilih di modal. */
    #[Locked]
    public int $versiDipilih = 0;

    /** Teks versi terpilih yang sedang disunting di modal (state klien, terkirim bersama aksi berikutnya). */
    public string $suntingSaran = '';

    /** @var array<string, string> Teks isian sebelum saran AI dipakai (bagian → teks), untuk Urungkan. */
    #[Locked]
    public array $teksSebelum = [];

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
            abort_unless(in_array(substr($property, 5), [...BagianAi::KOLOM, 'cover_manual'], true), 403, 'Isian ini diubah di halaman Data Kegiatan.');
        }
    }

    public function updated(string $property): void
    {
        if (! str_starts_with($property, 'form.') || $property === 'form.cover_manual') {
            return;
        }

        $field = substr($property, 5);
        if ($field === 'nama_kegiatan_cover') {
            $this->form->setNamaCoverManual();
        }
        $this->form->rapikanUraian($field);
        $this->adaPerubahan = $this->isianBerubah() !== [];
        $this->form->validateOnly($field);
    }

    public function resetNamaCover(): void
    {
        $this->pastikanDapatDiedit();
        $this->form->resetNamaCover();
        $this->adaPerubahan = $this->isianBerubah() !== [];
        $this->form->validateOnly('nama_kegiatan_cover');
    }

    /**
     * Teks yang sedang disunting (kolom BagianAi::KOLOM + cover_manual).
     *
     * @return array<string, mixed>
     */
    public function teksSekarang(): array
    {
        return array_intersect_key($this->form->dataUntukDisimpan(), array_flip([...BagianAi::KOLOM, 'cover_manual']));
    }

    /**
     * Kolom yang berbeda dari versi tersimpan.
     *
     * @return list<string>
     */
    public function isianBerubah(): array
    {
        $sekarang = self::baku($this->teksSekarang());

        return array_values(array_filter(BagianAi::KOLOM, fn (string $k) => ($this->teksDimuat[$k] ?? '') !== $sekarang[$k]));
    }

    public function simpan(bool $timpa = false): void
    {
        $this->pastikanDapatDiedit();

        try {
            foreach (BagianAi::KOLOM as $field) {
                $this->form->validateOnly($field);
            }
        } catch (ValidationException $e) {
            $this->dispatch('notify', message: 'Periksa kembali isian yang ditandai merah.', type: 'error');

            throw $e;
        }

        $repo = app(KegiatanRepository::class);
        $tersimpan = $repo->temukan($this->kegiatanId) ?? $this->dataKegiatan;
        $teksTersimpan = self::baku($tersimpan);

        $bentrok = array_filter(BagianAi::KOLOM, fn (string $k) => ($this->teksDimuat[$k] ?? '') !== $teksTersimpan[$k]);
        if (! $timpa && $bentrok !== []) {
            $this->bentrok = array_combine(array_values($bentrok), array_map(fn (string $k) => [$this->teksDimuat[$k] ?? '', $teksTersimpan[$k]], array_values($bentrok)));
            $this->dispatch('notify', message: 'Kalimat kegiatan ini baru diubah di tempat lain — pilih muat versi terbaru atau timpa.', type: 'error');

            return;
        }

        $berubah = $this->isianBerubah();
        if ($berubah !== [] || $timpa) {
            $repo->simpan(array_merge($tersimpan, $this->teksSekarang()), $this->kegiatanId);
        }
        $this->muatDari($repo->temukan($this->kegiatanId) ?? $tersimpan);

        $this->dispatch('notify', ...($berubah !== [] || $timpa
            ? ['message' => 'Kalimat KAK tersimpan.', 'type' => 'success']
            : ['message' => 'Tidak ada perubahan kalimat untuk disimpan.', 'type' => 'info']));
    }

    /** Buang suntingan yang belum disimpan. */
    public function batalkanPerubahan(): void
    {
        $this->muatDari(app(KegiatanRepository::class)->temukan($this->kegiatanId) ?? $this->dataKegiatan);
        $this->dispatch('notify', message: 'Perubahan kalimat dibatalkan.', type: 'info');
    }

    /** Saat bentrok: ambil kalimat yang disimpan pihak lain (suntingan di layar ini dibuang). */
    public function muatVersiTerbaru(): void
    {
        $this->muatDari(app(KegiatanRepository::class)->temukan($this->kegiatanId) ?? $this->dataKegiatan);
        $this->dispatch('notify', message: 'Kalimat terbaru dimuat.', type: 'info');
    }

    /**
     * "Buat dengan AI": minta 2–3 versi saran satu bagian ke penyusun (tiruan selama AI belum tersambung). Bahan dari
     * BagianAi::bahan & KonteksAi::teks (tanpa nama/NIP pejabat). Batas harian dicek dulu (BatasAi); kegagalan penyedia
     * tampil di kartu bagian dengan "Coba lagi", bukan sebagai galat halaman.
     */
    public function mintaSaran(string $bagian, PenyusunSaranKalimat $penyusun): void
    {
        $this->pastikanDapatDiedit();
        abort_unless(isset(BagianAi::BAGIAN[$bagian]) && BagianAi::BAGIAN[$bagian]['kolom'] !== null, 404);

        $pengguna = StubData::currentUser();
        $kegiatan = $this->kegiatanSekarang();
        unset($this->galatAi[$bagian]);

        if (blank(trim((string) ($kegiatan['nama_kegiatan'] ?? '')))) {
            $this->galatAi[$bagian] = 'Isi nama kegiatan dulu di Data Kegiatan.';

            return;
        }
        if (BatasAi::sisa($pengguna) <= 0) {
            $this->galatAi[$bagian] = BatasAi::pesanHabis();

            return;
        }

        $bahanBagian = array_column(array_filter(BagianAi::bahan($bagian, $kegiatan), fn (array $b) => $b['nilai'] !== null), 'nilai', 'label');
        $permintaan = new PermintaanSaranKalimat(
            kegiatanId: $this->kegiatanId,
            bagian: $bagian,
            teks: trim((string) ($kegiatan[BagianAi::BAGIAN[$bagian]['kolom']] ?? '')),
            instruksi: '',
            bahanBagian: $bahanBagian,
            bahan: KonteksAi::teks($kegiatan),
            namaKegiatan: (string) $kegiatan['nama_kegiatan'],
        );

        try {
            $saran = self::rapikanSaran($bagian, $penyusun->sarankan($permintaan));
        } catch (GagalUsulanAi $e) {
            $this->galatAi[$bagian] = $e->getMessage();

            return;
        }

        BatasAi::catat($pengguna, $bagian);
        if ($saran === []) {
            $this->galatAi[$bagian] = 'AI tidak memberi saran yang bisa dipakai. Coba lagi.';

            return;
        }

        $this->saran[$bagian] = $saran;
        $this->bukaSaran($bagian);
    }

    /** Buka modal saran satu bagian pada versi pertama. */
    public function bukaSaran(string $bagian): void
    {
        abort_unless(isset($this->saran[$bagian]), 404);
        $this->saranTerbuka = $bagian;
        $this->pilihVersi(0);
    }

    /** Pilih versi saran; suntingan versi sebelumnya dibuang. */
    public function pilihVersi(int $versi): void
    {
        $daftar = $this->saran[$this->saranTerbuka ?? ''] ?? [];
        abort_unless(isset($daftar[$versi]), 404);
        $this->versiDipilih = $versi;
        $this->suntingSaran = $daftar[$versi]['teks'];
        $this->resetErrorBag('suntingSaran');
    }

    /** Kembalikan teks versi terpilih seperti saran AI aslinya. */
    public function kembalikanTeksAi(): void
    {
        $this->pilihVersi($this->versiDipilih);
    }

    /**
     * Pakai versi terpilih (beserta suntingannya) sebagai isian bagian: belum tersimpan sampai "Simpan" dan bisa
     * diurungkan dari kartu bagiannya.
     */
    public function pakaiSaran(): void
    {
        $this->pastikanDapatDiedit();
        $bagian = $this->saranTerbuka;
        abort_unless($bagian !== null && isset($this->saran[$bagian]), 404);

        $kolom = BagianAi::BAGIAN[$bagian]['kolom'];
        $teks = self::rapikanSaran($bagian, [['teks' => $this->suntingSaran]])[0]['teks'] ?? '';
        $this->resetErrorBag('suntingSaran');
        if ($teks === '') {
            $this->addError('suntingSaran', 'Teks saran tidak boleh kosong.');

            return;
        }

        $this->teksSebelum[$bagian] ??= (string) $this->form->{$kolom};
        $this->form->{$kolom} = $teks;
        if ($kolom === 'nama_kegiatan_cover') {
            $this->form->setNamaCoverManual();
        }
        $this->adaPerubahan = $this->isianBerubah() !== [];
        $this->saranTerbuka = null;
        try {
            $this->form->validateOnly($kolom);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());
        }

        $this->dispatch('notify', message: 'Saran AI dipakai untuk '.mb_strtolower(BagianAi::BAGIAN[$bagian]['judul']).' — periksa lalu simpan.', type: 'success');
    }

    /** Kembalikan isian bagian seperti sebelum saran AI dipakai. */
    public function urungkanSaran(string $bagian): void
    {
        $this->pastikanDapatDiedit();
        abort_unless(isset($this->teksSebelum[$bagian]), 404);

        $kolom = BagianAi::BAGIAN[$bagian]['kolom'];
        $this->form->{$kolom} = $this->teksSebelum[$bagian];
        if ($kolom === 'nama_kegiatan_cover') {
            $this->form->setNamaCoverManual();
        }
        unset($this->teksSebelum[$bagian]);
        $this->adaPerubahan = $this->isianBerubah() !== [];
        $this->resetErrorBag('form.'.$kolom);
    }

    /** Tolak seluruh saran bagian yang terbuka: saran dibuang, isian tidak berubah. */
    public function tolakSaran(): void
    {
        $bagian = $this->saranTerbuka;
        abort_unless($bagian !== null, 404);
        unset($this->saran[$bagian]);
        $this->saranTerbuka = null;
        $this->dispatch('notify', message: 'Saran AI ditolak — isian tidak berubah.', type: 'info');
    }

    public function tutupSaran(): void
    {
        $this->saranTerbuka = null;
    }

    /**
     * Keadaan tombol "Buat dengan AI" satu bagian.
     *
     * @return array{status: string, label: string, keterangan: string|null, jumlah: int}
     */
    public function keadaanTombol(string $bagian): array
    {
        return TombolAi::keadaan($bagian, $this->kegiatanSekarang(), $this->dapatDiedit(), $this->sisaAi(), $this->saran[$bagian] ?? [], $this->galatAi[$bagian] ?? null);
    }

    public function sisaAi(): int
    {
        return BatasAi::sisa(StubData::currentUser());
    }

    /**
     * Hasil penyusun dirapikan: maks. 3 versi, teks satu spasi (nama cover kapital satu baris), sesuai batas panjang
     * kolomnya, tanpa versi kosong/kembar.
     *
     * @param  array<int, mixed>  $saran
     * @return list<array{judul: string, ciri: string, teks: string}>
     */
    public static function rapikanSaran(string $bagian, array $saran): array
    {
        $maks = ['nama_cover' => 255, 'latar_belakang' => 5000, 'tujuan' => 2000, 'sasaran' => 2000, 'pelaksanaan' => 3000][$bagian] ?? 2000;
        $hasil = [];
        foreach ($saran as $i => $s) {
            if (! is_array($s)) {
                continue;
            }
            $teks = $bagian === 'nama_cover' ? mb_strtoupper(Teks::rapikan(preg_replace('/\s+/u', ' ', (string) ($s['teks'] ?? '')))) : Teks::rapikan((string) ($s['teks'] ?? ''));
            $teks = mb_substr($teks, 0, $maks);
            if ($teks === '' || in_array($teks, array_column($hasil, 'teks'), true)) {
                continue;
            }
            $hasil[] = ['judul' => Teks::rapikan((string) ($s['judul'] ?? '')) ?: 'Versi '.(count($hasil) + 1), 'ciri' => mb_substr(Teks::rapikan((string) ($s['ciri'] ?? '')), 0, 60), 'teks' => $teks];
        }

        return array_slice($hasil, 0, 3);
    }

    /** Data kegiatan dengan teks yang sedang disunting (bahan AI & pratinjau mengikuti layar). */
    public function kegiatanSekarang(): array
    {
        return array_merge($this->dataKegiatan, $this->teksSekarang());
    }

    #[Computed]
    public function jadwal(): JadwalKegiatan
    {
        return app(MatrikJadwalRepository::class)->jadwal($this->kegiatanId);
    }

    /** Susunan KAK dari teks di layar untuk pratinjau bagian (cover & nomor bagian BagianAi::BAGIAN). */
    public function susunan(): SusunanKak
    {
        $repo = app(MatrikJadwalRepository::class);

        return SusunanKak::dari($this->kegiatanSekarang(), null, $repo->matrik($this->kegiatanId), $this->jadwal);
    }

    /**
     * Ringkasan panel samping: kelengkapan bagian, aspek konteks, penyedia AI.
     *
     * @return array{terisi: array<string, bool>, aspek: array<string, bool>, konteks: bool, penyedia: string|null, batas: int}
     */
    public function ringkasan(): array
    {
        $kegiatan = $this->kegiatanSekarang();
        $konteks = trim((string) ($kegiatan['konteks'] ?? ''));

        return [
            'terisi' => array_map(fn (string $b) => BagianAi::terisi($b, $kegiatan, $this->jadwal), array_combine(array_keys(BagianAi::BAGIAN), array_keys(BagianAi::BAGIAN))),
            'aspek' => KonteksAi::aspekTerisi($konteks),
            'konteks' => $konteks !== '',
            'penyedia' => StubPengaturan::pengaturanAi()['provider_aktif'],
            'batas' => StubPengaturan::pengaturanAi()['batas_per_user_per_hari'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private static function baku(array $data): array
    {
        return array_combine(BagianAi::KOLOM, array_map(fn (string $k) => trim((string) ($data[$k] ?? '')), BagianAi::KOLOM));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function muatDari(array $data): void
    {
        $this->dataKegiatan = $data;
        $this->form->isiDari($data);
        $this->teksDimuat = self::baku($data);
        $this->adaPerubahan = false;
        $this->bentrok = null;
        $this->teksSebelum = [];
        $this->resetValidation();
        unset($this->jadwal);
    }

    private function pastikanDapatDiedit(): void
    {
        if (! $this->dapatDiedit()) {
            abort(403, 'Kegiatan berstatus '.$this->status()->label().' sehingga kalimatnya terkunci.');
        }
    }

    public function render()
    {
        return view('livewire.kegiatan.kalimat-ai', ['tahunAktif' => StubData::tahunAktif()['tahun']])
            ->title('Kalimat KAK · '.($this->dataKegiatan['nama_kegiatan'] ?? 'Kegiatan'));
    }
}
