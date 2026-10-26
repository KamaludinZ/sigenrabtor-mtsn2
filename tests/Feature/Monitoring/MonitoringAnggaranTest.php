<?php

namespace Tests\Feature\Monitoring;

use App\Contracts\KegiatanRepository;
use App\Livewire\Monitoring\Anggaran;
use App\Support\Kegiatan\TahunKegiatan;
use App\Support\Monitoring\RingkasanAnggaran;
use App\Support\Rab\RekapKelompok;
use App\Support\Stub\StubData;
use App\Support\Stub\StubPengaturan;
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

    public function test_rincian_per_kegiatan_dan_urutannya(): void
    {
        $ringkasan = RingkasanAnggaran::dari(app(KegiatanRepository::class)->semua());
        $urutan = fn (string $urut, ?string $arah = null) => array_column($ringkasan->rincianKegiatan($urut, $arah), 'id');

        $rincian = $ringkasan->rincianKegiatan();
        $this->assertSame([1, 3, 2], array_column($rincian, 'id'), 'Bawaan: total RAB terbesar lebih dulu.');
        $review = $rincian[1];
        $this->assertSame(['6.17.13', 'Tim Penjamin Mutu', 7_500_000, 8_850_000, null, 450_000, 2, 1, 1],
            [$review['kode_rktm'], $review['leading_nama'], $review['pagu'], $review['total'], $review['selisih'], $review['kelebihan'], $review['rab_jumlah'], $review['melebihi'], $review['tanpa_pagu']]);
        $this->assertSame([['DIPA', null, 900_000, 'tanpa_pagu'], ['Komite', 7_500_000, 7_950_000, 'melebihi']],
            array_map(fn ($r) => [$r['sumber'], $r['pagu'], $r['total'], $r['status']->status], $review['rab']));
        $this->assertSame(106.0, $review['rab'][1]['persen']);

        $this->assertSame([2, 3, 1], $urutan('total', 'asc'));
        $this->assertSame([1, 2, 3], $urutan('pagu'));
        $this->assertSame([3, 2, 1], $urutan('kelebihan'), 'RAB yang melebihi pagunya sendiri tetap di atas walau ada RAB tanpa pagu; seri menurut nama.');
        $this->assertSame([3, 2, 1], $urutan('nama'));
        $this->assertSame([1, 3, 2], $urutan('acak'), 'Kunci tidak dikenal → urutan bawaan.');

        $sumberKomite = RingkasanAnggaran::dari(app(KegiatanRepository::class)->semua(), ['sumber' => 'Komite'])->rincianKegiatan();
        $this->assertSame([['Komite'], ['Komite'], ['Komite']], array_map(fn ($b) => array_column($b['rab'], 'sumber'), $sumberKomite));
    }

    public function test_tabel_rincian_urut_halaman_dan_saringan(): void
    {
        $repo = app(KegiatanRepository::class);
        for ($i = 1; $i <= 19; $i++) {
            $repo->simpan(['nama_kegiatan' => sprintf('Kegiatan uji %02d', $i), 'leading' => '1.2', 'sumber_dana' => [['sumber' => 'DIPA', 'pagu' => $i * 100_000]]]);
        }
        $repo->simpan(['nama_kegiatan' => 'Tanpa sumber', 'leading' => '1.2']);

        $halaman = Livewire::test(Anggaran::class)
            ->assertSeeHtml('data-testid="tabel-rincian"')
            ->assertSeeHtmlInOrder(['data-kegiatan="1"', 'data-kegiatan="3"', 'data-kegiatan="2"'])
            ->assertSeeHtmlInOrder(['data-kegiatan="3"', 'Review RKJM dan Penyusunan RKTM', '6.17.13', 'Jumlah 2 RAB', 'Rp8.850.000', 'data-rab="DIPA"', 'Belum diisi', 'Rp900.000', 'Pagu belum diisi', 'data-rab="Komite"', 'Rp7.500.000', 'Rp7.950.000', '106%', 'Melebihi pagu Rp450.000'])
            ->assertSeeHtml('aria-sort="descending"')
            ->assertSee('Menampilkan 1–20 dari 23 kegiatan')
            ->assertSeeHtmlInOrder(['data-testid="jumlah-rincian"', 'Jumlah 23 kegiatan', 'Rp45.000.000', 'Rp26.750.000', '1 RAB melebihi pagunya'])
            ->call('keHalaman', 2)
            ->assertSet('halaman', 2)
            ->assertSee('Menampilkan 21–23 dari 23 kegiatan')
            ->assertSeeHtml('Sumber dana belum dipilih')
            ->call('keHalaman', 9)
            ->assertSet('halaman', 2);

        $halaman->call('urutkan', 'total')
            ->assertSet('arah', 'asc')
            ->assertSet('halaman', 1)
            ->assertSeeHtml('aria-sort="ascending"')
            ->set('urut', 'nama')
            ->assertSet('arah', 'asc')
            ->assertSeeHtmlInOrder(['Kegiatan uji 01', 'Kegiatan uji 19', 'Review RKJM'])
            ->assertDontSee('Workshop Penyusunan')
            ->call('urutkan', 'kelebihan')
            ->assertSet('urut', 'kelebihan')
            ->assertSet('arah', 'desc')
            ->assertSeeHtmlInOrder(['data-kegiatan="3"', 'Kegiatan uji 01'])
            ->call('keHalaman', 2)
            ->set('leading', '1.6')
            ->assertSet('halaman', 1)
            ->assertSee('Menampilkan 1–3 dari 3 kegiatan')
            ->set('status', 'final')
            ->assertSeeHtml('data-testid="rincian-kosong"')
            ->assertSee('Tidak ada kegiatan yang cocok dengan saringan.');

        Livewire::withQueryParams(['urut' => 'acak', 'arah' => 'miring', 'hal' => 7])->test(Anggaran::class)
            ->assertSet('urut', 'total')->assertSet('arah', 'desc')->assertSet('halaman', 2);
    }

    public function test_urutan_tabel_per_leading(): void
    {
        $repo = app(KegiatanRepository::class);
        $lomba = $repo->simpan(['nama_kegiatan' => 'Lomba', 'leading' => '1.1', 'sumber_dana' => [['sumber' => 'DIPA', 'pagu' => 1_000_000]]]);
        $tanpaLeading = $repo->simpan(['nama_kegiatan' => 'Tanpa leading']);
        $ringkasan = RingkasanAnggaran::dari($repo->semua());
        $kode = fn (string $urut, ?string $arah = null) => array_column($ringkasan->perLeading(urut: $urut, arah: $arah), 'kode');

        $master = ['1.1', '1.2', '1.3', '1.4', '1.5', '1.6', '1.7', '1.8'];
        $this->assertSame([...$master, null], $kode('kode'));
        $this->assertSame([...array_reverse($master), null], $kode('kode', 'desc'), 'Baris tanpa leading selalu di akhir.');
        $this->assertSame([...$master, null], $kode('acak'));
        $this->assertSame(['1.6', '1.1', null], array_slice($kode('jumlah'), 0, 3));
        $this->assertSame(['1.6', '1.1'], array_slice($kode('semua_total'), 0, 2));
        $this->assertSame(['1.6'], array_slice($kode('komite_selisih'), 0, 1), 'Selisih naik: melebihi pagu lebih dulu.');
        $this->assertSame(['1.1', '1.2', '1.3'], array_slice($kode('dipa_selisih'), 0, 3), 'Selisih yang belum bisa dihitung (1.6: RAB tanpa pagu) di akhir, menurut kode.');
        $this->assertSame(['1.6', '1.1', '1.2'], array_slice($kode('komite_selisih', 'desc'), 0, 3), 'Arah turun: nilai kosong tetap di akhir, menurut kode.');
        $this->assertArrayHasKey('komite_pagu', RingkasanAnggaran::urutLeading());
        $this->assertSame(['Selisih gabungan terkecil (melebihi dulu)', 'asc'], RingkasanAnggaran::urutLeading()['semua_selisih']);

        $this->assertSame([3, 2, 1, $lomba, $tanpaLeading], array_column($ringkasan->rincianKegiatan('pemakaian'), 'id'), 'Pemakaian tertinggi (106%) dulu, seri (100%) menurut nama, 0% lalu tanpa RAB di akhir.');
        $this->assertSame([$lomba, 3, 2, 1, $tanpaLeading], array_column($ringkasan->rincianKegiatan('leading'), 'id'), 'Urutan master leading; leading sama → nama; tanpa leading di akhir.');
    }

    public function test_judul_kolom_per_leading_mengurutkan(): void
    {
        app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Lomba', 'leading' => '1.1', 'sumber_dana' => [['sumber' => 'DIPA', 'pagu' => 1_000_000]]]);

        Livewire::test(Anggaran::class)
            ->assertSeeHtml('data-testid="urut-leading"')
            ->call('urutkanLeading', 'komite_selisih')
            ->assertSet('urutLeading', 'komite_selisih')
            ->assertSet('arahLeading', 'asc')
            ->assertSeeHtmlInOrder(['data-leading="1.6"', 'data-leading="1.1"'])
            ->assertSeeHtml("wire:click=\"urutkanLeading('komite_selisih')\"")
            ->call('urutkanLeading', 'komite_selisih')
            ->assertSet('arahLeading', 'desc')
            ->set('urutLeading', 'semua_total')
            ->assertSet('arahLeading', 'desc')
            ->assertSeeHtmlInOrder(['data-leading="1.6"', 'data-leading="1.1"', 'data-leading="1.2"'])
            ->set('urutLeading', 'kode')
            ->assertSet('arahLeading', 'asc')
            ->assertSeeHtmlInOrder(['data-leading="1.1"', 'data-leading="1.2"', 'data-leading="1.6"'])
            ->set('urutLeading', 'dipa_total')
            ->set('sumber', 'Komite')
            ->assertSet('urutLeading', 'kode')
            ->call('urutkanLeading', 'tidak_ada')
            ->assertSet('urutLeading', 'kode')
            ->call('urutkan', 'pemakaian')
            ->assertSet('urut', 'pemakaian')
            ->assertSet('arah', 'desc')
            ->assertSeeHtml('aria-sort="descending"');

        Livewire::withQueryParams(['urut_leading' => 'dipa_total', 'sumber' => 'Komite'])->test(Anggaran::class)
            ->assertSet('urutLeading', 'kode')->assertSet('arahLeading', 'asc');
        Livewire::withQueryParams(['urut_leading' => 'acak', 'arah_leading' => 'miring'])->test(Anggaran::class)
            ->assertSet('urutLeading', 'kode')->assertSet('arahLeading', 'asc');
        Livewire::withQueryParams(['urut_leading' => 'komite_total', 'arah_leading' => 'desc'])->test(Anggaran::class)
            ->assertSet('urutLeading', 'komite_total')->assertSet('arahLeading', 'desc')
            ->assertSeeHtml('aria-sort="descending"');
    }

    public function test_rekap_per_kelompok_belanja(): void
    {
        $ringkasan = RingkasanAnggaran::dari(app(KegiatanRepository::class)->semua());
        $rekap = $ringkasan->perKelompok();

        $this->assertSame(['DIPA', 'Komite'], $rekap['sumber']);
        $this->assertSame([26_750_000, ['DIPA' => 6_300_000, 'Komite' => 20_450_000], 33], [$rekap['total'], $rekap['totalPerSumber'], $rekap['jumlahItem']]);
        $this->assertSame($ringkasan->total()['total'], $rekap['total'], 'Sama dengan total RAB ringkasan.');
        $kelompok = array_column($rekap['kelompok'], null, 'kunci');
        $this->assertSame([['DIPA' => 2_100_000, 'Komite' => 9_380_000], 11_480_000, 42.9, 20, 3], [$kelompok['bahan']['perSumber'], $kelompok['bahan']['total'], $kelompok['bahan']['persen'], $kelompok['bahan']['jumlahItem'], $kelompok['bahan']['kegiatan']]);
        $this->assertSame([13_620_000, 50.9], [$kelompok['jasa']['total'], $kelompok['jasa']['persen']]);
        $this->assertSame([1_650_000, 6.2], [$kelompok['lain']['total'], $kelompok['lain']['persen']]);
        $this->assertSame('Review RKJM dan Penyusunan RKTM', $kelompok['bahan']['item'][0]['kegiatan'], 'Item terbesar membawa nama kegiatannya.');
        $this->assertSame(['521211', '521213', '521219', '524113'], array_column($rekap['kode'], 'kode'));
        $this->assertSame($rekap['total'], array_sum(array_column($rekap['kode'], 'total')));

        $this->assertSame([['DIPA', 6_300_000], ['Komite', 20_450_000], ['Gabungan', 26_750_000]], array_map(fn ($b) => [$b['label'], $b['total']], RekapKelompok::barisBatang($rekap)));
        $this->assertSame(['bahan' => 9_380_000, 'jasa' => 10_020_000, 'lain' => 1_050_000], RekapKelompok::barisBatang($rekap)[1]['nilai']);

        $komite = RingkasanAnggaran::dari(app(KegiatanRepository::class)->semua(), ['sumber' => 'Komite'])->perKelompok();
        $this->assertSame([['Komite'], 20_450_000], [$komite['sumber'], $komite['total']]);
        $this->assertCount(1, RekapKelompok::barisBatang($komite), 'Satu sumber dana → tanpa batang gabungan.');
        $kosong = RingkasanAnggaran::dari(app(KegiatanRepository::class)->semua(), ['leading' => '1.1'])->perKelompok();
        $this->assertSame([['DIPA', 'Komite'], 0, 0.0], [$kosong['sumber'], $kosong['total'], $kosong['kelompok'][0]['persen']]);
    }

    public function test_kartu_rekap_kelompok_di_halaman(): void
    {
        Livewire::test(Anggaran::class)
            ->assertSeeHtml('data-testid="bagian-monitoring-kelompok"')
            ->assertSeeHtmlInOrder(['data-kelompok-legenda="bahan"', '42,9%', 'data-kelompok-legenda="jasa"', '50,9%', 'data-kelompok-legenda="lain"', '6,2%'])
            ->assertSeeHtml('data-testid="batang-gabungan"')
            ->assertSeeHtmlInOrder(['data-kelompok="bahan"', '20 item · 3 kegiatan', 'Rp2.100.000', 'Rp9.380.000', 'Rp11.480.000'])
            ->assertSeeHtmlInOrder(['data-kode-akun="521213"', 'Belanja Honor Output Kegiatan', 'Rp13.620.000'])
            ->set('sumber', 'DIPA')
            ->assertDontSeeHtml('data-testid="batang-gabungan"')
            ->assertSeeHtmlInOrder(['data-kelompok="jasa"', 'Rp3.600.000'])
            ->set('status', 'final')
            ->assertSeeHtml('data-testid="kelompok-kosong"');
    }

    public function test_pembuat_kegiatan_tercatat_sekali(): void
    {
        $repo = app(KegiatanRepository::class);
        $this->assertSame([1, 1, 2], [$repo->temukan(1)['user_id'], $repo->temukan(2)['user_id'], $repo->temukan(3)['user_id']]);

        $id = $repo->simpan(['nama_kegiatan' => 'Baru oleh leading']);
        $this->assertSame(1, $repo->temukan($id)['user_id']);
        session(['stub_role' => 'admin']);
        $repo->simpan(['nama_kegiatan' => 'Diubah admin', 'user_id' => 2], $id);
        $this->assertSame(1, $repo->temukan($id)['user_id'], 'Mengubah kegiatan tidak mengganti pembuatnya.');
        $this->assertSame(2, $repo->temukan($repo->simpan(['nama_kegiatan' => 'Baru oleh admin']))['user_id']);
    }

    public function test_rekap_per_operator(): void
    {
        $repo = app(KegiatanRepository::class);
        $operator = RingkasanAnggaran::dari($repo->semua())->perOperator(StubPengaturan::pengguna());

        $this->assertSame([1, 2], array_column($operator, 'user_id'), 'Total RAB terbesar lebih dulu.');
        [$leading, $admin] = $operator;
        $this->assertSame(['Muslimin, M.Pd.', 'leading', 'Tim Penjamin Mutu', 2, ['1.6'], 18_500_000, 17_900_000, 600_000, 3, 0, 0],
            [$leading['nama'], $leading['peran'], $leading['jabatan'], $leading['jumlah'], $leading['leading'], $leading['pagu'], $leading['total'], $leading['selisih'], $leading['rab'], $leading['melebihi'], $leading['tanpa_pagu']]);
        $this->assertStringStartsWith(StubData::tahunAktif()['tahun'].'-01-05', $leading['terakhir'], 'Perubahan terakhir = kegiatan terbaru miliknya.');
        $this->assertSame(['Koordinator PUSKOM', 'admin', 1, 8_850_000, null, 1, 1, 7_950_000],
            [$admin['nama'], $admin['peran'], $admin['jumlah'], $admin['total'], $admin['selisih'], $admin['melebihi'], $admin['tanpa_pagu'], $admin['sumber']['Komite']['total']]);

        $repo->simpan(['nama_kegiatan' => 'Lama', 'leading' => '1.1', 'user_id' => 99, 'sumber_dana' => [['sumber' => 'DIPA', 'pagu' => 50_000_000]]]);
        $operator = RingkasanAnggaran::dari($repo->semua())->perOperator(StubPengaturan::pengguna());
        $this->assertSame([1, 2, null], array_column($operator, 'user_id'), 'Pembuat yang tidak dikenal di baris terakhir.');
        $this->assertSame(['Pembuat tidak tercatat', 1, ['1.1']], [$operator[2]['nama'], $operator[2]['jumlah'], $operator[2]['leading']]);
        $this->assertSame(RingkasanAnggaran::dari($repo->semua())->total()['total'], array_sum(array_column($operator, 'total')));
    }

    public function test_kartu_rekap_operator_di_halaman(): void
    {
        $tahun = StubData::tahunAktif()['tahun'];

        Livewire::test(Anggaran::class)
            ->assertSeeHtml('data-testid="bagian-monitoring-operator"')
            ->assertDontSee('Segera disiapkan')
            ->assertSeeHtmlInOrder(['data-operator="1"', 'Muslimin, M.Pd.', 'Leading', 'untuk 1.6 Tim Penjamin Mutu', 'Rp17.900.000', '2 kegiatan', 'data-status-operator="draf"', '3 RAB', "Terakhir mengubah 5 Januari {$tahun}"])
            ->assertSeeHtmlInOrder(['data-operator="2"', 'Koordinator PUSKOM', 'Admin', 'Admin Aplikasi ·', 'untuk 1.6 Tim Penjamin Mutu', 'Rp8.850.000', '1 kegiatan', '1 melebihi pagu', '1 belum berpagu'])
            ->assertSeeHtmlInOrder(['data-testid="jumlah-operator"', 'Jumlah 2 operator · 3 kegiatan', 'Rp26.750.000'])
            ->set('sumber', 'DIPA')
            ->assertSeeHtmlInOrder(['data-operator="1"', 'Rp5.400.000', 'data-operator="2"', 'Rp900.000'])
            ->set('status', 'final')
            ->assertSeeHtml('data-testid="operator-kosong"');
    }

    public function test_tahun_anggaran_kegiatan(): void
    {
        $repo = app(KegiatanRepository::class);
        $this->assertSame([2026, 2026, 2026], array_map(fn ($id) => $repo->temukan($id)['tahun_anggaran'], [1, 2, 3]));
        $this->assertSame(2025, TahunKegiatan::dari(['tahun_anggaran' => 2025]));
        $this->assertSame(2025, TahunKegiatan::dari(['dibuat_pada' => '2025-03-01T08:00:00+07:00']), 'Kegiatan lama: tahun dibuatnya.');
        $this->assertSame(2026, TahunKegiatan::dari([]), 'Tanpa keterangan: tahun aktif.');

        $id = $repo->simpan(['nama_kegiatan' => 'Lama', 'tahun_anggaran' => 2025]);
        $repo->simpan(['nama_kegiatan' => 'Lama diubah', 'tahun_anggaran' => 2026], $id);
        $this->assertSame(2025, $repo->temukan($id)['tahun_anggaran'], 'Mengubah kegiatan tidak memindahkan tahunnya.');
        $this->assertSame(2026, $repo->temukan($repo->simpan(['nama_kegiatan' => 'Baru']))['tahun_anggaran']);

        $semua = $repo->semua();
        $this->assertSame(4, RingkasanAnggaran::dari($semua, ['tahun' => 2026])->jumlahKegiatan());
        $this->assertSame(1, RingkasanAnggaran::dari($semua, ['tahun' => 2025])->jumlahKegiatan());
        $this->assertSame(5, RingkasanAnggaran::dari($semua)->jumlahKegiatan(), 'Tanpa saringan tahun: semua.');
    }

    public function test_saringan_rentang_tanggal_pelaksanaan(): void
    {
        $repo = app(KegiatanRepository::class);
        $bulanSaja = $repo->simpan(['nama_kegiatan' => 'Bulan saja', 'bulan_pelaksanaan' => [3, 10]]);
        $repo->simpan(['nama_kegiatan' => 'Tanpa jadwal']);
        $semua = $repo->semua();
        $id = fn (array $saringan) => array_column(RingkasanAnggaran::dari($semua, $saringan)->rincianKegiatan('nama'), 'id');

        $this->assertSame([1], $id(['dari' => '2026-07-01', 'sampai' => '2026-07-31']), 'Workshop 14 Juli.');
        $this->assertSame([$bulanSaja, 3, 2], $id(['dari' => '2026-09-01']), 'Bulan Oktober, Review November, Supervisi 31 Agu–4 Sep beririsan.');
        $this->assertSame([$bulanSaja, 2, 1], $id(['sampai' => '2026-08-31']), 'Bulan Maret, Supervisi mulai 31 Agustus, Workshop Juli.');
        $this->assertSame([$bulanSaja], $id(['dari' => '2026-10-15', 'sampai' => '2026-10-20']), 'Tanpa tanggal: dicocokkan per bulan pelaksanaan.');
        $this->assertSame([], $id(['dari' => '2026-04-01', 'sampai' => '2026-06-30']), 'Bulan Maret & Oktober tidak menutup April–Juni.');
        $this->assertSame(1, RingkasanAnggaran::dari($semua, ['dari' => '2026-01-01'])->tanpaJadwal);
        $this->assertSame(0, RingkasanAnggaran::dari($semua)->tanpaJadwal);
        $this->assertCount(5, $id(['dari' => '2026-02-30']), 'Tanggal tidak sah diabaikan.');
    }

    public function test_formulir_saringan_tahun_dan_tanggal(): void
    {
        app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Kegiatan 2025', 'leading' => '1.1', 'tahun_anggaran' => 2025, 'sumber_dana' => [['sumber' => 'DIPA', 'pagu' => 2_000_000]]]);

        Livewire::test(Anggaran::class)
            ->assertSeeHtmlInOrder(['Tahun anggaran', '2026 · aktif', '2025 · terkunci'])
            ->assertSeeHtml('min="2026-01-01" max="2026-12-31"')
            ->assertSeeHtmlInOrder(['data-testid="kartu-kegiatan"', 'Kegiatan', '3'])
            ->set('tahun', '2025')
            ->assertSee('TA 2025')
            ->assertSeeHtml('data-testid="tahun-bukan-aktif"')
            ->assertSeeHtml('min="2025-01-01" max="2025-12-31"')
            ->assertSeeHtmlInOrder(['data-testid="kartu-kegiatan"', 'Kegiatan', '1'])
            ->assertSeeHtml('data-kegiatan="4"')
            ->set('tahun', '2026')
            ->assertSet('tahun', '')
            ->assertDontSeeHtml('data-testid="tahun-bukan-aktif"')
            ->set('dari', '2026-07-01')
            ->set('sampai', '2026-07-31')
            ->assertSee('Pelaksanaan 1 Juli 2026 – 31 Juli 2026')
            ->assertSeeHtmlInOrder(['data-testid="kartu-kegiatan"', 'Kegiatan', '1'])
            ->assertSeeHtml('data-kegiatan="1"')
            ->assertDontSeeHtml('data-kegiatan="3"')
            ->set('dari', '2026-08-15')
            ->assertSeeHtml('data-testid="rentang-terbalik"')
            ->assertSee('Kosongkan tanggal')
            ->assertSeeHtmlInOrder(['data-testid="kartu-kegiatan"', 'Kegiatan', '3'])
            ->set('sampai', '2026-02-30')
            ->assertSet('sampai', '')
            ->assertSee('Pelaksanaan sejak 15 Agustus 2026')
            ->call('hapusSaringan', 'tanggal')
            ->assertSet('dari', '')
            ->set('tahun', '2025')
            ->set('leading', '1.1')
            ->call('hapusSaringan')
            ->assertSet('tahun', '')
            ->assertSet('leading', '');

        Livewire::withQueryParams(['tahun' => '1999', 'dari' => 'kemarin', 'sampai' => '2026-13-01'])->test(Anggaran::class)
            ->assertSet('tahun', '')->assertSet('dari', '')->assertSet('sampai', '');
        Livewire::withQueryParams(['tahun' => '2025'])->test(Anggaran::class)->assertSet('tahun', '2025');
    }
}
