<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Contracts\RiwayatSaranAiRepository;
use App\Repositories\EloquentKegiatanRepository;
use App\Repositories\EloquentRiwayatSaranAiRepository;
use App\Support\Kegiatan\AksesKegiatan;
use App\Support\Kegiatan\KeputusanSaranAi;
use App\Support\Kegiatan\SaranKalimatAi;
use App\Support\Stub\StubData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\RujukanDb;
use Tests\TestCase;

class RiwayatSaranAiApiTest extends TestCase
{
    use RefreshDatabase;

    private const LEADING = ['id' => 1, 'name' => 'Muslimin, M.Pd.', 'role' => 'leading', 'leadings' => ['1.6']];

    /** @return array<string, array{bool}> */
    public static function penyimpanan(): array
    {
        return ['tiruan' => [false], 'database' => [true]];
    }

    /** @param  array<string, mixed>  $isi */
    private function kegiatan(array $isi = []): int
    {
        return app(KegiatanRepository::class)->simpan($isi + ['nama_kegiatan' => 'Rapat Kerja Madrasah', 'leading' => '1.6', 'user_id' => 1, 'tahun_anggaran' => 2026]);
    }

    /** @param  list<string>  $teks */
    private function catat(int $kegiatan, string $bagian, array $teks, int $pengguna = 1, ?string $penyedia = null): string
    {
        return app(RiwayatSaranAiRepository::class)->catat([
            'kegiatan_id' => $kegiatan, 'bagian' => $bagian, 'prompt' => "Bagian: {$bagian}", 'instruksi' => '', 'penyedia' => $penyedia, 'user_id' => $pengguna,
            'versi' => array_map(fn (string $t, int $i) => ['judul' => 'Versi '.($i + 1), 'ciri' => 'Formal', 'teks' => $t], $teks, array_keys($teks)),
        ]);
    }

    /** @return list<array{0: string, 1: string|null}> [status, teks_dipakai] per versi */
    private function keadaan(string $id): array
    {
        return array_map(fn (array $v) => [$v['status'], $v['teks_dipakai']], app(RiwayatSaranAiRepository::class)->temukan($id)['versi']);
    }

    #[DataProvider('penyimpanan')]
    public function test_catat_temukan_dan_putuskan_sama_di_tiruan_dan_database(bool $database): void
    {
        if ($database) {
            RujukanDb::siapkan();
            $this->app->singleton(KegiatanRepository::class, EloquentKegiatanRepository::class);
            $this->app->singleton(RiwayatSaranAiRepository::class, EloquentRiwayatSaranAiRepository::class);
        }
        $repo = app(RiwayatSaranAiRepository::class);
        $kegiatan = $this->kegiatan();

        $a = $this->catat($kegiatan, 'tujuan', ['meningkatkan mutu.', 'memperkuat budaya mutu.', 'menyusun program.']);
        $p = $repo->temukan($a);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $a);
        $this->assertSame(['id', 'kegiatan_id', 'bagian', 'prompt', 'instruksi', 'penyedia', 'user_id', 'pada', 'diputuskan_pada', 'versi'], array_keys($p));
        $this->assertSame([$kegiatan, 'tujuan', 'Bagian: tujuan', '', null, 1, null], [$p['kegiatan_id'], $p['bagian'], $p['prompt'], $p['instruksi'], $p['penyedia'], $p['user_id'], $p['diputuskan_pada']]);
        $this->assertMatchesRegularExpression('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d\.\d{6}\+07:00$/', $p['pada']);
        $this->assertSame([['judul' => 'Versi 1', 'ciri' => 'Formal', 'teks' => 'meningkatkan mutu.', 'status' => 'menunggu', 'teks_dipakai' => null]], array_slice($p['versi'], 0, 1));

