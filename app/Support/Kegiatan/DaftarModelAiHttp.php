<?php

namespace App\Support\Kegiatan;

use App\Contracts\DaftarModelAi;
use App\Support\Pengaturan\AturanPenyediaAi;
use App\Support\Pengaturan\PenyediaAi;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use RuntimeException;

/**
 * Daftar model dari server penyedia (PRD F24 "Model"), lewat SambunganPenyediaAi dengan konfigurasi tersimpan:
 * - Ollama `GET {alamat}/api/tags` — model yang sudah diunduh di server sekolah (ukuran, parameter, kuantisasi; model
 *   embedding dilewati);
 * - Claude `GET {alamat}/models` (berhalaman `after_id`) — model yang boleh dipakai akun (nama tampil, konteks);
 * - OpenAI `GET {alamat}/models` — hanya model obrolan (gpt-*, o*, chatgpt-*), terbaru dulu;
 * - Gemini `GET {alamat}/models` (berhalaman `pageToken`) — yang mendukung generateContent.
 * Nama model yang tidak berpola AturanPenyediaAi::POLA_MODEL dibuang (aman dipakai di atribut & formulir), paling banyak
 * MAKS_MODEL. Konfigurasi belum lengkap atau penyedia gagal → RuntimeException berpesan untuk admin (KegagalanAi).
 * Tidak ada model yang ditandai "disarankan": pilihan model (dan biayanya) keputusan admin.
 */
final class DaftarModelAiHttp implements DaftarModelAi
{
    public const MAKS_MODEL = 60;

    public const MAKS_HALAMAN = 5;

    /** Batas waktu memuat daftar (detik); daftar model tidak perlu menunggu selama menyusun kalimat. */
    public const BATAS_DETIK = 20;

    /** Ciri keluarga model Claude untuk keterangan (kecepatan & biaya relatif). */
    public const CIRI_CLAUDE = [
        'haiku' => 'paling cepat & hemat',
        'sonnet' => 'seimbang antara mutu & biaya',
        'opus' => 'lebih cermat; biaya lebih tinggi',
        'fable' => 'paling mampu; biaya tertinggi',
    ];

    /** Model OpenAI yang bukan model obrolan. */
    private const BUKAN_OBROLAN_OPENAI = '/(?:embedding|whisper|tts|dall-e|audio|realtime|transcribe|image|search|moderation|instruct)/i';

    public function daftar(string $penyedia, array $konfigurasi): array
    {
        $info = PenyediaAi::DAFTAR[$penyedia] ?? throw new RuntimeException('Penyedia AI tidak dikenal.');
        if ($penyedia === 'ollama' && blank($konfigurasi['base_url'] ?? null)) {
            throw new RuntimeException('Alamat server sekolah belum diisi — atur di tab Penyedia.');
        }
        if ($info['butuh_kunci'] && ! ($konfigurasi['ada_kunci'] ?? false)) {
            throw new RuntimeException("Kunci API {$info['nama']} belum diisi — atur di tab Penyedia.");
        }

        try {
            $sambungan = SambunganPenyediaAi::untuk($penyedia, $konfigurasi, perluModel: false);
            $model = match ($penyedia) {
                'ollama' => $this->ollama($sambungan),
                'claude' => $this->claude($sambungan),
                'openai' => $this->openai($sambungan),
                'gemini' => $this->gemini($sambungan),
            };
        } catch (GagalPenyediaAi $e) {
            throw new RuntimeException("Daftar model {$info['nama']} tidak bisa dimuat: ".lcfirst(KegagalanAi::judul($e->jenis)).'. '.KegagalanAi::saranAdmin($e->jenis), 0, $e);
        }

        $sah = array_filter($model, fn (array $m) => mb_strlen($m['nama']) <= 100 && preg_match(AturanPenyediaAi::POLA_MODEL, $m['nama']));

        return array_slice(array_values(collect($sah)->unique('nama')->all()), 0, self::MAKS_MODEL);
    }

    /** @return list<array{nama: string, ukuran: string|null, keterangan: string, disarankan: bool}> */
    private function ollama(SambunganPenyediaAi $s): array
    {
        $model = [];
        foreach ((array) $this->ambil($s, '/api/tags')->json('models', []) as $m) {
            $nama = is_array($m) ? ($m['name'] ?? $m['model'] ?? null) : null;
            $detail = is_array($m['details'] ?? null) ? $m['details'] : [];
            if (! is_string($nama) || str_contains(mb_strtolower($nama.' '.($detail['family'] ?? '')), 'embed') || str_contains((string) ($detail['family'] ?? ''), 'bert')) {
                continue;
            }
            $ciri = array_filter([
                is_string($detail['parameter_size'] ?? null) ? $detail['parameter_size'].' parameter' : null,
                is_string($detail['quantization_level'] ?? null) ? $detail['quantization_level'] : null,
                match (KemampuanModelAi::berpikirOllama($nama)) {
                    false => 'model berpikir — mode berpikir dimatikan otomatis',
                    'low' => 'selalu berpikir — jawaban lebih lambat',
                    default => null,
                },
            ]);
            $model[] = [
                'nama' => $nama,
                'ukuran' => is_numeric($m['size'] ?? null) ? str_replace('.', ',', number_format($m['size'] / 1e9, 1)).' GB' : null,
                'keterangan' => $ciri === [] ? 'Terpasang di server sekolah.' : 'Terpasang di server sekolah · '.implode(' · ', $ciri).'.',
                'disarankan' => false,
            ];
        }

        return $model;
    }

