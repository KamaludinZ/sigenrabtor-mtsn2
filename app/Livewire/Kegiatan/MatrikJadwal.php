<?php

namespace App\Livewire\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Contracts\MatrikJadwalRepository;
use App\Enums\StatusKegiatan;
use App\Support\Dokumen\SusunanKak;
use App\Support\Kegiatan\DataMatrikJadwal;
use App\Support\Kegiatan\DataPelaksanaan;
use App\Support\Kegiatan\GagalUsulanAi;
use App\Support\Kegiatan\JadwalKegiatan;
use App\Support\Kegiatan\KonsistensiBulan;
use App\Support\Kegiatan\KonteksAi;
use App\Support\Kegiatan\KunciKegiatan;
use App\Support\Kegiatan\MatrikTahapan;
use App\Support\Kegiatan\PemeriksaKonsistensi;
use App\Support\Kegiatan\RiwayatSaranAi;
use App\Support\Kegiatan\SaranJadwalAi;
use App\Support\Kegiatan\SimpanMatrikJadwal;
use App\Support\Kegiatan\StatusKuotaAi;
use App\Support\Stub\StubData;
use App\Support\Teks;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use RuntimeException;

/**
 * Halaman Matrik & Jadwal satu kegiatan (PRD F10): matrik tahapan (Persiapan, Pelaksanaan, Evaluasi & Pelaporan)
 * × bulan 1–12 untuk bagian 4 KAK, dan jadwal kegiatan 6 baris baku untuk bagian 7. Bulan pelaksanaan dari Data
 * Kegiatan ditandai sebagai acuan.
 */
#[Layout('layouts.app')]
class MatrikJadwal extends Component
{
    /** Uraian jadwal baku (JadwalKegiatan::URAIAN_BAKU); bulannya diturunkan dari jadwal pelaksanaan saat diisi. */
    public const JADWAL_BAKU = JadwalKegiatan::URAIAN_BAKU;

    /** Instruksi cepat di panel usul jadwal AI (disisipkan ke kotak instruksi, tetap bisa diedit). */
    public const SARAN_INSTRUKSI = ['lebih ringkas', 'persiapan lebih awal', 'tambahkan rapat evaluasi', 'sertakan publikasi kegiatan'];

    #[Locked]
    public int $kegiatanId;

    /** @var array<string, mixed> */
    #[Locked]
    public array $dataKegiatan = [];

    /**
     * Matrik yang sedang disunting: kunci tahapan → butir {uraian, bulan}. Sel diatur sebagai state lokal di klien
     * (seret/klik, tanpa request) dan baru terkirim bersama aksi berikutnya; tersimpan ke data kegiatan saat "Simpan".
     *
     * @var array<string, list<array{uraian: string, bulan: list<int>}>>
     */
    public array $matrik = [];

    /**
     * Jadwal yang sedang disunting: uraian + bulan mulai & "s.d." (opsional). Disimpan sebagai daftar bulan berurutan;
     * uraian tidak dirapikan di tempat (ketikan tidak tertimpa) — spasinya dirapikan saat disimpan.
     *
     * @var list<array{uraian: string, mulai: int|null, sampai: int|null}>
     */
    public array $jadwal = [];

    public bool $adaPerubahan = false;

    /** @var array{matrik: array<string, mixed>, pesan: string}|null Matrik sebelum diisi otomatis/dikosongkan (untuk "Urungkan"). */
    #[Locked]
    public ?array $matrikSebelum = null;

    /** @var array{jadwal: list<array<string, mixed>>, pesan: string}|null Jadwal sebelum diisi baku (untuk "Urungkan"). */
    #[Locked]
    public ?array $jadwalSebelum = null;

    /** Panel pratinjau KAK bagian 4 & 7; membukanya ikut mengirim tanda matrik lokal sehingga pratinjau memuat isian terbaru. */
    public bool $pratinjauTerbuka = false;

    /** Panel "Usulkan dengan AI" (PRD F10 Usul Jadwal AI): permintaan → 2–3 versi usulan → pakai salah satu atau tolak. */
    public bool $usulTerbuka = false;

    /** Catatan penyedia usulan terakhir (mis. dijawab penyedia cadangan). */
    #[Locked]
    public ?string $catatanPenyedia = null;

    /** Id permintaan usulan terakhir di riwayat saran AI (RiwayatSaranAi). */
    #[Locked]
    public ?string $permintaanUsulan = null;

    /** Jenis kegiatan acuan usulan (kunci KonteksAi::jenisKegiatan()); ditebak dari nama kegiatan saat panel dibuka. */
    public string $usulJenis = '';

