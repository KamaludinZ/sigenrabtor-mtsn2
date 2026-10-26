<?php

namespace Tests\Feature\Monitoring;

use App\Contracts\KegiatanRepository;
use App\Livewire\Monitoring\Anggaran;
use App\Support\Monitoring\RingkasanAnggaran;
use Livewire\Livewire;
use Tests\TestCase;

class MonitoringAnggaranTest extends TestCase
{
    public function test_hanya_admin_dan_menu_aktif(): void
    {
        $this->get(route('monitoring.index'))->assertForbidden();

        $this->withSession(['stub_role' => 'admin'])->get(route('monitoring.index'))
            ->assertOk()
            ->assertSeeLivewire(Anggaran::class)
            ->assertSee('<title>Monitoring Anggaran · ', false)
            ->assertSeeHtml('href="'.route('monitoring.index').'"')
            ->assertSeeHtml('aria-current="page"');
    }

    public function test_ringkasan_contoh_kegiatan(): void
    {
        $ringkasan = RingkasanAnggaran::dari(app(KegiatanRepository::class)->semua());

        $this->assertSame(3, $ringkasan->jumlahKegiatan());
        $this->assertSame(['draf' => 3, 'diperiksa' => 0, 'dikembalikan' => 0, 'disetujui' => 0, 'final' => 0], $ringkasan->perStatus());
        $this->assertSame([
            'DIPA' => ['pagu' => 6_000_000, 'total' => 6_300_000, 'selisih' => -300_000, 'rab' => 2, 'melebihi' => 0, 'tanpa_pagu' => 1],
            'Komite' => ['pagu' => 20_000_000, 'total' => 20_450_000, 'selisih' => -450_000, 'rab' => 3, 'melebihi' => 1, 'tanpa_pagu' => 0],
        ], $ringkasan->perSumber());
        $this->assertSame(['pagu' => 26_000_000, 'total' => 26_750_000, 'selisih' => -750_000, 'rab' => 5, 'melebihi' => 1, 'tanpa_pagu' => 1], $ringkasan->total());

        $dipa = RingkasanAnggaran::dari(app(KegiatanRepository::class)->semua(), ['sumber' => 'DIPA']);
        $this->assertSame([2, 0], [$dipa->jumlahKegiatan(), $dipa->perSumber()['Komite']['rab']]);
        $this->assertSame(0, RingkasanAnggaran::dari(app(KegiatanRepository::class)->semua(), ['leading' => '1.1'])->jumlahKegiatan());
        $this->assertSame(0, RingkasanAnggaran::dari(app(KegiatanRepository::class)->semua(), ['status' => 'final'])->jumlahKegiatan());
    }

    public function test_halaman_dengan_saringan_di_url(): void
    {
        app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Final', 'status' => 'final', 'leading' => '1.1', 'sumber_dana' => [['sumber' => 'DIPA', 'pagu' => 1_000_000]]]);

        Livewire::test(Anggaran::class)
            ->assertSeeHtml('data-testid="kartu-kegiatan"')
            ->assertSeeHtmlInOrder(['Kegiatan', '4', 'data-status-jumlah="draf"', 'data-status-jumlah="final"'])
            ->assertSeeHtmlInOrder(['Total pagu', 'Rp27.000.000', 'Total RAB', 'Rp26.750.000', 'Sisa pagu', 'Rp250.000'])
            ->assertSeeHtml('data-testid="jumlah-melebihi"')
            ->assertSee('1 RAB melebihi pagunya')
            ->assertSee('1 RAB belum berpagu')
            ->assertSeeHtmlInOrder(['Total per leading', 'Rekap per kelompok belanja', 'Rekap per operator', 'Rincian per kegiatan'])
            ->set('sumber', 'Komite')
            ->assertSeeHtml('data-testid="saringan-aktif"')
            ->assertSee('Dana Komite')
            ->assertSeeHtmlInOrder(['Total pagu', 'Rp20.000.000', 'Total RAB', 'Rp20.450.000'])
            ->set('status', 'final')
            ->assertSeeHtmlInOrder(['Kegiatan', '0'])
            ->call('hapusSaringan', 'status')
            ->assertSet('status', '')
            ->assertSet('sumber', 'Komite')
            ->call('hapusSaringan')
            ->assertSet('sumber', '')
            ->assertDontSeeHtml('data-testid="saringan-aktif"');

        Livewire::withQueryParams(['leading' => '9.9', 'sumber' => 'BOS', 'status' => 'acak'])->test(Anggaran::class)
            ->assertSet('leading', '')->assertSet('sumber', '')->assertSet('status', '');
        Livewire::withQueryParams(['leading' => '1.1'])->test(Anggaran::class)
            ->assertSet('leading', '1.1')->assertSee('Waka Kurikulum')->assertSeeHtmlInOrder(['Total pagu', 'Rp1.000.000']);
    }

