<?php

namespace Tests\Feature\Pemeriksaan;

use App\Contracts\CatatanPemeriksaanRepository;
use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Livewire\Pemeriksaan\Periksa;
use App\Repositories\EloquentCatatanPemeriksaanRepository;
use App\Repositories\EloquentKegiatanRepository;
use App\Support\Kegiatan\RiwayatStatus;
use App\Support\Pemeriksaan\CatatanPemeriksaan;
use App\Support\Pemeriksaan\KeputusanPemeriksaan;
use App\Support\Pengaturan\PengaturanBerubah;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\RujukanDb;
use Tests\TestCase;

class CatatanPemeriksaanTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN = ['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin', 'leadings' => []];

    private const ADMIN_LAIN = ['id' => 5, 'name' => 'Admin Kedua', 'role' => 'admin', 'leadings' => []];

    private const LEADING = ['id' => 1, 'name' => 'Muslimin, M.Pd.', 'role' => 'leading', 'leadings' => ['1.6']];

    /** @return array<string, array{bool}> */
    public static function penyimpanan(): array
    {
        return ['tiruan' => [false], 'database' => [true]];
    }

    private function pakaiDatabase(): void
    {
        RujukanDb::siapkan();
        $this->app->singleton(KegiatanRepository::class, EloquentKegiatanRepository::class);
        $this->app->singleton(CatatanPemeriksaanRepository::class, EloquentCatatanPemeriksaanRepository::class);
    }

    private function kegiatan(string $status = 'diperiksa', int $tahun = 2026): int
    {
        $riwayat = [RiwayatStatus::entri('ajukan', StatusKegiatan::Diperiksa, self::LEADING)];
        if ($status === 'final') {
            $riwayat[] = RiwayatStatus::entri('setujui', StatusKegiatan::Disetujui, self::ADMIN);
            $riwayat[] = RiwayatStatus::entri('finalkan', StatusKegiatan::Final, self::LEADING);
        }

        return app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Rapat Kerja Madrasah', 'leading' => '1.6', 'user_id' => 1, 'tahun_anggaran' => $tahun, 'status' => $status, 'riwayat_status' => $status === 'draf' ? [] : $riwayat]);
    }

    /** @return array<string, string> */
    private function galat(callable $aksi): array
    {
        try {
            $aksi();
        } catch (ValidationException $e) {
            return array_map(fn (array $p) => $p[0], $e->errors());
        }
        $this->fail('Seharusnya ditolak.');
    }

    #[DataProvider('penyimpanan')]
    public function test_simpan_draf_lalu_dibuang_saat_keputusan(bool $database): void
    {
        $database && $this->pakaiDatabase();
        $id = $this->kegiatan();
        $this->assertNull(CatatanPemeriksaan::draf($id));

        $hasil = CatatanPemeriksaan::simpan($id, "  Sasaran   belum terukur.\n\n\n\nSesuaikan honor. ", self::ADMIN, '');
        $this->assertTrue($hasil['berubah']);
        $this->assertSame(['Sasaran belum terukur.'."\n\n".'Sesuaikan honor.', 2, 'Koordinator PUSKOM'], [$hasil['draf']['catatan'], $hasil['draf']['user_id'], $hasil['draf']['oleh']]);
        $this->assertMatchesRegularExpression('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d\.\d{6}\+07:00$/', $hasil['versi']);
        $this->assertSame($hasil['draf'], CatatanPemeriksaan::draf($id));

        $sama = CatatanPemeriksaan::simpan($id, 'Sasaran belum terukur.'."\n\n".'Sesuaikan honor.', self::ADMIN, $hasil['versi']);
        $this->assertSame([false, $hasil['versi']], [$sama['berubah'], $sama['versi']], 'Teks sama tidak ditulis ulang.');

        // Admin lain menimpa draf; versi lama ditolak, tanpa versi tetap boleh (endpoint tanpa versi).
        $lain = CatatanPemeriksaan::simpan($id, 'Perbaiki tanggal laporan.', self::ADMIN_LAIN, $hasil['versi']);
        try {
            CatatanPemeriksaan::simpan($id, 'Ketikan lama.', self::ADMIN, $hasil['versi']);
            $this->fail('Versi usang harus ditolak.');
        } catch (PengaturanBerubah $e) {
            $this->assertStringContainsString('diubah Admin Kedua', $e->getMessage());
            $this->assertSame('Perbaiki tanggal laporan.', CatatanPemeriksaan::draf($id)['catatan']);
        }

        // Keputusan memakai catatannya sendiri lalu membuang draf.
        $keputusan = KeputusanPemeriksaan::putuskan($id, 'kembalikan', $lain['draf']['catatan'], self::ADMIN);
        $this->assertSame('Perbaiki tanggal laporan.', $keputusan['entri']['catatan']);
        $this->assertNull(CatatanPemeriksaan::draf($id));
        $this->assertSame([['aksi' => 'kembalikan', 'keputusan' => 'dikembalikan', 'label' => StatusKegiatan::labelRiwayat('kembalikan'), 'catatan' => 'Perbaiki tanggal laporan.', 'oleh' => 'Koordinator PUSKOM']],
            array_map(fn ($r) => array_diff_key($r, ['pada' => 1]), CatatanPemeriksaan::riwayat(app(KegiatanRepository::class)->temukan($id))));

        // Draf kosong dihapus; versi '' = belum ada draf.
        $final = $this->kegiatan('final');
        CatatanPemeriksaan::simpan($final, 'Alasan buka kunci.', self::ADMIN);
        $kosong = CatatanPemeriksaan::simpan($final, " \n ", self::ADMIN);
        $this->assertSame([null, true, ''], [$kosong['draf'], $kosong['berubah'], $kosong['versi']]);
        $this->assertNull(CatatanPemeriksaan::draf($final));
        $this->assertFalse(CatatanPemeriksaan::simpan($final, '', self::ADMIN, '')['berubah']);
    }

    public function test_aturan_simpan_draf(): void
    {
        $id = $this->kegiatan();
        $this->assertSame(['catatan' => 'Catatan paling banyak '.RiwayatStatus::MAKS_CATATAN.' karakter.'], $this->galat(fn () => CatatanPemeriksaan::simpan($id, str_repeat('a', RiwayatStatus::MAKS_CATATAN + 1), self::ADMIN)));

        $draf = $this->kegiatan('draf');
        $this->assertSame(['catatan' => 'Kegiatan berstatus Draf tidak sedang menunggu keputusan pemeriksaan — catatan tidak disimpan.'], $this->galat(fn () => CatatanPemeriksaan::simpan($draf, 'Catatan.', self::ADMIN)));
        $lalu = $this->kegiatan(tahun: 2025);
        $this->assertSame(['catatan' => 'Tahun Anggaran 2025 terkunci — kegiatan tahun itu hanya bisa dilihat.'], $this->galat(fn () => CatatanPemeriksaan::simpan($lalu, 'Catatan.', self::ADMIN)));
        $this->assertNull(CatatanPemeriksaan::draf($draf));

        try {
            CatatanPemeriksaan::simpan(999, 'Catatan.', self::ADMIN);
            $this->fail('Kegiatan tidak ada harus ditolak.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Kegiatan tidak ditemukan.', $e->getMessage());
        }
        $this->expectException(AuthorizationException::class);
        CatatanPemeriksaan::simpan($id, 'Catatan.', self::LEADING);
    }

    public function test_tabel_menolak_isi_tidak_rapi_dan_ikut_terhapus(): void
    {
        $this->pakaiDatabase();
        $id = $this->kegiatan();
        foreach (['', ' Ada spasi tepi', str_repeat('a', RiwayatStatus::MAKS_CATATAN + 1)] as $catatan) {
            try {
                DB::transaction(fn () => DB::table('catatan_pemeriksaan')->insert(['activity_id' => $id, 'oleh' => 'Admin', 'catatan' => $catatan]));
                $this->fail('Catatan "'.substr($catatan, 0, 20).'" seharusnya ditolak.');
            } catch (QueryException $e) {
                $this->assertStringContainsString('catatan_pemeriksaan_isian_check', $e->getMessage());
            }
        }

        CatatanPemeriksaan::simpan($id, 'Catatan.', self::ADMIN);
        DB::table('activities')->where('id', $id)->delete();
        $this->assertSame(0, DB::table('catatan_pemeriksaan')->count());
    }

    public function test_endpoint_baca_dan_simpan(): void
    {
        $id = $this->kegiatan();
        $baca = route('pemeriksaan.catatan', ['kegiatan' => $id]);
        $simpan = route('pemeriksaan.catatan.simpan', ['kegiatan' => $id]);

        $this->withSession(['stub_role' => 'leading'])->getJson($baca)->assertForbidden();
        $this->putJson($simpan, ['catatan' => 'x'])->assertForbidden();
        $this->withSession(['stub_role' => 'admin']);
        $this->getJson(route('pemeriksaan.catatan', ['kegiatan' => 999]))->assertNotFound();
        $this->putJson(route('pemeriksaan.catatan.simpan', ['kegiatan' => 999]), ['catatan' => 'x'])->assertNotFound();

        $this->getJson($baca)->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJson(['kegiatan_id' => $id, 'status' => 'diperiksa', 'label_status' => 'Diperiksa', 'dapat_dicatat' => true, 'alasan' => null, 'batas' => RiwayatStatus::MAKS_CATATAN, 'draf' => null, 'versi' => '', 'riwayat' => []])
            ->assertJsonPath('keputusan_tersedia', [['aksi' => 'setujui', 'label' => 'Setujui', 'catatan_wajib' => false], ['aksi' => 'kembalikan', 'label' => 'Kembalikan', 'catatan_wajib' => true]]);

        $versi = $this->putJson($simpan, ['catatan' => 'Lengkapi sasaran.', 'versi' => ''])->assertOk()
            ->assertJson(['berubah' => true, 'pesan' => 'Draf catatan tersimpan.', 'draf' => ['catatan' => 'Lengkapi sasaran.', 'oleh' => 'Koordinator PUSKOM']])
            ->json('versi');
        $this->getJson($baca)->assertJsonPath('draf.catatan', 'Lengkapi sasaran.')->assertJsonPath('versi', $versi);

        $this->putJson($simpan, ['catatan' => 'Ketikan lain.', 'versi' => ''])->assertStatus(409)
            ->assertJsonPath('draf.catatan', 'Lengkapi sasaran.')->assertJsonPath('versi', $versi);
        $this->putJson($simpan, ['catatan' => str_repeat('a', RiwayatStatus::MAKS_CATATAN + 1)])->assertStatus(422)
            ->assertJsonPath('galat.catatan.0', 'Catatan paling banyak '.RiwayatStatus::MAKS_CATATAN.' karakter.');
        $this->putJson($simpan, ['catatan' => '', 'versi' => $versi])->assertOk()->assertJson(['draf' => null, 'berubah' => true, 'versi' => '']);

        $draf = $this->kegiatan('draf');
        $this->getJson(route('pemeriksaan.catatan', ['kegiatan' => $draf]))->assertJson(['dapat_dicatat' => false, 'keputusan_tersedia' => []]);
    }

    public function test_panel_memuat_menyimpan_dan_menangani_bentrok_draf(): void
    {
        session(['stub_role' => 'admin']);
        $id = $this->kegiatan();
        CatatanPemeriksaan::simpan($id, 'Draf kemarin.', self::ADMIN_LAIN);

        $halaman = Livewire::test(Periksa::class, ['kegiatan' => $id])
            ->assertSet('catatan', 'Draf kemarin.')
            ->assertSeeHtml('data-testid="draf-catatan"')
            ->assertSee('oleh Admin Kedua')
            ->set('catatan', 'Draf kemarin. Tambah: perbaiki RAB.')
            ->assertHasNoErrors()
            ->assertSee('oleh Koordinator PUSKOM');
        $this->assertSame('Draf kemarin. Tambah: perbaiki RAB.', CatatanPemeriksaan::draf($id)['catatan']);

        // Admin lain menyimpan sesudahnya: draf tidak ditimpa diam-diam.
        CatatanPemeriksaan::simpan($id, 'Versi admin lain.', self::ADMIN_LAIN);
        $halaman->set('catatan', 'Ketikan saya.')
            ->assertSet('bentrokCatatan', true)
            ->assertSeeHtml('data-testid="catatan-bentrok"')
            ->assertSee('Versi admin lain.')
            ->assertDispatched('notify', type: 'error');
        $this->assertSame('Versi admin lain.', CatatanPemeriksaan::draf($id)['catatan']);
        $halaman->call('pakaiCatatanLain')->assertSet('catatan', 'Versi admin lain.')->assertSet('bentrokCatatan', false);

        CatatanPemeriksaan::simpan($id, 'Lagi dari admin lain.', self::ADMIN_LAIN);
        $halaman->set('catatan', 'Punya saya.')->assertSet('bentrokCatatan', true)
            ->call('simpanCatatan')->assertSet('bentrokCatatan', false)->assertDontSeeHtml('data-testid="catatan-bentrok"');
        $this->assertSame('Punya saya.', CatatanPemeriksaan::draf($id)['catatan']);

        // Keputusan mengirim catatan & membuang draf.
        $halaman->call('minta', 'kembalikan')->call('putuskan', 'kembalikan')
            ->assertSet('catatan', '')
            ->assertDontSeeHtml('data-testid="draf-catatan"');
        $this->assertNull(CatatanPemeriksaan::draf($id));
        $this->assertSame('Punya saya.', app(KegiatanRepository::class)->temukan($id)['riwayat_status'][1]['catatan']);

        // Draf yang ditolak aturannya tampil sebagai galat kolom.
        Livewire::test(Periksa::class, ['kegiatan' => $this->kegiatan(tahun: 2025)])
            ->set('catatan', 'Catatan.')
            ->assertHasErrors(['catatan']);
    }
}
