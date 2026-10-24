<?php

namespace App\Livewire\Forms;

use App\Rules\KalimatUtuh;
use App\Support\Kegiatan\JadwalPelaksanaan;
use App\Support\Stub\StubData;
use App\Support\Teks;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Str;
use Livewire\Form;

/**
 * Seluruh isian form Data Kegiatan beserta aturan validasinya.
 *
 * Draf boleh belum lengkap: hanya identitas minimum yang wajib. Kelengkapan dokumen
 * (tujuan, sasaran, dst.) diperiksa terpisah saat pratinjau/cetak.
 */
class KegiatanForm extends Form
{
    /** Isian uraian (teks panjang) yang spasinya dirapikan otomatis. */
    public const URAIAN = ['latar_belakang', 'tujuan', 'sasaran'];

    // Identitas Kegiatan
    public string $nama_kegiatan = '';

    public string $nama_kegiatan_cover = '';

    /** True bila nama cover diubah manual sehingga tidak lagi mengikuti nama kegiatan. */
    public bool $cover_manual = false;

    public string $latar_belakang = '';

    public string $tujuan = '';

    public string $sasaran = '';

    public ?string $tanggal_dokumen = null;

    public ?string $tanggal_laporan = null;

    // Pelaksanaan — jadwal
    public ?string $tanggal_mulai = null;

    /** @var int|string|null Lama kegiatan dalam hari (input angka; string kosong = belum diisi). */
    public $lama_hari = null;

    /** Hitung lama kegiatan dengan hari kerja saja (lewati Sabtu & Minggu). */
    public bool $hari_kerja = false;

    /** @var list<int> Nomor bulan pelaksanaan (1–12); bisa lebih dari satu seperti timeline cascade Perkin. */
    public array $bulan_pelaksanaan = [];

    /** True selama tanggal pelaporan mengikuti tanggal selesai + 10 hari (belum diubah manual). */
    public bool $laporan_otomatis = true;

    // Pelaksana & Panitia
    /** @var list<array{key: string, nama: string, jabatan: string, peran: string}> */
    public array $panitia = [];

    /** Batas jumlah anggota tim agar tabel KAK tetap wajar. */
    public const MAKS_PANITIA = 50;

    /** Saran peran tim; isian tetap bebas. */
    public const PERAN_PANITIA = [
        'Ketua', 'Wakil Ketua', 'Sekretaris', 'Bendahara', 'Koordinator', 'Anggota', 'Narasumber', 'Pengarah',
    ];