    public function test_perbandingan_pagu_dipa_dan_komite(): void
    {
        $ringkasan = RingkasanAnggaran::dari(app(KegiatanRepository::class)->semua());
        $data = $ringkasan->perbandinganSumber();

        $pilih = fn (array $d) => array_intersect_key($d, array_flip(['persen', 'porsi_pagu', 'porsi_total', 'total_tanpa_pagu', 'kelebihan', 'kondisi']));
        $this->assertSame(['persen' => 105.0, 'porsi_pagu' => 23.1, 'porsi_total' => 23.6, 'total_tanpa_pagu' => 900_000, 'kelebihan' => 0,
            'kondisi' => ['sesuai' => 0, 'sisa' => 1, 'melebihi' => 0, 'tanpa_pagu' => 1, 'rab_kosong' => 0]], $pilih($data['DIPA']));
        $this->assertSame(['persen' => 102.3, 'porsi_pagu' => 76.9, 'porsi_total' => 76.4, 'total_tanpa_pagu' => 0, 'kelebihan' => 450_000,
            'kondisi' => ['sesuai' => 2, 'sisa' => 0, 'melebihi' => 1, 'tanpa_pagu' => 0, 'rab_kosong' => 0]], $pilih($data['Komite']));
        $this->assertSame($ringkasan->perSumber()['DIPA'], array_intersect_key($data['DIPA'], $ringkasan->perSumber()['DIPA']), 'Angka dasar sama dengan perSumber().');
        foreach ($data as $d) {
            $this->assertSame($d['rab'], array_sum($d['kondisi']), 'Satu kondisi per RAB.');
        }
        $this->assertSame(['DIPA' => 0, 'Komite' => 1, 'keduanya' => 2, 'belum' => 0], $ringkasan->kombinasiSumber());
    }

    public function test_porsi_sumber_dana_selalu_berjumlah_100(): void
    {
        $porsi = function (array $sumberDana) {
            $repo = app(KegiatanRepository::class);
            $id = $repo->simpan(['nama_kegiatan' => 'Porsi '.uniqid(), 'leading' => '1.2', 'sumber_dana' => $sumberDana]);
            $ringkasan = RingkasanAnggaran::dari(array_values(array_filter($repo->semua(), fn ($k) => $k['id'] === $id)));

            return [array_column($ringkasan->perbandinganSumber(), 'porsi_pagu'), $ringkasan->kombinasiSumber()];
        };

        $this->assertSame([33.3, 66.7], $porsi([['sumber' => 'DIPA', 'pagu' => 1_000_000], ['sumber' => 'Komite', 'pagu' => 2_000_000]])[0]);
        [$hanyaKomite, $kombinasi] = $porsi([['sumber' => 'Komite', 'pagu' => 500_000]]);
        $this->assertSame([0.0, 100.0], $hanyaKomite);
        $this->assertSame(1, $kombinasi['Komite']);
        [$kosong, $kombinasi] = $porsi([]);
        $this->assertSame([null, null], $kosong);
        $this->assertSame(1, $kombinasi['belum']);
    }

    public function test_kartu_perbandingan_di_halaman(): void
    {
        Livewire::test(Anggaran::class)
            ->assertSeeHtml('data-testid="perbandingan-sumber"')
            ->assertSeeHtml('aria-label="Porsi pagu: DIPA 23,1% (Rp6.000.000), Komite 76,9% (Rp20.000.000)"')
            ->assertSeeHtml('data-testid="perbandingan-dipa" data-status="melebihi"')
            ->assertSee('Total RAB termasuk Rp900.000 dari 1 RAB yang pagunya belum diisi.')
            ->assertSee('(lebih Rp450.000)')
            ->assertSeeHtmlInOrder(['data-testid="perbandingan-komite"', 'Pagu', 'Rp20.000.000', 'Total RAB', 'Rp20.450.000', 'Pemakaian', '102,3%'])
            ->assertSeeHtml('href="'.route('kegiatan.index', ['sumber' => 'komite']).'"')
            ->assertSeeHtmlInOrder(['data-testid="kombinasi-sumber"', 'hanya DIPA', '0', 'hanya Komite', '1', 'DIPA &amp; Komite', '2'])
            ->set('sumber', 'Komite')
            ->assertSeeHtml('data-testid="perbandingan-disaring"')
            ->assertDontSeeHtml('data-testid="perbandingan-dipa"')
            ->assertDontSeeHtml('data-testid="porsi-pagu"')
            ->assertSeeHtml('data-testid="perbandingan-komite"')
            ->call('hapusSaringan', 'sumber')
            ->assertSeeHtml('data-testid="perbandingan-dipa"');
    }

