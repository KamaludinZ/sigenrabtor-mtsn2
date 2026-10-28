<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Contracts\MatrikJadwalRepository;
use App\Contracts\RabRepository;
use App\Contracts\RiwayatSuntingRepository;
use App\Repositories\EloquentKegiatanRepository;
use App\Repositories\EloquentMatrikJadwalRepository;
use App\Repositories\EloquentRabRepository;
use App\Repositories\EloquentRiwayatSuntingRepository;
use App\Support\Dokumen\RiwayatSunting;
use App\Support\Dokumen\SuntingDokumen;
use App\Support\Stub\StubRiwayatSuntingRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\RujukanDb;
use Tests\TestCase;

class RiwayatSuntingTest extends TestCase
{
    use RefreshDatabase;

    private const LEADING = ['id' => 1, 'name' => 'Muslimin, M.Pd.', 'role' => 'leading', 'leadings' => ['1.6']];

    private const KUNCI = ['id', 'kegiatan_id', 'bidang', 'nomor_bagian', 'asal', 'teks_lama', 'teks_baru', 'user_id', 'pengguna', 'dibuat_pada'];

    /** @return array<string, array{bool}> */
    public static function penyimpanan(): array
    {
        return ['tiruan' => [false], 'database' => [true]];
    }

    private function pakaiDatabase(): void
    {
        RujukanDb::siapkan();
        $this->app->singleton(KegiatanRepository::class, EloquentKegiatanRepository::class);
        $this->app->singleton(RiwayatSuntingRepository::class, EloquentRiwayatSuntingRepository::class);
        // RAB & matrik juga dari database agar id kegiatan baru tidak bertemu contoh tiruan berid sama.
        $this->app->singleton(RabRepository::class, EloquentRabRepository::class);
        $this->app->singleton(MatrikJadwalRepository::class, EloquentMatrikJadwalRepository::class);
    }

    private function kegiatanBaru(): int
    {
        return app(KegiatanRepository::class)->simpan([
            'nama_kegiatan' => 'Rapat Kerja Madrasah', 'leading' => '1.6', 'user_id' => 1, 'tahun_anggaran' => 2026, 'status' => 'draf',
            'tujuan' => 'menyusun rencana kerja tahunan madrasah.', 'tempat' => 'Ruang Rapat Madrasah',
        ]);
    }

    private function sunting(int $id, string $kunci, string $teks): array
    {
        return SuntingDokumen::simpan($id, app(KegiatanRepository::class)->temukan($id), $kunci, $teks, self::LEADING, null);
    }

    #[DataProvider('penyimpanan')]
    public function test_sunting_yang_mengubah_teks_tercatat(bool $database): void
    {
        $database && $this->pakaiDatabase();
        $id = $this->kegiatanBaru();

        $this->sunting($id, 'tempat', 'Aula Utama MTsN 2 Kota Malang');
        $this->travel(1)->seconds();
        $this->sunting($id, 'tempat', 'Aula Utama MTsN 2 Kota Malang');
        $this->sunting($id, 'tujuan', '');
        $this->travel(1)->seconds();
        $this->sunting($id, 'persiapan', "Rapat koordinasi panitia\nPenyiapan bahan rapat kerja");
        $this->travel(1)->seconds();
        $this->sunting($id, 'persiapan', '');

        $riwayat = RiwayatSunting::untuk($id);
        $this->assertSame(['persiapan', 'persiapan', 'tujuan', 'tempat'], array_column($riwayat, 'bidang'), 'Terbaru dulu; simpan tanpa perubahan tidak tercatat.');
        $this->assertSame(['kembali ke teks baku', 'disesuaikan', 'dikosongkan', 'diubah'], array_column($riwayat, 'keterangan'));
        $this->assertSame([...self::KUNCI, 'label', 'keterangan'], array_keys($riwayat[0]));

        $tempat = $riwayat[3];
        $this->assertSame([$id, 5, 'isian', 'Ruang Rapat Madrasah', 'Aula Utama MTsN 2 Kota Malang', 1, 'Muslimin, M.Pd.', 'Tempat pelaksanaan'],
            [$tempat['kegiatan_id'], $tempat['nomor_bagian'], $tempat['asal'], $tempat['teks_lama'], $tempat['teks_baru'], $tempat['user_id'], $tempat['pengguna'], $tempat['label']]);
        $this->assertMatchesRegularExpression('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d\.\d{6}\+07:00$/', $tempat['dibuat_pada']);
        $this->assertSame([null, "Rapat koordinasi panitia\nPenyiapan bahan rapat kerja", 'teks_khusus', 4], [$riwayat[1]['teks_lama'], $riwayat[1]['teks_baru'], $riwayat[1]['asal'], $riwayat[1]['nomor_bagian']]);
        $this->assertSame(["Rapat koordinasi panitia\nPenyiapan bahan rapat kerja", null], [$riwayat[0]['teks_lama'], $riwayat[0]['teks_baru']]);
        $this->assertSame(['menyusun rencana kerja tahunan madrasah.', null], [$riwayat[2]['teks_lama'], $riwayat[2]['teks_baru']]);

        $this->assertSame(['tempat'], array_column(RiwayatSunting::untuk($id, 'tempat'), 'bidang'));
        $this->assertCount(2, RiwayatSunting::untuk($id, batas: 2));
        $this->assertSame([], RiwayatSunting::untuk($id + 1));
    }

    public function test_entri_membandingkan_teks_tersimpan(): void
    {
        $sebelum = ['tempat' => '  Aula  ', 'teks_khusus' => []];
        $this->assertNull(RiwayatSunting::entri($sebelum, ['tempat' => 'Aula'], 'tempat', self::LEADING), 'Spasi tepi tidak dihitung perubahan.');
        $this->assertSame(
            ['bidang' => 'pengarah', 'nomor_bagian' => 6, 'asal' => 'teks_khusus', 'teks_lama' => null, 'teks_baru' => 'Pengarah kegiatan {nama_kegiatan} adalah Kepala Madrasah.', 'user_id' => 1, 'pengguna' => 'Muslimin, M.Pd.'],
            RiwayatSunting::entri($sebelum, ['teks_khusus' => ['pengarah' => 'Pengarah kegiatan {nama_kegiatan} adalah Kepala Madrasah.']], 'pengarah', self::LEADING),
        );
        $this->assertSame(SuntingDokumen::maks('persiapan') * 10 + 9, SuntingDokumen::maksTeks('persiapan'));
        $this->assertSame(255, SuntingDokumen::maksTeks('tempat'));
    }

    public function test_jejak_gagal_ditulis_tidak_membatalkan_perubahan_data(): void
    {
        $this->app->instance(RiwayatSuntingRepository::class, new class extends StubRiwayatSuntingRepository
        {
            public function catat(int $kegiatanId, array $entri): array
            {
                throw new RuntimeException('Riwayat sunting gagal disimpan — coba lagi.');
            }
        });
        $id = $this->kegiatanBaru();

        $hasil = $this->sunting($id, 'tempat', 'Aula Utama MTsN 2 Kota Malang');
        $this->assertContains('tempat', $hasil['diubah']);
        $this->assertSame('Aula Utama MTsN 2 Kota Malang', app(KegiatanRepository::class)->temukan($id)['tempat']);
    }
}
