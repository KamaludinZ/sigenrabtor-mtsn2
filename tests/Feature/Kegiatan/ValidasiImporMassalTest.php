<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\ImporKegiatanRepository;
use App\Contracts\KegiatanRepository;
use App\Contracts\LeadingRepository;
use App\Repositories\EloquentImporKegiatanRepository;
use App\Support\Kegiatan\Impor\UnggahPerkin;
use App\Support\Kegiatan\Impor\ValidasiImporMassal;
use App\Support\Stub\StubData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\RujukanDb;
use Tests\TestCase;

class ValidasiImporMassalTest extends TestCase
{
    use RefreshDatabase;

    private function impor(): array
    {
        $berkas = new UploadedFile(base_path('tests/fixtures/perkin-cascade-contoh.xlsx'), 'perkin-cascade-contoh.xlsx', null, null, true);

        return UnggahPerkin::unggah($berkas, StubData::currentUser())['impor'];
    }

    public function test_periksa_ulang_menandai_baris_yang_kini_tidak_sah(): void
    {
        $impor = $this->impor();
        $this->assertSame(4, $impor['jumlah_siap']);

        // Sejak pratinjau: satu nama kini sudah ada di tahun aktif.
        app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Workshop Media Pembelajaran Adaptif', 'leading' => '1.6', 'user_id' => 1, 'tahun_anggaran' => 2026]);
        $hasil = ValidasiImporMassal::periksaUlang($impor['id'], StubData::currentUser());
        $this->assertSame([['nomor_baris' => 5, 'status' => 'dilewati', 'pesan' => ['Kegiatan dengan nama ini sudah ada di TA 2026.']]], $hasil['berubah']);
        $this->assertSame([3, 3, 2, 4], [$hasil['siap'], $hasil['impor']['jumlah_siap'], $hasil['impor']['jumlah_dilewati'], $hasil['impor']['jumlah_gagal']]);
        $this->assertSame('dilewati', collect(app(ImporKegiatanRepository::class)->baris($impor['id']))->firstWhere('nomor_baris', 5)['status']);

        // Leading dinonaktifkan: baris yang tersisa gagal dengan pesan aturan ValidasiKegiatan.
        $leading = collect(app(LeadingRepository::class)->semua())->firstWhere('kode', '1.6');
        app(LeadingRepository::class)->simpan(['is_active' => false] + $leading, '1.6');
        $hasil = ValidasiImporMassal::periksaUlang($impor['id'], StubData::currentUser());
        $this->assertSame([4, 6, 10], array_column($hasil['berubah'], 'nomor_baris'));
        $this->assertSame(['gagal'], array_values(array_unique(array_column($hasil['berubah'], 'status'))));
        $this->assertSame([0, 0, 7], [$hasil['siap'], $hasil['impor']['jumlah_siap'], $hasil['impor']['jumlah_gagal']]);
        $this->assertSame(9, $hasil['impor']['jumlah_siap'] + $hasil['impor']['jumlah_dilewati'] + $hasil['impor']['jumlah_gagal']);

        app(ImporKegiatanRepository::class)->akhiri($impor['id'], 'selesai');
        $this->expectException(ValidationException::class);
        ValidasiImporMassal::periksaUlang($impor['id'], StubData::currentUser());
    }

    public function test_periksa_baris_untuk_simpan_satu_per_satu(): void
    {
        $namaAda = ValidasiImporMassal::namaAda();
        $data = ['nama_kegiatan' => 'Rapat Kerja Baru', 'nama_kegiatan_cover' => 'RAPAT KERJA BARU', 'leading' => '1.6'];
        $this->assertNull(ValidasiImporMassal::periksaBaris($data, $namaAda));
        $this->assertSame('dilewati', ValidasiImporMassal::periksaBaris(['nama_kegiatan' => 'rapat  kerja baru'] + $data, $namaAda)['status'], 'Baris kembar sesudahnya dilewati.');
        $gagal = ValidasiImporMassal::periksaBaris(['nama_kegiatan' => 'Lain Lagi', 'kode_sk' => 'SK.99'] + $data, $namaAda);
        $this->assertSame(['gagal', ['Kode SK SK.99 tidak ada di master TA 2026.']], [$gagal['status'], $gagal['pesan']]);

        $tahunLalu = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Hanya Tahun Lalu', 'tahun_anggaran' => 2025]);
        $this->assertArrayNotHasKey('hanya tahun lalu', ValidasiImporMassal::namaAda());
        $this->assertArrayHasKey('hanya tahun lalu', ValidasiImporMassal::namaAda(hanyaTahunAktif: false), "Kegiatan {$tahunLalu}.");
    }

    public function test_endpoint_periksa_ulang(): void
    {
        $impor = $this->impor();
        $this->postJson(route('kegiatan.impor_perkin.periksa', ['impor' => $impor['id']]))->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJson(['berubah' => [], 'siap' => 4, 'impor' => ['id' => $impor['id'], 'jumlah_siap' => 4]]);
        $this->postJson(route('kegiatan.impor_perkin.periksa', ['impor' => '00000000-0000-4000-8000-000000000000']))->assertNotFound();

        $lain = app(ImporKegiatanRepository::class)->mulai(['tahun' => 2026, 'jenis' => 'excel', 'nama_berkas' => 'a.xlsx', 'lembar' => null, 'user_id' => 99, 'pengguna' => 'Akun Lain'], []);
        $this->postJson(route('kegiatan.impor_perkin.periksa', ['impor' => $lain['id']]))->assertForbidden();
        app(ImporKegiatanRepository::class)->akhiri($impor['id'], 'dibatalkan');
        $this->postJson(route('kegiatan.impor_perkin.periksa', ['impor' => $impor['id']]))->assertStatus(422)->assertJsonStructure(['galat' => ['impor']]);
    }

    /** @return array<string, array{bool}> */
    public static function penyimpanan(): array
    {
        return ['tiruan' => [false], 'database' => [true]];
    }

    #[DataProvider('penyimpanan')]
    public function test_repositori_menandai_baris_dan_rekap(bool $database): void
    {
        if ($database) {
            RujukanDb::siapkan();
            $this->app->singleton(ImporKegiatanRepository::class, EloquentImporKegiatanRepository::class);
        }
        $repo = app(ImporKegiatanRepository::class);
        $impor = $repo->mulai(['tahun' => 2026, 'jenis' => 'perkin', 'nama_berkas' => 'a.xlsx', 'lembar' => 'Cascade', 'user_id' => 1, 'pengguna' => 'Muslimin, M.Pd.'], [
            ['nomor_baris' => 2, 'status' => 'siap', 'data' => ['nama_kegiatan' => 'A'], 'pesan' => [], 'peringatan' => []],
            ['nomor_baris' => 3, 'status' => 'siap', 'data' => ['nama_kegiatan' => 'B'], 'pesan' => [], 'peringatan' => []],
        ]);
        $repo->tandaiBaris($impor['id'], 3, 'gagal', ['Leading tidak aktif.']);
        $this->assertSame([1, 0, 1], [$repo->temukan($impor['id'])['jumlah_siap'], $repo->temukan($impor['id'])['jumlah_dilewati'], $repo->temukan($impor['id'])['jumlah_gagal']]);
        $this->assertSame(['gagal', ['Leading tidak aktif.']], [$repo->baris($impor['id'])[1]['status'], $repo->baris($impor['id'])[1]['pesan']]);

        $this->expectException(\InvalidArgumentException::class);
        $repo->tandaiBaris($impor['id'], 9, 'gagal', ['x']);
    }
}
