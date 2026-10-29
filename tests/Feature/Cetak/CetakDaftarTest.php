<?php

namespace Tests\Feature\Cetak;

use App\Contracts\KegiatanRepository;
use App\Livewire\Cetak\Daftar;
use App\Support\Dokumen\CetakDokumen;
use App\Support\Dokumen\DaftarCetak;
use App\Support\Dokumen\StatusCetak;
use App\Support\Navigation;
use Livewire\Livewire;
use Tests\TestCase;

class CetakDaftarTest extends TestCase
{
    private const LEADING = ['id' => 1, 'name' => 'Muslimin, M.Pd.', 'role' => 'leading', 'leadings' => ['1.6']];

    public function test_baris_per_kegiatan_dan_sumber_dana_tahun_aktif(): void
    {
        $repo = app(KegiatanRepository::class);
        $repo->simpan(['nama_kegiatan' => 'Kegiatan Tahun Lalu', 'leading' => '1.6', 'user_id' => 1, 'tahun_anggaran' => 2025, 'status' => 'draf']);
        $tanpaSumber = $repo->simpan(['nama_kegiatan' => 'Apel Pagi Madrasah', 'leading' => '1.6', 'user_id' => 1, 'status' => 'draf']);
        $baris = DaftarCetak::baris(self::LEADING);

        $this->assertSame(["{$tanpaSumber}|", '3|DIPA', '3|Komite', '2|DIPA', '2|Komite', '1|Komite'], array_column($baris, 'kunci'), 'Urut nama lalu sumber dana; tahun lain tidak ikut.');
        $satu = collect($baris)->firstWhere('kunci', '1|Komite');
        $kegiatan = $repo->temukan(1);
        $this->assertSame(CetakDokumen::namaBerkas(StatusCetak::dari($kegiatan, 'Komite'), ['cover', 'kak', 'rab']), $satu['nama_berkas']);
        $this->assertSame([['cover', 'kak', 'rab'], 'Draf'], [$satu['lembar'], $satu['label_status']]);
        $this->assertGreaterThan(0, $satu['rincian']);
        $this->assertSame(['cover', 'kak'], collect($baris)->firstWhere('kunci', "{$tanpaSumber}|")['lembar']);

        $this->assertCount(2, DaftarCetak::saring($baris, ['sumber' => 'DIPA']));
        $this->assertSame(['2|DIPA', '2|Komite'], array_column(DaftarCetak::saring($baris, ['cari' => 'SUPERVISI']), 'kunci'));
        $this->assertSame(['jumlah' => 6, 'siap' => 0, 'draf' => 6, 'kegiatan' => 4], DaftarCetak::ringkasan($baris));
        $this->assertSame([], DaftarCetak::saring($baris, ['keadaan' => 'siap']));
    }

    public function test_halaman_cetak_dan_pilihan_lembar(): void
    {
        $this->get(route('cetak.index'))->assertOk()->assertSee('Cetak &amp; Unduh', false)->assertSee('data-testid="daftar-cetak"', false);

        $halaman = Livewire::test(Daftar::class)
            ->assertSee('data-testid="panel-cetak-terpilih"', false)
            ->call('pilih', '2|DIPA')
            ->assertSet('pilihan', '2|DIPA')
            ->assertSee('RAB DIPA');
        $this->assertSame(['cover', 'kak', 'rab'], $halaman->instance()->lembarTerpilih());

        $halaman->call('aturLembar', 'cover')->assertSet('lembar', 'kak,rab');
        $this->assertStringContainsString('dokumen=kak%2Crab', $halaman->instance()->dokumen['tautan_cetak']);
        $this->assertStringContainsString('otomatis=1', $halaman->instance()->dokumen['tautan_cetak']);
        $halaman->call('aturLembar', 'kak')->call('aturLembar', 'rab')->assertSet('lembar', 'rab', 'Lembar terakhir tidak bisa dilepas.');
        $halaman->call('aturLembar', 'cover')->call('aturLembar', 'kak')->assertSet('lembar', '');
        $halaman->assertSee('data-testid="unduh-word-terpilih"', false)->assertSee('unduhWord(', false)->assertSee('dokumen=cover%2Ckak', false);
        $halaman->assertSee('data-testid="unduh-excel-terpilih"', false)->assertSee(route('kegiatan.rab.excel', ['kegiatan' => 2, 'sumber' => 'DIPA']), false);

        $halaman->call('pilih', '99|DIPA')->assertSet('pilihan', '2|DIPA', 'Dokumen di luar daftar diabaikan.');
        $halaman->set('sumber', 'Komite')->assertSee('data-kunci="1|Komite"', false)->assertDontSee('data-kunci="2|DIPA"', false)
            ->set('keadaan', 'siap')->assertSee('data-testid="cetak-kosong"', false)
            ->call('hapusSaringan')->assertSet('keadaan', '')->assertSet('sumber', '')->assertSee('data-kunci="2|DIPA"', false);
    }

    public function test_cakupan_akun_dan_menu(): void
    {
        $lain = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Kegiatan Leading Lain', 'leading' => '1.2', 'user_id' => 3, 'status' => 'draf']);
        $this->assertNotContains("{$lain}|", array_column(DaftarCetak::baris(self::LEADING), 'kunci'));
        $this->assertContains("{$lain}|", array_column(DaftarCetak::baris(['id' => 2, 'name' => 'Admin', 'role' => 'admin', 'leadings' => []]), 'kunci'));

        $menu = collect(Navigation::groups('leading'))->flatMap(fn (array $g) => $g['items'])->firstWhere('route', 'cetak.index');
        $this->assertSame(['Cetak & Unduh', true], [$menu['label'], $menu['available']]);
    }

    public function test_tombol_aksi_cetak_di_data_kegiatan_dan_ruang_kerja(): void
    {
        $daftar = $this->get(route('kegiatan.index'))->assertOk();
        $daftar->assertSee('data-testid="aksi-cetak" data-kegiatan="2"', false)
            ->assertSee(e(CetakDokumen::tautan(2, 'DIPA', ['cover', 'kak', 'rab'], otomatis: true)), false)
            ->assertSee(e(route('kegiatan.rab.excel', ['kegiatan' => 2, 'sumber' => 'DIPA'])), false)
            ->assertSee(e(route('cetak.index', ['pilih' => '2|DIPA'])), false);

        $rab = $this->get(route('kegiatan.rab', 1))->assertOk();
        $rab->assertSee('id="aksi-cetak-1-tab"', false)->assertSee('data-testid="aksi-cetak-set-Komite"', false);

        $tanpaSumber = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Apel Pagi Madrasah', 'leading' => '1.6', 'user_id' => 1, 'status' => 'draf']);
        $this->get(route('kegiatan.edit', $tanpaSumber))->assertOk()
            ->assertSee('data-testid="aksi-cetak-set-kak"', false)
            ->assertSee(e(CetakDokumen::tautan($tanpaSumber, null, ['cover', 'kak'], otomatis: true)), false)
            ->assertDontSee('data-testid="aksi-rab-excel-', false);
        $this->get(route('kegiatan.create'))->assertOk()->assertDontSee('data-testid="aksi-cetak"', false);
    }
}