    /** @return list<array{nama: string, ukuran: null, keterangan: string, disarankan: bool}> */
    private function claude(SambunganPenyediaAi $s): array
    {
        $model = [];
        $setelah = null;
        for ($halaman = 0; $halaman < self::MAKS_HALAMAN; $halaman++) {
            $jawaban = $this->ambil($s, '/models', array_filter(['limit' => 100, 'after_id' => $setelah]));
            foreach ((array) $jawaban->json('data', []) as $m) {
                if (! is_array($m) || ! is_string($m['id'] ?? null)) {
                    continue;
                }
                $keluarga = preg_match('/^claude-([a-z]+)-/', $m['id'], $cocok) ? $cocok[1] : null;
                $model[] = ['nama' => $m['id'], 'ukuran' => null, 'disarankan' => false, 'keterangan' => implode(' · ', array_filter([
                    is_string($m['display_name'] ?? null) ? $m['display_name'] : null,
                    self::CIRI_CLAUDE[$keluarga] ?? null,
                    is_numeric($m['max_input_tokens'] ?? null) ? 'konteks '.self::token((int) $m['max_input_tokens']).' token' : null,
                ])).'.'];
            }
            $setelah = $jawaban->json('last_id');
            if ($jawaban->json('has_more') !== true || ! is_string($setelah)) {
                break;
            }
        }

        return $model;
    }

    /** @return list<array{nama: string, ukuran: null, keterangan: string, disarankan: bool}> */
    private function openai(SambunganPenyediaAi $s): array
    {
        $data = collect((array) $this->ambil($s, '/models')->json('data', []))
            ->filter(fn (mixed $m) => is_array($m) && is_string($m['id'] ?? null) && preg_match('/^(?:gpt-|o\d|chatgpt-)/i', $m['id']) && ! preg_match(self::BUKAN_OBROLAN_OPENAI, $m['id']))
            ->sortByDesc(fn (array $m) => (int) ($m['created'] ?? 0));

        return $data->map(fn (array $m) => ['nama' => $m['id'], 'ukuran' => null, 'disarankan' => false, 'keterangan' => KemampuanModelAi::selaluBerpikir('openai', $m['id'])
            ? 'Model penalaran — lebih lambat; pengaturan Kreativitas tidak berlaku.'
            : 'Model obrolan OpenAI.'])->values()->all();
    }

    /** @return list<array{nama: string, ukuran: null, keterangan: string, disarankan: bool}> */
    private function gemini(SambunganPenyediaAi $s): array
    {
        $model = [];
        $token = null;
        for ($halaman = 0; $halaman < self::MAKS_HALAMAN; $halaman++) {
            $jawaban = $this->ambil($s, '/models', array_filter(['pageSize' => 100, 'pageToken' => $token]));
            foreach ((array) $jawaban->json('models', []) as $m) {
                if (! is_array($m) || ! is_string($m['name'] ?? null) || ! in_array('generateContent', (array) ($m['supportedGenerationMethods'] ?? []), true)) {
                    continue;
                }
                $nama = preg_replace('#^models/#', '', $m['name']) ?? '';
                if (! str_starts_with($nama, 'gemini')) {
                    continue;
                }
                $model[] = ['nama' => $nama, 'ukuran' => null, 'disarankan' => false, 'keterangan' => implode(' · ', array_filter([
                    is_string($m['displayName'] ?? null) ? $m['displayName'] : null,
                    is_numeric($m['inputTokenLimit'] ?? null) ? 'konteks '.self::token((int) $m['inputTokenLimit']).' token' : null,
                ])).'.'];
            }
            $token = $jawaban->json('nextPageToken');
            if (! is_string($token) || $token === '') {
                break;
            }
        }

        return $model;
    }

    /**
     * @param  array<string, mixed>  $query
     *
     * @throws GagalPenyediaAi
     */
    private function ambil(SambunganPenyediaAi $s, string $jalur, array $query = []): Response
    {
        try {
            $jawaban = $s->http(min(self::BATAS_DETIK, $s->batasEfektif()))->get($s->alamat.$jalur, $query);
        } catch (ConnectionException $e) {
            throw SambunganPenyediaAi::gagal(preg_match('/timed?\s?out|cURL error 28/i', $e->getMessage()) ? 'waktu_habis' : 'tidak_terhubung', $s->penyedia, 'daftar model: koneksi gagal');
        }
        if ($jawaban->failed()) {
            throw SambunganPenyediaAi::gagal(KlienLlmHttp::jenisGagal($jawaban), $s->penyedia, 'daftar model: HTTP '.$jawaban->status());
        }

        return $jawaban;
    }

    /** 1000000 → "1 jt", 200000 → "200 rb". */
    private static function token(int $jumlah): string
    {
        return $jumlah >= 1_000_000 ? str_replace('.', ',', rtrim(rtrim(number_format($jumlah / 1_000_000, 1), '0'), '.')).' jt' : intdiv($jumlah, 1000).' rb';
    }
}