    /** Instruksi tambahan untuk AI (opsional), mis. "lebih ringkas". */
    public string $usulInstruksi = '';

    /** Pesan bila penyusun usulan (AI) gagal; ditampilkan di panel bersama tombol coba lagi. */
    #[Locked]
    public ?string $usulGagal = null;

    /**
     * Versi usulan terakhir; kosong selama belum dibuat atau sesudah dipakai/ditolak.
     *
     * @var list<array{judul: string, ciri: string, keterangan: string, baris: list<array{uraian: string, mulai: int|null, sampai: int|null}>}>
     */
    #[Locked]
    public array $usulan = [];

    public function mount(KegiatanRepository $repo, int $kegiatan): void
    {
        $this->dataKegiatan = $repo->temukan($kegiatan) ?? abort(404, 'Kegiatan tidak ditemukan.');
        $this->kegiatanId = $kegiatan;
        $this->muatIsian();
    }

    /** Matrik sesuai isian saat ini (termasuk yang belum disimpan): jumlah tanda, bulan per tahapan, kelengkapan. */
    #[Computed]
    public function susunanMatrik(): MatrikTahapan
    {
        return MatrikTahapan::dari(['matrik' => $this->matrik]);
    }

    /** Jadwal sesuai isian saat ini: waktu per baris (tahun aktif), jumlah terisi, kelengkapan. */
    #[Computed]
    public function susunanJadwal(): JadwalKegiatan
    {
        return JadwalKegiatan::dari(['jadwal' => self::jadwalUntukDisimpan($this->jadwal)]);
    }

    /** Konsistensi bulan matrik & jadwal terhadap bulan pelaksanaan dan batas laporan (isian saat ini). */
    #[Computed]
    public function konsistensi(): KonsistensiBulan
    {
        return PemeriksaKonsistensi::bulan($this->dataKegiatan, $this->susunanMatrik, $this->susunanJadwal);
    }

    /** KAK/TOR dari isian saat ini (termasuk yang belum disimpan) untuk pratinjau bagian Tahapan & Jadwal. */
    #[Computed]
    public function pratinjauKak(): SusunanKak
    {
        return SusunanKak::dari($this->dataKegiatan, null, $this->susunanMatrik, $this->susunanJadwal);
    }

    #[Computed]
    public function pelaksanaan(): DataPelaksanaan
    {
        return DataPelaksanaan::dari($this->dataKegiatan);
    }

    /** Isian matrik & jadwal hanya boleh diubah selama kegiatan masih Draf/Dikembalikan. */
    public function updating(string $properti): void
    {
        if (str_starts_with($properti, 'matrik') || str_starts_with($properti, 'jadwal')) {
            $this->pastikanDapatDiedit();
        }
    }

    /**
     * Perubahan sel dari state lokal klien: bulan dirapikan (urut, unik, 1–12; klien sudah mengirim bentuk yang sama),
     * tahapan tak dikenal dibuang, dan "Urungkan" isi otomatis tidak berlaku lagi karena matrik sudah diubah tangan.
     */
    public function updatedMatrik(): void
    {
        $this->matrik = MatrikTahapan::dari(['matrik' => $this->matrik])->butir;
        $this->adaPerubahan = true;
        $this->matrikSebelum = null;
    }

    /** Pilihan bulan dari <select> datang sebagai teks: jadikan angka (kosong → null); "s.d." sebelum bulan mulai dilepas. */
    public function updatedJadwal(mixed $nilai, string $kunci): void
    {
        [$index, $kolom] = array_pad(explode('.', $kunci), 2, null);

        if (isset($this->jadwal[$index]) && in_array($kolom, ['mulai', 'sampai'], true)) {
            $baris = &$this->jadwal[$index];
            $baris[$kolom] = self::bulanAtauNull($baris[$kolom]);

            if ($baris['mulai'] === null || ($baris['sampai'] !== null && $baris['sampai'] <= $baris['mulai'])) {
                $baris['sampai'] = null;
            }
        }

        $this->adaPerubahan = true;
        $this->jadwalSebelum = null;
    }

