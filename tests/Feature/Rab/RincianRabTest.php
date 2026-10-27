<?php

namespace Tests\Feature\Rab;

use App\Contracts\KegiatanRepository;
use App\Contracts\RabRepository;
use App\Repositories\EloquentKegiatanRepository;
use App\Repositories\EloquentRabRepository;
use App\Support\Rab\RincianRab;
use App\Support\Stub\StubKegiatanRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class RincianRabTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{bool}> */
    public static function penyimpanan(): array
    {
        return ['tiruan' => [false], 'database' => [true]];
    }

    private function kegiatan(bool $database, string $status = 'draf'): int
    {
        if ($database) {
            $this->app->singleton(KegiatanRepository::class, EloquentKegiatanRepository::class);
            $this->app->singleton(RabRepository::class, EloquentRabRepository::class);
        }

        return app(KegiatanRepository::class)->simpan(['status' => $status] + Arr::except(StubKegiatanRepository::contohDuaSumber(), ['id', 'status']));
    }

    #[DataProvider('penyimpanan')]
    public function test_tambah_dan_ubah_dengan_isian_mentah(bool $database): void
    {
        $id = $this->kegiatan($database);

        $hasil = RincianRab::tambah($id, 'DIPA', ['kode' => ' 521211 ', 'uraian' => '  Konsumsi   peserta ', 'volume' => '1,5', 'satuan' => ' kotak ', 'harga_satuan' => 'Rp40.000']);

        $this->assertSame(['kode' => '521211', 'uraian' => 'Konsumsi peserta', 'kategori' => 'bahan', 'volume' => 1.5, 'satuan' => 'kotak', 'harga_satuan' => 40_000], $hasil['item']);
        $this->assertSame(60_000, $hasil['jumlah']);
        $this->assertSame(['id' => $hasil['id']] + $hasil['item'], app(RabRepository::class)->rincian($id, 'DIPA')[0]);

        $ubah = RincianRab::ubah($id, 'DIPA', $hasil['id'], ['uraian' => 'Honor narasumber', 'volume' => '2', 'satuan' => 'OJ', 'harga_satuan' => '900.000']);
        $this->assertSame(['jasa', 2, 1_800_000], [$ubah['item']['kategori'], $ubah['item']['volume'], $ubah['jumlah']]);
        $this->assertNull(RincianRab::ubah($id, 'DIPA', 'tidak-ada', ['uraian' => 'ATK', 'volume' => 1, 'satuan' => 'paket', 'harga_satuan' => 1_000]));
        if ($database) {
            $this->assertSame(1_800_000, DB::table('anggaran_kegiatan')->where('activity_id', $id)->where('sumber_dana', 'DIPA')->value('total_rab'));
        }
    }

    #[DataProvider('penyimpanan')]
    public function test_hapus_dan_kembalikan_ke_posisi_semula(bool $database): void
    {
        $id = $this->kegiatan($database);
        $item = fn (string $uraian) => ['uraian' => $uraian, 'volume' => '1', 'satuan' => 'paket', 'harga_satuan' => '10.000'];
        $ids = array_map(fn (string $u) => RincianRab::tambah($id, 'Komite', $item($u))['id'], ['ATK', 'Spanduk', 'Konsumsi']);

        $terhapus = RincianRab::hapus($id, 'Komite', $ids[1]);
        $this->assertSame([1, 'Spanduk'], [$terhapus['index'], $terhapus['item']['uraian']]);
        $this->assertSame(['ATK', 'Konsumsi'], array_column(app(RabRepository::class)->rincian($id, 'Komite'), 'uraian'));
        $this->assertNull(RincianRab::hapus($id, 'Komite', $ids[1]), 'Sudah terhapus.');

        RincianRab::kembalikan($id, 'Komite', $terhapus['index'], $terhapus['item']);
        RincianRab::kembalikan($id, 'Komite', $terhapus['index'], $terhapus['item']);
        $this->assertSame([$ids, ['ATK', 'Spanduk', 'Konsumsi']], [array_column(app(RabRepository::class)->rincian($id, 'Komite'), 'id'), array_column(app(RabRepository::class)->rincian($id, 'Komite'), 'uraian')]);
        if ($database) {
            $this->assertSame(30_000, DB::table('anggaran_kegiatan')->where('activity_id', $id)->where('sumber_dana', 'Komite')->value('total_rab'));
        }
    }

    public function test_isian_tidak_sah_ditolak(): void
    {
        $id = $this->kegiatan(false);
        $sah = ['uraian' => 'Konsumsi peserta', 'volume' => '2', 'satuan' => 'kotak', 'harga_satuan' => '25.000'];

        foreach ([
            'volume' => ['volume' => '0'],
            'harga_satuan' => ['harga_satuan' => ''],
            'uraian' => ['uraian' => 'AB'],
            'kode' => ['kode' => '521/211'],
            'kategori' => ['kategori' => 'modal'],
        ] as $isian => $ubah) {
            try {
                RincianRab::tambah($id, 'DIPA', $ubah + $sah);
                $this->fail("{$isian} seharusnya ditolak.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey($isian, $e->errors(), $isian);
            }
        }

        try {
            RincianRab::tambah($id, 'BOS', $sah);
            $this->fail('Sumber dana seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertSame(['sumber' => ['Sumber dana harus DIPA atau Komite.']], $e->errors());
        }
        $this->assertSame([], app(RabRepository::class)->rincian($id, 'DIPA'));
    }

    public function test_kegiatan_tidak_ada_ditolak(): void
    {
        $this->expectException(RuntimeException::class);
        RincianRab::tambah(999, 'DIPA', []);
    }

    public function test_kegiatan_terkunci_ditolak(): void
    {
        $terkunci = $this->kegiatan(false, 'diperiksa');
        try {
            RincianRab::tambah($terkunci, 'DIPA', ['uraian' => 'Konsumsi peserta', 'volume' => '2', 'satuan' => 'kotak', 'harga_satuan' => '25.000']);
            $this->fail('Kegiatan terkunci seharusnya ditolak.');
        } catch (AuthorizationException $e) {
            $this->assertSame('RAB kegiatan berstatus Diperiksa sehingga terkunci.', $e->getMessage());
        }

        $this->expectException(AuthorizationException::class);
        RincianRab::hapus($terkunci, 'DIPA', 'apa-saja');
    }
}
