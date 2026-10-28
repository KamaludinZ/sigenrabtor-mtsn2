<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Contracts\RabRepository;
use App\Contracts\TemplateDokumenRepository;
use App\Livewire\Kegiatan\SusunDokumen;
use App\Support\Dokumen\DokumenKegiatan;
use App\Support\Dokumen\IsianSusun;
use App\Support\Dokumen\JenisDokumen;
use App\Support\Dokumen\StatusCetak;
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
        return SusunanOtomatis::dari(SusunanKak::dari($kegiatan), SusunanRab::dari($kegiatan), $kegiatan, $pengguna);
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
        $pembiayaan = SusunanOtomatis::dari(SusunanKak::dari($contoh3, 'Komite'), SusunanRab::dari($contoh3, 'Komite'), $contoh3, self::LEADING)->bagian[7];
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

    public function test_master_template_per_jenis_dokumen(): void
    {
        $this->assertSame(['kak' => null, 'rab' => null], JenisDokumen::template('lengkap', 2026), 'Tanpa master: susunan baku aplikasi.');
        $repo = app(TemplateDokumenRepository::class);
        $berkas = fn (string $nama) => UploadedFile::fake()->createWithContent($nama, Docx::isi(['Kegiatan ${nama_kegiatan}']));

        $repo->tambah(2026, 'Master RAB', 'RAB', $berkas('rab.docx'), ['nama_kegiatan']);
        $repo->tambah(2026, 'Master gabungan', 'KAK+TOR', $berkas('kak-tor.docx'), ['nama_kegiatan']);
        $this->assertSame('Master gabungan', JenisDokumen::template('TOR', 2026)['kak']['nama'], 'TOR tanpa master sendiri memakai master gabungan.');
        $this->assertSame(['kak'], array_keys(JenisDokumen::template('KAK', 2026)));
        $this->assertSame(['rab'], array_keys(JenisDokumen::template('RAB', 2026)));
        $repo->tambah(2026, 'Master KAK', 'KAK', $berkas('kak.docx'), ['nama_kegiatan']);
        $this->assertSame(['Master KAK', ['nama_kegiatan']], [JenisDokumen::template('KAK', 2026)['kak']['nama'], JenisDokumen::template('KAK', 2026)['kak']['variabel']]);
        $this->assertSame(['Master gabungan', 'Master RAB'], [JenisDokumen::template('lengkap', 2026)['kak']['nama'], JenisDokumen::template('lengkap', 2026)['rab']['nama']]);
        $this->assertSame(['kak' => null], JenisDokumen::template('KAK+TOR', 2025));
    }

    public function test_pemilihan_jenis_dokumen(): void
    {
        $this->assertSame(['lengkap', 'KAK', 'lengkap'], [JenisDokumen::rapikan(null), JenisDokumen::rapikan('KAK'), JenisDokumen::rapikan('Surat')]);
        $this->assertEqualsCanonicalizing(array_keys(DokumenKegiatan::JENIS), JenisDokumen::URUTAN, 'Semua jenis dokumen bisa dipilih.');

        $halaman = Livewire::test(SusunDokumen::class, ['kegiatan' => 1])
            ->assertSeeHtml('data-testid="pilih-jenis-dokumen"')
            ->assertSeeHtml('data-jenis="lengkap" data-terpilih="ya"')
            ->assertSee('KAK-TOR_RAB_Workshop_Penyusunan_Kurikulum');
        $this->assertSame(5, substr_count($halaman->html(), 'data-jenis="'));
        $this->assertLessThan(strpos($halaman->html(), 'data-jenis="KAK+TOR"'), strpos($halaman->html(), 'data-jenis="lengkap"'), 'Bawaan di depan.');

        // RAB saja: tanpa cover & bagian KAK/TOR, berkas RAB, cek rincian RAB.
        $rab = $halaman->set('jenis', 'RAB')->assertSet('jenis', 'RAB')->html();
        $this->assertSame([0, false, true], [substr_count($rab, 'data-testid="bagian-susun"'), str_contains($rab, 'data-testid="susun-cover"'), str_contains($rab, 'data-testid="susun-rab"')]);
        $this->assertStringContainsString('>RAB_Workshop_Penyusunan_Kurikulum', $rab);
        $this->assertStringContainsString('data-cek="rincian_rab"', $rab);

        // KAK saja: sembilan bagian tanpa RAB & cover.
        $kak = $halaman->set('jenis', 'KAK')->html();
        $this->assertSame([9, false, false], [substr_count($kak, 'data-testid="bagian-susun"'), str_contains($kak, 'data-testid="susun-cover"'), str_contains($kak, 'data-testid="susun-rab"')]);
        $this->assertStringContainsString('>KAK_Workshop_Penyusunan_Kurikulum', $kak);
        $halaman->set('jenis', 'Surat')->assertSet('jenis', 'lengkap');

        // Tanpa sumber dana: RAB & set lengkap tidak bisa dipilih, jenis jatuh ke KAK/TOR.
        $id = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Rapat Kerja Madrasah', 'leading' => '1.6', 'user_id' => 1, 'tahun_anggaran' => 2026]);
        Livewire::withQueryParams(['jenis' => 'RAB'])->test(SusunDokumen::class, ['kegiatan' => $id])
            ->assertSet('jenis', 'KAK+TOR')
            ->assertSeeHtml('data-jenis="RAB" data-terpilih="tidak" data-bisa="tidak"')
            ->assertSeeHtml('data-jenis="lengkap" data-terpilih="tidak" data-bisa="tidak"')
            ->assertSee('Pilih sumber dana kegiatan dulu (Data Kegiatan › Sumber Dana).')
            ->set('jenis', 'lengkap')
            ->assertSet('jenis', 'KAK+TOR');

        $pilihan = collect(JenisDokumen::pilihan(StatusCetak::dari(app(KegiatanRepository::class)->temukan(1), null), 'Komite', 2026))->keyBy('kunci');
        $this->assertSame([['Cover', 'KAK/TOR', 'RAB'], true, true], [$pilihan['lengkap']['isi'], $pilihan['lengkap']['perlu_sumber'], $pilihan['lengkap']['draf']]);
        $this->assertSame([['KAK/TOR'], false], [$pilihan['TOR']['isi'], $pilihan['TOR']['perlu_sumber']]);
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
        $this->assertSame('lengkap', $halaman->get('jenis'), 'Bawaan set lengkap: cover, KAK/TOR, RAB.');
        $this->assertStringContainsString('data-testid="susun-cover"', $html);
        $this->assertStringContainsString('data-testid="susun-rab"', $html);
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

    public function test_lengkapi_isian_bagian_di_tempat(): void
    {
        $repo = app(KegiatanRepository::class);
        $id = $repo->simpan(['nama_kegiatan' => 'Rapat Kerja Madrasah', 'leading' => '1.6', 'user_id' => 1, 'tahun_anggaran' => 2026]);
        $this->assertSame([[], ['tujuan', 'sasaran']], [IsianSusun::untuk(2), IsianSusun::untuk(3)]);
        $this->assertSame(['tanggal_mulai' => null, 'lama_hari' => 2, 'peserta_jumlah' => null, 'peserta_jenis' => 'guru', 'uraian_pelaksanaan' => null],
            IsianSusun::kiriman(4, ['lama_hari' => '2', 'tanggal_mulai' => ' ', 'peserta_jenis' => ' guru ', 'tempat' => 'diabaikan']));

        $halaman = Livewire::test(SusunDokumen::class, ['kegiatan' => $id])
            ->assertSeeHtml('data-testid="lengkapi-3"')
            ->assertDontSeeHtml('data-testid="lengkapi-2"')
            ->call('bukaIsian', 3)
            ->assertSet('isianTerbuka', 3)
            ->assertSet('isian', ['tujuan' => '', 'sasaran' => ''])
            ->assertSeeHtml('data-testid="isian-bagian-3"')
            ->set('isian.tujuan', 'menyusun rencana kerja tahunan madrasah.')
            ->set('isian.sasaran', 'rencana kerja yang terukur dan disepakati warga madrasah.')
            ->call('simpanIsian')
            ->assertHasNoErrors()
            ->assertSet('isianTerbuka', null)
            ->assertDispatched('notify', message: 'Bagian 3 diperbarui — susunan KAK/TOR mengikuti isian baru.', type: 'success')
            ->assertSeeHtml('data-bagian="3" data-status="lengkap"');
        $this->assertSame('menyusun rencana kerja tahunan madrasah.', $repo->temukan($id)['tujuan']);

        // Galat aturan tampil di kolomnya; isian tetap terbuka.
        $halaman->call('bukaIsian', 4)->set('isian.lama_hari', 'dua')->set('isian.peserta_jumlah', '0')->call('simpanIsian')
            ->assertHasErrors(['isian.lama_hari', 'isian.peserta_jumlah'])
            ->assertSet('isianTerbuka', 4)
            ->call('tutupIsian')
            ->assertSet('isianTerbuka', null);

        // Berubah di tempat lain sejak dibuka: isian dimuat ulang, tidak menimpa.
        $halaman->call('bukaIsian', 5);
        $repo->simpan(['tempat' => 'Aula Utama'] + $repo->temukan($id), $id);
        $halaman->set('isian.tempat', 'Laboratorium')->call('simpanIsian')
            ->assertSet('isian.tempat', 'Aula Utama')
            ->assertSet('isianTerbuka', 5)
            ->assertDispatched('notify', type: 'error');
        $this->assertSame('Aula Utama', $repo->temukan($id)['tempat']);
        $halaman->call('bukaIsian', 2)->assertNotFound();
    }

    public function test_tambah_rincian_rab_dari_susun(): void
    {
        $repo = app(KegiatanRepository::class);
        $id = $repo->simpan(['nama_kegiatan' => 'Rapat Kerja Madrasah', 'leading' => '1.6', 'user_id' => 1, 'tahun_anggaran' => 2026, 'sumber_dana' => [['sumber' => 'DIPA', 'pagu' => 2_000_000]]]);

        Livewire::test(SusunDokumen::class, ['kegiatan' => $id])
            ->assertSee('Belum ada rincian')
            ->call('tambahRincian')
            ->assertHasErrors(['rincianBaru.uraian', 'rincianBaru.volume', 'rincianBaru.satuan', 'rincianBaru.harga_satuan'])
            ->set('rincianBaru', ['uraian' => 'Konsumsi rapat koordinasi', 'kategori' => '', 'volume' => '20', 'satuan' => 'kotak', 'harga_satuan' => '25.000'])
            ->call('tambahRincian')
            ->assertHasNoErrors()
            ->assertSet('rincianBaru.uraian', '')
            ->assertDispatched('notify', message: 'Rincian ditambahkan ke RAB DIPA: Rp500.000.', type: 'success')
            ->assertSeeHtml('data-testid="daftar-rincian-susun"')
            ->assertSee('Konsumsi rapat koordinasi');
        $this->assertSame(['Konsumsi rapat koordinasi', 'bahan'], [app(RabRepository::class)->rincian($id, 'DIPA')[0]['uraian'], app(RabRepository::class)->rincian($id, 'DIPA')[0]['kategori']], 'Kelompok ditebak dari uraian.');

        // Terkunci (diajukan): tanpa formulir; aksi ditolak.
        $repo->simpan(['status' => 'diperiksa'] + $repo->temukan($id), $id);
        Livewire::test(SusunDokumen::class, ['kegiatan' => $id])
            ->assertDontSeeHtml('data-testid="tambah-rincian-susun"')
            ->assertDontSeeHtml('data-testid="lengkapi-3"')
            ->set('rincianBaru', ['uraian' => 'Fotokopi materi', 'kategori' => 'bahan', 'volume' => '10', 'satuan' => 'rim', 'harga_satuan' => '50000'])
            ->call('tambahRincian')
            ->assertDispatched('notify', type: 'error')
            ->call('bukaIsian', 3)
            ->assertForbidden();
        $this->assertCount(1, app(RabRepository::class)->rincian($id, 'DIPA'));
    }

    public function test_tampilan_dokumen_utuh_hasil_susunan(): void
    {
        $halaman = Livewire::test(SusunDokumen::class, ['kegiatan' => 1])
            ->assertSet('tampilan', 'bagian')
            ->call('bukaIsian', 3)
            ->set('tampilan', 'dokumen')
            ->assertSet('isianTerbuka', null)
            ->assertSeeHtml('data-testid="hasil-susunan"')
            ->assertSeeHtml('data-tampilan="dokumen"')
            ->assertSeeHtml('href="#cetak-kak-bagian-7"')
            ->assertSeeHtml('href="#hasil-rab"');
        $html = $halaman->html();
        $this->assertSame([0, 1, 1], [substr_count($html, 'data-testid="bagian-susun"'), substr_count($html, 'data-testid="pratinjau-kak"'), substr_count($html, 'data-testid="pratinjau-rab"')]);
        $this->assertStringContainsString('data-testid="hasil-cover"', $html);
        $this->assertStringNotContainsString('data-testid="pratinjau-kak-cover"', $html, 'Set lengkap memakai cover terpisah.');
        $this->assertStringContainsString('data-testid="cap-draf"', $html, 'Cap DRAF tampil di dokumen utuh.');
        $this->assertStringNotContainsString('data-testid="buka-semua-bagian"', $html);

        // KAK saja: halaman judul KAK/TOR ikut, tanpa cover & RAB.
        $kak = $halaman->set('jenis', 'KAK')->html();
        $this->assertSame([true, false, false], [str_contains($kak, 'data-testid="pratinjau-kak-cover"'), str_contains($kak, 'data-testid="hasil-cover"'), str_contains($kak, 'data-testid="hasil-rab"')]);

        $halaman->set('tampilan', 'cetak')->assertSet('tampilan', 'bagian')->assertSeeHtml('data-testid="bagian-susun"');
        Livewire::withQueryParams(['tampilan' => 'dokumen', 'jenis' => 'RAB'])->test(SusunDokumen::class, ['kegiatan' => 1])
            ->assertSeeHtml('data-testid="hasil-rab"')
            ->assertDontSeeHtml('data-testid="hasil-kak"');
    }

    public function test_hak_akses_kegiatan(): void
    {
        $id = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Lomba Kreativitas Siswa', 'leading' => '1.2', 'user_id' => 4, 'tahun_anggaran' => 2026]);
        $this->get(route('kegiatan.dokumen', ['kegiatan' => $id]))->assertForbidden();
        $this->get(route('kegiatan.dokumen', ['kegiatan' => 999]))->assertNotFound();
    }
}