    /** Hierarki peran untuk "Urutkan sesuai peran". */
    public const URUTAN_PERAN = [
        'Pengarah', 'Penanggung Jawab', 'Ketua', 'Wakil Ketua', 'Sekretaris', 'Bendahara', 'Koordinator', 'Narasumber', 'Anggota',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $tahun = StubData::tahunAktif()['tahun'];

        return [
            'nama_kegiatan' => ['required', 'string', 'min:5', 'max:255'],
            'nama_kegiatan_cover' => ['required', 'string', 'max:255'],
            'latar_belakang' => ['nullable', 'string', 'max:5000', new KalimatUtuh],
            'tujuan' => ['nullable', 'string', 'max:2000', new KalimatUtuh],
            'sasaran' => ['nullable', 'string', 'max:2000', new KalimatUtuh],
            'tanggal_dokumen' => ['nullable', 'date', 'after_or_equal:'.($tahun - 1).'-01-01', "before_or_equal:{$tahun}-12-31", $this->aturanDokumenSebelumMulai()],
            'tanggal_laporan' => ['nullable', 'date', 'after_or_equal:tanggal_dokumen', $this->aturanLaporanSetelahSelesai()],
            'tanggal_mulai' => ['nullable', 'date', "after_or_equal:{$tahun}-01-01", "before_or_equal:{$tahun}-12-31"],
            'lama_hari' => ['nullable', 'integer', 'min:1', 'max:366', $this->aturanSelesaiDalamTahun($tahun)],
            'hari_kerja' => ['boolean'],
            'bulan_pelaksanaan' => ['array', 'max:12', $this->aturanBulanMencakupTanggal()],
            'bulan_pelaksanaan.*' => ['integer', 'between:1,12'],
            'panitia' => ['array', 'max:'.self::MAKS_PANITIA],
            'panitia.*.nama' => ['required', 'string', 'max:150'],
            'panitia.*.jabatan' => ['nullable', 'string', 'max:150'],
            'panitia.*.peran' => ['required', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'nama_kegiatan' => 'nama kegiatan',
            'nama_kegiatan_cover' => 'nama kegiatan (cover)',
            'latar_belakang' => 'latar belakang',
            'tujuan' => 'tujuan',
            'sasaran' => 'sasaran',
            'tanggal_dokumen' => 'tanggal dokumen',
            'tanggal_laporan' => 'tanggal pelaporan',
            'tanggal_mulai' => 'tanggal mulai',
            'lama_hari' => 'lama kegiatan',
            'bulan_pelaksanaan' => 'bulan pelaksanaan',
            'panitia.*.nama' => 'nama anggota',
            'panitia.*.jabatan' => 'jabatan anggota',
            'panitia.*.peran' => 'peran anggota',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        $tahun = StubData::tahunAktif()['tahun'];
        $diTahunAnggaran = "Tanggal mulai harus di Tahun Anggaran {$tahun}.";
        $rentangDokumen = 'Tanggal dokumen harus antara 1 Januari '.($tahun - 1)." dan 31 Desember {$tahun}.";

        return [
            'required' => 'Kolom :attribute wajib diisi.',
            'min' => 'Kolom :attribute minimal :min karakter.',
            'max' => 'Kolom :attribute maksimal :max karakter.',
            'date' => 'Kolom :attribute harus berupa tanggal yang valid.',
            'integer' => 'Kolom :attribute harus berupa angka bulat.',
            'tanggal_laporan.after_or_equal' => 'Tanggal pelaporan tidak boleh sebelum tanggal dokumen.',
            'tanggal_dokumen.after_or_equal' => $rentangDokumen,
            'tanggal_dokumen.before_or_equal' => $rentangDokumen,
            'tanggal_mulai.after_or_equal' => $diTahunAnggaran,
            'tanggal_mulai.before_or_equal' => $diTahunAnggaran,
            'lama_hari.min' => 'Lama kegiatan minimal 1 hari.',
            'lama_hari.max' => 'Lama kegiatan maksimal 366 hari.',
            'panitia.max' => 'Susunan tim maksimal :max orang.',
        ];
    }

    /**
     * Tanggal selesai hasil hitung, atau null bila tanggal mulai/lama belum valid.
     */
    public function tanggalSelesai(): ?CarbonImmutable
    {
        $mulai = $this->tanggalMulai();
        $lama = filter_var($this->lama_hari, FILTER_VALIDATE_INT);

        if ($mulai === null || $lama === false || $lama < 1 || $lama > 366) {
            return null;
        }

        return JadwalPelaksanaan::tanggalSelesai($mulai, $lama, $this->hari_kerja);
    }

    public function tanggalMulai(): ?CarbonImmutable
    {
        if (! $this->tanggal_mulai || ! strtotime($this->tanggal_mulai)) {
            return null;
        }

        return CarbonImmutable::parse($this->tanggal_mulai)->startOfDay();
    }

    /**
     * Turunkan bulan pelaksanaan (bila belum dipilih) dan tanggal pelaporan otomatis dari jadwal.
     */
    public function sinkronJadwal(): void
    {
        $mulai = $this->tanggalMulai();
        $selesai = $this->tanggalSelesai();

        if ($mulai && $selesai && $this->bulan_pelaksanaan === []) {
            $this->bulan_pelaksanaan = JadwalPelaksanaan::bulanTercakup($mulai, $selesai);
        }

        if ($this->laporan_otomatis && $selesai) {
            $this->tanggal_laporan = JadwalPelaksanaan::batasLaporan($selesai)->toDateString();
        }
    }

    /**
     * Isi ulang bulan pelaksanaan persis sesuai rentang tanggal mulai–selesai.
     */
    public function sesuaikanBulanDenganTanggal(): void
    {
        $mulai = $this->tanggalMulai();
        $selesai = $this->tanggalSelesai();

        if ($mulai && $selesai) {
            $this->bulan_pelaksanaan = JadwalPelaksanaan::bulanTercakup($mulai, $selesai);
        }
    }

    public function toggleBulan(int $bulan): void
    {
        if ($bulan < 1 || $bulan > 12) {
            return;
        }

        $this->bulan_pelaksanaan = in_array($bulan, $this->bulan_pelaksanaan, true)
            ? array_values(array_diff($this->bulan_pelaksanaan, [$bulan]))
            : [...$this->bulan_pelaksanaan, $bulan];

        sort($this->bulan_pelaksanaan);
    }

    /**
     * Tanggal pelaporan diubah manual: berhenti mengikuti jadwal. Dikosongkan: kembali otomatis.
     */
    public function setLaporanManual(): void
    {
        $this->laporan_otomatis = blank($this->tanggal_laporan);

        if ($this->laporan_otomatis) {
            $this->sinkronJadwal();
        }
    }

    private function aturanSelesaiDalamTahun(int $tahun): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($tahun) {
            $selesai = $this->tanggalSelesai();

            if ($selesai && $selesai->year > $tahun) {
                $fail('Kegiatan selesai '.self::teksTanggal($selesai).", melewati akhir Tahun Anggaran {$tahun}.");
            }
        };
    }

    private function aturanLaporanSetelahSelesai(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            $selesai = $this->tanggalSelesai();

            if ($selesai && strtotime((string) $value) && CarbonImmutable::parse($value)->lt($selesai)) {
                $fail('Tanggal pelaporan tidak boleh sebelum kegiatan selesai ('.self::teksTanggal($selesai).').');
            }
        };
    }

