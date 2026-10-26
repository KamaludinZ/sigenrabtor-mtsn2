<?php

namespace App\Livewire\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Support\Kegiatan\DataPelaksanaan;
use App\Support\Kegiatan\JadwalKegiatan;
use App\Support\Kegiatan\MatrikTahapan;
use App\Support\Stub\StubData;
use App\Support\Teks;
use Carbon\CarbonImmutable;
use Closure;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Halaman Matrik & Jadwal satu kegiatan (PRD F10): matrik tahapan (Persiapan, Pelaksanaan, Evaluasi & Pelaporan)
 * × bulan 1–12 untuk bagian 4 KAK, dan jadwal kegiatan 6 baris baku untuk bagian 7. Bulan pelaksanaan dari Data
 * Kegiatan ditandai sebagai acuan.
 */
#[Layout('layouts.app')]
class MatrikJadwal extends Component
{
    /** Uraian jadwal baku (bisa diubah per kegiatan); bulannya diturunkan dari jadwal pelaksanaan saat diisi. */
    public const JADWAL_BAKU = [
        'Pembentukan kepanitiaan',
        'Rapat koordinasi panitia',
        'Persiapan sarana dan bahan kegiatan',
        'Pelaksanaan kegiatan',
        'Evaluasi kegiatan',
        'Penyusunan dan penyerahan laporan',
    ];

    #[Locked]
    public int $kegiatanId;

    /** @var array<string, mixed> */
    #[Locked]
    public array $dataKegiatan = [];

