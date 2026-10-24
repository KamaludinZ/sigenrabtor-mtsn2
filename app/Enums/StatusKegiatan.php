<?php

namespace App\Enums;

/**
 * Status dokumen kegiatan (PRD F17/F18): Draf → Diperiksa → Disetujui → Final, dengan cabang Dikembalikan.
 */
enum StatusKegiatan: string
{
    case Draf = 'draf';
    case Diperiksa = 'diperiksa';
    case Dikembalikan = 'dikembalikan';
    case Disetujui = 'disetujui';
    case Final = 'final';

    public function label(): string
    {
        return match ($this) {
            self::Draf => 'Draf',
            self::Diperiksa => 'Diperiksa',
            self::Dikembalikan => 'Dikembalikan',
            self::Disetujui => 'Disetujui',
            self::Final => 'Final',
        };
    }

    public function deskripsi(): string
    {
        return match ($this) {
            self::Draf => 'Data masih bisa diubah. Dokumen dicetak dengan tanda “DRAF”.',
            self::Diperiksa => 'Sedang diperiksa admin. Isian dikunci sampai disetujui atau dikembalikan.',
            self::Dikembalikan => 'Dikembalikan admin untuk diperbaiki. Perbaiki data, lalu ajukan ulang.',
            self::Disetujui => 'Disetujui. Cetak, tanda tangani, lalu unggah arsip final.',
            self::Final => 'Final dan terkunci. Hanya admin yang dapat membuka kunci.',
        };
    }

    /** Hanya Draf dan Dikembalikan yang datanya boleh diubah. */
    public function dapatDiedit(): bool
    {
        return in_array($this, [self::Draf, self::Dikembalikan], true);
    }

    /**
     * Urutan langkah alur utama untuk penanda kemajuan (Dikembalikan dipetakan ke langkah Draf).
     */
    public function langkah(): int
    {
        return match ($this) {
            self::Draf, self::Dikembalikan => 0,
            self::Diperiksa => 1,
            self::Disetujui => 2,
            self::Final => 3,
        };
    }

    /**
     * @return array<string, array{label: string, dari: list<self>, ke: self, peran: list<string>, gaya: string, ikon: string, konfirmasi: string}>
     */
    public static function semuaAksi(): array
    {
        return [
            'ajukan' => [
                'label' => 'Ajukan pemeriksaan', 'dari' => [self::Draf, self::Dikembalikan], 'ke' => self::Diperiksa,
                'peran' => ['leading', 'admin'], 'gaya' => 'primary', 'ikon' => 'check-badge',
                'konfirmasi' => 'Data akan dikunci selama diperiksa admin.',
            ],
            'tarik' => [
                'label' => 'Tarik pengajuan', 'dari' => [self::Diperiksa], 'ke' => self::Draf,
                'peran' => ['leading'], 'gaya' => 'secondary', 'ikon' => 'history',
                'konfirmasi' => 'Pengajuan dibatalkan dan data bisa diubah lagi.',
            ],
            'setujui' => [
                'label' => 'Setujui', 'dari' => [self::Diperiksa], 'ke' => self::Disetujui,
                'peran' => ['admin'], 'gaya' => 'primary', 'ikon' => 'check',
                'konfirmasi' => 'Kegiatan disetujui dan siap dicetak untuk ditandatangani.',
            ],
            'kembalikan' => [
                'label' => 'Kembalikan', 'dari' => [self::Diperiksa, self::Disetujui], 'ke' => self::Dikembalikan,
                'peran' => ['admin'], 'gaya' => 'secondary', 'ikon' => 'arrow-down',
                'konfirmasi' => 'Leading dapat memperbaiki data lalu mengajukan ulang.',
            ],
            'finalkan' => [
                'label' => 'Tandai final', 'dari' => [self::Disetujui], 'ke' => self::Final,
                'peran' => ['leading', 'admin'], 'gaya' => 'primary', 'ikon' => 'lock',
                'konfirmasi' => 'Data dikunci permanen. Unggah arsip PDF bertanda tangan menyusul.',
            ],
            'buka_kunci' => [
                'label' => 'Buka kunci', 'dari' => [self::Final], 'ke' => self::Dikembalikan,
                'peran' => ['admin'], 'gaya' => 'danger', 'ikon' => 'lock',
                'konfirmasi' => 'Kunci dibuka untuk perbaikan; tercatat siapa dan kapan.',
            ],
        ];
    }

    /**
     * Kalimat riwayat untuk tiap aksi, mis. "Diajukan untuk diperiksa".
     */
    public static function labelRiwayat(string $aksi): string
    {
        return match ($aksi) {
            'dibuat' => 'Draf dibuat',
            'ajukan' => 'Diajukan untuk diperiksa',
            'tarik' => 'Pengajuan ditarik',
            'setujui' => 'Disetujui',
            'kembalikan' => 'Dikembalikan untuk perbaikan',
            'finalkan' => 'Ditandai final',
            'buka_kunci' => 'Kunci dibuka',
            default => ucfirst(str_replace('_', ' ', $aksi)),
        };
    }

    /**
     * Aksi yang tersedia dari status ini untuk peran tertentu.
     *
     * @return array<string, array{label: string, dari: list<self>, ke: self, peran: list<string>, gaya: string, ikon: string, konfirmasi: string}>
     */
    public function aksiUntuk(string $peran): array
    {
        return array_filter(
            self::semuaAksi(),
            fn (array $aksi) => in_array($this, $aksi['dari'], true) && in_array($peran, $aksi['peran'], true),
        );
    }
}
