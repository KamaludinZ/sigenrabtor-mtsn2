<?php

namespace Tests\Feature\Master;

use App\Support\Stub\StubMasterKodeRepository;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\MasterKodeDb;
use Tests\Support\RujukanDb;
use Tests\TestCase;

class SkemaMasterKodeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RujukanDb::siapkan();
    }

    private function ditolak(callable $aksi): void
    {
        try {
            DB::transaction(function () use ($aksi) {
                // FK IKSK → SK DEFERRABLE diperiksa saat commit; transaksi tes tidak pernah commit.
                DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
                $aksi();
            });
        } catch (QueryException) {
            $this->addToAssertionCount(1);

            return;
        }
        $this->fail('Seharusnya ditolak database.');
    }

    private function muat(int $tahun): void
    {
        MasterKodeDb::muat($tahun);
    }

    public function test_seluruh_master_tiruan_muat_di_tabel(): void
    {
        $repo = new StubMasterKodeRepository;
        foreach ($repo->tahunTersedia() as $tahun) {
            $this->muat($tahun);
        }
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');

        foreach ($repo->tahunTersedia() as $tahun) {
            $this->assertSame(
                [count($repo->kodeSk($tahun)), count($repo->kodeIksk($tahun)), count($repo->kodeRktm($tahun)), count($repo->kodeEdm($tahun)), count($repo->komponenEdm($tahun)), count($repo->budayaMutu($tahun))],
                array_map(fn (string $t) => DB::table($t)->where('tahun_anggaran', $tahun)->count(), ['kode_sk', 'kode_iksk', 'kode_rktm', 'kode_edm', 'komponen_edm', 'budaya_mutu']),
                "TA {$tahun}",
            );
        }
        $this->assertTrue(Schema::hasColumns('kode_edm', ['tahun_anggaran', 'urutan', 'kode', 'komponen', 'uraian']));
    }

    public function test_kode_unik_per_tahun_dan_iksk_milik_sk_tahun_yang_sama(): void
    {
        $this->muat(2026);

        // Kode sama boleh di tahun lain, tidak boleh dobel di tahun yang sama.
        DB::table('kode_sk')->insert(['tahun_anggaran' => 2027, 'urutan' => 1, 'kode' => 'SK.10', 'uraian' => 'Sasaran 2027']);
        $this->ditolak(fn () => DB::table('kode_sk')->insert(['tahun_anggaran' => 2026, 'urutan' => 99, 'kode' => 'SK.10', 'uraian' => 'Dobel']));
        $this->ditolak(fn () => DB::table('kode_rktm')->insert(['tahun_anggaran' => 2026, 'urutan' => 99, 'kode' => '6.17.12', 'snp' => 'x', 'kegiatan' => 'x', 'sub_kegiatan' => 'x']));
        $this->ditolak(fn () => DB::table('budaya_mutu')->insert(['tahun_anggaran' => 2026, 'nomor' => 9, 'nama' => 'Integritas']));
        $this->ditolak(fn () => DB::table('budaya_mutu')->insert(['tahun_anggaran' => 2026, 'nomor' => 0, 'nama' => 'Nol']));

        // IKSK hanya untuk SK yang ada di master tahun itu; SK yang masih punya IKSK tidak bisa dihapus.
        $this->ditolak(fn () => DB::table('kode_iksk')->insert(['tahun_anggaran' => 2027, 'urutan' => 1, 'kode' => 'IKSK 11.1', 'kode_sk' => 'SK.11', 'uraian' => 'x']));
        $this->ditolak(fn () => DB::table('kode_sk')->where('tahun_anggaran', 2026)->where('kode', 'SK.10')->delete());
    }

    public function test_master_satu_tahun_bisa_diganti_dalam_satu_transaksi(): void
    {
        $this->muat(2026);

        DB::transaction(function () {
            DB::statement('SET CONSTRAINTS ALL DEFERRED');
            // Urutan bebas: SK dihapus lebih dulu, IKSK menyusul — sah selama konsisten saat transaksi selesai.
            DB::table('kode_sk')->where('tahun_anggaran', 2026)->delete();
            DB::table('kode_iksk')->where('tahun_anggaran', 2026)->delete();
            DB::table('kode_iksk')->insert(['tahun_anggaran' => 2026, 'urutan' => 1, 'kode' => 'IKSK 10.1', 'kode_sk' => 'SK.10', 'uraian' => 'Indikator baru']);
            DB::table('kode_sk')->insert(['tahun_anggaran' => 2026, 'urutan' => 1, 'kode' => 'SK.10', 'uraian' => 'Sasaran baru']);
            DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        });

        $this->assertSame([1, 1], [DB::table('kode_sk')->where('tahun_anggaran', 2026)->count(), DB::table('kode_iksk')->where('tahun_anggaran', 2026)->count()]);
    }

    public function test_format_kode_isian_wajib_dan_urutan_sesuai_pembaca_excel(): void
    {
        $this->muat(2026);
        $baris = fn (string $tabel, array $isi) => fn () => DB::table($tabel)->insert($isi + ['tahun_anggaran' => 2026, 'urutan' => 900]);

        foreach ([
            $baris('kode_sk', ['kode' => 'SK 99', 'uraian' => 'Sasaran']),
            $baris('kode_sk', ['kode' => 'SK.99', 'uraian' => '  ']),
            $baris('kode_iksk', ['kode' => 'IKSK99.1', 'kode_sk' => 'SK.1', 'uraian' => 'Indikator']),
            $baris('kode_iksk', ['kode' => 'IKSK 99.1', 'kode_sk' => 'sk.1', 'uraian' => 'Indikator']),
            $baris('kode_rktm', ['kode' => '6.17', 'snp' => 'SNP', 'kegiatan' => 'K', 'sub_kegiatan' => 'S']),
            $baris('kode_rktm', ['kode' => '6.17.99', 'snp' => 'SNP', 'kegiatan' => 'K', 'sub_kegiatan' => '']),
            $baris('kode_edm', ['kode' => 'a9', 'komponen' => 'A', 'uraian' => 'U']),
            $baris('kode_edm', ['kode' => 'A9', 'komponen' => 'B', 'uraian' => 'U']),
            $baris('kode_sk', ['kode' => 'SK.99', 'uraian' => 'Sasaran', 'urutan' => 1]),
            $baris('kode_sk', ['kode' => 'SK.99', 'uraian' => 'Sasaran', 'urutan' => 0]),
            fn () => DB::table('komponen_edm')->insert(['tahun_anggaran' => 2026, 'huruf' => 'AB', 'nama' => 'Komponen']),
            fn () => DB::table('budaya_mutu')->insert(['tahun_anggaran' => 2026, 'nomor' => 99, 'nama' => ' ']),
        ] as $aksi) {
            $this->ditolak($aksi);
        }

        DB::table('kode_edm')->insert(['tahun_anggaran' => 2026, 'urutan' => 900, 'kode' => 'A9', 'komponen' => 'A', 'uraian' => 'Indikator baru']);
        $this->assertSame(1, DB::table('kode_edm')->where('tahun_anggaran', 2026)->where('kode', 'A9')->count());
    }

    public function test_jejak_unggahan_master(): void
    {
        $unggahan = fn (array $isi) => $isi + ['id' => (string) Str::uuid(), 'tahun_anggaran' => 2026, 'cakupan' => 'semua', 'nama_berkas' => 'MASTER 2026.xlsx', 'ringkasan' => json_encode(['sk' => ['baru' => 3, 'berubah' => 1, 'tetap' => 10, 'hilang' => 0, 'gagal' => 0]]), 'user_id' => 2, 'pengguna' => 'Koordinator PUSKOM'];

        DB::table('master_kode_unggahan')->insert($unggahan([]));
        DB::table('master_kode_unggahan')->insert($unggahan(['cakupan' => 'edm', 'nama_berkas' => 'EDM.csv']));
        foreach ([['cakupan' => 'bos'], ['nama_berkas' => ' '], ['pengguna' => ''], ['ringkasan' => '[]'], ['tahun_anggaran' => 2031]] as $salah) {
            $this->ditolak(fn () => DB::table('master_kode_unggahan')->insert($unggahan($salah)));
        }

        DB::table('users')->where('id', 2)->delete();
        $this->assertSame([[null, 'Koordinator PUSKOM']], DB::table('master_kode_unggahan')->where('cakupan', 'semua')->get(['user_id', 'pengguna'])->map(fn ($b) => [$b->user_id, $b->pengguna])->all(), 'Nama pengguna saat itu tetap tercatat.');
        $this->assertSame(3, json_decode(DB::table('master_kode_unggahan')->where('cakupan', 'semua')->value('ringkasan'), true)['sk']['baru']);
    }
}
