<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Livewire\Kegiatan\Form;
use App\Support\Dokumen\SusunanKak;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Tests\TestCase;

class PratinjauKakTest extends TestCase
{
    private function kegiatan(int $id): array
    {
        return app(KegiatanRepository::class)->temukan($id);
    }

    public function test_sembilan_bagian_baku_berurutan_dengan_kalimat_dari_data(): void
    {
        $kak = SusunanKak::dari($this->kegiatan(1));

        $this->assertSame(
            ['Latar Belakang', 'Dasar Hukum', 'Tujuan dan Sasaran', 'Tahapan Kegiatan', 'Tempat Pelaksanaan', 'Penyelenggara Kegiatan', 'Jadwal Kegiatan', 'Pembiayaan', 'Lembar Pengesahan'],
            array_column($kak->bagian, 'judul'),
        );
        $this->assertSame('Komite', $kak->sumber);
        $this->assertSame('KEGIATAN WORKSHOP PENYUSUNAN KURIKULUM MADRASAH DAN PERANGKAT PEMBELAJARAN', mb_strtoupper($kak->cover['kegiatan']));
        $this->assertSame([], $kak->kurang);
        $this->assertTrue($kak->draf, 'Kegiatan berstatus draf selalu bercap DRAF.');

        $teks = $this->teks($kak);
        $this->assertStringContainsString('adalah kegiatan Workshop Penyusunan Kurikulum Madrasah dan Perangkat Pembelajaran.', $teks);
        $this->assertStringContainsString('Kegiatan Workshop Penyusunan Kurikulum Madrasah dan Perangkat Pembelajaran bertujuan menghasilkan kegiatan', $teks);
        $this->assertStringContainsString('Terwujudnya dokumen perencanaan yang handal', $teks);
        $this->assertStringContainsString('akan dilaksanakan selama 1 hari, 14 Juli 2026, diikuti oleh 56 orang peserta (Guru dan tenaga kependidikan).', $teks);
        $this->assertStringContainsString('selambat-lambatnya tanggal 30 Juli 2026.', $teks);
        $this->assertStringContainsString('bertempat di Aula Madrasah.', $teks);
        $this->assertStringContainsString('Pelaksana kegiatan Workshop Penyusunan Kurikulum Madrasah dan Perangkat Pembelajaran adalah Ketua Tim Penjamin Mutu.', $teks);
        $this->assertStringContainsString('sebesar Rp10.000.000,- (Sepuluh Juta Rupiah) yang dibebankan pada dana KOMITE', $teks);
    }

    public function test_dokumen_per_sumber_dana_dan_pengesahan_komite(): void
    {
        $dipa = SusunanKak::dari($this->kegiatan(2), 'DIPA');
        $komite = SusunanKak::dari($this->kegiatan(2), 'Komite');

        $this->assertSame(['DIPA', 'Komite'], $dipa->daftarSumber);
        $this->assertStringContainsString('sebesar Rp5.400.000,- (Lima Juta Empat Ratus Ribu Rupiah) yang dibebankan pada dana DIPA', $this->teks($dipa));
        $this->assertStringContainsString('dana KOMITE', $this->teks($komite));

        $peran = fn (SusunanKak $kak) => array_column($kak->bagian[8]['isi'][0]['kolom'], 'peran');
        $this->assertSame(['Pembuat,', 'Menyetujui,'], $peran($dipa));
        $this->assertSame(['Mengetahui,', 'Pembuat,', 'Menyetujui,'], $peran($komite));
        $this->assertSame('Muslimin, M.Pd.', $komite->bagian[8]['isi'][0]['kolom'][1]['nama']);
    }

    public function test_isian_kosong_ditandai_bukan_diisi_teks_palsu(): void
    {
        $kak = SusunanKak::dari(['nama_kegiatan' => 'Rapat Kerja', 'status' => 'draf']);

        $this->assertSame([
            'tujuan kegiatan', 'sasaran kegiatan', 'tanggal mulai & lama kegiatan', 'tanggal pelaporan', 'tempat pelaksanaan',
            'leading penanggung jawab (Kode & Klasifikasi)', 'sumber dana (bagian Sumber Dana)', 'tanggal dokumen',
        ], $kak->kurang);
        $this->assertNull($kak->sumber);
        $this->assertStringNotContainsString('bertempat di', $this->teks($kak));
    }

    public function test_disetujui_dan_lengkap_tanpa_cap_draf(): void
    {
        $this->assertFalse(SusunanKak::dari(['status' => 'disetujui'] + $this->kegiatan(1))->draf);
        $this->assertTrue(SusunanKak::dari(['status' => 'disetujui', 'tempat' => ''] + $this->kegiatan(1))->draf);
    }

    public function test_komponen_merender_bagian_penanda_kosong_dan_cap_draf(): void
    {
        $html = Blade::render('<x-dokumen.kak-tor :susunan="$s" />', ['s' => SusunanKak::dari(['nama_kegiatan' => 'Rapat Kerja'])]);

        $this->assertStringContainsString('data-testid="pratinjau-kak"', $html);
        $this->assertStringContainsString('1. Latar Belakang', $html);
        $this->assertStringContainsString('9. Lembar Pengesahan', $html);
        $this->assertStringContainsString('Lengkapi tempat pelaksanaan.', $html);
        $this->assertStringContainsString('diisi di tab Matrik &amp; Jadwal', $html);
        $this->assertSame(2, substr_count($html, 'DRAF'));
    }

    public function test_panel_pratinjau_di_data_kegiatan_memakai_isian_belum_disimpan(): void
    {
        Livewire::test(Form::class, ['kegiatan' => 2])
            ->assertDontSeeHtml('data-testid="panel-pratinjau"')
            ->set('form.tempat', 'Laboratorium IPA')
            ->set('pratinjauTerbuka', true)
            ->assertSeeHtml('data-testid="panel-pratinjau"')
            ->assertSee('bertempat di Laboratorium IPA.')
            ->assertSee('KAK DIPA')
            ->assertSee('dana DIPA')
            ->set('pratinjauSumber', 'Komite')
            ->assertSee('dana KOMITE')
            ->assertSee('Ketua Komite MTsN 2 Kota Malang')
            ->set('pratinjauTerbuka', false)
            ->assertDontSeeHtml('data-testid="panel-pratinjau"');
    }

    private function teks(SusunanKak $kak): string
    {
        $potongan = [];
        $bagian = $kak->bagian;
        array_walk_recursive($bagian, function ($nilai) use (&$potongan) {
            if (is_string($nilai)) {
                $potongan[] = $nilai;
            }
        });

        return implode("\n", $potongan);
    }
}
