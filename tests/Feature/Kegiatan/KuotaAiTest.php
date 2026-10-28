<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Contracts\PemakaianAiRepository;
use App\Contracts\PengaturanAiRepository;
use App\Contracts\PenyusunSaranKalimat;
use App\Repositories\EloquentPemakaianAiRepository;
use App\Support\Kegiatan\BatasAi;
use App\Support\Kegiatan\GagalPenyediaAi;
use App\Support\Kegiatan\GagalUsulanAi;
use App\Support\Kegiatan\PermintaanSaranKalimat;
use App\Support\Kegiatan\SaranKalimatAi;
use App\Support\Stub\StubData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\RujukanDb;
use Tests\TestCase;

class KuotaAiTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{bool}> */
    public static function penyimpanan(): array
    {
        return ['tiruan' => [false], 'database' => [true]];
    }

    /** @param  array<string, mixed>  $ubah */
    private function aturAi(array $ubah): void
    {
        $data = array_diff_key(app(PengaturanAiRepository::class)->ambil(), ['diperbarui_pada' => 1]);
        app(PengaturanAiRepository::class)->simpan(array_replace_recursive($data, $ubah));
    }

    #[DataProvider('penyimpanan')]
    public function test_jatah_diambil_dan_dikembalikan_sama_di_tiruan_dan_database(bool $database): void
    {
        if ($database) {
            RujukanDb::siapkan();
            $this->app->singleton(PemakaianAiRepository::class, EloquentPemakaianAiRepository::class);
        }
        $repo = app(PemakaianAiRepository::class);
        $hari = now()->toDateString();

        $this->assertSame([true, true, false], [$repo->ambilJatah(1, 'tujuan', 2), $repo->ambilJatah(1, 'usul_jadwal', 2), $repo->ambilJatah(1, 'sasaran', 2)], 'Batas 2 untuk semua fitur.');
        $this->assertSame([2, ['tujuan' => 1, 'usul_jadwal' => 1]], [$repo->jumlah(1, $hari), $this->urut($repo->rincian(1, $hari))]);
        $this->assertTrue($repo->ambilJatah(3, 'tujuan', 2), 'Akun lain punya jatah sendiri.');

        $repo->catat(1, 'tujuan');
        $repo->kembalikan(1, 'tujuan');
        $repo->kembalikan(1, 'usul_jadwal');
        $repo->kembalikan(1, 'latar_belakang');
        $this->assertSame([1, ['tujuan' => 1]], [$repo->jumlah(1, $hari), $this->urut($repo->rincian(1, $hari))], 'Kembalikan mengurangi satu; fitur tanpa catatan tidak berubah.');
        $this->assertSame(0, $repo->jumlah(1, now()->subDay()->toDateString()));

        if ($database) {
            $this->assertSame([['user_id' => 1, 'fitur' => 'tujuan', 'jumlah' => 1], ['user_id' => 3, 'fitur' => 'tujuan', 'jumlah' => 1]], DB::table('ai_usage')->orderBy('user_id')->get(['user_id', 'fitur', 'jumlah'])->map(fn ($r) => (array) $r)->all(), 'Baris berjumlah 0 dihapus.');
        }
    }

    /** @param  array<string, int>  $rincian */
    private function urut(array $rincian): array
    {
        ksort($rincian);

        return $rincian;
    }

    public function test_batas_habis_dan_penyedia_gagal_tidak_dihitung(): void
    {
        $pengguna = StubData::currentUser();
        $this->aturAi(['batas_khusus' => [(string) $pengguna['id'] => 1]]);

        BatasAi::ambil($pengguna, 'tujuan');
        try {
            BatasAi::ambil($pengguna, 'sasaran');
            $this->fail('Jatah habis harus ditolak.');
        } catch (GagalUsulanAi $e) {
            $this->assertSame('Batas 1 permintaan AI hari ini sudah tercapai — coba lagi besok.', $e->getMessage());
        }
        BatasAi::kembalikan($pengguna, 'tujuan');
        $this->assertSame(1, BatasAi::sisa($pengguna));

        $this->app->instance(PenyusunSaranKalimat::class, new class implements PenyusunSaranKalimat
        {
            public function sarankan(PermintaanSaranKalimat $permintaan, string $penyedia = 'tiruan'): array
            {
                throw new GagalPenyediaAi('tidak_terhubung', $penyedia);
            }
        });
        try {
            SaranKalimatAi::minta(app(KegiatanRepository::class)->temukan(1), 'tujuan', $pengguna);
            $this->fail('Penyedia gagal.');
        } catch (GagalUsulanAi) {
            $this->assertSame([1, []], [BatasAi::sisa($pengguna), app(PemakaianAiRepository::class)->rincian((int) $pengguna['id'], now()->toDateString())], 'Jatah dikembalikan saat penyedia gagal.');
        }
    }

    public function test_endpoint_cek_kuota_akun(): void
    {
        $this->getJson(route('ai.kuota'))->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJson(['akun' => ['id' => 1], 'tanggal' => now()->toDateString(), 'batas' => 20, 'terpakai' => 0, 'sisa' => 20, 'persen' => 0, 'tingkat' => 'aman', 'label_tingkat' => 'Cukup', 'khusus' => false, 'boleh' => true, 'pesan_habis' => null, 'rincian' => []]);

        $this->aturAi(['batas_khusus' => ['1' => 2]]);
        BatasAi::ambil(StubData::currentUser(), 'tujuan');
        BatasAi::ambil(StubData::currentUser(), 'usul_jadwal');
        $this->getJson(route('ai.kuota'))->assertOk()
            ->assertJson(['batas' => 2, 'terpakai' => 2, 'sisa' => 0, 'tingkat' => 'habis', 'khusus' => true, 'boleh' => false, 'pesan_habis' => 'Batas 2 permintaan AI hari ini sudah tercapai — coba lagi besok.'])
            ->assertJsonCount(2, 'rincian');
        $this->getJson(route('ai.kuota', ['akun' => 1]))->assertOk()->assertJsonPath('akun.id', 1);
        $this->getJson(route('ai.kuota', ['akun' => 3]))->assertForbidden();

        session(['stub_role' => 'admin']);
        $this->getJson(route('ai.kuota', ['akun' => 3]))->assertOk()->assertJson(['akun' => ['id' => 3], 'sisa' => 20]);
        $this->getJson(route('ai.kuota', ['akun' => 99]))->assertNotFound();
    }
}
