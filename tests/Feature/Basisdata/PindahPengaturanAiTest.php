<?php

namespace Tests\Feature\Basisdata;

use App\Repositories\EloquentPengaturanAiRepository;
use App\Support\Basisdata\PindahPengaturanAi;
use App\Support\Stub\StubPengaturanAiRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class PindahPengaturanAiTest extends TestCase
{
    use RefreshDatabase;

    /** @param  array<string, mixed>  $ubah */
    private function tiruan(array $ubah): void
    {
        $data = array_diff_key((new StubPengaturanAiRepository)->ambil(), ['diperbarui_pada' => 1]);
        (new StubPengaturanAiRepository)->simpan(array_replace_recursive($data, $ubah));
    }

    public function test_sinkron_pengaturan_ai_tiruan_ke_database(): void
    {
        $kunci = Crypt::encryptString('sk-ant-kunci-uji-0123456789');
        $this->tiruan([
            'provider_aktif' => 'ollama', 'provider_cadangan' => 'claude',
            'penyedia' => ['ollama' => ['model' => 'qwen2.5:7b'], 'claude' => ['model' => 'claude-sonnet-5-5', 'ada_kunci' => true, 'kunci_akhir' => '6789', 'kunci_terenkripsi' => $kunci]],
            'batas_khusus' => [3 => 40], 'parameter' => ['jumlah_versi' => 2],
        ]);

        $uji = PindahPengaturanAi::jalankan(uji: true);
        $this->assertSame(['status' => 'diganti', 'provider_aktif' => 'ollama', 'provider_cadangan' => 'claude', 'penyedia' => ['claude', 'ollama']], $uji);
        $this->assertSame(0, DB::table('ai_settings')->count(), 'Mode uji tidak menyimpan.');

        $this->artisan('sigenrabtor:pindah-pengaturan-ai')->expectsOutputToContain('Pengaturan AI disinkronkan ke database dan lolos verifikasi.')->assertSuccessful();
        $db = (new EloquentPengaturanAiRepository)->ambil();
        $this->assertSame(PindahPengaturanAi::bentuk((new StubPengaturanAiRepository)->ambil()), PindahPengaturanAi::bentuk($db));
        $this->assertSame([$kunci, 40, 2], [$db['penyedia']['claude']['kunci_terenkripsi'], $db['batas_khusus'][3], $db['parameter']['jumlah_versi']], 'Kunci API disalin apa adanya.');

        $this->assertSame('tetap', PindahPengaturanAi::jalankan()['status'], 'Sudah sama: tidak ditulis ulang.');
        $this->artisan('sigenrabtor:pindah-pengaturan-ai', ['--uji' => true])->expectsOutputToContain('Sudah sama')->assertSuccessful();
    }

    public function test_pengaturan_yang_ditolak_database_dibatalkan_semuanya(): void
    {
        // Penyedia cadangan cloud tanpa kunci API: tiruan menyimpannya, database menolak.
        $this->tiruan(['provider_aktif' => 'ollama', 'provider_cadangan' => 'gemini', 'penyedia' => ['ollama' => ['model' => 'qwen2.5:7b'], 'gemini' => ['model' => 'gemini-uji']]]);

        try {
            PindahPengaturanAi::jalankan();
            $this->fail('Penyedia yang belum siap harus ditolak.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Penyedia AI gemini belum siap dipakai: model & kunci API belum diatur.', $e->getMessage());
        }
        $this->assertSame([0, 0], [DB::table('ai_settings')->count(), DB::table('ai_provider_settings')->count()]);
        $this->artisan('sigenrabtor:pindah-pengaturan-ai')->expectsOutputToContain('Database menolak pengaturan AI tiruan')->assertFailed();
    }
}