    public function test_total_per_leading_dipa_dan_komite_terpisah(): void
    {
        $repo = app(KegiatanRepository::class);
        $repo->simpan(['nama_kegiatan' => 'Lomba', 'leading' => '1.1', 'sumber_dana' => [['sumber' => 'DIPA', 'pagu' => 1_000_000]]]);
        $ringkasan = RingkasanAnggaran::dari($repo->semua());
        $perLeading = array_column($ringkasan->perLeading(), null, 'kode');

        $mutu = $perLeading['1.6'];
        $this->assertSame(['pagu' => 6_000_000, 'total' => 6_300_000, 'rab' => 2, 'melebihi' => 0, 'tanpa_pagu' => 1, 'selisih' => null, 'persen' => null], $mutu['sumber']['DIPA'], 'Ada RAB tanpa pagu → selisih tidak dihitung.');
        $this->assertSame(['pagu' => 20_000_000, 'total' => 20_450_000, 'rab' => 3, 'melebihi' => 1, 'tanpa_pagu' => 0, 'selisih' => -450_000, 'persen' => 102.3], $mutu['sumber']['Komite']);
        $this->assertSame([26_000_000, 26_750_000, 5, null], [$mutu['pagu'], $mutu['total'], $mutu['rab'], $mutu['selisih']]);
        $this->assertSame(['pagu' => 1_000_000, 'total' => 0, 'rab' => 1, 'melebihi' => 0, 'tanpa_pagu' => 0, 'selisih' => 1_000_000, 'persen' => 0.0], $perLeading['1.1']['sumber']['DIPA']);
        $this->assertSame([0, null], [$perLeading['1.1']['sumber']['Komite']['rab'], $perLeading['1.1']['sumber']['Komite']['selisih']]);
        $this->assertSame([0, null], [$perLeading['1.3']['rab'], $perLeading['1.3']['selisih']]);

        foreach ($ringkasan->perSumber() as $sumber => $s) {
            $this->assertSame($s['total'], array_sum(array_map(fn ($b) => $b['sumber'][$sumber]['total'], $perLeading)), "Jumlah {$sumber} per leading = total sumber.");
            $this->assertSame($s['pagu'], array_sum(array_map(fn ($b) => $b['sumber'][$sumber]['pagu'], $perLeading)));
        }
    }

    public function test_tabel_total_per_leading_di_halaman(): void
    {
        app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Lomba', 'leading' => '1.1', 'sumber_dana' => [['sumber' => 'DIPA', 'pagu' => 1_000_000]]]);

        Livewire::test(Anggaran::class)
            ->assertSeeHtml('data-testid="bagian-monitoring-leading"')
            ->assertSee('2 dari 8 leading punya kegiatan')
            ->assertSeeHtml('data-leading="1.3"')
            ->assertSeeHtmlInOrder(['data-leading="1.6"', 'Rp6.000.000', 'Rp6.300.000', 'data-selisih=""', '1 belum berpagu', 'Rp20.000.000', 'Rp20.450.000', 'data-selisih="-450000"', '-Rp450.000', '1 dari 3 RAB melebihi'])
            ->assertSeeHtmlInOrder(['data-leading="1.1"', 'Rp1.000.000', 'Rp0', 'data-selisih="1000000"', 'Rp1.000.000'])
            ->assertSeeHtml('data-testid="jumlah-per-leading"')
            ->assertSeeHtmlInOrder(['data-testid="jumlah-per-leading"', 'Rp7.000.000', 'Rp6.300.000', 'Rp20.000.000', 'Rp20.450.000', 'Rp27.000.000', 'Rp26.750.000'])
            ->set('sumber', 'Komite')
            ->assertSee('sumber dana Komite')
            ->assertSeeHtml('min-w-[40rem]')
            ->assertSee('1 dari 8 leading punya kegiatan')
            ->assertSeeHtmlInOrder(['data-leading-kartu="1.1"', 'Belum ada kegiatan.'])
            ->set('sumber', '')
            ->set('leading', '1.6')
            ->assertSeeHtml('data-leading="1.6"')
            ->assertDontSeeHtml('data-leading="1.1"')
            ->assertDontSeeHtml('data-testid="jumlah-per-leading"')
            ->assertSee('1 dari 1 leading punya kegiatan');
    }
}
