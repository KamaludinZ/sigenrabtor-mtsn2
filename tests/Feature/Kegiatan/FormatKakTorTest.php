<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Contracts\RiwayatPengaturanRepository;
use App\Livewire\Kegiatan\SusunDokumen;
use App\Livewire\Pengaturan\FormatSeragam;
use App\Support\Dokumen\ChecklistIsian;
use App\Support\Dokumen\DataPratinjau;
use App\Support\Dokumen\FormatKakTor;
use App\Support\Dokumen\FormatSeragam as AturanFormat;
use App\Support\Dokumen\Generasi\WordKakTor;
use App\Support\Dokumen\IsianSusun;
use App\Support\Dokumen\PemeriksaanCetak;
use App\Support\Dokumen\SuntingDokumen;
use App\Support\Dokumen\SusunanKak;
use App\Support\Dokumen\SusunanRab;
use App\Support\Kegiatan\KalimatKak;
use App\Support\Kegiatan\TeksKhusus;
use App\Support\Kegiatan\UbahDataKegiatan;
use App\Support\Rab\EksporRabExcel;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Blade;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;
use ZipArchive;

class FormatKakTorTest extends TestCase
{
    private const ADMIN = ['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin', 'leadings' => []];

    /** Contoh kegiatan 1 (Komite, TA 2026) dengan format 5 bab dipilih untuk kegiatan itu. */
    private function kegiatanLimaBab(array $data = []): array
    {
        $repo = app(KegiatanRepository::class);
        $repo->simpan($data + ['format_dokumen' => FormatKakTor::LIMA_BAB] + $repo->temukan(1), 1);

        return $repo->temukan(1);
    }

    /** @return list<string> Teks subjudul satu bagian. */
    private function subjudul(array $bagian): array
    {
        return array_values(array_column(array_filter($bagian['isi'], fn (array $b) => $b['jenis'] === 'subjudul'), 'teks'));
    }

