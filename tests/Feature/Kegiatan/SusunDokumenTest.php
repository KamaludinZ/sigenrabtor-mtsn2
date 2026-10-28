<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Contracts\TemplateDokumenRepository;
use App\Livewire\Kegiatan\SusunDokumen;
use App\Support\Dokumen\SusunanKak;
use App\Support\Dokumen\SusunanOtomatis;
use App\Support\Dokumen\SusunanRab;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\Support\Docx;
use Tests\TestCase;

class SusunDokumenTest extends TestCase
{
    private const LEADING = ['id' => 1, 'name' => 'Muslimin, M.Pd.', 'role' => 'leading', 'leadings' => ['1.6']];

    private const ADMIN = ['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin', 'leadings' => []];

    /** @param  array<string, mixed>  $kegiatan */
    private function susunan(array $kegiatan, array $pengguna = self::LEADING): SusunanOtomatis
    {
        return SusunanOtomatis::dari(SusunanKak::dari($kegiatan), SusunanRab::dari($kegiatan), $kegiatan, $pengguna, 2026);
    }

    public function test_status_dan_asal_isi_tiap_bagian(): void
    {
        $id = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Rapat Kerja Madrasah', 'leading' => '1.6', 'user_id' => 1, 'tahun_anggaran' => 2026]);
        $kegiatan = app(KegiatanRepository::class)->temukan($id);
        $kak = SusunanKak::dari($kegiatan);
        $susunan = $this->susunan($kegiatan);

        $this->assertSame(range(1, 9), array_column($susunan->bagian, 'nomor'));
        $this->assertSame(array_column($kak->bagian, 'judul'), array_column($susunan->bagian, 'judul'));
        foreach ($susunan->bagian as $b) {
            $this->assertSame(array_values(array_unique($kak->kurangPerBagian[$b['nomor']] ?? [])), $b['kurang']);
            $this->assertSame($b['kurang'] !== [] ? 'kurang' : ($b['periksa'] !== [] ? 'periksa' : 'lengkap'), $b['status']);
        }
        $this->assertSame(['tujuan kegiatan', 'sasaran kegiatan'], $susunan->bagian[2]['kurang']);
        // Pejabat TA 2026 contoh sengaja kosong: lembar pengesahan perlu diperiksa (kaidah penanda tangan), bukan kurang isian.
        $kegiatan['tanggal_dokumen'] = '2026-01-03';
        $pengesahan = $this->susunan($kegiatan)->bagian[8];
        $this->assertSame(['periksa', []], [$pengesahan['status'], $pengesahan['kurang']]);
        $this->assertStringStartsWith('Nama penanda tangan belum diisi: ', $pengesahan['periksa'][0]);

        // RAB Komite contoh 3 melebihi pagu: Pembiayaan perlu diperiksa.
        $contoh3 = app(KegiatanRepository::class)->temukan(3);
        $pembiayaan = SusunanOtomatis::dari(SusunanKak::dari($contoh3, 'Komite'), SusunanRab::dari($contoh3, 'Komite'), $contoh3, self::LEADING, 2026)->bagian[7];
        $this->assertSame('periksa', $pembiayaan['status']);
        $this->assertStringStartsWith('RAB Komite melebihi pagu Rp', $pembiayaan['periksa'][0]);
        $this->assertSame(count(array_filter($susunan->bagian, fn ($b) => $b['status'] === 'lengkap')), $susunan->lengkap);
        $this->assertSame((int) floor($susunan->lengkap / 9 * 100), $susunan->persen());

        // Asal isi: tempat kegiatan bertaut ke ruang kerjanya; yang diatur admin hanya bertaut untuk admin.
        $this->assertSame(['latar_baku', 'identitas', 'kalimat'], array_column($susunan->bagian[0]['asal'], 'kunci'));
        $this->assertSame(route('kegiatan.edit', ['kegiatan' => $id]).'#identitas', $susunan->bagian[0]['asal'][1]['tautan']);
        $this->assertSame(route('kegiatan.matrik', ['kegiatan' => $id]).'#jadwal', $susunan->bagian[6]['asal'][0]['tautan']);
        $this->assertSame([null, true], [$susunan->bagian[1]['asal'][0]['tautan'], $susunan->bagian[1]['asal'][0]['admin']]);
        $admin = $this->susunan($kegiatan, self::ADMIN);
        $this->assertSame(route('pengaturan.madrasah.dasar_hukum'), $admin->bagian[1]['asal'][0]['tautan']);
        $this->assertSame(route('pengaturan.madrasah.index', ['tab' => 'pejabat']), $admin->bagian[8]['asal'][0]['tautan']);

        // Tanpa sumber dana: kepala RAB tetap tersusun, kekurangannya tercatat.
        $this->assertContains('Kode RKTM', array_column($susunan->kepalaRab, 'label'));
        $this->assertNull(collect($susunan->kepalaRab)->firstWhere('label', 'Kode RKTM')['nilai']);
        $this->assertContains('kode rktm', $susunan->kurangRab);
    }

