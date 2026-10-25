<?php

namespace App\Livewire\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Support\Kegiatan\DataPelaksanaan;
use App\Support\Kegiatan\JadwalKegiatan;
use App\Support\Kegiatan\MatrikTahapan;
use App\Support\Stub\StubData;
use Carbon\CarbonImmutable;
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

    public bool $adaPerubahan = false;

    /** @var array{matrik: array<string, mixed>, pesan: string}|null Matrik sebelum diisi otomatis/dikosongkan (untuk "Urungkan"). */
    #[Locked]
    public ?array $matrikSebelum = null;

    public function mount(KegiatanRepository $repo, int $kegiatan): void
    {
        $this->dataKegiatan = $repo->temukan($kegiatan) ?? abort(404, 'Kegiatan tidak ditemukan.');
        $this->kegiatanId = $kegiatan;
        $this->matrik = MatrikTahapan::dari($this->dataKegiatan)->butir;
    }

    /** Matrik sesuai isian saat ini (termasuk yang belum disimpan): jumlah tanda, bulan per tahapan, kelengkapan. */
    #[Computed]
    public function susunanMatrik(): MatrikTahapan
    {
        return MatrikTahapan::dari(['matrik' => $this->matrik]);
    }

    #[Computed]
    public function jadwal(): JadwalKegiatan
    {
        return JadwalKegiatan::dari($this->dataKegiatan);
    }

    #[Computed]
    public function pelaksanaan(): DataPelaksanaan
    {
        return DataPelaksanaan::dari($this->dataKegiatan);
    }

    /** Isian matrik hanya boleh diubah selama kegiatan masih Draf/Dikembalikan. */
    public function updatingMatrik(): void
    {
        $this->pastikanDapatDiedit();
    }

    /** Nilai bulan yang dikirim klien dirapikan (urut, unik, 1–12); klien sudah mengirim bentuk yang sama. */
    public function updatedMatrik(): void
    {
        $this->matrik = MatrikTahapan::dari(['matrik' => $this->matrik])->butir;
        $this->adaPerubahan = true;
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
        $bulan = $this->pelaksanaan->bulan;

        if ($bulan === []) {
            $this->dispatch('notify', message: 'Isi bulan pelaksanaan di Data Kegiatan terlebih dahulu.', type: 'error');

            return;
        }

        $usulan = [
            'persiapan' => [max(1, $bulan[0] - 1)],
            'pelaksanaan' => $bulan,
            'evaluasi_pelaporan' => [$this->bulanLaporan() ?? end($bulan)],
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

    public function simpan(KegiatanRepository $repo): void
    {
        $this->pastikanDapatDiedit();
        $this->validate();

        $data = $repo->temukan($this->kegiatanId) ?? $this->dataKegiatan;
        $data['matrik'] = MatrikTahapan::dari(['matrik' => $this->matrik])->butir;
        $repo->simpan($data, $this->kegiatanId);

        $this->dataKegiatan = $repo->temukan($this->kegiatanId) ?? $data;
        $this->matrik = $data['matrik'];
        $this->adaPerubahan = false;
        $this->matrikSebelum = null;
        $this->dispatch('notify', message: 'Matrik tahapan tersimpan.', type: 'success');
    }

    public function batalkanPerubahan(): void
    {
        $this->matrik = MatrikTahapan::dari($this->dataKegiatan)->butir;
        $this->adaPerubahan = false;
        $this->matrikSebelum = null;
        $this->resetValidation();
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'matrik' => ['array'],
            'matrik.*' => ['array'],
            'matrik.*.*.uraian' => ['required', 'string', 'max:150'],
            'matrik.*.*.bulan' => ['array'],
            'matrik.*.*.bulan.*' => ['integer', 'between:1,12'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return ['matrik.*.*.uraian.required' => 'Uraian butir tahapan wajib diisi.'];
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
        abort_unless($this->dapatDiedit(), 403, 'Matrik terkunci karena status kegiatan bukan Draf atau Dikembalikan.');
    }
}
