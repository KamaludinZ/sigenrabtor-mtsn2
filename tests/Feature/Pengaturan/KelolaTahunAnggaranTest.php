<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\RiwayatPengaturanRepository;
use App\Contracts\TahunAnggaranRepository;
use App\Livewire\Pengaturan\TahunAnggaran;
use App\Repositories\EloquentTahunAnggaranRepository;
use App\Support\Pengaturan\KelolaTahunAnggaran;
use App\Support\Stub\StubTahunAnggaranRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class KelolaTahunAnggaranTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN = ['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin', 'leadings' => []];

    /** @return array<string, array{bool}> */
    public static function penyimpanan(): array
    {
        return ['tiruan' => [false], 'database' => [true]];
    }

    private function galat(callable $aksi): string
    {
        try {
            $aksi();
        } catch (ValidationException $e) {
            return $e->errors()['tahun'][0];
        }
        $this->fail('Seharusnya ditolak.');
    }

    /** @return list<int> */
    private function tahun(): array
    {
        return array_column(app(TahunAnggaranRepository::class)->semua(), 'tahun');
    }

    private function riwayatTerakhir(): array
    {
        return app(RiwayatPengaturanRepository::class)->semua()[0];
    }

    #[DataProvider('penyimpanan')]
    public function test_repositori_tambah_kunci_hapus(bool $database): void
    {
        if ($database) {
            foreach (StubTahunAnggaranRepository::contoh() as $t) {
                DB::table('tahun_anggaran')->insert(Arr::except($t, ['id']));
            }
            $this->app->singleton(TahunAnggaranRepository::class, EloquentTahunAnggaranRepository::class);
        }
        $repo = app(TahunAnggaranRepository::class);

        $repo->tambah(2027);
        $this->assertSame([2027, 2026, 2025], $this->tahun());
        $this->assertSame(
            ['tahun' => 2027, 'is_active' => false, 'is_locked' => false, 'no_sk_rktm' => null, 'tanggal_sk_rktm' => null, 'diaktifkan_oleh' => null, 'diaktifkan_pada' => null],
            Arr::except($repo->semua()[0], ['id']),
        );
        $repo->kunci(2027, true);
        $this->assertTrue($repo->semua()[0]['is_locked']);
        $repo->hapus(2027);
        $this->assertSame([2026, 2025], $this->tahun());

        foreach ([fn () => $repo->tambah(2026), fn () => $repo->kunci(2030, true), fn () => $repo->hapus(2030)] as $aksi) {
            try {
                $aksi();
                $this->fail('Seharusnya ditolak.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_pemakaian_tiap_tahun(): void
    {
        $this->assertSame(['kegiatan' => 3, 'master_kode' => true, 'data_pagu' => 6, 'pejabat' => false, 'dasar_hukum' => true], KelolaTahunAnggaran::pemakaian(2026));
        $this->assertSame(['kegiatan' => 0, 'master_kode' => true, 'data_pagu' => 0, 'pejabat' => true, 'dasar_hukum' => true], KelolaTahunAnggaran::pemakaian(2025));

        KelolaTahunAnggaran::tambah('2027', self::ADMIN);
        $daftar = array_column(KelolaTahunAnggaran::daftar(), null, 'tahun');
        $this->assertSame([true, false, false], [$daftar[2027]['boleh_dihapus'], $daftar[2026]['boleh_dihapus'], $daftar[2025]['boleh_dihapus']]);
        $this->assertSame(['kegiatan' => 0, 'master_kode' => false, 'data_pagu' => 0, 'pejabat' => false, 'dasar_hukum' => false], $daftar[2027]['pemakaian']);
    }

    public function test_tambah_kunci_dan_hapus_beserta_riwayat(): void
    {
        $this->assertSame('Tahun berupa angka 2000–2100.', $this->galat(fn () => KelolaTahunAnggaran::tambah('dua ribu', self::ADMIN)));
        $this->assertSame('Tahun berupa angka 2000–2100.', $this->galat(fn () => KelolaTahunAnggaran::tambah(2101, self::ADMIN)));
        $this->assertSame('Tahun Anggaran 2026 sudah ada.', $this->galat(fn () => KelolaTahunAnggaran::tambah('2026', self::ADMIN)));

        $this->assertSame(2027, KelolaTahunAnggaran::tambah(' 2027 ', self::ADMIN));
        $this->assertSame(['tambah_tahun', 'Tahun Anggaran 2027 ditambahkan.'], [$this->riwayatTerakhir()['aksi'], $this->riwayatTerakhir()['keterangan']]);

        $this->assertSame('Tahun Anggaran 2026 sedang aktif — aktifkan tahun lain sebelum menguncinya.', $this->galat(fn () => KelolaTahunAnggaran::kunci(2026, true, self::ADMIN)));
        KelolaTahunAnggaran::kunci(2025, false, self::ADMIN);
        $this->assertFalse(collect(app(TahunAnggaranRepository::class)->semua())->firstWhere('tahun', 2025)['is_locked']);
        $this->assertSame('Tahun Anggaran 2025 dibuka kuncinya.', $this->riwayatTerakhir()['keterangan']);
        $jumlahRiwayat = count(app(RiwayatPengaturanRepository::class)->semua());
        KelolaTahunAnggaran::kunci(2025, false, self::ADMIN);
        $this->assertCount($jumlahRiwayat, app(RiwayatPengaturanRepository::class)->semua(), 'Tanpa perubahan tidak dicatat.');

        $this->assertSame('Tahun Anggaran 2026 sedang aktif dan tidak bisa dihapus.', $this->galat(fn () => KelolaTahunAnggaran::hapus(2026, self::ADMIN)));
        $this->assertSame('Tahun Anggaran 2025 masih dipakai (master kode, pejabat penanda tangan, dasar hukum) sehingga tidak bisa dihapus.', $this->galat(fn () => KelolaTahunAnggaran::hapus(2025, self::ADMIN)));
        $this->assertSame('Tahun Anggaran 2030 tidak ditemukan.', $this->galat(fn () => KelolaTahunAnggaran::hapus(2030, self::ADMIN)));
        KelolaTahunAnggaran::hapus(2027, self::ADMIN);
        $this->assertSame([2026, 2025], $this->tahun());
        $this->assertSame(['hapus_tahun', 'Tahun Anggaran 2027 dihapus.'], [$this->riwayatTerakhir()['aksi'], $this->riwayatTerakhir()['keterangan']]);

        $this->expectException(AuthorizationException::class);
        KelolaTahunAnggaran::tambah(2028, ['role' => 'leading']);
    }

    public function test_endpoint_khusus_admin(): void
    {
        $this->getJson(route('pengaturan.tahun.data'))->assertForbidden();
        $this->withSession(['stub_role' => 'admin'])->getJson(route('pengaturan.tahun.data'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('tahun.0.tahun', 2026)
            ->assertJsonPath('tahun.0.pemakaian.kegiatan', 3)
            ->assertJsonPath('tahun.1.boleh_dihapus', false);
    }

    public function test_halaman_tahun_anggaran(): void
    {
        $this->withSession(['stub_role' => 'admin']);

        Livewire::test(TahunAnggaran::class)
            ->set('tahunBaru', '2026')
            ->call('tambahTahun')
            ->assertHasErrors(['tahunBaru'])
            ->assertSee('Tahun Anggaran 2026 sudah ada.')
            ->set('tahunBaru', '2027')
            ->call('tambahTahun')
            ->assertHasNoErrors()
            ->assertSet('tahunBaru', '')
            ->assertSeeHtml('data-testid="tahun-2027" data-status="tidak-aktif"')
            ->assertSeeHtml('data-testid="hapus-2027"')
            ->assertDontSeeHtml('data-testid="hapus-2025"')
            ->call('ubahKunci', 2027)
            ->assertSeeHtml('data-testid="tahun-2027" data-status="terkunci"')
            ->call('mintaHapus', 2025)
            ->assertSet('akanDihapus', null)
            ->call('mintaHapus', 2027)
            ->assertSeeHtml('data-testid="konfirmasi-hapus-tahun"')
            ->call('hapusTahun')
            ->assertDispatched('notify', message: 'Tahun Anggaran 2027 dihapus.', type: 'info')
            ->assertDontSeeHtml('data-testid="tahun-2027"');
    }
}
