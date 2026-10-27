<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\PengaturanAiRepository;
use App\Contracts\RiwayatPengaturanRepository;
use App\Livewire\Pengaturan\PengaturanAi;
use App\Support\Pengaturan\AturanPenyediaAi;
use App\Support\Stub\StubPengaturan;
use Illuminate\Support\Facades\Crypt;
use Livewire\Livewire;
use Tests\TestCase;

class KunciApiCloudTest extends TestCase
{
    private const KUNCI = 'sk-ant-api03-contohkuncifiktifuntukujiWXYZ';

    protected function setUp(): void
    {
        parent::setUp();
        session(['stub_role' => 'admin']);
    }

    public function test_aturan_isian_cloud(): void
    {
        $isian = fn (array $ubah = []) => $ubah + ['model' => 'claude-sonnet-5-5', 'kunci' => self::KUNCI, 'hapus_kunci' => false, 'base_url' => '', 'peran' => 'aktif'];

        $hasil = AturanPenyediaAi::cloud('claude', $isian(['base_url' => ' https://gateway.madrasah.sch.id/ ']), []);
        $this->assertSame([[], []], [$hasil['galat'], $hasil['peringatan']]);
        $this->assertSame('https://gateway.madrasah.sch.id', $hasil['data']['base_url']);

        $this->assertSame(['model', 'kunci'], array_keys(AturanPenyediaAi::cloud('claude', $isian(['model' => '', 'kunci' => '']), [])['galat']));
        $this->assertSame([], AturanPenyediaAi::cloud('claude', $isian(['kunci' => '']), ['ada_kunci' => true])['galat'], 'Kunci kosong = kunci tersimpan tetap dipakai.');
        $this->assertArrayHasKey('kunci', AturanPenyediaAi::cloud('claude', $isian(['kunci' => '', 'hapus_kunci' => true]), ['ada_kunci' => true])['galat']);
        $this->assertSame([], AturanPenyediaAi::cloud('claude', $isian(['model' => '', 'kunci' => '', 'peran' => 'tidak']), [])['galat'], 'Tidak dipakai: boleh kosong.');
        $this->assertArrayHasKey('kunci', AturanPenyediaAi::cloud('claude', $isian(['kunci' => 'sk-ant pendek']), [])['galat']);
        $this->assertArrayHasKey('base_url', AturanPenyediaAi::cloud('claude', $isian(['base_url' => 'http://gateway']), [])['galat']);
        $this->assertSame(['Kunci OpenAI biasanya diawali "sk-" — pastikan tidak tertukar dengan penyedia lain.'], AturanPenyediaAi::cloud('openai', $isian(['kunci' => 'AIzaSyContohKunciGeminiFiktif12345']), [])['peringatan']);
    }