    public function test_format_berlaku_per_kegiatan_tahun_dan_bawaan(): void
    {
        $kegiatan = app(KegiatanRepository::class)->temukan(1);
        $this->assertSame([FormatKakTor::SEMBILAN_BAGIAN, FormatKakTor::LIMA_BAB], [FormatKakTor::bawaan(2026), FormatKakTor::bawaan(2027)]);
        $this->assertSame(FormatKakTor::SEMBILAN_BAGIAN, FormatKakTor::untuk($kegiatan), 'TA 2026 tanpa pilihan = format 9 bagian.');
        $this->assertSame(FormatKakTor::LIMA_BAB, FormatKakTor::untuk(['tahun_anggaran' => 2027]));

        $hasil = FormatKakTor::simpanTahun(2026, FormatKakTor::LIMA_BAB, self::ADMIN);
        $this->assertSame(['format' => 'lima_bab', 'pilihan' => 'lima_bab', 'berubah' => true], $hasil);
        $this->assertSame(FormatKakTor::LIMA_BAB, FormatKakTor::untuk($kegiatan));
        $this->assertSame(FormatKakTor::SEMBILAN_BAGIAN, FormatKakTor::untuk(['format_dokumen' => FormatKakTor::SEMBILAN_BAGIAN] + $kegiatan), 'Pilihan kegiatan mengalahkan pilihan tahun.');
        $this->assertSame('Format KAK/TOR TA 2026: bawaan (Format 9 bagian) → Format 5 bab.', app(RiwayatPengaturanRepository::class)->semua()[0]['keterangan']);
        $this->assertFalse(FormatKakTor::simpanTahun(2026, FormatKakTor::LIMA_BAB, self::ADMIN)['berubah']);
        $this->assertSame('sembilan_bagian', FormatKakTor::simpanTahun(2026, null, self::ADMIN)['format']);
        $this->assertNull(FormatKakTor::pilihanTahun(2026));

        try {
            FormatKakTor::simpanTahun(2026, 'empat_bab', self::ADMIN);
            $this->fail('Format tak dikenal ditolak.');
        } catch (ValidationException $e) {
            $this->assertSame('Pilih format dokumen yang tersedia.', $e->errors()['format'][0]);
        }
        foreach ([[2026, ['role' => 'leading']], [2025, self::ADMIN]] as [$tahun, $pengguna]) {
            try {
                FormatKakTor::simpanTahun($tahun, FormatKakTor::LIMA_BAB, $pengguna);
                $this->fail('Bukan admin / tahun terkunci ditolak.');
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }

        // Format kegiatan lewat UbahDataKegiatan (aturan ValidasiKegiatan).
        try {
            UbahDataKegiatan::ubah(1, ['format_dokumen' => 'empat_bab'], self::ADMIN);
            $this->fail('Format kegiatan tak dikenal ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('format_dokumen', $e->errors());
        }
        $this->assertSame(['format_dokumen'], UbahDataKegiatan::ubah(1, ['format_dokumen' => 'lima_bab'], self::ADMIN)['diubah']);
        $this->assertSame('lima_bab', app(KegiatanRepository::class)->temukan(1)['format_dokumen']);
    }

    public function test_susunan_lima_bab(): void
    {
        $kegiatan = $this->kegiatanLimaBab();
        $kak = SusunanKak::dari($kegiatan);
        $nama = KalimatKak::tanpaAwalanKegiatan($kegiatan['nama_kegiatan']);

        $this->assertSame(FormatKakTor::LIMA_BAB, $kak->format);
        $this->assertSame([[1, 'I', 'Pendahuluan'], [2, 'II', 'Kegiatan yang Dilaksanakan'], [3, 'III', 'Tahapan Kegiatan'], [4, 'IV', 'Hasil yang Akan Dicapai'], [5, 'V', 'Penutup']],
            array_map(fn (array $b) => [$b['nomor'], $b['label'], $b['judul']], $kak->bagian));
        $this->assertSame(['A. Latar Belakang', 'B. Dasar Hukum', 'C. Maksud dan Tujuan'], $this->subjudul($kak->bagian[0]));
        $this->assertSame(['1. Nama Kegiatan', '2. Peserta', '3. Waktu dan Tempat', '4. Biaya', '5. Penyelenggaraan Kegiatan', 'a. Penanggung Jawab', 'b. Pelaksana', '6. Narasumber / Fasilitator dan Materi'], $this->subjudul($kak->bagian[1]));
        $this->assertSame(['1. Persiapan', '2. Pelaksanaan', '3. Pelaporan'], $this->subjudul($kak->bagian[2]));
        $this->assertSame(['1. Output', '2. Outcome'], $this->subjudul($kak->bagian[3]));

        $teks = collect($kak->bagian)->flatMap(fn (array $b) => array_column($b['isi'], 'teks'))->all();
        $this->assertContains(KalimatKak::maksudTujuan($kegiatan['nama_kegiatan'], $kegiatan['tujuan']), $teks);
        $this->assertStringStartsWith("Maksud dan tujuan dilaksanakannya kegiatan {$nama} adalah untuk ", KalimatKak::maksudTujuan($kegiatan['nama_kegiatan'], $kegiatan['tujuan']));
        $this->assertContains("Penanggung jawab kegiatan {$nama} adalah Kepala ".StubData::madrasah()['nama'].'.', $teks);
        $this->assertContains("Terlaksananya kegiatan {$nama} bagi peserta.", $teks);
        $this->assertContains('Adapun matrik tahapan kegiatan adalah sebagai berikut:', $teks);
        $this->assertContains(KalimatKak::penutup($kegiatan['nama_kegiatan'], StubData::madrasah()['nama_singkat']), $teks);

        // Sasaran & tabel jadwal tidak dicetak; jangkar tempat cetak tetap ada.
        $jenis = collect($kak->bagian)->flatMap(fn (array $b) => array_column($b['isi'], 'jenis'))->all();
        $this->assertNotContains('jadwal', $jenis);
        $tempat = collect($kak->bagian)->flatMap(fn (array $b) => array_filter(array_column($b['isi'], 'tempat')))->values()->all();
        $this->assertSame(['kak-pelaksanaan', 'kak-pembiayaan', 'kak-pelaporan'], $tempat);
        $this->assertSame(['tujuan', 'tempat', 'penanggung_jawab', 'narasumber', 'persiapan', 'uraian_pelaksanaan', 'output', 'outcome'],
            collect($kak->bagian)->flatMap(fn (array $b) => array_filter(array_column($b['isi'], 'sunting')))->values()->all());
        $this->assertSame([[3], [1], [1], []], [$kak->nomorUntuk('matrik', 'jadwal'), $kak->nomorUntuk('latar_belakang', 'dasar_hukum'), $kak->nomorUntuk('tujuan'), $kak->nomorUntuk('sasaran')]);
        $this->assertNotContains('sasaran kegiatan', $kak->kurang);

        // Tanda tangan: Menyetujui & pembuat berdampingan, Mengetahui di tengah bawah (Ketua Komite tanpa NIP).
        $kolom = collect($kak->bagian[4]['isi'])->firstWhere('jenis', 'pengesahan')['kolom'];
        $this->assertSame([['Menyetujui', 'Menyetujui', 0], ['', 'Pembuat', 0], ['Mengetahui', 'Mengetahui', 1]], array_map(fn (array $k) => [$k['peran'], $k['sebutan'], $k['baris']], $kolom));
        $this->assertTrue($kolom[2]['tanpa_nip']);
        $this->assertStringStartsWith('Malang, ', $kolom[1]['tempat_tanggal']);
        $this->assertCount(2, SusunanKak::barisPengesahan($kolom));
    }

    public function test_format_sembilan_bagian_tidak_berubah(): void
    {
        $kegiatan = app(KegiatanRepository::class)->temukan(1);
        $kak = SusunanKak::dari($kegiatan);

        $this->assertSame(FormatKakTor::SEMBILAN_BAGIAN, $kak->format);
        $this->assertSame(range(1, 9), array_column($kak->bagian, 'nomor'));
        $this->assertArrayNotHasKey('label', $kak->bagian[0]);
        $this->assertSame('7', SusunanKak::labelBagian($kak->bagian[6]));
        $this->assertSame([4, 7], $kak->nomorUntuk('matrik', 'jadwal'));
        $kolom = collect($kak->bagian[8]['isi'])->firstWhere('jenis', 'pengesahan')['kolom'];
        $this->assertSame(['Mengetahui,', 'Pembuat,', 'Menyetujui,'], array_column($kolom, 'peran'));
        $this->assertArrayNotHasKey('baris', $kolom[0]);
        $this->assertCount(1, SusunanKak::barisPengesahan($kolom));
        $this->assertEquals(SusunanKak::dari($kegiatan, format: FormatKakTor::SEMBILAN_BAGIAN), $kak);
    }

    public function test_teks_khusus_dan_sunting_format_lima_bab(): void
    {
        $kegiatan = $this->kegiatanLimaBab(['teks_khusus' => ['narasumber' => 'Narasumber kegiatan {nama_kegiatan} adalah pengawas madrasah.', 'output' => 'Tersusunnya dokumen kurikulum madrasah.']]);
        $nama = KalimatKak::tanpaAwalanKegiatan($kegiatan['nama_kegiatan']);

        $this->assertSame("Narasumber kegiatan {$nama} adalah pengawas madrasah.", TeksKhusus::cetak('narasumber', $kegiatan));
        $this->assertSame(['penanggung_jawab', 'narasumber'], TeksKhusus::untukBagian(2, FormatKakTor::LIMA_BAB));
        $this->assertSame(['persiapan', 'analisis_hasil'], TeksKhusus::untukBagian(3, FormatKakTor::LIMA_BAB));
        $this->assertSame(['persiapan', 'analisis_hasil'], TeksKhusus::untukBagian(4));
        $this->assertSame(['pengarah'], TeksKhusus::untukBagian(6));
        $this->assertSame(['Tahapan persiapan (III.1)', 'Tahapan persiapan (4a)', 'Output (IV.1)'], [TeksKhusus::label('persiapan', FormatKakTor::LIMA_BAB), TeksKhusus::label('persiapan'), TeksKhusus::label('output', FormatKakTor::LIMA_BAB)]);
        $this->assertSame([], TeksKhusus::rapikan(['outcome' => TeksKhusus::bakuMentah('outcome')]), 'Sama dengan teks baku = tidak disimpan.');
        $this->assertSame(['output' => 'Output (IV.1): tiap kalimat 10–1000 karakter.'], TeksKhusus::galat(['output' => 'Pendek']));

        $kak = SusunanKak::dari($kegiatan);
        $this->assertSame(['latar_belakang'], SuntingDokumen::tambahan($kak->bagian[0], $kak->format));
        $this->assertSame([], SuntingDokumen::tambahan($kak->bagian[3], $kak->format));
        $this->assertSame(['tempat', 'penanggung_jawab', 'narasumber'], SuntingDokumen::untukBagian(2, FormatKakTor::LIMA_BAB));
        $this->assertSame([['peserta_jumlah', 'peserta_jenis', 'tanggal_mulai', 'lama_hari', 'tempat'], ['uraian_pelaksanaan']], [IsianSusun::untuk(2, FormatKakTor::LIMA_BAB), IsianSusun::untuk(3, FormatKakTor::LIMA_BAB)]);
        $this->assertContains('Tersusunnya dokumen kurikulum madrasah.', collect($kak->bagian[3]['isi'])->pluck('teks')->all());

        // Data pratinjau (Word di peramban) membawa label bab & format.
        $data = DataPratinjau::untuk($kegiatan, null, self::ADMIN, ['kak']);
        $this->assertSame(['I', 'II', 'III', 'IV', 'V'], array_column($data['dokumen']['kak']['bagian'], 'label'));
        $this->assertSame(['kunci' => 'lima_bab', 'label' => 'Format 5 bab'], $data['dokumen']['kak']['format']);
        $this->assertSame(['tempat', 'penanggung_jawab', 'narasumber'], array_column($data['dokumen']['kak']['bagian'][1]['sunting'], 'kunci'));
    }

    public function test_pengesahan_rab_dan_berkas_lima_bab(): void
    {
        $kegiatan = $this->kegiatanLimaBab();
        $rab = SusunanRab::dari($kegiatan);
        $this->assertSame(FormatKakTor::LIMA_BAB, $rab->format);
        $this->assertSame([['Menyetujui', 0], ['Penanggungjawab', 0], ['Mengetahui', 1]], array_map(fn (array $k) => [$k['peran'], $k['baris']], $rab->pengesahan));

        $html = Blade::render('<x-dokumen.pengesahan :kolom="$kolom" />', ['kolom' => $rab->pengesahan]);
        $this->assertStringContainsString('data-tata-letak="dua_baris"', $html);
        $this->assertStringContainsString('data-baris-pengesahan="1"', $html);
        $this->assertSame(2, substr_count($html, 'NIP.'), 'Ketua Komite tanpa NIP tidak dicetak baris NIP-nya.');
        $this->assertStringContainsString('data-tata-letak="sejajar"', Blade::render('<x-dokumen.pengesahan :kolom="$kolom" />', ['kolom' => SusunanRab::dari(app(KegiatanRepository::class)->temukan(2))->pengesahan]));

        // Excel RAB: Menyetujui di kolom A, Penanggungjawab di D, Mengetahui di tengah (kolom uraian) di bawahnya.
        $lembar = EksporRabExcel::buat($kegiatan, $rab->sumber)['buku']->getActiveSheet();
        $sel = [];
        foreach ($lembar->getRowIterator() as $baris) {
            foreach ($baris->getCellIterator() as $c) {
                if (in_array($c->getValue(), ['Menyetujui', 'Penanggungjawab', 'Mengetahui'], true)) {
                    $sel[$c->getValue()] = [$c->getColumn(), $c->getRow()];
                }
            }
        }
        $this->assertSame(['A', 'D'], [$sel['Menyetujui'][0], $sel['Penanggungjawab'][0]]);
        $this->assertContains($sel['Mengetahui'][0], ['B', 'C']);
        $this->assertSame('center', $lembar->getStyle($sel['Mengetahui'][0].$sel['Mengetahui'][1])->getAlignment()->getHorizontal());
        $this->assertSame([$sel['Menyetujui'][1], $sel['Menyetujui'][1] + 7], [$sel['Penanggungjawab'][1], $sel['Mengetahui'][1]]);

        // Word KAK/TOR: judul bab romawi; baris Mengetahui merentang dua kolom.
        $docx = WordKakTor::susun(DataPratinjau::untuk($kegiatan, null, self::ADMIN, ['kak']));
        $berkas = tempnam(sys_get_temp_dir(), 'docx');
        file_put_contents($berkas, $docx);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($berkas));
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();
        unlink($berkas);
        foreach (['I. PENDAHULUAN', 'II. KEGIATAN YANG DILAKSANAKAN', 'V. PENUTUP', 'Mengetahui', 'a. Penanggung Jawab'] as $teks) {
            $this->assertStringContainsString($teks, $xml);
        }
        $this->assertStringContainsString('<w:gridSpan w:val="2"/>', $xml);
    }

    public function test_cek_cetak_dan_checklist_tanpa_sasaran_jadwal(): void
    {
        $kegiatan = $this->kegiatanLimaBab();
        $cek = array_column(PemeriksaanCetak::periksa($kegiatan, 'lengkap')['cek'], 'kunci');
        $this->assertSame(['tujuan', 'tempat', 'rincian_rab', 'matrik'], $cek);
        $this->assertNotContains('sasaran', array_column(collect(ChecklistIsian::untuk($kegiatan))->firstWhere('kunci', 'identitas')['butir'], 'kunci'));
        $this->assertNotContains('jadwal', array_column(collect(ChecklistIsian::untuk($kegiatan))->firstWhere('kunci', 'matrik')['butir'], 'kunci'));

        $lama = app(KegiatanRepository::class)->temukan(2);
        $this->assertContains('jadwal', array_column(PemeriksaanCetak::periksa($lama, 'lengkap')['cek'], 'kunci'));
    }

    public function test_pilih_format_di_susun_dokumen_dan_format_seragam(): void
    {
        $this->withSession(['stub_role' => 'admin']);

        Livewire::test(SusunDokumen::class, ['kegiatan' => 1])
            ->assertSeeHtml('data-testid="format-kak" data-format="sembilan_bagian"')
            ->call('ubahFormat', 'lima_bab')
            ->assertDispatched('notify', message: 'Format KAK/TOR kini Format 5 bab — susunan dokumen menyesuaikan.', type: 'success')
            ->assertSeeHtml('data-testid="format-kak" data-format="lima_bab"')
            ->assertSee('Kegiatan yang Dilaksanakan')
            ->call('bukaIsian', 2)
            ->assertSet('isianTerbuka', 2)
            ->assertSeeHtml('data-testid="isian-bagian-2"')
            ->call('ubahFormat', '')
            ->assertSeeHtml('data-format="sembilan_bagian"')
            ->call('ubahFormat', 'empat_bab')
            ->assertNotFound();
        $this->assertArrayNotHasKey('format_dokumen', array_filter(app(KegiatanRepository::class)->temukan(1)));

        Livewire::test(FormatSeragam::class)
            ->set('tab', 'susunan')
            ->assertSeeHtml('data-testid="format-kak-tahun" data-format="sembilan_bagian"')
            ->set('formatTahun', 'lima_bab')
            ->assertDispatched('notify', message: 'Format KAK/TOR TA 2026 kini Format 5 bab — berlaku untuk kegiatan yang tidak memilih format sendiri.', type: 'success')
            ->assertSeeHtml('data-testid="format-kak-tahun" data-format="lima_bab"')
            ->assertSee('KAK/TOR — Format 5 bab, 5 bagian');
        $this->assertSame(FormatKakTor::LIMA_BAB, FormatKakTor::tahun(2026));
        $this->assertSame(['format' => 'lima_bab', 'label' => 'Format 5 bab'], array_intersect_key(AturanFormat::susunan(), ['format' => 1, 'label' => 1]));
        $this->assertSame(['I', 'II', 'III', 'IV', 'V'], array_column(AturanFormat::susunan()['kak'], 'label'));
    }
}
