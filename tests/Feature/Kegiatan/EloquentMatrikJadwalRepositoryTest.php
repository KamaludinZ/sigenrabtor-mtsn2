<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Contracts\MatrikJadwalRepository;
use App\Livewire\Kegiatan\MatrikJadwal;
use App\Repositories\EloquentKegiatanRepository;
use App\Repositories\EloquentMatrikJadwalRepository;
use App\Support\Kegiatan\JadwalKegiatan;
use App\Support\Kegiatan\MatrikTahapan;
use App\Support\Stub\StubKegiatanRepository;
use App\Support\Stub\StubMatrikJadwalRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class EloquentMatrikJadwalRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private EloquentMatrikJadwalRepository $repo;

    private int $kegiatan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new EloquentMatrikJadwalRepository;
        $this->kegiatan = (new EloquentKegiatanRepository)->simpan(Arr::except(StubKegiatanRepository::contohMelebihiPagu(), ['id', 'status']));
    }

    public function test_belum_disimpan_memakai_butir_dan_baris_baku_seperti_tiruan(): void
    {
        $tiruan = new StubMatrikJadwalRepository(new StubKegiatanRepository);

        $this->assertSame($tiruan->matrik(1)->butir, $this->repo->matrik($this->kegiatan)->butir);
        $this->assertSame($tiruan->jadwal(1)->baris, $this->repo->jadwal($this->kegiatan)->baris);
        $this->assertCount(JadwalKegiatan::JUMLAH_BAKU, $this->repo->jadwal(999)->baris);
    }

    public function test_simpan_dan_baca_ulang_apa_adanya(): void
    {
        ['matrik' => $matrik, 'jadwal' => $jadwal] = StubMatrikJadwalRepository::contoh()[3];
        $versi = DB::table('activities')->find($this->kegiatan)->updated_at;

        $this->repo->simpan($this->kegiatan, MatrikTahapan::dari(['matrik' => $matrik]), JadwalKegiatan::dari(['jadwal' => $jadwal]));
        $this->assertSame($matrik, $this->repo->matrik($this->kegiatan)->butir);
        $this->assertSame($jadwal, $this->repo->jadwal($this->kegiatan)->baris);
        $this->assertSame($versi, DB::table('activities')->find($this->kegiatan)->updated_at, 'Versi Data Kegiatan tidak berubah.');

        // Simpan ulang mengganti seluruhnya; tahapan & jadwal yang dikosongkan tetap kosong (tidak kembali ke baku).
        $matrik['pelaksanaan'] = [];
        $this->repo->simpan($this->kegiatan, MatrikTahapan::dari(['matrik' => $matrik]), JadwalKegiatan::dari(['jadwal' => []]));
        $this->assertSame([], $this->repo->matrik($this->kegiatan)->butir['pelaksanaan']);
        $this->assertSame([], $this->repo->jadwal($this->kegiatan)->baris);
        $this->assertSame([4, 0], [DB::table('matrik_tahapan')->count(), DB::table('schedules')->count()]);

        // Kegiatan dihapus → matrik & jadwalnya ikut terhapus.
        DB::table('activities')->delete($this->kegiatan);
        $this->assertSame([0, 0], [DB::table('matrik_tahapan')->count(), DB::table('schedules')->count()]);
    }

    public function test_kegiatan_tidak_ada_ditolak_tanpa_menulis(): void
    {
        $this->expectException(RuntimeException::class);

        try {
            $this->repo->simpan(999, MatrikTahapan::dari([]), JadwalKegiatan::dari([]));
        } finally {
            $this->assertSame([0, 0], [DB::table('matrik_tahapan')->count(), DB::table('schedules')->count()]);
        }
    }

    public function test_halaman_matrik_jadwal_menyimpan_ke_database(): void
    {
        $this->app->singleton(KegiatanRepository::class, EloquentKegiatanRepository::class);
        $this->app->singleton(MatrikJadwalRepository::class, EloquentMatrikJadwalRepository::class);

        Livewire::test(MatrikJadwal::class, ['kegiatan' => $this->kegiatan])
            ->set('matrik.pelaksanaan.0.bulan', [11])
            ->set('jadwal.0.uraian', 'Rapat panitia')
            ->set('jadwal.0.mulai', '10')
            ->set('jadwal.0.sampai', '11')
            ->call('simpan')
            ->assertHasNoErrors();

        $this->assertSame([11], $this->repo->matrik($this->kegiatan)->butir['pelaksanaan'][0]['bulan']);
        $this->assertSame(['uraian' => 'Rapat panitia', 'bulan' => [10, 11]], $this->repo->jadwal($this->kegiatan)->baris[0]);
        Livewire::test(MatrikJadwal::class, ['kegiatan' => $this->kegiatan])->assertSet('jadwal.0', ['uraian' => 'Rapat panitia', 'mulai' => '10', 'sampai' => '11']);
    }
}
