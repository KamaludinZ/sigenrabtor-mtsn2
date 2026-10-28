<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\DrafSuntingRepository;
use App\Contracts\KegiatanRepository;
use App\Contracts\MatrikJadwalRepository;
use App\Contracts\RabRepository;
use App\Contracts\RiwayatSuntingRepository;
use App\Repositories\EloquentDrafSuntingRepository;
use App\Repositories\EloquentKegiatanRepository;
use App\Repositories\EloquentMatrikJadwalRepository;
use App\Repositories\EloquentRabRepository;
use App\Repositories\EloquentRiwayatSuntingRepository;
use App\Support\Dokumen\DrafSunting;
use App\Support\Dokumen\RiwayatSunting;
use App\Support\Dokumen\SuntingDokumen;
use App\Support\Kegiatan\UbahDataKegiatan;
use App\Support\Pengaturan\PengaturanBerubah;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\RujukanDb;
use Tests\TestCase;

class DrafSuntingTest extends TestCase
{
    use RefreshDatabase;

    private const LEADING = ['id' => 1, 'name' => 'Muslimin, M.Pd.', 'role' => 'leading', 'leadings' => ['1.6']];

    private const ADMIN = ['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin', 'leadings' => []];

    private const KUNCI = ['id', 'kegiatan_id', 'user_id', 'bidang', 'teks', 'teks_awal', 'dibuat_pada', 'diperbarui_pada'];

    /** @return array<string, array{bool}> */
    public static function penyimpanan(): array
    {
        return ['tiruan' => [false], 'database' => [true]];
    }

    private function pakaiDatabase(): void
    {
        RujukanDb::siapkan();
        $this->app->singleton(KegiatanRepository::class, EloquentKegiatanRepository::class);
        $this->app->singleton(DrafSuntingRepository::class, EloquentDrafSuntingRepository::class);
        $this->app->singleton(RiwayatSuntingRepository::class, EloquentRiwayatSuntingRepository::class);
        // RAB & matrik juga dari database agar id kegiatan baru tidak bertemu contoh tiruan berid sama.
        $this->app->singleton(RabRepository::class, EloquentRabRepository::class);
        $this->app->singleton(MatrikJadwalRepository::class, EloquentMatrikJadwalRepository::class);
    }

    private function kegiatanBaru(): int
    {
        return app(KegiatanRepository::class)->simpan([
            'nama_kegiatan' => 'Rapat Kerja Madrasah', 'leading' => '1.6', 'user_id' => 1, 'tahun_anggaran' => 2026, 'status' => 'draf',
            'tempat' => 'Ruang Rapat Madrasah',
        ]);
    }

    private function galat(callable $aksi): array
    {
        try {
            $aksi();
        } catch (ValidationException $e) {
            return $e->errors();
        }
        $this->fail('Seharusnya ditolak validasi.');
    }

