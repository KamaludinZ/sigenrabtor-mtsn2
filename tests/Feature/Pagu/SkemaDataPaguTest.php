<?php

namespace Tests\Feature\Pagu;

use App\Support\Stub\StubDataPaguRepository;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SkemaDataPaguTest extends TestCase
{
    use RefreshDatabase;

    private function ditolak(callable $aksi): void
    {
        try {
            DB::transaction($aksi);
        } catch (QueryException) {
            $this->addToAssertionCount(1);

            return;
        }
        $this->fail('Seharusnya ditolak database.');
    }

    private function dataPagu(string $nama, int $tahun = 2026, array $pagu = ['Komite' => 1_000_000]): string
    {
        $id = (string) Str::uuid();
        DB::table('data_pagu')->insert(['id' => $id, 'tahun_anggaran' => $tahun, 'nama_kegiatan' => $nama, 'kode_leading' => '1.6', 'asal' => 'impor']);
        foreach ($pagu as $sumber => $nilai) {
            DB::table('data_pagu_sumber')->insert(['data_pagu_id' => $id, 'sumber_dana' => $sumber, 'pagu' => $nilai]);
        }

        return $id;
    }

    public function test_contoh_data_pagu_tiruan_muat_di_tabel(): void
    {
        foreach (StubDataPaguRepository::contoh() as $p) {
            DB::table('data_pagu')->insert(['id' => $p['id'], 'tahun_anggaran' => $p['tahun'], 'nama_kegiatan' => $p['nama_kegiatan'], 'kode_leading' => $p['leading'], 'asal' => $p['asal']]);
            foreach (array_filter($p['pagu'], fn ($n) => $n !== null) as $sumber => $nilai) {
                DB::table('data_pagu_sumber')->insert(['data_pagu_id' => $p['id'], 'sumber_dana' => $sumber, 'pagu' => $nilai]);
            }
        }

        $this->assertSame(count(StubDataPaguRepository::contoh()), DB::table('data_pagu')->count());
        $this->assertSame(10_000_000, DB::table('data_pagu_sumber')->where('data_pagu_id', '9b2f1c3e-0001-4a51-8c11-000000000001')->where('sumber_dana', 'Komite')->value('pagu'));
    }

    public function test_nama_unik_per_tahun_dan_pagu_per_sumber_dana(): void
    {
        $this->dataPagu('Lomba Kreativitas Siswa');
        $this->dataPagu('Lomba Kreativitas Siswa', 2027);
        $this->ditolak(fn () => $this->dataPagu('  lomba   KREATIVITAS siswa '));

        $id = $this->dataPagu('Pengadaan Buku Referensi', pagu: ['DIPA' => 15_000_000]);
        $this->ditolak(fn () => DB::table('data_pagu_sumber')->insert(['data_pagu_id' => $id, 'sumber_dana' => 'BOS', 'pagu' => 1]));
        $this->ditolak(fn () => DB::table('data_pagu_sumber')->insert(['data_pagu_id' => $id, 'sumber_dana' => 'Komite', 'pagu' => 0]));
        $this->ditolak(fn () => DB::table('data_pagu_sumber')->insert(['data_pagu_id' => $id, 'sumber_dana' => 'DIPA', 'pagu' => 1]));
        $this->ditolak(fn () => DB::table('data_pagu')->insert(['id' => (string) Str::uuid(), 'tahun_anggaran' => 2026, 'nama_kegiatan' => 'Asal tak dikenal', 'asal' => 'tebakan']));
    }

    public function test_kegiatan_tertaut_dan_dilepas_saat_data_pagu_dihapus(): void
    {
        $id = $this->dataPagu('Workshop Kurikulum');
        $kegiatan = DB::table('activities')->insertGetId(['tahun_anggaran' => 2026, 'nama_kegiatan' => 'Workshop Kurikulum', 'data_pagu_id' => $id]);
        $this->ditolak(fn () => DB::table('activities')->insert(['tahun_anggaran' => 2026, 'nama_kegiatan' => 'Lain', 'data_pagu_id' => (string) Str::uuid()]));

        DB::table('data_pagu')->where('id', $id)->delete();

        $this->assertNull(DB::table('activities')->where('id', $kegiatan)->value('data_pagu_id'));
        $this->assertSame(0, DB::table('data_pagu_sumber')->count());
    }
}