    /**
     * Isi otomatis dari jadwal pelaksanaan (Data Kegiatan): Persiapan sebulan sebelum bulan pelaksanaan pertama, Pelaksanaan
     * pada bulan pelaksanaan, Evaluasi & Pelaporan pada bulan batas laporan. Bisa diurungkan.
     */
    public function isiDariPelaksanaan(): void
    {
        $this->pastikanDapatDiedit();
        $acuan = $this->acuanBulan();

        if ($acuan === null) {
            $this->dispatch('notify', message: 'Isi bulan pelaksanaan di Data Kegiatan terlebih dahulu.', type: 'error');

            return;
        }

        $usulan = [
            'persiapan' => [$acuan['persiapan']],
            'pelaksanaan' => $acuan['pelaksanaan'],
            'evaluasi_pelaporan' => [$acuan['laporan']],
        ];

        $this->ubahSemua(fn (string $tahapan) => $usulan[$tahapan], 'Matrik diisi dari jadwal pelaksanaan.');
    }

    public function kosongkanMatrik(): void
    {
        $this->pastikanDapatDiedit();
        $this->ubahSemua(fn () => [], 'Semua tanda bulan dikosongkan.');
    }

    public function urungkanMatrik(): void
    {
        $this->pastikanDapatDiedit();

        if ($this->matrikSebelum === null) {
            return;
        }

        $this->matrik = $this->matrikSebelum['matrik'];
        $this->matrikSebelum = null;
        $this->adaPerubahan = true;
    }

    /**
     * Enam baris baku dengan bulan dari jadwal pelaksanaan (persiapan sebulan sebelumnya, pelaksanaan, evaluasi di bulan
     * terakhir, laporan di bulan batas laporan). Tanpa bulan pelaksanaan, hanya uraiannya yang diisi. Bisa diurungkan.
     */
    public function isiJadwalBaku(): void
    {
        $this->pastikanDapatDiedit();
        $acuan = $this->acuanBulan();
        $rentang = fn (?int $mulai, ?int $sampai = null) => ['mulai' => $mulai, 'sampai' => $sampai !== null && $sampai > $mulai ? $sampai : null];

        $waktu = $acuan === null
            ? array_fill(0, count(self::JADWAL_BAKU), $rentang(null))
            : [
                $rentang($acuan['persiapan']),
                $rentang($acuan['persiapan']),
                $rentang($acuan['persiapan'], $acuan['pelaksanaan'][0]),
                $rentang($acuan['pelaksanaan'][0], end($acuan['pelaksanaan'])),
                $rentang(end($acuan['pelaksanaan'])),
                $rentang($acuan['laporan']),
            ];

        $sebelum = $this->jadwal;
        $this->jadwal = array_map(fn (string $uraian, array $w) => ['uraian' => $uraian] + $w, self::JADWAL_BAKU, $waktu);

        if ($this->jadwal !== $sebelum) {
            $this->jadwalSebelum = ['jadwal' => $sebelum, 'pesan' => $acuan === null
                ? 'Uraian jadwal baku diisi; pilih bulannya karena bulan pelaksanaan belum diisi.'
                : 'Jadwal baku diisi dari jadwal pelaksanaan.'];
            $this->adaPerubahan = true;
        }
    }

    public function urungkanJadwal(): void
    {
        $this->pastikanDapatDiedit();

        if ($this->jadwalSebelum === null) {
            return;
        }

        $this->jadwal = $this->jadwalSebelum['jadwal'];
        $this->jadwalSebelum = null;
        $this->adaPerubahan = true;
    }

    public function bukaUsulJadwal(): void
    {
        $this->pastikanDapatDiedit();

        if (! array_key_exists($this->usulJenis, KonteksAi::jenisKegiatan())) {
            $this->usulJenis = KonteksAi::tebakJenis((string) ($this->dataKegiatan['nama_kegiatan'] ?? ''));
        }

        $this->usulTerbuka = true;
    }

    public function tutupUsulJadwal(): void
    {
        $this->usulTerbuka = false;
    }

    /**
     * Minta 2–3 versi usulan ke penyusun (tiruan selama AI belum tersambung). Bahan dari KonteksAi (tanpa nama/NIP
     * pejabat); AI hanya menyusun uraian & bulan. Kegagalan penyedia tampil di panel, bukan sebagai galat halaman.
     */
    public function buatUsulJadwal(): void
    {
        $this->pastikanDapatDiedit();
        // Aturan SaranJadwalAi dengan nama properti panel (jenis → usulJenis, instruksi → usulInstruksi).
        $nama = ['jenis' => 'usulJenis', 'instruksi' => 'usulInstruksi'];
        $this->validate(
            array_combine(array_map(fn (string $k) => $nama[$k], array_keys(SaranJadwalAi::aturan())), SaranJadwalAi::aturan()),
            array_combine(array_map(fn (string $k) => strtr($k, $nama), array_keys(SaranJadwalAi::pesan())), SaranJadwalAi::pesan()),
        );
        $this->usulGagal = null;

        try {
            $hasil = SaranJadwalAi::minta($this->dataKegiatan, StubData::currentUser(), $this->usulJenis, $this->usulInstruksi);
        } catch (GagalUsulanAi $e) {
            $this->usulan = [];
            $this->usulGagal = $e->getMessage();

            return;
        }

        $this->usulan = $hasil['versi'];
        $this->catatanPenyedia = $hasil['catatan'];
        $this->permintaanUsulan = $hasil['permintaan'];
    }

