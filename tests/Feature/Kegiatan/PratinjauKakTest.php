<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Contracts\MatrikJadwalRepository;
use App\Contracts\PejabatRepository;
use App\Contracts\RabRepository;
use App\Livewire\Kegiatan\Form;
use App\Support\Dokumen\SusunanKak;
use App\Support\Kegiatan\JadwalKegiatan;
use App\Support\Kegiatan\MatrikTahapan;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Tests\TestCase;

class PratinjauKakTest extends TestCase
{
    private function kegiatan(int $id): array
    {
        return app(KegiatanRepository::class)->temukan($id);
    }

    /** Matrik (tiga tahapan bertanda) & jadwal lengkap, agar kelengkapan KAK hanya bergantung pada isian lain. */
    private function lengkapiMatrikJadwal(int $id): void
    {
        app(MatrikJadwalRepository::class)->simpan(
            $id,
            MatrikTahapan::dari(['matrik' => [
                'persiapan' => [['uraian' => 'Rapat dan pembagian job deskripsi', 'bulan' => [6]]],
                'pelaksanaan' => [['uraian' => 'Persiapan pelaksanaan', 'bulan' => [7]]],
                'evaluasi_pelaporan' => [['uraian' => 'Penyusunan laporan', 'bulan' => [7]]],
            ]]),
            JadwalKegiatan::dari(['jadwal' => [['uraian' => 'Pembentukan kepanitiaan', 'bulan' => [6]], ['uraian' => 'Pelaksanaan workshop', 'bulan' => [7]]]]),
        );
    }

    public function test_sembilan_bagian_baku_berurutan_dengan_kalimat_dari_data(): void
    {
        $this->assertSame(['matrik tahapan (tab Matrik & Jadwal)', 'jadwal kegiatan (tab Matrik & Jadwal)'], SusunanKak::dari($this->kegiatan(1))->kurang);

        $this->lengkapiMatrikJadwal(1);
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
        $this->assertSame(['Mengetahui,', 'Pembuat,', 'Menyetujui,'], $peran($dipa), 'PRD §12: Mengetahui (Ketua Komite) di dokumen DIPA maupun Komite.');
        $this->assertSame(['Mengetahui,', 'Pembuat,', 'Menyetujui,'], $peran($komite));
        $this->assertSame('Muslimin, M.Pd.', $komite->bagian[8]['isi'][0]['kolom'][1]['nama']);

        app(PejabatRepository::class)->simpanAturanMengetahui(2026, ['DIPA' => false, 'Komite' => true]);
        $this->assertSame(['Pembuat,', 'Menyetujui,'], $peran(SusunanKak::dari($this->kegiatan(2), 'DIPA')), 'Pengaturan blok Mengetahui per sumber dana.');
        $this->assertSame(['Mengetahui,', 'Pembuat,', 'Menyetujui,'], $peran(SusunanKak::dari($this->kegiatan(2), 'Komite')));
    }

    public function test_isian_kosong_ditandai_bukan_diisi_teks_palsu(): void
    {
        $kak = SusunanKak::dari(['nama_kegiatan' => 'Rapat Kerja', 'status' => 'draf']);

        $this->assertSame([
            'tujuan kegiatan', 'sasaran kegiatan', 'tanggal mulai & lama kegiatan', 'tanggal pelaporan', 'matrik tahapan (tab Matrik & Jadwal)',
            'tempat pelaksanaan', 'leading penanggung jawab (Kode & Klasifikasi)', 'jadwal kegiatan (tab Matrik & Jadwal)',
            'sumber dana (bagian Sumber Dana)', 'tanggal dokumen',
        ], $kak->kurang);
        $this->assertNull($kak->sumber);
        $this->assertStringNotContainsString('bertempat di', $this->teks($kak));
    }

