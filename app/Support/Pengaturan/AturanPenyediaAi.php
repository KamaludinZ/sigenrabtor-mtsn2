<?php

namespace App\Support\Pengaturan;

/**
 * Aturan konfigurasi penyedia AI (PRD F24): isian penyedia lokal (Ollama di server sekolah) dirapikan & diperiksa, dan
 * peran penyedia (aktif/cadangan/tidak dipakai) diterapkan tanpa membuat satu penyedia memegang dua peran.
 */
final class AturanPenyediaAi
{
    public const PERAN = ['aktif' => 'Penyedia aktif', 'cadangan' => 'Penyedia cadangan', 'tidak' => 'Tidak dipakai'];

    public const BATAS_WAKTU = [10, 300];

    /** Contoh model untuk server sekolah (saran isian, bukan daftar wajib). */
    public const CONTOH_MODEL_LOKAL = ['qwen2.5:7b', 'qwen2.5:3b', 'llama3.1:8b', 'gemma2:9b'];

    /**
     * Rapikan & periksa isian penyedia lokal.
     *
     * @param  array{base_url?: mixed, model?: mixed, batas_waktu?: mixed, peran?: mixed}  $isian
     * @return array{data: array{base_url: string, model: string, batas_waktu: int|null, peran: string}, galat: array<string, string>}
     */
    public static function lokal(array $isian): array
    {
        $data = [
            'base_url' => rtrim(trim((string) ($isian['base_url'] ?? '')), '/'),
            'model' => trim((string) ($isian['model'] ?? '')),
            'batas_waktu' => filter_var($isian['batas_waktu'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null,
            'peran' => (string) ($isian['peran'] ?? 'tidak'),
        ];
        $galat = [];

        $url = parse_url($data['base_url']);
        if ($data['base_url'] === '') {
            $galat['base_url'] = 'Alamat server wajib diisi, mis. http://localhost:11434.';
        } elseif (! is_array($url) || ! in_array($url['scheme'] ?? '', ['http', 'https'], true) || blank($url['host'] ?? null) || isset($url['query']) || isset($url['fragment'])) {
            $galat['base_url'] = 'Alamat server harus berbentuk http(s)://alamat[:port], mis. http://192.168.1.10:11434.';
        }

        if ($data['model'] === '' && $data['peran'] !== 'tidak') {
            $galat['model'] = 'Nama model wajib diisi bila penyedia ini dipakai, mis. qwen2.5:7b.';
        } elseif ($data['model'] !== '' && (mb_strlen($data['model']) > 100 || ! preg_match('/^[a-z0-9][a-z0-9._:\/-]*$/i', $data['model']))) {
            $galat['model'] = 'Nama model hanya huruf, angka, titik, titik dua, garis miring, dan tanda hubung (mis. qwen2.5:7b).';
        }

        [$min, $maks] = self::BATAS_WAKTU;
        if ($data['batas_waktu'] === null || $data['batas_waktu'] < $min || $data['batas_waktu'] > $maks) {
            $galat['batas_waktu'] = "Batas waktu {$min}–{$maks} detik.";
        }

        if (! array_key_exists($data['peran'], self::PERAN)) {
            $galat['peran'] = 'Pilih peran penyedia.';
        }

        return ['data' => $data, 'galat' => $galat];
    }

    /** Peran $penyedia dalam pengaturan: aktif, cadangan, atau tidak. */
    public static function peranDari(array $pengaturan, string $penyedia): string
    {
        return match ($penyedia) {
            $pengaturan['provider_aktif'] ?? null => 'aktif',
            $pengaturan['provider_cadangan'] ?? null => 'cadangan',
            default => 'tidak',
        };
    }

    /**
     * Terapkan peran $penyedia: menjadi aktif/cadangan menggantikan penyedia sebelumnya; satu penyedia tidak memegang dua
     * peran; tanpa penyedia aktif, cadangan naik menjadi aktif.
     *
     * @param  array<string, mixed>  $pengaturan
     * @return array<string, mixed>
     */
    public static function terapkanPeran(array $pengaturan, string $penyedia, string $peran): array
    {
        foreach (['provider_aktif', 'provider_cadangan'] as $kolom) {
            if (($pengaturan[$kolom] ?? null) === $penyedia) {
                $pengaturan[$kolom] = null;
            }
        }
        if ($peran === 'aktif') {
            $pengaturan['provider_aktif'] = $penyedia;
        } elseif ($peran === 'cadangan') {
            $pengaturan['provider_cadangan'] = $penyedia;
        }
        if ($pengaturan['provider_aktif'] === null && $pengaturan['provider_cadangan'] !== null) {
            [$pengaturan['provider_aktif'], $pengaturan['provider_cadangan']] = [$pengaturan['provider_cadangan'], null];
        }

        return $pengaturan;
    }
}
