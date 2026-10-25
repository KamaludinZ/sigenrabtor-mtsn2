<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Livewire\Kegiatan\Form;
use Livewire\Livewire;
use Tests\TestCase;

class PemilihanSumberDanaTest extends TestCase
{
    public function test_ringkasan_pilihan_mengikuti_centang(): void
    {
        Livewire::test(Form::class)
            ->assertSeeHtml('data-testid="ringkasan-pilihan-sumber" data-jumlah="0"')
            ->assertSee('Belum ada sumber dana dipilih')
            ->assertSeeHtml('data-testid="sumber-dipa" data-dipilih="tidak"')
            ->assertSee('Centang DIPA bila kegiatan ini juga dibiayai DIPA.')
            ->set('form.dana_dipa', true)
            ->assertSee('Dibiayai DIPA')
            ->assertSee('Akan dibuat 1 dokumen KAK/TOR & RAB DIPA.')
            ->assertSeeHtml('data-testid="sumber-dipa" data-dipilih="ya"')
            ->set('form.dana_komite', true)
            ->assertSeeHtml('data-jumlah="2"')
            ->assertSee('Dibiayai DIPA dan Komite')
            ->assertSee('Akan dibuat 2 dokumen KAK/TOR & RAB terpisah');
    }

    public function test_kartu_memuat_ringkasan_dan_tautan_rab(): void
    {
        Livewire::test(Form::class, ['kegiatan' => 2])
            ->assertSeeInOrder(['RAB DIPA', 'RAB tersusun (6 item)', 'Rp5.400.000', 'Lima Juta Empat Ratus Ribu Rupiah', 'Sisa pagu Rp600.000'])
            ->assertSeeHtml('href="'.route('kegiatan.rab', ['kegiatan' => 2, 'sumber' => 'DIPA']).'"')
            ->assertSee('Buka RAB Komite →');

        $id = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Belum ada RAB', 'sumber_dana' => [['sumber' => 'Komite', 'pagu' => 1_000_000]]]);
        Livewire::test(Form::class, ['kegiatan' => $id])
            ->assertSee('RAB belum berisi rincian')
            ->assertSee('Susun RAB Komite →')
            ->assertDontSeeHtml('data-testid="rincian-rab-komite"');
    }

    public function test_melepas_sumber_dengan_rab_berisi_diberi_peringatan(): void
    {
        Livewire::test(Form::class, ['kegiatan' => 2])
            ->assertDontSeeHtml('data-testid="rab-terlepas-dipa"')
            ->set('form.dana_dipa', false)
            ->assertSeeHtml('data-testid="rab-terlepas-dipa"')
            ->assertSee('RAB DIPA tersimpan (6 item, Rp5.400.000) tetapi tidak ikut dicetak selama DIPA tidak dipilih.')
            ->assertDontSeeHtml('data-testid="rekap-rab-dipa"')
            ->set('form.dana_dipa', true)
            ->assertDontSeeHtml('data-testid="rab-terlepas-dipa"')
            ->assertSeeHtml('data-testid="rekap-rab-dipa"');

        // Kegiatan baru (belum tersimpan) tidak punya RAB untuk dilepas.
        Livewire::test(Form::class)->set('form.dana_komite', true)->set('form.dana_komite', false)->assertDontSee('tidak ikut dicetak');
    }

    public function test_pagu_yang_dilepas_dipakai_lagi_saat_dicentang_ulang(): void
    {
        $komponen = Livewire::test(Form::class, ['kegiatan' => 2])
            ->set('form.dana_dipa', false)
            ->assertSet('form.pagu_dipa', '')
            ->assertSet('paguDilepas', ['dipa' => '6.000.000'])
            ->assertSee('Pagu sebelumnya Rp6.000.000 dipakai lagi bila DIPA dicentang.');
        $this->assertSame([['sumber' => 'Komite', 'pagu' => 2_500_000]], $komponen->get('form')->sumberDana());

        $komponen->set('form.dana_dipa', true)
            ->assertSet('form.pagu_dipa', '6.000.000')
            ->assertSet('paguDilepas', [])
            ->assertDispatched('notify', message: 'Pagu DIPA Rp6.000.000 dipakai lagi.')
            ->assertHasNoErrors()
            ->assertDontSeeHtml('data-testid="pagu-dilepas-dipa"');
        $this->assertSame(8_500_000, $komponen->get('form')->totalPagu());

        // Tersimpan tanpa DIPA, lalu dicentang lagi: pagu yang diingat tetap dipakai.
        $komponen->set('form.dana_dipa', false)->call('saveDraft');
        $this->assertSame([['sumber' => 'Komite', 'pagu' => 2_500_000]], app(KegiatanRepository::class)->temukan(2)['sumber_dana']);
        $komponen->set('form.dana_dipa', true)->assertSet('form.pagu_dipa', '6.000.000');
    }

    public function test_sumber_tanpa_pagu_dilepas_tanpa_ingatan_dan_galatnya_hilang(): void
    {
        Livewire::test(Form::class)
            ->set('form.dana_komite', true)
            ->set('form.pagu_komite', '0')
            ->assertHasErrors('form.pagu_komite')
            ->set('form.dana_komite', false)
            ->assertHasNoErrors('form.pagu_komite')
            ->assertSet('paguDilepas', ['komite' => '0'])
            ->set('form.dana_dipa', true)
            ->set('form.dana_dipa', false)
            ->assertSet('paguDilepas', ['komite' => '0'])
            ->set('form.dana_dipa', true)
            ->assertNotDispatched('notify')
            ->assertSet('form.pagu_dipa', '');
    }

    public function test_pilih_lagi_dari_peringatan_rab_dilepas(): void
    {
        Livewire::test(Form::class, ['kegiatan' => 2])
            ->set('form.dana_dipa', false)
            ->assertSeeHtml('wire:click="pilihSumberLagi(\'dipa\')"')
            ->assertSee('Pilih DIPA lagi')
            ->call('pilihSumberLagi', 'dipa')
            ->assertSet('form.dana_dipa', true)
            ->assertSet('form.pagu_dipa', '6.000.000')
            ->assertSet('adaPerubahan', true)
            ->assertDontSeeHtml('data-testid="rab-terlepas-dipa"')
            ->assertSeeHtml('data-testid="rekap-rab-dipa"')
            // Kunci tak dikenal atau sumber yang sudah dipilih: tidak terjadi apa-apa.
            ->call('pilihSumberLagi', 'bos')
            ->call('pilihSumberLagi', 'komite')
            ->assertSet('form.pagu_komite', '2.500.000');

        $final = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Terkunci', 'status' => 'final', 'sumber_dana' => [['sumber' => 'Komite', 'pagu' => 1_000_000]]]);
        Livewire::test(Form::class, ['kegiatan' => $final])->call('pilihSumberLagi', 'dipa')->assertForbidden();
    }
}
