<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\KegiatanRepository;
use App\Contracts\PilihanTemplateRepository;
use App\Contracts\RiwayatPengaturanRepository;
use App\Contracts\TemplateCoverRepository;
use App\Contracts\TemplateDokumenRepository;
use App\Livewire\Pengaturan\FormatSeragam;
use App\Support\Dokumen\FormatSeragam as AturanFormat;
use App\Support\Dokumen\PilihanTemplate;
use App\Support\Dokumen\TeksCover;
use App\Support\Navigation;
use App\Support\Pengaturan\BagianPengaturan;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\Docx;
use Tests\TestCase;

class FormatSeragamTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        session(['stub_role' => 'admin']);
    }

    public function test_aturan_nomor_dan_susunan_baku(): void
    {
        $this->assertSame('KAK-TOR/032/MTsN2KotaMalang/III/2026', AturanFormat::nomor('{jenis}/{urut}/{madrasah}/{bulan_romawi}/{tahun}', 32, ['jenis' => 'KAK+TOR', 'bulan' => 3, 'tahun' => 2026]));
        $this->assertSame('RAB/0007/DIPA/1.6/2026', AturanFormat::nomor('RAB/{urut}/{sumber}/{leading}/{tahun}', 7, ['sumber' => 'DIPA', 'leading' => '1.6', 'tahun' => 2026], 4));
        $this->assertSame('KAK/012/12/2026', AturanFormat::nomor('KAK/{urut}/{bulan}/{tahun}', 12, ['bulan' => 12, 'tahun' => 2026]));
        $this->assertSame('DOK/001/2026', AturanFormat::nomor('DOK/{urut}/{leading}/{tahun}', 1, ['tahun' => 2026]), 'Penanda kosong tidak meninggalkan garis miring ganda.');
        $this->assertSame(['I', 'IV', 'IX', 'XII', ''], array_map(AturanFormat::bulanRomawi(...), [1, 4, 9, 12, 13]));

        $this->assertNull(AturanFormat::galatPola('KAK/{urut}/{tahun}'));
        $this->assertSame('Pola nomor wajib diisi.', AturanFormat::galatPola(' '));
        $this->assertStringContainsString('{urut}', (string) AturanFormat::galatPola('KAK/{tahun}'));
        $this->assertSame('Penanda {nama} tidak dikenal.', AturanFormat::galatPola('KAK/{urut}/{nama}'));
        $this->assertStringContainsString('paling banyak', (string) AturanFormat::galatPola('{urut}'.str_repeat('X', 80)));

        $susunan = AturanFormat::susunan();
        $this->assertSame(range(1, 9), array_column($susunan['kak'], 'nomor'));
        $this->assertSame(['Latar Belakang', 'Lembar Pengesahan'], [$susunan['kak'][0]['judul'], $susunan['kak'][8]['judul']]);
        $this->assertSame(['No', 'Kode', 'Uraian', 'Vol', 'Satuan', 'Harga Satuan (Rp)', 'Jumlah (Rp)'], $susunan['rab_kolom']);
        $this->assertContains('Jumlah yang diajukan', $susunan['rab_kepala']);
    }

    public function test_halaman_format_seragam_untuk_admin(): void
    {
        $this->get(route('pengaturan.format.index'))->assertOk()->assertSee('Format Seragam');
        $this->assertTrue(BagianPengaturan::cari('format')['tersedia']);
        $menu = collect(Navigation::groups('admin'))->flatMap(fn (array $g) => $g['items'])->firstWhere('route', 'pengaturan.format.index');
        $this->assertTrue($menu['available']);

        Livewire::test(FormatSeragam::class)
            ->assertSet('tab', 'ringkasan')
            ->assertSeeHtml('data-unsur="kop" data-status="perlu"')
            ->assertSeeText('Belum diisi: alamat, telepon, logo.')
            ->assertSeeHtml('data-unsur="susunan" data-status="siap"')
            ->assertSeeText('9 bagian KAK/TOR · 7 kolom RAB, urutan terkunci.')
            ->assertSeeText('4 dari 5 jenis dokumen bernomor otomatis.')
            ->assertSee('data-testid="kop-format"', false)
            ->set('tab', 'susunan')
            ->assertSeeHtml('data-bagian="9"')
            ->assertSeeText('Pembiayaan')
            ->set('tab', 'penomoran')
            ->assertSeeHtml('data-testid="aturan-nomor" data-jenis="RAB"')
            ->assertSeeText('RAB/013/DIPA/1.1/2026')
            ->assertSeeText('{bulan_romawi}')
            ->set('tab', 'template')
            ->assertSeeHtml('data-testid="template-jenis" data-jenis="lomba"')
            ->assertSee('data-testid="template-kosong"', false)
            ->assertSee('data-testid="template-leading-kosong"', false)
            ->set('tab', 'asing')
            ->assertSet('tab', 'ringkasan');
    }

    /** @return array{kak: string, kak2: string, cover: string} id template contoh TA 2026. */
    private function templateContoh(): array
    {
        $docx = fn (string $nama) => UploadedFile::fake()->createWithContent($nama, Docx::isi(['KAK ${nama_kegiatan}', 'Total ${total}']));

        return [
            'kak' => app(TemplateDokumenRepository::class)->tambah(2026, 'Master Workshop', 'KAK+TOR', $docx('workshop.docx'), ['nama_kegiatan', 'total']),
            'kak2' => app(TemplateDokumenRepository::class)->tambah(2026, 'Master Lomba', 'TOR', $docx('lomba.docx'), ['nama_kegiatan']),
            'cover' => app(TemplateCoverRepository::class)->tambah(2026, 'Cover Kesiswaan', new UploadedFile(base_path('tests/fixtures/cover-contoh.png'), 'cover.png', 'image/png', null, true), TeksCover::bawaan()),
        ];
    }

    public function test_aturan_pilihan_template(): void
    {
        $t = $this->templateContoh();
        $this->assertSame(['Master Workshop', 'Master Lomba'], array_column(PilihanTemplate::pilihanKak(2026), 'nama'));
        $this->assertSame('Master Workshop · KAK+TOR (default)', PilihanTemplate::pilihanKak(2026)[0]['label']);
        $this->assertSame(['Cover Kesiswaan (default)'], array_column(PilihanTemplate::pilihanCover(2026), 'label'));

        $rapi = PilihanTemplate::rapikan(['jenis' => ['workshop' => ['kak' => " {$t['kak']} ", 'cover' => ''], 'asing' => ['kak' => 'x']], 'leading' => [['leading' => '', 'jenis' => '', 'kak' => '', 'cover' => ''], ['leading' => '1.6', 'jenis' => 'supervisi', 'kak' => $t['kak2'], 'cover' => '']]]);
        $this->assertSame(['kak' => $t['kak'], 'cover' => null], $rapi['jenis']['workshop']);
        $this->assertArrayNotHasKey('asing', $rapi['jenis']);
        $this->assertCount(1, $rapi['leading'], 'Baris leading kosong dibuang.');
        $this->assertSame([], PilihanTemplate::galat($rapi, 2026));

        $galat = PilihanTemplate::galat(PilihanTemplate::rapikan(['jenis' => ['lomba' => ['kak' => 'tidak-ada', 'cover' => $t['kak']]], 'leading' => [
            ['leading' => '9.9', 'jenis' => 'aneh', 'kak' => null, 'cover' => null],
            ['leading' => '1.6', 'jenis' => 'lomba', 'kak' => $t['kak'], 'cover' => null],
            ['leading' => '1.6', 'jenis' => 'lomba', 'kak' => null, 'cover' => $t['cover']],
        ]]), 2026);
        $this->assertSame(['jenis.lomba.kak', 'jenis.lomba.cover', 'leading.0.leading', 'leading.0.jenis', 'leading.0.kak', 'leading.2.jenis'], array_keys($galat));
        $this->assertSame('Leading & jenis kegiatan ini sudah diatur di baris 2.', $galat['leading.2.jenis']);

        $admin = ['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin'];
        $hasil = PilihanTemplate::simpan(2026, ['jenis' => ['workshop' => ['kak' => $t['kak'], 'cover' => $t['cover']]], 'leading' => [['leading' => '1.6', 'jenis' => 'supervisi', 'kak' => $t['kak2'], 'cover' => '']]], $admin);
        $this->assertSame('Workshop / pelatihan guru: KAK/TOR susunan baku → Master Workshop; Workshop / pelatihan guru: cover default → Cover Kesiswaan; khusus leading 1.6 · Supervisi / evaluasi / rapat kerja ditambah', $hasil['ringkasan']);
        $this->assertSame($t['kak'], app(PilihanTemplateRepository::class)->untuk(2026)['jenis']['workshop']['kak']);
        $riwayat = collect(app(RiwayatPengaturanRepository::class)->semua())->firstWhere('aksi', 'ubah_pilihan_template');
        $this->assertStringStartsWith('TA 2026 — Workshop / pelatihan guru: KAK/TOR susunan baku → Master Workshop', $riwayat['keterangan']);
        $this->assertNull(PilihanTemplate::simpan(2026, $hasil['pilihan'], $admin)['ringkasan'], 'Tanpa perubahan tidak dicatat.');

        $kegiatan = app(KegiatanRepository::class)->temukan(1);
        $this->assertSame(['jenis' => 'workshop', 'asal' => 'jenis', 'kak' => $t['kak'], 'cover' => $t['cover']], PilihanTemplate::untukKegiatan($kegiatan), 'Workshop Penyusunan Kurikulum.');
        $supervisi = app(KegiatanRepository::class)->temukan(2);
        $this->assertSame(['jenis' => 'supervisi', 'asal' => 'leading', 'kak' => $t['kak2'], 'cover' => null], PilihanTemplate::untukKegiatan($supervisi));
        $this->assertSame('bawaan', PilihanTemplate::untukKegiatan(['nama_kegiatan' => 'Lomba Kreativitas', 'leading' => '1.2'])['asal']);

        app(TemplateDokumenRepository::class)->hapus($t['kak']);
        $data = PilihanTemplate::data(2026);
        $this->assertTrue(collect($data['jenis'])->firstWhere('kunci', 'workshop')['kak_hilang'], 'Template terhapus ditandai.');

        try {
            PilihanTemplate::simpan(2026, [], ['role' => 'leading']);
            $this->fail('Hanya admin.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }
        $this->expectException(ValidationException::class);
        PilihanTemplate::simpan(2026, ['leading' => [['leading' => '1.6']]], $admin);
    }

    public function test_formulir_pilihan_template(): void
    {
        $t = $this->templateContoh();
        Livewire::test(FormatSeragam::class, ['tab' => 'template'])
            ->set('tab', 'template')
            ->assertDontSee('data-testid="template-kosong"', false)
            ->assertSeeHtml('<option value="'.$t['kak'].'">Master Workshop · KAK+TOR (default)</option>')
            ->assertSeeHtml('data-berubah="0"')
            ->set('pilihan.jenis.workshop.kak', $t['kak'])
            ->assertSeeHtml('data-berubah="1"')
            ->assertSee('data-testid="template-belum-disimpan"', false)
            ->call('tambahLeading')
            ->assertSee('data-testid="pilih-leading-0"', false)
            ->set('pilihan.leading.0.leading', '1.6')
            ->call('simpanTemplate')
            ->assertHasErrors(['pilihan.leading.0.jenis', 'pilihan.leading.0.kak'])
            ->assertSee('data-testid="pilih-kak-leading-0-galat"', false)
            ->assertDispatched('notify', type: 'error')
            ->set('pilihan.leading.0.jenis', 'supervisi')
            ->set('pilihan.leading.0.cover', $t['cover'])
            ->call('simpanTemplate')
            ->assertHasNoErrors()
            ->assertDispatched('notify', type: 'success')
            ->assertSeeHtml('data-berubah="0"')
            ->assertSee('data-testid="template-tersimpan"', false)
            ->call('hapusLeading', 0)
            ->assertSee('data-testid="template-leading-kosong"', false)
            ->call('batalTemplate')
            ->assertSeeHtml('data-testid="template-leading" data-leading="1.6"');
        $this->assertSame($t['cover'], app(PilihanTemplateRepository::class)->untuk(2026)['leading'][0]['cover']);
    }

    public function test_pratinjau_dengan_template_terpilih(): void
    {
        $komponen = Livewire::test(FormatSeragam::class)->set('tab', 'template');
        $komponen->assertSeeHtml('data-testid="asal-pratinjau-template" data-asal="bawaan" data-jenis="workshop"')
            ->assertSeeText('cover default')
            ->assertSeeText('susunan baku aplikasi (bagian 1–3)')
            ->assertSeeText('Latar Belakang')
            ->assertDontSee('data-testid="isi-master"', false);

        $t = $this->templateContoh();
        $komponen = Livewire::test(FormatSeragam::class)->set('tab', 'template')
            ->set('pilihan.jenis.workshop.kak', $t['kak'])
            ->set('pilihan.jenis.workshop.cover', $t['cover'])
            ->assertSeeHtml('data-testid="asal-pratinjau-template" data-asal="jenis" data-jenis="workshop"')
            ->assertSeeText('Master Workshop · KAK+TOR')
            ->assertSeeText('Cover Kesiswaan')
            ->assertSee('data-testid="isi-master" data-mode="isi"', false)
            ->assertSeeHtml('data-isi="nama_kegiatan"')
            ->assertSeeText('Workshop Penyusunan Kurikulum Madrasah dan Perangkat Pembelajaran')
            ->assertSeeHtml('/dokumen/cover/'.$t['cover'].'/gambar');
        $this->assertNull(app(PilihanTemplateRepository::class)->untuk(2026)['jenis']['workshop']['kak'] ?? null, 'Pratinjau memakai pilihan yang belum disimpan.');

        $komponen->set('contohKegiatan', '2')
            ->assertSet('contohSumber', '')
            ->assertSeeHtml('data-asal="bawaan" data-jenis="supervisi"')
            ->set('contohSumber', 'Komite')
            ->assertSee('Sumber dana pratinjau', false)
            ->set('pilihan.leading', [['leading' => '1.6', 'jenis' => 'supervisi', 'kak' => $t['kak2'], 'cover' => '']])
            ->assertSeeHtml('data-asal="leading" data-jenis="supervisi"')
            ->assertSeeText('Master Lomba · TOR');
    }

    public function test_hanya_admin(): void
    {
        session(['stub_role' => 'leading']);
        $this->get(route('pengaturan.format.index'))->assertForbidden();
    }
}
