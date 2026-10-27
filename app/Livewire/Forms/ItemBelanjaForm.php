<?php

namespace App\Livewire\Forms;

use App\Support\Format;
use App\Support\Rab\KalkulatorRab;
use App\Support\Rab\RincianRab;
use App\Support\Rab\ValidasiItemBelanja;
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

    public const MAKS_VOLUME = 100_000;

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

    /** Aturan, label, dan pesan bersama ValidasiItemBelanja (juga dipakai RincianRab di server). */
    protected function rules(): array
    {
        return ValidasiItemBelanja::aturan();
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return ValidasiItemBelanja::atribut();
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return ValidasiItemBelanja::pesan();
    }

    /**
     * Baca volume format Indonesia: "1,5" → 1.5, "1.000" → 1000, "1.250,5" → 1250.5, "6" → 6; null bila bukan angka.
     */
    public static function angkaVolume(string $teks): ?float
    {
        $teks = str_replace(' ', '', trim($teks));

        if (str_contains($teks, ',')) {
            $teks = str_replace(['.', ','], ['', '.'], $teks);
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $teks)) {
            $teks = str_replace('.', '', $teks);
        }

        return is_numeric($teks) ? (float) $teks : null;
    }

    /**
     * Volume untuk diisi ulang ke input: tanpa pemisah ribuan, pecahan memakai koma (1.5 → "1,5").
     */
    public static function teksVolume(int|float $volume): string
    {
        return str_replace('.', ',', (string) ($volume + 0));
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

        // Perkiraan hanya untuk angka dalam batas aturan (volume ≤ 100.000, harga ≤ MAKS_HARGA): ketikan di luar batas
        // ditolak validasi dan tidak boleh meluapkan perhitungan.
        return $volume > 0 && $volume <= self::MAKS_VOLUME && $harga > 0 && $harga <= self::MAKS_HARGA ? KalkulatorRab::jumlah($volume, $harga) : null;
    }

    /**
     * Data item untuk disimpan; volume bulat disimpan sebagai int.
     *
     * @return array{kode: string, uraian: string, kategori: string, volume: float|int, satuan: string, harga_satuan: int}
     */
    public function dataItem(): array
    {
        return RincianRab::rapikan(['kode' => $this->kode, 'uraian' => $this->uraian, 'kategori' => $this->kategori, 'volume' => $this->volume, 'satuan' => $this->satuan, 'harga_satuan' => $this->harga_satuan]);
    }

    /**
     * Isi form dari item tersimpan (untuk diubah). Kategori dianggap pilihan manual agar tidak ditebak ulang.
     *
     * @param  array<string, mixed>  $item
     */
    public function isiDari(array $item): void
    {
        $this->uraian = (string) ($item['uraian'] ?? '');
        $this->kategori = (string) ($item['kategori'] ?? '');
        $this->kategori_manual = $this->kategori !== '';
        $this->kode = (string) ($item['kode'] ?? '');
        $this->volume = isset($item['volume']) ? self::teksVolume($item['volume']) : '';
        $this->satuan = (string) ($item['satuan'] ?? '');
        $this->harga_satuan = isset($item['harga_satuan']) ? Format::angka((int) $item['harga_satuan']) : '';
    }

    public function kosongkan(): void
    {
        $this->reset();
    }
}
