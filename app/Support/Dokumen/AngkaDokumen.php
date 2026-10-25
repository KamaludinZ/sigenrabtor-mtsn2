<?php

namespace App\Support\Dokumen;

use App\Support\Format;
use App\Support\Kegiatan\KalimatKak;
use App\Support\Rab\HasilRab;
use App\Support\Terbilang;

/**
 * Satu-satunya sumber nominal & terbilang yang tercetak di dokumen satu kegiatan × sumber dana.
 *
 * KAK bagian 8 Pembiayaan, header RAB "Jumlah yang diajukan", baris "Total keseluruhan" tabel RAB, dan variabel
 * template ${total}/${terbilang} semuanya diturunkan dari total RAB yang sama (PRD aturan 1–2), sehingga angka
 * dan terbilang tidak mungkin berbeda antardokumen.
 */
final readonly class AngkaDokumen
{
    public function __construct(
        public string $sumber,
        public int $total,
        public int $tahun,
        public string $madrasah,
    ) {}

    public static function dari(HasilRab $hasil, string $sumber, int $tahun, string $madrasah): self
    {
        return new self($sumber, $hasil->total, $tahun, $madrasah);
    }

    /** Tanpa rincian (total nol) dokumen tidak memuat nominal & terbilang. */
    public function adaRincian(): bool
    {
        return $this->total > 0;
    }

    /** "Rp10.000.000,-" — gaya baku KAK dan header RAB. */
    public function nominal(): string
    {
        return Format::rupiahDokumen($this->total);
    }

    /** "10.000.000" — kolom Jumlah (Rp) pada baris Total keseluruhan. */
    public function angkaTabel(): string
    {
        return Format::angka($this->total);
    }

    public function terbilang(): ?string
    {
        return Terbilang::tampil($this->total);
    }

    /** "Rp10.000.000,- (Sepuluh Juta Rupiah)"; null bila belum ada rincian. */
    public function nominalDanTerbilang(): ?string
    {
        return $this->adaRincian() ? $this->nominal().' ('.$this->terbilang().')' : null;
    }

    /** Sebutan sumber dana di dokumen, mis. "DIPA MTsN 2 Kota Malang Tahun 2026" (header RAB & Pembiayaan KAK). */
    public function sebutanSumber(): string
    {
        return KalimatKak::sumberDana($this->sumber, $this->madrasah, $this->tahun);
    }

    public function kalimatPembiayaan(): ?string
    {
        return $this->adaRincian() ? KalimatKak::pembiayaan($this->total, $this->sumber, $this->tahun, $this->madrasah) : null;
    }

    /**
     * Nilai variabel template KAK/RAB (PRD 7C).
     *
     * @return array{total: string, terbilang: string}
     */
    public function variabel(): array
    {
        return ['total' => $this->nominal(), 'terbilang' => $this->terbilang() ?? ''];
    }

    /**
     * Setiap tempat total tercetak, beserta teksnya dan hasil pemeriksaan pasangan nominal–terbilang.
     *
     * @return list<array{kunci: string, dokumen: string, bagian: string, teks: string|null, temuan: list<array{nominal: int, tertulis: string, seharusnya: string, masalah: string}>}>
     */
    public function tempatCetak(): array
    {
        $tempat = [
            ['kunci' => 'kak-pembiayaan', 'dokumen' => 'KAK', 'bagian' => '8. Pembiayaan', 'teks' => $this->kalimatPembiayaan()],
            ['kunci' => 'rab-diajukan', 'dokumen' => 'RAB', 'bagian' => 'Jumlah yang diajukan', 'teks' => $this->nominalDanTerbilang()],
            ['kunci' => 'rab-total', 'dokumen' => 'RAB', 'bagian' => 'Total keseluruhan', 'teks' => $this->adaRincian() ? $this->angkaTabel() : null],
        ];

        return array_map(fn ($t) => $t + ['temuan' => $t['teks'] ? $this->periksa($t['teks']) : []], $tempat);
    }

    /**
     * Teks dengan pasangan nominal–terbilang diperiksa keduanya; angka polos (kolom tabel) cukup dibandingkan dengan total.
     *
     * @return list<array{nominal: int, tertulis: string, seharusnya: string, masalah: string}>
     */
    private function periksa(string $teks): array
    {
        if (PemeriksaTerbilang::jumlahPasangan($teks) > 0) {
            return PemeriksaTerbilang::periksa($teks, $this->total);
        }

        $nominal = Format::parseRupiah($teks);

        return $nominal === $this->total ? [] : [[
            'nominal' => (int) $nominal, 'tertulis' => $teks, 'seharusnya' => $this->angkaTabel(), 'masalah' => 'Nominal berbeda dari total RAB.',
        ]];
    }

    /** Semua tempat cetak bebas temuan. */
    public function sinkron(): bool
    {
        return collect($this->tempatCetak())->every(fn ($t) => $t['temuan'] === []);
    }
}