    /**
     * KAK adalah rencana: tanggal pengesahannya tidak boleh setelah kegiatan dimulai.
     */
    private function aturanDokumenSebelumMulai(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            $mulai = $this->tanggalMulai();

            if ($mulai && strtotime((string) $value) && CarbonImmutable::parse($value)->gt($mulai)) {
                $fail('Tanggal dokumen harus sebelum atau sama dengan tanggal mulai kegiatan ('.self::teksTanggal($mulai).').');
            }
        };
    }

    /**
     * Bulan pelaksanaan boleh lebih luas (mis. bulan persiapan) tetapi wajib mencakup bulan kegiatan berlangsung.
     */
    private function aturanBulanMencakupTanggal(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            $mulai = $this->tanggalMulai();
            $selesai = $this->tanggalSelesai();

            if (! $mulai || ! $selesai || ! is_array($value) || $value === []) {
                return;
            }

            $kurang = array_diff(JadwalPelaksanaan::bulanTercakup($mulai, $selesai), array_map('intval', $value));

            if ($kurang !== []) {
                $nama = implode(', ', array_map(fn ($b) => JadwalPelaksanaan::NAMA_BULAN[$b], $kurang));
                $fail("Bulan pelaksanaan belum mencakup bulan kegiatan berlangsung: {$nama}.");
            }
        };
    }

    private static function teksTanggal(CarbonImmutable $tanggal): string
    {
        return $tanggal->locale('id')->translatedFormat('j F Y');
    }

    public function tambahPanitia(string $peran = 'Anggota', string $nama = '', string $jabatan = ''): bool
    {
        if (count($this->panitia) >= self::MAKS_PANITIA) {
            return false;
        }

        $this->panitia[] = ['key' => (string) Str::uuid(), 'nama' => $nama, 'jabatan' => $jabatan, 'peran' => $peran];

        return true;
    }

    public function hapusPanitia(int $index): void
    {
        unset($this->panitia[$index]);
        $this->panitia = array_values($this->panitia);
    }

    /**
     * Geser anggota ke atas (-1) atau ke bawah (+1); urutan ini yang tercetak di KAK.
     */
    public function geserPanitia(int $index, int $arah): void
    {
        $tujuan = $index + $arah;

        if (! isset($this->panitia[$index], $this->panitia[$tujuan])) {
            return;
        }

        [$this->panitia[$index], $this->panitia[$tujuan]] = [$this->panitia[$tujuan], $this->panitia[$index]];
    }

    /**
     * Pindahkan anggota (berdasarkan key) ke posisi baru — dipakai seret-lepas.
     */
    public function pindahkanPanitia(string $key, int $posisi): void
    {
        $index = array_search($key, array_column($this->panitia, 'key'), true);

        if ($index === false) {
            return;
        }

        $anggota = $this->panitia[$index];
        array_splice($this->panitia, $index, 1);
        array_splice($this->panitia, max(0, min($posisi, count($this->panitia))), 0, [$anggota]);
    }

    /**
     * Kembalikan anggota yang baru dihapus ke posisi semula.
     *
     * @param  array{key: string, nama: string, jabatan: string, peran: string}  $anggota
     */
    public function sisipkanPanitia(int $index, array $anggota): void
    {
        array_splice($this->panitia, max(0, min($index, count($this->panitia))), 0, [$anggota]);
    }

    /**
     * Urutkan sesuai hierarki peran (Pengarah → Ketua → … → Anggota). Peran di luar daftar
     * diletakkan sebelum Anggota; urutan antar anggota berperan sama dipertahankan.
     */
    public function urutkanSesuaiPeran(): void
    {
        $urutan = array_map('mb_strtolower', self::URUTAN_PERAN);
        $posisiAnggota = array_search('anggota', $urutan, true);

        $peringkat = function (array $anggota) use ($urutan, $posisiAnggota): float {
            $i = array_search(mb_strtolower(trim($anggota['peran'])), $urutan, true);

            return $i === false ? $posisiAnggota - 0.5 : $i;
        };

        $berindeks = array_map(null, array_keys($this->panitia), $this->panitia);
        usort($berindeks, fn ($a, $b) => [$peringkat($a[1]), $a[0]] <=> [$peringkat($b[1]), $b[0]]);

        $this->panitia = array_column($berindeks, 1);
    }

    /**
     * Indeks baris yang namanya sama dengan baris lain (tanpa membedakan huruf/spasi).
     *
     * @return list<int>
     */
    public function panitiaGanda(): array
    {
        $nama = array_map(fn ($a) => mb_strtolower(preg_replace('/\s+/u', ' ', trim($a['nama']))), $this->panitia);
        $hitung = array_count_values(array_filter($nama));

        return array_keys(array_filter($nama, fn ($n) => $n !== '' && $hitung[$n] > 1));
    }

    /**
     * Susunan baku panitia kegiatan madrasah; hanya dipakai saat daftar masih kosong.
     */
    public function isiSusunanBaku(): void
    {
        if ($this->panitia !== []) {
            return;
        }

        foreach (['Ketua', 'Sekretaris', 'Bendahara', 'Anggota'] as $peran) {
            $this->tambahPanitia($peran);
        }
    }

    public function rapikanUraian(string $field): void
    {
        if (in_array($field, self::URAIAN, true)) {
            $this->{$field} = Teks::rapikan($this->{$field});
        }
    }

    /**
     * Nama cover selalu kapital dan mengikuti nama kegiatan sampai diubah manual.
     */
    public function syncNamaCover(): void
    {
        if (! $this->cover_manual) {
            $this->nama_kegiatan_cover = mb_strtoupper(trim($this->nama_kegiatan));
        }
    }

    public function setNamaCoverManual(): void
    {
        $this->nama_kegiatan_cover = mb_strtoupper($this->nama_kegiatan_cover);
        $this->cover_manual = $this->nama_kegiatan_cover !== mb_strtoupper(trim($this->nama_kegiatan));
    }

    public function resetNamaCover(): void
    {
        $this->cover_manual = false;
        $this->syncNamaCover();
    }

    /**
     * Peringatan non-blok: teks yang akan menimbulkan pengulangan kata di dokumen,
     * karena template KAK sudah menambahkan kata pembukanya sendiri.
     *
     * @return array<string, string>
     */
    public function peringatan(): array
    {
        $cek = [
            'nama_kegiatan' => ['kegiatan', 'Template sudah menulis “kegiatan …”; awalan “Kegiatan” akan tercetak dobel.'],
            'tujuan' => ['bertujuan', 'Template sudah menulis “Kegiatan … bertujuan …”; hapus kata “bertujuan” di awal.'],
            'sasaran' => ['terwujudnya', 'Template sudah menulis “Terwujudnya …”; hapus kata “Terwujudnya” di awal.'],
        ];

        $peringatan = [];

        foreach ($cek as $field => [$awalan, $pesan]) {
            $kataPertama = mb_strtolower(strtok(trim($this->{$field}), " \t\n") ?: '');

            if ($kataPertama === $awalan) {
                $peringatan[$field] = $pesan;
            }
        }

        return $peringatan + $this->peringatanJadwal();
    }

    /**
     * Peringatan non-blok pada jadwal yang sah tetapi patut dicek ulang.
     *
     * @return array<string, string>
     */
    public function peringatanJadwal(): array
    {
        $peringatan = [];
        $mulai = $this->tanggalMulai();
        $tahun = StubData::tahunAktif()['tahun'];

        if ($mulai?->isSunday()) {
            $peringatan['tanggal_mulai'] = 'Tanggal mulai jatuh pada hari Minggu.';
        }

        if ($this->tanggal_laporan && strtotime($this->tanggal_laporan) && CarbonImmutable::parse($this->tanggal_laporan)->year > $tahun) {
            $peringatan['tanggal_laporan'] = "Batas laporan melewati Tahun Anggaran {$tahun}.";
        }

        return $peringatan;
    }
}
