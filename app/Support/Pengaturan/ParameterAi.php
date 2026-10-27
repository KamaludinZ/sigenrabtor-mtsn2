<?php

namespace App\Support\Pengaturan;

/**
 * Parameter jawaban AI (PRD F24, berlaku untuk semua penyedia): kreativitas (suhu 0–1), panjang maksimal jawaban (token),
 * dan jumlah versi saran per permintaan (PRD: 2–3 versi).
 */
final class ParameterAi
{
    public const BAWAAN = ['suhu' => 0.4, 'maks_token' => 1024, 'jumlah_versi' => 3];

    /** Kunci → [suhu, label, keterangan]. */
    public const PRESET_SUHU = [
        'konsisten' => [0.2, 'Konsisten', 'Kalimat hampir sama setiap kali — cocok untuk dokumen baku.'],
        'seimbang' => [0.4, 'Seimbang', 'Variasi secukupnya antarversi (bawaan).'],
        'kreatif' => [0.8, 'Kreatif', 'Versi lebih beragam; periksa lebih teliti.'],
    ];

    /** Panjang maksimal jawaban → label. */
    public const MAKS_TOKEN = [512 => 'Pendek (± 1 paragraf)', 1024 => 'Sedang (± 2–3 paragraf)', 2048 => 'Panjang (± 5 paragraf)'];

    public const JUMLAH_VERSI = [2, 3];

    /**
     * Parameter tersimpan (dilengkapi bawaan).
     *
     * @param  array<string, mixed>  $pengaturan
     * @return array{suhu: float, maks_token: int, jumlah_versi: int}
     */
    public static function dari(array $pengaturan): array
    {
        $p = (array) ($pengaturan['parameter'] ?? []) + self::BAWAAN;

        return ['suhu' => (float) $p['suhu'], 'maks_token' => (int) $p['maks_token'], 'jumlah_versi' => (int) $p['jumlah_versi']];
    }

    /**
     * @param  array{suhu?: mixed, maks_token?: mixed, jumlah_versi?: mixed}  $isian
     * @return array{data: array{suhu: float, maks_token: int, jumlah_versi: int}, galat: array<string, string>}
     */
    public static function rapikan(array $isian): array
    {
        $suhu = is_numeric($isian['suhu'] ?? null) ? round((float) $isian['suhu'], 1) : null;
        $maks = filter_var($isian['maks_token'] ?? null, FILTER_VALIDATE_INT);
        $versi = filter_var($isian['jumlah_versi'] ?? null, FILTER_VALIDATE_INT);
        $galat = array_filter([
            'suhu' => $suhu === null || $suhu < 0 || $suhu > 1 ? 'Kreativitas antara 0 dan 1.' : null,
            'maks_token' => ! isset(self::MAKS_TOKEN[$maks]) ? 'Pilih panjang jawaban.' : null,
            'jumlah_versi' => ! in_array($versi, self::JUMLAH_VERSI, true) ? 'Jumlah versi 2 atau 3.' : null,
        ]);

        return ['data' => ['suhu' => (float) $suhu, 'maks_token' => (int) $maks, 'jumlah_versi' => (int) $versi], 'galat' => $galat];
    }

    /** Preset yang cocok dengan suhu; null bila suhu diatur sendiri. */
    public static function preset(float $suhu): ?string
    {
        foreach (self::PRESET_SUHU as $kunci => [$nilai]) {
            if (abs($nilai - $suhu) < 0.001) {
                return $kunci;
            }
        }

        return null;
    }
}