    public function test_kunci_disimpan_terenkripsi_dan_tidak_pernah_ditampilkan(): void
    {
        $riwayat = app(RiwayatPengaturanRepository::class);

        $halaman = Livewire::test(PengaturanAi::class)
            ->assertSeeHtml('data-testid="status-kunci-claude">Kunci: belum ada')
            ->call('bukaCloud', 'claude')
            ->assertSet('isianCloud.kunci', '')
            ->set('isianCloud.model', 'claude-sonnet-5-5')
            ->set('isianCloud.kunci', self::KUNCI)
            ->set('isianCloud.peran', 'aktif')
            ->call('simpanCloud')
            ->assertHasNoErrors()
            ->assertSet('cloudTerbuka', null)
            ->assertSet('isianCloud.kunci', '')
            ->assertDispatched('notify', message: 'Konfigurasi Claude (Anthropic) tersimpan.', type: 'success')
            ->assertSee('•••• WXYZ')
            ->assertDontSee(self::KUNCI);
        $this->assertStringNotContainsString(self::KUNCI, $halaman->html());

        $claude = app(PengaturanAiRepository::class)->ambil()['penyedia']['claude'];
        $this->assertSame([true, 'WXYZ', self::KUNCI], [$claude['ada_kunci'], $claude['kunci_akhir'], Crypt::decryptString($claude['kunci_terenkripsi'])]);
        $this->assertStringNotContainsString(self::KUNCI, json_encode(app(PengaturanAiRepository::class)->ambil()));
        $ai = StubPengaturan::pengaturanAi();
        $this->assertSame(['claude', 'claude-sonnet-5-5', true], [$ai['provider_aktif'], $ai['model'], $ai['ada_kunci']]);
        $catatan = collect($riwayat->semua())->firstWhere('aksi', 'ubah_penyedia_ai')['keterangan'];
        $this->assertStringStartsWith('Claude (Anthropic) — ', $catatan);
        $this->assertStringContainsString('Kunci API: diisi', $catatan);
        $this->assertStringNotContainsString('WXYZ', $catatan);

        // Dibuka lagi: kunci tidak dimuat; ganti model saja → kunci tetap.
        Livewire::test(PengaturanAi::class)
            ->call('bukaCloud', 'claude')
            ->assertSet('isianCloud.kunci', '')
            ->assertSet('isianCloud.peran', 'aktif')
            ->assertSee('Tersimpan •••• WXYZ — kosongkan bila tidak diganti.')
            ->set('isianCloud.model', 'claude-haiku-5-5')
            ->call('simpanCloud');
        $this->assertSame([true, 'claude-haiku-5-5'], [app(PengaturanAiRepository::class)->ambil()['penyedia']['claude']['ada_kunci'], StubPengaturan::pengaturanAi()['model']]);
        $terbaru = collect($riwayat->semua())->firstWhere('aksi', 'ubah_penyedia_ai')['keterangan'];
        $this->assertSame('Claude (Anthropic) — Model: claude-sonnet-5-5 → claude-haiku-5-5', $terbaru, 'Kunci tidak diganti → tidak dicatat.');

        // Hapus kunci selama dipakai ditolak; berhenti dipakai lalu hapus → tercatat "dihapus".
        Livewire::test(PengaturanAi::class)
            ->call('bukaCloud', 'claude')
            ->set('isianCloud.hapus_kunci', true)
            ->call('simpanCloud')
            ->assertHasErrors('isianCloud.kunci')
            ->set('isianCloud.peran', 'tidak')
            ->call('simpanCloud')
            ->assertHasNoErrors();
        $this->assertFalse(app(PengaturanAiRepository::class)->ambil()['penyedia']['claude']['ada_kunci']);
        $this->assertNull(StubPengaturan::pengaturanAi()['provider_aktif']);
        $this->assertTrue(collect($riwayat->semua())->contains(fn ($r) => str_contains($r['keterangan'], 'Kunci API: dihapus')));

        Livewire::test(PengaturanAi::class)->call('bukaCloud', 'ollama')->assertNotFound();
    }

    public function test_peringatan_awalan_kunci_perlu_dikonfirmasi(): void
    {
        $halaman = Livewire::test(PengaturanAi::class)
            ->call('bukaCloud', 'openai')
            ->set('isianCloud.model', 'model-openai')
            ->set('isianCloud.kunci', 'AIzaSyContohKunciGeminiFiktif12345')
            ->call('simpanCloud')
            ->assertSeeHtml('data-testid="peringatan-cloud"')
            ->assertSee('Tetap simpan')
            ->assertSet('cloudTerbuka', 'openai');
        $this->assertFalse(app(PengaturanAiRepository::class)->ambil()['penyedia']['openai']['ada_kunci'] ?? false);

        // Kunci diganti → peringatan dinilai ulang; kunci yang sama disimpan pada simpan kedua.
        $halaman->set('isianCloud.kunci', 'AIzaSyContohKunciGeminiFiktif67890')
            ->assertSet('peringatanCloud', [])
            ->call('simpanCloud')
            ->assertSet('cloudTerbuka', 'openai')
            ->call('simpanCloud')
            ->assertSet('cloudTerbuka', null);
        $this->assertSame('7890', app(PengaturanAiRepository::class)->ambil()['penyedia']['openai']['kunci_akhir']);
    }
}