    public function test_master_template_yang_dipakai(): void
    {
        $this->assertNull(SusunanOtomatis::template(2026), 'Tanpa master: susunan baku aplikasi.');
        $repo = app(TemplateDokumenRepository::class);
        $berkas = fn (string $nama) => UploadedFile::fake()->createWithContent($nama, Docx::isi(['Kegiatan ${nama_kegiatan}']));

        $repo->tambah(2026, 'Master RAB', 'RAB', $berkas('rab.docx'), ['nama_kegiatan']);
        $this->assertNull(SusunanOtomatis::template(2026), 'Master RAB bukan master KAK/TOR.');
        $repo->tambah(2026, 'Master KAK', 'KAK', $berkas('kak.docx'), ['nama_kegiatan']);
        $this->assertSame(['Master KAK', 'KAK', ['nama_kegiatan']], [SusunanOtomatis::template(2026)['nama'], SusunanOtomatis::template(2026)['jenis'], SusunanOtomatis::template(2026)['variabel']]);
        $repo->tambah(2026, 'Master gabungan', 'KAK+TOR', $berkas('kak-tor.docx'), ['nama_kegiatan']);
        $this->assertSame('KAK+TOR', SusunanOtomatis::template(2026)['jenis'], 'KAK/TOR gabungan didahulukan.');
        $this->assertNull(SusunanOtomatis::template(2025));
    }

    public function test_halaman_susun_kak_tor(): void
    {
        $this->get(route('kegiatan.dokumen', ['kegiatan' => 1]))->assertOk()->assertSee('Susun KAK/TOR');

        $halaman = Livewire::test(SusunDokumen::class, ['kegiatan' => 1])
            ->assertSeeHtml('data-testid="bilah-susun"')
            ->assertSeeHtml('data-testid="draf-dokumen" data-ada="ya"')
            ->assertSee('Tersimpan 8 Januari 2026, 10.15 oleh Muslimin, M.Pd.')
            ->assertSeeHtml('data-testid="template-susun" data-ada="tidak"')
            ->assertSee('Belum ada master KAK/TOR — dokumen memakai susunan baku aplikasi.')
            ->assertSee('Master template diatur admin.')
            ->assertSeeHtml('aria-current="page"');
        $html = $halaman->html();
        $this->assertSame(9, substr_count($html, 'data-testid="bagian-susun"'));
        $this->assertSame(9, substr_count($html, 'data-testid="pratinjau-kak"'), 'Cuplikan hasil susunan tiap bagian.');
        $this->assertStringNotContainsString('data-testid="cap-draf"', $html, 'Cap DRAF tidak diulang di cuplikan.');
        $this->assertStringContainsString('href="'.route('kegiatan.dokumen', ['kegiatan' => 1]).'"', $this->get(route('kegiatan.pratinjau', ['kegiatan' => 1]))->getContent(), 'Tab Susun KAK/TOR di ruang kerja.');

        // Dua sumber dana (contoh 2): pilihan sumber mengganti RAB & cuplikan Pembiayaan; sumber asing diabaikan.
        Livewire::test(SusunDokumen::class, ['kegiatan' => 2])
            ->assertSeeHtml('data-testid="draf-dokumen" data-ada="tidak"')
            ->assertSet('sumber', '')
            ->set('sumber', 'Komite')
            ->assertSet('sumber', 'Komite')
            ->assertSee('RAB Komite')
            ->set('sumber', 'BOS')
            ->assertSet('sumber', '');

        session(['stub_role' => 'admin']);
        Livewire::test(SusunDokumen::class, ['kegiatan' => 1])
            ->assertSee('Atur master KAK/TOR')
            ->assertSeeHtml('href="'.route('pengaturan.madrasah.dasar_hukum').'"');
    }

    public function test_hak_akses_kegiatan(): void
    {
        $id = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Lomba Kreativitas Siswa', 'leading' => '1.2', 'user_id' => 4, 'tahun_anggaran' => 2026]);
        $this->get(route('kegiatan.dokumen', ['kegiatan' => $id]))->assertForbidden();
        $this->get(route('kegiatan.dokumen', ['kegiatan' => 999]))->assertNotFound();
    }
}
