<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Contracts\MatrikJadwalRepository;
use App\Contracts\RabRepository;
use App\Livewire\Kegiatan\SalinImpor;
use App\Support\Kegiatan\Duplikat\DuplikatKegiatan;
use App\Support\Kegiatan\JadwalKegiatan;
use App\Support\Kegiatan\MatrikTahapan;
use App\Support\Rab\AnggaranKegiatan;
use Livewire\Livewire;
use Tests\TestCase;

class SalinImporTest extends TestCase
{
    private function kegiatanLalu(string $nama, string $leading): int
    {
        return app(KegiatanRepository::class)->simpan(['nama_kegiatan' => $nama, 'leading' => $leading, 'tahun_anggaran' => 2025, 'status' => 'final', 'sumber_dana' => [['sumber' => 'DIPA', 'pagu' => 2_000_000]]]);
    }

    public function test_halaman_dan_tautan_dari_data_kegiatan(): void
    {
        $this->get(route('kegiatan.index'))->assertSeeHtml('href="'.route('kegiatan.salin_impor').'"');

        $this->get(route('kegiatan.salin_impor'))
            ->assertOk()
            ->assertSeeLivewire(SalinImpor::class)
            ->assertSee('<title>Duplikat &amp; Impor · ', false)
            ->assertSee('Menjadi draf TA 2026');
    }

    public function test_tab_duplikat_memilih_kegiatan_tahun_lalu_dalam_cakupan_akun(): void
    {
        $this->assertSame([2025], DuplikatKegiatan::tahunSumber());

        Livewire::test(SalinImpor::class)
            ->assertSeeHtml('data-testid="panel-duplikat"')
            ->assertSee('Belum ada kegiatan TA 2025 yang bisa diduplikat.')
            ->assertSeeHtml('data-perubahan="kode"');

        $milik = $this->kegiatanLalu('Workshop Mutu 2025', '1.6');
        $lain = $this->kegiatanLalu('Lomba Sains', '1.1');

        Livewire::test(SalinImpor::class)
            ->assertSeeHtml('data-sumber-duplikat="'.$milik.'"')
            ->assertDontSeeHtml('data-sumber-duplikat="'.$lain.'"')
            ->assertDontSeeHtml('data-sumber-duplikat="1"')
            ->set('pilihan', [$milik, $lain, 999])
            ->assertSet('pilihan', [$milik], 'Hanya kegiatan sumber dalam cakupan akun.')
            ->call('kosongkanPilihan')
            ->call('pilihSemua')
            ->assertSet('pilihan', [$milik])
            ->assertSee('1 kegiatan dipilih')
            ->call('kosongkanPilihan')
            ->assertSet('pilihan', []);

        session(['stub_role' => 'admin']);
        Livewire::test(SalinImpor::class)
            ->assertSeeHtml('data-sumber-duplikat="'.$lain.'"')
            ->set('cari', 'lomba')
            ->assertDontSeeHtml('data-sumber-duplikat="'.$milik.'"')
            ->set('cari', '')
            ->set('leading', '1.6')
            ->assertSeeHtml('data-sumber-duplikat="'.$milik.'"')
            ->assertDontSeeHtml('data-sumber-duplikat="'.$lain.'"')
            ->call('pilihSemua')
            ->assertSet('pilihan', [$milik])
            ->assertSee('1 kegiatan dipilih');

        Livewire::withQueryParams(['tab' => 'acak', 'dari' => 2030])->test(SalinImpor::class)
            ->assertSet('tab', 'duplikat')
            ->assertSet('tahunSumber', 0);
    }

