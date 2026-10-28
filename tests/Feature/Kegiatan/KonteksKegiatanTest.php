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
        $this->assertSame(['Agustus – September 2026', '5 hari', 'Ruang Kelas dan Ruang Rapat Madrasah', '54 orang Guru', 'DIPA · Komite'],
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

    public function test_contoh_catatan_yang_cocok_didahulukan(): void
    {
        $this->assertSame(['workshop', 'supervisi', 'lomba', 'siswa'], array_column(KonteksAi::contoh(''), 'kunci'));
        $this->assertSame([false, false, false, false], array_column(KonteksAi::contoh(''), 'cocok'));

        $supervisi = KonteksAi::contoh('Supervisi Akademik dan Refleksi Pembelajaran');
        $this->assertSame(['supervisi', true], [$supervisi[0]['kunci'], $supervisi[0]['cocok']]);
        $this->assertSame('lomba', KonteksAi::contoh('Lomba Kebersihan Kelas')[0]['kunci']);

        foreach (KonteksAi::contoh('') as $contoh) {
            // Contoh panduan memuat keempat aspek dan tidak memuat NIP.
            $this->assertSame(['latar' => true, 'peserta' => true, 'narasumber' => true, 'output' => true], KonteksAi::aspekTerisi($contoh['teks']), $contoh['kunci']);
            $this->assertNull(KonteksAi::peringatanPrivasi($contoh['teks']));
        }
    }

    public function test_mengosongkan_catatan_memberi_tahu_dan_bisa_diurungkan(): void
    {
        $asli = app(KegiatanRepository::class)->temukan(1)['konteks'];
        $this->assertNotSame('', $asli);

        Livewire::test(Form::class, ['kegiatan' => 1])
            ->assertDontSeeHtml('data-testid="konteks-dikosongkan"')
            ->set('form.konteks', "  \n ")
            ->assertSet('konteksDikosongkan', $asli)
            ->assertDispatched('notify', type: 'info')
            ->assertSeeHtml('data-testid="konteks-dikosongkan"')
            ->assertSee('kosong — AI hanya memakai isian di atas')
            ->tap(fn () => $this->assertSame('', app(KegiatanRepository::class)->temukan(1)['konteks'], 'Pengosongan tersimpan otomatis.'))
            ->call('urungkanKosongkanKonteks')
            ->assertSet('form.konteks', $asli)
            ->assertSet('konteksDikosongkan', null)
            ->assertSet('adaPerubahan', true)
            ->tap(fn () => $this->assertSame($asli, app(KegiatanRepository::class)->temukan(1)['konteks'], 'Pemulihan juga tersimpan.'))
            ->assertDontSeeHtml('data-testid="konteks-dikosongkan"')
            // Dikosongkan lalu diisi ulang: tidak ada lagi yang perlu diurungkan.
            ->set('form.konteks', '')
            ->set('form.konteks', 'Catatan baru: peserta seluruh guru.')
            ->assertSet('konteksDikosongkan', null);

        // Catatan yang memang belum pernah diisi: tidak ada pemberitahuan.
        Livewire::test(Form::class)
            ->set('form.konteks', '')
            ->assertSet('konteksDikosongkan', null)
            ->assertNotDispatched('notify')
            ->call('urungkanKosongkanKonteks')
            ->assertSet('form.konteks', '');
    }

    public function test_konteks_tersimpan_otomatis_tanpa_ikut_menyimpan_isian_lain(): void
    {
        $repo = app(KegiatanRepository::class);
        $nama = $repo->temukan(1)['nama_kegiatan'];

        $komponen = Livewire::test(Form::class, ['kegiatan' => 1])
            ->assertSee('Tersimpan otomatis beberapa saat setelah berhenti mengetik.')
            ->set('form.nama_kegiatan', 'Nama yang belum disimpan')
            ->set('form.konteks', 'Peserta   seluruh guru.  ')
            // Kotak teks tidak dirapikan di tempat (ketikan tidak tertimpa); penyimpanan memakai versi rapi.
            ->assertSet('form.konteks', 'Peserta   seluruh guru.  ')
            // Teks yang dikirim klien ikut dikonfirmasi agar pewaktu simpan otomatis tidak mengirim ulang nilai yang sama.
            ->assertDispatched('konteks-tersimpan', teks: 'Peserta   seluruh guru.  ')
            ->assertSet('adaPerubahan', true);
        $this->assertMatchesRegularExpression('/^\d{2}\.\d{2}$/', $komponen->get('konteksTersimpanPada'));

        $tersimpan = $repo->temukan(1);
        $this->assertSame('Peserta seluruh guru.', $tersimpan['konteks']);
        $this->assertSame($nama, $tersimpan['nama_kegiatan'], 'Isian lain yang belum disimpan tidak ikut tersimpan.');
    }

    public function test_simpan_otomatis_hanya_untuk_kegiatan_tersimpan_dan_isian_sah(): void
    {
        $repo = app(KegiatanRepository::class);
        $jumlah = count($repo->semua());

        Livewire::test(Form::class)
            ->assertSee('Tersimpan di perangkat ini sampai kegiatan disimpan.')
            ->set('form.konteks', 'Catatan kegiatan baru.')
            ->assertNotDispatched('konteks-tersimpan')
            ->assertSet('konteksTersimpanPada', null);
        $this->assertCount($jumlah, $repo->semua(), 'Kegiatan baru tidak dibuat lewat simpan otomatis.');

        $asli = $repo->temukan(1)['konteks'];
        Livewire::test(Form::class, ['kegiatan' => 1])
            ->set('form.konteks', str_repeat('a', 3001))
            ->assertHasErrors('form.konteks')
            ->assertNotDispatched('konteks-tersimpan');
        $this->assertSame($asli, $repo->temukan(1)['konteks']);

        // Simpan Draf merapikan spasi konteks.
        Livewire::test(Form::class, ['kegiatan' => 1])->set('form.konteks', "Output   dokumen\n\n\n\nKTSP")->call('saveDraft');
        $this->assertSame("Output dokumen\n\nKTSP", $repo->temukan(1)['konteks']);
    }

    public function test_bagian_konteks_di_form(): void
    {
        Livewire::test(Form::class, ['kegiatan' => 2])
            ->assertSeeHtml('data-testid="bagian-konteks"')
            ->assertSee('Sudah diketahui AI')
            ->assertSee('Diambil dari isian lain (8/8)')
            ->assertSeeHtml('data-sisip="narasumber"')
            ->assertSeeHtml('data-testid="contoh-konteks"')
            ->assertSee('Cocok dengan nama kegiatan')
            ->assertSeeInOrder(['data-contoh="supervisi"', 'data-contoh="workshop"'], false)
            ->assertSee(KonteksAi::CONTOH_ASPEK['narasumber'])
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
