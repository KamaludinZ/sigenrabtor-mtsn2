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

    /**
     * Kesiapan satu penyedia untuk dipakai: Ollama perlu alamat server & model; API cloud perlu model & kunci API.
     *
     * @param  array<string, mixed>  $pengaturan
     * @return array{siap: bool, kurang: list<string>}
     */
    public static function siap(array $pengaturan, string $penyedia): array
    {
        $info = PenyediaAi::DAFTAR[$penyedia] ?? null;
        if ($info === null) {
            return ['siap' => false, 'kurang' => ['penyedia tidak dikenal']];
        }
        $konfigurasi = $pengaturan['penyedia'][$penyedia] ?? [];
        $kurang = array_values(array_filter([
            ! $info['butuh_kunci'] && blank($konfigurasi['base_url'] ?? null) ? 'alamat server' : null,
            blank($konfigurasi['model'] ?? null) ? 'model' : null,
            $info['butuh_kunci'] && ! ($konfigurasi['ada_kunci'] ?? false) ? 'kunci API' : null,
        ]));

        return ['siap' => $kurang === [], 'kurang' => $kurang];
    }

    /**
     * Pilih penyedia aktif & cadangan sekaligus: aktif null = tanpa AI (saran tiruan); cadangan butuh penyedia aktif,
     * berbeda dari aktif, dan keduanya harus siap (siap()).
     *
     * @param  array<string, mixed>  $pengaturan
     * @return array{pengaturan: array<string, mixed>, galat: array<string, string>}
     */
    public static function pilih(array $pengaturan, ?string $aktif, ?string $cadangan): array
    {
        $aktif = $aktif ?: null;
        $cadangan = $cadangan ?: null;
        $galat = [];

        foreach (['aktif' => $aktif, 'cadangan' => $cadangan] as $peran => $penyedia) {
            if ($penyedia === null) {
                continue;
            }
            $kesiapan = self::siap($pengaturan, $penyedia);
            if (! $kesiapan['siap']) {
                $galat[$peran] = PenyediaAi::nama($penyedia).' belum siap — lengkapi '.implode(', ', $kesiapan['kurang']).' dulu.';
            }
        }
        if ($cadangan !== null && $aktif === null) {
            $galat['cadangan'] = 'Pilih penyedia aktif dulu; cadangan hanya dipakai bila penyedia aktif gagal.';
        } elseif ($cadangan !== null && $cadangan === $aktif) {
            $galat['cadangan'] = 'Penyedia cadangan harus berbeda dari penyedia aktif.';
        }

        return ['pengaturan' => ['provider_aktif' => $aktif, 'provider_cadangan' => $cadangan] + $pengaturan, 'galat' => $galat];
    }

    /** Awalan kunci API yang lazim per penyedia cloud (hanya untuk peringatan salah tempel). */
    public const AWALAN_KUNCI = ['claude' => 'sk-ant-', 'openai' => 'sk-', 'gemini' => 'AIza'];

    /**
     * Rapikan & periksa isian penyedia cloud. Kunci API kosong = tetap memakai kunci tersimpan (bila ada); `hapus_kunci`
     * membuang kunci tersimpan. Kunci tidak pernah dikembalikan dalam bentuk utuh selain di `data.kunci` untuk disimpan.
     *
     * @param  array{model?: mixed, kunci?: mixed, hapus_kunci?: mixed, base_url?: mixed, peran?: mixed}  $isian
     * @param  array<string, mixed>  $tersimpan  Konfigurasi penyedia ini yang tersimpan.
     * @return array{data: array{model: string, kunci: string|null, hapus_kunci: bool, base_url: string, peran: string}, galat: array<string, string>, peringatan: list<string>}
     */
    public static function cloud(string $penyedia, array $isian, array $tersimpan): array
    {
        $data = [
            'model' => trim((string) ($isian['model'] ?? '')),
            'kunci' => trim((string) ($isian['kunci'] ?? '')) ?: null,
            'hapus_kunci' => (bool) ($isian['hapus_kunci'] ?? false),
            'base_url' => rtrim(trim((string) ($isian['base_url'] ?? '')), '/'),
            'peran' => (string) ($isian['peran'] ?? 'tidak'),
        ];
        $galat = [];
        $peringatan = [];
        $dipakai = $data['peran'] !== 'tidak';

        if ($data['model'] === '' && $dipakai) {
            $galat['model'] = 'Nama model wajib diisi bila penyedia ini dipakai.';
        } elseif ($data['model'] !== '' && (mb_strlen($data['model']) > 100 || ! preg_match('/^[a-z0-9][a-z0-9._:\/-]*$/i', $data['model']))) {
            $galat['model'] = 'Nama model hanya huruf, angka, titik, titik dua, garis miring, dan tanda hubung.';
        }

        $adaKunci = $data['kunci'] !== null || (! $data['hapus_kunci'] && ($tersimpan['ada_kunci'] ?? false));
        if ($data['kunci'] !== null && (preg_match('/\s/', $data['kunci']) || mb_strlen($data['kunci']) < 20 || mb_strlen($data['kunci']) > 300)) {
            $galat['kunci'] = 'Kunci API 20–300 karakter tanpa spasi — salin utuh dari dasbor penyedia.';
        } elseif (! $adaKunci && $dipakai) {
            $galat['kunci'] = 'Kunci API wajib diisi bila penyedia ini dipakai.';
        } elseif ($data['kunci'] !== null && isset(self::AWALAN_KUNCI[$penyedia]) && ! str_starts_with($data['kunci'], self::AWALAN_KUNCI[$penyedia])) {
            $peringatan[] = 'Kunci '.PenyediaAi::nama($penyedia).' biasanya diawali "'.self::AWALAN_KUNCI[$penyedia].'" — pastikan tidak tertukar dengan penyedia lain.';
        }

        $url = parse_url($data['base_url']);
        if ($data['base_url'] !== '' && (! is_array($url) || ($url['scheme'] ?? '') !== 'https' || blank($url['host'] ?? null) || isset($url['query']))) {
            $galat['base_url'] = 'Alamat API harus https://…, atau kosongkan untuk memakai alamat resmi penyedia.';
        }
        if (! array_key_exists($data['peran'], self::PERAN)) {
            $galat['peran'] = 'Pilih peran penyedia.';
        }

        return ['data' => $data, 'galat' => $galat, 'peringatan' => $peringatan];
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
