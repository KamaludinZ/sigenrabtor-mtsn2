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
use App\Support\Kegiatan\PermintaanSaranKalimat;
use App\Support\Kegiatan\PromptLlm;
use App\Support\Kegiatan\SaranJadwalAi;
use App\Support\Kegiatan\SaranKalimatAi;
use App\Support\Stub\StubData;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class KoneksiLlmTest extends TestCase
{
    private const KUNCI = 'kunci-rahasia-uji-0123456789';

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
        Http::assertSent(fn (Request $r) => $r->url() === 'https://api.anthropic.com/v1/messages'
            && $r->header('x-api-key') === [self::KUNCI] && $r->header('anthropic-version') === ['2023-06-01']
            && $r['model'] === 'claude-sonnet-5-5' && $r['max_tokens'] === 1024 * 3 + PromptLlm::TOKEN_KERANGKA && $r['temperature'] === 0.4
            && str_contains($r['system'], 'JSON') && $r['messages'][0]['role'] === 'user');

        $this->assertSame(['openai', 'meningkatkan mutu dari OpenAI.'], $minta('openai', ['model' => 'gpt-uji', 'base_url' => 'https://gateway.madrasah.sch.id/v1']));
        Http::assertSent(fn (Request $r) => $r->url() === 'https://gateway.madrasah.sch.id/v1/chat/completions'
            && $r->header('Authorization') === ['Bearer '.self::KUNCI] && $r['response_format'] === ['type' => 'json_object']
            && $r['max_completion_tokens'] === 1024 * 3 + PromptLlm::TOKEN_KERANGKA && $r['messages'][0]['role'] === 'system');

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
        Http::fake([
            '192.168.1.10:11434/*' => Http::sequence()
                ->push(['error' => "model 'qwen2.5:7b' not found"], 404)
                ->push(['error' => 'server sibuk'], 503)
                ->pushFailedConnection()
                ->pushFailedConnection('cURL error 28: Operation timed out after 45001 milliseconds')
                ->push(['message' => ['content' => 'Maaf, saya tidak bisa membantu.']])
                ->push(['message' => ['content' => '']]),
            'api.anthropic.com/*' => Http::sequence()
                ->push(['type' => 'error', 'error' => ['type' => 'authentication_error', 'message' => 'invalid x-api-key '.self::KUNCI]], 401)
                ->push(['type' => 'error', 'error' => ['type' => 'rate_limit_error']], 429)
                ->push(['type' => 'error', 'error' => ['type' => 'overloaded_error']], 529)
                ->push(['type' => 'error', 'error' => ['type' => 'invalid_request_error', 'message' => 'max_tokens terlalu besar']], 400),
        ]);
        $klien = app(KlienLlm::class);
        $tanya = fn (string $penyedia) => $this->gagal(fn () => PromptLlm::versi($klien->tanya($penyedia, 'Sistem JSON', 'Pesan', ['suhu' => 0.4, 'maks_token' => 512]), $penyedia))->jenis;

        $this->assertSame(['model_tidak_ada', 'tidak_terhubung', 'tidak_terhubung', 'waktu_habis', 'jawaban_rusak', 'jawaban_rusak'], array_map(fn () => $tanya('ollama'), range(1, 6)));
        $this->assertSame(['kunci_ditolak', 'kuota_penyedia', 'kuota_penyedia', 'jawaban_rusak'], array_map(fn () => $tanya('claude'), range(1, 4)));
        Http::assertSentCount(10);

        // Konfigurasi belum lengkap: gagal tanpa menghubungi penyedia.
        $this->atur(['penyedia' => ['ollama' => ['model' => ''], 'openai' => ['model' => 'gpt-uji', 'ada_kunci' => false, 'kunci_terenkripsi' => null], 'gemini' => ['model' => 'gemini-uji', 'ada_kunci' => true, 'kunci_terenkripsi' => 'bukan-enkripsi']]]);
        $this->assertSame(['model_tidak_ada', 'kunci_ditolak', 'kunci_ditolak', 'tidak_terhubung'], [$tanya('ollama'), $tanya('openai'), $tanya('gemini'), $tanya('mistral')]);
        Http::assertSentCount(10);

        // Tiap kegagalan sampai di server/konfigurasi dicatat (jawaban tak terbaca dari model tidak), tanpa kunci maupun pesan penyedia.
        Log::shouldHaveReceived('warning')->withArgs(fn (string $pesan, array $konteks) => ! str_contains(json_encode($konteks), self::KUNCI))->times(13);
        Log::shouldHaveReceived('warning')->withArgs(fn (string $pesan, array $konteks) => $konteks === ['penyedia' => 'claude', 'jenis' => 'kunci_ditolak', 'rincian' => 'HTTP 401 authentication_error'])->once();
    }

    public function test_penyedia_aktif_gagal_cadangan_mengambil_alih_lalu_keduanya_gagal(): void
    {
        $this->ollama();
        $this->atur(['provider_cadangan' => 'claude', 'penyedia' => ['claude' => ['model' => 'claude-sonnet-5-5', 'ada_kunci' => true, 'kunci_terenkripsi' => Crypt::encryptString(self::KUNCI)]]]);
        Http::fake([
            '192.168.1.10:11434/*' => Http::sequence()->push(['error' => 'model not found'], 404)->pushFailedConnection(),
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
