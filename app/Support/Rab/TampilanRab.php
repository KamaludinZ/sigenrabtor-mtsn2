<?php

namespace App\Support\Rab;

/**
 * Logika render halaman RAB berdasarkan sumber dana kegiatan:
 * - mode "tanpa_sumber" (belum memilih DIPA/Komite), "tunggal" (satu RAB), atau "ganda" (DIPA + Komite);
 * - urutan sumber baku (DIPA lalu Komite) dan sumber yang dibuka di penyunting (fallback ke sumber pertama);
 * - halaman yang tersedia: "Semua RAB" hanya untuk kegiatan dua sumber; "Termin pembayaran" per sumber dana;
 * - RAB tersimpan milik sumber yang sudah dilepas (tidak dicetak, tampil baca-saja).
 */
final readonly class TampilanRab
{
    public const HALAMAN = ['rincian' => 'Rincian belanja', 'semua' => 'Semua RAB', 'rekap' => 'Rekap per kelompok', 'termin' => 'Termin pembayaran'];

    /** Halaman yang menampilkan satu RAB (sumber dana aktif); pindah kartu sumber tetap di halaman yang sama. */
    public const HALAMAN_PER_SUMBER = ['rincian', 'termin'];

    /**
     * @param  list<string>  $sumber  Sumber dana terpilih, urut baku.
     * @param  list<string>  $terlepas  Sumber dengan RAB tersimpan yang tidak lagi dipilih.
     */
    public function __construct(
        public string $mode,
        public array $sumber,
        public array $terlepas,
        public ?string $aktif,
        public string $halaman,
    ) {}

    /**
     * @param  list<AnggaranSumber>  $anggaran  Hasil AnggaranKegiatan::untuk(..., sertakanTerlepas: true).
     */
    public static function tentukan(array $anggaran, ?string $sumberDiminta, ?string $halamanDiminta): self
    {
        $sumber = array_values(array_map(fn (AnggaranSumber $a) => $a->sumber, array_filter($anggaran, fn (AnggaranSumber $a) => $a->dipilih)));
        $terlepas = array_values(array_map(fn (AnggaranSumber $a) => $a->sumber, array_filter($anggaran, fn (AnggaranSumber $a) => ! $a->dipilih)));

        $mode = match (count($sumber)) {
            0 => 'tanpa_sumber',
            1 => 'tunggal',
            default => 'ganda',
        };

        $tersedia = self::halamanUntuk($mode);
        $halaman = isset($tersedia[$halamanDiminta]) ? $halamanDiminta : 'rincian';

        return new self(
            $mode,
            $sumber,
            $terlepas,
            in_array($sumberDiminta, $sumber, true) ? $sumberDiminta : ($sumber[0] ?? null),
            $halaman,
        );
    }

    /**
     * Halaman RAB yang bisa dibuka untuk mode ini.
     *
     * @return array<string, string>
     */
    public function halamanTersedia(): array
    {
        return self::halamanUntuk($this->mode);
    }

    /**
     * @return array<string, string>
     */
    private static function halamanUntuk(string $mode): array
    {
        return match ($mode) {
            'tanpa_sumber' => [],
            'tunggal' => array_diff_key(self::HALAMAN, ['semua' => true]),
            default => self::HALAMAN,
        };
    }
}
