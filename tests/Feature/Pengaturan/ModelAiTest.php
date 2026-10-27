<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\PengaturanAiRepository;
use App\Contracts\RiwayatPengaturanRepository;
use App\Livewire\Kegiatan\KalimatAi;
use App\Livewire\Pengaturan\PengaturanAi;
use App\Support\Pengaturan\ParameterAi;
use App\Support\Stub\StubPengaturanAiRepository;
use Livewire\Livewire;
use Tests\TestCase;

class ModelAiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        session(['stub_role' => 'admin']);
    }

    private function simpan(callable $ubah): void
    {
        $data = array_diff_key(app(PengaturanAiRepository::class)->ambil(), ['diperbarui_pada' => 1]);
        app(PengaturanAiRepository::class)->simpan($ubah($data));
    }

    public function test_aturan_parameter_jawaban(): void
    {
        $this->assertSame(ParameterAi::BAWAAN, ParameterAi::dari([]));
        $this->assertSame(['suhu' => 0.8, 'maks_token' => 2048, 'jumlah_versi' => 2], ParameterAi::rapikan(['suhu' => '0.83', 'maks_token' => '2048', 'jumlah_versi' => '2'])['data']);
        $this->assertSame(['suhu', 'maks_token', 'jumlah_versi'], array_keys(ParameterAi::rapikan(['suhu' => 1.5, 'maks_token' => 999, 'jumlah_versi' => 4])['galat']));
        $this->assertSame(['kreatif', null], [ParameterAi::preset(0.8), ParameterAi::preset(0.7)]);
    }

    public function test_parameter_disimpan_dan_membatasi_jumlah_versi_saran(): void
    {
        Livewire::withQueryParams(['tab' => 'model'])->test(PengaturanAi::class)
            ->assertSeeHtml('data-testid="model-tanpa-penyedia"')
            ->assertSet('isianParameter', ParameterAi::BAWAAN)
            ->call('pilihPresetSuhu', 'kreatif')
            ->assertSet('isianParameter.suhu', 0.8)
            ->assertSee('Versi lebih beragam; periksa lebih teliti.')
            ->set('isianParameter.maks_token', '2048')
            ->set('isianParameter.jumlah_versi', '2')
            ->call('simpanParameter')
            ->assertHasNoErrors()
            ->assertDispatched('notify', message: 'Parameter jawaban AI tersimpan.', type: 'success')
            ->set('isianParameter.jumlah_versi', '5')
            ->call('simpanParameter')
            ->assertHasErrors('isianParameter.jumlah_versi')
            ->call('kembalikanParameter')
            ->assertSet('isianParameter', ParameterAi::BAWAAN);

        $this->assertSame(['suhu' => 0.8, 'maks_token' => 2048, 'jumlah_versi' => 2], ParameterAi::dari(app(PengaturanAiRepository::class)->ambil()));
        $this->assertSame('Parameter jawaban — Kreativitas: 0,4 → 0,8; Panjang jawaban: Sedang (± 2–3 paragraf) → Panjang (± 5 paragraf); Jumlah versi: 3 versi → 2 versi', collect(app(RiwayatPengaturanRepository::class)->semua())->firstWhere('aksi', 'ubah_model_ai')['keterangan']);

        // Buat dengan AI hanya memberi 2 versi.
        $halaman = Livewire::test(KalimatAi::class, ['kegiatan' => 1])->call('mintaSaran', 'tujuan');
        $this->assertCount(2, $halaman->get('saran')['tujuan']);
    }

    public function test_daftar_model_penyedia_aktif_dan_cadangan(): void
    {
        $this->simpan(function (array $d) {
            $d['provider_aktif'] = 'ollama';
            $d['provider_cadangan'] = 'claude';
            $d['penyedia']['ollama']['model'] = 'qwen2.5:7b';
            $d['penyedia']['claude'] = ['model' => 'claude-sonnet-5-5', 'ada_kunci' => false];

            return $d;
        });

        $halaman = Livewire::withQueryParams(['tab' => 'model'])->test(PengaturanAi::class)
            ->assertSeeHtml('data-testid="model-aktif"')
            ->assertSeeHtml('data-testid="model-cadangan"')
            ->call('muatModel', 'ollama')
            ->assertSeeHtml('data-model="qwen2.5:3b"')
            ->assertSee('Disarankan')
            ->call('muatModel', 'claude')
            ->assertSeeHtml('data-testid="galat-model-claude"')
            ->assertSee('Kunci API Claude belum diisi')
            ->call('pakaiModel', 'ollama', 'qwen2.5:3b')
            ->assertDispatched('notify', message: 'Model qwen2.5:3b dipakai untuk Ollama (server sekolah).', type: 'success')
            ->assertSeeHtml('data-testid="model-dipakai-ollama">qwen2.5:3b');
        $this->assertSame('qwen2.5:3b', app(PengaturanAiRepository::class)->ambil()['penyedia']['ollama']['model']);
        $this->assertSame('Ollama (server sekolah) — Model: qwen2.5:7b → qwen2.5:3b', collect(app(RiwayatPengaturanRepository::class)->semua())->firstWhere('aksi', 'ubah_model_ai')['keterangan']);

        // Simpan dari halaman ini tidak membuat parameter dianggap bentrok; perubahan dari tempat lain membatalkannya.
        $halaman->call('pilihPresetSuhu', 'konsisten')->call('simpanParameter')->assertDispatched('notify', message: 'Parameter jawaban AI tersimpan.', type: 'success');
        $this->simpan(fn (array $d) => ['batas_per_user_per_hari' => 30] + $d);
        $halaman->call('pilihPresetSuhu', 'kreatif')->call('simpanParameter')->assertDispatched('notify', type: 'error');
        $this->assertSame(0.2, ParameterAi::dari(app(PengaturanAiRepository::class)->ambil())['suhu']);

        Livewire::withQueryParams(['tab' => 'model'])->test(PengaturanAi::class)->call('muatModel', 'ollama')->call('pakaiModel', 'ollama', 'model-asing')->assertNotFound();
        $this->simpan(fn (array $d) => array_replace_recursive($d, ['penyedia' => ['ollama' => ['base_url' => '']]]));
        Livewire::test(PengaturanAi::class)->call('muatModel', 'ollama')->assertSet('daftarModel.ollama.galat', 'Alamat server sekolah belum diisi — atur di tab Penyedia.');
        $this->assertSame(StubPengaturanAiRepository::bawaan()['parameter'], ParameterAi::BAWAAN);
    }
}
