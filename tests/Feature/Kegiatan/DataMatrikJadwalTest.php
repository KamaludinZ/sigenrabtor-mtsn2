<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Contracts\MatrikJadwalRepository;
use App\Contracts\RabRepository;
use App\Repositories\EloquentKegiatanRepository;
use App\Repositories\EloquentMatrikJadwalRepository;
use App\Repositories\EloquentRabRepository;
use App\Support\Kegiatan\DataMatrikJadwal;
use App\Support\Kegiatan\JadwalKegiatan;
use App\Support\Kegiatan\MatrikTahapan;
use App\Support\Stub\StubKegiatanRepository;
use App\Support\Stub\StubMatrikJadwalRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Tests\TestCase;

class DataMatrikJadwalTest extends TestCase
{
    use RefreshDatabase;

    public function test_acuan_bulan_dan_bulan_laporan(): void
    {
        $this->assertNull(DataMatrikJadwal::acuan([], 11));
        $this->assertSame(['persiapan' => 6, 'pelaksanaan' => [7, 8], 'laporan' => 8], DataMatrikJadwal::acuan([7, 8], null));
        $this->assertSame(['persiapan' => 1, 'pelaksanaan' => [1], 'laporan' => 2], DataMatrikJadwal::acuan([1], 2), 'Persiapan tidak sebelum Januari.');

        $this->assertSame(11, DataMatrikJadwal::bulanLaporan(['tanggal_laporan' => '2026-11-27'], 2026));
        $this->assertSame(12, DataMatrikJadwal::bulanLaporan(['tanggal_laporan' => '2027-01-10'], 2026), 'Laporan tahun berikutnya ditandai Desember.');
        $this->assertNull(DataMatrikJadwal::bulanLaporan(['tanggal_laporan' => '27/11/2026'], 2026));
        $this->assertNull(DataMatrikJadwal::bulanLaporan([], 2026));
    }

    public function test_endpoint_contoh_tersalin_beserta_temuan_konsistensi(): void
    {
        $respons = $this->getJson(route('kegiatan.matrik_jadwal', ['kegiatan' => 3]))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('kegiatan_id', 3)
            ->assertJsonPath('tahun', 2026)
            ->assertJsonPath('dapat_diubah', true)
            ->assertJsonPath('pelaksanaan.bulan', [11])
            ->assertJsonPath('pelaksanaan.teks_bulan', 'November 2026')
            ->assertJsonPath('pelaksanaan.bulan_laporan', 11)
            ->assertJsonPath('acuan', ['persiapan' => 10, 'pelaksanaan' => [11], 'laporan' => 11])
            ->assertJsonPath('matrik.jumlah_tanda', 6)
            ->assertJsonPath('matrik.lengkap', true)
            ->assertJsonPath('matrik.tahapan.2.bulan', [11, 12])
            ->assertJsonPath('jadwal.maks_baris', JadwalKegiatan::MAKS_BARIS)
            ->assertJsonPath('konsistensi.jumlah_peringatan', 5)
            ->assertJsonCount(6, 'konsistensi.temuan');

        $this->assertSame(array_keys(MatrikTahapan::TAHAPAN), array_column($respons->json('matrik.tahapan'), 'kunci'));
        $this->assertSame(
            ['nomor' => 3, 'uraian' => 'Persiapan sarana dan bahan kegiatan', 'bulan' => [10, 11], 'mulai' => 10, 'sampai' => 11, 'waktu' => 'Oktober – November 2026', 'baku' => true],
            $respons->json('jadwal.baris.2'),
        );
        $this->assertSame([false, 'Juli 2026'], [$respons->json('jadwal.baris.3.baku'), $respons->json('jadwal.baris.3.waktu')]);
    }

    public function test_belum_pernah_disimpan_hak_baca_dan_terkunci(): void
    {
        // Contoh 1 belum menyimpan matrik & jadwal: butir baku tanpa tanda & 6 baris baku kosong.
        $this->getJson(route('kegiatan.matrik_jadwal', ['kegiatan' => 1]))
            ->assertJsonPath('matrik.jumlah_tanda', 0)
            ->assertJsonPath('matrik.lengkap', false)
            ->assertJsonPath('matrik.tahapan.0.butir.0', ['uraian' => MatrikTahapan::BUTIR_BAKU['persiapan'][0], 'bulan' => []])
            ->assertJsonCount(JadwalKegiatan::JUMLAH_BAKU, 'jadwal.baris')
            ->assertJsonPath('jadwal.baris.0', ['nomor' => 1, 'uraian' => '', 'bulan' => [], 'mulai' => null, 'sampai' => null, 'waktu' => null, 'baku' => false])
            ->assertJsonPath('jadwal.jumlah_terisi', 0);

        $this->getJson(route('kegiatan.matrik_jadwal', ['kegiatan' => 999]))->assertNotFound();
        $lain = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Kegiatan Leading Lain', 'leading' => '1.2', 'user_id' => 2]);
        $this->getJson(route('kegiatan.matrik_jadwal', ['kegiatan' => $lain]))->assertForbidden();
        $this->withSession(['stub_role' => 'admin'])->getJson(route('kegiatan.matrik_jadwal', ['kegiatan' => $lain]))
            ->assertOk()
            ->assertJsonPath('acuan', null)
            ->assertJsonPath('konsistensi.temuan.0.kunci', 'tanpa-bulan');

        $repo = app(KegiatanRepository::class);
        $repo->simpan(['status' => 'final'] + $repo->temukan(3), 3);
        $this->getJson(route('kegiatan.matrik_jadwal', ['kegiatan' => 3]))->assertJsonPath('status', 'final')->assertJsonPath('dapat_diubah', false);
    }

    public function test_hasil_database_sama_dengan_tiruan(): void
    {
        $tiruan = DataMatrikJadwal::untuk(app(KegiatanRepository::class)->temukan(3));

        $this->app->singleton(KegiatanRepository::class, EloquentKegiatanRepository::class);
        $this->app->singleton(RabRepository::class, EloquentRabRepository::class);
        $this->app->singleton(MatrikJadwalRepository::class, EloquentMatrikJadwalRepository::class);
        $id = app(KegiatanRepository::class)->simpan(Arr::except(StubKegiatanRepository::contohMelebihiPagu(), ['id', 'status']));
        $contoh = StubMatrikJadwalRepository::contoh()[3];
        app(MatrikJadwalRepository::class)->simpan($id, MatrikTahapan::dari($contoh), JadwalKegiatan::dari($contoh));

        $this->assertSame(['kegiatan_id' => $id] + $tiruan, DataMatrikJadwal::untuk(app(KegiatanRepository::class)->temukan($id)));
    }
}
