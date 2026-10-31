<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Contracts\KlienLlm;
use App\Contracts\PengaturanAiRepository;
use App\Contracts\PromptAiRepository;
use App\Contracts\RiwayatSaranAiRepository;
use App\Support\Kegiatan\BatasAi;
use App\Support\Kegiatan\GagalPenyediaAi;
use App\Support\Kegiatan\GagalUsulanAi;
use App\Support\Kegiatan\KalimatKak;
use App\Support\Kegiatan\KemampuanModelAi;
use App\Support\Kegiatan\KlienLlmHttp;
use App\Support\Kegiatan\PermintaanSaranKalimat;
use App\Support\Kegiatan\PromptLlm;
use App\Support\Kegiatan\SambunganPenyediaAi;
use App\Support\Kegiatan\SaranJadwalAi;
use App\Support\Kegiatan\SaranKalimatAi;
use App\Support\Stub\StubData;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class KoneksiLlmTest extends TestCase
{
    private const KUNCI = 'kunci-rahasia-uji-0123456789';

    protected function setUp(): void
    {
        parent::setUp();
        // Percobaan ulang menunggu lewat Sleep: dipalsukan agar tes tidak benar-benar menunggu.
        Sleep::fake();
    }

    /** @param  array<string, mixed>  $ubah */
    private function atur(array $ubah): void
    {
        $data = array_diff_key(app(PengaturanAiRepository::class)->ambil(), ['diperbarui_pada' => 1]);
        app(PengaturanAiRepository::class)->simpan(array_replace_recursive($data, $ubah));
    }

    private function ollama(array $penyedia = []): void
    {
        $this->atur(['provider_aktif' => 'ollama', 'penyedia' => ['ollama' => $penyedia + ['base_url' => 'http://192.168.1.10:11434', 'model' => 'qwen2.5:7b', 'batas_waktu' => 45]]]);
    }

    private function kegiatan(int $id = 1): array
    {
        return app(KegiatanRepository::class)->temukan($id);
    }

    /** @param  list<array<string, mixed>>  $versi */
    private static function jawabanOllama(array $versi): array
    {
        return ['model' => 'qwen2.5:7b', 'message' => ['role' => 'assistant', 'content' => json_encode(['versi' => $versi])], 'done' => true];
    }

    private function gagal(\Closure $jalan): GagalPenyediaAi
    {
        try {
            $jalan();
        } catch (GagalPenyediaAi $e) {
            return $e;
        }
        $this->fail('Seharusnya gagal.');
    }

    public function test_tanpa_penyedia_aktif_dijawab_tiruan_tanpa_menghubungi_server(): void
    {
        Http::fake();
        $hasil = SaranKalimatAi::minta($this->kegiatan(), 'tujuan', StubData::currentUser());

        $this->assertSame(['tiruan', 3], [$hasil['penyedia'], count($hasil['versi'])]);
        Http::assertNothingSent();
    }

    public function test_ollama_menerima_prompt_kustom_dan_parameter_pengaturan_ai(): void
    {
        app(PromptAiRepository::class)->simpan(['gaya_bahasa' => null, 'bagian' => ['tujuan' => ['instruksi' => 'Tulis tujuan {nama_kegiatan} tahun {tahun} dengan kata kerja operasional.', 'gaya_bahasa' => 'Bahasa dinas yang ringkas.', 'aktif' => true]]]);
        $this->ollama();
        $this->atur(['parameter' => ['suhu' => 0.2, 'maks_token' => 512, 'jumlah_versi' => 2]]);
        Http::fake(['192.168.1.10:11434/api/chat' => Http::response(['message' => ['role' => 'assistant', 'content' => "```json\n".json_encode(['versi' => [
            ['judul' => 'Versi 1', 'ciri' => 'Spesifik', 'teks' => 'menyusun **dokumen** kurikulum madrasah.'],
            ['judul' => 'Versi 2', 'ciri' => 'Ringkas', 'teks' => 'memperbarui perangkat pembelajaran.'],
            ['judul' => 'Versi 3', 'ciri' => 'Lebih', 'teks' => 'versi ketiga dibuang.'],
        ]])."\n```"]])]);
        $sisa = BatasAi::sisa(StubData::currentUser());

        $hasil = SaranKalimatAi::minta($this->kegiatan(), 'tujuan', StubData::currentUser());

        $this->assertSame('ollama', $hasil['penyedia']);
        $this->assertSame(['menyusun dokumen kurikulum madrasah.', 'memperbarui perangkat pembelajaran.'], array_column($hasil['versi'], 'teks'), 'Markdown dibersihkan, jumlah versi mengikuti Pengaturan AI.');
        $this->assertSame($sisa - 1, BatasAi::sisa(StubData::currentUser()));

        $prompt = app(RiwayatSaranAiRepository::class)->semua()[0]['prompt'];
        $nama = KalimatKak::tanpaAwalanKegiatan($this->kegiatan()['nama_kegiatan']);
        $this->assertStringContainsString("Tugas: Tulis tujuan {$nama} tahun 2026 dengan kata kerja operasional.\nGaya bahasa: Bahasa dinas yang ringkas.", $prompt);
        Http::assertSentCount(1);
        Http::assertSent(function (Request $r) use ($prompt, $nama) {
            $sistem = $r['messages'][0]['content'];

            return $r->url() === 'http://192.168.1.10:11434/api/chat' && $r->method() === 'POST'
                && [$r['model'], $r['stream'], $r['format']] === ['qwen2.5:7b', false, 'json']
                && $r['options'] === ['temperature' => 0.2, 'num_predict' => 512 * 2 + PromptLlm::TOKEN_KERANGKA]
                && [$r['messages'][0]['role'], $r['messages'][1]['role']] === ['system', 'user']
                && str_contains($sistem, 'Susun 2 versi') && str_contains($sistem, 'Teks dicetak sesudah awalan "Kegiatan … bertujuan ".') && ! str_contains($sistem, $nama)
                && $r['messages'][1]['content'] === $prompt;
        });
    }

    public function test_api_cloud_claude_openai_dan_gemini(): void
    {
        $kunci = Crypt::encryptString(self::KUNCI);
        $versi = fn (string $teks) => ['versi' => [['judul' => 'Versi 1', 'ciri' => 'Formal', 'teks' => $teks]]];
        Http::fake([
            'api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => 'Berikut sarannya: '.json_encode($versi('meningkatkan mutu dari Claude.')).' Semoga membantu.']], 'stop_reason' => 'end_turn']),
            'gateway.madrasah.sch.id/*' => Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => json_encode($versi('meningkatkan mutu dari OpenAI.'))]]]]),
            'generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => '{"versi":[{"judul":"Versi 1","ciri":"Formal",'], ['text' => '"teks":"meningkatkan mutu dari Gemini."}]}']]]]]]),
        ]);

        $minta = function (string $penyedia, array $konfigurasi) use ($kunci) {
            $this->atur(['provider_aktif' => $penyedia, 'penyedia' => [$penyedia => $konfigurasi + ['ada_kunci' => true, 'kunci_akhir' => '6789', 'kunci_terenkripsi' => $kunci]]]);
            $hasil = SaranKalimatAi::minta($this->kegiatan(), 'tujuan', StubData::currentUser());

            return [$hasil['penyedia'], $hasil['versi'][0]['teks']];
        };

        $this->assertSame(['claude', 'meningkatkan mutu dari Claude.'], $minta('claude', ['model' => 'claude-sonnet-5-5']));
        // Claude generasi 5: tanpa temperature (ditolak model), upaya berpikir rendah + cadangan token berpikir.
        Http::assertSent(fn (Request $r) => $r->url() === 'https://api.anthropic.com/v1/messages'
            && $r->header('x-api-key') === [self::KUNCI] && $r->header('anthropic-version') === ['2023-06-01']
            && $r['model'] === 'claude-sonnet-5-5' && $r['max_tokens'] === 1024 * 3 + PromptLlm::TOKEN_KERANGKA + KlienLlmHttp::CADANGAN_BERPIKIR
            && ! isset($r['temperature']) && $r['output_config'] === ['effort' => 'low']
            && str_contains($r['system'], 'JSON') && $r['messages'][0]['role'] === 'user');

        $this->assertSame(['openai', 'meningkatkan mutu dari OpenAI.'], $minta('openai', ['model' => 'gpt-uji', 'base_url' => 'https://gateway.madrasah.sch.id/v1']));
        Http::assertSent(fn (Request $r) => $r->url() === 'https://gateway.madrasah.sch.id/v1/chat/completions'
            && $r->header('Authorization') === ['Bearer '.self::KUNCI] && $r['response_format'] === ['type' => 'json_object']
            && $r['max_completion_tokens'] === 1024 * 3 + PromptLlm::TOKEN_KERANGKA && $r['temperature'] === 0.4 && ! isset($r['reasoning_effort'])
            && $r['messages'][0]['role'] === 'system');

        $this->assertSame(['gemini', 'meningkatkan mutu dari Gemini.'], $minta('gemini', ['model' => 'models/gemini-uji']));
        Http::assertSent(fn (Request $r) => $r->url() === 'https://generativelanguage.googleapis.com/v1beta/models/gemini-uji:generateContent'
            && $r->header('x-goog-api-key') === [self::KUNCI] && $r['generationConfig']['responseMimeType'] === 'application/json'
            && str_contains($r['systemInstruction']['parts'][0]['text'], 'JSON'));

        // Contoh riwayat tiruan tanpa penyedia (AI tiruan) tidak dihitung.
        $penyedia = array_values(array_filter(array_column(app(RiwayatSaranAiRepository::class)->semua(), 'penyedia')));
        sort($penyedia);
        $this->assertSame(['claude', 'gemini', 'openai'], $penyedia, 'Riwayat mencatat penyedia yang menjawab.');
    }

    public function test_kegagalan_penyedia_dipetakan_tanpa_mencatat_kunci(): void
    {
        Log::spy();
        $this->atur(['penyedia' => ['ollama' => ['base_url' => 'http://192.168.1.10:11434', 'model' => 'qwen2.5:7b', 'batas_waktu' => 45], 'claude' => ['model' => 'claude-sonnet-5-5', 'ada_kunci' => true, 'kunci_terenkripsi' => Crypt::encryptString(self::KUNCI)]]]);
        $sibuk = ['error' => 'server sibuk'];
        $batasLaju = ['type' => 'error', 'error' => ['type' => 'rate_limit_error', 'message' => 'Number of requests has exceeded your rate limit']];
        Http::fake([
            '192.168.1.10:11434/*' => Http::sequence()
                ->push(['error' => "model 'qwen2.5:7b' not found"], 404)
                ->push($sibuk, 503)->push($sibuk, 503)->push($sibuk, 503)
                ->pushFailedConnection()->pushFailedConnection()->pushFailedConnection()
                ->pushFailedConnection('cURL error 28: Operation timed out after 45001 milliseconds')
                ->push(['message' => ['content' => 'Maaf, saya tidak bisa membantu.']])
                ->push(['message' => ['content' => ''], 'done_reason' => 'length']),
            'api.anthropic.com/*' => Http::sequence()
                ->push(['type' => 'error', 'error' => ['type' => 'authentication_error', 'message' => 'invalid x-api-key '.self::KUNCI]], 401)
                ->push($batasLaju, 429, ['retry-after' => '2'])->push($batasLaju, 429, ['retry-after' => '2'])->push($batasLaju, 429, ['retry-after' => '2'])
                ->push(['type' => 'error', 'error' => ['type' => 'rate_limit_error']], 429, ['retry-after' => '120'])
                ->push(['type' => 'error', 'error' => ['type' => 'invalid_request_error', 'message' => 'Your credit balance is too low to access the Anthropic API.']], 400)
                ->push(['type' => 'error', 'error' => ['type' => 'invalid_request_error', 'message' => 'messages.0.content: tidak boleh kosong']], 400, ['request-id' => 'req_uji123'])
                ->push(['type' => 'error', 'error' => ['type' => 'overloaded_error', 'message' => 'Overloaded']], 529)->push(['type' => 'error', 'error' => ['type' => 'overloaded_error']], 529)->push(['type' => 'error', 'error' => ['type' => 'api_error']], 500)
                ->push(['id' => 'msg_1', 'content' => [['type' => 'thinking', 'thinking' => '']], 'stop_reason' => 'refusal', 'stop_details' => ['type' => 'refusal', 'category' => 'cyber']]),
        ]);
        $klien = app(KlienLlm::class);
        $tanya = fn (string $penyedia) => $this->gagal(fn () => PromptLlm::versi($klien->tanya($penyedia, 'Sistem JSON', 'Pesan', ['suhu' => 0.4, 'maks_token' => 512]), $penyedia))->jenis;

        // Galat sementara (503, koneksi putus) dicoba ulang dua kali; waktu tunggu yang sudah habis tidak.
        $this->assertSame(['model_tidak_ada', 'penyedia_sibuk', 'tidak_terhubung', 'waktu_habis', 'jawaban_rusak', 'jawaban_rusak'], array_map(fn () => $tanya('ollama'), range(1, 6)));
        // 429 dicoba ulang sesuai Retry-After; Retry-After terlalu lama, saldo habis, & galat permintaan tidak; model menolak → permintaan_ditolak.
        $this->assertSame(['kunci_ditolak', 'kuota_penyedia', 'kuota_penyedia', 'kuota_penyedia', 'permintaan_ditolak', 'penyedia_sibuk', 'permintaan_ditolak'], array_map(fn () => $tanya('claude'), range(1, 7)));
        Http::assertSentCount(10 + 11);
        Sleep::assertSleptTimes(2 + 2 + 2 + 2);

        // Konfigurasi belum lengkap: gagal tanpa menghubungi penyedia.
        $this->atur(['penyedia' => ['ollama' => ['model' => ''], 'openai' => ['model' => 'gpt-uji', 'ada_kunci' => false, 'kunci_terenkripsi' => null], 'gemini' => ['model' => 'gemini-uji', 'ada_kunci' => true, 'kunci_terenkripsi' => 'bukan-enkripsi']]]);
        $this->assertSame(['model_tidak_ada', 'kunci_ditolak', 'kunci_ditolak', 'tidak_terhubung'], [$tanya('ollama'), $tanya('openai'), $tanya('gemini'), $tanya('mistral')]);
        Http::assertSentCount(21);

        // Tiap kegagalan sampai di server/konfigurasi dicatat (jawaban tak terbaca dari model tidak), tanpa kunci maupun pesan penyedia.
        Log::shouldHaveReceived('warning')->withArgs(fn (string $pesan, array $konteks) => ! str_contains(json_encode($konteks), self::KUNCI))->times(16);
        Log::shouldHaveReceived('warning')->withArgs(fn (string $pesan, array $konteks) => $konteks === ['penyedia' => 'claude', 'jenis' => 'kunci_ditolak', 'rincian' => 'HTTP 401 authentication_error'])->once();
        Log::shouldHaveReceived('warning')->withArgs(fn (string $pesan, array $konteks) => $konteks === ['penyedia' => 'claude', 'jenis' => 'permintaan_ditolak', 'rincian' => 'HTTP 400 invalid_request_error', 'permintaan' => 'req_uji123'])->once();
        Log::shouldHaveReceived('warning')->withArgs(fn (string $pesan, array $konteks) => $konteks === ['penyedia' => 'claude', 'jenis' => 'permintaan_ditolak', 'rincian' => 'refusal cyber'])->once();
        Log::shouldHaveReceived('warning')->withArgs(fn (string $pesan, array $konteks) => $konteks === ['penyedia' => 'ollama', 'jenis' => 'jawaban_rusak', 'rincian' => 'batas token habis sebelum ada teks jawaban'])->once();
    }

    public function test_bentuk_permintaan_mengikuti_kemampuan_model_dan_parameter_ditolak_dibuang(): void
    {
        $kunci = Crypt::encryptString(self::KUNCI);
        $jawabanClaude = ['content' => [['type' => 'thinking', 'thinking' => '', 'signature' => 'x'], ['type' => 'text', 'text' => '{"status":"ok"}']], 'stop_reason' => 'end_turn'];
        $jawabanOpenai = ['choices' => [['message' => ['content' => '{"status":"ok"}'], 'finish_reason' => 'stop']]];
        Http::fake([
            'api.anthropic.com/*' => Http::sequence()
                ->push(['type' => 'error', 'error' => ['type' => 'overloaded_error']], 529)->push($jawabanClaude)
                ->push($jawabanClaude)->push($jawabanClaude),
            'api.openai.com/*' => Http::sequence()
                ->push(['error' => ['message' => "Unsupported value: 'temperature' does not support 0.4 with this model. Only the default (1) value is supported.", 'type' => 'invalid_request_error', 'param' => 'temperature', 'code' => 'unsupported_value']], 400)
                ->push($jawabanOpenai)->push($jawabanOpenai)
                ->push(['choices' => [['message' => ['content' => null, 'refusal' => 'Maaf.'], 'finish_reason' => 'stop']]]),
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push(['candidates' => [['content' => ['parts' => [['text' => 'pikiran', 'thought' => true], ['text' => '{"status":"ok"}']]], 'finishReason' => 'STOP']]])
                ->push(['promptFeedback' => ['blockReason' => 'SAFETY']]),
        ]);
        $klien = app(KlienLlm::class);
        $tanya = function (string $penyedia, string $model) use ($klien, $kunci) {
            $this->atur(['penyedia' => [$penyedia => ['model' => $model, 'ada_kunci' => true, 'kunci_akhir' => '6789', 'kunci_terenkripsi' => $kunci, 'base_url' => '']]]);

            return $klien->tanya($penyedia, 'Sistem', 'Pesan', ['suhu' => 0.4, 'maks_token' => 300]);
        };
        $dikirim = fn (string $host) => Http::recorded(fn (Request $r) => str_contains($r->url(), $host))->map(fn (array $p) => $p[0]->data())->values()->all();

        // Claude: generasi 5 pulih dari 529 (sekali coba ulang), blok berpikir dilewati; Sonnet 4.6 masih bersuhu; Opus 4.8 tanpa suhu & tanpa upaya.
        $this->assertSame(['{"status":"ok"}', '{"status":"ok"}', '{"status":"ok"}'], [$tanya('claude', 'claude-opus-5-5'), $tanya('claude', 'claude-sonnet-4-6'), $tanya('claude', 'claude-opus-4-8')]);
        $claude = $dikirim('anthropic');
        $this->assertCount(4, $claude);
        $this->assertSame([300 + KlienLlmHttp::CADANGAN_BERPIKIR, ['effort' => 'low'], false], [$claude[1]['max_tokens'], $claude[1]['output_config'], isset($claude[1]['temperature'])]);
        $this->assertSame([300, 0.4, false], [$claude[2]['max_tokens'], $claude[2]['temperature'], isset($claude[2]['output_config'])]);
        $this->assertSame([300, false, false], [$claude[3]['max_tokens'], isset($claude[3]['temperature']), isset($claude[3]['output_config'])]);

        // OpenAI: suhu yang ditolak dibuang lalu dikirim ulang; model penalaran tanpa suhu dengan reasoning_effort; penolakan → permintaan_ditolak.
        $this->assertSame(['{"status":"ok"}', '{"status":"ok"}'], [$tanya('openai', 'gpt-4.1'), $tanya('openai', 'o4-mini')]);
        $this->assertSame('permintaan_ditolak', $this->gagal(fn () => $tanya('openai', 'gpt-4.1'))->jenis);
        $openai = $dikirim('openai');
        $this->assertSame([0.4, false], [$openai[0]['temperature'], isset($openai[1]['temperature'])], 'Dikirim ulang tanpa temperature.');
        $this->assertSame([300 + KlienLlmHttp::CADANGAN_BERPIKIR, 'low', false], [$openai[2]['max_completion_tokens'], $openai[2]['reasoning_effort'], isset($openai[2]['temperature'])]);

        // Gemini 2.5: cadangan token berpikir, bagian "thought" dilewati; diblokir filter → permintaan_ditolak.
        $this->assertSame('{"status":"ok"}', $tanya('gemini', 'gemini-2.5-flash'));
        $this->assertSame('permintaan_ditolak', $this->gagal(fn () => $tanya('gemini', 'gemini-2.5-flash'))->jenis);
        $this->assertSame(300 + KlienLlmHttp::CADANGAN_BERPIKIR, $dikirim('googleapis')[0]['generationConfig']['maxOutputTokens']);
        Sleep::assertSleptTimes(1);
    }

    public function test_kemampuan_model(): void
    {
        $suhu = fn (string $p, string $m) => KemampuanModelAi::terimaSuhu($p, $m);
        $pikir = fn (string $p, string $m) => KemampuanModelAi::selaluBerpikir($p, $m);
        foreach (['claude-sonnet-4-6', 'claude-opus-4-6', 'claude-haiku-4-5', 'claude-haiku-4-5-20251001', 'claude-sonnet-4-20250514', 'claude-opus-4-1-20250805', 'claude-3-haiku-20240307'] as $m) {
            $this->assertTrue($suhu('claude', $m), $m);
            $this->assertFalse($pikir('claude', $m), $m);
        }
        foreach (['claude-opus-5-5', 'claude-sonnet-5-5', 'claude-haiku-5-5', 'claude-sonnet-5', 'claude-fable-5-1', 'claude-opus-4-7', 'claude-opus-4-8', 'model-tak-dikenal'] as $m) {
            $this->assertFalse($suhu('claude', $m), $m);
        }
        $this->assertSame([true, true, true, true, false, false], array_map(fn (string $m) => $pikir('claude', $m), ['claude-opus-5-5', 'claude-sonnet-5', 'claude-haiku-5-5', 'claude-fable-5-1', 'claude-opus-4-8', 'claude-3-haiku-20240307']));
        $this->assertSame([[true, false], [false, true], [false, true], [true, false]], array_map(fn (string $m) => [$suhu('openai', $m), $pikir('openai', $m)], ['gpt-4.1', 'o4-mini', 'gpt-5-mini', 'gpt-4o']));
        $this->assertSame([true, true, false], [$pikir('gemini', 'gemini-2.5-pro'), $pikir('gemini', 'models/gemini-3-flash'), $pikir('gemini', 'gemini-2.0-flash')]);
        $this->assertSame([true, false], [$suhu('ollama', 'qwen2.5:7b'), $pikir('ollama', 'qwen2.5:7b')]);
    }

    public function test_sambungan_tidak_membocorkan_kunci_dan_batas_permintaan_web(): void
    {
        $this->atur(['penyedia' => ['claude' => ['model' => 'claude-opus-5-5', 'ada_kunci' => true, 'kunci_terenkripsi' => Crypt::encryptString(self::KUNCI), 'batas_waktu' => 500]]]);
        $sambungan = SambunganPenyediaAi::untuk('claude');

        $this->assertStringNotContainsString(self::KUNCI, print_r($sambungan, true));
        $this->assertStringNotContainsString(self::KUNCI, var_export((array) $sambungan->__debugInfo(), true));
        $this->assertSame(['https://api.anthropic.com/v1', 300], [$sambungan->alamat, $sambungan->batas], 'Batas waktu dijepit AturanPenyediaAi::BATAS_WAKTU.');
        // Di konsol/antrean tidak ada batas permintaan web; batas penyedia berlaku penuh.
        $this->assertNull(SambunganPenyediaAi::sisaPermintaan());
        $this->assertSame(300.0, $sambungan->batasEfektif());
    }

    public function test_penyedia_aktif_gagal_cadangan_mengambil_alih_lalu_keduanya_gagal(): void
    {
        $this->ollama();
        $this->atur(['provider_cadangan' => 'claude', 'penyedia' => ['claude' => ['model' => 'claude-sonnet-5-5', 'ada_kunci' => true, 'kunci_terenkripsi' => Crypt::encryptString(self::KUNCI)]]]);
        Http::fake([
            '192.168.1.10:11434/*' => Http::sequence()->push(['error' => 'model not found'], 404)->pushFailedConnection()->pushFailedConnection()->pushFailedConnection(),
            'api.anthropic.com/*' => Http::sequence()
                ->push(['content' => [['type' => 'text', 'text' => json_encode(['versi' => [['judul' => 'Versi 1', 'ciri' => 'Formal', 'teks' => 'meningkatkan mutu layanan madrasah.']]])]]])
                ->push(['error' => ['type' => 'authentication_error']], 401),
        ]);
        $pengguna = StubData::currentUser();

        $hasil = SaranKalimatAi::minta($this->kegiatan(), 'tujuan', $pengguna);
        $this->assertSame(['claude', 'Disusun penyedia cadangan (Claude (Anthropic)) karena penyedia aktif gagal: model tidak ditemukan.'], [$hasil['penyedia'], $hasil['catatan']]);

        $sisa = BatasAi::sisa($pengguna);
        try {
            SaranKalimatAi::minta($this->kegiatan(), 'tujuan', $pengguna);
            $this->fail('Kedua penyedia gagal.');
        } catch (GagalUsulanAi $e) {
            $this->assertSame('Server AI tidak bisa dihubungi. Coba lagi beberapa saat lagi. Penyedia cadangan juga gagal.', $e->getMessage());
            $this->assertSame(['tidak_terhubung', true], [$e->getPrevious()->jenis, $e->getPrevious()->cadanganGagal]);
        }
        $this->assertSame($sisa, BatasAi::sisa($pengguna), 'Permintaan yang gagal tidak dihitung.');
    }

    public function test_usulan_jadwal_lewat_llm_dengan_prompt_jadwal(): void
    {
        app(PromptAiRepository::class)->simpan(['gaya_bahasa' => null, 'bagian' => ['jadwal' => ['instruksi' => 'Usulkan jadwal {nama_kegiatan} yang ringkas per bulan.', 'aktif' => true]]]);
        $this->ollama();
        Http::fake(['192.168.1.10:11434/*' => Http::response(self::jawabanOllama([
            ['judul' => 'Versi 1', 'ciri' => 'Ringkas', 'keterangan' => 'Tiga tahap.', 'baris' => [
                ['uraian' => 'Rapat persiapan', 'mulai' => 'Juni', 'sampai' => null],
                ['uraian' => 'Pelaksanaan workshop', 'mulai' => 7, 'sampai' => '8'],
                ['uraian' => 'Penyusunan laporan', 'mulai' => 'agu'],
                'bukan baris',
            ]],
            'bukan versi',
        ]))]);

        $hasil = SaranJadwalAi::minta($this->kegiatan(), StubData::currentUser(), 'umum', 'tiga tahap saja');

        $this->assertSame('ollama', $hasil['penyedia']);
        $this->assertSame([
            ['uraian' => 'Rapat persiapan', 'mulai' => 6, 'sampai' => null],
            ['uraian' => 'Pelaksanaan workshop', 'mulai' => 7, 'sampai' => 8],
            ['uraian' => 'Penyusunan laporan', 'mulai' => 8, 'sampai' => null],
        ], $hasil['versi'][0]['baris']);

        $riwayat = collect(app(RiwayatSaranAiRepository::class)->semua())->firstWhere('id', $hasil['permintaan']);
        $nama = KalimatKak::tanpaAwalanKegiatan($this->kegiatan()['nama_kegiatan']);
        $this->assertStringContainsString("Tugas: Usulkan jadwal {$nama} yang ringkas per bulan.", $riwayat['prompt']);
        $this->assertStringContainsString("\nAcuan bulan: ", $riwayat['prompt']);
        $this->assertSame('ollama', $riwayat['penyedia']);
        Http::assertSent(fn (Request $r) => $r['messages'][1]['content'] === $riwayat['prompt'] && str_contains($r['messages'][0]['content'], '"baris"'));
    }

    public function test_pembacaan_jawaban_dan_bulan(): void
    {
        $this->assertSame([['teks' => 'a']], PromptLlm::versi('[{"teks":"a"}, 3]', 'ollama'), 'Daftar langsung.');
        $this->assertSame([['teks' => 'b']], PromptLlm::versi('{"versions":[{"teks":"b"}]}', 'ollama'), 'Daftar pertama di objek.');
        $this->assertSame([['teks' => 'c']], PromptLlm::versi("Jawaban:\n```\n{\"versi\":[{\"teks\":\"c\"}]}\n```", 'ollama'));
        foreach (['', 'bukan json', '{"versi":[]}', '{"versi":"teks"}', '{"versi":[1,2]}'] as $rusak) {
            $this->assertSame('jawaban_rusak', $this->gagal(fn () => PromptLlm::versi($rusak, 'claude'))->jenis, $rusak);
        }

        $this->assertSame([1, 8, 12, 3, null, null, null, null], array_map(PromptLlm::bulan(...), ['Januari 2026', 'Agu', '12', 3, 0, 'Agt', 'xy', true]));
        $this->assertSame(['', '5', ''], array_map(PromptLlm::teks(...), [['a'], 5, true]));
        $this->assertSame('bulan pelaksanaan belum diisi', PromptLlm::acuan(null, 2026));

        $sampul = PromptLlm::sistemKalimat(new PermintaanSaranKalimat(1, 'nama_cover', '', '', [], '', 'Rapat Kerja'), 3);
        $this->assertStringContainsString('Format teks: satu baris huruf kapital', $sampul);
        $this->assertStringNotContainsString('awalan', $sampul);
        $this->assertStringContainsString('perbaikan "Teks yang diperbaiki"', PromptLlm::sistemKalimat(new PermintaanSaranKalimat(1, 'sasaran', 'teks lama', 'lebih ringkas', [], '', 'Rapat Kerja'), 2));
    }
}
