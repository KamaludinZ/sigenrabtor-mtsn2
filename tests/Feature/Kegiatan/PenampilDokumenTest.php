<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Livewire\Kegiatan\PratinjauDokumen;
use App\Support\Dokumen\PenampilDokumen;
use App\Support\Dokumen\SusunanKak;
use App\Support\Dokumen\SusunanRab;
use Livewire\Livewire;
use Tests\TestCase;

class PenampilDokumenTest extends TestCase
{
    public function test_daftar_isi_dokumen_dan_isian_kurang(): void
    {
        $kegiatan = app(KegiatanRepository::class)->temukan(2);
        $kak = SusunanKak::dari($kegiatan);
        $isi = PenampilDokumen::isi($kak, SusunanRab::dari($kegiatan));

        $this->assertSame(['cover', 'kak-1', 'kak-2', 'kak-3', 'kak-4', 'kak-5', 'kak-6', 'kak-7', 'kak-8', 'kak-9', 'rab'], array_column($isi, 'kunci'));
        $this->assertSame(['cover', 'kak', 'kak', 'kak', 'kak', 'kak', 'kak', 'kak', 'kak', 'kak', 'rab'], array_column($isi, 'lembar'));
        $this->assertSame(['Cover', '1. Latar Belakang', '7. Jadwal Kegiatan', 'RAB DIPA'], [$isi[0]['label'], $isi[1]['label'], $isi[7]['label'], $isi[10]['label']]);
        $this->assertSame(['dok-cover', 'cetak-kak-bagian-4', 'dok-rab'], [$isi[0]['jangkar'], $isi[4]['jangkar'], $isi[10]['jangkar']]);
        foreach ($kak->bagian as $i => $b) {
            $this->assertSame(count($kak->kurangPerBagian[$b['nomor']] ?? []), $isi[$i + 1]['kurang'], "Bagian {$b['nomor']}");
        }
        $this->assertGreaterThan(0, $isi[4]['kurang'] + $isi[7]['kurang'], 'Contoh 2 belum punya matrik/jadwal lengkap.');
        $this->assertSame('RAB Komite', PenampilDokumen::isi(SusunanKak::dari($kegiatan, 'Komite'), SusunanRab::dari($kegiatan, 'Komite'))[10]['label']);
        $this->assertArrayHasKey(PenampilDokumen::BAWAAN, PenampilDokumen::PERBESARAN);
        $this->assertSame(['cover', 'kak', 'rab'], array_keys(PenampilDokumen::LEMBAR));
    }

    public function test_penampil_lembar_a4_di_tab_pratinjau(): void
    {
        $halaman = Livewire::test(PratinjauDokumen::class, ['kegiatan' => 2]);
        $html = $halaman->html();

        $this->assertStringContainsString('x-data="penampilDokumen(', $html);
        $this->assertSame(3, substr_count($html, 'data-lembar="'));
        foreach (['data-lembar="cover"', 'data-lembar="kak"', 'data-lembar="rab"', 'data-testid="kendali-penampil"', 'data-testid="daftar-isi-pratinjau"', 'data-testid="pilihan-perbesaran"', 'data-testid="penunjuk-halaman"', 'data-testid="mode-pratinjau"'] as $penanda) {
            $this->assertStringContainsString($penanda, $html, $penanda);
        }
        $this->assertSame(11, substr_count($html, 'data-isi="'));
        $this->assertSame(count(PenampilDokumen::PERBESARAN), substr_count($html, 'data-pilihan-perbesaran="'));
        foreach (PenampilDokumen::isi($halaman->instance()->kak, $halaman->instance()->rab) as $butir) {
            $this->assertStringContainsString('id="'.$butir['jangkar'].'"', $html, "Jangkar {$butir['jangkar']} ada di dokumen.");
        }
        // Tata letak kertas mengikuti ukuran cetak di dalam lembar A4 (varian cetak:), bukan lebar layar.
        $this->assertStringContainsString('cetak:px-14', $html);
        $this->assertStringContainsString('cetak:grid-cols-3', $html);
        // Mode sunting menyusul.
        $this->assertMatchesRegularExpression('/aria-pressed="false" disabled[^>]*>.*?Sunting teks/s', $html);

        // Ganti sumber dana: daftar isi mengikuti RAB sumber itu.
        $halaman->set('sumber', 'Komite')->assertSeeHtml('data-isi="rab" data-kurang="0"')->assertSee('RAB Komite');
    }
}
