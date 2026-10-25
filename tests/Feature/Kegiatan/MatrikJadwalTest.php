<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Livewire\Kegiatan\MatrikJadwal;
use App\Livewire\Kegiatan\Rab;
use App\Support\Kegiatan\JadwalKegiatan;
use App\Support\Kegiatan\MatrikTahapan;
use Livewire\Livewire;
use Tests\TestCase;

class MatrikJadwalTest extends TestCase
{
    public function test_matrik_baku_tiga_tahapan_dan_tanda_bulan_dirapikan(): void
    {
        $baku = MatrikTahapan::dari([]);
        $this->assertSame(['persiapan', 'pelaksanaan', 'evaluasi_pelaporan'], array_keys($baku->butir));
        $this->assertSame(['Rapat dan pembagian job deskripsi', 'Penyerahan anggaran per seksi'], array_column($baku->butir['persiapan'], 'uraian'));
        $this->assertSame([0, false], [$baku->jumlahTanda(), $baku->lengkap()]);

        $matrik = MatrikTahapan::dari(['matrik' => [
            'persiapan' => [['uraian' => ' Rapat panitia ', 'bulan' => [6, '6', 13, 0, 5]]],
            'pelaksanaan' => [['uraian' => 'Workshop', 'bulan' => [7]], ['uraian' => 'Pendampingan', 'bulan' => [7, 8]]],
        ]]);
        $this->assertSame([['uraian' => 'Rapat panitia', 'bulan' => [5, 6]]], $matrik->butir['persiapan']);
        $this->assertSame([7, 8], $matrik->bulanTahapan('pelaksanaan'));
        $this->assertSame(['Penyusunan laporan', 'Penyerahan laporan'], array_column($matrik->butir['evaluasi_pelaporan'], 'uraian'), 'Tahapan yang belum disimpan memakai butir baku.');
        $this->assertSame([5, false], [$matrik->jumlahTanda(), $matrik->lengkap()]);

        $this->assertTrue(MatrikTahapan::dari(['matrik' => [
            'persiapan' => [['uraian' => 'a', 'bulan' => [6]]],
            'pelaksanaan' => [['uraian' => 'b', 'bulan' => [7]]],
            'evaluasi_pelaporan' => [['uraian' => 'c', 'bulan' => []], ['uraian' => 'd', 'bulan' => [8]]],
        ]])->lengkap());
    }

    public function test_jadwal_enam_baris_baku_dan_waktu_mengikuti_tahun_aktif(): void
    {
        $baku = JadwalKegiatan::dari([]);
        $this->assertCount(JadwalKegiatan::JUMLAH_BAKU, $baku->baris);
        $this->assertSame([0, false, null], [$baku->jumlahTerisi(), $baku->lengkap(), $baku->waktu(0, 2026)]);

        $jadwal = JadwalKegiatan::dari(['jadwal' => [
            ['uraian' => 'Pembentukan panitia', 'bulan' => [6]],
            ['uraian' => 'Pelaksanaan workshop', 'bulan' => [8, 7]],
            ['uraian' => 'Penyusunan laporan', 'bulan' => []],
        ]]);
        $this->assertSame(['Juni 2026', 'Juli – Agustus 2026', null], [$jadwal->waktu(0, 2026), $jadwal->waktu(1, 2026), $jadwal->waktu(2, 2026)]);
        $this->assertSame([2, false], [$jadwal->jumlahTerisi(), $jadwal->lengkap()]);
        $this->assertTrue(JadwalKegiatan::dari(['jadwal' => [['uraian' => 'Rapat', 'bulan' => [1]]]])->lengkap());
    }

    public function test_halaman_matrik_jadwal_dengan_acuan_bulan_pelaksanaan(): void
    {
        $this->get(route('kegiatan.matrik', ['kegiatan' => 1]))->assertOk()->assertSeeLivewire(MatrikJadwal::class);

        $komponen = Livewire::test(MatrikJadwal::class, ['kegiatan' => 1])
            ->assertSee('Matrik &amp; Jadwal Kegiatan', false)
            ->assertSeeHtml('aria-current="page"')
            ->assertSeeInOrder(['Bulan pelaksanaan', 'Juli 2026', 'Tanggal', '14 Juli 2026', 'Batas laporan', '30 Juli 2026'])
            ->assertSeeInOrder(['Matrik tahapan', '1. Persiapan', 'Rapat dan pembagian job deskripsi', '2. Pelaksanaan', '3. Evaluasi dan Pelaporan', 'Penyerahan laporan'])
            ->assertSeeInOrder(['Jadwal kegiatan', '0 dari 6 baris terisi', 'Belum diisi'])
            ->assertSeeHtml('data-testid="baris-jadwal-6"')
            ->assertSeeHtml('data-testid="kelengkapan-matrik" data-lengkap="tidak"')
            ->assertSeeHtml('href="'.route('kegiatan.edit', ['kegiatan' => 1]).'#pelaksanaan"');

        // Kolom bulan pelaksanaan (Juli) ditandai sebagai acuan.
        $this->assertMatchesRegularExpression('/data-bulan="7"\s+data-pelaksanaan\s+class="[^"]*bg-brand-50/', $komponen->html());
        $this->assertDoesNotMatchRegularExpression('/data-bulan="6"[^>]*data-pelaksanaan/', $komponen->html());

        $this->get(route('kegiatan.matrik', ['kegiatan' => 999]))->assertNotFound();
    }

