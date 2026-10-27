<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\PengaturanAiRepository;
use App\Contracts\RiwayatPengaturanRepository;
use App\Livewire\Pengaturan\PengaturanAi;
use App\Support\Pengaturan\AturanPenyediaAi;
use App\Support\Stub\StubPengaturan;
use App\Support\Stub\StubPengaturanAiRepository;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class PenyediaLokalAiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        session(['stub_role' => 'admin']);
    }

    public function test_aturan_isian_lokal_dan_peran_penyedia(): void
    {
        ['data' => $data, 'galat' => $galat] = AturanPenyediaAi::lokal(['base_url' => ' http://192.168.1.10:11434/ ', 'model' => ' qwen2.5:7b ', 'batas_waktu' => '90', 'peran' => 'aktif']);
        $this->assertSame([], $galat);
        $this->assertSame(['base_url' => 'http://192.168.1.10:11434', 'model' => 'qwen2.5:7b', 'batas_waktu' => 90, 'peran' => 'aktif'], $data);

        $galat = AturanPenyediaAi::lokal(['base_url' => 'ftp://server', 'model' => '', 'batas_waktu' => 5, 'peran' => 'cadangan'])['galat'];
        $this->assertSame(['base_url', 'model', 'batas_waktu'], array_keys($galat));
        $this->assertArrayHasKey('base_url', AturanPenyediaAi::lokal(['base_url' => '', 'batas_waktu' => 60, 'peran' => 'tidak'])['galat']);
        $this->assertSame([], AturanPenyediaAi::lokal(['base_url' => 'http://localhost:11434', 'model' => '', 'batas_waktu' => 60, 'peran' => 'tidak'])['galat'], 'Model boleh kosong bila tidak dipakai.');
        $this->assertArrayHasKey('model', AturanPenyediaAi::lokal(['base_url' => 'http://localhost', 'model' => 'qwen 7b', 'batas_waktu' => 60, 'peran' => 'tidak'])['galat']);
        $this->assertArrayHasKey('peran', AturanPenyediaAi::lokal(['base_url' => 'http://localhost', 'model' => 'a', 'batas_waktu' => 60, 'peran' => 'utama'])['galat']);

        $pengaturan = ['provider_aktif' => 'claude', 'provider_cadangan' => null];
        $this->assertSame(['provider_aktif' => 'ollama', 'provider_cadangan' => null], AturanPenyediaAi::terapkanPeran($pengaturan, 'ollama', 'aktif'));
        $this->assertSame(['provider_aktif' => 'claude', 'provider_cadangan' => 'ollama'], AturanPenyediaAi::terapkanPeran($pengaturan, 'ollama', 'cadangan'));
        $this->assertSame(['provider_aktif' => 'ollama', 'provider_cadangan' => null], AturanPenyediaAi::terapkanPeran(['provider_aktif' => null, 'provider_cadangan' => null], 'ollama', 'cadangan'), 'Tanpa aktif, cadangan naik.');
        $this->assertSame(['provider_aktif' => 'claude', 'provider_cadangan' => null], AturanPenyediaAi::terapkanPeran(['provider_aktif' => 'ollama', 'provider_cadangan' => 'claude'], 'ollama', 'tidak'));
        $this->assertSame('cadangan', AturanPenyediaAi::peranDari(['provider_aktif' => 'claude', 'provider_cadangan' => 'ollama'], 'ollama'));
    }

    public function test_simpan_konfigurasi_server_sekolah(): void
    {
        Livewire::test(PengaturanAi::class)
            ->assertSeeHtml('data-testid="peran-lokal">Tidak dipakai')
            ->call('bukaLokal')
            ->assertSet('isianLokal', ['base_url' => 'http://localhost:11434', 'model' => '', 'batas_waktu' => 60, 'peran' => 'tidak'])
            ->assertSeeHtml('data-testid="formulir-lokal"')
            ->set('isianLokal.peran', 'aktif')
            ->call('simpanLokal')
            ->assertHasErrors('isianLokal.model')
            ->set('isianLokal.model', 'qwen2.5:7b')
            ->set('isianLokal.base_url', 'http://192.168.1.10:11434/')
            ->call('simpanLokal')
            ->assertHasNoErrors()
            ->assertSet('lokalTerbuka', false)
            ->assertDispatched('notify', message: 'Konfigurasi server sekolah tersimpan.', type: 'success')
            ->assertSeeHtml('data-testid="peran-lokal">Penyedia aktif')
            ->assertDontSeeHtml('data-testid="ai-belum-dipilih"')
            ->assertSee('Ollama (server sekolah)')
            ->assertSee('http://192.168.1.10:11434');

        $ai = StubPengaturan::pengaturanAi();
        $this->assertSame(['ollama', 'qwen2.5:7b', 'http://192.168.1.10:11434'], [$ai['provider_aktif'], $ai['model'], $ai['base_url']]);
        $catatan = collect(app(RiwayatPengaturanRepository::class)->semua())->firstWhere('aksi', 'ubah_penyedia_ai');
        $this->assertStringContainsString('Server sekolah (Ollama) — ', $catatan['keterangan']);
        $this->assertStringContainsString('Penyedia aktif: belum dipilih → Ollama (server sekolah)', $catatan['keterangan']);

        // Simpan tanpa perubahan tidak mencatat riwayat lagi.
        $jumlah = count(app(RiwayatPengaturanRepository::class)->semua());
        Livewire::test(PengaturanAi::class)->call('bukaLokal')->call('simpanLokal');
        $this->assertCount($jumlah, app(RiwayatPengaturanRepository::class)->semua());

        // Cadangan tanpa penyedia aktif → dipakai sebagai aktif.
        app(PengaturanAiRepository::class)->simpan(array_diff_key(StubPengaturanAiRepository::bawaan(), ['diperbarui_pada' => 1]));
        Livewire::test(PengaturanAi::class)
            ->call('bukaLokal')
            ->set('isianLokal.model', 'llama3.1:8b')
            ->set('isianLokal.peran', 'cadangan')
            ->call('simpanLokal')
            ->assertDispatched('notify', message: 'Konfigurasi server sekolah tersimpan — belum ada penyedia aktif, jadi server sekolah dipakai sebagai penyedia aktif.', type: 'success');
        $this->assertSame('ollama', StubPengaturan::pengaturanAi()['provider_aktif']);
    }

    public function test_bentrok_dan_gagal_simpan_tidak_membuang_isian(): void
    {
        $halaman = Livewire::test(PengaturanAi::class)->call('bukaLokal')->set('isianLokal.model', 'gemma2:9b')->set('isianLokal.peran', 'aktif');
        app(PengaturanAiRepository::class)->simpan(['batas_per_user_per_hari' => 30] + array_diff_key(StubPengaturanAiRepository::bawaan(), ['diperbarui_pada' => 1]));

        $halaman->call('simpanLokal')
            ->assertDispatched('notify', type: 'error')
            ->assertSet('lokalTerbuka', true)
            ->assertSet('isianLokal.model', 'gemma2:9b');
        $this->assertNull(StubPengaturan::pengaturanAi()['provider_aktif']);
        // Sesudah dimuat ulang, simpan berikutnya berhasil.
        $halaman->call('simpanLokal')->assertSet('lokalTerbuka', false);
        $this->assertSame(['ollama', 30], [StubPengaturan::pengaturanAi()['provider_aktif'], StubPengaturan::pengaturanAi()['batas_per_user_per_hari']]);

        $this->app->instance(PengaturanAiRepository::class, new class extends StubPengaturanAiRepository
        {
            public function simpan(array $data): void
            {
                throw new RuntimeException('Pengaturan AI gagal disimpan. Coba lagi.');
            }
        });
        Livewire::test(PengaturanAi::class)->call('bukaLokal')->set('isianLokal.model', 'qwen2.5:3b')->call('simpanLokal')
            ->assertDispatched('notify', message: 'Pengaturan AI gagal disimpan. Coba lagi.', type: 'error')
            ->assertSet('lokalTerbuka', true)
            ->assertSet('isianLokal.model', 'qwen2.5:3b');
    }
}
