<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\PengaturanAiRepository;
use App\Contracts\PromptAiRepository;
use App\Livewire\Pengaturan\PengaturanAi;
use App\Support\Stub\StubPengaturanAiRepository;
use App\Support\Stub\StubPromptAiRepository;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class SimpanPengaturanAiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        session(['stub_role' => 'admin']);
    }

    public function test_status_simpan_terakhir_disimpan_dan_penanda_formulir(): void
    {
        Livewire::test(PengaturanAi::class)
            ->assertDontSeeHtml('data-testid="terakhir-disimpan-ai"')
            ->assertSeeHtml('window.SIRABTOR.adaFormKotor()')
            ->call('bukaLokal')
            ->assertSeeHtml('x-data="formAi(\'isianLokal\', \'lokal\')"')
            ->assertSeeHtml('data-testid="belum-disimpan-lokal"')
            ->set('isianLokal.model', 'qwen2.5:7b')
            ->call('simpanLokal')
            ->assertDispatched('pengaturan-ai-tersimpan', bagian: 'lokal')
            ->assertSet('gagalSimpan', null)
            ->assertSeeHtml('data-testid="terakhir-disimpan-ai"')
            ->assertSee('oleh Koordinator PUSKOM')
            ->assertSee('Ubah penyedia AI')
            ->assertSeeHtml('href="'.route('pengaturan.riwayat.index', ['bagian' => 'ai']).'"');

        Livewire::withQueryParams(['tab' => 'model'])->test(PengaturanAi::class)
            ->assertSeeHtml('x-data="formAi(\'isianParameter\', \'parameter\')"')
            ->call('pilihPresetSuhu', 'kreatif')
            ->call('simpanParameter')
            ->assertDispatched('pengaturan-ai-tersimpan', bagian: 'parameter')
            ->assertSee('Ubah model &amp; parameter AI', false);

        Livewire::withQueryParams(['tab' => 'prompt'])->test(PengaturanAi::class)
            ->call('ubahGaya')
            ->set('isianGaya', 'Bahasa baku yang ringkas.')
            ->call('simpanGaya')
            ->assertDispatched('pengaturan-ai-tersimpan', bagian: 'gaya');
    }

    public function test_gagal_simpan_tampil_di_formulir_tanpa_membuang_isian(): void
    {
        $this->app->instance(PengaturanAiRepository::class, new class extends StubPengaturanAiRepository
        {
            public function simpan(array $data): void
            {
                throw new RuntimeException('Pengaturan AI gagal disimpan. Coba lagi.');
            }
        });

        $halaman = Livewire::test(PengaturanAi::class)
            ->call('bukaLokal')
            ->set('isianLokal.model', 'qwen2.5:7b')
            ->call('simpanLokal')
            ->assertSet('gagalSimpan', ['bagian' => 'lokal', 'pesan' => 'Pengaturan AI gagal disimpan. Coba lagi.'])
            ->assertSeeHtml('data-testid="gagal-simpan-lokal"')
            ->assertSee('Isian Anda masih ada — tekan Simpan untuk mencoba lagi.')
            ->assertSet('lokalTerbuka', true)
            ->assertSet('isianLokal.model', 'qwen2.5:7b')
            ->assertNotDispatched('pengaturan-ai-tersimpan');

        // Penyimpanan pulih → coba lagi berhasil dan pesan gagal hilang.
        $this->app->instance(PengaturanAiRepository::class, new StubPengaturanAiRepository);
        $halaman->call('simpanLokal')->assertSet('gagalSimpan', null)->assertSet('lokalTerbuka', false);

        $this->app->instance(PromptAiRepository::class, new class extends StubPromptAiRepository
        {
            public function simpan(array $data): void
            {
                throw new RuntimeException('Prompt AI gagal disimpan. Coba lagi.');
            }
        });
        Livewire::withQueryParams(['tab' => 'prompt'])->test(PengaturanAi::class)
            ->call('ubahPrompt', 'tujuan')
            ->set('isianPrompt.aktif', false)
            ->call('simpanPromptBagian')
            ->assertSet('gagalSimpan.bagian', 'prompt')
            ->assertSeeHtml('data-testid="gagal-simpan-prompt"')
            ->assertSet('promptTerbuka', 'tujuan');
    }
}