    public function test_sel_grid_diatur_per_baris_dan_dirapikan(): void
    {
        $komponen = Livewire::test(MatrikJadwal::class, ['kegiatan' => 1])
            ->assertSeeHtml('x-data="gridMatrik({ dapatDiedit: true })"')
            ->assertSeeHtml('aria-label="Persiapan: Rapat dan pembagian job deskripsi — Juli"')
            ->call('aturBulan', 'persiapan', 0, [7, '6', 6, 13])
            ->assertSet('matrik.persiapan.0.bulan', [6, 7])
            ->assertSet('adaPerubahan', true)
            ->assertSee('2 tanda bulan')
            ->assertSeeHtml('data-testid="belum-disimpan"')
            // Butir/tahapan tak dikenal diabaikan; nilai dari klien juga dirapikan lewat updatedMatrik.
            ->call('aturBulan', 'tahapan-lain', 0, [1])
            ->call('aturBulan', 'persiapan', 9, [1])
            ->set('matrik.pelaksanaan.1.bulan', [9, 3, 3])
            ->assertSet('matrik.pelaksanaan.1.bulan', [3, 9]);

        $this->assertMatchesRegularExpression('/data-bulan="7" data-ditandai="ya"/', $komponen->html());
        $this->assertSame(4, $komponen->instance()->susunanMatrik->jumlahTanda());
    }

    public function test_isi_dari_jadwal_pelaksanaan_kosongkan_dan_urungkan(): void
    {
        // Workshop: Juli 2026, laporan 30 Juli → Persiapan Juni, Pelaksanaan Juli, Evaluasi & Pelaporan Juli.
        Livewire::test(MatrikJadwal::class, ['kegiatan' => 1])
            ->call('isiDariPelaksanaan')
            ->assertSet('matrik.persiapan.0.bulan', [6])
            ->assertSet('matrik.persiapan.1.bulan', [6])
            ->assertSet('matrik.pelaksanaan.0.bulan', [7])
            ->assertSet('matrik.evaluasi_pelaporan.1.bulan', [7])
            ->assertSee('Matrik diisi dari jadwal pelaksanaan.')
            ->assertSeeHtml('data-testid="kelengkapan-matrik" data-lengkap="ya"')
            ->call('kosongkanMatrik')
            ->assertSet('matrik.pelaksanaan.0.bulan', [])
            ->assertSee('Semua tanda bulan dikosongkan.')
            ->call('urungkanMatrik')
            ->assertSet('matrik.pelaksanaan.0.bulan', [7])
            ->assertSet('matrikSebelum', null);

        // Supervisi: Agustus–September, laporan 14 September.
        Livewire::test(MatrikJadwal::class, ['kegiatan' => 2])
            ->call('isiDariPelaksanaan')
            ->assertSet('matrik.persiapan.0.bulan', [7])
            ->assertSet('matrik.pelaksanaan.1.bulan', [8, 9])
            ->assertSet('matrik.evaluasi_pelaporan.0.bulan', [9])
            ->call('aturBulan', 'persiapan', 0, [6, 7])
            ->assertSet('matrikSebelum', null);

        $tanpaBulan = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Tanpa jadwal']);
        Livewire::test(MatrikJadwal::class, ['kegiatan' => $tanpaBulan])
            ->assertSeeHtml('wire:click="isiDariPelaksanaan" disabled')
            ->call('isiDariPelaksanaan')
            ->assertDispatched('notify', message: 'Isi bulan pelaksanaan di Data Kegiatan terlebih dahulu.')
            ->assertSet('adaPerubahan', false);
    }

    public function test_simpan_dan_batalkan_matrik(): void
    {
        $repo = app(KegiatanRepository::class);
        $namaSemula = $repo->temukan(1)['nama_kegiatan'];

        Livewire::test(MatrikJadwal::class, ['kegiatan' => 1])
            ->call('aturBulan', 'pelaksanaan', 0, [7])
            ->call('batalkanPerubahan')
            ->assertSet('matrik.pelaksanaan.0.bulan', [])
            ->assertSet('adaPerubahan', false)
            ->call('isiDariPelaksanaan')
            ->call('simpan')
            ->assertHasNoErrors()
            ->assertDispatched('notify', message: 'Matrik tahapan tersimpan.')
            ->assertSet('adaPerubahan', false)
            ->assertDontSeeHtml('data-testid="matrik-berubah"');

        $tersimpan = $repo->temukan(1);
        $this->assertSame([6], $tersimpan['matrik']['persiapan'][0]['bulan']);
        $this->assertSame($namaSemula, $tersimpan['nama_kegiatan'], 'Isian kegiatan lain tidak tersentuh.');
        Livewire::test(MatrikJadwal::class, ['kegiatan' => 1])->assertSet('matrik.evaluasi_pelaporan.1.bulan', [7])->assertSee('6 tanda bulan');

        // Uraian butir wajib diisi.
        Livewire::test(MatrikJadwal::class, ['kegiatan' => 1])
            ->set('matrik.persiapan.0.uraian', '')
            ->call('simpan')
            ->assertHasErrors(['matrik.persiapan.0.uraian' => 'required']);
    }

    public function test_matrik_terkunci_di_luar_draf(): void
    {
        $id = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Sudah final', 'status' => 'final', 'bulan_pelaksanaan' => [5]]);

        Livewire::test(MatrikJadwal::class, ['kegiatan' => $id])
            ->assertSeeHtml('data-testid="banner-terkunci"')
            ->assertSeeHtml('x-data="gridMatrik({ dapatDiedit: false })"')
            ->assertDontSee('Isi dari jadwal pelaksanaan')
            ->assertDontSeeHtml('wire:click="simpan"')
            ->call('aturBulan', 'persiapan', 0, [5])
            ->assertForbidden();
    }

    public function test_tab_matrik_jadwal_aktif_di_ruang_kerja_kegiatan(): void
    {
        Livewire::test(Rab::class, ['kegiatan' => 1])
            ->assertSeeHtml('href="'.route('kegiatan.matrik', ['kegiatan' => 1]).'"');
    }
}
