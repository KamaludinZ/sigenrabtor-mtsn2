<?php

namespace App\Livewire\Forms;

use App\Support\Format;
use Closure;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * Isian satu item belanja RAB (PRD tabel budget_items): jumlah selalu dihitung = volume × harga satuan.
 */
class ItemBelanjaForm extends Form
{
    public const KATEGORI = ['bahan', 'jasa', 'lain'];

    public const LABEL_KATEGORI = ['bahan' => 'Bahan', 'jasa' => 'Jasa', 'lain' => 'Lain-lain'];

    /** Batas harga satuan untuk menolak salah ketik digit berlebih. */
    public const MAKS_HARGA = 10_000_000_000;

    public const SATUAN = ['OJ', 'OH', 'OK', 'orang', 'kotak', 'pack', 'paket', 'rim', 'lembar', 'buah', 'dus', 'botol', 'kali', 'kegiatan', 'bulan', 'unit'];

    public const SARAN_URAIAN = [
        'Honorarium Narasumber', 'Honorarium Pemateri', 'Honorarium Panitia', 'Nasi Kotak', 'Kue Kotak', 'Air Mineral',
        'ATK peserta', 'Fotokopi modul', 'Spanduk kegiatan', 'Sertifikat peserta', 'Dokumentasi kegiatan', 'Jilid Laporan',
        'Transport Narasumber', 'Transport Pengawas',
    ];

    /** Kode akun belanja yang lazim dipakai (MAK); isian tetap bebas. */
    public const KODE_AKUN = [
        '521211' => 'Belanja Bahan',
        '521213' => 'Belanja Honor Output Kegiatan',
        '521219' => 'Belanja Barang Non Operasional Lainnya',
        '522151' => 'Belanja Jasa Profesi',
        '524111' => 'Belanja Perjalanan Dinas Biasa',
        '524113' => 'Belanja Perjalanan Dinas Dalam Kota',
    ];

    public string $uraian = '';

    public string $kategori = '';

    /** True bila kategori dipilih manual sehingga tidak lagi ditebak dari uraian. */
    public bool $kategori_manual = false;

    public string $kode = '';

    /** Volume sebagai teks ketikan (boleh desimal dengan koma, mis. "1,5"). */
    public string $volume = '';

    public string $satuan = '';

    /** Harga satuan berformat ribuan ("600.000"). */
    public string $harga_satuan = '';

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'uraian' => ['required', 'string', 'min:3', 'max:255'],
            'kategori' => ['required', Rule::in(self::KATEGORI)],
            'kode' => ['nullable', 'string', 'max:20', 'regex:/^[0-9A-Za-z.\-]+$/'],
            'volume' => ['required', function (string $attribute, mixed $value, Closure $fail) {
                $angka = self::angkaVolume((string) $value);

                if ($angka === null) {
                    $fail('Volume harus berupa angka, mis. 6 atau 1,5.');
                } elseif ($angka <= 0) {
                    $fail('Volume harus lebih dari 0.');
                } elseif ($angka > 100_000) {
                    $fail('Volume terlalu besar (maksimal 100.000).');
                }
            }],
            'satuan' => ['required', 'string', 'max:30'],
            'harga_satuan' => ['required', function (string $attribute, mixed $value, Closure $fail) {
                $harga = Format::parseRupiah($value);

                if ($harga === null || $harga < 1) {
                    $fail('Isi harga satuan dengan angka rupiah lebih dari 0.');
                } elseif ($harga > self::MAKS_HARGA) {
                    $fail('Harga satuan terlalu besar. Periksa jumlah digit.');
                }
            }],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return ['uraian' => 'uraian', 'kategori' => 'kelompok belanja', 'kode' => 'kode akun', 'satuan' => 'satuan', 'harga_satuan' => 'harga satuan'];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'required' => 'Kolom :attribute wajib diisi.',
            'min' => 'Kolom :attribute minimal :min karakter.',
            'max' => 'Kolom :attribute maksimal :max karakter.',
            'in' => 'Pilih kelompok belanja: bahan, jasa, atau lain-lain.',
            'kode.regex' => 'Kode akun hanya boleh berisi angka, huruf, titik, atau tanda hubung.',
        ];
    }

    /**
     * "1,5" / "1.5" / "6" → angka; null bila bukan angka.
     */
    public static function angkaVolume(string $teks): ?float
    {
        $teks = str_replace(',', '.', trim($teks));

        return is_numeric($teks) ? (float) $teks : null;
    }

    /**
     * Tebak kelompok belanja dari uraian (honor/jasa → jasa, transport/perjalanan/sewa → lain, selain itu bahan).
     */
    public static function tebakKategori(string $uraian): string
    {
        $uraian = mb_strtolower($uraian);

        return match (true) {
            (bool) preg_match('/honor|narasumber|pemateri|jasa|lembur|penyelesaian tugas|pelatih/u', $uraian) => 'jasa',
            (bool) preg_match('/transport|perjalanan|akomodasi|sewa|penginapan/u', $uraian) => 'lain',
            default => 'bahan',
        };
    }

    public function sinkronKategori(): void
    {
        if (! $this->kategori_manual && trim($this->uraian) !== '') {
            $this->kategori = self::tebakKategori($this->uraian);
        }
    }

    public function rapikanHarga(): void
    {
        $harga = Format::parseRupiah($this->harga_satuan);
        $this->harga_satuan = $harga === null ? '' : Format::angka($harga);
    }

    public function jumlah(): ?int
    {
        $volume = self::angkaVolume($this->volume);
        $harga = Format::parseRupiah($this->harga_satuan);

        return $volume && $harga ? (int) round($volume * $harga) : null;
    }

    /**
     * Data item untuk disimpan; volume bulat disimpan sebagai int.
     *
     * @return array{kode: string, uraian: string, kategori: string, volume: float|int, satuan: string, harga_satuan: int}
     */
    public function dataItem(): array
    {
        $volume = self::angkaVolume($this->volume) ?? 0;

        return [
            'kode' => trim($this->kode),
            'uraian' => trim(preg_replace('/\s+/u', ' ', $this->uraian)),
            'kategori' => $this->kategori,
            'volume' => floor($volume) == $volume ? (int) $volume : round($volume, 2),
            'satuan' => trim($this->satuan),
            'harga_satuan' => (int) Format::parseRupiah($this->harga_satuan),
        ];
    }

    public function kosongkan(): void
    {
        $this->reset();
    }
}