    public function test_daftar_kegiatan_tahun_lalu_dengan_rincian_dan_yang_sudah_ada(): void
    {
        $baru = app(KegiatanRepository::class)->simpan([
            'nama_kegiatan' => 'Rapat Kerja Tahunan', 'leading' => '1.6', 'tahun_anggaran' => 2025, 'status' => 'final', 'kode_rktm' => '6.17.12',
            'tujuan' => 'menyusun program kerja', 'tempat' => 'Aula', 'peserta_jumlah' => 40, 'peserta_jenis' => 'Guru', 'lama_hari' => 2,
            'bulan_pelaksanaan' => [1, 2], 'sumber_dana' => [['sumber' => 'Komite', 'pagu' => 3_000_000]],
        ]);
        // Nama sama dengan contoh kegiatan 2 di TA 2026 → sudah diduplikat.
        $ganda = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => '  supervisi akademik dan refleksi  PEMBELAJARAN', 'leading' => '1.6', 'tahun_anggaran' => 2025]);

        $baris = collect(DuplikatKegiatan::sumber(app(KegiatanRepository::class)->semua(), 2025, ['role' => 'leading', 'leadings' => ['1.6']]))->keyBy('id');
        $this->assertSame(['Aula', '40 orang (Guru)', 2, 'Januari – Februari 2025', '6.17.12', null], [$baris[$baru]['tempat'], $baris[$baru]['peserta'], $baris[$baru]['lama_hari'], $baris[$baru]['bulan'], $baris[$baru]['kode_rktm'], $baris[$baru]['sudah_ada']]);
        $this->assertSame([['sumber' => 'Komite', 'total' => 0, 'item' => 0]], $baris[$baru]['sumber']);
        $this->assertSame(2, $baris[$ganda]['sudah_ada']);

        Livewire::test(SalinImpor::class)
            ->assertSeeHtml('data-testid="daftar-tahun-lalu"')
            ->assertSeeHtml('data-sumber-duplikat="'.$ganda.'" data-sudah-ada="ya"')
            ->assertSee('Sudah ada di TA 2026')
            ->assertSeeHtml('href="'.route('kegiatan.edit', ['kegiatan' => 2]).'"')
            ->assertSeeHtml('data-rincian-lalu="'.$baru.'"')
            ->assertSee('menyusun program kerja')
            ->call('pilihSemua')
            ->assertSet('pilihan', [$baru], 'Yang sudah ada dilewati.')
            ->set('pilihan', [$baru, $ganda])
            ->assertSet('pilihan', [$baru]);
    }

    public function test_rencana_duplikat_menggeser_tanggal_dan_mencocokkan_kode_serta_pagu(): void
    {
        $this->assertSame(['2026-07-14', '2025-02-28', null, 'kosong'], [
            DuplikatKegiatan::geserTahun('2025-07-14', 1), DuplikatKegiatan::geserTahun('2024-02-29', 1), DuplikatKegiatan::geserTahun('', 1), DuplikatKegiatan::geserTahun('kosong', 1),
        ]);

        $sumber = [
            'id' => 50, 'tahun_anggaran' => 2025, 'nama_kegiatan' => 'pelatihan penulisan karya ilmiah  guru', 'leading' => '1.6', 'status' => 'final',
            'tanggal_dokumen' => '2025-01-06', 'tanggal_mulai' => '2025-03-10', 'kode_rktm' => '1.1.2', 'kode_sk' => 'SK.99', 'kode_edm' => 'A1', 'budaya_mutu' => 9,
            'sumber_dana' => [['sumber' => 'Komite', 'pagu' => 1_000_000]], 'riwayat_status' => [['aksi' => 'final']], 'kunci' => ['oleh' => 'Admin'],
        ];
        ['data' => $data, 'catatan' => $catatan] = DuplikatKegiatan::rencana($sumber);

        $this->assertSame(['2026-01-06', '2026-03-10', '1.1.2', null, 'A1', null], [$data['tanggal_dokumen'], $data['tanggal_mulai'], $data['kode_rktm'], $data['kode_sk'], $data['kode_edm'], $data['budaya_mutu']]);
        $this->assertArrayNotHasKey('status', $data);
        $this->assertArrayNotHasKey('riwayat_status', $data);
        $this->assertArrayNotHasKey('kunci', $data);
        // Nama sama (tanpa memandang huruf & spasi) dengan Data Pagu TA 2026 yang belum dipakai.
        $this->assertSame([['sumber' => 'DIPA', 'pagu' => 4_500_000]], $data['sumber_dana']);
        $this->assertSame('9b2f1c3e-0004-4a51-8c11-000000000004', $data['data_pagu_id']);
        $this->assertSame(['kode', 'kode', 'pagu'], array_column($catatan, 'jenis'));
        $this->assertSame('Kode SK SK.99 tidak ada di master TA 2026 — pilih ulang.', $catatan[0]['teks']);

        // Data Pagu yang sudah jadi kegiatan tidak diambil → pagu lama disalin dengan catatan.
        ['data' => $data, 'catatan' => $catatan] = DuplikatKegiatan::rencana(['nama_kegiatan' => 'Supervisi Akademik dan Refleksi Pembelajaran'] + $sumber);
        $this->assertSame([['sumber' => 'Komite', 'pagu' => 1_000_000]], $data['sumber_dana']);
        $this->assertArrayNotHasKey('data_pagu_id', $data);
        $this->assertSame('Pagu disalin dari TA 2025 — periksa pagu TA 2026.', end($catatan)['teks']);
    }

    public function test_duplikat_menyalin_kegiatan_rab_termin_dan_matrik_menjadi_draf(): void
    {
        $lalu = app(KegiatanRepository::class)->simpan([
            'nama_kegiatan' => 'Rapat Kerja Tahunan', 'leading' => '1.6', 'tahun_anggaran' => 2025, 'status' => 'final', 'tanggal_mulai' => '2025-02-03',
            'kode_rktm' => '1.1.2', 'kode_sk' => 'SK.99', 'bulan_pelaksanaan' => [2], 'sumber_dana' => [['sumber' => 'Komite', 'pagu' => 300_000]],
        ]);
        $rab = app(RabRepository::class);
        $rab->tambahItem($lalu, 'Komite', ['kode' => '521211', 'uraian' => 'Konsumsi rapat', 'kategori' => 'konsumsi', 'volume' => 10, 'satuan' => 'kotak', 'harga_satuan' => 20_000]);
        $rab->tambahItem($lalu, 'Komite', ['kode' => '', 'uraian' => 'ATK', 'kategori' => 'bahan', 'volume' => 1, 'satuan' => 'paket', 'harga_satuan' => 100_000]);
        $rab->simpanTermin($lalu, 'Komite', [['tanggal' => '2025-02-01', 'nominal' => 300_000]]);
        $matrik = new MatrikTahapan(['persiapan' => [['uraian' => 'Rapat panitia', 'bulan' => [1]]], 'pelaksanaan' => [['uraian' => 'Rapat kerja', 'bulan' => [2]]], 'evaluasi_pelaporan' => [['uraian' => 'Laporan', 'bulan' => [3]]]]);
        app(MatrikJadwalRepository::class)->simpan($lalu, $matrik, new JadwalKegiatan([['uraian' => 'Pelaksanaan rapat', 'bulan' => [2]]]));

        Livewire::test(SalinImpor::class)
            ->call('bukaDuplikat')
            ->assertSet('dialogDuplikat', false, 'Tanpa pilihan dialog tidak dibuka.')
            ->set('pilihan', [$lalu])
            ->call('bukaDuplikat')
            ->assertSet('dialogDuplikat', true)
            ->assertSeeHtml('data-testid="dialog-duplikat"')
            ->assertSeeHtml('data-rencana="'.$lalu.'"')
            ->assertSeeHtml('data-catatan="kode"')
            ->call('batalDuplikat')
            ->assertSet('dialogDuplikat', false)
            ->assertDontSeeHtml('data-testid="dialog-duplikat"')
            ->assertSet('pilihan', [$lalu])
            ->call('bukaDuplikat')
            ->call('duplikat')
            ->assertSet('dialogDuplikat', false)
            ->assertSet('pilihan', [])
            ->assertDispatched('notify', message: '1 kegiatan diduplikat menjadi draf TA 2026.', type: 'success')
            ->assertSeeHtml('data-testid="hasil-duplikat"')
            ->assertSeeHtml('data-sumber-duplikat="'.$lalu.'" data-sudah-ada="ya"');

        $baru = collect(app(KegiatanRepository::class)->semua())->first(fn (array $k) => $k['nama_kegiatan'] === 'Rapat Kerja Tahunan' && $k['id'] !== $lalu);
        $this->assertNotNull($baru);
        $this->assertSame(['draf', 2026, '2026-02-03', '1.1.2', null, ['id' => $lalu, 'tahun' => 2025]], [$baru['status'], $baru['tahun_anggaran'], $baru['tanggal_mulai'], $baru['kode_rktm'], $baru['kode_sk'], $baru['duplikat_dari']]);
        $this->assertSame(['dibuat'], array_column($baru['riwayat_status'], 'aksi'));
        $this->assertSame('Diduplikat dari TA 2025.', $baru['riwayat_status'][0]['catatan']);

        $anggaran = AnggaranKegiatan::untuk($baru)[0];
        $this->assertSame(['Komite', 300_000, 2], [$anggaran->sumber, $anggaran->total(), $anggaran->jumlahItem()]);
        $this->assertSame([['tanggal' => '2026-02-01', 'nominal' => 300_000]], $rab->termin($baru['id'], 'Komite'));
        $this->assertSame(['Konsumsi rapat', 'ATK'], array_column($rab->rincian($baru['id'], 'Komite'), 'uraian'));
        $this->assertEquals($matrik, app(MatrikJadwalRepository::class)->matrik($baru['id']));
        $this->assertSame([['uraian' => 'Pelaksanaan rapat', 'bulan' => [2]]], app(MatrikJadwalRepository::class)->jadwal($baru['id'])->baris);
        // Sumber tidak berubah.
        $this->assertSame('final', app(KegiatanRepository::class)->temukan($lalu)['status']);
        $this->assertCount(2, $rab->rincian($lalu, 'Komite'));
    }

    public function test_tab_impor_perkin(): void
    {
        Livewire::withQueryParams(['tab' => 'perkin'])->test(SalinImpor::class)
            ->assertSeeHtml('data-testid="panel-perkin"')
            ->assertSeeHtml('data-testid="kolom-perkin"')
            ->assertSeeInOrder(['Kode SK', 'Kode IKSK', 'Nama Kegiatan', 'Leading', 'Pagu DIPA / Pagu Komite', 'Bulan Pelaksanaan'])
            ->assertSee('Aman diimpor ulang');
    }
}
