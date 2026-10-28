<?php

namespace Tests\Feature\Pagu;

use App\Contracts\DataPaguRepository;
use App\Contracts\KegiatanRepository;
use App\Contracts\RabRepository;
use App\Livewire\Pagu\Daftar;
use App\Repositories\EloquentDataPaguRepository;
use App\Repositories\EloquentKegiatanRepository;
use App\Repositories\EloquentRabRepository;
use App\Support\Pagu\SimpanDataPagu;
use App\Support\Stub\StubDataPaguRepository;
use App\Support\Stub\StubKegiatanRepository;
use App\Support\Stub\StubRab;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SimpanDataPaguTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN = ['id' => 2, 'role' => 'admin', 'leadings' => []];

    private const SUPERVISI = '9b2f1c3e-0002-4a51-8c11-000000000002';

    /** @return array<string, array{bool}> */
    public static function penyimpanan(): array
    {
        return ['tiruan' => [false], 'database' => [true]];
    }

    /** Contoh Data Pagu & kegiatan 2 (DIPA 6 jt/RAB 5,4 jt, Komite sesuai pagu) di penyimpanan pilihan; mengembalikan id kegiatan. */
    private function siapkan(bool $database): int
    {
        if (! $database) {
            return 2;
        }
        $this->app->singleton(DataPaguRepository::class, EloquentDataPaguRepository::class);
        $this->app->singleton(KegiatanRepository::class, EloquentKegiatanRepository::class);
        $this->app->singleton(RabRepository::class, EloquentRabRepository::class);
        foreach (StubDataPaguRepository::contoh() as $p) {
            DB::table('data_pagu')->insert(['id' => $p['id'], 'tahun_anggaran' => $p['tahun'], 'nama_kegiatan' => $p['nama_kegiatan'], 'kode_leading' => $p['leading'], 'asal' => $p['asal']]);
            app(DataPaguRepository::class)->simpan($p, $p['id']);
        }
        $id = app(KegiatanRepository::class)->simpan(Arr::except(StubKegiatanRepository::contohDuaSumber(), ['id', 'status']));
        foreach (StubRab::DAFTAR_CONTOH[2] as $sumber) {
            foreach (StubRab::rincian(2, $sumber) as $item) {
                app(RabRepository::class)->tambahItem($id, $sumber, $item);
            }
        }

        return $id;
    }

    /** @return array<string, string> */
    private function galat(array $isian, ?string $id = null): array
    {
        try {
            SimpanDataPagu::simpan($isian, $id, self::ADMIN);
        } catch (ValidationException $e) {
            return array_map(fn (array $pesan) => $pesan[0], $e->errors());
        }
        $this->fail('Seharusnya ditolak: '.json_encode($isian));
    }

    public function test_perapian_isian(): void
    {
        $this->assertSame(
            ['nama_kegiatan' => 'Rapat Koordinasi Wali Kelas', 'leading' => null, 'pagu' => ['DIPA' => null, 'Komite' => 3_000_000]],
            SimpanDataPagu::rapikan(['nama_kegiatan' => '  Kegiatan  Rapat   Koordinasi Wali Kelas ', 'leading' => '', 'pagu_dipa' => '0', 'pagu_komite' => 'Rp3.000.000']),
        );
        $this->assertSame(['DIPA' => 1_500_000, 'Komite' => null], SimpanDataPagu::rapikan(['nama_kegiatan' => 'X', 'pagu' => ['DIPA' => '1.500.000', 'Komite' => '']])['pagu']);
    }

    public function test_aturan_isian(): void
    {
        $this->assertSame(['nama_kegiatan' => 'Nama kegiatan wajib diisi.', 'pagu.Komite' => 'Isi pagu DIPA atau Komite.'], $this->galat(['nama_kegiatan' => '   ']));
        $this->assertSame(['nama_kegiatan' => 'Nama kegiatan maksimal 255 karakter.'], $this->galat(['nama_kegiatan' => str_repeat('a', 256), 'pagu_dipa' => '1']));
        $this->assertSame(['nama_kegiatan' => 'Nama ini sudah ada di Data Pagu.'], $this->galat(['nama_kegiatan' => ' supervisi  akademik DAN refleksi pembelajaran', 'pagu_dipa' => '1']));
        $this->assertSame(['leading' => 'Leading tidak dikenal.', 'pagu.DIPA' => 'Pagu melebihi batas wajar.'], $this->galat(['nama_kegiatan' => 'Baru', 'leading' => '1.9', 'pagu_dipa' => '200.000.000.000']));

        // Nama sendiri boleh tetap; leading nonaktif yang sudah tersimpan tetap boleh dipakai.
        $id = app(DataPaguRepository::class)->simpan(['tahun' => 2026, 'nama_kegiatan' => 'Kegiatan Leading Lama', 'leading' => '1.9', 'pagu' => ['DIPA' => 1_000_000]]);
        $hasil = SimpanDataPagu::simpan(['nama_kegiatan' => 'kegiatan leading LAMA', 'leading' => '1.9', 'pagu_dipa' => '2.000.000'], $id, self::ADMIN, tahun: 2030);
        $this->assertSame(['leading LAMA', '1.9', 2026, 2_000_000], [$hasil['data']['nama_kegiatan'], $hasil['data']['leading'], $hasil['data']['tahun'], $hasil['data']['pagu']['DIPA']], 'Tahun data tidak berpindah.');
        $this->assertCount(count(StubDataPaguRepository::contoh()) + 1, app(DataPaguRepository::class)->semua(2026));
    }

    public function test_hanya_admin_dan_data_yang_ada(): void
    {
        foreach ([fn () => SimpanDataPagu::simpan(['nama_kegiatan' => 'Baru', 'pagu_dipa' => '1'], null, ['id' => 1, 'role' => 'leading', 'leadings' => ['1.6']]),
            fn () => SimpanDataPagu::simpan(['nama_kegiatan' => 'Baru', 'pagu_dipa' => '1'], 'tidak-ada', self::ADMIN),
            fn () => SimpanDataPagu::hapus(self::SUPERVISI, ['id' => 1, 'role' => 'leading']),
            fn () => SimpanDataPagu::hapus('tidak-ada', self::ADMIN)] as $aksi) {
            try {
                $aksi();
                $this->fail('Seharusnya ditolak.');
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertCount(count(StubDataPaguRepository::contoh()), app(DataPaguRepository::class)->semua(2026));
    }

    #[DataProvider('penyimpanan')]
    public function test_ubah_pagu_dampak_tanpa_dan_dengan_menerapkan_ke_kegiatan(bool $database): void
    {
        $kegiatanId = $this->siapkan($database);
        $kegiatan = app(KegiatanRepository::class);
        $isian = ['nama_kegiatan' => 'Supervisi Akademik dan Refleksi Pembelajaran', 'leading' => '1.6', 'pagu_dipa' => '5.000.000', 'pagu_komite' => '2.500.000'];

        $hasil = SimpanDataPagu::simpan($isian, self::SUPERVISI, self::ADMIN);
        $this->assertSame(['id' => $kegiatanId, 'nama' => 'Supervisi Akademik dan Refleksi Pembelajaran'], $hasil['kegiatan']);
        $this->assertFalse($hasil['diterapkan']);
        $this->assertSame(
            ['sumber' => 'DIPA', 'pagu_kegiatan' => 6_000_000, 'pagu_data' => 5_000_000, 'berubah' => true, 'total_rab' => 5_400_000, 'status' => 'sisa', 'status_baru' => 'melebihi', 'keterangan_baru' => 'Melebihi pagu Rp400.000'],
            $hasil['dampak'][0],
        );
        $this->assertFalse($hasil['dampak'][1]['berubah']);
        $this->assertSame('Pagu kegiatan "Supervisi Akademik dan Refleksi Pembelajaran" tidak ikut berubah; dengan pagu baru: DIPA Melebihi pagu Rp400.000.', SimpanDataPagu::ringkasDampak($hasil));
        $this->assertSame(6_000_000, $kegiatan->temukan($kegiatanId)['sumber_dana'][0]['pagu'], 'Tanpa terapkan kegiatan tidak berubah.');

        $hasil = SimpanDataPagu::simpan($isian, self::SUPERVISI, self::ADMIN, terapkan: true);
        $this->assertTrue($hasil['diterapkan']);
        $this->assertSame([['sumber' => 'DIPA', 'pagu' => 5_000_000], ['sumber' => 'Komite', 'pagu' => 2_500_000]], $kegiatan->temukan($kegiatanId)['sumber_dana']);
        $this->assertSame('Pagu RAB kegiatan "Supervisi Akademik dan Refleksi Pembelajaran" ikut diperbarui (DIPA Melebihi pagu Rp400.000).', SimpanDataPagu::ringkasDampak($hasil));
        $this->assertNull(SimpanDataPagu::ringkasDampak(SimpanDataPagu::simpan($isian, self::SUPERVISI, self::ADMIN, terapkan: true)), 'Tanpa perbedaan tidak ada kalimat dampak.');

        // Sumber tanpa pagu di Data Pagu tidak dipilih lagi; RAB-nya tetap tersimpan dan dilaporkan.
        $hasil = SimpanDataPagu::simpan(['pagu_komite' => ''] + $isian, self::SUPERVISI, self::ADMIN, terapkan: true);
        $this->assertSame([['sumber' => 'DIPA', 'pagu' => 5_000_000]], $kegiatan->temukan($kegiatanId)['sumber_dana']);
        $this->assertSame(['Komite'], array_keys($hasil['terlepas']));
        $this->assertNotEmpty(app(RabRepository::class)->rincian($kegiatanId, 'Komite'));
    }

    public function test_kegiatan_terkunci_tidak_diubah_dan_hapus_tidak_mengubah_kegiatan(): void
    {
        $repo = app(KegiatanRepository::class);
        $repo->simpan(['status' => 'final'] + $repo->temukan(2), 2);

        $hasil = SimpanDataPagu::simpan(['nama_kegiatan' => 'Supervisi Akademik dan Refleksi Pembelajaran', 'leading' => '1.6', 'pagu_dipa' => '1.000.000', 'pagu_komite' => '2.500.000'], self::SUPERVISI, self::ADMIN, terapkan: true);
        $this->assertFalse($hasil['diterapkan']);
        $this->assertSame('Kegiatan "Supervisi Akademik dan Refleksi Pembelajaran" berstatus Final sehingga pagunya tidak diubah.', $hasil['alasan']);
        $this->assertSame(1_000_000, app(DataPaguRepository::class)->temukan(self::SUPERVISI)['pagu']['DIPA'], 'Data Pagu tetap tersimpan.');
        $this->assertSame(6_000_000, $repo->temukan(2)['sumber_dana'][0]['pagu']);

        $this->assertSame(['id' => 2, 'nama' => 'Supervisi Akademik dan Refleksi Pembelajaran'], SimpanDataPagu::hapus(self::SUPERVISI, self::ADMIN));
        $this->assertNull(app(DataPaguRepository::class)->temukan(self::SUPERVISI));
        $this->assertSame(6_000_000, $repo->temukan(2)['sumber_dana'][0]['pagu']);
    }

    public function test_halaman_data_pagu_menawarkan_pembaruan_pagu_kegiatan(): void
    {
        $this->withSession(['stub_role' => 'admin']);

        Livewire::test(Daftar::class)
            ->call('ubah', '9b2f1c3e-0004-4a51-8c11-000000000004')
            ->assertDontSee('Perbarui juga pagu RAB kegiatan')
            ->call('ubah', self::SUPERVISI)
            ->assertSee('Perbarui juga pagu RAB kegiatan')
            ->set('isian.pagu_dipa', '5.000.000')
            ->set('terapkan', true)
            ->call('simpan')
            ->assertHasNoErrors()
            ->assertDispatched('notify', message: 'Pagu RAB kegiatan "Supervisi Akademik dan Refleksi Pembelajaran" ikut diperbarui (DIPA Melebihi pagu Rp400.000).', type: 'info')
            ->assertSet('ubahId', null)
            ->assertSet('terapkan', false);
        $this->assertSame(5_000_000, app(KegiatanRepository::class)->temukan(2)['sumber_dana'][0]['pagu']);

        // Galat server dipetakan ke isian formulir; leading nonaktif yang tersimpan tetap tampil sebagai pilihan.
        $id = app(DataPaguRepository::class)->simpan(['tahun' => 2026, 'nama_kegiatan' => 'Kegiatan Leading Lama', 'leading' => '1.9', 'pagu' => ['DIPA' => 1_000_000]]);
        Livewire::test(Daftar::class)
            ->call('ubah', $id)
            ->assertSee('(nonaktif)')
            ->set('isian.pagu_dipa', '')
            ->call('simpan')
            ->assertHasErrors(['isian.pagu_komite'])
            ->set('isian.pagu_dipa', '200.000.000.000')
            ->call('simpan')
            ->assertHasErrors(['isian.pagu_dipa'])
            ->assertHasNoErrors(['isian.pagu_komite', 'isian.leading']);
    }
}
