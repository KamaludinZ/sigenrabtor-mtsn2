<?php

namespace App\Enums;

/**
 * Sumber dana kegiatan (PRD F5). Nilai = isi kolom anggaran_kegiatan.sumber_dana; urutan kasus = urutan baku tampil.
 * Setiap sumber dana punya RAB, dokumen, dan buku cetak sendiri.
 */
enum SumberDana: string
{
    case Dipa = 'DIPA';
    case Komite = 'Komite';

    /** Kunci huruf kecil untuk nama isian form (dana_dipa, pagu_dipa) dan data-testid. */
    public function kunci(): string
    {
        return mb_strtolower($this->value);
    }

    /** Kebalikan kunci(): "dipa" → DIPA; null bila tidak dikenal. */
    public static function dariKunci(string $kunci): ?self
    {
        foreach (self::cases() as $sumber) {
            if ($sumber->kunci() === $kunci) {
                return $sumber;
            }
        }

        return null;
    }

    public function keterangan(): string
    {
        return match ($this) {
            self::Dipa => 'Daftar Isian Pelaksanaan Anggaran (APBN).',
            self::Komite => 'Dana komite madrasah.',
        };
    }

    /** Label RAB di layar, mis. "RAB DIPA". */
    public function labelRab(): string
    {
        return 'RAB '.$this->value;
    }

    /**
     * Sebutan sumber dana di dokumen cetak, mis. "KOMITE MTsN 2 Kota Malang Tahun 2026" — dipakai kalimat Pembiayaan
     * KAK ("…dibebankan pada dana …") dan header RAB, sehingga keduanya selalu sama.
     */
    public function sebutanDokumen(string $madrasah, int $tahun): string
    {
        return mb_strtoupper($this->value)." {$madrasah} Tahun {$tahun}";
    }

    /** Kelas warna lencana per sumber dana (dibedakan dari warna status kegiatan & kelompok belanja). */
    public function warna(): string
    {
        return match ($this) {
            self::Dipa => 'bg-indigo-50 text-indigo-800 ring-indigo-200',
            self::Komite => 'bg-gold-50 text-gold-700 ring-gold-300',
        };
    }

    /**
     * @return list<string>
     */
    public static function urutan(): array
    {
        return array_column(self::cases(), 'value');
    }
}
