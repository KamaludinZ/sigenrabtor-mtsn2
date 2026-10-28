<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\DrafDokumenRepository;
use App\Contracts\HasilDokumenRepository;
use App\Contracts\KegiatanRepository;
use App\Contracts\MatrikJadwalRepository;
use App\Contracts\RabRepository;
use App\Repositories\EloquentDrafDokumenRepository;
use App\Repositories\EloquentHasilDokumenRepository;
use App\Repositories\EloquentKegiatanRepository;
use App\Repositories\EloquentMatrikJadwalRepository;
use App\Repositories\EloquentRabRepository;
use App\Support\Dokumen\DrafDokumen;
use App\Support\Dokumen\HasilKakTor;
use App\Support\Dokumen\PembuatDokumen;
use App\Support\Dokumen\SusunanKak;
use App\Support\Dokumen\SusunanOtomatis;
use App\Support\Dokumen\SusunanRab;
use App\Support\Dokumen\TeksCover;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\RujukanDb;
use Tests\TestCase;

class PembuatDokumenTest extends TestCase
{
    use RefreshDatabase;

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
        $this->app->singleton(HasilDokumenRepository::class, EloquentHasilDokumenRepository::class);
        $this->app->singleton(DrafDokumenRepository::class, EloquentDrafDokumenRepository::class);
        // RAB & matrik juga dari database agar id kegiatan baru tidak bertemu contoh tiruan berid sama.
        $this->app->singleton(RabRepository::class, EloquentRabRepository::class);
        $this->app->singleton(MatrikJadwalRepository::class, EloquentMatrikJadwalRepository::class);
    }

    private function kegiatanBaru(array $data = []): int
    {
        return app(KegiatanRepository::class)->simpan($data + ['nama_kegiatan' => 'Rapat Kerja Madrasah', 'leading' => '1.6', 'user_id' => 1, 'tahun_anggaran' => 2026, 'tujuan' => 'menyusun rencana kerja tahunan.', 'sumber_dana' => [['sumber' => 'Komite', 'pagu' => 3_000_000]]]);
    }

    #[DataProvider('penyimpanan')]
    public function test_susun_simpan_dan_susun_ulang(bool $database): void
    {
        $database && $this->pakaiDatabase();
        $repo = app(KegiatanRepository::class);
        $id = $this->kegiatanBaru();
        $this->assertNull(PembuatDokumen::data($id, 'lengkap', null, self::LEADING)['dokumen']);

        $hasil = PembuatDokumen::susun($id, 'lengkap', null, self::LEADING);
        $dokumen = $hasil['dokumen'];
        $this->assertSame([true, true, 'Dokumen Set lengkap (cover, KAK/TOR, RAB) · Komite disusun dari data terbaru.'], [$hasil['baru'], $hasil['berubah'], $hasil['pesan']]);
        $this->assertSame(['lengkap', 'Komite', 'draf', 'Draf', true, 9], [$dokumen['jenis'], $dokumen['sumber'], $dokumen['status'], $dokumen['label_status'], $dokumen['bertanda_draf'], $dokumen['jumlah_bagian']]);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $dokumen['sidik_isi']);
        $this->assertSame('sesuai', $dokumen['keadaan']['keadaan']);
        $this->assertSame('KAK-TOR_RAB_Rapat_Kerja_Madrasah_KOMITE_2026_DRAF.pdf', $dokumen['nama_berkas']);
        $this->assertContains('Belum disetujui admin (status Draf).', $dokumen['alasan_draf']);
        $kegiatan = $repo->temukan($id);
        $kak = SusunanKak::dari($kegiatan);
        $this->assertSame(HasilKakTor::bagian($kak, SusunanOtomatis::dari($kak, SusunanRab::dari($kegiatan), $kegiatan, self::LEADING)), $dokumen['bagian'], 'Bagian tersimpan = hasil susunan.');
        $this->assertSame(['No', 'Uraian', 'Vol', 'Satuan', 'Harga Satuan (Rp)', 'Jumlah (Rp)'], $dokumen['rab']['tabel']['kolom']);
        $this->assertSame([0, 'Kementerian'], [$dokumen['rab']['tabel']['total'], $dokumen['rab']['kepala'][0]['label']]);
        $this->assertEquals(TeksCover::nilai($kegiatan, 'Komite'), $dokumen['cover']);
        $this->assertSame([1, 'Muslimin, M.Pd.'], [$dokumen['user_id'], $dokumen['oleh']]);

        // Disusun lagi tanpa perubahan data: hasil yang sama, tanpa perubahan.
        $this->travel(1)->seconds();
        $lagi = PembuatDokumen::susun($id, 'lengkap', 'Komite', self::LEADING);
        $this->assertSame([false, false, $dokumen['id']], [$lagi['baru'], $lagi['berubah'], $lagi['dokumen']['id']]);
        $this->assertStringContainsString('sudah sesuai data terbaru', $lagi['pesan']);
        $this->assertGreaterThan($dokumen['disusun_pada'], $lagi['dokumen']['disusun_pada']);

        // Data berubah → hasil tersimpan "berubah"; disusun ulang mengikuti data terbaru.
        $repo->simpan(['tempat' => 'Aula Utama MTsN 2 Kota Malang'] + $repo->temukan($id), $id);
        $this->assertSame('berubah', PembuatDokumen::data($id, 'lengkap', null, self::LEADING)['dokumen']['keadaan']['keadaan']);
        $ulang = PembuatDokumen::susun($id, 'lengkap', null, self::LEADING);
        $this->assertSame([false, true], [$ulang['baru'], $ulang['berubah']]);
        $this->assertNotSame($dokumen['sidik_isi'], $ulang['dokumen']['sidik_isi']);
        $this->assertStringContainsString('Aula Utama MTsN 2 Kota Malang', json_encode($ulang['dokumen']['bagian'][4]['isi'], JSON_UNESCAPED_UNICODE));
        $this->assertSame('sesuai', $ulang['dokumen']['keadaan']['keadaan']);

        // Jenis lain: RAB tanpa bagian & cover, KAK tanpa RAB & cover.
        $this->travel(1)->seconds();
        $rab = PembuatDokumen::susun($id, 'RAB', 'Komite', self::LEADING)['dokumen'];
        $this->assertSame([[], null, 'Rencana Anggaran Biaya Kegiatan Rapat Kerja Madrasah MTsN 2 Kota Malang Tahun 2026'], [$rab['bagian'], $rab['cover'], $rab['rab']['judul']]);
        $this->travel(1)->seconds();
        $kakSaja = PembuatDokumen::susun($id, 'KAK', null, self::LEADING)['dokumen'];
        $this->assertSame([9, null, null], [count($kakSaja['bagian']), $kakSaja['rab'], $kakSaja['cover']]);
        $this->assertStringStartsWith('KAK-TOR_Rapat_Kerja', $kakSaja['nama_berkas']);
        $this->assertSame(['KAK', 'RAB', 'lengkap'], array_column(PembuatDokumen::data($id, 'lengkap', null, self::LEADING)['semua'], 'jenis'));
    }

    #[DataProvider('penyimpanan')]
    public function test_aturan_jenis_sumber_dan_akses(bool $database): void
    {
        $database && $this->pakaiDatabase();
        $id = $this->kegiatanBaru();
        foreach ([['BOS', null, 'jenis'], ['KAK', 'DIPA', 'sumber']] as [$jenis, $sumber, $galat]) {
            try {
                PembuatDokumen::susun($id, $jenis, $sumber, self::LEADING);
                $this->fail("{$jenis}/{$sumber} harus ditolak.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey($galat, $e->errors());
            }
        }
        $tanpa = $this->kegiatanBaru(['nama_kegiatan' => 'Rapat Tanpa Dana', 'sumber_dana' => []]);
        try {
            PembuatDokumen::susun($tanpa, 'lengkap', null, self::LEADING);
            $this->fail('Set lengkap tanpa sumber dana harus ditolak.');
        } catch (ValidationException $e) {
            $this->assertSame('Pilih sumber dana kegiatan dulu — Set lengkap (cover, KAK/TOR, RAB) disusun per sumber dana.', $e->errors()['sumber'][0]);
        }
        $this->assertNull(PembuatDokumen::susun($tanpa, 'KAK+TOR', null, self::LEADING)['dokumen']['sumber']);

        $lain = $this->kegiatanBaru(['nama_kegiatan' => 'Lomba Kreativitas Siswa', 'leading' => '1.2', 'user_id' => 4]);
        $this->expectException(AuthorizationException::class);
        PembuatDokumen::susun($lain, 'KAK', null, self::LEADING);
    }

    public function test_penyimpanan_database_dan_draf_berbagi_baris(): void
    {
        $this->pakaiDatabase();
        $id = $this->kegiatanBaru();
        DrafDokumen::simpan($id, 'lengkap', null, self::LEADING);
        $dokumen = PembuatDokumen::susun($id, 'lengkap', null, self::LEADING)['dokumen'];

        $baris = DB::table('documents')->where('activity_id', $id)->where('jenis', 'lengkap')->first();
        $this->assertSame([(int) $dokumen['id'], $dokumen['sidik_isi']], [(int) $baris->id, trim($baris->sidik_isi)]);
        $this->assertNotNull($baris->disusun_pada);
        $this->assertSame(['draf', 'hasil'], array_keys(json_decode($baris->konten, true)), 'Draf & hasil satu baris dokumen.');
        $this->assertSame(9, DB::table('dokumen_bagian')->where('document_id', $baris->id)->count());

        // Disusun ulang mengganti bagian; menghapus draf tidak membuang hasil susunan.
        PembuatDokumen::susun($id, 'lengkap', null, self::LEADING);
        $this->assertSame(9, DB::table('dokumen_bagian')->where('document_id', $baris->id)->count());
        $this->assertTrue(app(DrafDokumenRepository::class)->hapus($id, 'lengkap', 'Komite'));
        $this->assertTrue(DB::table('documents')->where('id', $baris->id)->exists());
        $this->assertSame(['hasil'], array_keys(json_decode(DB::table('documents')->where('id', $baris->id)->value('konten'), true)));
        $this->assertNotNull(app(HasilDokumenRepository::class)->temukan($id, 'lengkap', 'Komite'));

        // Kegiatan tak ada / sumber tanpa baris anggaran → RuntimeException.
        $repo = app(HasilDokumenRepository::class);
        $rekam = PembuatDokumen::rekam(app(KegiatanRepository::class)->temukan($id), 'RAB', 'Komite', self::LEADING);
        foreach ([[999, $rekam, 'Kegiatan tidak ditemukan.'], [$id, ['sumber' => 'DIPA'] + $rekam, 'Sumber dana DIPA belum tersimpan untuk kegiatan ini.'], [$id, ['status' => 'asing'] + $rekam, EloquentHasilDokumenRepository::PESAN_GAGAL]] as [$kegiatan, $isi, $pesan]) {
            try {
                $repo->simpan($kegiatan, $isi);
                $this->fail("Harus ditolak: {$pesan}");
            } catch (RuntimeException $e) {
                $this->assertSame($pesan, $e->getMessage());
            }
        }
    }

    public function test_endpoint_susun_dan_hasil(): void
    {
        $id = $this->kegiatanBaru();

        $this->getJson(route('kegiatan.dokumen.hasil', ['kegiatan' => $id]))->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJson(['jenis' => 'lengkap', 'sumber' => 'Komite', 'dokumen' => null, 'semua' => []]);
        $baru = $this->postJson(route('kegiatan.dokumen.susun', ['kegiatan' => $id]), ['jenis' => 'lengkap'])->assertCreated()
            ->assertJsonPath('baru', true)
            ->assertJsonPath('dokumen.jumlah_bagian', 9)
            ->assertJsonPath('dokumen.bagian.0.judul', 'Latar Belakang')
            ->assertJsonPath('dokumen.keadaan.keadaan', 'sesuai')
            ->assertJsonPath('dokumen.rab.tabel.total', 0);
        $this->postJson(route('kegiatan.dokumen.susun', ['kegiatan' => $id]), ['jenis' => 'lengkap', 'sumber' => 'Komite'])->assertOk()
            ->assertJsonPath('dokumen.id', $baru->json('dokumen.id'))
            ->assertJsonPath('berubah', false);
        $this->getJson(route('kegiatan.dokumen.hasil', ['kegiatan' => $id, 'jenis' => 'lengkap']))->assertOk()
            ->assertJsonPath('dokumen.id', $baru->json('dokumen.id'))
            ->assertJsonPath('dokumen.tautan_cetak', route('kegiatan.cetak', ['kegiatan' => $id, 'sumber' => 'Komite']))
            ->assertJsonCount(1, 'semua');

        $this->postJson(route('kegiatan.dokumen.susun', ['kegiatan' => $id]), ['jenis' => 'BOS'])->assertStatus(422)->assertJsonStructure(['pesan', 'galat' => ['jenis']]);
        $this->getJson(route('kegiatan.dokumen.hasil', ['kegiatan' => $id, 'jenis' => 'RAB', 'sumber' => 'DIPA']))->assertStatus(422);
        $tanpa = $this->kegiatanBaru(['nama_kegiatan' => 'Rapat Tanpa Dana', 'sumber_dana' => []]);
        $this->getJson(route('kegiatan.dokumen.hasil', ['kegiatan' => $tanpa]))->assertOk()->assertJsonPath('jenis', 'KAK+TOR');
        $lain = $this->kegiatanBaru(['nama_kegiatan' => 'Lomba Kreativitas Siswa', 'leading' => '1.2', 'user_id' => 4]);
        $this->postJson(route('kegiatan.dokumen.susun', ['kegiatan' => $lain]), ['jenis' => 'KAK'])->assertForbidden();
        $this->getJson(route('kegiatan.dokumen.hasil', ['kegiatan' => $lain]))->assertForbidden();
        $this->postJson(route('kegiatan.dokumen.susun', ['kegiatan' => 999]), ['jenis' => 'KAK'])->assertNotFound();
        $this->getJson(route('kegiatan.dokumen.hasil', ['kegiatan' => 999]))->assertNotFound();
    }
}
