<?php

namespace Tests\Feature\Master;

use App\Contracts\MasterKodeRepository;
use App\Contracts\RiwayatPengaturanRepository;
use App\Contracts\TahunAnggaranRepository;
use App\Livewire\Master\Daftar;
use App\Repositories\EloquentMasterKodeRepository;
use App\Support\Master\SalinMasterKode;
use App\Support\MasterKode;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\MasterKodeDb;
use Tests\Support\RujukanDb;
use Tests\TestCase;

class SalinMasterKodeTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN = ['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin', 'leadings' => []];

    /** @return array<string, array{bool}> */
    public static function penyimpanan(): array
    {
        return ['tiruan' => [false], 'database' => [true]];
    }

    private function siapkan(bool $database): void
    {
        app(TahunAnggaranRepository::class)->tambah(2027);
        if ($database) {
            RujukanDb::siapkan();
            DB::table('tahun_anggaran')->insert(['tahun' => 2027, 'is_active' => false, 'is_locked' => false]);
            MasterKodeDb::muat(2025);
            MasterKodeDb::muat(2026);
            $this->app->singleton(MasterKodeRepository::class, EloquentMasterKodeRepository::class);
            DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        }
    }

    /** @return array<string, int> */
    private function jumlah(int $tahun): array
    {
        return ['sk' => count(MasterKode::kodeSk($tahun)), 'iksk' => count(MasterKode::kodeIksk(tahun: $tahun)), 'rktm' => count(MasterKode::kodeRktm($tahun)), 'edm' => count(MasterKode::kodeEdm($tahun)), 'budaya' => count(MasterKode::budayaMutu($tahun))];
    }

    #[DataProvider('penyimpanan')]
    public function test_salin_master_ke_tahun_kosong(bool $database): void
    {
        $this->siapkan($database);
        $this->assertSame(2026, SalinMasterKode::sumber(2027), 'Tahun bermaster terdekat sebelumnya.');
        $this->assertSame(2025, SalinMasterKode::sumber(2026));
        $this->assertFalse(MasterKode::tersedia(2027));

        $hasil = SalinMasterKode::salin(2026, 2027, self::ADMIN);
        $this->assertSame([$this->jumlah(2026), []], [$hasil['disalin'], $hasil['dilewati']]);
        $this->assertSame([MasterKode::kodeSk(2026), MasterKode::kodeIksk(tahun: 2026), MasterKode::kodeEdm(2026), MasterKode::komponenEdm(2026), MasterKode::budayaMutu(2026)],
            [MasterKode::kodeSk(2027), MasterKode::kodeIksk(tahun: 2027), MasterKode::kodeEdm(2027), MasterKode::komponenEdm(2027), MasterKode::budayaMutu(2027)]);
        $this->assertTrue(MasterKode::tersedia(2027));

        $unggahan = app(MasterKodeRepository::class)->unggahan(2027)[0];
        $this->assertSame(['semua', 'Salinan master TA 2026', ['baru' => $this->jumlah(2026)['sk']]], [$unggahan['cakupan'], $unggahan['nama_berkas'], $unggahan['ringkasan']['sk']]);
        $this->assertStringStartsWith('Master kode TA 2026 disalin ke TA 2027: SK '.$this->jumlah(2026)['sk'].', IKSK', app(RiwayatPengaturanRepository::class)->semua()[0]['keterangan']);

        // Master tahun lain tidak berubah; menyalin ulang tidak menimpa apa pun.
        app(MasterKodeRepository::class)->ganti(2027, ['budaya_mutu' => [['nomor' => 1, 'nama' => 'Khusus 2027']]]);
        $this->assertSame(5, count(MasterKode::budayaMutu(2026)));
        $this->assertSame(['disalin' => [], 'dilewati' => ['sk', 'iksk', 'rktm', 'edm', 'budaya']], SalinMasterKode::salin(2026, 2027, self::ADMIN));
        $this->assertSame([['nomor' => 1, 'nama' => 'Khusus 2027']], MasterKode::budayaMutu(2027));
    }

    public function test_hanya_kelompok_yang_kosong_dan_izin(): void
    {
        $this->siapkan(false);
        app(MasterKodeRepository::class)->ganti(2027, ['kode_sk' => [['kode' => 'SK.1', 'uraian' => 'Sasaran 2027']], 'budaya_mutu' => [['nomor' => 1, 'nama' => 'Jujur']]]);

        $hasil = SalinMasterKode::salin(2026, 2027, self::ADMIN);
        $this->assertSame(['rktm', 'edm'], array_keys($hasil['disalin']), 'SK terisi → SK & IKSK dilewati bersama agar induk IKSK tetap sah.');
        $this->assertSame(['sk', 'iksk', 'budaya'], $hasil['dilewati']);
        $this->assertSame([[['kode' => 'SK.1', 'uraian' => 'Sasaran 2027']], []], [MasterKode::kodeSk(2027), MasterKode::kodeIksk(tahun: 2027)]);
        $this->assertStringEndsWith('(sudah terisi, tidak diubah: SK, IKSK, 5 Budaya Mutu).', app(RiwayatPengaturanRepository::class)->semua()[0]['keterangan']);

        foreach ([fn () => SalinMasterKode::salin(2026, 2025, self::ADMIN), fn () => SalinMasterKode::salin(2026, 2031, self::ADMIN), fn () => SalinMasterKode::salin(2026, 2027, ['role' => 'leading'])] as $aksi) {
            try {
                $aksi();
                $this->fail('Seharusnya ditolak.');
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }
        foreach ([2027, 2031] as $dari) {
            try {
                SalinMasterKode::salin($dari, 2027, self::ADMIN);
                $this->fail('Sumber tidak sah.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('dari', $e->errors());
            }
        }
    }

    public function test_halaman_dan_endpoint_salin(): void
    {
        $this->siapkan(false);
        $this->withSession(['stub_role' => 'admin']);

        Livewire::withQueryParams(['ta' => '2027'])->test(Daftar::class)
            ->assertSeeHtml('data-testid="master-kode-kosong"')
            ->assertSeeHtml('data-testid="salin-master"')
            ->assertSee('Salin dari TA 2026')
            ->call('salinMaster', 2026)
            ->assertDispatched('notify', message: 'Master kode TA 2026 disalin ke TA 2027 ('.array_sum($this->jumlah(2026)).' kode).', type: 'success')
            ->assertDontSeeHtml('data-testid="master-kode-kosong"')
            ->assertSeeHtml('data-testid="unggahan-terakhir"');

        $this->postJson(route('master.salin', ['tahun' => 2027]))
            ->assertOk()
            ->assertJsonPath('dari', 2026)
            ->assertJsonPath('disalin', []);
        $this->postJson(route('master.salin', ['tahun' => 2025]), ['dari' => 2026])->assertForbidden();
        $this->postJson(route('master.salin', ['tahun' => 2027]), ['dari' => 2031])->assertStatus(422);
        $this->withSession(['stub_role' => 'leading'])->postJson(route('master.salin', ['tahun' => 2027]))->assertForbidden();
    }
}
