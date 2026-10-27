<?php

namespace App\Support\Kegiatan;

use App\Enums\StatusKegiatan;
use App\Enums\SumberDana;
use App\Rules\KalimatUtuh;
use App\Support\Format;
use App\Support\Master\IkskPerSk;
use App\Support\MasterKode;
use App\Support\Stub\StubData;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Validasi sisi server data kegiatan (bentuk data KegiatanRepository) — satu sumber aturan, pesan, dan label untuk form
 * Data Kegiatan (KegiatanForm, dipakai juga Kode & Klasifikasi dan Kalimat KAK) dan jalur simpan lain (impor Excel &
 * cascade Perkin). Draf boleh belum lengkap: yang diperiksa hanya bentuk & batas isian, kecocokan dengan master tahun
 * aktif, dan konsistensi antarkolom (tanggal, IKSK–SK); syarat pengajuan diperiksa terpisah (Form::ubahStatus).
 */
final class ValidasiKegiatan
{
    /** Batas pagu per sumber dana (Rp) untuk menolak salah ketik digit berlebih. */
    public const MAKS_PAGU = 100_000_000_000;

    public const MAKS_PANITIA = 50;

    /** Asal kegiatan (kolom activities.asal). */
    public const ASAL = ['manual', 'impor_excel', 'impor_perkin'];

    /**
     * @param  array<string, mixed>  $data  Data yang divalidasi; aturan antarkolom membaca tanggal & kode SK darinya.
     * @param  array<string, mixed>|null  $pengguna  Akun yang menyimpan (menentukan pilihan leading); null = akun saat ini.
     * @param  string|null  $leadingTersimpan  Leading tersimpan kegiatan ini — tetap sah walau kini nonaktif.
     * @return array<string, mixed>
     */
    public static function aturan(array $data, ?array $pengguna = null, ?string $leadingTersimpan = null): array
    {
        $tahun = StubData::tahunAktif()['tahun'];
        $pelaksanaan = DataPelaksanaan::dari($data);
        $mulai = $pelaksanaan->mulai;
        $selesai = $pelaksanaan->selesai();

        return [
            'nama_kegiatan' => ['required', 'string', 'min:5', 'max:255'],
            'nama_kegiatan_cover' => ['required', 'string', 'max:255'],
            'cover_manual' => ['boolean'],
            'latar_belakang' => ['nullable', 'string', 'max:5000', new KalimatUtuh],
            'tujuan' => ['nullable', 'string', 'max:2000', new KalimatUtuh],
            'sasaran' => ['nullable', 'string', 'max:2000', new KalimatUtuh],
            'tanggal_dokumen' => ['nullable', 'date', 'after_or_equal:'.($tahun - 1).'-01-01', "before_or_equal:{$tahun}-12-31", self::dokumenSebelumMulai($mulai)],
            'tanggal_laporan' => ['nullable', 'date', 'after_or_equal:tanggal_dokumen', self::laporanSetelahSelesai($selesai)],
            'laporan_otomatis' => ['boolean'],
            'tanggal_mulai' => ['nullable', 'date', "after_or_equal:{$tahun}-01-01", "before_or_equal:{$tahun}-12-31"],
            'lama_hari' => ['nullable', 'integer', 'min:1', 'max:366', self::selesaiDalamTahun($selesai, $tahun)],
            'hari_kerja' => ['boolean'],
            'bulan_pelaksanaan' => ['array', 'max:12', self::bulanMencakupTanggal($mulai, $selesai)],
            'bulan_pelaksanaan.*' => ['integer', 'between:1,12'],
            'leading' => ['nullable', Rule::in(array_column(MasterKode::pilihanLeading($pengguna ?? StubData::currentUser(), $leadingTersimpan), 'kode'))],
            'kode_rktm' => ['nullable', Rule::in(array_column(MasterKode::kodeRktm(), 'kode'))],
            'kode_sk' => ['nullable', Rule::in(array_column(MasterKode::kodeSk(), 'kode'))],
            'kode_iksk' => ['nullable', Rule::in(array_column(MasterKode::kodeIksk(), 'kode')), self::ikskSesuaiSk($data['kode_sk'] ?? null)],
            'kode_edm' => ['nullable', Rule::in(array_column(MasterKode::kodeEdm(), 'kode'))],
            'budaya_mutu' => ['nullable', Rule::in(array_column(MasterKode::budayaMutu(), 'nomor'))],
            'tempat' => ['nullable', 'string', 'max:255'],
            'peserta_jumlah' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'peserta_jenis' => ['nullable', 'string', 'max:150'],
            'uraian_pelaksanaan' => ['nullable', 'string', 'max:3000', new KalimatUtuh],
            'konteks' => ['nullable', 'string', 'max:'.DataKonteks::BATAS],
            'sumber_dana' => ['array', 'max:'.count(SumberDana::cases())],
            'sumber_dana.*.sumber' => ['required', 'distinct', Rule::enum(SumberDana::class)],
            'sumber_dana.*.pagu' => ['nullable', 'integer', 'min:1', 'max:'.self::MAKS_PAGU],
            'data_pagu_id' => ['nullable', 'uuid'],
            'panitia' => ['array', 'max:'.self::MAKS_PANITIA],
            'panitia.*.nama' => ['required', 'string', 'max:150'],
            'panitia.*.jabatan' => ['nullable', 'string', 'max:150'],
            'panitia.*.peran' => ['required', 'string', 'max:100'],
            'status' => [Rule::enum(StatusKegiatan::class)],
            'asal' => [Rule::in(self::ASAL)],
        ];
    }

