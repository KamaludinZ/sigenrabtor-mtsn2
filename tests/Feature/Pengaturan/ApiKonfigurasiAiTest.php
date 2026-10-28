<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\PengaturanAiRepository;
use App\Contracts\PromptAiRepository;
use App\Support\Kegiatan\PayloadAi;
use App\Support\Pengaturan\PromptAi;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class ApiKonfigurasiAiTest extends TestCase
{
    private const KUNCI = 'sk-ant-kunci-uji-0123456789';

    /** @param  array<string, mixed>  $ubah */
    private function atur(array $ubah): void
    {
        $data = array_diff_key(app(PengaturanAiRepository::class)->ambil(), ['diperbarui_pada' => 1]);
        app(PengaturanAiRepository::class)->simpan(array_replace_recursive($data, $ubah));
    }

    public function test_konfigurasi_lengkap_untuk_admin_tanpa_kunci_api(): void
    {
        session(['stub_role' => 'admin']);
        $data = $this->getJson(route('pengaturan.ai.data'))->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonStructure(['versi', 'penyedia' => ['versi', 'provider_aktif', 'penyedia'], 'parameter' => ['parameter', 'preset_suhu'], 'prompt' => ['gaya_bahasa', 'bagian'], 'batas' => ['batas_umum', 'akun', 'kapasitas'], 'simulasi', 'data_dikirim' => ['dikirim', 'tidak_pernah', 'disamarkan'], 'terakhir_disimpan', 'tautan' => ['halaman', 'riwayat']])
            ->assertJson(['versi' => null, 'simulasi' => ['aktif' => false], 'terakhir_disimpan' => null, 'data_dikirim' => ['dikirim' => PayloadAi::DIKIRIM, 'tidak_pernah' => PayloadAi::TIDAK_PERNAH], 'tautan' => ['halaman' => route('pengaturan.ai.index')]])
            ->json();
        $this->assertSame([4, 6, 20], [count($data['penyedia']['penyedia']), count($data['prompt']['bagian']), $data['batas']['batas_umum']]);
        $this->assertContains('NIK', $data['data_dikirim']['disamarkan']);

        $this->putJson(route('pengaturan.ai.penyedia.simpan', ['penyedia' => 'claude']), ['model' => 'claude-sonnet-5-5', 'kunci' => self::KUNCI, 'peran' => 'aktif'])->assertOk();
        $this->putJson(route('pengaturan.ai.parameter.simpan'), ['suhu' => 0.2, 'maks_token' => 512, 'jumlah_versi' => 2])->assertOk();
        $respons = $this->getJson(route('pengaturan.ai.data'))->assertOk()
            ->assertJson(['penyedia' => ['provider_aktif' => 'claude'], 'parameter' => ['parameter' => ['jumlah_versi' => 2]], 'terakhir_disimpan' => ['label' => 'Ubah model & parameter AI']]);
        $this->assertSame(app(PengaturanAiRepository::class)->ambil()['diperbarui_pada'], $respons->json('versi'));
        $this->assertStringNotContainsString(self::KUNCI, $respons->getContent());
        $this->assertStringNotContainsString('kunci_terenkripsi', $respons->getContent());
        $this->assertStringNotContainsString(app(PengaturanAiRepository::class)->ambil()['penyedia']['claude']['kunci_terenkripsi'], $respons->getContent());

        $this->atur(['simulasi_gagal' => ['jenis' => 'waktu_habis', 'cakupan' => 'semua']]);
        $this->getJson(route('pengaturan.ai.data'))->assertJson(['simulasi' => ['aktif' => true, 'jenis' => 'waktu_habis', 'judul' => 'Waktu tunggu habis', 'cakupan' => 'semua']]);

        session(['stub_role' => 'leading']);
        $this->getJson(route('pengaturan.ai.data'))->assertForbidden();
    }

    public function test_konfigurasi_berlaku_untuk_semua_pengguna(): void
    {
        $respons = $this->getJson(route('ai.konfigurasi'))->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJson([
                'penyedia' => ['aktif' => null, 'nama' => 'AI tiruan', 'jenis' => null, 'cadangan' => null, 'tiruan' => true],
                'jumlah_versi' => 3, 'batas_aman' => PromptAi::BATAS_AMAN, 'simulasi' => null,
                'kuota' => ['batas' => 20, 'sisa' => 20, 'tingkat' => 'aman'],
            ])
            ->assertJsonPath('bagian.2', ['bagian' => 'tujuan', 'judul' => 'Tujuan', 'tercetak' => 'Bagian 3A · Tujuan', 'aktif' => true]);
        $this->assertCount(6, $respons->json('bagian'));

        $this->atur([
            'provider_aktif' => 'ollama', 'provider_cadangan' => 'claude', 'parameter' => ['jumlah_versi' => 2],
            'penyedia' => ['ollama' => ['model' => 'qwen2.5:7b'], 'claude' => ['model' => 'claude-sonnet-5-5', 'ada_kunci' => true, 'kunci_akhir' => '6789', 'kunci_terenkripsi' => Crypt::encryptString(self::KUNCI)]],
            'simulasi_gagal' => ['jenis' => 'tidak_terhubung', 'cakupan' => 'aktif'],
        ]);
        app(PromptAiRepository::class)->simpan(['gaya_bahasa' => null, 'bagian' => ['tujuan' => ['instruksi' => PromptAi::BAWAAN['tujuan'], 'gaya_bahasa' => null, 'aktif' => false]]]);

        $respons = $this->getJson(route('ai.konfigurasi'))->assertOk()
            ->assertJson(['penyedia' => ['aktif' => 'ollama', 'nama' => 'Ollama (server sekolah)', 'jenis' => 'lokal', 'cadangan' => 'claude', 'nama_cadangan' => 'Claude (Anthropic)', 'tiruan' => false], 'jumlah_versi' => 2, 'simulasi' => null])
            ->assertJsonPath('bagian.2.aktif', false);
        foreach (['base_url', 'qwen2.5:7b', 'kunci', '192.168'] as $rahasia) {
            $this->assertStringNotContainsString($rahasia, $respons->getContent(), "Konfigurasi pengguna tanpa {$rahasia}.");
        }

        session(['stub_role' => 'admin']);
        $this->getJson(route('ai.konfigurasi'))->assertOk()->assertJson(['simulasi' => ['aktif' => true, 'jenis' => 'tidak_terhubung', 'cakupan' => 'aktif']]);
    }
}
