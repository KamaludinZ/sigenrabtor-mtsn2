<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\PengaturanAiRepository;
use App\Contracts\PromptAiRepository;
use App\Contracts\RiwayatPengaturanRepository;
use App\Livewire\Pengaturan\PengaturanAi;
use App\Support\Pengaturan\ParameterAi;
use App\Support\Pengaturan\PromptAi;
use Livewire\Livewire;
use Tests\TestCase;

class ApiModelPromptAiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        session(['stub_role' => 'admin']);
    }

    /** @return list<string> */
    private function riwayat(string $aksi): array
    {
        return array_column(array_filter(app(RiwayatPengaturanRepository::class)->semua(), fn (array $r) => $r['aksi'] === $aksi), 'keterangan');
    }

    public function test_daftar_dan_pakai_model(): void
    {
        $this->getJson(route('pengaturan.ai.model', ['penyedia' => 'ollama']))->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJson(['penyedia' => 'ollama', 'nama' => 'Ollama (server sekolah)', 'model' => null, 'galat' => null])
            ->assertJsonCount(4, 'daftar')
            ->assertJsonPath('daftar.0.nama', 'qwen2.5:7b');
        $this->getJson(route('pengaturan.ai.model', ['penyedia' => 'claude']))->assertOk()->assertJson(['daftar' => [], 'galat' => 'Kunci API Claude belum diisi — atur di tab Penyedia.']);
        $this->getJson(route('pengaturan.ai.model', ['penyedia' => 'mistral']))->assertNotFound();

        $pakai = fn (string $penyedia, array $isian) => $this->putJson(route('pengaturan.ai.model.simpan', ['penyedia' => $penyedia]), $isian);
        $pakai('ollama', ['model' => 'mistral:7b'])->assertUnprocessable()->assertJsonPath('galat.model.0', 'Model mistral:7b tidak ada di daftar model Ollama (server sekolah).');
        $pakai('claude', ['model' => 'claude-sonnet-5-5'])->assertUnprocessable()->assertJsonPath('galat.model.0', 'Kunci API Claude belum diisi — atur di tab Penyedia.');
        $pakai('openai', ['model' => 'gpt uji'])->assertUnprocessable()->assertJsonPath('galat.model.0', 'Nama model hanya huruf, angka, titik, titik dua, garis miring, dan tanda hubung.');

        $pakai('ollama', ['model' => 'qwen2.5:3b', 'versi' => null])->assertOk()
            ->assertJson(['pesan' => 'Model qwen2.5:3b dipakai untuk Ollama (server sekolah).', 'berubah' => true])
            ->assertJsonPath('pengaturan.penyedia.0.model', 'qwen2.5:3b');
        $this->assertSame('Ollama (server sekolah) — Model: belum diisi → qwen2.5:3b', $this->riwayat('ubah_model_ai')[0]);
        $pakai('openai', ['model' => 'gpt-uji'])->assertOk()->assertJsonPath('pengaturan.penyedia.2.model', 'gpt-uji');
        $pakai('ollama', ['model' => 'qwen2.5:7b', 'versi' => null])->assertStatus(409)->assertJsonPath('versi', app(PengaturanAiRepository::class)->ambil()['diperbarui_pada']);
    }

    public function test_parameter_jawaban(): void
    {
        $this->getJson(route('pengaturan.ai.parameter'))->assertOk()
            ->assertJson(['versi' => null, 'parameter' => ParameterAi::BAWAAN, 'bawaan' => ParameterAi::BAWAAN, 'jumlah_versi' => [2, 3]])
            ->assertJsonCount(3, 'preset_suhu')
            ->assertJsonPath('maks_token.2', ['nilai' => 2048, 'label' => 'Panjang (± 5 paragraf)']);

        $simpan = fn (array $isian) => $this->putJson(route('pengaturan.ai.parameter.simpan'), $isian);
        $simpan(['suhu' => 2, 'jumlah_versi' => 3])->assertUnprocessable()->assertJsonStructure(['galat' => ['suhu', 'maks_token']]);
        $simpan(['suhu' => 0.8, 'maks_token' => 2048, 'jumlah_versi' => 2, 'versi' => null])->assertOk()
            ->assertJson(['pesan' => 'Parameter jawaban AI tersimpan.', 'berubah' => true, 'pengaturan' => ['parameter' => ['suhu' => 0.8, 'maks_token' => 2048, 'jumlah_versi' => 2]]]);
        $this->assertSame('Parameter jawaban — Kreativitas: 0,4 → 0,8; Panjang jawaban: Sedang (± 2–3 paragraf) → Panjang (± 5 paragraf); Jumlah versi: 3 versi → 2 versi', $this->riwayat('ubah_model_ai')[0]);
        $simpan(['suhu' => 0.8, 'maks_token' => 2048, 'jumlah_versi' => 2])->assertOk()->assertJson(['berubah' => false]);
    }

    public function test_gaya_bahasa_dan_prompt_per_bagian(): void
    {
        $data = $this->getJson(route('pengaturan.ai.prompt'))->assertOk()
            ->assertJson(['versi' => null, 'gaya_bahasa' => ['teks' => PromptAi::GAYA_BAHASA, 'bawaan' => true], 'batas_aman' => PromptAi::BATAS_AMAN, 'panjang_instruksi' => [20, 1000]])
            ->json();
        $this->assertSame(['nama_cover', 'latar_belakang', 'tujuan', 'sasaran', 'pelaksanaan', 'jadwal'], array_column($data['bagian'], 'bagian'));
        $this->assertSame(['{nama_kegiatan}', '{tahun}'], array_column($data['penanda'], 'penanda'));

        $gaya = fn (array $isian) => $this->putJson(route('pengaturan.ai.prompt.gaya'), $isian);
        $gaya(['gaya_bahasa' => '   '])->assertUnprocessable()->assertJsonPath('galat.gaya_bahasa.0', 'Gaya bahasa 1–300 karakter.');
        $gaya(['gaya_bahasa' => ' Bahasa dinas   yang ringkas. ', 'versi' => null])->assertOk()
            ->assertJson(['pesan' => 'Gaya bahasa baku tersimpan.', 'berubah' => true, 'pengaturan' => ['gaya_bahasa' => ['teks' => 'Bahasa dinas yang ringkas.', 'bawaan' => false]]]);
        $this->assertSame('Bahasa dinas yang ringkas.', PromptAi::gayaBahasa());
        $gaya(['bawaan' => true])->assertOk()->assertJsonPath('pengaturan.gaya_bahasa.bawaan', true);

        $prompt = fn (string $bagian, array $isian) => $this->putJson(route('pengaturan.ai.prompt.simpan', ['bagian' => $bagian]), $isian);
        $prompt('tujuan', ['instruksi' => 'Tulis tujuan {nama_kegiatn} yang diawali kata kerja operasional.'])->assertUnprocessable()
            ->assertJsonPath('galat.instruksi.0', 'Penanda {nama_kegiatn} tidak dikenal — maksud Anda {nama_kegiatan}? Penanda yang boleh: {nama_kegiatan}, {tahun}.');

        $versi = app(PromptAiRepository::class)->ambil()['diperbarui_pada'];
        $prompt('tujuan', ['instruksi' => 'Tulis tujuan {nama_kegiatan} yang diawali kata kerja operasional.', 'gaya_bahasa' => null, 'aktif' => false, 'versi' => $versi])->assertOk()
            ->assertJson(['pesan' => 'Prompt tujuan tersimpan — tombol “Buat dengan AI” bagian ini dinonaktifkan.', 'berubah' => true])
            ->assertJsonPath('pengaturan.bagian.2.aktif', false)
            ->assertJsonPath('pengaturan.bagian.2.bawaan', false);
        $this->assertFalse(PromptAi::aktif('tujuan'));
        $this->assertSame('Tujuan — Instruksi: bawaan → diubah; Status: aktif → nonaktif', $this->riwayat('ubah_prompt_ai')[0]);

        $prompt('tujuan', ['bawaan' => true, 'versi' => $versi])->assertStatus(409)->assertJsonPath('pesan', 'Prompt AI baru diubah di tempat lain — tampilan dimuat ulang, isian Anda tetap. Periksa lalu simpan lagi.');
        $prompt('tujuan', ['bawaan' => true])->assertOk()->assertJsonPath('pengaturan.bagian.2.bawaan', true);
        $this->assertArrayNotHasKey('tujuan', (array) app(PromptAiRepository::class)->ambil()['bagian'], 'Sama dengan bawaan: baris bagian dibuang.');
        $this->putJson(route('pengaturan.ai.prompt.simpan', ['bagian' => 'penutup']), [])->assertNotFound();

        session(['stub_role' => 'leading']);
        $this->getJson(route('pengaturan.ai.prompt'))->assertForbidden();
        $prompt('tujuan', ['bawaan' => true])->assertForbidden();
        $this->putJson(route('pengaturan.ai.parameter.simpan'), [])->assertForbidden();
        $this->putJson(route('pengaturan.ai.model.simpan', ['penyedia' => 'ollama']), [])->assertForbidden();
    }

    public function test_simpan_tanpa_perubahan_di_halaman_tetap_berhasil(): void
    {
        // Batas sama dengan yang tersimpan: tersimpan (notifikasi sukses) tanpa riwayat baru.
        Livewire::withQueryParams(['tab' => 'pemakaian'])->test(PengaturanAi::class)
            ->set('isianBatas', 20)
            ->call('simpanBatas')
            ->assertDispatched('notify', message: 'Batas umum tersimpan: 20 permintaan AI per akun per hari.', type: 'success')
            ->assertDispatched('pengaturan-ai-tersimpan', bagian: 'batas');
        $this->assertSame([], $this->riwayat('ubah_batas_ai'));
    }
}
