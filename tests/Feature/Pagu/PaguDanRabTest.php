<?php

namespace Tests\Feature\Pagu;

use App\Contracts\DataPaguRepository;
use App\Contracts\KegiatanRepository;
use App\Contracts\RabRepository;
use App\Repositories\EloquentDataPaguRepository;
use App\Repositories\EloquentKegiatanRepository;
use App\Repositories\EloquentRabRepository;
use App\Support\Pagu\PaguDanRab;
use App\Support\Rab\AnggaranKegiatan;
use App\Support\Stub\StubDataPaguRepository;
use App\Support\Stub\StubKegiatanRepository;
use App\Support\Stub\StubRab;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaguDanRabTest extends TestCase
{
    use RefreshDatabase;

    private const PELATIHAN = '9b2f1c3e-0004-4a51-8c11-000000000004';

    public function test_pagu_dan_rab_per_data_pagu_akun_leading(): void
    {
        $respons = $this->getJson(route('pagu.rab'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('tahun', 2026)
            ->assertJsonPath('ringkasan.jumlah', 4)
            ->assertJsonPath('ringkasan.dipakai', 3);

        $data = array_column($respons->json('data'), null, 'nama_kegiatan');
        $this->assertSame(['Pelatihan Penulisan Karya Ilmiah Guru', 'Review RKJM dan Penyusunan RKTM', 'Supervisi Akademik dan Refleksi Pembelajaran', 'Workshop Penyusunan Kurikulum Madrasah dan Perangkat Pembelajaran'], array_keys($data), 'Hanya leading 1.6, urut nama.');

        $supervisi = $data['Supervisi Akademik dan Refleksi Pembelajaran'];
        $this->assertSame(['id' => 2, 'nama' => 'Supervisi Akademik dan Refleksi Pembelajaran', 'boleh_lihat' => true], $supervisi['kegiatan']);
        $this->assertSame([
            'sumber' => 'DIPA', 'pagu_data' => 6_000_000, 'pagu_kegiatan' => 6_000_000, 'selaras' => true, 'total_rab' => 5_400_000,
            'selisih' => 600_000, 'status_pagu' => 'sisa', 'keterangan_pagu' => 'Sisa pagu Rp600.000',
        ], $supervisi['sumber'][0]);
        $this->assertSame(['Komite', 'sesuai'], [$supervisi['sumber'][1]['sumber'], $supervisi['sumber'][1]['status_pagu']]);

        // Contoh 3: pagu DIPA belum diisi di kegiatan maupun Data Pagu, RAB Komite melebihi pagu.
        [$dipa, $komite] = $data['Review RKJM dan Penyusunan RKTM']['sumber'];
        $this->assertSame([null, null, false, 'tanpa_pagu'], [$dipa['pagu_data'], $dipa['pagu_kegiatan'], $dipa['selaras'], $dipa['status_pagu']]);
        $anggaran = AnggaranKegiatan::perSumber(app(KegiatanRepository::class)->temukan(3))['Komite'];
        $this->assertSame(['melebihi', $anggaran->total(), $anggaran->keteranganPagu()->teks()], [$komite['status_pagu'], $komite['total_rab'], $komite['keterangan_pagu']]);

        // Belum menjadi kegiatan: hanya pagu Data Pagu.
        $pelatihan = $data['Pelatihan Penulisan Karya Ilmiah Guru'];
        $this->assertSame([self::PELATIHAN, null], [$pelatihan['id'], $pelatihan['kegiatan']]);
        $this->assertSame([['sumber' => 'DIPA', 'pagu_data' => 4_500_000, 'pagu_kegiatan' => null, 'selaras' => false, 'total_rab' => null, 'selisih' => null, 'status_pagu' => null, 'keterangan_pagu' => null]], $pelatihan['sumber']);

        $this->assertSame(['DIPA' => 6_000_000 + 4_500_000, 'Komite' => 7_500_000 + 2_500_000 + 10_000_000], $respons->json('ringkasan.pagu'));
        $this->assertSame(['DIPA' => 5_400_000 + $dipa['total_rab'], 'Komite' => $anggaran->total() + 2_500_000 + 10_000_000], $respons->json('ringkasan.total_rab'));
    }

    public function test_admin_semua_data_pagu_dan_tahun_lain(): void
    {
        $this->withSession(['stub_role' => 'admin'])->getJson(route('pagu.rab'))
            ->assertJsonPath('ringkasan.jumlah', count(StubDataPaguRepository::contoh()))
            ->assertJsonPath('ringkasan.dipakai', 3);

        $this->getJson(route('pagu.rab', ['ta' => 2025]))->assertJsonPath('tahun', 2025)->assertJsonPath('ringkasan.jumlah', 0)->assertJsonPath('data', []);
        $this->getJson(route('pagu.rab', ['ta' => 'semua']))->assertJsonPath('tahun', 2026);
    }

    public function test_rab_kegiatan_leading_lain_tidak_disertakan(): void
    {
        $nama = 'Rapat Koordinasi Wali Kelas';
        app(DataPaguRepository::class)->simpan(['tahun' => 2026, 'nama_kegiatan' => $nama, 'leading' => null, 'pagu' => ['Komite' => 3_000_000]]);
        $id = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => $nama, 'leading' => '1.2', 'user_id' => 2, 'sumber_dana' => [['sumber' => 'Komite', 'pagu' => 3_000_000]]]);
        app(RabRepository::class)->tambahItem($id, 'Komite', StubRab::rincian(2, 'Komite')[0]);

        $leading = collect($this->getJson(route('pagu.rab'))->json('data'))->firstWhere('nama_kegiatan', $nama);
        $this->assertSame(['id' => $id, 'nama' => $nama, 'boleh_lihat' => false], $leading['kegiatan']);
        $this->assertSame([3_000_000, null, null, null], [$leading['sumber'][0]['pagu_data'], $leading['sumber'][0]['pagu_kegiatan'], $leading['sumber'][0]['total_rab'], $leading['sumber'][0]['status_pagu']]);

        $admin = collect($this->withSession(['stub_role' => 'admin'])->getJson(route('pagu.rab'))->json('data'))->firstWhere('nama_kegiatan', $nama);
        $this->assertTrue($admin['kegiatan']['boleh_lihat']);
        $this->assertSame([3_000_000, true], [$admin['sumber'][0]['pagu_kegiatan'], $admin['sumber'][0]['selaras']]);
        $this->assertGreaterThan(0, $admin['sumber'][0]['total_rab']);
    }

    public function test_hasil_database_sama_dengan_tiruan(): void
    {
        $tiruan = PaguDanRab::untuk(['id' => 1, 'role' => 'leading', 'leadings' => ['1.6']], 2026);

        $this->app->singleton(DataPaguRepository::class, EloquentDataPaguRepository::class);
        $this->app->singleton(KegiatanRepository::class, EloquentKegiatanRepository::class);
        $this->app->singleton(RabRepository::class, EloquentRabRepository::class);
        foreach (StubDataPaguRepository::contoh() as $p) {
            DB::table('data_pagu')->insert(['id' => $p['id'], 'tahun_anggaran' => $p['tahun'], 'nama_kegiatan' => $p['nama_kegiatan'], 'kode_leading' => $p['leading'], 'asal' => $p['asal']]);
            app(DataPaguRepository::class)->simpan($p, $p['id']);
        }
        $idBaru = [];
        foreach (StubKegiatanRepository::contohSemua() as $idContoh => $kegiatan) {
            $idBaru[$idContoh] = app(KegiatanRepository::class)->simpan(Arr::except($kegiatan, ['id', 'status']));
            foreach (StubRab::DAFTAR_CONTOH[$idContoh] as $sumber) {
                foreach (StubRab::rincian($idContoh, $sumber) as $item) {
                    app(RabRepository::class)->tambahItem($idBaru[$idContoh], $sumber, $item);
                }
            }
        }

        $database = PaguDanRab::untuk(['id' => 1, 'role' => 'leading', 'leadings' => ['1.6']], 2026);
        $petaId = array_flip($idBaru);
        foreach ($database['data'] as &$baris) {
            if ($baris['kegiatan']) {
                $baris['kegiatan']['id'] = $petaId[$baris['kegiatan']['id']];
            }
        }

        $this->assertSame($tiruan, $database);
    }
}