    /** Status kuota AI harian akun yang sedang masuk (panel usulan jadwal). */
    public function statusKuota(): array
    {
        return StatusKuotaAi::untuk(StubData::currentUser());
    }

    /** Ganti seluruh baris jadwal dengan satu versi usulan; bisa diurungkan dan baru tersimpan saat "Simpan". */
    public function pakaiUsulan(int $versi): void
    {
        $this->pastikanDapatDiedit();

        if (! isset($this->usulan[$versi])) {
            return;
        }

        $dipakai = $this->usulan[$versi];
        SaranJadwalAi::putuskan($this->permintaanUsulan, $versi);
        $this->permintaanUsulan = null;
        $sebelum = $this->jadwal;
        $this->jadwal = $dipakai['baris'];
        $this->jadwalSebelum = ['jadwal' => $sebelum, 'pesan' => "Jadwal diganti dengan usulan AI ({$dipakai['judul']}) — periksa lalu simpan."];
        $this->resetValidation();
        $this->adaPerubahan = true;
        $this->usulan = [];
        $this->usulTerbuka = false;
    }

    /** Tolak semua versi lalu kembali ke permintaan (jenis & instruksi tetap, bisa diubah lalu dibuat ulang). */
    public function tolakUsulan(): void
    {
        SaranJadwalAi::putuskan($this->permintaanUsulan, null);
        $this->permintaanUsulan = null;
        $this->usulan = [];
    }

    /** Baris kosong di akhir jadwal (maks. JadwalKegiatan::MAKS_BARIS); kolom uraiannya langsung difokuskan di klien. */
    public function tambahBarisJadwal(): void
    {
        $this->pastikanDapatDiedit();

        if (count($this->jadwal) >= JadwalKegiatan::MAKS_BARIS) {
            $this->dispatch('notify', message: 'Jadwal sudah mencapai batas '.JadwalKegiatan::MAKS_BARIS.' baris.', type: 'error');

            return;
        }

        $this->jadwal[] = ['uraian' => '', 'mulai' => null, 'sampai' => null];
        $this->adaPerubahan = true;
        $this->jadwalSebelum = null;
        $this->dispatch('jadwal-ditambah');
    }

    /** Hapus satu baris; nomor baris sesudahnya ikut naik. Bisa diurungkan sampai jadwal diubah lagi. */
    public function hapusBarisJadwal(int $index): void
    {
        $this->pastikanDapatDiedit();

        if (! isset($this->jadwal[$index])) {
            return;
        }

        $sebelum = $this->jadwal;
        $uraian = Teks::rapikan((string) $this->jadwal[$index]['uraian']);
        array_splice($this->jadwal, $index, 1);

        $this->jadwalSebelum = ['jadwal' => $sebelum, 'pesan' => 'Baris '.($index + 1).($uraian !== '' ? ' “'.Str::limit($uraian, 60).'”' : '').' dihapus.'];
        $this->resetErrorJadwal();
        $this->adaPerubahan = true;
    }

    /** Naikkan (-1) atau turunkan (+1) satu baris — urutan ini yang tercetak di tabel Jadwal Kegiatan KAK. */
    public function geserBarisJadwal(int $index, int $arah): void
    {
        $this->pastikanDapatDiedit();
        $tujuan = $index + ($arah > 0 ? 1 : -1);

        if (! isset($this->jadwal[$index], $this->jadwal[$tujuan])) {
            return;
        }

        [$this->jadwal[$index], $this->jadwal[$tujuan]] = [$this->jadwal[$tujuan], $this->jadwal[$index]];
        $this->resetErrorJadwal();
        $this->adaPerubahan = true;
        $this->jadwalSebelum = null;
    }

    /** Simpan lewat SimpanMatrikJadwal (terpisah dari data kegiatan); bila gagal, isian di layar tetap utuh. */
    public function simpan(): void
    {
        $this->pastikanDapatDiedit();
        $this->validate();

        try {
            SimpanMatrikJadwal::simpan($this->kegiatanId, $this->matrik, $this->jadwal);
        } catch (RuntimeException $e) {
            report($e);
            $this->dispatch('notify', message: 'Matrik & jadwal gagal disimpan. Perubahan masih ada di layar — coba simpan lagi.', type: 'error');

            return;
        }

        $this->muatIsian();
        $this->dispatch('notify', message: 'Matrik & jadwal tersimpan.', type: 'success');
    }

