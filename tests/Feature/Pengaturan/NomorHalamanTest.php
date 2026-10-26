<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\NomorHalamanRepository;
use App\Livewire\Pengaturan\TemplateDokumen;
use App\Livewire\Pengaturan\TemplateNomorHalaman;
use App\Support\Dokumen\NomorHalaman;
use Livewire\Livewire;
use Tests\TestCase;

class NomorHalamanTest extends TestCase
{
    public function test_format_diperiksa_dan_aturan_dirapikan(): void
    {
        $this->assertNull(NomorHalaman::galatFormat('Halaman {x} dari {y}'));
        $this->assertNull(NomorHalaman::galatFormat('{x}'));
        $this->assertSame('Format harus memuat {x} (nomor halaman).', NomorHalaman::galatFormat('Halaman'));
        $this->assertSame('Hanya penanda {x} dan {y} yang dikenal.', NomorHalaman::galatFormat('Hal {x} {z}'));
        $this->assertSame('Isi format nomor halaman.', NomorHalaman::galatFormat('  '));
        $this->assertSame('Format maksimal 40 karakter.', NomorHalaman::galatFormat(str_repeat('a', 41).'{x}'));

        $this->assertSame(NomorHalaman::BAWAAN, NomorHalaman::rapikan(NomorHalaman::BAWAAN));
        $rapi = NomorHalaman::rapikan(['format' => 'tanpa penanda', 'posisi' => 'tengah', 'mulai_dari' => '0', 'sampul_tanpa_nomor' => 0]);
        $this->assertSame(['Halaman {x} dari {y}', 'bawah_tengah', 1, false], [$rapi['format'], $rapi['posisi'], $rapi['mulai_dari'], $rapi['sampul_tanpa_nomor']]);
        $this->assertSame('Halaman 3 dari 18', NomorHalaman::label('Halaman {x} dari {y}', 3, 18));
    }

    public function test_simulasi_nomor_dan_halaman_awal_kegiatan(): void
    {
        $bawaan = NomorHalaman::simulasi(NomorHalaman::BAWAAN, ['A', 'B']);
        $this->assertSame([18, [3, 11]], [$bawaan['terakhir'], $bawaan['mulai']]);
        $this->assertSame([null, 'Halaman 2 dari 18', null, 'Halaman 4 dari 18'], array_column(array_slice($bawaan['halaman'], 0, 4), 'label'));
        $this->assertSame('Halaman 18 dari 18', end($bawaan['halaman'])['label']);

        // Halaman tanpa nomor tidak dihitung: daftar isi = 1, kegiatan pertama mulai di KAK halaman 2.
        $tanpaHitung = NomorHalaman::simulasi(['hitung_tanpa_nomor' => false] + NomorHalaman::BAWAAN, ['A', 'B']);
        $this->assertSame([15, [2, 9]], [$tanpaHitung['terakhir'], $tanpaHitung['mulai']]);

        // Mulai dari 5, semua halaman bernomor, format sederhana.
        $semua = NomorHalaman::simulasi(['format' => '{x}', 'mulai_dari' => 5, 'sampul_tanpa_nomor' => false] + NomorHalaman::BAWAAN, ['A']);
        $this->assertSame(['5', '6', '7'], array_column(array_slice($semua['halaman'], 0, 3), 'label'));
        $this->assertSame([7], $semua['mulai']);
    }

    public function test_tab_nomor_halaman_simulasi_dan_simpan(): void
    {
        Livewire::withQueryParams(['tab' => 'nomor_halaman'])->test(TemplateDokumen::class)
            ->assertSet('tab', 'nomor_halaman')
            ->assertSeeLivewire(TemplateNomorHalaman::class)
            ->assertDontSee('Segera');

        Livewire::test(TemplateNomorHalaman::class)
            ->assertSet('pilihanFormat', 'Halaman {x} dari {y}')
            ->assertSee('Tim Penjamin Mutu · Komite · 3 kegiatan · 26 halaman')
            ->assertSeeHtml('data-halaman="2" data-jenis="daftar_isi" data-label="Halaman 2 dari 26"')
            ->assertSeeHtml('data-halaman="1" data-jenis="cover_buku" data-label=""')
            ->assertSeeHtml('data-mulai="0">3</span>')
            ->assertSeeHtml('wire:key="simpan-nomor-terkunci" disabled')
            ->set('pilihanFormat', '- {x} -')
            ->set('aturan.hitung_tanpa_nomor', false)
            ->assertSeeHtml('data-testid="nomor-berubah"')
            ->assertSeeHtml('data-jenis="daftar_isi" data-label="- 1 -"')
            ->assertSeeHtml('data-mulai="0">2</span>')
            ->call('simpan')
            ->assertHasNoErrors()
            ->assertDispatched('notify', message: 'Aturan nomor halaman tersimpan.')
            ->assertDontSeeHtml('data-testid="nomor-berubah"');

        $tersimpan = app(NomorHalamanRepository::class)->untuk(2026);
        $this->assertSame(['- {x} -', false], [$tersimpan['format'], $tersimpan['hitung_tanpa_nomor']]);
        $this->assertSame(NomorHalaman::BAWAAN, app(NomorHalamanRepository::class)->untuk(2025), 'Per tahun anggaran.');
    }

    public function test_format_sendiri_divalidasi(): void
    {
        Livewire::test(TemplateNomorHalaman::class)
            ->set('pilihanFormat', TemplateNomorHalaman::FORMAT_LAIN)
            ->assertSeeHtml('id="format-lain"')
            ->set('formatLain', 'Halaman')
            ->assertHasErrors(['formatLain'])
            ->assertSee('Format harus memuat {x} (nomor halaman).')
            ->call('simpan')
            ->assertHasErrors(['formatLain'])
            ->assertNotDispatched('notify')
            ->set('formatLain', 'Lembar {x}')
            ->assertHasNoErrors()
            ->assertSeeHtml('data-label="Lembar 2"')
            ->call('simpan')
            ->assertHasNoErrors();

        $this->assertSame('Lembar {x}', app(NomorHalamanRepository::class)->untuk(2026)['format']);
        Livewire::test(TemplateNomorHalaman::class)
            ->assertSet('pilihanFormat', TemplateNomorHalaman::FORMAT_LAIN)
            ->assertSet('formatLain', 'Lembar {x}')
            ->call('pakaiAturanAplikasi')
            ->assertSet('pilihanFormat', 'Halaman {x} dari {y}')
            ->assertSeeHtml('data-testid="nomor-berubah"');
    }
}
