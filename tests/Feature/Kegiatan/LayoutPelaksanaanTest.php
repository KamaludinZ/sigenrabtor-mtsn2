<?php

namespace Tests\Feature\Kegiatan;

use App\Livewire\Kegiatan\Form;
use App\Support\Kegiatan\KalimatKak;
use Carbon\CarbonImmutable;
use Livewire\Livewire;
use Tests\TestCase;

class LayoutPelaksanaanTest extends TestCase
{
    public function test_kalimat_kak_pelaksanaan_pelaporan_dan_tempat(): void
    {
        $mulai = CarbonImmutable::parse('2026-07-14');
        $selesai = CarbonImmutable::parse('2026-07-16');

        $this->assertSame(
            'Kegiatan Workshop Kurikulum akan dilaksanakan selama 3 hari, 14 – 16 Juli 2026, diikuti oleh 56 orang peserta (Guru dan tenaga kependidikan). Workshop tatap muka dengan narasumber.',
            KalimatKak::pelaksanaan('Kegiatan Workshop Kurikulum', 3, $mulai, $selesai, 56, 'Guru dan tenaga kependidikan', ' workshop tatap muka dengan narasumber. '),
        );
        $this->assertSame(
            'Kegiatan Rapat akan dilaksanakan selama 1 hari, 14 Juli 2026.',
            KalimatKak::pelaksanaan('Rapat', 1, $mulai, $mulai),
        );
        $this->assertSame('Kegiatan Rapat akan dilaksanakan selama 1 hari, 14 Juli 2026, diikuti oleh siswa kelas 9.', KalimatKak::pelaksanaan('Rapat', 1, $mulai, $mulai, null, 'siswa kelas 9'));
        $this->assertSame('Kegiatan Workshop Kurikulum bertempat di Aula Madrasah.', KalimatKak::tempat('kegiatan Workshop Kurikulum', ' Aula Madrasah. '));
        $this->assertSame(
            'Laporan pelaksanaan kegiatan Rapat disampaikan kepada kepala madrasah selambat-lambatnya tanggal 26 Juli 2026.',
            KalimatKak::pelaporan('Rapat', CarbonImmutable::parse('2026-07-26')),
        );
    }

    public function test_tiga_kelompok_dengan_status_kelengkapan(): void
    {
        Livewire::test(Form::class)
            ->assertSeeInOrder(['Waktu pelaksanaan', 'Tempat &amp; peserta', 'Bentuk pelaksanaan', 'Tercetak di KAK'], false)
            ->assertSeeHtml('data-kelengkapan="kosong"')
            ->assertSeeHtml('data-kelengkapan="opsional"')
            ->set('form.tanggal_mulai', '2026-07-14')
            ->set('form.lama_hari', '2')
            ->set('form.tempat', 'Aula Madrasah')
            ->assertSet('form.bulan_pelaksanaan', [7])
            ->tap(fn ($komponen) => $this->assertSame(
                ['waktu' => 'lengkap', 'tempat' => 'sebagian', 'bentuk' => 'opsional'],
                $komponen->instance()->form->kelengkapanPelaksanaan(),
            ));
    }

    public function test_pratinjau_kak_mengikuti_isian_dan_menandai_yang_kurang(): void
    {
        Livewire::test(Form::class)
            ->set('form.nama_kegiatan', 'Workshop Kurikulum')
            ->assertSee('Lengkapi tanggal mulai &amp; lama kegiatan agar bagian ini tercetak.', false)
            ->assertSee('Lengkapi tempat pelaksanaan agar bagian ini tercetak.')
            ->set('form.tanggal_mulai', '2026-07-14')
            ->set('form.lama_hari', '1')
            ->set('form.peserta_jumlah', '56')
            ->set('form.peserta_jenis', 'Guru')
            ->set('form.tempat', 'Aula Madrasah')
            ->assertSee('Kegiatan Workshop Kurikulum akan dilaksanakan selama 1 hari, 14 Juli 2026, diikuti oleh 56 orang peserta (Guru).')
            ->assertSee('Kegiatan Workshop Kurikulum bertempat di Aula Madrasah.')
            ->assertSee('selambat-lambatnya tanggal 24 Juli 2026.')
            ->assertDontSee('agar bagian ini tercetak');
    }

    public function test_penghitung_uraian_tidak_bergantung_urutan_init(): void
    {
        Livewire::test(Form::class, ['kegiatan' => 1])
            ->assertSeeHtml('this.$nextTick(() => this.n = this.$refs.ta.value.length)')
            ->assertSeeHtml("this.\$wire.\$get('form.uraian_pelaksanaan')");
    }
}
