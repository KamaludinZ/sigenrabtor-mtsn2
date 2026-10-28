<?php

namespace Tests\Feature\Pagu;

use App\Contracts\DataPaguRepository;
use App\Repositories\EloquentDataPaguRepository;
use App\Support\Stub\StubDataPaguRepository;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EloquentDataPaguRepositoryTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{bool}> */
    public static function penyimpanan(): array
    {
        return ['tiruan' => [false], 'database' => [true]];
    }

    /** Repositori pilihan berisi contoh data pagu tiruan (database: disalin lewat simpan()). */
    private function repo(bool $database): DataPaguRepository
    {
        if (! $database) {
            return app(StubDataPaguRepository::class);
        }
        $this->app->singleton(DataPaguRepository::class, EloquentDataPaguRepository::class);
        $repo = app(DataPaguRepository::class);
        foreach (StubDataPaguRepository::contoh() as $p) {
            DB::table('data_pagu')->insert(['id' => $p['id'], 'tahun_anggaran' => $p['tahun'], 'nama_kegiatan' => $p['nama_kegiatan'], 'kode_leading' => $p['leading'], 'asal' => $p['asal']]);
            $repo->simpan($p, $p['id']);
        }

        return $repo;
    }

    /** Bentuk data tanpa waktu (waktu dibuat berbeda antar-penyimpanan). */
    private function tanpaWaktu(?array $data): ?array
    {
        return $data === null ? null : Arr::except($data, ['dibuat_pada', 'diperbarui_pada']);
    }

    public function test_bentuk_dan_urutan_database_sama_dengan_tiruan(): void
    {
        $tiruan = array_map($this->tanpaWaktu(...), $this->repo(false)->semua(2026));
        $database = array_map($this->tanpaWaktu(...), $this->repo(true)->semua(2026));

        $this->assertSame($tiruan, $database);
        $this->assertSame(['id', 'tahun', 'nama_kegiatan', 'leading', 'pagu', 'asal', 'dibuat_pada', 'diperbarui_pada'], array_keys(app(DataPaguRepository::class)->semua(2026)[0]));
        $this->assertSame([], app(DataPaguRepository::class)->semua(2025));
    }

    #[DataProvider('penyimpanan')]
    public function test_tambah_ubah_hapus(bool $database): void
    {
        $repo = $this->repo($database);

        $id = $repo->simpan(['tahun' => 2026, 'nama_kegiatan' => 'Rapat Koordinasi Wali Kelas', 'leading' => '', 'pagu' => ['Komite' => '3000000']]);
        $baru = $repo->temukan($id);
        $this->assertSame(['id' => $id, 'tahun' => 2026, 'nama_kegiatan' => 'Rapat Koordinasi Wali Kelas', 'leading' => null, 'pagu' => ['DIPA' => null, 'Komite' => 3_000_000], 'asal' => 'manual'], $this->tanpaWaktu($baru));
        $this->assertContains($id, array_column($repo->semua(2026), 'id'));

        $repo->simpan(['tahun' => 2026, 'nama_kegiatan' => 'Rapat Koordinasi Wali Kelas', 'leading' => '1.2', 'pagu' => ['DIPA' => 1_000_000, 'Komite' => null]], $id);
        $ubah = $repo->temukan($id);
        $this->assertSame(['DIPA' => 1_000_000, 'Komite' => null], $ubah['pagu'], 'Pagu sumber yang dikosongkan terhapus.');
        $this->assertSame(['1.2', 'manual', $baru['dibuat_pada']], [$ubah['leading'], $ubah['asal'], $ubah['dibuat_pada']], 'Asal & waktu dibuat tetap saat diubah.');

        $repo->hapus($id);
        $this->assertNull($repo->temukan($id));
        $this->assertNotContains($id, array_column($repo->semua(2026), 'id'));
    }

    public function test_database_menolak_nama_ganda_dan_id_bukan_uuid(): void
    {
        $repo = $this->repo(true);

        $this->assertNull($repo->temukan('123'));
        $repo->hapus('bukan-uuid');
        $this->assertCount(count(StubDataPaguRepository::contoh()), $repo->semua(2026));

        try {
            $repo->simpan(['tahun' => 2026, 'nama_kegiatan' => '  supervisi akademik DAN refleksi pembelajaran ', 'pagu' => ['DIPA' => 1]]);
            $this->fail('Nama ganda dalam satu tahun seharusnya ditolak.');
        } catch (QueryException) {
            $this->assertCount(count(StubDataPaguRepository::contoh()), $repo->semua(2026), 'Simpan gagal tidak meninggalkan baris.');
        }
    }
}
