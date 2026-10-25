<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Livewire\Kegiatan\Form;
use App\Support\Kegiatan\KonteksAi;
use Livewire\Livewire;
use Tests\TestCase;

class KonteksKegiatanTest extends TestCase
{
    public function test_aspek_catatan_terdeteksi_dari_kata_penanda(): void
    {
        $this->assertSame(['latar' => false, 'peserta' => false, 'narasumber' => false, 'output' => false], KonteksAi::aspekTerisi(''));
        $this->assertSame(
            ['latar' => true, 'peserta' => true, 'narasumber' => true, 'output' => true],
            KonteksAi::aspekTerisi('Lanjutan workshop tahun lalu. Peserta 49 guru; Narasumber pengawas madrasah; output dokumen KTSP.'),
        );
        $this->assertSame(['latar' => false, 'peserta' => false, 'narasumber' => true, 'output' => false], KonteksAi::aspekTerisi('Pemateri dari Kemenag'));
    }

    public function test_nip_diperingatkan_dan_disembunyikan(): void
    {
        $this->assertNull(KonteksAi::peringatanPrivasi('Peserta 54 guru, anggaran 10000000.'));
        $this->assertNull(KonteksAi::peringatanPrivasi('Nomor 1972100820071010021'), '19 digit bukan NIP (sama dengan memuatNip di klien).');
        $this->assertNotNull(KonteksAi::peringatanPrivasi('Narasumber 197210082007101002'));
        $this->assertNotNull(KonteksAi::peringatanPrivasi('Narasumber: Bapak X, NIP. 19721008 200710 1 002'));

        $this->assertSame('Narasumber Bapak X, [NIP disembunyikan] dan rekan [NIP disembunyikan].',
            KonteksAi::redaksi('Narasumber Bapak X, NIP. 197210082007101002 dan rekan 19721008 200710 1 002.'));
    }

    public function test_bahan_ai_dari_isian_lain_tanpa_pejabat(): void
    {
        $kegiatan = app(KegiatanRepository::class)->temukan(2);
        $bahan = array_column(KonteksAi::dariIsian($kegiatan), 'nilai', 'label');

        $this->assertSame('Supervisi Akademik dan Refleksi Pembelajaran', $bahan['Nama kegiatan']);
        $this->assertSame('Tim Penjamin Mutu', $bahan['Leading']);
        $this->assertStringStartsWith('6.19.1 ', $bahan['Program RKTM']);
        $this->assertSame(['Agustus – September 2026', '5 hari', 'Ruang Kelas dan Ruang Rapat Madrasah', '54 orang Guru', 'DIPA Rp6.000.000 · Komite Rp2.500.000'],
            [$bahan['Waktu'], $bahan['Lama'], $bahan['Tempat'], $bahan['Peserta'], $bahan['Sumber dana']]);

        $kegiatan['konteks'] = 'Narasumber pengawas, NIP 197210082007101002.';
        $teks = KonteksAi::teks($kegiatan);
        $this->assertStringContainsString("Nama kegiatan: Supervisi Akademik dan Refleksi Pembelajaran\nLeading: Tim Penjamin Mutu", $teks);
        $this->assertStringContainsString("Catatan leading:\nNarasumber pengawas, [NIP disembunyikan].", $teks);
        $this->assertDoesNotMatchRegularExpression('/\d{18}/', $teks);
        $this->assertStringNotContainsString('Kepala MTsN', $teks, 'Pejabat penanda tangan bukan bahan AI.');

        $kosong = array_column(KonteksAi::dariIsian([]), 'nilai');
        $this->assertSame(array_fill(0, 8, null), $kosong);
        $this->assertSame('', KonteksAi::teks([]));
    }

    public function test_bagian_konteks_di_form(): void
    {
        Livewire::test(Form::class, ['kegiatan' => 2])
            ->assertSeeHtml('data-testid="bagian-konteks"')
            ->assertSee('Sudah diketahui AI')
            ->assertSee('Diambil dari isian lain (8/8)')
            ->assertSeeHtml('data-sisip="narasumber"')
            ->set('form.konteks', 'Peserta seluruh guru; narasumber pengawas madrasah.')
            ->assertSeeHtml('data-aspek="peserta" data-terisi="ya"')
            ->assertSeeHtml('data-aspek="narasumber" data-terisi="ya"')
            ->assertSeeHtml('data-aspek="output" data-terisi="tidak"')
            ->assertSeeHtml('data-nip="tidak"')
            ->set('form.konteks', 'Narasumber NIP 197210082007101002')
            ->assertSeeHtml('data-nip="ya"');

        // Kontrol klien: aspek, kata penanda, dan kunci draf lokal per kegiatan dikirim dari server.
        Livewire::test(Form::class, ['kegiatan' => 2])
            ->assertSeeHtml('x-data="catatanKonteks(')
            ->assertSeeHtml('sirabtor:konteks:2')
            ->assertSeeHtml('x-on:kegiatan-tersimpan.window="tersimpan()"')
            ->set('form.konteks', 'Output dokumen supervisi.')
            ->call('saveDraft')
            ->assertDispatched('kegiatan-tersimpan', id: 2);

        // Kegiatan baru akun leading: leading sudah terisi otomatis, isian lain belum.
        Livewire::test(Form::class)->assertSee('Diambil dari isian lain (1/8)')->assertSee('belum diisi')->assertSeeHtml('sirabtor:konteks:baru');
    }
}