    /**
     * Matrik yang sedang disunting: kunci tahapan → butir {uraian, bulan}. Sel diatur di klien (seret/klik) lalu dikirim
     * per baris lewat aturBulan(); tersimpan ke data kegiatan saat "Simpan".
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

    /** Nilai bulan yang dikirim klien dirapikan (urut, unik, 1–12); klien sudah mengirim bentuk yang sama. */
    public function updatedMatrik(): void
    {
        $this->matrik = MatrikTahapan::dari(['matrik' => $this->matrik])->butir;
        $this->adaPerubahan = true;
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
     * Bulan bertanda satu butir — dikirim sekali setelah klik atau seretan selesai.
     *
     * @param  list<int|string>  $bulan
     */
    public function aturBulan(string $tahapan, int $index, array $bulan): void
    {
        $this->pastikanDapatDiedit();

        if (! isset($this->matrik[$tahapan][$index])) {
            return;
        }

        $this->matrik[$tahapan][$index]['bulan'] = MatrikTahapan::rapikanBulan($bulan);
        $this->adaPerubahan = true;
        $this->matrikSebelum = null;
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

    public function kosongkanBarisJadwal(int $index): void
    {
        $this->pastikanDapatDiedit();

        if (! isset($this->jadwal[$index])) {
            return;
        }

        $this->jadwal[$index] = ['uraian' => '', 'mulai' => null, 'sampai' => null];
        $this->resetValidation(["jadwal.{$index}.uraian", "jadwal.{$index}.mulai", "jadwal.{$index}.sampai"]);
        $this->adaPerubahan = true;
        $this->jadwalSebelum = null;
    }

    public function simpan(KegiatanRepository $repo): void
    {
        $this->pastikanDapatDiedit();
        $this->validate();

        $data = $repo->temukan($this->kegiatanId) ?? $this->dataKegiatan;
        $data['matrik'] = MatrikTahapan::dari(['matrik' => $this->matrik])->butir;
        $data['jadwal'] = self::jadwalUntukDisimpan($this->jadwal);
        $repo->simpan($data, $this->kegiatanId);

        $this->dataKegiatan = $repo->temukan($this->kegiatanId) ?? $data;
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
        $waktuUrut = function (string $attribute, mixed $nilai, Closure $fail) {
            $mulai = $this->jadwal[(int) explode('.', $attribute)[1]]['mulai'] ?? null;

            if ($nilai !== null && ($mulai === null || $nilai < $mulai)) {
                $fail('Bulan "s.d." tidak boleh sebelum bulan mulai.');
            }
        };

        return [
            'matrik' => ['array'],
            'matrik.*' => ['array'],
            'matrik.*.*.uraian' => ['required', 'string', 'max:150'],
            'matrik.*.*.bulan' => ['array'],
            'matrik.*.*.bulan.*' => ['integer', 'between:1,12'],
            'jadwal' => ['array', 'max:12'],
            // Baris boleh kosong; uraian baru wajib bila bulannya sudah dipilih (required_with bersifat implisit, tetap
            // diperiksa walau uraian kosong).
            'jadwal.*.uraian' => ['nullable', 'string', 'max:200', 'required_with:jadwal.*.mulai'],
            'jadwal.*.mulai' => ['nullable', 'integer', 'between:1,12'],
            'jadwal.*.sampai' => ['nullable', 'integer', 'between:1,12', $waktuUrut],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'matrik.*.*.uraian.required' => 'Uraian butir tahapan wajib diisi.',
            'jadwal.*.uraian.max' => 'Uraian jadwal maksimal 200 karakter.',
            'jadwal.*.uraian.required_with' => 'Isi uraian kegiatan untuk bulan yang sudah dipilih.',
        ];
    }

    public function dapatDiedit(): bool
    {
        return $this->status()->dapatDiedit();
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
     * Baris jadwal form → bentuk penyimpanan {uraian (rapi), bulan (rentang mulai..s.d.)}.
     *
     * @param  list<array{uraian: string, mulai: int|null, sampai: int|null}>  $jadwal
     * @return list<array{uraian: string, bulan: list<int>}>
     */
    public static function jadwalUntukDisimpan(array $jadwal): array
    {
        return array_values(array_map(function (array $b) {
            $mulai = self::bulanAtauNull($b['mulai'] ?? null);
            $sampai = self::bulanAtauNull($b['sampai'] ?? null);

            return [
                'uraian' => Teks::rapikan((string) ($b['uraian'] ?? '')),
                'bulan' => $mulai === null ? [] : range($mulai, max($mulai, $sampai ?? $mulai)),
            ];
        }, $jadwal));
    }

    private function muatIsian(): void
    {
        $this->matrik = MatrikTahapan::dari($this->dataKegiatan)->butir;
        $this->jadwal = array_map(fn (array $b) => [
            'uraian' => $b['uraian'],
            'mulai' => $b['bulan'][0] ?? null,
            'sampai' => count($b['bulan']) > 1 ? end($b['bulan']) : null,
        ], JadwalKegiatan::dari($this->dataKegiatan)->baris);
        $this->adaPerubahan = false;
        $this->matrikSebelum = null;
        $this->jadwalSebelum = null;
    }

    /**
     * Bulan acuan dari Data Kegiatan; null bila bulan pelaksanaan belum diisi.
     *
     * @return array{persiapan: int, pelaksanaan: list<int>, laporan: int}|null
     */
    private function acuanBulan(): ?array
    {
        $bulan = $this->pelaksanaan->bulan;

        if ($bulan === []) {
            return null;
        }

        return ['persiapan' => max(1, $bulan[0] - 1), 'pelaksanaan' => $bulan, 'laporan' => $this->bulanLaporan() ?? end($bulan)];
    }

    /** Bulan batas laporan di tahun anggaran aktif; laporan yang jatuh di tahun berikutnya ditandai Desember. */
    private function bulanLaporan(): ?int
    {
        $tanggal = $this->dataKegiatan['tanggal_laporan'] ?? null;

        if (! is_string($tanggal) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
            return null;
        }

        $laporan = CarbonImmutable::parse($tanggal);

        return $laporan->year > $this->tahun() ? 12 : $laporan->month;
    }

    private static function bulanAtauNull(mixed $nilai): ?int
    {
        $bulan = filter_var($nilai, FILTER_VALIDATE_INT);

        return $bulan !== false && $bulan >= 1 && $bulan <= 12 ? $bulan : null;
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

    private function pastikanDapatDiedit(): void
    {
        abort_unless($this->dapatDiedit(), 403, 'Matrik & jadwal terkunci karena status kegiatan bukan Draf atau Dikembalikan.');
    }
}
