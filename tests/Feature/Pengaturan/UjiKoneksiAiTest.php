<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\PengaturanAiRepository;
use App\Livewire\Pengaturan\PengaturanAi;
use App\Support\Kegiatan\BatasAi;
use App\Support\Kegiatan\KesehatanPenyediaAi;
use App\Support\Pengaturan\KelolaPenyediaAi;
use App\Support\Pengaturan\UjiKoneksiAi;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class UjiKoneksiAiTest extends TestCase
{
    private const KUNCI = 'sk-ant-api03-kunci-fiktif-uji-WXYZ';

    private const ADMIN = ['id' => 2, 'name' => 'Admin', 'role' => 'admin'];

    protected function setUp(): void
    {
        parent::setUp();
        session(['stub_role' => 'admin']);
        Sleep::fake();
    }

    /** @param  array<string, mixed>  $ubah */
    private function atur(array $ubah): void
    {
        $data = array_diff_key(app(PengaturanAiRepository::class)->ambil(), ['diperbarui_pada' => 1]);
        app(PengaturanAiRepository::class)->simpan(array_replace_recursive($data, $ubah));
    }

    private function claude(string $model = 'claude-opus-5-5'): void
    {
        $this->atur(['provider_aktif' => 'claude', 'penyedia' => ['claude' => ['model' => $model, 'ada_kunci' => true, 'kunci_akhir' => 'WXYZ', 'kunci_terenkripsi' => Crypt::encryptString(self::KUNCI)]]]);
    }

    public function test_konfigurasi_belum_lengkap_tidak_menghubungi_penyedia(): void
    {
        Http::fake();
        $hasil = UjiKoneksiAi::uji('claude', self::ADMIN);

        $this->assertSame([false, 'Konfigurasi belum lengkap', 'Lengkapi dulu: model, kunci API.', ['model', 'kunci API']], [$hasil['berhasil'], $hasil['judul'], $hasil['pesan'], $hasil['kurang']]);
        $this->assertNull(UjiKoneksiAi::terakhir('claude'), 'Bukan hasil koneksi: tidak disimpan.');
        Http::assertNothingSent();
    }

    public function test_berhasil_disimpan_sampai_pengaturan_berubah_dan_memulihkan_failover(): void
    {
        Log::spy();
        $this->claude();
        KesehatanPenyediaAi::catatGagal('claude', 'tidak_terhubung');
        $this->assertNotNull(KesehatanPenyediaAi::jeda('claude'));
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'thinking', 'thinking' => ''], ['type' => 'text', 'text' => '{"status":"ok"}']], 'stop_reason' => 'end_turn'])]);
        $sisa = BatasAi::sisa(StubData::currentUser());

        $hasil = UjiKoneksiAi::uji('claude', self::ADMIN);

        $this->assertSame([true, 'claude', 'Claude (Anthropic)', 'claude-opus-5-5', 'Terhubung', null], [$hasil['berhasil'], $hasil['penyedia'], $hasil['nama'], $hasil['model'], $hasil['judul'], $hasil['jenis']]);
        $this->assertMatchesRegularExpression('/^Model claude-opus-5-5 menjawab dalam \d+,\d detik\.$/', $hasil['pesan']);
        $this->assertIsInt($hasil['waktu_ms']);
        $this->assertSame($hasil, UjiKoneksiAi::terakhir('claude'));
        $this->assertSame($hasil, collect(KelolaPenyediaAi::data()['penyedia'])->firstWhere('kunci', 'claude')['uji']);
        $this->assertNull(KesehatanPenyediaAi::jeda('claude'), 'Koneksi yang berhasil memulihkan penyedia.');
        $this->assertSame($sisa, BatasAi::sisa(StubData::currentUser()), 'Uji koneksi tidak memakai kuota harian.');
        // Pesan uji tanpa data kegiatan; jalur sama dengan permintaan pengguna (tanpa temperature untuk Opus 5.5).
        Http::assertSent(fn (Request $r) => $r->url() === 'https://api.anthropic.com/v1/messages' && $r['system'] === UjiKoneksiAi::SISTEM
            && $r['messages'][0]['content'] === UjiKoneksiAi::PESAN && ! isset($r['temperature']));
        Log::shouldHaveReceived('info')->withArgs(fn (string $pesan, array $konteks) => $pesan === 'Uji koneksi penyedia AI.' && $konteks['berhasil'] === true && ! str_contains(json_encode($konteks), self::KUNCI))->once();

        // Pengaturan berubah → kembali "belum diuji".
        $this->atur(['penyedia' => ['claude' => ['model' => 'claude-sonnet-5-5']]]);
        $this->assertNull(UjiKoneksiAi::terakhir('claude'));
    }

    public function test_gagal_berjenis_dengan_saran_admin_tanpa_kunci(): void
    {
        $this->claude();
        $this->atur(['simulasi_gagal' => ['jenis' => 'kuota_penyedia', 'cakupan' => 'semua']]);
        Http::fake(['api.anthropic.com/*' => Http::response(['type' => 'error', 'error' => ['type' => 'authentication_error', 'message' => 'invalid x-api-key '.self::KUNCI]], 401)]);

        $hasil = UjiKoneksiAi::uji('claude', self::ADMIN);

        $this->assertSame([false, 'kunci_ditolak', 'Kunci API ditolak', 'Kunci API tidak sah atau kedaluwarsa — ganti kunci di tab Penyedia.'], [$hasil['berhasil'], $hasil['jenis'], $hasil['judul'], $hasil['pesan']], 'Simulasi kegagalan tidak memengaruhi uji koneksi sungguhan.');
        $this->assertStringNotContainsString(self::KUNCI, json_encode($hasil));
        $this->assertSame($hasil, UjiKoneksiAi::terakhir('claude'));
    }

    public function test_dibatasi_per_admin_dan_hanya_admin(): void
    {
        $this->claude();
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => '{"status":"ok"}']]])]);
        foreach (range(1, UjiKoneksiAi::MAKS_PER_MENIT) as $_) {
            $this->assertTrue(UjiKoneksiAi::uji('claude', self::ADMIN)['berhasil']);
        }
        try {
            UjiKoneksiAi::uji('claude', self::ADMIN);
            $this->fail('Terlalu sering.');
        } catch (ValidationException $e) {
            $this->assertStringStartsWith('Uji koneksi terlalu sering — coba lagi dalam ', $e->errors()['uji'][0]);
        }
        $this->assertTrue(UjiKoneksiAi::uji('claude', ['id' => 9] + self::ADMIN)['berhasil'], 'Batas per admin.');

        $this->expectException(AuthorizationException::class);
        UjiKoneksiAi::uji('claude', ['id' => 1, 'role' => 'leading']);
    }

    public function test_endpoint_dan_tombol_di_halaman_pengaturan_ai(): void
    {
        $this->claude();
        Http::fake(['api.anthropic.com/*' => Http::sequence()
            ->push(['content' => [['type' => 'text', 'text' => '{"status":"ok"}']]])
            ->push(['type' => 'error', 'error' => ['type' => 'not_found_error', 'message' => 'model: claude-opus-5-5']], 404)
            ->whenEmpty(Http::response(['content' => [['type' => 'text', 'text' => '{"status":"ok"}']]]))]);

        $this->postJson(route('pengaturan.ai.uji', 'claude'))->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('hasil.berhasil', true)->assertJsonPath('hasil.model', 'claude-opus-5-5');
        $this->postJson(route('pengaturan.ai.uji', 'mistral'))->assertNotFound();

        Livewire::test(PengaturanAi::class)
            ->assertSeeHtml('data-testid="hasil-uji-aktif" data-berhasil="1"')
            ->assertSeeHtml('data-testid="uji-koneksi-claude"')
            ->call('ujiKoneksi', 'claude')
            ->assertDispatched('notify', message: 'Uji koneksi Claude (Anthropic) gagal: Model tidak ditemukan.', type: 'error')
            ->assertSeeHtml('data-testid="hasil-uji-aktif" data-berhasil="0" data-jenis="model_tidak_ada"')
            ->assertSee('Model belum diunduh di server');

        // Terlalu sering → 429 + Retry-After.
        while (UjiKoneksiAi::tunggu(StubData::currentUser()) === 0) {
            UjiKoneksiAi::uji('claude', StubData::currentUser());
        }
        $this->postJson(route('pengaturan.ai.uji', 'claude'))->assertStatus(429)->assertHeader('Retry-After')->assertJsonStructure(['pesan']);

        session(['stub_role' => 'leading']);
        $this->postJson(route('pengaturan.ai.uji', 'claude'))->assertForbidden();
    }
}