    #[DataProvider('penyimpanan')]
    public function test_simpan_perbarui_dan_terapkan_draf(bool $database): void
    {
        $database && $this->pakaiDatabase();
        $repo = app(KegiatanRepository::class);
        $id = $this->kegiatanBaru();

        $pertama = DrafSunting::simpan($id, 'tempat', 'Aula Utama MTsN', self::LEADING);
        $this->assertSame([true, false], [$pertama['baru'], $pertama['sama']]);
        $this->assertSame([...self::KUNCI, 'label', 'versi', 'keadaan', 'keterangan'], array_keys($pertama['draf']));
        $this->assertSame([$id, 1, 'tempat', 'Aula Utama MTsN', 'Ruang Rapat Madrasah', 'belum_diterapkan'], [$pertama['draf']['kegiatan_id'], $pertama['draf']['user_id'], $pertama['draf']['bidang'], $pertama['draf']['teks'], $pertama['draf']['teks_awal'], $pertama['draf']['keadaan']]);
        $this->assertSame('Ruang Rapat Madrasah', $repo->temukan($id)['tempat'], 'Draf belum mengubah data kegiatan.');

        $this->travel(1)->seconds();
        $kedua = DrafSunting::simpan($id, 'tempat', 'Aula Utama MTsN 2 Kota Malang', self::LEADING, $pertama['draf']['versi']);
        $this->assertSame([false, $pertama['draf']['id'], $pertama['draf']['dibuat_pada'], 'Ruang Rapat Madrasah'], [$kedua['baru'], $kedua['draf']['id'], $kedua['draf']['dibuat_pada'], $kedua['draf']['teks_awal']]);
        $this->assertGreaterThan($pertama['draf']['diperbarui_pada'], $kedua['draf']['diperbarui_pada']);
        $this->expectsBentrok(fn () => DrafSunting::simpan($id, 'tempat', 'Aula lain', self::LEADING, $pertama['draf']['versi']));

        $data = DrafSunting::data($repo->temukan($id), self::LEADING);
        $this->assertSame([1, array_keys(SuntingDokumen::BIDANG), UbahDataKegiatan::versi($repo->temukan($id))], [$data['jumlah_draf'], array_column($data['bidang'], 'kunci'), $data['versi']]);
        $this->assertSame('Aula Utama MTsN 2 Kota Malang', collect($data['bidang'])->firstWhere('kunci', 'tempat')['draf']['teks']);
        $this->assertSame(0, DrafSunting::data($repo->temukan($id), self::ADMIN)['jumlah_draf'], 'Draf milik akun penulisnya.');

        $hasil = DrafSunting::terapkan($id, 'tempat', self::LEADING, null, $kedua['draf']['versi']);
        $this->assertContains('tempat', $hasil['diubah']);
        $this->assertSame('Aula Utama MTsN 2 Kota Malang', $repo->temukan($id)['tempat']);
        $this->assertNull(app(DrafSuntingRepository::class)->temukan($id, 1, 'tempat'), 'Draf dibuang sesudah diterapkan.');
        $this->assertSame(['tempat', 'Ruang Rapat Madrasah'], [RiwayatSunting::untuk($id)[0]['bidang'], RiwayatSunting::untuk($id)[0]['teks_lama']]);
        $this->assertArrayHasKey('bidang', $this->galat(fn () => DrafSunting::terapkan($id, 'tempat', self::LEADING)));
    }

    #[DataProvider('penyimpanan')]
    public function test_teks_sama_data_berubah_dan_aturan(bool $database): void
    {
        $database && $this->pakaiDatabase();
        $repo = app(KegiatanRepository::class);
        $id = $this->kegiatanBaru();

        DrafSunting::simpan($id, 'tempat', 'Aula Baru', self::LEADING);
        $sama = DrafSunting::simpan($id, 'tempat', '  Ruang Rapat Madrasah ', self::LEADING);
        $this->assertSame([true, null], [$sama['sama'], $sama['draf']]);
        $this->assertNull(app(DrafSuntingRepository::class)->temukan($id, 1, 'tempat'), 'Teks sama dengan tersimpan membuang draf.');

        DrafSunting::simpan($id, 'tempat', 'Aula Baru', self::LEADING);
        SuntingDokumen::simpan($id, $repo->temukan($id), 'tempat', 'Aula Lama', self::ADMIN, null);
        $draf = collect(DrafSunting::data($repo->temukan($id), self::LEADING)['bidang'])->firstWhere('kunci', 'tempat')['draf'];
        $this->assertSame(['data_berubah', DrafSunting::KEADAAN['data_berubah']], [$draf['keadaan'], $draf['keterangan']]);

        // Teks khusus: draf teks baku tetap berupa teks baku sampai berbeda.
        $persiapan = DrafSunting::simpan($id, 'persiapan', "Rapat koordinasi panitia\nabc", self::LEADING);
        $this->assertNull($persiapan['draf']['teks_awal'], 'Teks baku = teks awal kosong.');
        $this->assertNotSame([], $this->galat(fn () => DrafSunting::terapkan($id, 'persiapan', self::LEADING)));
        $this->assertNotNull(app(DrafSuntingRepository::class)->temukan($id, 1, 'persiapan'), 'Gagal diterapkan: draf tetap.');

        $this->assertArrayHasKey('bidang', $this->galat(fn () => DrafSunting::simpan($id, 'nomor_dokumen', 'x', self::LEADING)));
        $this->assertArrayHasKey('teks', $this->galat(fn () => DrafSunting::simpan($id, 'tempat', str_repeat('a', SuntingDokumen::maksDraf('tempat') + 1), self::LEADING)));

        $repo->simpan(['status' => 'diperiksa'] + $repo->temukan($id), $id);
        try {
            DrafSunting::simpan($id, 'tempat', 'Aula Terkunci', self::LEADING);
            $this->fail('Kegiatan terkunci harus ditolak.');
        } catch (AuthorizationException) {
        }
        $this->expectsBentrok(fn () => DrafSunting::hapus($id, 'tempat', self::LEADING, 'usang'));
        $this->assertTrue(DrafSunting::hapus($id, 'tempat', self::LEADING), 'Draf tetap bisa dibuang walau kegiatan terkunci.');
        $this->assertFalse(DrafSunting::hapus($id, 'tempat', self::LEADING));
    }

