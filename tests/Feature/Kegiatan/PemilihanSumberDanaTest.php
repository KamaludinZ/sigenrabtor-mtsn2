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
            ->assertSeeInOrder(['Pagu DIPA', 'Terhadap total RAB Rp5.400.000', 'Sisa pagu Rp600.000', 'RAB DIPA', 'RAB tersusun (6 item)', 'Rp5.400.000', 'Lima Juta Empat Ratus Ribu Rupiah'])
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

    public function test_input_pagu_bersyarat_dengan_status_batas_pagu(): void
    {
        // Sumber belum dicentang: input nonaktif dengan petunjuk; tanpa RAB berisi tidak ada status batas pagu.
        Livewire::test(Form::class)
            ->assertSeeHtml('data-testid="input-pagu-dipa"')
            ->assertSeeHtml('id="pagu_dipa"')
            ->assertSeeHtml('x-bind:disabled="! $wire.form.dana_dipa"')
            ->assertSeeHtml('x-bind:placeholder="$wire.form.dana_dipa ? \'0\' : \'Centang DIPA dulu\'"')
            ->assertDontSeeHtml('data-testid="status-pagu-form-dipa"');

        // RAB DIPA berisi: status awal dari server, dihitung ulang di klien (data-status-pagu ikut x-bind).
        Livewire::test(Form::class, ['kegiatan' => 3])
            ->assertSeeHtml('data-testid="status-pagu-form-dipa" aria-live="polite"')
            ->assertSeeHtml('data-status-pagu="tanpa_pagu" x-bind:data-status-pagu="status.status"')
            ->assertSeeInOrder(['Pagu DIPA', 'Terhadap total RAB Rp900.000', 'Pagu belum diisi'])
            ->assertSeeInOrder(['Pagu Komite', 'Terhadap total RAB Rp7.950.000', 'Melebihi pagu Rp450.000'])
            ->set('form.pagu_komite', '8.000.000')
            ->assertSeeHtml('data-status-pagu="sisa"')
            ->assertSee('Sisa pagu Rp50.000');
    }

    /** Elemen ber-test-id tampil saat dirender (tanpa display: none awal untuk x-show). */
    private function tampil(string $html, string $testId): bool
    {
        // Atribut bernilai dalam kutip ganda boleh memuat ">" (mis. x-show="n > 1").
        $atribut = '(?:\s+[^\s"\'=<>]+(?:="[^"]*")?)*';
        $this->assertSame(1, preg_match('/<[a-z0-9]+'.$atribut.'\s+data-testid="'.preg_quote($testId, '/').'"'.$atribut.'\s*>/', $html, $tag), "Elemen {$testId} ada.");

        return ! str_contains($tag[0], 'display: none');
    }

    public function test_status_pagu_belum_lengkap_tampil_di_kartu_ringkasan_dan_total(): void
    {
        $komponen = Livewire::test(Form::class)->set('form.dana_dipa', true);
        $this->assertTrue($this->tampil($komponen->html(), 'pagu-kosong-dipa'));
        $this->assertFalse($this->tampil($komponen->html(), 'pagu-kosong-komite'));
        $komponen->assertSee('Pagu DIPA belum diisi — RAB belum bisa dibandingkan dengan pagu.')
            ->assertSeeHtml('x-show="dipilih(\'dipa\') && pagu(\'dipa\') < 1"');

        $komponen->set('form.dana_komite', true)
            ->assertSee('Pagu DIPA dan Komite belum diisi — RAB belum bisa dibandingkan dengan pagu.')
            ->assertSee('2 pagu belum diisi')
            ->set('form.pagu_dipa', '1.000.000')
            ->assertSee('Pagu Komite belum diisi — RAB belum bisa dibandingkan dengan pagu.')
            ->assertSee('1 pagu belum diisi');
        $this->assertFalse($this->tampil($komponen->html(), 'pagu-kosong-dipa'));
        $this->assertTrue($this->tampil($komponen->html(), 'pagu-kosong-komite'));

        $komponen->set('form.pagu_komite', '500.000')
            ->assertSeeInOrder(['Total pagu', 'Rp1.500.000']);
        $this->assertFalse($this->tampil($komponen->html(), 'pagu-belum-lengkap'));
        $this->assertFalse($this->tampil($komponen->html(), 'jumlah-pagu-kosong'));

        // Contoh Review RKJM: pagu DIPA kosong dari data tersimpan.
        $rktm = Livewire::test(Form::class, ['kegiatan' => 3])
            ->assertSee('Pagu DIPA belum diisi — RAB belum bisa dibandingkan dengan pagu.')
            ->assertSeeInOrder(['Total pagu', 'Rp7.500.000', '1 pagu belum diisi']);
        $this->assertTrue($this->tampil($rktm->html(), 'pagu-kosong-dipa'));
    }

    public function test_ringkasan_pagu_di_halaman_kegiatan(): void
    {
        $komponen = Livewire::test(Form::class, ['kegiatan' => 3]);
        $html = $komponen->html();

        // Dua varian (samping desktop & bawah layar sempit) dengan test-id sendiri.
        foreach (['samping', 'bawah'] as $varian) {
            $this->assertStringContainsString('data-testid="ringkasan-pagu-'.$varian.'"', $html);
            $this->assertTrue($this->tampil($html, "ringkasan-pagu-{$varian}-dipa"));
            $this->assertTrue($this->tampil($html, "ringkasan-pagu-{$varian}-total"));
        }
        $komponen->assertSeeHtml('x-data="paguKegiatan({ sumber: ')
            ->assertSeeHtml('href="'.route('kegiatan.rab', ['kegiatan' => 3, 'halaman' => 'pagu']).'"')
            ->assertSeeInOrder(['Ringkasan pagu', 'Pagu &amp; RAB →', 'RAB DIPA', 'Rp900.000', 'belum diisi', 'Belum bisa dibandingkan', 'RAB Komite', 'Rp7.950.000', 'Rp7.500.000', 'Melebihi pagu Rp450.000', 'Total RAB', 'Rp8.850.000', 'Total pagu', 'Rp7.500.000'], false);

        // Total RAB sumber yang dilepas tetap dikirim ke klien agar langsung tampil saat dicentang lagi.
        $komponen->set('form.dana_dipa', false);
        // Keluaran @js meng-escape tanda kutip; cukup cocokkan kunci & angkanya.
        $this->assertMatchesRegularExpression('/DIPA[^:]*:\{[^:]*total[^:]*:900000,[^:]*item[^:]*:2\}/', $komponen->html());
        $this->assertFalse($this->tampil($komponen->html(), 'ringkasan-pagu-samping-dipa'));
        $this->assertFalse($this->tampil($komponen->html(), 'ringkasan-pagu-samping-total'));

        // Kegiatan baru: belum ada sumber, tanpa tautan Pagu & RAB.
        $baru = Livewire::test(Form::class)->assertSee('Belum ada sumber dana dipilih.')->assertDontSee('Pagu &amp; RAB →', false);
        $this->assertFalse($this->tampil($baru->html(), 'ringkasan-pagu-samping-komite'));
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
