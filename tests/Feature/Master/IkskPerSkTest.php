<?php

namespace Tests\Feature\Master;

use App\Contracts\MasterKodeRepository;
use App\Repositories\EloquentMasterKodeRepository;
use App\Support\Master\IkskPerSk;
use App\Support\MasterKode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MasterKodeDb;
use Tests\TestCase;

class IkskPerSkTest extends TestCase
{
    use RefreshDatabase;

    public function test_pilihan_induk_dan_galat(): void
    {
        $pilihan = IkskPerSk::pilihan('SK.11');
        $this->assertSame(array_values(array_filter(array_column(MasterKode::kodeIksk(), 'kode'), fn ($k) => str_starts_with($k, 'IKSK 11.'))), array_column($pilihan, 'kode'));
        $this->assertSame([[], []], [IkskPerSk::pilihan(null), IkskPerSk::pilihan('')]);

        $this->assertSame(['SK.10', null, null], [IkskPerSk::induk('IKSK 10.6'), IkskPerSk::induk('IKSK 99.9'), IkskPerSk::induk('')]);

        $this->assertNull(IkskPerSk::galat('SK.10', 'IKSK 10.6'));
        $this->assertNull(IkskPerSk::galat('SK.10', null));
        $this->assertNull(IkskPerSk::galat('SK.10', 'IKSK 99.9'), 'Kode di luar master ditangani aturan pilihan master.');
        $this->assertSame('Pilih kode SK terlebih dahulu.', IkskPerSk::galat('', 'IKSK 10.6'));
        $this->assertSame('IKSK 10.6 bukan indikator dari SK.11.', IkskPerSk::galat('SK.11', 'IKSK 10.6'));
    }

    public function test_iksk_sesudah_sk_diganti(): void
    {
        $this->assertSame(['iksk' => 'IKSK 10.6', 'otomatis' => false], IkskPerSk::sesudahGantiSk('SK.10', 'IKSK 10.6'), 'Masih indikator SK baru: tetap.');
        $this->assertSame(['iksk' => '', 'otomatis' => false], IkskPerSk::sesudahGantiSk('SK.11', 'IKSK 10.6'), 'Bukan indikator SK baru: dikosongkan.');
        $this->assertSame(['iksk' => 'IKSK 12.1', 'otomatis' => true], IkskPerSk::sesudahGantiSk('SK.12', 'IKSK 10.6'), 'Satu-satunya indikator dipilih.');
        $this->assertSame(['iksk' => '', 'otomatis' => false], IkskPerSk::sesudahGantiSk('', 'IKSK 10.6'));
    }

    public function test_aturan_sama_dari_master_database_per_tahun(): void
    {
        $harapan = [IkskPerSk::pilihan('SK.10', 2025), IkskPerSk::pilihan('SK.10', 2026), IkskPerSk::sesudahGantiSk('SK.12', '', 2026)];

        MasterKodeDb::muat(2025);
        MasterKodeDb::muat(2026);
        $this->app->singleton(MasterKodeRepository::class, EloquentMasterKodeRepository::class);

        $this->assertSame($harapan, [IkskPerSk::pilihan('SK.10', 2025), IkskPerSk::pilihan('SK.10', 2026), IkskPerSk::sesudahGantiSk('SK.12', '', 2026)]);
    }
}
