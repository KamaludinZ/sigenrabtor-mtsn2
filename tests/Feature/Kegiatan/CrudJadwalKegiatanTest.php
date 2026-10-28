<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Contracts\MatrikJadwalRepository;
use App\Repositories\EloquentKegiatanRepository;
use App\Repositories\EloquentMatrikJadwalRepository;
use App\Support\Kegiatan\JadwalKegiatan;
use App\Support\Kegiatan\MatrikTahapan;
use App\Support\Kegiatan\SimpanMatrikJadwal;
use App\Support\Stub\StubKegiatanRepository;
use App\Support\Stub\StubMatrikJadwalRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CrudJadwalKegiatanTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{bool}> */
    public static function penyimpanan(): array
    {
        return ['tiruan' => [false], 'database' => [true]];
    }

    /** Kegiatan contoh 3 (matrik & 6 baris jadwal tersimpan) di penyimpanan pilihan; mengembalikan id. */
    private function kegiatan(bool $database): int
    {
        if (! $database) {
            return 3;
        }
        $this->app->singleton(KegiatanRepository::class, EloquentKegiatanRepository::class);
        $this->app->singleton(MatrikJadwalRepository::class, EloquentMatrikJadwalRepository::class);
        $id = app(KegiatanRepository::class)->simpan(Arr::except(StubKegiatanRepository::contohMelebihiPagu(), ['id', 'status']));
        $contoh = StubMatrikJadwalRepository::contoh()[3];
        app(MatrikJadwalRepository::class)->simpan($id, MatrikTahapan::dari($contoh), JadwalKegiatan::dari($contoh));

        return $id;
    }

    /** @return list<string> */
    private function uraian(int $id): array
    {
        return array_column(app(MatrikJadwalRepository::class)->jadwal($id)->baris, 'uraian');
    }

    /** @return array<string, string> */
    private function galat(callable $aksi): array
    {
        try {
            $aksi();
        } catch (ValidationException $e) {
            return array_map(fn (array $p) => $p[0], $e->errors());
        }
        $this->fail('Seharusnya ditolak.');
    }

    #[DataProvider('penyimpanan')]
    public function test_tambah_ubah_geser_hapus_baris(bool $database): void
    {
        $id = $this->kegiatan($database);
        $matrik = app(MatrikJadwalRepository::class)->matrik($id)->butir;
        $awal = $this->uraian($id);

        $hasil = SimpanMatrikJadwal::tambahBarisJadwal($id, ['uraian' => '  Publikasi   kegiatan ', 'mulai' => '11', 'sampai' => '12']);
        $this->assertSame(['uraian' => 'Publikasi kegiatan', 'bulan' => [11, 12]], $hasil['jadwal']->baris[6]);

        SimpanMatrikJadwal::tambahBarisJadwal($id, ['uraian' => 'Survei awal', 'mulai' => 9], nomor: 1);
        $this->assertSame(['Survei awal', ...$awal, 'Publikasi kegiatan'], $this->uraian($id));

        SimpanMatrikJadwal::ubahBarisJadwal($id, 5, ['uraian' => 'Pelaksanaan workshop', 'mulai' => 11, 'sampai' => null]);
        $this->assertSame(['uraian' => 'Pelaksanaan workshop', 'bulan' => [11]], app(MatrikJadwalRepository::class)->jadwal($id)->baris[4]);

        SimpanMatrikJadwal::geserBarisJadwal($id, 1, 1);
        $this->assertSame([$awal[0], 'Survei awal'], array_slice($this->uraian($id), 0, 2));
        SimpanMatrikJadwal::geserBarisJadwal($id, 1, -1);
        $this->assertSame($awal[0], $this->uraian($id)[0], 'Baris pertama tidak bisa naik lagi.');

        $hasil = SimpanMatrikJadwal::hapusBarisJadwal($id, 2);
        $this->assertNotContains('Survei awal', $this->uraian($id));
        $this->assertCount(7, $hasil['jadwal']->baris);
        $this->assertNotContains('jadwal-4-dini', array_column($hasil['konsistensi']->temuan(), 'kunci'), 'Baris Juli diganti November → temuan hilang.');
        $this->assertSame($matrik, app(MatrikJadwalRepository::class)->matrik($id)->butir, 'Matrik tidak tersentuh.');
    }

    public function test_ganti_seluruh_jadwal_dan_jadwal_kosong(): void
    {
        SimpanMatrikJadwal::simpanJadwal(3, [['uraian' => 'Rapat', 'mulai' => 10, 'sampai' => 11], ['uraian' => '', 'mulai' => null, 'sampai' => null]]);
        $this->assertSame([['uraian' => 'Rapat', 'bulan' => [10, 11]], ['uraian' => '', 'bulan' => []]], app(MatrikJadwalRepository::class)->jadwal(3)->baris);

        SimpanMatrikJadwal::simpanJadwal(3, []);
        $this->assertSame([], app(MatrikJadwalRepository::class)->jadwal(3)->baris, 'Jadwal kosong tersimpan apa adanya.');
        $this->assertSame(StubMatrikJadwalRepository::contoh()[3]['matrik'], app(MatrikJadwalRepository::class)->matrik(3)->butir);
    }

    public function test_aturan_baris_batas_dan_nomor(): void
    {
        $this->assertSame([
            'jadwal.6.uraian' => 'Isi uraian kegiatan untuk bulan yang sudah dipilih.',
        ], $this->galat(fn () => SimpanMatrikJadwal::tambahBarisJadwal(3, ['uraian' => '', 'mulai' => 7])));
        $this->assertSame([
            'jadwal.0.sampai' => 'Bulan "s.d." tidak boleh sebelum bulan mulai.',
        ], $this->galat(fn () => SimpanMatrikJadwal::ubahBarisJadwal(3, 1, ['uraian' => 'Rapat', 'mulai' => 10, 'sampai' => 9])));
        $galat = $this->galat(fn () => SimpanMatrikJadwal::ubahBarisJadwal(3, 1, ['uraian' => str_repeat('a', JadwalKegiatan::MAKS_URAIAN + 1), 'mulai' => 13]));
        $this->assertSame(['jadwal.0.uraian', 'jadwal.0.mulai'], array_keys($galat));
        $this->assertSame('Uraian jadwal maksimal 200 karakter.', $galat['jadwal.0.uraian']);
        $this->assertSame(['nomor' => 'Baris jadwal nomor 7 tidak ada.'], $this->galat(fn () => SimpanMatrikJadwal::hapusBarisJadwal(3, 7)));
        $this->assertSame(['nomor' => 'Baris jadwal nomor 0 tidak ada.'], $this->galat(fn () => SimpanMatrikJadwal::geserBarisJadwal(3, 0, 1)));
        $this->assertSame(['jadwal' => 'Jadwal maksimal 12 baris.'], $this->galat(fn () => SimpanMatrikJadwal::simpanJadwal(3, array_fill(0, 13, ['uraian' => '', 'mulai' => null, 'sampai' => null]))));

        for ($i = 0; $i < JadwalKegiatan::MAKS_BARIS - JadwalKegiatan::JUMLAH_BAKU; $i++) {
            SimpanMatrikJadwal::tambahBarisJadwal(3, ['uraian' => "Tambahan {$i}"]);
        }
        $this->assertSame(['jadwal' => 'Jadwal sudah mencapai batas 12 baris.'], $this->galat(fn () => SimpanMatrikJadwal::tambahBarisJadwal(3, ['uraian' => 'Satu lagi'])));
        $this->assertCount(JadwalKegiatan::MAKS_BARIS, app(MatrikJadwalRepository::class)->jadwal(3)->baris);

        $repo = app(KegiatanRepository::class);
        $repo->simpan(['status' => 'final'] + $repo->temukan(3), 3);
        $this->expectException(AuthorizationException::class);
        SimpanMatrikJadwal::hapusBarisJadwal(3, 1);
    }

    public function test_simpan_matrik_dan_jadwal_sekaligus(): void
    {
        $hasil = SimpanMatrikJadwal::simpan(1, ['persiapan' => [['uraian' => 'Rapat', 'bulan' => [6]]], 'pelaksanaan' => [], 'evaluasi_pelaporan' => []], [['uraian' => 'Rapat', 'mulai' => 6, 'sampai' => null]]);

        $this->assertSame([['uraian' => 'Rapat', 'bulan' => [6]]], $hasil['matrik']->butir['persiapan']);
        $this->assertSame([['uraian' => 'Rapat', 'bulan' => [6]]], app(MatrikJadwalRepository::class)->jadwal(1)->baris);
        $this->assertSame(['matrik.persiapan.0.uraian', 'jadwal.0.uraian'], array_keys($this->galat(fn () => SimpanMatrikJadwal::simpan(1, ['persiapan' => [['uraian' => '', 'bulan' => []]]], [['uraian' => '', 'mulai' => 3]]))));
    }
}