        $repo->putuskan($a, 1, 'memperkuat budaya mutu madrasah.');
        $this->assertSame([['ditolak', null], ['diedit', 'memperkuat budaya mutu madrasah.'], ['ditolak', null]], $this->keadaan($a));
        $this->assertNotNull($repo->temukan($a)['diputuskan_pada']);
        $repo->putuskan($a, 0, 'meningkatkan mutu.');
        $this->assertSame([['diterima', null], ['ditolak', null], ['ditolak', null]], $this->keadaan($a), 'Versi dipakai berpindah tanpa melanggar "satu versi dipakai".');
        $repo->putuskan($a, null);
        $this->assertSame([['ditolak', null], ['ditolak', null], ['ditolak', null]], $this->keadaan($a));

        $b = $this->catat($kegiatan, 'jadwal', ["Persiapan — Juni\nPelaksanaan — Juli"], 2, 'ollama');
        $milik = array_values(array_filter($repo->semua(), fn (array $p) => in_array($p['id'], [$a, $b], true)));
        $this->assertSame([[$b, 'ollama', 2], [$a, null, 1]], array_map(fn (array $p) => [$p['id'], $p['penyedia'], $p['user_id']], $milik), 'Terbaru dulu.');

        $repo->putuskan('tidak-ada', 0);
        $this->assertNull($repo->temukan('tidak-ada'));

        // Keputusan lewat layanan API tersimpan sama.
        $c = $this->catat($kegiatan, 'nama_cover', ['RAPAT KERJA MADRASAH', 'RAPAT KERJA']);
        $hasil = KeputusanSaranAi::putuskan($c, '2', ' rapat  kerja tahunan ', self::LEADING);
        $this->assertSame(['dipakai', 2, 'RAPAT KERJA TAHUNAN'], [$hasil['keputusan'], $hasil['versi_dipakai'], $hasil['versi'][1]['teks_dipakai']]);
        $this->assertSame([['ditolak', null], ['diedit', 'RAPAT KERJA TAHUNAN']], $this->keadaan($c));

