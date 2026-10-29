<?php

namespace Tests\Feature\Pengaturan;

use App\Livewire\Pengaturan\FormatSeragam;
use App\Support\Dokumen\FormatSeragam as AturanFormat;
use App\Support\Navigation;
use App\Support\Pengaturan\BagianPengaturan;
use Livewire\Livewire;
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
            ->assertSeeText('Master KAK/TOR Lomba Siswa')
            ->assertSeeHtml('data-testid="template-leading" data-leading="1.6"')
            ->set('tab', 'asing')
            ->assertSet('tab', 'ringkasan');
    }

    public function test_hanya_admin(): void
    {
        session(['stub_role' => 'leading']);
        $this->get(route('pengaturan.format.index'))->assertForbidden();
    }
}
