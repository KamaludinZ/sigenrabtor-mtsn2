<?php

namespace App\Livewire\Forms;

use App\Contracts\KegiatanRepository;
use App\Support\Format;
use App\Support\Kegiatan\DataPelaksanaan;
use App\Support\Kegiatan\JadwalPelaksanaan;
use App\Support\Kegiatan\KalimatKak;
use App\Support\Kegiatan\SimpanPelaksanaan;
use App\Support\Kegiatan\SusunanPanitia;
use App\Support\Kegiatan\TahunKegiatan;
use App\Support\Kegiatan\ValidasiKegiatan;
use App\Support\Master\IkskPerSk;
use App\Support\Rab\SimpanSumberDana;
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
    public const URAIAN = ['latar_belakang', 'tujuan', 'sasaran', 'uraian_pelaksanaan', 'konteks'];

    /**
     * Kode klasifikasi yang wajib terisi sebelum kode disimpan dan sebelum kegiatan diajukan pemeriksaan
     * (draf boleh belum lengkap). Kunci = isian, nilai = pesan.
     */
    public const KODE_WAJIB = [
        'kode_sk' => 'Kode SK wajib dipilih — setiap kegiatan harus mendukung satu Sasaran Kegiatan Perjanjian Kinerja.',
    ];

    /** Isian Pelaksanaan yang wajib terisi sebelum kegiatan diajukan; draf boleh menyimpannya kosong. */
    public const PELAKSANAAN_WAJIB = [
        'tanggal_mulai' => 'Tanggal mulai wajib diisi sebelum kegiatan diajukan.',
        'lama_hari' => 'Lama kegiatan wajib diisi sebelum kegiatan diajukan.',
        'bulan_pelaksanaan' => 'Pilih minimal satu bulan pelaksanaan sebelum kegiatan diajukan.',
        'tempat' => 'Tempat pelaksanaan wajib diisi sebelum kegiatan diajukan — tercetak di bagian 5 KAK.',
        'peserta_jumlah' => 'Jumlah peserta wajib diisi sebelum kegiatan diajukan.',
        'peserta_jenis' => 'Jenis peserta wajib diisi sebelum kegiatan diajukan.',
    ];

    /** Di atas batas ini angka masih sah, tetapi diberi peringatan kemungkinan salah ketik. */
    public const BATAS_WAJAR = ['lama_hari' => 31, 'peserta_jumlah' => 2000];

    /** Batas pagu per sumber dana (Rp) untuk menolak salah ketik digit berlebih. */
    public const MAKS_PAGU = ValidasiKegiatan::MAKS_PAGU;

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

    /** Data Pagu asal nama & pagu kegiatan ini (dipilih di Identitas); null = diisi manual. */
    public ?string $data_pagu_id = null;

    // Pelaksana & Panitia
    /** @var list<array{key: string, nama: string, jabatan: string, peran: string}> */
    public array $panitia = [];

    /** Batas jumlah anggota tim agar tabel KAK tetap wajar. */
    public const MAKS_PANITIA = ValidasiKegiatan::MAKS_PANITIA;

    /** Saran peran tim; isian tetap bebas. */
    public const PERAN_PANITIA = SusunanPanitia::PERAN;

    /** Hierarki peran untuk "Urutkan sesuai peran". */
    public const URUTAN_PERAN = SusunanPanitia::URUTAN_PERAN;

    /**
     * Aturan bersama ValidasiKegiatan untuk isian saat ini, ditambah pagu yang di form masih berupa teks rupiah per sumber.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        // Kode dicocokkan dengan master tahun anggaran kegiatannya (ValidasiKodeMaster).
        $bersama = ValidasiKegiatan::aturan($this->dataUntukDisimpan() + ['tahun_anggaran' => $this->tahunKegiatan()], StubData::currentUser(), $this->leadingTersimpan());

        return [
            // Teks khusus disesuaikan di tab Susun KAK/TOR (UbahDataKegiatan), bukan isian formulir ini.
            ...array_diff_key($bersama, array_flip(['sumber_dana', 'sumber_dana.*.sumber', 'sumber_dana.*.pagu', 'data_pagu_id', 'status', 'asal', 'teks_khusus', 'format_dokumen', 'template_kak'])),
            'dana_dipa' => ['boolean'],
            'dana_komite' => ['boolean'],
            'pagu_dipa' => $this->aturanPagu($this->dana_dipa),
            'pagu_komite' => $this->aturanPagu($this->dana_komite),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return ValidasiKegiatan::atribut() + ['pagu_dipa' => 'pagu DIPA', 'pagu_komite' => 'pagu Komite'];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return ValidasiKegiatan::pesan();
    }

    /**
     * State Pelaksanaan ternormalkan dari isian saat ini — dipakai bersama pratinjau KAK dan susunan dokumen.
     */
    /**
     * Leading yang tersimpan untuk kegiatan ini (dibaca dari penyimpanan, bukan isian) — tetap sah walau kini nonaktif.
     */
    /** Tahun anggaran kegiatan tersimpan (TahunKegiatan); kegiatan baru = tahun aktif — tahun master kodenya. */
    public function tahunKegiatan(): int
    {
        $id = $this->getComponent()->kegiatanId ?? null;
        $tersimpan = $id ? app(KegiatanRepository::class)->temukan((int) $id) : null;

        return $tersimpan === null ? StubData::tahunAktif()['tahun'] : TahunKegiatan::dari($tersimpan);
    }

    public function leadingTersimpan(): ?string
    {
        $id = $this->getComponent()->kegiatanId ?? null;

        return $id ? (app(KegiatanRepository::class)->temukan((int) $id)['leading'] ?? null) : null;
    }

    public function pelaksanaan(): DataPelaksanaan
    {
        return DataPelaksanaan::dari([
            'tanggal_mulai' => $this->tanggal_mulai,
            'lama_hari' => $this->lama_hari,
            'hari_kerja' => $this->hari_kerja,
            'bulan_pelaksanaan' => $this->bulan_pelaksanaan,
            'tempat' => $this->tempat,
            'peserta_jumlah' => $this->peserta_jumlah,
            'peserta_jenis' => $this->peserta_jenis,
            'uraian_pelaksanaan' => $this->uraian_pelaksanaan,
        ]);
    }

    /**
     * Tanggal selesai hasil hitung, atau null bila tanggal mulai/lama belum valid.
     */
    public function tanggalSelesai(): ?CarbonImmutable
    {
        return $this->pelaksanaan()->selesai();
    }

    public function tanggalMulai(): ?CarbonImmutable
    {
        return $this->pelaksanaan()->mulai;
    }

    /**
     * Turunkan bulan pelaksanaan (bila belum dipilih) dan tanggal pelaporan otomatis dari jadwal — aturan yang sama
     * dengan penerimaan data pelaksanaan di server (SimpanPelaksanaan::rapikan). Hanya kedua isian turunan itu yang diubah
     * agar ketikan isian lain tidak tertimpa.
     */
    public function sinkronJadwal(): void
    {
        $rapi = SimpanPelaksanaan::rapikan($this->isianPelaksanaan());

        if ($this->bulan_pelaksanaan === []) {
            $this->bulan_pelaksanaan = $rapi['bulan_pelaksanaan'];
        }

        if ($this->laporan_otomatis) {
            $this->tanggal_laporan = $rapi['tanggal_laporan'];
        }
    }

    /**
     * Isian pelaksanaan apa adanya (bahan SimpanPelaksanaan::rapikan).
     *
     * @return array<string, mixed>
     */
    public function isianPelaksanaan(): array
    {
        return array_combine(SimpanPelaksanaan::ISIAN, array_map(fn (string $isian) => $this->{$isian}, SimpanPelaksanaan::ISIAN));
    }

    /** Samakan teks pelaksanaan di layar dengan yang tersimpan (spasi dirapikan) setelah simpan. */
    public function rapikanPelaksanaan(): void
    {
        $rapi = SimpanPelaksanaan::rapikan($this->isianPelaksanaan());

        foreach (['tempat', 'peserta_jenis', 'uraian_pelaksanaan'] as $isian) {
            $this->{$isian} = $rapi[$isian];
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

    /**
     * Pilihan bulan dari klien: angka bulat tanpa duplikat, urut naik. Nilai bukan angka dibiarkan agar ditolak validasi.
     */
    public function rapikanBulan(): void
    {
        $this->bulan_pelaksanaan = SimpanPelaksanaan::rapikanBulan($this->bulan_pelaksanaan);
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

    public function tambahPanitia(string $peran = 'Anggota', string $nama = '', string $jabatan = ''): bool
    {
        $baru = SusunanPanitia::tambah($this->panitia, ['key' => (string) Str::uuid(), 'nama' => $nama, 'jabatan' => $jabatan, 'peran' => $peran]);

        if ($baru === null) {
            return false;
        }

        $this->panitia = $baru;

        return true;
    }

    public function hapusPanitia(int $index): void
    {
        $this->panitia = SusunanPanitia::hapus($this->panitia, $index);
    }

    /**
     * Geser anggota ke atas (-1) atau ke bawah (+1); urutan ini yang tercetak di KAK.
     */
    public function geserPanitia(int $index, int $arah): void
    {
        $this->panitia = SusunanPanitia::geser($this->panitia, $index, $arah);
    }

    /**
     * Pindahkan anggota (berdasarkan key) ke posisi baru — dipakai seret-lepas.
     */
    public function pindahkanPanitia(string $key, int $posisi): void
    {
        $this->panitia = SusunanPanitia::pindahkan($this->panitia, $key, $posisi);
    }

    /**
     * Kembalikan anggota yang baru dihapus ke posisi semula.
     *
     * @param  array{key: string, nama: string, jabatan: string, peran: string}  $anggota
     */
    public function sisipkanPanitia(int $index, array $anggota): void
    {
        $this->panitia = SusunanPanitia::sisipkan($this->panitia, $index, $anggota);
    }

    /**
     * Urutkan sesuai hierarki peran (Pengarah → Ketua → … → Anggota). Peran di luar daftar
     * diletakkan sebelum Anggota; urutan antar anggota berperan sama dipertahankan.
     */
    public function urutkanSesuaiPeran(): void
    {
        $this->panitia = SusunanPanitia::urutkanSesuaiPeran($this->panitia);
    }

    /**
     * Indeks baris yang namanya sama dengan baris lain (tanpa membedakan huruf/spasi).
     *
     * @return list<int>
     */
    public function panitiaGanda(): array
    {
        return SusunanPanitia::ganda($this->panitia);
    }

    /**
     * Susunan baku panitia kegiatan madrasah; hanya dipakai saat daftar masih kosong.
     */
    public function isiSusunanBaku(): void
    {
        if ($this->panitia !== []) {
            return;
        }

        foreach (SusunanPanitia::SUSUNAN_BAKU as $peran) {
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
        $this->data_pagu_id = filled($data['data_pagu_id'] ?? null) ? (string) $data['data_pagu_id'] : null;
    }

    /**
     * Data form yang disimpan (tanpa key baris sementara milik UI).
     *
     * @return array<string, mixed>
     */
    public function dataUntukDisimpan(): array
    {
        // Isian pelaksanaan dirapikan dengan aturan penerima server yang sama (SimpanPelaksanaan::rapikan).
        $pelaksanaan = SimpanPelaksanaan::rapikan($this->isianPelaksanaan());

        return [
            'nama_kegiatan' => $this->nama_kegiatan,
            'nama_kegiatan_cover' => $this->nama_kegiatan_cover,
            'cover_manual' => $this->cover_manual,
            'latar_belakang' => $this->latar_belakang,
            'tujuan' => $this->tujuan,
            'sasaran' => $this->sasaran,
            'tanggal_dokumen' => $this->tanggal_dokumen ?: null,
            'tanggal_laporan' => $pelaksanaan['tanggal_laporan'],
            'laporan_otomatis' => $pelaksanaan['laporan_otomatis'],
            'tanggal_mulai' => $pelaksanaan['tanggal_mulai'],
            'lama_hari' => $pelaksanaan['lama_hari'],
            'hari_kerja' => $pelaksanaan['hari_kerja'],
            'bulan_pelaksanaan' => $pelaksanaan['bulan_pelaksanaan'],
            'panitia' => SusunanPanitia::rapikan($this->panitia),
            'leading' => $this->leading ?: null,
            'kode_rktm' => $this->kode_rktm ?: null,
            'kode_sk' => $this->kode_sk ?: null,
            'kode_iksk' => $this->kode_iksk ?: null,
            'kode_edm' => $this->kode_edm ?: null,
            'budaya_mutu' => filled($this->budaya_mutu) ? (int) $this->budaya_mutu : null,
            'tempat' => $pelaksanaan['tempat'],
            'peserta_jumlah' => $pelaksanaan['peserta_jumlah'],
            'peserta_jenis' => $pelaksanaan['peserta_jenis'],
            'uraian_pelaksanaan' => $pelaksanaan['uraian_pelaksanaan'],
            'konteks' => Teks::rapikan($this->konteks),
            'sumber_dana' => $this->sumberDana(),
            'data_pagu_id' => $this->data_pagu_id,
        ];
    }

    /** Samakan isian panitia di layar dengan yang tersimpan (SusunanPanitia::rapikan), baris tetap berkunci sama. */
    public function rapikanPanitia(): void
    {
        $this->panitia = array_map(fn (array $a, array $rapi) => ['key' => $a['key']] + $rapi, $this->panitia, SusunanPanitia::rapikan($this->panitia));
    }

    /**
     * "Kegiatan Workshop …" → "Workshop …": template KAK sudah menulis kata "kegiatan" sendiri.
     */
    public static function tanpaAwalanKegiatan(string $nama): string
    {
        return KalimatKak::tanpaAwalanKegiatan($nama);
    }

    /**
     * Sumber dana terpilih beserta pagunya — satu baris per sumber (calon tabel anggaran_kegiatan).
     *
     * @return list<array{sumber: string, pagu: int|null}>
     */
    public function sumberDana(): array
    {
        // Perapian yang sama dengan penerima server (SimpanSumberDana::rapikan): urut baku, pagu rupiah → angka.
        return SimpanSumberDana::rapikan(array_filter(['DIPA' => $this->dana_dipa ? $this->pagu_dipa : false, 'Komite' => $this->dana_komite ? $this->pagu_komite : false], fn ($pagu) => $pagu !== false));
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
     * Sesudah SK berganti: IKSK dikosongkan bila bukan indikator SK baru, dan bila SK baru hanya punya satu indikator
     * indikator itu dipilih (IkskPerSk::sesudahGantiSk). Mengembalikan true bila IKSK dipilih otomatis.
     */
    public function sinkronIksk(): bool
    {
        ['iksk' => $this->kode_iksk, 'otomatis' => $otomatis] = IkskPerSk::sesudahGantiSk($this->kode_sk, $this->kode_iksk, $this->tahunKegiatan());

        return $otomatis;
    }

    /**
     * Status kelengkapan tiap bagian form: lengkap, sebagian, kosong, atau opsional.
     *
     * @return array<string, string>
     */
    public function kelengkapan(): array
    {
        $terisi = fn (array $nilai) => self::jumlahTerisi($nilai);
        $status = fn (int $isi, int $total) => self::statusTerisi($isi, $total);

        $identitas = [$this->nama_kegiatan, $this->tujuan, $this->sasaran, $this->tanggal_dokumen];
        $kode = [$this->leading, $this->kode_rktm, $this->kode_sk, $this->kode_iksk, $this->kode_edm, $this->budaya_mutu];
        $pelaksanaan = $this->pelaksanaan()->terisi();
        $panitiaBernama = array_filter($this->panitia, fn ($a) => filled($a['nama']));
        $sumber = $this->sumberDana();
        $sumberBerpagu = array_filter($sumber, fn ($s) => ($s['pagu'] ?? 0) > 0);

        return [
            'identitas' => $status($terisi($identitas), count($identitas)),
            'kode' => $status($terisi($kode), count($kode)),
            'pelaksanaan' => $status(count(array_filter($pelaksanaan)), count($pelaksanaan)),
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
     * Kelengkapan tiap kelompok bagian Pelaksanaan (waktu, tempat & peserta, bentuk — bentuk opsional).
     *
     * @return array{waktu: string, tempat: string, bentuk: string}
     */
    public function kelengkapanPelaksanaan(): array
    {
        return $this->pelaksanaan()->kelengkapan();
    }

    /**
     * @param  list<mixed>  $nilai
     */
    private static function jumlahTerisi(array $nilai): int
    {
        return count(array_filter($nilai, fn ($v) => filled($v) && $v !== []));
    }

    private static function statusTerisi(int $isi, int $total): string
    {
        return match (true) {
            $isi === $total => 'lengkap',
            $isi === 0 => 'kosong',
            default => 'sebagian',
        };
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

    /**
     * Pesan error untuk kode wajib yang masih kosong, berkunci nama properti Livewire (form.*).
     *
     * @return array<string, string>
     */
    /**
     * Semua isian wajib pengajuan yang masih kosong: kode SK dan isian Pelaksanaan, berkunci nama error Livewire.
     *
     * @return array<string, string>
     */
    public function wajibPengajuanKosong(): array
    {
        $kosong = $this->kodeWajibKosong();

        foreach (self::PELAKSANAAN_WAJIB as $field => $pesan) {
            if (blank($this->{$field})) {
                $kosong['form.'.$field] = $pesan;
            }
        }

        return $kosong;
    }

    public function kodeWajibKosong(): array
    {
        $kosong = [];
        foreach (self::KODE_WAJIB as $field => $pesan) {
            if (blank($this->{$field})) {
                $kosong['form.'.$field] = $pesan;
            }
        }

        return $kosong;
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
     * Peringatan bagian Pelaksanaan (tidak menghalangi simpan): jadwal ditambah angka di atas batas wajar.
     *
     * @return array<string, string>
     */
    public function peringatanPelaksanaan(): array
    {
        $peringatan = $this->peringatanJadwal();
        $lama = filter_var($this->lama_hari, FILTER_VALIDATE_INT);
        $peserta = filter_var($this->peserta_jumlah, FILTER_VALIDATE_INT);

        if ($lama !== false && $lama > self::BATAS_WAJAR['lama_hari'] && $lama <= 366) {
            $peringatan['lama_hari'] = "Lama kegiatan {$lama} hari (lebih dari sebulan) — pastikan bukan salah ketik.";
        }

        if ($peserta !== false && $peserta > self::BATAS_WAJAR['peserta_jumlah'] && $peserta <= 100_000) {
            $peringatan['peserta_jumlah'] = 'Jumlah peserta '.Format::angka($peserta).' orang — pastikan bukan salah ketik.';
        }

        return $peringatan;
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
