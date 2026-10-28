<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\PengaturanAiRepository;
use App\Contracts\RiwayatPengaturanRepository;
use App\Support\Stub\StubPengaturanAiRepository;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;
use Tests\TestCase;

class ApiPenyediaAiTest extends TestCase
{
    private const KUNCI = 'kunci-tanpa-awalan-0123456789';

    protected function setUp(): void
    {
        parent::setUp();
        session(['stub_role' => 'admin']);
    }

    private function versi(): ?string
    {
        return app(PengaturanAiRepository::class)->ambil()['diperbarui_pada'];
    }

    /** @return list<string> */
    private function riwayat(): array
    {
        return array_column(array_filter(app(RiwayatPengaturanRepository::class)->semua(), fn (array $r) => $r['aksi'] === 'ubah_penyedia_ai'), 'keterangan');
    }

    public function test_data_penyedia_tanpa_kunci_api(): void
    {
        $data = $this->getJson(route('pengaturan.ai.penyedia'))->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJson(['versi' => null, 'provider_aktif' => null, 'provider_cadangan' => null])
            ->assertJsonPath('penyedia.0', ['kunci' => 'ollama', 'nama' => 'Ollama (server sekolah)', 'jenis' => 'lokal', 'butuh_kunci' => false, 'base_url' => 'http://localhost:11434', 'alamat_bawaan' => 'http://localhost:11434', 'model' => null, 'batas_waktu' => 60, 'ada_kunci' => false, 'kunci_akhir' => null, 'peran' => 'tidak', 'siap' => false, 'kurang' => ['model']])
            ->json();
        $this->assertSame(['ollama', 'claude', 'openai', 'gemini'], array_column($data['penyedia'], 'kunci'));
        $this->assertSame('https://api.anthropic.com/v1', $data['penyedia'][1]['alamat_bawaan']);

        session(['stub_role' => 'leading']);
        $this->getJson(route('pengaturan.ai.penyedia'))->assertForbidden();
        $this->putJson(route('pengaturan.ai.penyedia.simpan', ['penyedia' => 'ollama']), [])->assertForbidden();
        $this->putJson(route('pengaturan.ai.pilihan'), [])->assertForbidden();
    }

    public function test_simpan_server_sekolah_dengan_versi(): void
    {
        $simpan = fn (array $isian) => $this->putJson(route('pengaturan.ai.penyedia.simpan', ['penyedia' => 'ollama']), $isian);

        $simpan(['base_url' => 'localhost', 'model' => 'qwen2.5:7b', 'batas_waktu' => 45, 'peran' => 'aktif', 'versi' => null])
            ->assertUnprocessable()->assertJsonStructure(['pesan', 'galat' => ['base_url']]);
        $this->assertNull($this->versi(), 'Isian salah tidak menyimpan.');

        $hasil = $simpan(['base_url' => 'http://192.168.1.10:11434/', 'model' => 'qwen2.5:7b', 'batas_waktu' => 45, 'peran' => 'aktif', 'versi' => null])->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJson(['pesan' => 'Konfigurasi server sekolah tersimpan.', 'berubah' => true, 'pengaturan' => ['provider_aktif' => 'ollama', 'nama_aktif' => 'Ollama (server sekolah)']])
            ->assertJsonPath('pengaturan.penyedia.0.base_url', 'http://192.168.1.10:11434')
            ->assertJsonPath('pengaturan.penyedia.0.siap', true)
            ->json();
        $this->assertSame($this->versi(), $hasil['pengaturan']['versi']);
        $this->assertStringStartsWith('Server sekolah (Ollama) — ', $this->riwayat()[0]);

        // Versi lama (sebelum simpan) ditolak; tanpa versi tidak diperiksa.
        $simpan(['base_url' => 'http://192.168.1.10:11434', 'model' => 'qwen2.5:3b', 'batas_waktu' => 45, 'peran' => 'aktif', 'versi' => null])
            ->assertStatus(409)->assertJson(['versi' => $this->versi()])->assertJsonPath('pesan', 'Pengaturan AI baru diubah di tempat lain — tampilan dimuat ulang, isian Anda tetap. Periksa lalu simpan lagi.');
        $simpan(['base_url' => 'http://192.168.1.10:11434', 'model' => 'qwen2.5:7b', 'batas_waktu' => 45, 'peran' => 'aktif'])->assertOk()->assertJson(['berubah' => false]);
        $this->assertCount(1, $this->riwayat(), 'Tanpa perubahan: riwayat tidak bertambah.');

        $this->putJson(route('pengaturan.ai.penyedia.simpan', ['penyedia' => 'mistral']), [])->assertNotFound();
    }

