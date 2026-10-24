<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Livewire\Kegiatan\Form;
use App\Support\Format;
use App\Support\Stub\StubMaster;
use Livewire\Livewire;
use Tests\TestCase;

class RefinementTransisiRabTest extends TestCase
{
    public function test_format_rupiah_indonesia(): void
    {
        $this->assertSame('Rp10.000.000', Format::rupiah(10_000_000));
        $this->assertSame('1.250.000', Format::angka(1_250_000));
        $this->assertSame(10_000_000, Format::parseRupiah('Rp 10.000.000,-'));
        $this->assertNull(Format::parseRupiah('abc'));
    }

    public function test_master_kode_dibaca_per_tahun_anggaran(): void
    {
        $this->assertTrue(StubMaster::tersedia(2026));
        $this->assertFalse(StubMaster::tersedia(2027), 'Tahun tanpa unggahan master harus kosong.');
        $this->assertNotEmpty(StubMaster::kodeIksk('SK.10'));
        $this->assertSame(['SK.10'], array_values(array_unique(array_column(StubMaster::kodeIksk('SK.10'), 'kode_sk'))));
    }

    public function test_leading_baru_otomatis_terisi_untuk_akun_satu_leading(): void
    {
        Livewire::test(Form::class)->assertSet('form.leading', '1.6');

        session(['stub_role' => 'admin']);
        Livewire::test(Form::class)->assertSet('form.leading', '');
    }

    public function test_akun_leading_tidak_bisa_memilih_leading_lain(): void
    {
        Livewire::test(Form::class)
            ->set('form.leading', '1.1')
            ->assertHasErrors('form.leading');
    }

    public function test_kode_harus_dari_master_dan_iksk_mengikuti_sk(): void
    {
        Livewire::test(Form::class)
            ->set('form.kode_rktm', '9.9.99')
            ->assertHasErrors('form.kode_rktm')
            ->set('form.kode_sk', 'SK.10')
            ->set('form.kode_iksk', 'IKSK 10.6')
            ->assertHasNoErrors('form.kode_iksk')
            ->set('form.kode_sk', 'SK.9')
            ->assertSet('form.kode_iksk', '')
            ->set('form.kode_iksk', 'IKSK 10.6')
            ->assertHasErrors('form.kode_iksk');
    }

    public function test_sub_kegiatan_rktm_bisa_dipakai_sebagai_nama(): void
    {
        Livewire::test(Form::class)
            ->set('form.kode_rktm', '1.1.7')
            ->assertSee('Pakai sub kegiatan RKTM sebagai nama kegiatan')
            ->call('pakaiNamaDariRktm')
            ->assertSet('form.nama_kegiatan', 'Workshop KTSP')
            ->assertSet('form.nama_kegiatan_cover', 'WORKSHOP KTSP');
    }

    public function test_awalan_kegiatan_dari_rktm_dibuang_agar_tidak_tercetak_dobel(): void
    {
        Livewire::test(Form::class)
            ->set('form.kode_rktm', '6.17.12')
            ->call('pakaiNamaDariRktm')
            ->assertSet('form.nama_kegiatan', 'Workshop penyusunan Kurikulum Madrasah dan Perangkat Pembelajaran')
            ->assertDontSee('awalan “Kegiatan” akan tercetak dobel')
            ->assertDontSee('Pakai sub kegiatan RKTM sebagai nama kegiatan');
    }

    public function test_kartu_pelaksana_menampilkan_jabatan_leading_terpilih(): void
    {
        Livewire::test(Form::class)
            ->assertSee('Ketua Tim Penjamin Mutu')
            ->assertSee('Leading 1.6 · Tim Penjamin Mutu');
    }

    public function test_pagu_dirapikan_dan_wajib_bila_sumber_dipilih(): void
    {
        Livewire::test(Form::class)
            ->set('form.dana_dipa', true)
            ->set('form.pagu_dipa', 'Rp 12500000')
            ->assertSet('form.pagu_dipa', '12.500.000')
            ->assertSee('Rp12.500.000')
            ->set('form.dana_komite', true)
            ->set('form.pagu_komite', '')
            ->assertHasErrors('form.pagu_komite')
            ->set('form.dana_komite', false)
            ->assertHasNoErrors('form.pagu_komite')
            ->assertSet('form.pagu_komite', '');
    }

