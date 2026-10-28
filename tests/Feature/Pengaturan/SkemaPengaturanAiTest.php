<?php

namespace Tests\Feature\Pengaturan;

use App\Models\PemakaianAi;
use App\Models\PengaturanAi;
use App\Models\PengaturanPenyediaAi;
use App\Models\TemplatePrompt;
use App\Support\Kegiatan\KegagalanAi;
use App\Support\Pengaturan\ParameterAi;
use App\Support\Pengaturan\PromptAi;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\Support\RujukanDb;
use Tests\TestCase;

class SkemaPengaturanAiTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRASI = 'database/migrations/2026_10_28_000037_tighten_pengaturan_ai_tables.php';

    private function ditolak(callable $aksi, string $catatan): QueryException
    {
        try {
            DB::transaction($aksi);
        } catch (QueryException $e) {
            $this->addToAssertionCount(1);

            return $e;
        }
        $this->fail("Seharusnya ditolak database: {$catatan}");
    }

    /** @return list<string> */
    private function constraintAi(): array
    {
        return DB::table('pg_constraint')->whereIn('conname', ['ai_settings_parameter_check', 'ai_settings_batas_khusus_check', 'ai_settings_simulasi_check', 'ai_provider_settings_isian_check', 'prompt_templates_isian_check'])->orderBy('conname')->pluck('conname')->all();
    }

    public function test_pengaturan_yang_disimpan_aplikasi_muat_di_tabel(): void
    {
        RujukanDb::segera();
        PengaturanPenyediaAi::create(['penyedia' => 'ollama', 'base_url' => 'http://192.168.1.10:11434', 'model' => 'qwen2.5:7b', 'batas_waktu' => 60]);
        PengaturanPenyediaAi::create(['penyedia' => 'claude', 'model' => 'claude-sonnet-5-5', 'kunci_terenkripsi' => Crypt::encryptString('sk-ant-kunci-uji-0123456789'), 'kunci_akhir' => '6789', 'batas_waktu' => 30]);
        PengaturanPenyediaAi::create(['penyedia' => 'openai', 'base_url' => 'https://gateway.madrasah.sch.id/v1', 'model' => 'gpt-uji']);
        PengaturanPenyediaAi::create(['penyedia' => 'gemini', 'model' => 'models/gemini-uji']);
        $ai = PengaturanAi::create(['id' => 1, 'provider_aktif' => 'ollama', 'provider_cadangan' => 'claude', 'parameter' => ParameterAi::BAWAAN, 'batas_khusus' => ['3' => 50, '4' => 1, '5' => 200], 'simulasi_gagal' => null]);

        foreach (ParameterAi::PRESET_SUHU as [$suhu]) {
            foreach (array_keys(ParameterAi::MAKS_TOKEN) as $token) {
                foreach (ParameterAi::JUMLAH_VERSI as $versi) {
                    $ai->update(['parameter' => ['suhu' => $suhu, 'maks_token' => $token, 'jumlah_versi' => $versi]]);
                }
            }
        }
        $ai->update(['parameter' => ['suhu' => 0, 'maks_token' => 512, 'jumlah_versi' => 2]]);
        $ai->update(['parameter' => []]);
        foreach (array_keys(KegagalanAi::JENIS) as $jenis) {
            foreach (['aktif', 'semua'] as $cakupan) {
                $ai->update(['simulasi_gagal' => ['jenis' => $jenis, 'cakupan' => $cakupan]]);
            }
        }
        foreach (PromptAi::BAWAAN as $bagian => $instruksi) {
            TemplatePrompt::create(['bagian' => $bagian, 'instruksi' => $instruksi, 'gaya_bahasa' => null, 'is_active' => $bagian !== 'jadwal']);
        }
        TemplatePrompt::create(['bagian' => 'umum', 'gaya_bahasa' => PromptAi::GAYA_BAHASA]);
        PemakaianAi::create(['user_id' => 1, 'tanggal' => '2026-10-28', 'fitur' => 'usul_jadwal', 'jumlah' => 3]);

        $this->assertSame(['ollama', 'claude', [], ['3' => 50, '4' => 1, '5' => 200]], [$ai->fresh()->provider_aktif, $ai->fresh()->provider_cadangan, $ai->fresh()->parameter, $ai->fresh()->batas_khusus]);
        $this->assertSame(['jenis' => 'jawaban_rusak', 'cakupan' => 'semua'], $ai->fresh()->simulasi_gagal);
        $this->assertArrayNotHasKey('kunci_terenkripsi', PengaturanPenyediaAi::find('claude')->toArray(), 'Kunci terenkripsi tidak ikut diserialkan.');
        $this->assertSame([7, false], [TemplatePrompt::count(), TemplatePrompt::find('jadwal')->is_active]);
        $this->assertSame('2026-10-28', PemakaianAi::first()->tanggal->format('Y-m-d'));
    }

    public function test_isian_di_luar_aturan_aplikasi_ditolak(): void
    {
        RujukanDb::siapkan();
        DB::table('ai_settings')->insert(['id' => 1]);
        $ubah = fn (array $isi) => fn () => DB::table('ai_settings')->update(array_map(fn ($v) => is_array($v) || is_string($v) ? json_encode($v) : $v, $isi));

        foreach ([
            [['suhu' => 1.5], 'suhu > 1'], [['suhu' => -0.1], 'suhu < 0'], [['suhu' => '0.4'], 'suhu berupa teks'],
            [['maks_token' => 999], 'panjang jawaban di luar pilihan'], [['jumlah_versi' => 4], 'versi > 3'], [['jumlah_versi' => 1], 'versi < 2'],
            [['temperature' => 0.4], 'kunci asing'],
        ] as [$parameter, $catatan]) {
            $this->ditolak($ubah(['parameter' => $parameter]), $catatan);
        }
        foreach ([[['abc' => 5], 'kunci bukan id akun'], [['0' => 5], 'id 0'], [['3' => 0], 'batas 0'], [['3' => 201], 'batas > 200'], [['3' => 2.5], 'batas pecahan'], [['3' => '5'], 'batas teks'], [[5, 6], 'bukan objek']] as [$batas, $catatan]) {
            $this->ditolak($ubah(['batas_khusus' => $batas]), $catatan);
        }
        foreach ([[['jenis' => 'acak', 'cakupan' => 'aktif'], 'jenis asing'], [['jenis' => 'waktu_habis'], 'tanpa cakupan'], [['jenis' => 'waktu_habis', 'cakupan' => 'sebagian'], 'cakupan asing'], [['jenis' => 'waktu_habis', 'cakupan' => 'semua', 'pesan' => 'x'], 'kunci asing'], ['waktu_habis', 'bukan objek']] as [$simulasi, $catatan]) {
            $this->ditolak($ubah(['simulasi_gagal' => $simulasi]), $catatan);
        }

        $penyedia = fn (array $isi) => fn () => DB::table('ai_provider_settings')->insert($isi);
        foreach ([
            [['penyedia' => 'ollama', 'base_url' => 'ftp://192.168.1.10'], 'skema bukan http'], [['penyedia' => 'ollama', 'base_url' => 'http://localhost:11434/'], 'garis miring akhir'],
            [['penyedia' => 'ollama', 'base_url' => 'http://localhost:11434?a=1'], 'query'], [['penyedia' => 'ollama', 'base_url' => 'localhost:11434'], 'tanpa skema'],
            [['penyedia' => 'claude', 'base_url' => 'http://api.anthropic.com/v1'], 'API cloud tanpa https'],
            [['penyedia' => 'ollama', 'model' => 'qwen 2.5'], 'model berspasi'], [['penyedia' => 'ollama', 'model' => '-qwen'], 'model diawali tanda'], [['penyedia' => 'ollama', 'model' => ''], 'model teks kosong'],
            [['penyedia' => 'ollama', 'kunci_terenkripsi' => 'enkripsi', 'kunci_akhir' => 'abcd'], 'kunci untuk Ollama'], [['penyedia' => 'openai', 'kunci_terenkripsi' => 'enkripsi', 'kunci_akhir' => 'abc'], 'akhir kunci 3 karakter'],
        ] as [$isi, $catatan]) {
            $this->ditolak($penyedia($isi), $catatan);
        }

        $prompt = fn (array $isi) => fn () => DB::table('prompt_templates')->insert($isi);
        $this->ditolak($prompt(['bagian' => 'tujuan', 'instruksi' => 'Tulis tujuan {nama_kegiatn} yang diawali kata kerja.']), 'penanda salah tulis');
        $this->ditolak($prompt(['bagian' => 'tujuan', 'instruksi' => 'Tulis tujuan untuk pejabat 198001102006041001 di madrasah.']), 'NIP');
        $this->ditolak($prompt(['bagian' => 'tujuan', 'instruksi' => 'Tulis tujuan untuk 19800110 200604 1 001 di madrasah.']), 'NIP berspasi');
        $this->ditolak($prompt(['bagian' => 'tujuan', 'instruksi' => 'Sebutkan NIP kepala madrasah dalam tujuan.']), 'kata NIP');
        $this->ditolak($prompt(['bagian' => 'umum', 'gaya_bahasa' => null]), 'gaya bahasa baku kosong');
        $this->ditolak($prompt(['bagian' => 'tujuan', 'instruksi' => 'Tulis satu frasa tujuan yang diawali kata kerja.', 'gaya_bahasa' => '   ']), 'gaya bahasa spasi');
        DB::table('prompt_templates')->insert(['bagian' => 'tujuan', 'instruksi' => 'Tulis tujuan {nama_kegiatan} tahun {tahun} yang diawali kata kerja, tanpa SNIPPET kode.', 'gaya_bahasa' => 'Bahasa dinas.']);
        $this->assertSame(1, DB::table('prompt_templates')->count(), 'Penanda terdaftar & kata yang hanya memuat "NIP" lolos.');
    }

    public function test_penyedia_aktif_dan_cadangan_wajib_siap(): void
    {
        RujukanDb::segera();
        DB::table('ai_provider_settings')->insert(['penyedia' => 'ollama', 'base_url' => 'http://localhost:11434', 'model' => null, 'batas_waktu' => 60]);
        $e = $this->ditolak(fn () => DB::table('ai_settings')->insert(['id' => 1, 'provider_aktif' => 'ollama']), 'Ollama tanpa model');
        $this->assertStringContainsString('Penyedia AI ollama belum siap dipakai: alamat server & model belum diatur.', $e->getMessage());

        DB::table('ai_provider_settings')->where('penyedia', 'ollama')->update(['model' => 'qwen2.5:7b']);
        DB::table('ai_settings')->insert(['id' => 1, 'provider_aktif' => 'ollama']);
        $e = $this->ditolak(fn () => DB::table('ai_settings')->update(['provider_cadangan' => 'claude']), 'Claude belum diatur');
        $this->assertStringContainsString('Penyedia AI claude belum siap dipakai: model & kunci API belum diatur.', $e->getMessage());
        DB::table('ai_provider_settings')->insert(['penyedia' => 'claude', 'model' => 'claude-sonnet-5-5']);
        $this->ditolak(fn () => DB::table('ai_settings')->update(['provider_cadangan' => 'claude']), 'Claude tanpa kunci');

        DB::table('ai_provider_settings')->where('penyedia', 'claude')->update(['kunci_terenkripsi' => Crypt::encryptString('sk-ant-kunci-uji-0123456789'), 'kunci_akhir' => '6789']);
        DB::table('ai_settings')->update(['provider_cadangan' => 'claude']);
        $this->ditolak(fn () => DB::table('ai_provider_settings')->where('penyedia', 'claude')->update(['kunci_terenkripsi' => null, 'kunci_akhir' => null]), 'kunci cadangan dihapus');
        $this->ditolak(fn () => DB::table('ai_provider_settings')->where('penyedia', 'ollama')->update(['base_url' => null]), 'alamat penyedia aktif dihapus');
        $this->ditolak(fn () => DB::table('ai_provider_settings')->where('penyedia', 'ollama')->delete(), 'penyedia aktif dihapus');

        DB::table('ai_settings')->update(['provider_cadangan' => null]);
        DB::table('ai_provider_settings')->where('penyedia', 'claude')->update(['kunci_terenkripsi' => null, 'kunci_akhir' => null]);
        $this->assertSame([null, 'ollama'], [DB::table('ai_provider_settings')->where('penyedia', 'claude')->value('kunci_akhir'), DB::table('ai_settings')->value('provider_aktif')], 'Penyedia yang tidak dipakai boleh belum siap.');
    }

    public function test_migrasi_bisa_dibatalkan(): void
    {
        $this->assertCount(5, $this->constraintAi());

        $this->artisan('migrate:rollback', ['--path' => self::MIGRASI])->assertSuccessful();
        $this->assertSame([], $this->constraintAi());
        $this->assertSame(0, DB::table('pg_proc')->whereIn('proname', ['batas_khusus_ai_sah', 'periksa_penyedia_ai_siap'])->count());
        DB::table('ai_settings')->insert(['id' => 1, 'provider_aktif' => 'claude', 'parameter' => json_encode(['suhu' => 5])]);
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        DB::table('ai_settings')->delete();
        DB::statement('SET CONSTRAINTS ALL DEFERRED');

        $this->artisan('migrate', ['--path' => self::MIGRASI])->assertSuccessful();
        $this->assertCount(5, $this->constraintAi());
    }
}