    /** @return array<string, string> */
    public static function pesan(): array
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
            'lama_hari.integer' => 'Lama kegiatan harus angka bulat (jumlah hari), mis. 3.',
            'peserta_jumlah.integer' => 'Jumlah peserta harus angka bulat tanpa titik atau koma, mis. 56.',
            'array' => 'Kolom :attribute tidak valid.',
            'bulan_pelaksanaan.max' => 'Bulan pelaksanaan paling banyak 12.',
            'bulan_pelaksanaan.*.integer' => 'Bulan pelaksanaan tidak valid.',
            'bulan_pelaksanaan.*.between' => 'Bulan pelaksanaan harus antara Januari dan Desember.',
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
            'sumber_dana.max' => 'Sumber dana paling banyak '.count(SumberDana::cases()).' (DIPA dan Komite).',
            'sumber_dana.*.sumber.enum' => 'Sumber dana harus DIPA atau Komite.',
            'sumber_dana.*.sumber.distinct' => 'Sumber dana :input dipilih lebih dari sekali.',
            'sumber_dana.*.pagu.integer' => 'Pagu harus angka rupiah bulat.',
            'sumber_dana.*.pagu.min' => 'Pagu harus lebih dari Rp0.',
            'sumber_dana.*.pagu.max' => 'Pagu terlalu besar (maksimal '.Format::rupiah(self::MAKS_PAGU).'). Periksa jumlah digit.',
            'data_pagu_id.uuid' => 'Tautan Data Pagu tidak valid.',
            'status.enum' => 'Status kegiatan tidak dikenal.',
            'asal.in' => 'Asal kegiatan tidak dikenal.',
        ];
    }

    /** @return array<string, string> */
    public static function atribut(): array
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
            'sumber_dana' => 'sumber dana',
            'panitia.*.nama' => 'nama anggota',
            'panitia.*.jabatan' => 'jabatan anggota',
            'panitia.*.peran' => 'peran anggota',
        ];
    }

    /**
     * Pesan galat data kegiatan sebelum disimpan lewat jalur selain form (impor); kosong bila sah.
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>  $kecuali  Isian yang sudah diperiksa pemanggil dengan pesannya sendiri (mis. leading).
     * @param  array<string, mixed>|null  $pengguna
     * @return list<string>
     */
    public static function galat(array $data, array $kecuali = [], ?array $pengguna = null, ?string $leadingTersimpan = null): array
    {
        return self::validator($data, fn (string $isian) => ! in_array($isian, $kecuali, true), $pengguna, $leadingTersimpan)->errors()->all();
    }

    /**
     * Periksa hanya isian $hanya (mis. kode & klasifikasi saat disimpan terpisah); aturan antarkolom tetap membaca $data
     * utuh. Galat dilempar sebagai ValidationException berkunci isian.
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>  $hanya
     * @param  array<string, mixed>|null  $pengguna
     *
     * @throws ValidationException
     */
    public static function periksa(array $data, array $hanya, ?array $pengguna = null, ?string $leadingTersimpan = null): void
    {
        self::validator($data, fn (string $isian) => in_array($isian, $hanya, true), $pengguna, $leadingTersimpan)->validate();
    }

    /** @param  Closure(string): bool  $dipakai  Isian (akar kunci aturan) yang diperiksa. */
    private static function validator(array $data, Closure $dipakai, ?array $pengguna, ?string $leadingTersimpan): \Illuminate\Validation\Validator
    {
        $aturan = array_filter(self::aturan($data, $pengguna, $leadingTersimpan), fn (string $kunci) => $dipakai(Str::before($kunci, '.')), ARRAY_FILTER_USE_KEY);

        return Validator::make($data, $aturan, self::pesan(), self::atribut());
    }

    private static function selesaiDalamTahun(?CarbonImmutable $selesai, int $tahun): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($selesai, $tahun) {
            if ($selesai && $selesai->year > $tahun) {
                $fail('Kegiatan selesai '.self::teksTanggal($selesai).", melewati akhir Tahun Anggaran {$tahun}.");
            }
        };
    }

    private static function laporanSetelahSelesai(?CarbonImmutable $selesai): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($selesai) {
            if ($selesai && strtotime((string) $value) && CarbonImmutable::parse($value)->lt($selesai)) {
                $fail('Tanggal pelaporan tidak boleh sebelum kegiatan selesai ('.self::teksTanggal($selesai).').');
            }
        };
    }

    /** KAK adalah rencana: tanggal pengesahannya tidak boleh setelah kegiatan dimulai. */
    private static function dokumenSebelumMulai(?CarbonImmutable $mulai): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($mulai) {
            if ($mulai && strtotime((string) $value) && CarbonImmutable::parse($value)->gt($mulai)) {
                $fail('Tanggal dokumen harus sebelum atau sama dengan tanggal mulai kegiatan ('.self::teksTanggal($mulai).').');
            }
        };
    }

    /** Bulan pelaksanaan boleh lebih luas (mis. bulan persiapan) tetapi wajib mencakup bulan kegiatan berlangsung. */
    private static function bulanMencakupTanggal(?CarbonImmutable $mulai, ?CarbonImmutable $selesai): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($mulai, $selesai) {
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

    /** IKSK harus indikator SK terpilih (IkskPerSk::galat). */
    private static function ikskSesuaiSk(?string $kodeSk): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($kodeSk) {
            if ($pesan = IkskPerSk::galat($kodeSk, filled($value) ? (string) $value : null)) {
                $fail($pesan);
            }
        };
    }

    private static function teksTanggal(CarbonImmutable $tanggal): string
    {
        return $tanggal->locale('id')->translatedFormat('j F Y');
    }
}