    public function test_kelengkapan_per_bagian(): void
    {
        $komponen = Livewire::test(Form::class, ['kegiatan' => 1]);
        $kelengkapan = $komponen->instance()->kelengkapan;

        $this->assertSame('lengkap', $kelengkapan['identitas']);
        $this->assertSame('lengkap', $kelengkapan['kode']);
        $this->assertSame('lengkap', $kelengkapan['pelaksanaan']);
        $this->assertSame('lengkap', $kelengkapan['panitia']);
        $this->assertSame('lengkap', $kelengkapan['sumber-dana']);
        $komponen->assertSee('5/5 lengkap');

        $baru = Livewire::test(Form::class)->instance()->kelengkapan;
        $this->assertSame('kosong', $baru['identitas']);
        $this->assertSame('sebagian', $baru['kode'], 'Leading sudah terisi otomatis.');
        $this->assertSame('opsional', $baru['konteks']);
    }

    public function test_data_baru_tersimpan_dan_dimuat_kembali(): void
    {
        Livewire::test(Form::class)
            ->set('form.nama_kegiatan', 'Pengadaan Buku Kurikulum')
            ->set('form.kode_rktm', '1.1.10')
            ->set('form.tempat', 'Perpustakaan')
            ->set('form.peserta_jumlah', 300)
            ->set('form.konteks', 'Buku untuk kelas VII, VIII, dan IX.')
            ->set('form.dana_dipa', true)
            ->set('form.pagu_dipa', '29.250.000')
            ->call('saveDraft');

        $tersimpan = app(KegiatanRepository::class)->temukan(2);
        $this->assertSame([['sumber' => 'DIPA', 'pagu' => 29_250_000]], $tersimpan['sumber_dana']);
        $this->assertSame(300, $tersimpan['peserta_jumlah']);

        Livewire::test(Form::class, ['kegiatan' => 2])
            ->assertSet('form.dana_dipa', true)
            ->assertSet('form.pagu_dipa', '29.250.000')
            ->assertSet('form.kode_rktm', '1.1.10')
            ->assertSet('form.konteks', 'Buku untuk kelas VII, VIII, dan IX.');
    }

    public function test_lanjut_ke_rab_mewajibkan_sumber_dana(): void
    {
        Livewire::test(Form::class)
            ->set('form.nama_kegiatan', 'Kegiatan Tanpa Dana')
            ->call('lanjutKeRab')
            ->assertHasErrors('form.sumber_dana')
            ->assertNoRedirect();

        $this->assertCount(1, app(KegiatanRepository::class)->terbaru());
    }

    public function test_lanjut_ke_rab_menyimpan_kegiatan_baru_lalu_membuka_halamannya(): void
    {
        Livewire::test(Form::class)
            ->set('form.nama_kegiatan', 'Kegiatan Dengan Dana')
            ->set('form.dana_komite', true)
            ->set('form.pagu_komite', '5.000.000')
            ->call('lanjutKeRab')
            ->assertHasNoErrors()
            ->assertRedirect(route('kegiatan.rab', ['kegiatan' => 2]));

        $this->assertSame('Kegiatan Dengan Dana', app(KegiatanRepository::class)->temukan(2)['nama_kegiatan']);
    }

    public function test_kartu_lanjut_rab_menampilkan_sumber_dana_dan_pagu(): void
    {
        $this->get(route('kegiatan.edit', ['kegiatan' => 1]))
            ->assertOk()
            ->assertSee('Susun Rencana Anggaran Biaya (RAB)')
            ->assertSee('RAB Komite')
            ->assertSee('pagu Rp10.000.000')
            ->assertSee('Simpan &amp; lanjut ke RAB', false);
    }
}
