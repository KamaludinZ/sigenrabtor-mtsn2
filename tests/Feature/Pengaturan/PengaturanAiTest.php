<?php

namespace Tests\Feature\Pengaturan;

use App\Livewire\Pengaturan\PengaturanAi;
use App\Support\Kegiatan\BagianAi;
use App\Support\Kegiatan\BatasAi;
use App\Support\Pengaturan\PenyediaAi;
use App\Support\Pengaturan\PromptAi;
use Livewire\Livewire;
use Tests\TestCase;

class PengaturanAiTest extends TestCase
{
    public function test_halaman_khusus_admin_dan_tersambung_dari_menu(): void
    {
        $this->get(route('pengaturan.ai.index'))->assertForbidden();

        $this->withSession(['stub_role' => 'admin'])
            ->get(route('pengaturan.ai.index'))
            ->assertOk()
            ->assertSeeLivewire(PengaturanAi::class)
            ->assertSee('<title>Pengaturan AI · ', false)
            ->assertSeeHtml('href="'.route('pengaturan.ai.index').'"');
    }

    public function test_tab_penyedia_prompt_dan_pemakaian(): void
    {
        session(['stub_role' => 'admin']);

        Livewire::test(PengaturanAi::class)
            ->assertSet('tab', 'penyedia')
            ->assertSeeHtml('data-testid="ai-belum-dipilih"')
            ->assertSeeHtml('data-testid="penyedia-aktif"')
            ->assertSeeHtml('data-testid="penyedia-cadangan"')
            ->assertSeeHtmlInOrder(array_map(fn ($k) => 'data-penyedia="'.$k.'"', array_keys(PenyediaAi::DAFTAR)))
            ->assertSee('Tidak pernah: NIP, nama pejabat, pagu dan rincian RAB.')
            ->set('tab', 'prompt')
            ->assertSeeHtml('data-testid="panel-prompt-ai"')
            ->assertSeeHtmlInOrder(array_map(fn ($k) => 'data-prompt="'.$k.'"', array_keys(BagianAi::BAGIAN)))
            ->assertSee(PromptAi::GAYA_BAHASA)
            ->assertSee(PromptAi::BAWAAN['tujuan']);

        // Pemakaian hari ini per akun aktif (akun nonaktif tidak ikut), terbanyak dulu.
        BatasAi::catat(['id' => 3], 'tujuan');
        BatasAi::catat(['id' => 3], 'sasaran');
        $halaman = Livewire::withQueryParams(['tab' => 'pemakaian'])->test(PengaturanAi::class)
            ->assertSeeHtml('data-testid="panel-pemakaian-ai"')
            ->assertSee('2 permintaan dari semua akun · batas umum 20 per akun per hari.')
            ->assertSeeHtml('data-akun="3"')
            ->assertDontSeeHtml('data-akun="6"')
            ->assertSeeHtml('href="'.route('saran_ai.index').'"');
        $this->assertSame([3, 2], [$halaman->instance()->pemakaianHariIni()[0]['id'], $halaman->instance()->pemakaianHariIni()[0]['terpakai']]);
        $this->assertSame(4, $halaman->instance()->ringkasanSaran()['permintaan']);

        Livewire::withQueryParams(['tab' => 'asing'])->test(PengaturanAi::class)->assertSet('tab', 'penyedia');
    }
}
