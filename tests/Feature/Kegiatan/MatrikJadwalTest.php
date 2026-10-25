<?php

namespace Tests\Feature\Kegiatan;

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
        $this->assertMatchesRegularExpression('/data-bulan="7"\s+class="[^"]*bg-brand-50/', $komponen->html());
        $this->assertDoesNotMatchRegularExpression('/data-bulan="6"\s+class="[^"]*bg-brand-50/', $komponen->html());

        $this->get(route('kegiatan.matrik', ['kegiatan' => 999]))->assertNotFound();
    }

    public function test_tab_matrik_jadwal_aktif_di_ruang_kerja_kegiatan(): void
    {
        Livewire::test(Rab::class, ['kegiatan' => 1])
            ->assertSeeHtml('href="'.route('kegiatan.matrik', ['kegiatan' => 1]).'"');
    }
}
