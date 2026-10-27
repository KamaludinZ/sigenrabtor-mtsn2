<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Livewire\Kegiatan\Form;
use App\Repositories\EloquentKegiatanRepository;
use App\Support\Kegiatan\SimpanPelaksanaan;
use App\Support\Stub\StubKegiatanRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class SimpanPelaksanaanTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{bool}> */
    public static function penyimpanan(): array
    {
        return ['tiruan' => [false], 'database' => [true]];
    }

    public function test_perapian_seperti_form_data_kegiatan(): void
    {
        $rapi = SimpanPelaksanaan::rapikan([
            'tanggal_mulai' => '2026-07-14', 'lama_hari' => '3', 'hari_kerja' => true, 'bulan_pelaksanaan' => [],
            'tempat' => '  Aula   Madrasah ', 'peserta_jumlah' => '56', 'peserta_jenis' => ' Guru ', 'uraian_pelaksanaan' => " workshop  tatap muka. \n\n\n\nkerja kelompok.",
            'nama_kegiatan' => 'diabaikan',
        ]);

        $this->assertSame([
            'tanggal_mulai' => '2026-07-14', 'lama_hari' => 3, 'hari_kerja' => true, 'bulan_pelaksanaan' => [7],
            'tanggal_laporan' => '2026-07-26', 'laporan_otomatis' => true, 'tempat' => 'Aula Madrasah', 'peserta_jumlah' => 56,
            'peserta_jenis' => 'Guru', 'uraian_pelaksanaan' => "workshop tatap muka.\n\nkerja kelompok.",
        ], $rapi);

        // Pelaporan manual dan bulan pilihan sendiri dipertahankan; bulan dirapikan unik & urut.
        $manual = SimpanPelaksanaan::rapikan(['tanggal_mulai' => '2026-07-14', 'lama_hari' => 3, 'laporan_otomatis' => false, 'tanggal_laporan' => '2026-08-31', 'bulan_pelaksanaan' => ['8', 7, 7]]);
        $this->assertSame(['2026-08-31', [7, 8]], [$manual['tanggal_laporan'], $manual['bulan_pelaksanaan']]);
    }

    #[DataProvider('penyimpanan')]
    public function test_hanya_isian_pelaksanaan_yang_disimpan(bool $database): void
    {
        if ($database) {
            $this->app->singleton(KegiatanRepository::class, EloquentKegiatanRepository::class);
        }
        $repo = app(KegiatanRepository::class);
        $id = $repo->simpan(Arr::except(StubKegiatanRepository::contoh(), ['id', 'status']));

        // Jadwal pindah ke Agustus: bulan diturunkan ulang dari jadwal dan pelaporan kembali otomatis.
        $hasil = SimpanPelaksanaan::simpan($id, ['tanggal_mulai' => '2026-08-03', 'lama_hari' => 2, 'bulan_pelaksanaan' => [], 'laporan_otomatis' => true, 'tempat' => 'Lab Komputer']);

        $k = $repo->temukan($id);
        $this->assertSame(['2026-08-03', 2, 'Lab Komputer', [8], '2026-08-14', 56], [$k['tanggal_mulai'], $k['lama_hari'], $k['tempat'], $k['bulan_pelaksanaan'], $k['tanggal_laporan'], $k['peserta_jumlah']]);
        $this->assertSame($hasil['tempat'], $k['tempat']);
        $this->assertSame(['Workshop Penyusunan Kurikulum Madrasah dan Perangkat Pembelajaran', 'SK.10'], [$k['nama_kegiatan'], $k['kode_sk']]);
        if ($database) {
            $this->assertSame('2026-08-04', DB::table('activities')->where('id', $id)->value('tanggal_selesai'));
        }
    }

    public function test_form_data_kegiatan_menyimpan_pelaksanaan_dengan_aturan_yang_sama(): void
    {
        $komponen = Livewire::test(Form::class)
            ->set('form.nama_kegiatan', 'Workshop Kurikulum Madrasah')
            ->set('form.tanggal_mulai', '2026-07-14')
            ->set('form.lama_hari', '3')
            ->set('form.hari_kerja', true)
            ->assertSet('form.bulan_pelaksanaan', [7])
            ->assertSet('form.tanggal_laporan', '2026-07-26')
            ->set('form.tempat', '  Aula   Madrasah ')
            ->set('form.peserta_jenis', 'Guru  dan  staf')
            ->call('saveDraft')
            ->assertHasNoErrors()
            ->assertSet('form.tempat', 'Aula Madrasah')
            ->assertSet('form.peserta_jenis', 'Guru dan staf');

        $tersimpan = collect(app(KegiatanRepository::class)->semua())->firstWhere('nama_kegiatan', 'Workshop Kurikulum Madrasah');
        $this->assertEquals(SimpanPelaksanaan::rapikan($tersimpan), Arr::only($tersimpan, SimpanPelaksanaan::ISIAN), 'Yang tersimpan sudah rapi menurut penerima server.');
        $this->assertSame(['Aula Madrasah', 'Guru dan staf', [7], '2026-07-26', 3], [$tersimpan['tempat'], $tersimpan['peserta_jenis'], $tersimpan['bulan_pelaksanaan'], $tersimpan['tanggal_laporan'], $tersimpan['lama_hari']]);
        $this->assertTrue($komponen->get('form.laporan_otomatis'));
    }

    public function test_isian_tidak_sah_ditolak_tanpa_menyimpan(): void
    {
        $repo = app(KegiatanRepository::class);
        $id = $repo->simpan(Arr::except(StubKegiatanRepository::contoh(), ['id', 'status']));

        foreach ([
            'lama_hari' => ['lama_hari' => 'tiga'],
            'tanggal_mulai' => ['tanggal_mulai' => '2027-01-04'],
            'bulan_pelaksanaan' => ['tanggal_mulai' => '2026-07-30', 'lama_hari' => 5, 'bulan_pelaksanaan' => [7]],
            'peserta_jumlah' => ['peserta_jumlah' => 0],
        ] as $isian => $data) {
            try {
                SimpanPelaksanaan::simpan($id, $data);
                $this->fail("{$isian} seharusnya ditolak.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey($isian, $e->errors(), $isian);
            }
        }
        $this->assertSame(['2026-07-14', 1], [$repo->temukan($id)['tanggal_mulai'], $repo->temukan($id)['lama_hari']]);

        $this->expectException(RuntimeException::class);
        SimpanPelaksanaan::simpan(999, []);
    }
}