        if ($database) {
            $this->assertSame([['ditolak', null], ['diedit', 'RAPAT KERJA TAHUNAN']], DB::table('ai_suggestion_versions')->where('suggestion_id', $c)->orderBy('urutan')->get()->map(fn ($v) => [$v->status, $v->teks_dipakai])->all());
            $this->assertSame(3, DB::table('ai_suggestions')->count());
        }
    }

    public function test_api_penarikan_riwayat_dalam_cakupan_akun(): void
    {
        $lain = $this->kegiatan(['leading' => '1.2', 'user_id' => 4, 'nama_kegiatan' => 'Lomba Kreativitas Siswa']);
        $milikLain = $this->catat($lain, 'tujuan', ['menumbuhkan kreativitas siswa.'], 4);

        $data = $this->getJson(route('saran_ai.data'))->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJson(['halaman' => ['ke' => 1, 'per' => 20, 'jumlah' => 1, 'total' => 4], 'ringkasan' => ['permintaan' => 4, 'dipakai' => 2]])
            ->assertJsonStructure(['saringan' => ['kegiatan', 'bagian', 'status', 'cari', 'hal'], 'pilihan_kegiatan' => [['id', 'nama']], 'permintaan' => [[
                'id', 'kegiatan' => ['id', 'nama', 'leading', 'tautan'], 'bagian' => ['kunci', 'judul', 'tercetak'], 'peminta' => ['id', 'nama'], 'penyedia' => ['kunci', 'nama'],
                'pada', 'diputuskan_pada', 'keputusan', 'versi_dipakai', 'instruksi', 'versi' => [['nomor', 'judul', 'ciri', 'teks', 'status', 'label_status', 'teks_dipakai']], 'prompt',
            ]]])->json();
        $this->assertNotContains($milikLain, array_column($data['permintaan'], 'id'), 'Akun leading hanya melihat kegiatan leading-nya.');
        $this->assertSame([3, 2, 1], array_column($data['pilihan_kegiatan'], 'id'), 'Kegiatan berriwayat, menurut permintaan terbaru.');

        $tujuan = $this->getJson(route('saran_ai.data', ['bagian' => 'tujuan']))->assertOk()->json('permintaan');
        $this->assertSame(['contoh-saran-1', 'dipakai', 1, 'AI tiruan', 'Muslimin, M.Pd.'], [$tujuan[0]['id'], $tujuan[0]['keputusan'], $tujuan[0]['versi_dipakai'], $tujuan[0]['penyedia']['nama'], $tujuan[0]['peminta']['nama']]);
        $this->assertSame([$tujuan[0]['versi'][0]['teks'], null], [$tujuan[0]['versi'][0]['teks_dipakai'], $tujuan[0]['versi'][1]['teks_dipakai']], 'Teks dipakai = teks versi yang diterima.');
        $this->assertSame(route('kegiatan.kalimat', ['kegiatan' => 1]).'#kalimat-tujuan', $tujuan[0]['kegiatan']['tautan']);

        $diedit = $this->getJson(route('saran_ai.data', ['status' => 'diedit']))->json('permintaan.0');
        $this->assertSame(['contoh-saran-2', 'Diedit lalu dipakai', 'dokumen kurikulum madrasah yang lengkap, disahkan, dan siap diterapkan.', 'lebih singkat'], [$diedit['id'], $diedit['versi'][1]['label_status'], $diedit['versi'][1]['teks_dipakai'], $diedit['instruksi']]);
        $jadwal = $this->getJson(route('saran_ai.data', ['bagian' => 'jadwal']))->json('permintaan.0');
        $this->assertSame(['menunggu', null, route('kegiatan.matrik', ['kegiatan' => 3])], [$jadwal['keputusan'], $jadwal['versi_dipakai'], $jadwal['kegiatan']['tautan']]);

        $this->getJson(route('saran_ai.data', ['bagian' => 'acak', 'status' => 'x', 'hal' => 99, 'cari' => '  REVIEW   rkjm ']))->assertOk()
            ->assertJson(['saringan' => ['bagian' => '', 'status' => '', 'cari' => 'REVIEW rkjm', 'hal' => 1], 'halaman' => ['total' => 1]])
            ->assertJsonPath('permintaan.0.id', 'contoh-saran-4');
        $this->getJson(route('saran_ai.data', ['kegiatan' => 2]))->assertJsonPath('permintaan.0.keputusan', 'ditolak');

        $this->withSession(['stub_role' => 'admin']);
        $this->assertContains($milikLain, array_column($this->getJson(route('saran_ai.data'))->json('permintaan'), 'id'), 'Admin melihat semua leading.');
        $this->withSession(['stub_role' => 'leading']);

        // Riwayat satu kegiatan & satu permintaan.
        $this->getJson(route('kegiatan.saran_ai', ['kegiatan' => 1]))->assertOk()
            ->assertJson(['kegiatan_id' => 1, 'bagian' => null, 'ringkasan' => ['permintaan' => 2]])
            ->assertJsonPath('per_bagian.2', ['bagian' => 'tujuan', 'judul' => 'Tujuan', 'permintaan' => 1]);
        $this->getJson(route('kegiatan.saran_ai', ['kegiatan' => 1, 'bagian' => 'sasaran']))->assertOk()->assertJson(['bagian' => 'sasaran', 'ringkasan' => ['permintaan' => 1]]);
        $this->getJson(route('kegiatan.saran_ai', ['kegiatan' => $lain]))->assertForbidden();
        $this->getJson(route('kegiatan.saran_ai', ['kegiatan' => 999]))->assertNotFound();

        $this->getJson(route('saran_ai.detail', ['permintaan' => 'contoh-saran-3']))->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJson(['id' => 'contoh-saran-3', 'keputusan' => 'ditolak', 'kegiatan' => ['nama' => 'Supervisi Akademik dan Refleksi Pembelajaran']]);
        $this->getJson(route('saran_ai.detail', ['permintaan' => $milikLain]))->assertForbidden();
        $this->getJson(route('saran_ai.detail', ['permintaan' => 'tidak-ada']))->assertNotFound();
    }

    public function test_api_pencatatan_keputusan(): void
    {
        $permintaan = SaranKalimatAi::minta(app(KegiatanRepository::class)->temukan(1), 'nama_cover', StubData::currentUser())['permintaan'];
        $putuskan = fn (string $id, array $isi) => $this->putJson(route('saran_ai.putuskan', ['permintaan' => $id]), $isi);

        $putuskan($permintaan, ['versi' => 5])->assertUnprocessable()->assertJsonPath('galat.versi.0', 'Pilih nomor versi 1–3, atau kosongkan untuk menolak semua versi.');
        $putuskan($permintaan, ['versi' => null, 'teks_dipakai' => 'RAPAT'])->assertUnprocessable()->assertJsonPath('pesan', 'Teks yang dipakai hanya untuk versi yang dipilih.');
        $putuskan($permintaan, ['versi' => 1, 'teks_dipakai' => ['RAPAT']])->assertUnprocessable()->assertJsonPath('galat.teks_dipakai.0', 'Teks yang dipakai harus berupa teks.');
        $putuskan($permintaan, ['versi' => 1, 'teks_dipakai' => str_repeat('A', 256)])->assertUnprocessable()->assertJsonPath('pesan', 'Teks yang dipakai maksimal 255 karakter.');

        $this->withSession(['stub_role' => 'admin']);
        $putuskan($permintaan, ['versi' => 1])->assertForbidden()->assertJsonPath('pesan', 'Hanya akun yang meminta saran ini yang bisa memutuskannya.');
        $this->withSession(['stub_role' => 'leading']);

        $hasil = $putuskan($permintaan, ['versi' => 2, 'teks_dipakai' => ' workshop   kurikulum madrasah '])->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJson(['pesan' => 'Versi 2 tercatat dipakai.', 'permintaan' => ['id' => $permintaan, 'keputusan' => 'dipakai', 'versi_dipakai' => 2]])
            ->json('permintaan');
        $this->assertSame([['ditolak', null], ['diedit', 'WORKSHOP KURIKULUM MADRASAH'], ['ditolak', null]], array_map(fn (array $v) => [$v['status'], $v['teks_dipakai']], $hasil['versi']));
        $putuskan($permintaan, ['versi' => 1])->assertStatus(409)->assertJsonPath('pesan', 'Saran ini sudah diputuskan — minta saran baru bila perlu.');

        $tolak = SaranKalimatAi::minta(app(KegiatanRepository::class)->temukan(1), 'tujuan', StubData::currentUser())['permintaan'];
        $putuskan($tolak, ['versi' => null])->assertOk()->assertJson(['pesan' => 'Semua versi tercatat ditolak.', 'permintaan' => ['keputusan' => 'ditolak', 'versi_dipakai' => null]]);

        // Usulan jadwal (contoh, diminta admin) dipakai apa adanya.
        $this->withSession(['stub_role' => 'admin']);
        $putuskan('contoh-saran-4', ['versi' => 1, 'teks_dipakai' => 'Rapat — Januari'])->assertUnprocessable()->assertJsonPath('pesan', 'Usulan jadwal dipakai apa adanya — sunting barisnya di Matrik & Jadwal.');
        $jadwal = $putuskan('contoh-saran-4', ['versi' => '1'])->assertOk()->json('permintaan');
        $this->assertSame(['diterima', $jadwal['versi'][0]['teks'], 'ditolak'], [$jadwal['versi'][0]['status'], $jadwal['versi'][0]['teks_dipakai'], $jadwal['versi'][1]['status']]);
        $this->withSession(['stub_role' => 'leading']);

        $lain = $this->catat($this->kegiatan(['leading' => '1.2', 'user_id' => 4]), 'tujuan', ['menumbuhkan kreativitas.'], 4);
        $putuskan($lain, ['versi' => 1])->assertForbidden()->assertJsonPath('pesan', AksesKegiatan::PESAN_TOLAK);
        $putuskan('tidak-ada', ['versi' => 1])->assertNotFound()->assertJsonPath('pesan', 'Saran AI tidak ditemukan.');
    }
}