    public function test_simpan_api_cloud_dengan_kunci_dan_peringatan(): void
    {
        $simpan = fn (array $isian) => $this->putJson(route('pengaturan.ai.penyedia.simpan', ['penyedia' => 'claude']), $isian);

        $simpan(['model' => 'claude-sonnet-5-5', 'kunci' => self::KUNCI, 'peran' => 'aktif', 'versi' => null])->assertUnprocessable()
            ->assertJson(['perlu_konfirmasi' => true])->assertJsonStructure(['pesan', 'peringatan' => [0]]);
        $this->assertNull($this->versi(), 'Peringatan kunci: belum tersimpan.');

        $respons = $simpan(['model' => 'claude-sonnet-5-5', 'kunci' => self::KUNCI, 'peran' => 'aktif', 'tetap_simpan' => true, 'versi' => null])->assertOk()
            ->assertJson(['pesan' => 'Konfigurasi Claude (Anthropic) tersimpan.', 'pengaturan' => ['provider_aktif' => 'claude']])
            ->assertJsonPath('pengaturan.penyedia.1.ada_kunci', true)
            ->assertJsonPath('pengaturan.penyedia.1.kunci_akhir', '6789');
        $this->assertStringNotContainsString(self::KUNCI, $respons->getContent());
        $this->assertStringNotContainsString('kunci_terenkripsi', $respons->getContent());
        $tersimpan = app(PengaturanAiRepository::class)->ambil()['penyedia']['claude'];
        $this->assertSame(self::KUNCI, Crypt::decryptString($tersimpan['kunci_terenkripsi']));
        $this->assertStringContainsString('Kunci API: diisi', $this->riwayat()[0]);
        $this->assertStringNotContainsString(self::KUNCI, $this->riwayat()[0]);

        $simpan(['model' => 'claude-sonnet-5-5', 'hapus_kunci' => true, 'peran' => 'aktif'])->assertUnprocessable()->assertJsonStructure(['galat' => ['kunci']]);
        $this->putJson(route('pengaturan.ai.penyedia.simpan', ['penyedia' => 'openai']), ['model' => 'gpt uji', 'peran' => 'tidak'])->assertUnprocessable()->assertJsonStructure(['galat' => ['model']]);
    }

    public function test_pilih_penyedia_aktif_dan_cadangan(): void
    {
        $this->putJson(route('pengaturan.ai.penyedia.simpan', ['penyedia' => 'ollama']), ['base_url' => 'http://localhost:11434', 'model' => 'qwen2.5:7b', 'batas_waktu' => 60, 'peran' => 'tidak'])->assertOk();
        $this->putJson(route('pengaturan.ai.penyedia.simpan', ['penyedia' => 'claude']), ['model' => 'claude-sonnet-5-5', 'kunci' => 'sk-ant-'.self::KUNCI, 'peran' => 'tidak'])->assertOk();
        $pilih = fn (array $isian) => $this->putJson(route('pengaturan.ai.pilihan'), $isian);

        $pilih(['aktif' => 'gemini', 'cadangan' => null])->assertUnprocessable()->assertJsonStructure(['galat' => ['aktif']]);
        $pilih(['aktif' => 'claude', 'cadangan' => 'claude'])->assertUnprocessable()->assertJsonStructure(['galat' => ['cadangan']]);
        $pilih(['aktif' => 'claude', 'cadangan' => 'ollama', 'versi' => $this->versi()])->assertOk()
            ->assertJson(['pesan' => 'Penyedia AI tersimpan: Claude (Anthropic), cadangan Ollama (server sekolah).', 'pengaturan' => ['provider_aktif' => 'claude', 'provider_cadangan' => 'ollama']]);
        $this->assertSame('Penyedia aktif: belum dipilih → Claude (Anthropic); Penyedia cadangan: belum dipilih → Ollama (server sekolah)', $this->riwayat()[0]);
        $pilih(['aktif' => '', 'cadangan' => ''])->assertOk()->assertJsonPath('pesan', 'Tanpa penyedia AI — tombol “Buat dengan AI” memakai saran contoh (tiruan).');
        $pilih(['aktif' => 'claude', 'versi' => '2020-01-01T00:00:00.000000+07:00'])->assertStatus(409);
    }

    public function test_gagal_simpan_dilaporkan(): void
    {
        $this->app->instance(PengaturanAiRepository::class, new class extends StubPengaturanAiRepository
        {
            public function simpan(array $data): void
            {
                throw new RuntimeException('Pengaturan AI gagal disimpan. Coba lagi.');
            }
        });

        $this->putJson(route('pengaturan.ai.pilihan'), ['aktif' => null])->assertStatus(503)->assertJsonPath('pesan', 'Pengaturan AI gagal disimpan. Coba lagi.');
        $this->assertSame([], $this->riwayat(), 'Gagal simpan: riwayat tidak dicatat.');
    }
}
