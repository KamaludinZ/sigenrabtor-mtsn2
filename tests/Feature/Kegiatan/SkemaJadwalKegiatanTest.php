<?php

namespace Tests\Feature\Kegiatan;

use App\Livewire\Kegiatan\MatrikJadwal;
use App\Repositories\EloquentMatrikJadwalRepository;
use App\Support\Kegiatan\JadwalKegiatan;
use App\Support\Kegiatan\MatrikTahapan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SkemaJadwalKegiatanTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRASI = 'database/migrations/2026_10_28_000019_add_waktu_and_baku_to_schedules.php';

    private int $kegiatan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kegiatan = DB::table('activities')->insertGetId(['tahun_anggaran' => 2026, 'nama_kegiatan' => 'Kegiatan Uji', 'kode_leading' => '1.6']);
    }

    /** @param  list<array{uraian: string, bulan: list<int>}>  $baris */
    private function simpan(array $baris): void
    {
        (new EloquentMatrikJadwalRepository)->simpan($this->kegiatan, MatrikTahapan::dari([]), JadwalKegiatan::dari(['jadwal' => $baris]));
    }

    public function test_kolom_sesuai_prd(): void
    {
        $this->assertTrue(Schema::hasColumns('schedules', ['id', 'activity_id', 'urutan', 'uraian', 'bulan', 'bulan_mulai', 'bulan_selesai', 'is_baku']));
        $this->assertSame(MatrikJadwal::JADWAL_BAKU, JadwalKegiatan::URAIAN_BAKU);
        $this->assertCount(JadwalKegiatan::JUMLAH_BAKU, JadwalKegiatan::URAIAN_BAKU);
    }

    public function test_waktu_pelaksanaan_terhitung_dan_baris_baku_tercatat(): void
    {
        $this->simpan([
            ['uraian' => 'Pembentukan kepanitiaan', 'bulan' => [6]],
            ['uraian' => 'Rapat  KOORDINASI panitia', 'bulan' => [6, 7]],
            ['uraian' => 'Pelaksanaan workshop kurikulum', 'bulan' => [7, 8, 9]],
            ['uraian' => '', 'bulan' => []],
        ]);

        $this->assertSame(
            [[1, 6, 6, true], [2, 6, 7, true], [3, 7, 9, false], [4, null, null, false]],
            DB::table('schedules')->where('activity_id', $this->kegiatan)->orderBy('urutan')->get()
                ->map(fn ($b) => [$b->urutan, $b->bulan_mulai, $b->bulan_selesai, $b->is_baku])->all(),
        );
        $baris = (new EloquentMatrikJadwalRepository)->jadwal($this->kegiatan)->baris[1];
        $this->assertSame(['Rapat  KOORDINASI panitia', [6, 7]], [$baris['uraian'], $baris['bulan']], 'Kolom tambahan tidak mengubah bentuk data repositori.');

        // Jadwal per bulan bisa dicari langsung dari kolom terhitung.
        $this->assertSame(2, DB::table('schedules')->where('bulan_mulai', '<=', 7)->where('bulan_selesai', '>=', 7)->count());
        $this->assertTrue(JadwalKegiatan::uraianBaku(' penyusunan dan   penyerahan laporan'));
        $this->assertFalse(JadwalKegiatan::uraianBaku('Penyusunan laporan'));
    }

    public function test_migrasi_mengisi_baris_lama_dan_bisa_dibatalkan(): void
    {
        $this->artisan('migrate:rollback', ['--path' => self::MIGRASI])->assertSuccessful();
        $this->assertFalse(Schema::hasColumn('schedules', 'is_baku'));
        DB::table('schedules')->insert([
            ['activity_id' => $this->kegiatan, 'urutan' => 1, 'uraian' => 'Evaluasi kegiatan', 'bulan' => '[11]'],
            ['activity_id' => $this->kegiatan, 'urutan' => 2, 'uraian' => 'Lomba poster', 'bulan' => '[]'],
        ]);

        $this->artisan('migrate', ['--path' => self::MIGRASI])->assertSuccessful();

        $this->assertSame([[true, 11, 11], [false, null, null]], DB::table('schedules')->orderBy('urutan')->get()->map(fn ($b) => [$b->is_baku, $b->bulan_mulai, $b->bulan_selesai])->all());
    }
}
