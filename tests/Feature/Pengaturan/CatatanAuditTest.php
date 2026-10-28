<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\RiwayatPengaturanRepository;
use App\Repositories\EloquentRiwayatPengaturanRepository;
use App\Support\Pengaturan\CatatanAudit;
use App\Support\Pengaturan\RiwayatPengaturan;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\RujukanDb;
use Tests\TestCase;

class CatatanAuditTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{bool}> */
    public static function penyimpanan(): array
    {
        return ['tiruan' => [false], 'database' => [true]];
    }

    /** Tiga entri pada tanggal berbeda (2026-03-01 leading, 03-05 & 03-05 admin). */
    private function isi(bool $database): void
    {
        if ($database) {
            RujukanDb::siapkan();
            $this->app->singleton(RiwayatPengaturanRepository::class, EloquentRiwayatPengaturanRepository::class);
        }
        $this->travelTo('2026-03-01 09:00:00');
        RiwayatPengaturan::catat('ubah_profil', 'Nama: A → B.');
        $this->withSession(['stub_role' => 'admin']);
        $this->travelTo('2026-03-05 10:00:00');
        RiwayatPengaturan::catat('unggah_cover', 'Cover “Cover A” diunggah.');
        RiwayatPengaturan::catat('ubah_batas_ai', 'Batas harian: 20 → 30.');
        $this->travelBack();
    }

    #[DataProvider('penyimpanan')]
    public function test_catat_dan_baca_terbaru_dulu(bool $database): void
    {
        $this->isi($database);
        $semua = app(RiwayatPengaturanRepository::class)->semua();

        $this->assertSame(['ubah_batas_ai', 'unggah_cover', 'ubah_profil'], array_slice(array_column($semua, 'aksi'), 0, 3), 'Terbaru dulu; waktu sama → yang dicatat belakangan dulu.');
        $this->assertSame(['user_id' => 2, 'pengguna' => 'Koordinator PUSKOM', 'aksi' => 'ubah_batas_ai', 'keterangan' => 'Batas harian: 20 → 30.', 'tahun' => 2026], Arr::except($semua[0], ['id', 'waktu']));
        $this->assertSame([1, 'Muslimin, M.Pd.'], [$semua[2]['user_id'], $semua[2]['pengguna']]);
        $this->assertSame('2026-03-05T10:00:00+07:00', $semua[0]['waktu']);
        $this->assertSame(['id', 'waktu', 'user_id', 'pengguna', 'aksi', 'keterangan', 'tahun'], array_keys($semua[0]));
    }

    public function test_saringan_catatan_audit(): void
    {
        $this->isi(false);
        $aksi = fn (array $saringan) => array_column(CatatanAudit::saring($saringan), 'aksi');

        $this->assertSame(['unggah_cover'], $aksi(['bagian' => 'template']));
        $this->assertSame(['ubah_profil'], $aksi(['pengguna' => 'Muslimin, M.Pd.']));
        $this->assertSame(['ubah_batas_ai'], $aksi(['aksi' => 'ubah_batas_ai']));
        $this->assertSame(['ubah_batas_ai', 'unggah_cover'], $aksi(['dari' => '2026-03-02', 'sampai' => '2026-03-05']));
        $this->assertSame(['ubah_profil'], $aksi(['dari' => '2026-02-01', 'sampai' => '2026-03-01', 'tahun' => '2026']));
        $this->assertSame(['ubah_profil', 'ganti_tahun'], $aksi(['sampai' => '2026-03-01', 'tahun' => '2026']), 'Aktivasi contoh 2 Januari 2026 ikut.');
        $this->assertSame(['ganti_tahun'], $aksi(['tahun' => '2025']), 'Contoh aktivasi 2025.');
        $this->assertSame(['unggah_cover'], $aksi(['cari' => 'COVER A']));
        $this->assertSame(['ubah_batas_ai'], $aksi(['cari' => 'pemakaian AI']), 'Kata kunci juga mencari label aksi.');

        $this->assertSame(['tahun' => '', 'dari' => '', 'sampai' => ''], Arr::only(CatatanAudit::rapikanSaringan(['dari' => '2026-03-05', 'sampai' => '2026-03-01', 'tahun' => 'dua']), ['dari', 'sampai', 'tahun']), 'Rentang terbalik & tahun bukan angka diabaikan.');
        $this->assertSame('', CatatanAudit::rapikanSaringan(['dari' => '05/03/2026'])['dari']);

        $entri = CatatanAudit::saring([])[0];
        $this->assertSame(['Ubah batas pemakaian AI', 'ai'], [$entri['label_aksi'], $entri['bagian']]);

        $halaman = CatatanAudit::halaman([], 2, 2);
        $this->assertSame([5, 2, 3, 2], [$halaman['jumlah'], $halaman['halaman'], $halaman['jumlah_halaman'], count($halaman['data'])]);
        $this->assertSame(3, CatatanAudit::halaman([], 99, 2)['halaman'], 'Halaman di luar jangkauan → halaman terakhir.');
    }

    public function test_endpoint_khusus_admin(): void
    {
        $this->isi(false);
        $this->withSession(['stub_role' => 'leading'])->getJson(route('pengaturan.riwayat.data'))->assertForbidden();
        $this->withSession(['stub_role' => 'admin'])->getJson(route('pengaturan.riwayat.data', ['bagian' => 'ai', 'cari' => 'batas']))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('jumlah', 1)
            ->assertJsonPath('data.0.aksi', 'ubah_batas_ai')
            ->assertJsonPath('saringan.bagian', 'ai')
            ->assertJsonPath('per_halaman', 50);
    }

    public function test_catatan_database_hanya_bisa_ditambah(): void
    {
        $this->isi(true);
        $id = DB::table('activity_logs')->where('aksi', 'ubah_profil')->value('id');

        foreach ([fn () => DB::table('activity_logs')->where('id', $id)->update(['keterangan' => 'diubah']), fn () => DB::table('activity_logs')->where('id', $id)->delete()] as $aksi) {
            try {
                DB::transaction($aksi);
                $this->fail('Catatan audit seharusnya tidak bisa diubah/dihapus.');
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }

        DB::table('users')->where('id', 1)->delete();
        $entri = collect(app(RiwayatPengaturanRepository::class)->semua())->firstWhere('aksi', 'ubah_profil');
        $this->assertSame([null, 'Muslimin, M.Pd.', 'Nama: A → B.'], [$entri['user_id'], $entri['pengguna'], $entri['keterangan']], 'Akun dihapus: nama saat itu tetap tercatat.');
    }
}
