<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\PengaturanAiRepository;
use App\Contracts\RiwayatPengaturanRepository;
use App\Livewire\Pengaturan\PengaturanAi;
use App\Support\Pengaturan\AturanPenyediaAi;
use App\Support\Stub\StubPengaturan;
use App\Support\Stub\StubPengaturanAiRepository;
use Livewire\Livewire;
use Tests\TestCase;

class PemilihanPenyediaAiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        session(['stub_role' => 'admin']);
    }

    /** Ollama & Claude siap dipakai, tanpa penyedia terpilih. */
    private function siapkan(): void
    {
        $data = array_diff_key(StubPengaturanAiRepository::bawaan(), ['diperbarui_pada' => 1]);
        $data['penyedia']['ollama']['model'] = 'qwen2.5:7b';
        $data['penyedia']['claude'] = ['model' => 'claude-sonnet-5-5', 'ada_kunci' => true];
        app(PengaturanAiRepository::class)->simpan($data);
    }

    public function test_kesiapan_dan_aturan_pilihan(): void
    {
        $bawaan = StubPengaturanAiRepository::bawaan();
        $this->assertSame(['siap' => false, 'kurang' => ['model']], AturanPenyediaAi::siap($bawaan, 'ollama'));
        $this->assertSame(['siap' => false, 'kurang' => ['model', 'kunci API']], AturanPenyediaAi::siap($bawaan, 'claude'));
        $this->siapkan();
        $ai = app(PengaturanAiRepository::class)->ambil();
        $this->assertTrue(AturanPenyediaAi::siap($ai, 'ollama')['siap']);
        $this->assertTrue(AturanPenyediaAi::siap($ai, 'claude')['siap']);

        $this->assertSame([], AturanPenyediaAi::pilih($ai, 'claude', 'ollama')['galat']);
        $this->assertSame([], AturanPenyediaAi::pilih($ai, '', '')['galat'], 'Tanpa penyedia boleh (saran tiruan).');
        $this->assertSame(['cadangan'], array_keys(AturanPenyediaAi::pilih($ai, null, 'ollama')['galat']));
        $this->assertSame('Penyedia cadangan harus berbeda dari penyedia aktif.', AturanPenyediaAi::pilih($ai, 'ollama', 'ollama')['galat']['cadangan']);
        $this->assertSame('Gemini (Google) belum siap — lengkapi model, kunci API dulu.', AturanPenyediaAi::pilih($ai, 'gemini', null)['galat']['aktif']);
    }

    public function test_pilih_tukar_dan_lepas_penyedia(): void
    {
        $this->siapkan();
        $riwayat = app(RiwayatPengaturanRepository::class);

        Livewire::test(PengaturanAi::class)
            ->call('bukaPilihan')
            ->assertSeeHtml('data-testid="pilih-penyedia"')
            ->assertSeeHtml('data-pilih-penyedia="gemini" data-siap="tidak"')
            ->assertSeeHtml('data-pilih-penyedia="claude" data-siap="ya"')
            ->assertSet('pilihan', ['aktif' => '', 'cadangan' => ''])
            ->set('pilihan.cadangan', 'ollama')
            ->call('simpanPilihan')
            ->assertHasErrors('pilihan.cadangan')
            ->set('pilihan.aktif', 'gemini')
            ->call('simpanPilihan')
            ->assertHasErrors('pilihan.aktif')
            ->set('pilihan.aktif', 'claude')
            ->assertSee('bila gagal: Ollama (server sekolah)')
            ->call('simpanPilihan')
            ->assertHasNoErrors()
            ->assertSet('pilihTerbuka', false)
            ->assertDispatched('notify', message: 'Penyedia AI tersimpan: Claude (Anthropic), cadangan Ollama (server sekolah).', type: 'success');

        $ai = StubPengaturan::pengaturanAi();
        $this->assertSame(['claude', 'ollama', 'claude-sonnet-5-5', true], [$ai['provider_aktif'], $ai['provider_cadangan'], $ai['model'], $ai['ada_kunci']]);
        $this->assertSame('Penyedia aktif: belum dipilih → Claude (Anthropic); Penyedia cadangan: belum dipilih → Ollama (server sekolah)', collect($riwayat->semua())->firstWhere('aksi', 'ubah_penyedia_ai')['keterangan']);

        Livewire::test(PengaturanAi::class)
            ->call('bukaPilihan')
            ->assertSet('pilihan', ['aktif' => 'claude', 'cadangan' => 'ollama'])
            ->call('tukarPilihan')
            ->assertSet('pilihan', ['aktif' => 'ollama', 'cadangan' => 'claude'])
            ->call('simpanPilihan')
            ->assertHasNoErrors();
        $this->assertSame(['ollama', 'claude'], [StubPengaturan::pengaturanAi()['provider_aktif'], StubPengaturan::pengaturanAi()['provider_cadangan']]);

        // Bentrok dengan perubahan di tempat lain: pilihan tetap, tidak tersimpan.
        $halaman = Livewire::test(PengaturanAi::class)->call('bukaPilihan')->set('pilihan.cadangan', '');
        app(PengaturanAiRepository::class)->simpan(['batas_per_user_per_hari' => 25] + array_diff_key(app(PengaturanAiRepository::class)->ambil(), ['diperbarui_pada' => 1]));
        $halaman->call('simpanPilihan')->assertDispatched('notify', type: 'error')->assertSet('pilihTerbuka', true)->assertSet('pilihan.cadangan', '');
        $this->assertSame('claude', StubPengaturan::pengaturanAi()['provider_cadangan']);

        // Tanpa penyedia sama sekali → saran tiruan.
        $halaman->set('pilihan.aktif', '')->call('simpanPilihan')
            ->assertDispatched('notify', message: 'Tanpa penyedia AI — tombol “Buat dengan AI” memakai saran contoh (tiruan).', type: 'success')
            ->assertSeeHtml('data-testid="ai-belum-dipilih"');
        $this->assertSame([null, null], [StubPengaturan::pengaturanAi()['provider_aktif'], StubPengaturan::pengaturanAi()['provider_cadangan']]);
    }
}
