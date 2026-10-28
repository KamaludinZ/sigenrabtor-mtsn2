<?php

namespace Tests\Feature\Rab;

use App\Contracts\KegiatanRepository;
use App\Contracts\RabRepository;
use App\Repositories\EloquentKegiatanRepository;
use App\Repositories\EloquentRabRepository;
use App\Support\Rab\KalkulatorRab;
use App\Support\Rab\SimpanSumberDana;
use App\Support\Stub\StubKegiatanRepository;
use App\Support\Stub\StubRab;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SimpanSumberDanaTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{bool}> */
    public static function penyimpanan(): array
    {
        return ['tiruan' => [false], 'database' => [true]];
    }

    /** Kegiatan contoh 2 (DIPA & Komite beserta RAB-nya) di penyimpanan pilihan; mengembalikan id. */
    private function kegiatan(bool $database): int
    {
        if (! $database) {
            return 2;
        }
        $this->app->singleton(KegiatanRepository::class, EloquentKegiatanRepository::class);
        $this->app->singleton(RabRepository::class, EloquentRabRepository::class);
        $id = app(KegiatanRepository::class)->simpan(Arr::except(StubKegiatanRepository::contohDuaSumber(), ['id', 'status']));
        foreach (StubRab::DAFTAR_CONTOH[2] as $sumber) {
            foreach (StubRab::rincian(2, $sumber) as $item) {
                app(RabRepository::class)->tambahItem($id, $sumber, $item);
            }
        }

        return $id;
    }

    public function test_perapian_daftar_maupun_peta(): void
    {
        $this->assertSame(
            [['sumber' => 'DIPA', 'pagu' => 6_000_000], ['sumber' => 'Komite', 'pagu' => null]],
            SimpanSumberDana::rapikan(['Komite' => '', 'DIPA' => 'Rp6.000.000']),
        );
        $this->assertSame(
            [['sumber' => 'DIPA', 'pagu' => 1_000], ['sumber' => 'BOS', 'pagu' => 5]],
            SimpanSumberDana::rapikan([['sumber' => 'BOS', 'pagu' => 5], ['sumber' => 'DIPA', 'pagu' => '1.000'], ['sumber' => 'DIPA', 'pagu' => 9]]),
            'Urut baku, sumber tak dikenal di akhir (agar ditolak), entri ganda memakai yang pertama.',
        );
    }

    #[DataProvider('penyimpanan')]
    public function test_hanya_sumber_dana_yang_disimpan_dan_rab_terlepas_dilaporkan(bool $database): void
    {
        $id = $this->kegiatan($database);
        $repo = app(KegiatanRepository::class);
        $sebelum = $repo->temukan($id);

        $hasil = SimpanSumberDana::simpan($id, ['Komite' => '3.000.000']);

        $this->assertSame([['sumber' => 'Komite', 'pagu' => 3_000_000]], $repo->temukan($id)['sumber_dana']);
        $this->assertSame([$sebelum['nama_kegiatan'], $sebelum['tempat']], [$repo->temukan($id)['nama_kegiatan'], $repo->temukan($id)['tempat']]);
        $komite = KalkulatorRab::hitung(StubRab::rincian(2, 'Komite'))->total;
        $this->assertSame(['Komite'], array_keys($hasil['pagu']));
        $this->assertSame(3_000_000 - $komite, $hasil['pagu']['Komite']->selisih);
        $this->assertSame(['DIPA' => 'RAB DIPA (Rp5.400.000) tetap tersimpan tetapi tidak dicetak selama DIPA tidak dipilih.'], $hasil['terlepas']);
        $this->assertCount(6, app(RabRepository::class)->rincian($id, 'DIPA'), 'Rincian DIPA tidak dihapus.');

        // Dipilih lagi: RAB DIPA kembali tercetak.
        $this->assertSame([], SimpanSumberDana::simpan($id, ['DIPA' => '6.000.000', 'Komite' => '3.000.000'])['terlepas']);
    }

    public function test_isian_tidak_sah_dan_kegiatan_terkunci_ditolak(): void
    {
        foreach ([[['sumber' => 'BOS', 'pagu' => 1_000]], ['Komite' => '0'], ['DIPA' => '200.000.000.000']] as $isian) {
            try {
                SimpanSumberDana::simpan(2, $isian);
                $this->fail('Seharusnya ditolak: '.json_encode($isian));
            } catch (ValidationException $e) {
                $this->assertStringStartsWith('sumber_dana.', array_key_first($e->errors()));
            }
        }
        $this->assertCount(2, app(KegiatanRepository::class)->temukan(2)['sumber_dana']);

        $repo = app(KegiatanRepository::class);
        $repo->simpan(['status' => 'final'] + $repo->temukan(2), 2);
        $this->expectException(AuthorizationException::class);
        SimpanSumberDana::simpan(2, ['DIPA' => '1.000']);
    }
}