    public function batalkanPerubahan(): void
    {
        $this->muatIsian();
        $this->resetValidation();
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [...SimpanMatrikJadwal::aturanMatrik(), ...SimpanMatrikJadwal::aturanJadwal($this->jadwal)];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [...SimpanMatrikJadwal::pesanMatrik(), ...SimpanMatrikJadwal::pesanJadwal()];
    }

    public function dapatDiedit(): bool
    {
        return $this->status()->dapatDiedit() && $this->tahunTerkunci === null;
    }

    /** Tahun anggaran kegiatan bila terkunci (KunciKegiatan) — isian hanya bisa dilihat. */
    #[Computed]
    public function tahunTerkunci(): ?int
    {
        return KunciKegiatan::tahunTerkunci($this->dataKegiatan);
    }

    public function tahun(): int
    {
        return StubData::tahunAktif()['tahun'];
    }

    public function status(): StatusKegiatan
    {
        return StatusKegiatan::tryFrom($this->dataKegiatan['status'] ?? '') ?? StatusKegiatan::Draf;
    }

    public function render()
    {
        return view('livewire.kegiatan.matrik-jadwal')
            ->title('Matrik & Jadwal · '.($this->dataKegiatan['nama_kegiatan'] ?? 'Kegiatan'));
    }

    /**
     * Baris jadwal form → bentuk penyimpanan (SimpanMatrikJadwal::jadwalUntukDisimpan).
     *
     * @param  list<array{uraian: string, mulai: int|null, sampai: int|null}>  $jadwal
     * @return list<array{uraian: string, bulan: list<int>}>
     */
    public static function jadwalUntukDisimpan(array $jadwal): array
    {
        return SimpanMatrikJadwal::jadwalUntukDisimpan($jadwal);
    }

    private function muatIsian(): void
    {
        $repo = app(MatrikJadwalRepository::class);
        $this->matrik = $repo->matrik($this->kegiatanId)->butir;
        $this->jadwal = SimpanMatrikJadwal::isianJadwal($repo->jadwal($this->kegiatanId));
        $this->adaPerubahan = false;
        $this->matrikSebelum = null;
        $this->jadwalSebelum = null;
    }

    /**
     * Bulan acuan dari Data Kegiatan (DataMatrikJadwal::acuan); null bila bulan pelaksanaan belum diisi.
     *
     * @return array{persiapan: int, pelaksanaan: list<int>, laporan: int}|null
     */
    private function acuanBulan(): ?array
    {
        return DataMatrikJadwal::acuan($this->pelaksanaan->bulan, $this->bulanLaporan());
    }

    /** Bulan batas laporan di tahun anggaran aktif (DataMatrikJadwal::bulanLaporan). */
    public function bulanLaporan(): ?int
    {
        return DataMatrikJadwal::bulanLaporan($this->dataKegiatan, $this->tahun());
    }

    private static function bulanAtauNull(mixed $nilai): ?int
    {
        return SimpanMatrikJadwal::bulanAtauNull($nilai);
    }

    /**
     * @param  callable(string): list<int>  $bulanTahapan
     */
    private function ubahSemua(callable $bulanTahapan, string $pesan): void
    {
        $sebelum = $this->matrik;

        foreach ($this->matrik as $tahapan => $daftar) {
            foreach (array_keys($daftar) as $index) {
                $this->matrik[$tahapan][$index]['bulan'] = MatrikTahapan::rapikanBulan($bulanTahapan($tahapan));
            }
        }

        if ($this->matrik !== $sebelum) {
            $this->matrikSebelum = ['matrik' => $sebelum, 'pesan' => $pesan];
            $this->adaPerubahan = true;
        }
    }

    /** Pesan galat baris jadwal terikat ke indeks; buang setelah baris dihapus/digeser agar tidak salah tempat. */
    private function resetErrorJadwal(): void
    {
        $kunci = array_filter(array_keys($this->getErrorBag()->messages()), fn (string $k) => str_starts_with($k, 'jadwal.'));

        if ($kunci !== []) {
            $this->resetValidation($kunci);
        }
    }

    private function pastikanDapatDiedit(): void
    {
        abort_unless($this->dapatDiedit(), 403, 'Matrik & jadwal terkunci karena status kegiatan bukan Draf atau Dikembalikan.');
    }
}
