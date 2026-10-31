<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\DaftarModelAi;
use App\Contracts\PengaturanAiRepository;
use App\Livewire\Pengaturan\PengaturanAi;
use App\Support\Kegiatan\DaftarModelAiHttp;
use App\Support\Stub\StubDaftarModelAi;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class DaftarModelAiHttpTest extends TestCase
{
    private const KUNCI = 'sk-ant-api03-kunci-fiktif-uji-ABCD';

    protected function setUp(): void
    {
        parent::setUp();
        session(['stub_role' => 'admin']);
    }

    /** @return array<string, mixed> */
    private function cloud(string $model = ''): array
    {
        return ['model' => $model, 'ada_kunci' => true, 'kunci_akhir' => 'ABCD', 'kunci_terenkripsi' => Crypt::encryptString(self::KUNCI)];
    }

    private function galat(\Closure $jalan): string
    {
        try {
            $jalan();
        } catch (RuntimeException $e) {
            return $e->getMessage();
        }
        $this->fail('Seharusnya gagal.');
    }

    public function test_binding_mengikuti_konfigurasi(): void
    {
        $this->assertInstanceOf(StubDaftarModelAi::class, app(DaftarModelAi::class), 'phpunit.xml memakai tiruan.');
        config(['sigenrabtor.ai.daftar_model' => 'penyedia']);
        $this->app->forgetInstance(DaftarModelAi::class);
        $this->assertInstanceOf(DaftarModelAiHttp::class, app(DaftarModelAi::class));
    }

    public function test_claude_berhalaman_dengan_kepala_anthropic(): void
    {
        Http::fake([
            'api.anthropic.com/v1/models?limit=100' => Http::response(['data' => [
                ['type' => 'model', 'id' => 'claude-opus-5-5', 'display_name' => 'Claude Opus 5.5', 'max_input_tokens' => 1_000_000, 'max_tokens' => 128000],
                ['type' => 'model', 'id' => 'claude-sonnet-5-5', 'display_name' => 'Claude Sonnet 5.5', 'max_input_tokens' => 1_000_000],
            ], 'has_more' => true, 'first_id' => 'claude-opus-5-5', 'last_id' => 'claude-sonnet-5-5']),
            'api.anthropic.com/v1/models?limit=100&after_id=claude-sonnet-5-5' => Http::response(['data' => [
                ['type' => 'model', 'id' => 'claude-haiku-4-5-20251001', 'display_name' => 'Claude Haiku 4.5', 'max_input_tokens' => 200_000],
                ['type' => 'model', 'id' => "claude-'jahat'", 'display_name' => 'Nama tak sah'],
                ['type' => 'model', 'id' => 'claude-opus-5-5', 'display_name' => 'Ganda'],
            ], 'has_more' => false, 'last_id' => 'claude-opus-5-5']),
        ]);

        $daftar = (new DaftarModelAiHttp)->daftar('claude', $this->cloud());

        $this->assertSame(['claude-opus-5-5', 'claude-sonnet-5-5', 'claude-haiku-4-5-20251001'], array_column($daftar, 'nama'), 'Nama tak berpola & ganda dibuang.');
        $this->assertSame([
            'Claude Opus 5.5 · lebih cermat; biaya lebih tinggi · konteks 1 jt token.',
            'Claude Sonnet 5.5 · seimbang antara mutu & biaya · konteks 1 jt token.',
            'Claude Haiku 4.5 · paling cepat & hemat · konteks 200 rb token.',
        ], array_column($daftar, 'keterangan'));
        $this->assertSame([false], array_values(array_unique(array_column($daftar, 'disarankan'))), 'Tidak ada model yang disarankan aplikasi.');
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $r) => $r->method() === 'GET' && $r->header('x-api-key') === [self::KUNCI] && $r->header('anthropic-version') === ['2023-06-01']);
    }

    public function test_ollama_openai_dan_gemini(): void
    {
        Http::fake([
            '192.168.1.10:11434/api/tags' => Http::response(['models' => [
                ['name' => 'qwen2.5:7b', 'size' => 4_683_087_332, 'details' => ['family' => 'qwen2', 'parameter_size' => '7.6B', 'quantization_level' => 'Q4_K_M']],
                ['name' => 'nomic-embed-text:latest', 'size' => 274_302_450, 'details' => ['family' => 'nomic-bert']],
                ['name' => 'llama3.1:8b', 'size' => 4_920_753_328, 'details' => []],
                ['name' => 'qwen3:8b', 'size' => 5_225_387_923, 'details' => ['family' => 'qwen3', 'parameter_size' => '8.2B']],
            ]]),
            'api.openai.com/v1/models' => Http::response(['data' => [
                ['id' => 'gpt-4.1', 'created' => 100], ['id' => 'text-embedding-3-large', 'created' => 300], ['id' => 'o4-mini', 'created' => 200],
                ['id' => 'whisper-1', 'created' => 400], ['id' => 'gpt-4o-realtime-preview', 'created' => 500], ['id' => 'dall-e-3', 'created' => 50],
            ]]),
            'generativelanguage.googleapis.com/v1beta/models?pageSize=100' => Http::response(['models' => [
                ['name' => 'models/gemini-2.5-flash', 'displayName' => 'Gemini 2.5 Flash', 'inputTokenLimit' => 1_048_576, 'supportedGenerationMethods' => ['generateContent', 'countTokens']],
                ['name' => 'models/text-embedding-004', 'supportedGenerationMethods' => ['embedContent']],
            ], 'nextPageToken' => 'hal2']),
            'generativelanguage.googleapis.com/v1beta/models?pageSize=100&pageToken=hal2' => Http::response(['models' => [
                ['name' => 'models/gemini-2.5-pro', 'displayName' => 'Gemini 2.5 Pro', 'supportedGenerationMethods' => ['generateContent']],
            ]]),
        ]);
        $klien = new DaftarModelAiHttp;

        $ollama = $klien->daftar('ollama', ['base_url' => 'http://192.168.1.10:11434']);
        $this->assertSame(['qwen2.5:7b', 'llama3.1:8b', 'qwen3:8b'], array_column($ollama, 'nama'), 'Model embedding dilewati.');
        $this->assertSame('Terpasang di server sekolah · 8.2B parameter · model berpikir — mode berpikir dimatikan otomatis.', $ollama[2]['keterangan']);
        $this->assertSame(['4,7 GB', 'Terpasang di server sekolah · 7.6B parameter · Q4_K_M.'], [$ollama[0]['ukuran'], $ollama[0]['keterangan']]);

        $openai = $klien->daftar('openai', $this->cloud());
        $this->assertSame(['o4-mini', 'gpt-4.1'], array_column($openai, 'nama'), 'Hanya model obrolan, terbaru dulu.');
        $this->assertStringContainsString('Kreativitas tidak berlaku', $openai[0]['keterangan']);

        $gemini = $klien->daftar('gemini', $this->cloud());
        $this->assertSame(['gemini-2.5-flash', 'gemini-2.5-pro'], array_column($gemini, 'nama'));
        $this->assertSame('Gemini 2.5 Flash · konteks 1 jt token.', $gemini[0]['keterangan']);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'openai.com') && $r->header('Authorization') === ['Bearer '.self::KUNCI]);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'googleapis.com') && $r->header('x-goog-api-key') === [self::KUNCI] && ! str_contains($r->url(), self::KUNCI));
    }

    public function test_galat_berpesan_untuk_admin_tanpa_kunci(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::sequence()
                ->push(['type' => 'error', 'error' => ['type' => 'authentication_error', 'message' => 'invalid x-api-key']], 401)
                ->push(['type' => 'error', 'error' => ['type' => 'overloaded_error']], 529),
            '192.168.1.10:11434/*' => Http::failedConnection(),
        ]);
        $klien = new DaftarModelAiHttp;

        $this->assertSame('Alamat server sekolah belum diisi — atur di tab Penyedia.', $this->galat(fn () => $klien->daftar('ollama', ['base_url' => ''])));
        $this->assertSame('Kunci API Claude (Anthropic) belum diisi — atur di tab Penyedia.', $this->galat(fn () => $klien->daftar('claude', ['ada_kunci' => false])));
        $this->assertSame('Daftar model Claude (Anthropic) tidak bisa dimuat: kunci API ditolak. Kunci API tidak sah atau kedaluwarsa — ganti kunci di tab Penyedia.', $this->galat(fn () => $klien->daftar('claude', $this->cloud())));
        $this->assertStringStartsWith('Daftar model Claude (Anthropic) tidak bisa dimuat: penyedia AI sedang sibuk.', $this->galat(fn () => $klien->daftar('claude', $this->cloud())));
        $this->assertStringStartsWith('Daftar model Ollama (server sekolah) tidak bisa dimuat: server AI tidak bisa dihubungi.', $this->galat(fn () => $klien->daftar('ollama', ['base_url' => 'http://192.168.1.10:11434'])));
    }

    public function test_tab_model_memuat_dan_memakai_model_dari_server_penyedia(): void
    {
        config(['sigenrabtor.ai.daftar_model' => 'penyedia']);
        $this->app->forgetInstance(DaftarModelAi::class);
        $data = array_diff_key(app(PengaturanAiRepository::class)->ambil(), ['diperbarui_pada' => 1]);
        $data['provider_aktif'] = 'claude';
        $data['penyedia']['claude'] = $this->cloud('claude-sonnet-5-5');
        app(PengaturanAiRepository::class)->simpan($data);
        Http::fake(['api.anthropic.com/v1/models*' => Http::response(['data' => [
            ['id' => 'claude-opus-5-5', 'display_name' => 'Claude Opus 5.5'], ['id' => 'claude-sonnet-5-5', 'display_name' => 'Claude Sonnet 5.5'],
        ], 'has_more' => false])]);

        Livewire::withQueryParams(['tab' => 'model'])->test(PengaturanAi::class)
            ->call('muatModel', 'claude')
            ->assertSeeHtml('data-model="claude-opus-5-5"')
            ->assertDontSee('Disarankan')
            ->call('pakaiModel', 'claude', 'claude-opus-5-5')
            ->assertDispatched('notify', message: 'Model claude-opus-5-5 dipakai untuk Claude (Anthropic).', type: 'success');
        $this->assertSame('claude-opus-5-5', app(PengaturanAiRepository::class)->ambil()['penyedia']['claude']['model']);

        // Endpoint admin memakai daftar yang sama.
        $this->getJson(route('pengaturan.ai.model', 'claude'))->assertOk()->assertJsonPath('daftar.1.nama', 'claude-sonnet-5-5')->assertJsonPath('galat', null);
    }
}
