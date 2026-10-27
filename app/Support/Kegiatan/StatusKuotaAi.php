<?php

namespace App\Support\Kegiatan;

use App\Contracts\PemakaianAiRepository;

/**
 * Status kuota AI harian satu akun untuk ditampilkan ke penggunanya (<x-ai.status-kuota>): batas berlaku (umum/khusus),
 * terpakai, sisa, persen, tingkat (aman/menipis/habis), waktu pulih, dan rincian pemakaian hari ini per fitur.
 */
final class StatusKuotaAi
{
    /** Tingkat → [label, kelas teks, kelas batang]. */
    public const TINGKAT = [
        'aman' => ['Cukup', 'text-brand-800', 'bg-brand-600'],
        'menipis' => ['Menipis', 'text-gold-700', 'bg-gold-500'],
        'habis' => ['Habis', 'text-red-700', 'bg-red-500'],
    ];

    /**
     * @param  array{id: int}  $pengguna
     * @return array{batas: int, terpakai: int, sisa: int, persen: int, tingkat: string, khusus: bool, pulih: string, rincian: list<array{fitur: string, label: string, jumlah: int}>}
     */
    public static function untuk(array $pengguna): array
    {
        $batas = BatasAi::batas($pengguna);
        $terpakai = BatasAi::terpakai($pengguna);
        $sisa = max(0, $batas - $terpakai);
        $rincian = app(PemakaianAiRepository::class)->rincian((int) $pengguna['id'], now()->toDateString());
        arsort($rincian);

        return [
            'batas' => $batas,
            'terpakai' => $terpakai,
            'sisa' => $sisa,
            'persen' => $batas > 0 ? min(100, (int) round($terpakai / $batas * 100)) : 100,
            'tingkat' => self::tingkat($sisa, $batas),
            'khusus' => BatasAi::khusus($pengguna) !== null,
            'pulih' => '00.00',
            'rincian' => array_values(array_map(fn (string $fitur, int $jumlah) => ['fitur' => $fitur, 'label' => self::labelFitur($fitur), 'jumlah' => $jumlah], array_keys($rincian), $rincian)),
        ];
    }

    /** Habis = sisa 0; menipis = sisa ≤ 20% batas (min. 2); selain itu aman. */
    public static function tingkat(int $sisa, int $batas): string
    {
        return match (true) {
            $sisa <= 0 => 'habis',
            $sisa <= max(2, (int) floor($batas * 0.2)) => 'menipis',
            default => 'aman',
        };
    }

    public static function labelFitur(string $fitur): string
    {
        return $fitur === 'usul_jadwal' ? 'Usulan jadwal' : (BagianAi::BAGIAN[$fitur]['judul'] ?? $fitur);
    }
}