    public function test_endpoint_draf_sunting(): void
    {
        $id = $this->kegiatanBaru();
        $simpan = fn (array $isi, string $bidang = 'tempat') => $this->putJson(route('kegiatan.sunting.simpan', ['kegiatan' => $id, 'bidang' => $bidang]), $isi);

        $baru = $simpan(['teks' => 'Aula Utama'])->assertCreated()->assertHeader('Cache-Control', 'no-store, private')->assertJsonPath('draf.teks', 'Aula Utama');
        $simpan(['teks' => 'Aula Utama MTsN', 'versi' => $baru->json('draf.versi')])->assertOk()->assertJsonPath('baru', false);
        $simpan(['teks' => 'Aula lain', 'versi' => $baru->json('draf.versi')])->assertStatus(409)->assertJsonPath('draf.teks', 'Aula Utama MTsN');
        $simpan(['teks' => 'x'], 'nomor_dokumen')->assertStatus(422)->assertJsonStructure(['pesan', 'galat' => ['bidang']]);
        $this->getJson(route('kegiatan.sunting', $id))->assertOk()->assertJsonPath('jumlah_draf', 1)->assertJsonCount(count(SuntingDokumen::BIDANG), 'bidang');

        $this->postJson(route('kegiatan.sunting.terapkan', ['kegiatan' => $id, 'bidang' => 'tempat']), ['versi' => 'usang'])->assertStatus(409);
        $this->postJson(route('kegiatan.sunting.terapkan', ['kegiatan' => $id, 'bidang' => 'tempat']))->assertOk()->assertJsonPath('versi', UbahDataKegiatan::versi(app(KegiatanRepository::class)->temukan($id)));
        $this->postJson(route('kegiatan.sunting.terapkan', ['kegiatan' => $id, 'bidang' => 'tempat']))->assertStatus(422)->assertJsonStructure(['galat' => ['bidang']]);

        $simpan(['teks' => 'Aula Kedua']);
        $this->deleteJson(route('kegiatan.sunting.hapus', ['kegiatan' => $id, 'bidang' => 'tempat']))->assertOk();
        $this->deleteJson(route('kegiatan.sunting.hapus', ['kegiatan' => $id, 'bidang' => 'tempat']))->assertNotFound();

        $lain = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Kegiatan Leading Lain', 'leading' => '1.2', 'user_id' => 3, 'status' => 'draf']);
        $this->getJson(route('kegiatan.sunting', $lain))->assertForbidden();
        $this->putJson(route('kegiatan.sunting.simpan', ['kegiatan' => $lain, 'bidang' => 'tempat']), ['teks' => 'Aula'])->assertForbidden();
        $this->putJson(route('kegiatan.sunting.simpan', ['kegiatan' => 999, 'bidang' => 'tempat']), ['teks' => 'Aula'])->assertNotFound();
        $this->getJson(route('kegiatan.sunting', 999))->assertNotFound();
    }

    private function expectsBentrok(callable $aksi): void
    {
        try {
            $aksi();
            $this->fail('Versi usang harus ditolak.');
        } catch (PengaturanBerubah $e) {
            $this->assertSame(DrafSunting::PESAN_BENTROK, $e->getMessage());
        }
    }
}
