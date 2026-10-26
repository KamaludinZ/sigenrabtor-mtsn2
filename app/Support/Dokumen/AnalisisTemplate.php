<?php

namespace App\Support\Dokumen;

/**
 * Hasil pemeriksaan variabel master KAK/TOR: tiap variabel yang ditemukan beserta status terdaftar dan saran perbaikan
 * salah tulis. Template baru boleh disimpan bila memuat minimal satu variabel dan semuanya terdaftar (PRD §7C).
 */
final readonly class AnalisisTemplate
{
    /**
     * @param  list<array{nama: string, jumlah: int, terdaftar: bool, saran: string|null, keterangan: string|null, kelompok: string|null, jenis: string|null}>  $variabel
     */
    public function __construct(
        public array $variabel,
    ) {}

    /**
     * @param  array<string, int>  $temuan  Hasil PembacaTemplateDocx::variabel().
     */
    public static function dari(array $temuan): self
    {
        return new self(array_map(function (string $nama, int $jumlah) {
            $info = VariabelTemplate::DAFTAR[$nama] ?? null;

            return [
                'nama' => $nama,
                'jumlah' => $jumlah,
                'terdaftar' => $info !== null,
                'saran' => $info === null ? VariabelTemplate::saran($nama) : null,
                'keterangan' => $info[0] ?? null,
                'kelompok' => $info[1] ?? null,
                'jenis' => $info[2] ?? null,
            ];
        }, array_keys($temuan), $temuan));
    }

    /**
     * @return list<array{nama: string, jumlah: int, terdaftar: bool, saran: string|null, keterangan: string|null, kelompok: string|null, jenis: string|null}>
     */
    public function tidakTerdaftar(): array
    {
        return array_values(array_filter($this->variabel, fn (array $v) => ! $v['terdaftar']));
    }

    /**
     * Variabel dianjurkan yang belum dipakai (hanya pengingat).
     *
     * @return list<string>
     */
    public function dianjurkanBelumAda(): array
    {
        return array_values(array_diff(VariabelTemplate::DIANJURKAN, array_column($this->variabel, 'nama')));
    }

    public function bisaDisimpan(): bool
    {
        return $this->variabel !== [] && $this->tidakTerdaftar() === [];
    }

    /**
     * Nama variabel terdaftar (untuk disimpan bersama template).
     *
     * @return list<string>
     */
    public function namaTerdaftar(): array
    {
        return array_values(array_column(array_filter($this->variabel, fn (array $v) => $v['terdaftar']), 'nama'));
    }
}
