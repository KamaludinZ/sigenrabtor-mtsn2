<?php

namespace Tests\Feature\Kegiatan;

use App\Livewire\Kegiatan\MatrikJadwal;
use App\Repositories\EloquentMatrikJadwalRepository;
use App\Support\Kegiatan\JadwalKegiatan;
use App\Support\Kegiatan\MatrikTahapan;
use App\Support\Stub\StubMatrikJadwalRepository;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SkemaMatrikTahapanTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRASI = 'database/migrations/2026_10_28_000018_tighten_matrik_tahapan_and_schedules.php';

    private int $kegiatan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kegiatan = DB::table('activities')->insertGetId(['tahun_anggaran' => 2026, 'nama_kegiatan' => 'Kegiatan Uji', 'kode_leading' => '1.6']);
    }

    private function butir(array $isi): void
    {
        DB::table('matrik_tahapan')->insert(['activity_id' => $this->kegiatan, 'tahapan' => 'persiapan', 'urutan' => 1, 'uraian' => 'Rapat', 'bulan' => '[]', ...$isi]);
    }

    private function jadwal(array $isi): void
    {
        DB::table('schedules')->insert(['activity_id' => $this->kegiatan, 'urutan' => 1, 'uraian' => '', 'bulan' => '[]', ...$isi]);
    }

    private function ditolak(callable $aksi, string $catatan): void
    {
        try {
            DB::transaction($aksi);
        } catch (QueryException) {
            $this->addToAssertionCount(1);

            return;
        }
        $this->fail("Seharusnya ditolak database: {$catatan}");
    }

    public function test_daftar_bulan_sama_dengan_rapikan_bulan(): void
    {
        foreach ([[], [1], [1, 2, 12], [3, 3], [5, 3], [0], [13], ['3'], [1.5], [7, 9]] as $bulan) {
            $sah = MatrikTahapan::rapikanBulan($bulan) === $bulan;
            $this->assertSame($sah, DB::selectOne('select daftar_bulan_sah(?::jsonb) as sah', [json_encode($bulan)])->sah, json_encode($bulan));
        }
        foreach (['{}', '3', '"Juli"', 'null'] as $bukanDaftar) {
            $this->assertFalse(DB::selectOne('select daftar_bulan_sah(?::jsonb) as sah', [$bukanDaftar])->sah, $bukanDaftar);
        }
        $this->assertFalse(DB::selectOne("select daftar_bulan_sah('[7,9]'::jsonb, true) as sah")->sah, 'Bulan jadwal harus bersambung.');
        $this->assertTrue(DB::selectOne("select daftar_bulan_sah('[7,8,9]'::jsonb, true) as sah")->sah);
    }

    public function test_aturan_butir_matrik(): void
    {
        $this->butir(['bulan' => '[7,8]']);
        $this->butir(['urutan' => 2, 'uraian' => str_repeat('a', MatrikTahapan::MAKS_URAIAN), 'bulan' => '[7,9]']);

        foreach ([
            'bulan ganda' => ['urutan' => 3, 'bulan' => '[7,7]'],
            'bulan 13' => ['urutan' => 3, 'bulan' => '[13]'],
            'bulan teks' => ['urutan' => 3, 'bulan' => '["7"]'],
            'uraian kosong' => ['urutan' => 3, 'uraian' => ''],
            'uraian berspasi tepi' => ['urutan' => 3, 'uraian' => ' Rapat'],
            'uraian berbaris baru di akhir' => ['urutan' => 3, 'uraian' => "Rapat\n"],
            'uraian terlalu panjang' => ['urutan' => 3, 'uraian' => str_repeat('a', MatrikTahapan::MAKS_URAIAN + 1)],
            'urutan 0' => ['urutan' => 0],
            'tahapan asing' => ['urutan' => 3, 'tahapan' => 'penutupan'],
        ] as $catatan => $isi) {
            $this->ditolak(fn () => $this->butir($isi), $catatan);
        }
        $this->assertSame(2, DB::table('matrik_tahapan')->count());
    }

    public function test_aturan_baris_jadwal(): void
    {
        $this->jadwal([]);
        $this->jadwal(['urutan' => 2, 'uraian' => 'Pelaksanaan workshop', 'bulan' => '[7,8,9]']);
        $this->jadwal(['urutan' => JadwalKegiatan::MAKS_BARIS, 'uraian' => str_repeat('a', JadwalKegiatan::MAKS_URAIAN)]);

        foreach ([
            'bulan tidak bersambung' => ['urutan' => 3, 'uraian' => 'Evaluasi', 'bulan' => '[7,9]'],
            'bulan tanpa uraian' => ['urutan' => 3, 'bulan' => '[7]'],
            'uraian berspasi tepi' => ['urutan' => 3, 'uraian' => 'Evaluasi '],
            'uraian terlalu panjang' => ['urutan' => 3, 'uraian' => str_repeat('a', JadwalKegiatan::MAKS_URAIAN + 1)],
            'melewati batas baris' => ['urutan' => JadwalKegiatan::MAKS_BARIS + 1],
            'bulan bukan daftar' => ['urutan' => 3, 'bulan' => '{"mulai": 7}'],
        ] as $catatan => $isi) {
            $this->ditolak(fn () => $this->jadwal($isi), $catatan);
        }
        $this->assertSame(3, DB::table('schedules')->count());
    }

    public function test_isian_halaman_dan_contoh_tersimpan_utuh(): void
    {
        // Baris yang lolos aturan halaman (uraian dirapikan, mulai..sampai → bulan bersambung) diterima database.
        $repo = new EloquentMatrikJadwalRepository;
        $contoh = StubMatrikJadwalRepository::contoh()[3];
        $jadwal = MatrikJadwal::jadwalUntukDisimpan([['uraian' => '  Rapat   koordinasi ', 'mulai' => 9, 'sampai' => 11], ['uraian' => '', 'mulai' => null, 'sampai' => null]]);
        $repo->simpan($this->kegiatan, MatrikTahapan::dari($contoh), JadwalKegiatan::dari(['jadwal' => $jadwal]));

        $this->assertSame(MatrikTahapan::dari($contoh)->butir, $repo->matrik($this->kegiatan)->butir);
        $this->assertSame([['uraian' => 'Rapat koordinasi', 'bulan' => [9, 10, 11]], ['uraian' => '', 'bulan' => []]], $repo->jadwal($this->kegiatan)->baris);
    }

    public function test_migrasi_bisa_dibatalkan(): void
    {
        $this->artisan('migrate:rollback', ['--path' => self::MIGRASI])->assertSuccessful();
        $this->assertFalse(DB::table('pg_proc')->where('proname', 'daftar_bulan_sah')->exists());
        $this->jadwal(['uraian' => 'Bebas', 'bulan' => '[9,7]']);

        DB::table('schedules')->delete();
        $this->artisan('migrate', ['--path' => self::MIGRASI])->assertSuccessful();
        $this->ditolak(fn () => $this->jadwal(['uraian' => 'Bebas', 'bulan' => '[9,7]']), 'aturan kembali berlaku');
    }
}
