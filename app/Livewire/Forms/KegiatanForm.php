<?php

namespace App\Livewire\Forms;

use App\Rules\KalimatUtuh;
use App\Support\Format;
use App\Support\Kegiatan\JadwalPelaksanaan;
use App\Support\Stub\StubData;
use App\Support\Stub\StubMaster;
use App\Support\Teks;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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
    public const URAIAN = ['latar_belakang', 'tujuan', 'sasaran', 'uraian_pelaksanaan', 'konteks'];

    /** Batas pagu per sumber dana (Rp) untuk menolak salah ketik digit berlebih. */
    public const MAKS_PAGU = 100_000_000_000;

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

    // Kode & Klasifikasi (dipilih dari master tahun aktif)
    public string $leading = '';

    public string $kode_rktm = '';

    public string $kode_sk = '';

    public string $kode_iksk = '';

    public string $kode_edm = '';

    /** @var int|string|null Nomor 5 Budaya Mutu (1–5). */
    public $budaya_mutu = null;

    // Pelaksanaan — tempat & peserta
    public string $tempat = '';

    /** @var int|string|null */
    public $peserta_jumlah = null;

    public string $peserta_jenis = '';

    public string $uraian_pelaksanaan = '';

    // Konteks kegiatan (bahan AI; tidak tercetak)
    public string $konteks = '';

    // Sumber dana: pagu disimpan sebagai teks berformat ("10.000.000") agar tampil rapi di input.
    public bool $dana_dipa = false;

    public string $pagu_dipa = '';

    public bool $dana_komite = false;

    public string $pagu_komite = '';

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
            'leading' => ['nullable', Rule::in(array_column(StubMaster::leadingsUntuk(StubData::currentUser()), 'kode'))],
            'kode_rktm' => ['nullable', Rule::in(array_column(StubMaster::kodeRktm(), 'kode'))],
            'kode_sk' => ['nullable', Rule::in(array_column(StubMaster::kodeSk(), 'kode'))],
            'kode_iksk' => ['nullable', Rule::in(array_column(StubMaster::kodeIksk(), 'kode')), $this->aturanIkskSesuaiSk()],
            'kode_edm' => ['nullable', Rule::in(array_column(StubMaster::kodeEdm(), 'kode'))],
            'budaya_mutu' => ['nullable', Rule::in(array_column(StubMaster::budayaMutu(), 'nomor'))],
            'tempat' => ['nullable', 'string', 'max:255'],
            'peserta_jumlah' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'peserta_jenis' => ['nullable', 'string', 'max:150'],
            'uraian_pelaksanaan' => ['nullable', 'string', 'max:3000', new KalimatUtuh],
            'konteks' => ['nullable', 'string', 'max:3000'],
            'dana_dipa' => ['boolean'],
            'dana_komite' => ['boolean'],
            'pagu_dipa' => $this->aturanPagu($this->dana_dipa),
            'pagu_komite' => $this->aturanPagu($this->dana_komite),
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
            'leading' => 'leading',
            'kode_rktm' => 'kode RKTM',
            'kode_sk' => 'kode SK',
            'kode_iksk' => 'kode IKSK',
            'kode_edm' => 'kode EDM',
            'budaya_mutu' => '5 Budaya Mutu',
            'tempat' => 'tempat',
            'peserta_jumlah' => 'jumlah peserta',
            'peserta_jenis' => 'jenis peserta',
            'uraian_pelaksanaan' => 'uraian pelaksanaan',
            'konteks' => 'konteks kegiatan',
            'pagu_dipa' => 'pagu DIPA',
            'pagu_komite' => 'pagu Komite',
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
            'in' => 'Pilihan :attribute tidak ada di master tahun aktif.',
            'peserta_jumlah.min' => 'Jumlah peserta minimal 1 orang.',
            'peserta_jumlah.max' => 'Jumlah peserta maksimal 100.000 orang.',
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

    /**
     * Isi form dari data tersimpan. Kunci yang tidak dikenal diabaikan; tipe dinormalkan.
     *
     * @param  array<string, mixed>  $data
     */
    public function isiDari(array $data): void
    {
        foreach (['nama_kegiatan', 'nama_kegiatan_cover', 'latar_belakang', 'tujuan', 'sasaran'] as $field) {
            $this->{$field} = (string) ($data[$field] ?? '');
        }

        foreach (['tanggal_dokumen', 'tanggal_laporan', 'tanggal_mulai'] as $field) {
            $this->{$field} = filled($data[$field] ?? null) ? (string) $data[$field] : null;
        }

        $this->cover_manual = (bool) ($data['cover_manual'] ?? false);
        $this->laporan_otomatis = (bool) ($data['laporan_otomatis'] ?? true);
        $this->hari_kerja = (bool) ($data['hari_kerja'] ?? false);
        $this->lama_hari = filled($data['lama_hari'] ?? null) ? (int) $data['lama_hari'] : null;
        $this->bulan_pelaksanaan = array_values(array_map('intval', (array) ($data['bulan_pelaksanaan'] ?? [])));
        $this->panitia = array_map(fn ($anggota) => [
            'key' => (string) Str::uuid(),
            'nama' => (string) ($anggota['nama'] ?? ''),
            'jabatan' => (string) ($anggota['jabatan'] ?? ''),
            'peran' => (string) ($anggota['peran'] ?? 'Anggota'),
        ], array_values((array) ($data['panitia'] ?? [])));

        foreach (['leading', 'kode_rktm', 'kode_sk', 'kode_iksk', 'kode_edm', 'tempat', 'peserta_jenis', 'uraian_pelaksanaan', 'konteks'] as $field) {
            $this->{$field} = (string) ($data[$field] ?? '');
        }

        $this->budaya_mutu = filled($data['budaya_mutu'] ?? null) ? (int) $data['budaya_mutu'] : null;
        $this->peserta_jumlah = filled($data['peserta_jumlah'] ?? null) ? (int) $data['peserta_jumlah'] : null;

        $sumber = array_column((array) ($data['sumber_dana'] ?? []), 'pagu', 'sumber');
        $this->dana_dipa = array_key_exists('DIPA', $sumber);
        $this->dana_komite = array_key_exists('Komite', $sumber);
        $this->pagu_dipa = $this->dana_dipa && $sumber['DIPA'] !== null ? Format::angka((int) $sumber['DIPA']) : '';
        $this->pagu_komite = $this->dana_komite && $sumber['Komite'] !== null ? Format::angka((int) $sumber['Komite']) : '';
    }

    /**
     * Data form yang disimpan (tanpa key baris sementara milik UI).
     *
     * @return array<string, mixed>
     */
    public function dataUntukDisimpan(): array
    {
        $lama = filter_var($this->lama_hari, FILTER_VALIDATE_INT);

        return [
            'nama_kegiatan' => $this->nama_kegiatan,
            'nama_kegiatan_cover' => $this->nama_kegiatan_cover,
            'cover_manual' => $this->cover_manual,
            'latar_belakang' => $this->latar_belakang,
            'tujuan' => $this->tujuan,
            'sasaran' => $this->sasaran,
            'tanggal_dokumen' => $this->tanggal_dokumen ?: null,
            'tanggal_laporan' => $this->tanggal_laporan ?: null,
            'laporan_otomatis' => $this->laporan_otomatis,
            'tanggal_mulai' => $this->tanggal_mulai ?: null,
            'lama_hari' => $lama === false ? null : $lama,
            'hari_kerja' => $this->hari_kerja,
            'bulan_pelaksanaan' => $this->bulan_pelaksanaan,
            'panitia' => array_map(fn ($a) => ['nama' => $a['nama'], 'jabatan' => $a['jabatan'], 'peran' => $a['peran']], $this->panitia),
            'leading' => $this->leading ?: null,
            'kode_rktm' => $this->kode_rktm ?: null,
            'kode_sk' => $this->kode_sk ?: null,
            'kode_iksk' => $this->kode_iksk ?: null,
            'kode_edm' => $this->kode_edm ?: null,
            'budaya_mutu' => filled($this->budaya_mutu) ? (int) $this->budaya_mutu : null,
            'tempat' => $this->tempat,
            'peserta_jumlah' => filter_var($this->peserta_jumlah, FILTER_VALIDATE_INT) ?: null,
            'peserta_jenis' => $this->peserta_jenis,
            'uraian_pelaksanaan' => $this->uraian_pelaksanaan,
            'konteks' => $this->konteks,
            'sumber_dana' => $this->sumberDana(),
        ];
    }

    /**
     * "Kegiatan Workshop …" → "Workshop …": template KAK sudah menulis kata "kegiatan" sendiri.
     */
    public static function tanpaAwalanKegiatan(string $nama): string
    {
        return trim(preg_replace('/^kegiatan\s+/iu', '', trim($nama)));
    }

    /**
     * Sumber dana terpilih beserta pagunya — satu baris per sumber (calon tabel anggaran_kegiatan).
     *
     * @return list<array{sumber: string, pagu: int|null}>
     */
    public function sumberDana(): array
    {
        $sumber = [];

        if ($this->dana_dipa) {
            $sumber[] = ['sumber' => 'DIPA', 'pagu' => Format::parseRupiah($this->pagu_dipa)];
        }

        if ($this->dana_komite) {
            $sumber[] = ['sumber' => 'Komite', 'pagu' => Format::parseRupiah($this->pagu_komite)];
        }

        return $sumber;
    }

    public function totalPagu(): int
    {
        return array_sum(array_map(fn ($s) => (int) $s['pagu'], $this->sumberDana()));
    }

    /**
     * Rapikan tampilan pagu menjadi "10.000.000" setelah diketik.
     */
    public function rapikanPagu(string $field): void
    {
        if (in_array($field, ['pagu_dipa', 'pagu_komite'], true)) {
            $nilai = Format::parseRupiah($this->{$field});
            $this->{$field} = $nilai === null ? '' : Format::angka($nilai);
        }
    }

    /**
     * IKSK yang sudah dipilih dikosongkan bila tidak lagi berada di bawah SK terpilih.
     */
    public function sinkronIksk(): void
    {
        if ($this->kode_iksk === '') {
            return;
        }

        $iksk = StubMaster::cari(StubMaster::kodeIksk(), $this->kode_iksk);

        if ($iksk === null || $iksk['kode_sk'] !== $this->kode_sk) {
            $this->kode_iksk = '';
        }
    }

    /**
     * Status kelengkapan tiap bagian form: lengkap, sebagian, kosong, atau opsional.
     *
     * @return array<string, string>
     */
    public function kelengkapan(): array
    {
        $terisi = fn (array $nilai) => count(array_filter($nilai, fn ($v) => filled($v) && $v !== []));
        $status = fn (int $isi, int $total) => match (true) {
            $isi === $total => 'lengkap',
            $isi === 0 => 'kosong',
            default => 'sebagian',
        };

        $identitas = [$this->nama_kegiatan, $this->tujuan, $this->sasaran, $this->tanggal_dokumen];
        $kode = [$this->leading, $this->kode_rktm, $this->kode_sk, $this->kode_iksk, $this->kode_edm, $this->budaya_mutu];
        $pelaksanaan = [$this->tempat, $this->tanggal_mulai, $this->lama_hari, $this->peserta_jumlah, $this->peserta_jenis, $this->bulan_pelaksanaan];
        $panitiaBernama = array_filter($this->panitia, fn ($a) => filled($a['nama']));
        $sumber = $this->sumberDana();
        $sumberBerpagu = array_filter($sumber, fn ($s) => ($s['pagu'] ?? 0) > 0);

        return [
            'identitas' => $status($terisi($identitas), count($identitas)),
            'kode' => $status($terisi($kode), count($kode)),
            'pelaksanaan' => $status($terisi($pelaksanaan), count($pelaksanaan)),
            'konteks' => filled($this->konteks) ? 'lengkap' : 'opsional',
            'panitia' => match (true) {
                $this->panitia === [] => 'kosong',
                count($panitiaBernama) === count($this->panitia) => 'lengkap',
                default => 'sebagian',
            },
            'sumber-dana' => match (true) {
                $sumber === [] => 'kosong',
                count($sumberBerpagu) === count($sumber) => 'lengkap',
                default => 'sebagian',
            },
        ];
    }

    /**
     * @return list<string>
     */
    private function aturanPagu(bool $dipilih): array
    {
        if (! $dipilih) {
            return ['nullable'];
        }

        return ['required', function (string $attribute, mixed $value, Closure $fail) {
            $nilai = Format::parseRupiah($value);

            if ($nilai === null || $nilai < 1) {
                $fail('Isi :attribute dengan angka rupiah lebih dari 0.');
            } elseif ($nilai > self::MAKS_PAGU) {
                $fail('Pagu terlalu besar (maksimal '.Format::rupiah(self::MAKS_PAGU).'). Periksa jumlah digit.');
            }
        }];
    }

    private function aturanIkskSesuaiSk(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if (blank($value)) {
                return;
            }

            if (blank($this->kode_sk)) {
                $fail('Pilih kode SK terlebih dahulu.');

                return;
            }

            $iksk = StubMaster::cari(StubMaster::kodeIksk(), $value);

            if ($iksk !== null && $iksk['kode_sk'] !== $this->kode_sk) {
                $fail("{$value} bukan indikator dari {$this->kode_sk}.");
            }
        };
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
