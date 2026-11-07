<?php

namespace Tests\Feature\Basisdata;

use App\Repositories\EloquentMasterKodeRepository;
use App\Support\Stub\StubMasterKodeRepository;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\RujukanDb;
use Tests\TestCase;

class SkemaRencanaAksiTest extends TestCase
{
    use RefreshDatabase;

    private const RA = [
        ['kode' => 'RA.1', 'uraian' => 'Penyusunan Program Tahunan', 'kode_sk' => 'SK.1.2133', 'kode_iksk' => 'IKSK.1.2133.1', 'kode_rktm' => '1.1.1',
            'unit' => 'Waka Kurikulum', 'kode_leading' => '1.1', 'pagu_dipa' => 0, 'pagu_komite' => 12450000, 'bulan' => [6, 12], 'kode_edm' => null, 'budaya_mutu' => []],
        ['kode' => 'RA.2', 'uraian' => 'Tendik memenuhi kualifikasi', 'kode_sk' => 'SK.10', 'kode_iksk' => 'IKSK 10.2', 'kode_rktm' => null,
            'unit' => null, 'kode_leading' => null, 'pagu_dipa' => 1000, 'pagu_komite' => 0, 'bulan' => [], 'kode_edm' => ['A1', 'C2'], 'budaya_mutu' => [1, 3]],
    ];

    private const RELASI = [['kode_edm' => 'A1', 'kode_rktm' => '2.3.1'], ['kode_edm' => 'A1', 'kode_rktm' => '2.3.2'], ['kode_edm' => 'C1', 'kode_rktm' => '1.1.1']];

    /** @return array<string, array{bool}> */
    public static function penyimpanan(): array
    {
        return ['tiruan' => [false], 'database' => [true]];
    }

    #[DataProvider('penyimpanan')]
    public function test_rencana_aksi_dan_relasi_tersimpan_berbentuk_sama(bool $database): void
    {
        RujukanDb::siapkan();
        $repo = $database ? new EloquentMasterKodeRepository : new StubMasterKodeRepository;

        $repo->ganti(2026, ['rencana_aksi' => self::RA, 'relasi_edm_rktm' => self::RELASI]);

        $this->assertSame(self::RA, $repo->rencanaAksi(2026));
        $this->assertSame(self::RELASI, $repo->relasiEdmRktm(2026));
        $this->assertSame([], $repo->rencanaAksi(2025));
        $repo->ganti(2026, ['rencana_aksi' => [self::RA[1]]]);
        $this->assertSame([self::RA[1]], $repo->rencanaAksi(2026), 'Diganti seluruhnya.');
        $this->assertSame(self::RELASI, $repo->relasiEdmRktm(2026), 'Bagian lain tetap.');
    }

    public function test_batasan_rencana_aksi_dan_pola_kode_kemenag(): void
    {
        RujukanDb::siapkan();
        $repo = new EloquentMasterKodeRepository;
        $repo->ganti(2026, [
            'kode_sk' => [['kode' => 'SK.1.2133', 'uraian' => 'Pemerataan guru'], ['kode' => 'SK.10', 'uraian' => 'Layanan']],
            'kode_iksk' => [['kode' => 'IKSK.4422.1.1.a', 'kode_sk' => 'SK.1.2133', 'uraian' => 'Indikator berhuruf'], ['kode' => 'IKSK 10.2', 'kode_sk' => 'SK.10', 'uraian' => 'Gaya lama']],
        ]);
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        $this->assertSame(['SK.1.2133', 'SK.10'], array_column($repo->kodeSk(2026), 'kode'));
        DB::table('master_kode_unggahan')->insert(['id' => (string) Str::uuid(), 'tahun_anggaran' => 2026, 'cakupan' => 'rencana_aksi', 'nama_berkas' => 'perkin.xlsx', 'pengguna' => 'Admin']);

        $tolak = function (string $tabel, array $baris): void {
            try {
                DB::transaction(fn () => DB::table($tabel)->insert(['tahun_anggaran' => 2026] + $baris));
                $this->fail("Seharusnya ditolak: {$tabel} ".json_encode($baris));
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        };
        $ra = ['kode' => 'RA.9', 'urutan' => 9, 'uraian' => 'Rencana', 'bulan' => '[]', 'budaya_mutu' => '[]'];
        $tolak('kode_sk', ['kode' => 'SK.1.a', 'uraian' => 'x', 'urutan' => 5]);
        $tolak('kode_iksk', ['kode' => 'IKSK.12', 'kode_sk' => 'SK.10', 'uraian' => 'x', 'urutan' => 5]);
        $tolak('rencana_aksi', ['kode' => 'X.1'] + $ra);
        $tolak('rencana_aksi', ['uraian' => ' '] + $ra);
        $tolak('rencana_aksi', ['kode_iksk' => 'IKSK 10'] + $ra);
        $tolak('rencana_aksi', ['kode_rktm' => '1.1'] + $ra);
        $tolak('rencana_aksi', ['bulan' => '[13]'] + $ra);
        $tolak('rencana_aksi', ['kode_edm' => '["a1"]'] + $ra);
        $tolak('rencana_aksi', ['kode_leading' => '9.9'] + $ra);
        $tolak('rencana_aksi', ['pagu_dipa' => -1] + $ra);
        $tolak('relasi_edm_rktm', ['kode_edm' => 'A1', 'kode_rktm' => 'n', 'urutan' => 1]);

        DB::table('rencana_aksi')->insert(['tahun_anggaran' => 2026, 'kode_iksk' => 'IKSK.4422.1.1.a', 'kode_edm' => '["A1"]', 'bulan' => '[1,12]'] + $ra);
        $this->assertSame(1, DB::table('rencana_aksi')->count());
    }
}
