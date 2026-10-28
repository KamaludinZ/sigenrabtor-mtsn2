<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Repositories\EloquentKegiatanRepository;
use App\Support\Dokumen\SusunanKak;
use App\Support\Kegiatan\TeksKhusus;
use App\Support\Kegiatan\UbahDataKegiatan;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\RujukanDb;
use Tests\TestCase;

class SkemaTeksKhususTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRASI = 'database/migrations/2026_10_28_000038_add_teks_khusus_to_activities_table.php';

    private const LEADING = ['id' => 1, 'name' => 'Muslimin, M.Pd.', 'role' => 'leading', 'leadings' => ['1.6']];

    /** @return array<string, array{array<string, mixed>}> Kasus bersama aturan PHP (TeksKhusus::galat) & database. */
    public static function kasus(): array
    {
        return [
            'sah' => [['persiapan' => ['Rapat persiapan panitia {nama_kegiatan}.', 'Menyiapkan ruang.'], 'pengarah' => 'Pengarah kegiatan {nama_kegiatan} adalah Kepala Madrasah.']],
            'paragraf saja' => [['analisis_hasil' => 'Menganalisis capaian kegiatan bersama tim penjamin mutu.']],
            'kosong' => [[]],
            'kunci asing' => [['latar' => 'Teks yang cukup panjang.']],
            'butir terlalu banyak' => [['persiapan' => array_fill(0, TeksKhusus::MAKS_BUTIR + 1, 'Butir persiapan.')]],
            'butir terpendek' => [['persiapan' => ['Rapat']]],
            'butir terlalu pendek' => [['persiapan' => ['Rapa']]],
            'paragraf terlalu panjang' => [['pengarah' => str_repeat('a', 1001)]],
            'paragraf terpanjang' => [['pengarah' => str_repeat('é', 1000)]],
            'penanda asing' => [['pengarah' => 'Pengarah kegiatan {nama_kegiatan} adalah {kepala}.']],
            'daftar berupa teks' => [['persiapan' => 'Rapat persiapan panitia.']],
            'paragraf berupa daftar' => [['pengarah' => ['Pengarah kegiatan adalah Kepala.']]],
            'daftar kosong' => [['persiapan' => []]],
            'butir bukan teks' => [['persiapan' => ['Rapat persiapan panitia.', 12345]]],
        ];
    }

    /** @param  array<string, mixed>  $teks */
    #[DataProvider('kasus')]
    public function test_aturan_database_sama_dengan_teks_khusus(array $teks): void
    {
        $sahPhp = $teks !== [] && TeksKhusus::galat($teks) === [];
        try {
            DB::transaction(fn () => DB::table('activities')->insert(['tahun_anggaran' => 2026, 'nama_kegiatan' => 'Rapat Kerja', 'teks_khusus' => json_encode((object) $teks, JSON_UNESCAPED_UNICODE)]));
            $sahDb = true;
        } catch (QueryException) {
            $sahDb = false;
        }
        $this->assertSame($sahPhp, $sahDb);
    }

    public function test_tersimpan_lewat_repositori_dan_ubah_data_kegiatan(): void
    {
        RujukanDb::siapkan();
        $this->app->singleton(KegiatanRepository::class, EloquentKegiatanRepository::class);
        $repo = app(KegiatanRepository::class);
        $id = $repo->simpan(['nama_kegiatan' => 'Kegiatan Rapat Kerja Madrasah', 'leading' => '1.6', 'user_id' => 1, 'tahun_anggaran' => 2026]);
        $this->assertSame([], $repo->temukan($id)['teks_khusus']);
        $this->assertNull(DB::table('activities')->where('id', $id)->value('teks_khusus'));

        // Urutan masukan apa pun → urutan baku BIDANG (jsonb mengurutkan ulang kunci).
        $hasil = UbahDataKegiatan::ubah($id, ['teks_khusus' => [
            'pengarah' => 'Pengarah kegiatan {nama_kegiatan} adalah Kepala Madrasah bersama Komite.',
            'persiapan' => "Rapat koordinasi panitia.\nMenyiapkan undangan peserta.",
        ]], self::LEADING);
        $this->assertSame(['teks_khusus'], $hasil['diubah']);
        $tersimpan = $repo->temukan($id)['teks_khusus'];
        $this->assertSame(['persiapan', 'pengarah'], array_keys($tersimpan));
        $this->assertSame(['Rapat koordinasi panitia.', 'Menyiapkan undangan peserta.'], $tersimpan['persiapan']);
        $this->assertStringContainsString('Pengarah kegiatan Rapat Kerja Madrasah adalah Kepala Madrasah bersama Komite.', json_encode(SusunanKak::dari($repo->temukan($id))->bagian, JSON_UNESCAPED_UNICODE));

        // Kiriman yang sama (urutan berbeda) = tanpa perubahan.
        $sama = UbahDataKegiatan::ubah($id, ['teks_khusus' => ['pengarah' => $tersimpan['pengarah'], 'persiapan' => $tersimpan['persiapan']]], self::LEADING);
        $this->assertSame([], $sama['diubah']);

        // Kembali ke teks baku = NULL; isian lain tidak menyentuh teks khusus.
        UbahDataKegiatan::ubah($id, ['teks_khusus' => ['persiapan' => $tersimpan['persiapan']]], self::LEADING);
        $repo->simpan(['tempat' => 'Aula Madrasah'], $id);
        $this->assertSame(['persiapan'], array_keys($repo->temukan($id)['teks_khusus']));
        UbahDataKegiatan::ubah($id, ['teks_khusus' => []], self::LEADING);
        $this->assertNull(DB::table('activities')->where('id', $id)->value('teks_khusus'));
        $this->assertSame([], TeksKhusus::dari($repo->temukan($id)));
    }

    public function test_migrasi_bisa_dibatalkan_dan_dijalankan_ulang(): void
    {
        // ALTER TABLE activities ditolak selama ada pemeriksaan rujukan tertunda: periksa seketika.
        RujukanDb::segera();
        $this->artisan('migrate:rollback', ['--path' => self::MIGRASI])->assertSuccessful();
        $this->assertFalse(Schema::hasColumn('activities', 'teks_khusus'));
        $this->assertSame(0, DB::table('pg_proc')->where('proname', 'teks_khusus_sah')->count());
        $this->artisan('migrate', ['--path' => self::MIGRASI])->assertSuccessful();
        $this->assertTrue(Schema::hasColumn('activities', 'teks_khusus'));
    }
}
