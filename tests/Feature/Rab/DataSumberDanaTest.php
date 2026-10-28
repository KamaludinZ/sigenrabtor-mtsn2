<?php

namespace Tests\Feature\Rab;

use App\Contracts\DataPaguRepository;
use App\Contracts\KegiatanRepository;
use App\Contracts\RabRepository;
use App\Repositories\EloquentDataPaguRepository;
use App\Repositories\EloquentKegiatanRepository;
use App\Repositories\EloquentRabRepository;
use App\Support\Rab\DataSumberDana;
use App\Support\Rab\SimpanSumberDana;
use App\Support\Stub\StubDataPaguRepository;
use App\Support\Stub\StubKegiatanRepository;
use App\Support\Stub\StubRab;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DataSumberDanaTest extends TestCase
{
    use RefreshDatabase;

    public function test_semua_sumber_dana_kegiatan_beserta_pagu_rab_dan_data_pagu(): void
    {
        $respons = $this->getJson(route('kegiatan.sumber_dana', ['kegiatan' => 2]))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('kegiatan_id', 2)
            ->assertJsonPath('dapat_diubah', true)
            ->assertJsonPath('dipilih', ['DIPA', 'Komite'])
            ->assertJsonPath('data_pagu', ['id' => '9b2f1c3e-0002-4a51-8c11-000000000002', 'nama_kegiatan' => 'Supervisi Akademik dan Refleksi Pembelajaran', 'tertaut' => false]);

        $this->assertSame([
            'sumber' => 'DIPA', 'kunci' => 'dipa', 'keterangan' => 'Daftar Isian Pelaksanaan Anggaran (APBN).', 'label_rab' => 'RAB DIPA',
            'sebutan_dokumen' => 'DIPA MTsN 2 Kota Malang Tahun 2026', 'dipilih' => true, 'pagu' => 6_000_000, 'pagu_terbilang' => 'Enam Juta Rupiah',
            'total_rab' => 5_400_000, 'jumlah_item' => 6, 'status_pagu' => 'sisa', 'selisih_pagu' => 600_000, 'keterangan_pagu' => 'Sisa pagu Rp600.000',
            'terlepas' => false, 'pagu_data' => 6_000_000, 'selaras' => true,
        ], $respons->json('sumber.0'));
        $this->assertSame(['KOMITE MTsN 2 Kota Malang Tahun 2026', 'sesuai', true], [$respons->json('sumber.1.sebutan_dokumen'), $respons->json('sumber.1.status_pagu'), $respons->json('sumber.1.selaras')]);
        $this->assertSame(5_400_000 + $respons->json('sumber.1.total_rab'), $respons->json('gabungan.total'));
        $this->assertSame(8_500_000, $respons->json('gabungan.pagu'));
    }

    public function test_sumber_tidak_dipilih_pagu_kosong_dan_rab_terlepas(): void
    {
        // Contoh 1: hanya Komite; DIPA tetap tercantum sebagai pilihan.
        $this->getJson(route('kegiatan.sumber_dana', ['kegiatan' => 1]))
            ->assertJsonPath('dipilih', ['Komite'])
            ->assertJsonPath('sumber.0.sumber', 'DIPA')
            ->assertJsonPath('sumber.0.dipilih', false)
            ->assertJsonPath('sumber.0.total_rab', 0)
            ->assertJsonPath('sumber.0.status_pagu', null)
            ->assertJsonPath('sumber.0.terlepas', false)
            ->assertJsonPath('sumber.0.selaras', true);

        // Contoh 3: DIPA dipilih tanpa pagu (Data Pagu pun tanpa DIPA) → tidak selaras.
        $this->getJson(route('kegiatan.sumber_dana', ['kegiatan' => 3]))
            ->assertJsonPath('sumber.0.status_pagu', 'tanpa_pagu')
            ->assertJsonPath('sumber.0.keterangan_pagu', 'Pagu belum diisi')
            ->assertJsonPath('sumber.0.selaras', false)
            ->assertJsonPath('sumber.1.status_pagu', 'melebihi');

        SimpanSumberDana::simpan(2, ['Komite' => '2.500.000']);
        $respons = $this->getJson(route('kegiatan.sumber_dana', ['kegiatan' => 2]))
            ->assertJsonPath('dipilih', ['Komite'])
            ->assertJsonPath('sumber.0.dipilih', false)
            ->assertJsonPath('sumber.0.pagu', null)
            ->assertJsonPath('sumber.0.terlepas', true)
            ->assertJsonPath('sumber.0.total_rab', 5_400_000)
            ->assertJsonPath('sumber.0.selaras', false);
        $this->assertSame($respons->json('sumber.1.total_rab'), $respons->json('gabungan.total'), 'RAB terlepas tidak ikut gabungan.');
    }

    public function test_hak_baca_terkunci_dan_data_pagu_tertaut(): void
    {
        $this->getJson(route('kegiatan.sumber_dana', ['kegiatan' => 999]))->assertNotFound();
        $lain = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Lomba Kreativitas Siswa Tingkat Kota', 'leading' => '1.2', 'user_id' => 2, 'sumber_dana' => [['sumber' => 'Komite', 'pagu' => 12_000_000]]]);
        $this->getJson(route('kegiatan.sumber_dana', ['kegiatan' => $lain]))->assertForbidden();
        $this->withSession(['stub_role' => 'admin'])->getJson(route('kegiatan.sumber_dana', ['kegiatan' => $lain]))
            ->assertOk()
            ->assertJsonPath('data_pagu.nama_kegiatan', 'Lomba Kreativitas Siswa Tingkat Kota')
            ->assertJsonPath('sumber.1.selaras', true);

        $repo = app(KegiatanRepository::class);
        $repo->simpan(['status' => 'final', 'data_pagu_id' => '9b2f1c3e-0004-4a51-8c11-000000000004'] + $repo->temukan(1), 1);
        $this->getJson(route('kegiatan.sumber_dana', ['kegiatan' => 1]))
            ->assertJsonPath('dapat_diubah', false)
            ->assertJsonPath('data_pagu', ['id' => '9b2f1c3e-0004-4a51-8c11-000000000004', 'nama_kegiatan' => 'Pelatihan Penulisan Karya Ilmiah Guru', 'tertaut' => true])
            ->assertJsonPath('sumber.0.pagu_data', 4_500_000)
            ->assertJsonPath('sumber.0.selaras', false);
    }

    public function test_hasil_database_sama_dengan_tiruan(): void
    {
        $tiruan = DataSumberDana::untuk(app(KegiatanRepository::class)->temukan(3));

        $this->app->singleton(DataPaguRepository::class, EloquentDataPaguRepository::class);
        $this->app->singleton(KegiatanRepository::class, EloquentKegiatanRepository::class);
        $this->app->singleton(RabRepository::class, EloquentRabRepository::class);
        foreach (StubDataPaguRepository::contoh() as $p) {
            DB::table('data_pagu')->insert(['id' => $p['id'], 'tahun_anggaran' => $p['tahun'], 'nama_kegiatan' => $p['nama_kegiatan'], 'kode_leading' => $p['leading'], 'asal' => $p['asal']]);
            app(DataPaguRepository::class)->simpan($p, $p['id']);
        }
        $id = app(KegiatanRepository::class)->simpan(Arr::except(StubKegiatanRepository::contohMelebihiPagu(), ['id', 'status']));
        foreach (StubRab::DAFTAR_CONTOH[3] as $sumber) {
            foreach (StubRab::rincian(3, $sumber) as $item) {
                app(RabRepository::class)->tambahItem($id, $sumber, $item);
            }
        }

        $this->assertSame(['kegiatan_id' => $id] + $tiruan, DataSumberDana::untuk(app(KegiatanRepository::class)->temukan($id)));
    }
}