    public function test_disetujui_dan_lengkap_tanpa_cap_draf(): void
    {
        $this->assertTrue(SusunanKak::dari(['status' => 'disetujui'] + $this->kegiatan(1))->draf, 'Matrik & jadwal belum lengkap.');
        $this->lengkapiMatrikJadwal(1);
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
        $this->assertStringContainsString('Lengkapi matrik tahapan (tab Matrik &amp; Jadwal).', $html);
        $this->assertStringContainsString('Lengkapi jadwal kegiatan (tab Matrik &amp; Jadwal).', $html);
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

    public function test_penanda_pagu_di_pembiayaan_dan_cap_draf_bila_melebihi(): void
    {
        $komite = SusunanKak::dari($this->kegiatan(3), 'Komite');
        $this->assertSame(['melebihi', 'RAB Komite melebihi pagu Rp450.000'], [$komite->peringatanPagu->status->status, $komite->peringatanPagu->judul()]);
        $this->assertSame(['DIPA' => 'tanpa_pagu', 'Komite' => 'melebihi'], $komite->statusPagu);
        $this->assertSame(['paragraf', 'pagu'], array_column($komite->bagian[7]['isi'], 'jenis'), 'Penanda pagu tepat setelah kalimat Pembiayaan.');

        $dipa = SusunanKak::dari($this->kegiatan(3), 'DIPA');
        $this->assertSame('tanpa_pagu', $dipa->peringatanPagu->status->status);

        // Tepat pagu: tanpa penanda; disetujui & lengkap tanpa DRAF — sampai RAB-nya melebihi pagu.
        $this->lengkapiMatrikJadwal(1);
        $workshop = SusunanKak::dari(['status' => 'disetujui'] + $this->kegiatan(1));
        $this->assertNull($workshop->peringatanPagu);
        $this->assertNotContains('pagu', array_column($workshop->bagian[7]['isi'], 'jenis'));
        $this->assertFalse($workshop->draf);

        app(RabRepository::class)->tambahItem(1, 'Komite', ['kode' => '521211', 'uraian' => 'Spanduk', 'kategori' => 'bahan', 'volume' => 1, 'satuan' => 'lembar', 'harga_satuan' => 150_000]);
        $melebihi = SusunanKak::dari(['status' => 'disetujui'] + $this->kegiatan(1));
        $this->assertTrue($melebihi->draf, 'RAB melebihi pagu: dokumen tetap bercap DRAF.');
        $this->assertSame('RAB Komite melebihi pagu Rp150.000', $melebihi->peringatanPagu->judul());
    }

    public function test_matrik_dan_jadwal_tercetak_di_bagian_4_dan_7(): void
    {
        // Contoh kegiatan 3: matrik & jadwal tersimpan (sebagian tersalin, tetapi lengkap).
        $kak = SusunanKak::dari($this->kegiatan(3), 'Komite');
        $blok = fn (SusunanKak $s, int $nomor, string $jenis) => collect($s->bagian[$nomor - 1]['isi'])->firstWhere('jenis', $jenis);

        $matrik = $blok($kak, 4, 'matrik');
        $this->assertSame(2026, $matrik['tahun']);
        $this->assertSame(['Persiapan', 'Pelaksanaan', 'Evaluasi dan Pelaporan'], array_column($matrik['tahapan'], 'judul'));
        $this->assertSame([7], $matrik['tahapan'][1]['butir'][0]['bulan']);
        $this->assertSame([
            ['nomor' => 1, 'uraian' => 'Pembentukan kepanitiaan', 'waktu' => 'Oktober 2026'],
            ['nomor' => 3, 'uraian' => 'Persiapan sarana dan bahan kegiatan', 'waktu' => 'Oktober – November 2026'],
        ], [$blok($kak, 7, 'jadwal')['baris'][0], $blok($kak, 7, 'jadwal')['baris'][2]]);
        $this->assertNotContains('matrik tahapan (tab Matrik & Jadwal)', $kak->kurang);
        $this->assertNotContains('jadwal kegiatan (tab Matrik & Jadwal)', $kak->kurang);

        // Isian yang sedang disunting (belum disimpan) bisa diberikan langsung; baris kosong tidak ikut tercetak.
        $sunting = SusunanKak::dari($this->kegiatan(3), null, MatrikTahapan::dari([]), JadwalKegiatan::dari(['jadwal' => [
            ['uraian' => '', 'bulan' => []], ['uraian' => 'Rapat panitia', 'bulan' => []],
        ]]));
        $this->assertNull($blok($sunting, 4, 'matrik'));
        $this->assertSame([['nomor' => 1, 'uraian' => 'Rapat panitia', 'waktu' => null]], $blok($sunting, 7, 'jadwal')['baris']);
        $this->assertContains('matrik tahapan (tab Matrik & Jadwal)', $sunting->kurang);
        $this->assertContains('jadwal kegiatan (tab Matrik & Jadwal)', $sunting->kurang);

        $html = Blade::render('<x-dokumen.kak-tor :susunan="$s" :bagian="[4, 7]" />', ['s' => $kak]);
        $this->assertStringNotContainsString('data-testid="pratinjau-kak-cover"', $html);
        $this->assertSame(2, substr_count($html, 'data-bagian='));
        $this->assertStringContainsString('Bulan (2026)', $html);
        $this->assertSame(6, substr_count($html, 'data-matrik-butir'));
        $this->assertSame(6, preg_match_all('/<td class="border border-stone-400 text-center">v<\/td>/', $html), 'Satu "v" per butir × bulan bertanda.');
        $this->assertSame(6, substr_count($html, 'data-jadwal-baris'));
        $this->assertStringContainsString('Pelaksanaan workshop kurikulum', $html);
    }

    public function test_panel_pratinjau_menandai_pagu_per_dokumen(): void
    {
        Livewire::test(Form::class, ['kegiatan' => 3])
            ->set('pratinjauTerbuka', true)
            ->assertSeeHtml('data-testid="tanda-pagu-dipa"')
            ->assertSeeHtml('data-testid="tanda-pagu-komite"')
            ->assertSeeHtml('data-testid="peringatan-pagu-dipa"')
            ->assertSeeHtml('data-testid="penanda-pagu" data-status="tanpa_pagu"')
            ->assertSee('Penanda ini hanya di pratinjau, tidak ikut tercetak.')
            ->set('pratinjauSumber', 'Komite')
            ->assertSeeHtml('data-testid="penanda-pagu" data-status="melebihi"')
            ->assertSeeInOrder(['RAB Komite melebihi pagu Rp450.000', 'Buka RAB Komite', '8. Pembiayaan', 'RAB Komite melebihi pagu Rp450.000'])
            ->assertSeeHtml('href="'.route('kegiatan.rab', ['kegiatan' => 3, 'sumber' => 'Komite']).'"');

        Livewire::test(Form::class, ['kegiatan' => 1])
            ->set('pratinjauTerbuka', true)
            ->assertDontSeeHtml('data-testid="penanda-pagu"')
            ->assertDontSeeHtml('data-testid="peringatan-pagu-');
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
