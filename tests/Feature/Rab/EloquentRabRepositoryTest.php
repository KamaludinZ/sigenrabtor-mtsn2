<?php

namespace Tests\Feature\Rab;

use App\Contracts\KegiatanRepository;
use App\Contracts\RabRepository;
use App\Livewire\Kegiatan\Rab;
use App\Repositories\EloquentKegiatanRepository;
use App\Repositories\EloquentRabRepository;
use App\Support\Monitoring\RingkasanAnggaran;
use App\Support\Rab\KalkulatorRab;
use App\Support\Stub\StubKegiatanRepository;
use App\Support\Stub\StubRab;
use App\Support\Stub\StubRabRepository;
use App\Support\Terbilang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class EloquentRabRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private EloquentRabRepository $repo;

    private int $kegiatan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new EloquentRabRepository;
        $this->kegiatan = (new EloquentKegiatanRepository)->simpan(Arr::except(StubKegiatanRepository::contohDuaSumber(), ['id', 'status']));
    }

    /** @return list<string> */
    private function uraian(RabRepository $repo, int $kegiatan, string $sumber): array
    {
        return array_column($repo->rincian($kegiatan, $sumber), 'uraian');
    }

    public function test_bentuk_data_sama_dengan_tiruan(): void
    {
        foreach (StubRab::DAFTAR_CONTOH as $contoh => $daftarSumber) {
            foreach ($daftarSumber as $sumber) {
                $harapan = (new StubRabRepository)->rincian($contoh, $sumber);
                $kegiatan = (new EloquentKegiatanRepository)->simpan(['nama_kegiatan' => "Contoh {$contoh}"]);
                foreach ($harapan as $item) {
                    $this->repo->tambahItem($kegiatan, $sumber, Arr::except($item, 'id'));
                }

                $hasil = $this->repo->rincian($kegiatan, $sumber);
                $this->assertSame(array_map(fn ($i) => Arr::except($i, 'id'), $harapan), array_map(fn ($i) => Arr::except($i, 'id'), $hasil), "{$contoh}:{$sumber}");
                $this->assertSame(KalkulatorRab::hitung($hasil)->total, DB::table('anggaran_kegiatan')->where('activity_id', $kegiatan)->where('sumber_dana', $sumber)->value('total_rab'));
            }
        }
    }

    public function test_urutan_hapus_dan_urungkan_seperti_tiruan(): void
    {
        $tiruan = new StubRabRepository;
        $item = fn (string $uraian) => ['kode' => '521211', 'uraian' => $uraian, 'kategori' => 'bahan', 'volume' => 1.5, 'satuan' => 'paket', 'harga_satuan' => 3_333];

        foreach ([[$tiruan, 99], [$this->repo, $this->kegiatan]] as [$repo, $kegiatan]) {
            $id = array_map(fn ($u) => $repo->tambahItem($kegiatan, 'DIPA', $item($u)), ['ATK', 'Konsumsi', 'Spanduk']);
            $this->assertTrue($repo->ubahItem($kegiatan, 'DIPA', $id[1], ['kategori' => 'jasa'] + $item('Konsumsi rapat')));

            $hapus = $repo->hapusItem($kegiatan, 'DIPA', $id[0]);
            $this->assertSame([0, 'ATK', ['Konsumsi rapat', 'Spanduk']], [$hapus['index'], $hapus['item']['uraian'], $this->uraian($repo, $kegiatan, 'DIPA')]);
            $repo->sisipkanItem($kegiatan, 'DIPA', $hapus['index'], $hapus['item']);
            $this->assertSame([$id[0], 'ATK', 1.5], [$repo->rincian($kegiatan, 'DIPA')[0]['id'], $repo->rincian($kegiatan, 'DIPA')[0]['uraian'], $repo->rincian($kegiatan, 'DIPA')[0]['volume']]);

            $hapus = $repo->hapusItem($kegiatan, 'DIPA', $id[2]);
            $repo->sisipkanItem($kegiatan, 'DIPA', 99, $hapus['item']);
            $this->assertSame(['ATK', 'Konsumsi rapat', 'Spanduk'], $this->uraian($repo, $kegiatan, 'DIPA'));
            $this->assertSame(['jasa', false, null], [$repo->rincian($kegiatan, 'DIPA')[1]['kategori'], $repo->ubahItem($kegiatan, 'DIPA', 'tidak-ada', $item('X')), $repo->hapusItem($kegiatan, 'DIPA', 'tidak-ada')]);
        }

        // Urungkan terkirim dua kali tidak menggandakan item; total & terbilang mengikuti rincian.
        $this->repo->sisipkanItem($this->kegiatan, 'DIPA', 0, $this->repo->rincian($this->kegiatan, 'DIPA')[0]);
        $this->assertCount(3, $this->repo->rincian($this->kegiatan, 'DIPA'));
        $anggaran = DB::table('anggaran_kegiatan')->where('activity_id', $this->kegiatan)->where('sumber_dana', 'DIPA')->first();
        $this->assertSame([15_000, Terbilang::rupiah(15_000)], [$anggaran->total_rab, $anggaran->terbilang]);
    }

    public function test_termin_dan_sumber_tersimpan(): void
    {
        $this->assertSame([], $this->repo->termin($this->kegiatan, 'Komite'));
        $this->repo->simpanTermin($this->kegiatan, 'Komite', [['tanggal' => '2026-08-31', 'nominal' => 1_500_000], ['tanggal' => null, 'nominal' => 1_000_000]]);
        $this->assertSame([['tanggal' => '2026-08-31', 'nominal' => 1_500_000], ['tanggal' => null, 'nominal' => 1_000_000]], $this->repo->termin($this->kegiatan, 'Komite'));
        $this->assertSame(['Komite'], $this->repo->sumberTersimpan($this->kegiatan));

        // RAB untuk sumber dana yang tidak dipilih: barisnya dibuat tanpa mengubah pilihan sumber dana kegiatan.
        $kegiatan = (new EloquentKegiatanRepository)->simpan(['nama_kegiatan' => 'Lomba Kebersihan', 'sumber_dana' => [['sumber' => 'Komite', 'pagu' => 1_500_000]]]);
        $this->repo->tambahItem($kegiatan, 'DIPA', ['kode' => '', 'uraian' => 'Piala juara', 'kategori' => 'lain', 'volume' => 3, 'satuan' => 'buah', 'harga_satuan' => 150_000]);
        $this->assertSame([['sumber' => 'Komite', 'pagu' => 1_500_000]], (new EloquentKegiatanRepository)->temukan($kegiatan)['sumber_dana']);
        $this->assertSame(['DIPA'], $this->repo->sumberTersimpan($kegiatan));

        $this->repo->simpanTermin($this->kegiatan, 'Komite', []);
        $this->assertSame([], $this->repo->sumberTersimpan($this->kegiatan));
    }

    public function test_muat_banyak_kegiatan_dalam_tiga_kueri(): void
    {
        $this->app->singleton(RabRepository::class, EloquentRabRepository::class);
        $repo = app(RabRepository::class);
        $kegiatan = [];
        foreach (StubKegiatanRepository::contohSemua() as $contoh) {
            $id = (new EloquentKegiatanRepository)->simpan(Arr::except($contoh, ['id', 'status']));
            foreach (StubRab::DAFTAR_CONTOH[$contoh['id']] as $sumber) {
                foreach (StubRab::rincian($contoh['id'], $sumber) as $item) {
                    $repo->tambahItem($id, $sumber, $item);
                }
                $repo->simpanTermin($id, $sumber, StubRab::termin($contoh['id'], $sumber));
            }
            $kegiatan[] = (new EloquentKegiatanRepository)->temukan($id);
        }
        $baca = fn (RabRepository $r) => array_map(fn ($k) => [$r->sumberTersimpan($k['id']), $r->rincian($k['id'], 'DIPA'), $r->termin($k['id'], 'Komite')], $kegiatan);
        $harapan = $baca(new EloquentRabRepository);

        DB::enableQueryLog();
        $repo->muat([...array_column($kegiatan, 'id'), 999]);
        $this->assertCount(3, DB::getQueryLog());
        $this->assertSame($harapan, $baca($repo));
        $this->assertSame([[], []], [$repo->rincian(999, 'DIPA'), $repo->sumberTersimpan(999)]);
        $this->assertCount(3, DB::getQueryLog(), 'Pembacaan sesudah muat() tidak menanyai database.');

        // Ringkasan Monitoring seluruh kegiatan: RAB dimuat sekali.
        DB::flushQueryLog();
        $repo = new EloquentRabRepository;
        $this->app->instance(RabRepository::class, $repo);
        RingkasanAnggaran::dari($kegiatan);
        $this->assertCount(3, DB::getQueryLog());

        // Menulis membuang muatan: pembacaan berikutnya memakai data terbaru.
        $repo->tambahItem($kegiatan[0]['id'], 'Komite', ['kode' => '', 'uraian' => 'Item baru', 'kategori' => 'lain', 'volume' => 1, 'satuan' => 'paket', 'harga_satuan' => 1_000]);
        $this->assertSame('Item baru', Arr::last($repo->rincian($kegiatan[0]['id'], 'Komite'))['uraian']);
    }

    public function test_halaman_rab_memakai_database(): void
    {
        $this->app->singleton(KegiatanRepository::class, EloquentKegiatanRepository::class);
        $this->app->singleton(RabRepository::class, EloquentRabRepository::class);
        foreach (StubRab::rincian(2, 'DIPA') as $item) {
            $this->repo->tambahItem($this->kegiatan, 'DIPA', $item);
        }
        $awal = $this->uraian($this->repo, $this->kegiatan, 'DIPA');
        $id = fn (int $i) => $this->repo->rincian($this->kegiatan, 'DIPA')[$i]['id'];

        $komponen = Livewire::test(Rab::class, ['kegiatan' => $this->kegiatan])->call('geserItem', $id(0), 1);
        $this->assertSame([$awal[1], $awal[0]], array_slice($this->uraian($this->repo, $this->kegiatan, 'DIPA'), 0, 2));

        $komponen->call('pindahSumberItem', $id(2), 'Komite');
        $this->assertSame([$awal[2]], $this->uraian($this->repo, $this->kegiatan, 'Komite'));
        $this->assertCount(count($awal) - 1, $this->repo->rincian($this->kegiatan, 'DIPA'));
    }
}
