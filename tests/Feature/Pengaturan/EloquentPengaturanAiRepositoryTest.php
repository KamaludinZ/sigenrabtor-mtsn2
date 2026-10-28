<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\KlienLlm;
use App\Contracts\PengaturanAiRepository;
use App\Repositories\EloquentPengaturanAiRepository;
use App\Support\Basisdata\PindahPengaturanAi;
use App\Support\Pengaturan\ParameterAi;
use App\Support\Stub\StubPengaturan;
use App\Support\Stub\StubPengaturanAiRepository;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class EloquentPengaturanAiRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private const KUNCI = 'sk-ant-kunci-uji-0123456789';

    /** @return array<string, array{bool}> */
    public static function penyimpanan(): array
    {
        return ['tiruan' => [false], 'database' => [true]];
    }

    private function siapkan(bool $database): PengaturanAiRepository
    {
        if ($database) {
            $this->app->singleton(PengaturanAiRepository::class, EloquentPengaturanAiRepository::class);
        }

        return app(PengaturanAiRepository::class);
    }

    /** Kesiapan penyedia (constraint trigger tertunda) diperiksa seketika, seperti saat commit. */
    private function commit(bool $database): void
    {
        if ($database) {
            DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        }
    }

    /** @return array<string, mixed> */
    private function lengkap(string $kunciTerenkripsi): array
    {
        return [
            'provider_aktif' => 'ollama',
            'provider_cadangan' => 'claude',
            'penyedia' => [
                'ollama' => ['base_url' => 'http://192.168.1.10:11434', 'model' => 'qwen2.5:7b', 'batas_waktu' => 45, 'ada_kunci' => false],
                'claude' => ['ada_kunci' => true, 'kunci_akhir' => '6789', 'kunci_terenkripsi' => $kunciTerenkripsi, 'model' => 'claude-sonnet-5-5', 'base_url' => ''],
            ],
            'parameter' => ['suhu' => 0.2, 'maks_token' => 512, 'jumlah_versi' => 2],
            'batas_per_user_per_hari' => 30,
            'batas_khusus' => [3 => 50, 4 => 5],
            'simulasi_gagal' => ['jenis' => 'waktu_habis', 'cakupan' => 'aktif'],
        ];
    }

    #[DataProvider('penyimpanan')]
    public function test_ambil_dan_simpan_sama_di_tiruan_dan_database(bool $database): void
    {
        $repo = $this->siapkan($database);
        $this->assertSame(StubPengaturanAiRepository::bawaan(), $repo->ambil(), 'Belum ada pengaturan = bawaan.');

        $kunci = Crypt::encryptString(self::KUNCI);
        $repo->simpan($this->lengkap($kunci));
        $this->commit($database);
        $ai = $repo->ambil();
        $this->assertSame([
            'provider_aktif' => 'ollama',
            'provider_cadangan' => 'claude',
            'penyedia' => [
                'claude' => ['base_url' => '', 'model' => 'claude-sonnet-5-5', 'batas_waktu' => null, 'ada_kunci' => true, 'kunci_akhir' => '6789', 'kunci_terenkripsi' => $kunci],
                'ollama' => ['base_url' => 'http://192.168.1.10:11434', 'model' => 'qwen2.5:7b', 'batas_waktu' => 45, 'ada_kunci' => false, 'kunci_akhir' => null, 'kunci_terenkripsi' => null],
            ],
            'parameter' => ['suhu' => 0.2, 'maks_token' => 512, 'jumlah_versi' => 2],
            'batas_per_user_per_hari' => 30,
            'batas_khusus' => [3 => 50, 4 => 5],
            'simulasi_gagal' => ['jenis' => 'waktu_habis', 'cakupan' => 'aktif'],
        ], PindahPengaturanAi::bentuk($ai));
        $this->assertMatchesRegularExpression('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d\.\d{6}\+07:00$/', $ai['diperbarui_pada']);
        $this->assertSame(['ollama', 'qwen2.5:7b', 'http://192.168.1.10:11434', false], [StubPengaturan::pengaturanAi()['provider_aktif'], StubPengaturan::pengaturanAi()['model'], StubPengaturan::pengaturanAi()['base_url'], StubPengaturan::pengaturanAi()['ada_kunci']]);
        $this->assertSame(['suhu' => 0.2, 'maks_token' => 512, 'jumlah_versi' => 2], ParameterAi::dari($ai));

        // Claude tidak dipakai lagi & dibuang dari konfigurasi; kunci dihapus; versi berganti.
        $lagi = $this->lengkap($kunci);
        $lagi['provider_cadangan'] = null;
        unset($lagi['penyedia']['claude']);
        $lagi['batas_khusus'] = [];
        $lagi['simulasi_gagal'] = null;
        usleep(1000);
        $repo->simpan($lagi);
        $this->commit($database);
        $sesudah = $repo->ambil();
        $this->assertSame([null, ['ollama'], [], null], [$sesudah['provider_cadangan'], array_keys($sesudah['penyedia']), $sesudah['batas_khusus'], $sesudah['simulasi_gagal']]);
        $this->assertGreaterThan($ai['diperbarui_pada'], $sesudah['diperbarui_pada']);
    }

    public function test_klien_llm_membaca_penyedia_dan_kunci_dari_database(): void
    {
        $repo = $this->siapkan(true);
        $repo->simpan(['provider_aktif' => 'claude', 'provider_cadangan' => null, 'penyedia' => [
            'claude' => ['model' => 'claude-sonnet-5-5', 'base_url' => '', 'ada_kunci' => true, 'kunci_akhir' => '6789', 'kunci_terenkripsi' => Crypt::encryptString(self::KUNCI), 'batas_waktu' => 20],
        ]] + StubPengaturanAiRepository::bawaan());
        $this->commit(true);
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => '{"versi":[]}']]])]);

        $this->assertSame('{"versi":[]}', app(KlienLlm::class)->tanya('claude', 'Sistem JSON', 'Pesan', ['suhu' => 0.4, 'maks_token' => 512]));
        Http::assertSent(fn (Request $r) => $r->header('x-api-key') === [self::KUNCI] && $r['model'] === 'claude-sonnet-5-5');
    }

    public function test_database_menolak_pengaturan_yang_tidak_sah(): void
    {
        $repo = $this->siapkan(true);
        try {
            $repo->simpan(['parameter' => ['suhu' => 5]] + StubPengaturanAiRepository::bawaan());
            $this->fail('Parameter di luar aturan harus ditolak.');
        } catch (RuntimeException $e) {
            $this->assertSame('Pengaturan AI gagal disimpan. Coba lagi.', $e->getMessage());
            $this->assertInstanceOf(QueryException::class, $e->getPrevious());
        }

        // Penyedia aktif tanpa model: lolos sampai commit, lalu ditolak.
        $repo->simpan(['provider_aktif' => 'ollama'] + StubPengaturanAiRepository::bawaan());
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Penyedia AI ollama belum siap dipakai');
        $this->commit(true);
    }
}
