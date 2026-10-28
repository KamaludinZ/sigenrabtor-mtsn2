<?php

namespace Tests\Feature\Basisdata;

use App\Repositories\EloquentMasterKodeRepository;
use App\Support\Basisdata\PindahMasterKode;
use App\Support\Stub\StubMasterKodeRepository;
use Database\Seeders\DataTiruanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\RujukanDb;
use Tests\TestCase;

class PindahMasterKodeTest extends TestCase
{
    use RefreshDatabase;

    private const BAGIAN = ['kode_sk', 'kode_iksk', 'kode_rktm', 'kode_edm', 'komponen_edm', 'budaya_mutu'];

    private function samaDenganTiruan(int $tahun): void
    {
        $tiruan = new StubMasterKodeRepository;
        $db = new EloquentMasterKodeRepository;
        foreach (['kodeSk', 'kodeIksk', 'kodeRktm', 'kodeEdm', 'komponenEdm', 'budayaMutu'] as $fungsi) {
            $this->assertEquals($tiruan->{$fungsi}($tahun), $db->{$fungsi}($tahun), "TA {$tahun} {$fungsi}");
        }
    }

    public function test_master_tiruan_tersinkron_per_tahun(): void
    {
        RujukanDb::siapkan();

        $uji = PindahMasterKode::jalankan(uji: true);
        $this->assertSame([2025, 2026], array_keys($uji['ganti']));
        $this->assertSame(0, DB::table('kode_sk')->count(), 'Mode uji dibatalkan.');

        $hasil = PindahMasterKode::jalankan();
        $this->assertSame([2025 => self::BAGIAN, 2026 => self::BAGIAN], $hasil['ganti']);
        $this->samaDenganTiruan(2025);
        $this->samaDenganTiruan(2026);
        $this->assertSame(['ganti' => [], 'tetap' => [2025, 2026], 'tanpa_tahun' => [], 'unggahan' => 0], PindahMasterKode::jalankan(), 'Dijalankan ulang tanpa perubahan.');

        // Bagian yang berubah di database dikembalikan sendiri; tahun tanpa baris tahun_anggaran dilewati.
        DB::table('budaya_mutu')->where('tahun_anggaran', 2026)->where('nomor', 1)->update(['nama' => 'Diubah langsung']);
        $this->assertSame(['ganti' => [2026 => ['budaya_mutu']], 'tetap' => [], 'tanpa_tahun' => [2027], 'unggahan' => 0], PindahMasterKode::jalankan(tahun: [2026, 2027]));
        $this->samaDenganTiruan(2026);
    }

    public function test_jejak_unggahan_disalin_sekali(): void
    {
        RujukanDb::siapkan();
        $id = (new StubMasterKodeRepository)->catatUnggahan(['tahun' => 2026, 'cakupan' => 'sk', 'nama_berkas' => 'perkin.xlsx', 'ringkasan' => ['sk' => ['baru' => 2]], 'user_id' => 2, 'pengguna' => 'Koordinator PUSKOM']);

        $this->assertSame(1, PindahMasterKode::jalankan()['unggahan']);
        $this->assertSame(0, PindahMasterKode::jalankan()['unggahan']);
        $unggahan = (new EloquentMasterKodeRepository)->unggahan(2026);
        $this->assertSame([$id, 'perkin.xlsx', ['sk' => ['baru' => 2]], 2], [$unggahan[0]['id'], $unggahan[0]['nama_berkas'], $unggahan[0]['ringkasan'], $unggahan[0]['user_id']]);
    }

    public function test_seeder_data_tiruan_menyiapkan_database_kosong(): void
    {
        $this->seed(DataTiruanSeeder::class);

        $this->assertSame([2025, 2026], DB::table('tahun_anggaran')->orderBy('tahun')->pluck('tahun')->map(fn ($t) => (int) $t)->all());
        $this->assertSame(2026, (int) DB::table('tahun_anggaran')->where('is_active', true)->value('tahun'));
        $this->assertGreaterThan(0, DB::table('leadings')->count());
        $this->samaDenganTiruan(2026);

        $this->seed(DataTiruanSeeder::class);
        $this->assertSame(2, DB::table('tahun_anggaran')->count(), 'Idempoten.');
        $this->artisan('sigenrabtor:pindah-master', ['--uji' => true])->assertSuccessful()->expectsOutputToContain('lolos verifikasi');
    }
}
