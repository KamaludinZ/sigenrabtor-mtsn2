<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\ImporKegiatanRepository;
use App\Contracts\KegiatanRepository;
use App\Repositories\EloquentImporKegiatanRepository;
use App\Support\Pengaturan\KontrolTahunAktif;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\RujukanDb;
use Tests\TestCase;

class UnggahPerkinTest extends TestCase
{
    use RefreshDatabase;

    private function berkas(string $nama = 'perkin-cascade-contoh.xlsx'): UploadedFile
    {
        return new UploadedFile(base_path("tests/fixtures/{$nama}"), $nama, null, null, true);
    }

    public function test_unggah_mem_parsing_dan_mencatat_draf_tanpa_membuat_kegiatan(): void
    {
        $sebelum = count(app(KegiatanRepository::class)->semua());
        $hasil = $this->post(route('kegiatan.impor_perkin.unggah'), ['berkas' => $this->berkas()], ['Accept' => 'application/json'])->assertCreated()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJson(['lembar' => 'Cascade Perkin', 'impor' => ['jenis' => 'perkin', 'status' => 'berjalan', 'jumlah_siap' => 4, 'jumlah_dilewati' => 1, 'jumlah_gagal' => 4, 'jumlah_dibuat' => 0, 'pengguna' => 'Muslimin, M.Pd.', 'tahun' => 2026]])
            ->json();
        $this->assertSame(9, $hasil['impor']['jumlah_baris']);
        $this->assertCount(9, $hasil['baris']);
        $this->assertNotEmpty($hasil['cascade']);
        $this->assertSame(['baris', 'perkin', 'tampil', 'status', 'pesan', 'peringatan', 'data'], array_keys($hasil['baris'][0]));
        $this->assertSame($sebelum, count(app(KegiatanRepository::class)->semua()), 'Belum ada kegiatan yang dibuat.');

        $baris = app(ImporKegiatanRepository::class)->baris($hasil['impor']['id']);
        $this->assertSame(array_column($hasil['baris'], 'baris'), array_column($baris, 'nomor_baris'));
        $this->assertSame(array_column($hasil['baris'], 'status'), array_column($baris, 'status'));

        $data = $this->getJson(route('kegiatan.impor_perkin.data', ['impor' => $hasil['impor']['id']]))->assertOk()->json();
        $this->assertSame([$hasil['impor']['id'], 9], [$data['impor']['id'], count($data['baris'])]);
        $this->withSession(['stub_role' => 'admin'])->getJson(route('kegiatan.impor_perkin.data', ['impor' => $hasil['impor']['id']]))->assertOk();
    }

    public function test_berkas_salah_akses_dan_tahun_terkunci(): void
    {
        $this->post(route('kegiatan.impor_perkin.unggah'), ['berkas' => UploadedFile::fake()->create('ttd.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonPath('galat.berkas.0', 'Pilih berkas Excel (.xlsx, .xls) atau CSV.');
        $this->postJson(route('kegiatan.impor_perkin.unggah'))->assertStatus(422)->assertJsonPath('galat.berkas.0', 'Pilih berkas cascade Perkin (.xlsx).');
        $this->post(route('kegiatan.impor_perkin.unggah'), ['berkas' => $this->berkas('master-kode-asing.xlsx')], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonStructure(['pesan', 'galat' => ['berkas']]);

        $lain = app(ImporKegiatanRepository::class)->mulai(['tahun' => 2026, 'jenis' => 'perkin', 'nama_berkas' => 'a.xlsx', 'lembar' => 'Cascade', 'user_id' => 99, 'pengguna' => 'Akun Lain'], []);
        $this->getJson(route('kegiatan.impor_perkin.data', ['impor' => $lain['id']]))->assertForbidden();
        $this->getJson(route('kegiatan.impor_perkin.data', ['impor' => '00000000-0000-4000-8000-000000000000']))->assertNotFound();

        KontrolTahunAktif::aktifkan(2025, ['name' => 'Koordinator PUSKOM']);
        $this->post(route('kegiatan.impor_perkin.unggah'), ['berkas' => $this->berkas()], ['Accept' => 'application/json'])->assertForbidden();
    }

    /** @return array<string, array{bool}> */
    public static function penyimpanan(): array
    {
        return ['tiruan' => [false], 'database' => [true]];
    }

    #[DataProvider('penyimpanan')]
    public function test_repositori_impor(bool $database): void
    {
        $kegiatan = 50;
        if ($database) {
            RujukanDb::siapkan();
            $this->app->singleton(ImporKegiatanRepository::class, EloquentImporKegiatanRepository::class);
            $kegiatan = DB::table('activities')->insertGetId(['tahun_anggaran' => 2026, 'nama_kegiatan' => 'Hasil Impor', 'asal' => 'impor_perkin']);
        }
        $repo = app(ImporKegiatanRepository::class);
        $impor = $repo->mulai(['tahun' => 2026, 'jenis' => 'perkin', 'nama_berkas' => 'cascade.xlsx', 'lembar' => 'Cascade Perkin', 'user_id' => 1, 'pengguna' => 'Muslimin, M.Pd.'], [
            ['nomor_baris' => 7, 'status' => 'gagal', 'data' => [], 'pesan' => ['Leading tidak dikenal.'], 'peringatan' => []],
            ['nomor_baris' => 5, 'status' => 'siap', 'data' => ['nama_kegiatan' => 'Hasil Impor'], 'pesan' => [], 'peringatan' => ['IKSK dikosongkan.']],
            ['nomor_baris' => 6, 'status' => 'dilewati', 'data' => ['nama_kegiatan' => 'Sudah Ada'], 'pesan' => ['Nama sudah ada.'], 'peringatan' => []],
        ]);

        $this->assertSame(['berjalan', 3, 1, 1, 1, 0, null], [$impor['status'], $impor['jumlah_baris'], $impor['jumlah_siap'], $impor['jumlah_dilewati'], $impor['jumlah_gagal'], $impor['jumlah_dibuat'], $impor['selesai_pada']]);
        $this->assertSame($impor, $repo->temukan($impor['id']));
        $this->assertSame([5, 6, 7], array_column($repo->baris($impor['id']), 'nomor_baris'));
        $this->assertSame(['nama_kegiatan' => 'Hasil Impor'], $repo->baris($impor['id'])[0]['data']);

        $repo->tandaiDibuat($impor['id'], 5, $kegiatan);
        $repo->tandaiDibuat($impor['id'], 5, $kegiatan);
        $this->assertSame(1, $repo->temukan($impor['id'])['jumlah_dibuat'], 'Ditandai dua kali tetap satu.');
        $this->assertSame($kegiatan, $repo->baris($impor['id'])[0]['kegiatan_id']);
        $this->assertNotNull($repo->baris($impor['id'])[0]['diproses_pada']);

        $selesai = $repo->akhiri($impor['id'], 'selesai');
        $this->assertSame('selesai', $selesai['status']);
        $this->assertNotNull($selesai['selesai_pada']);
        $this->assertNull($repo->temukan('00000000-0000-4000-8000-000000000000'));

        $this->expectException(\InvalidArgumentException::class);
        $repo->tandaiDibuat($impor['id'], 6, $kegiatan);
    }
}
