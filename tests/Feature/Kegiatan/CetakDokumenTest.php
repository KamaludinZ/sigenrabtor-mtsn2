<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Livewire\Kegiatan\PratinjauDokumen;
use App\Support\Dokumen\CetakDokumen;
use App\Support\Dokumen\StatusCetak;
use Carbon\CarbonImmutable;
use Livewire\Livewire;
use Tests\TestCase;

class CetakDokumenTest extends TestCase
{
    public function test_pilihan_lembar_nama_berkas_dan_tautan(): void
    {
        $this->assertSame(['cover', 'kak', 'rab'], CetakDokumen::pilihan(null));
        $this->assertSame(['cover', 'kak', 'rab'], CetakDokumen::pilihan('asing'));
        $this->assertSame(['kak', 'rab'], CetakDokumen::pilihan('rab, kak'), 'Urut cetak.');
        $this->assertSame(['cover'], CetakDokumen::pilihan(['cover', 'cover']));
        $this->assertSame(['RAB', 'KAK-TOR', 'KAK-TOR', 'KAK-TOR_RAB'], [CetakDokumen::jenisBerkas(['rab']), CetakDokumen::jenisBerkas(['kak']), CetakDokumen::jenisBerkas(['cover', 'kak']), CetakDokumen::jenisBerkas(['kak', 'rab'])]);

        $kegiatan = app(KegiatanRepository::class)->temukan(2);
        $cetak = StatusCetak::dari($kegiatan, 'DIPA');
        $this->assertSame($cetak->rab, CetakDokumen::statusDraf($cetak, ['rab']));
        $this->assertSame($cetak->kak, CetakDokumen::statusDraf($cetak, ['kak']));
        $this->assertSame($cetak->set, CetakDokumen::statusDraf($cetak, ['cover', 'kak']));
        $this->assertSame('KAK-TOR_RAB_Supervisi_Akademik_dan_Refleksi_Pembelajaran_DIPA_2026_DRAF.pdf', CetakDokumen::namaBerkas($cetak, ['cover', 'kak', 'rab']));
        $this->assertStringStartsWith('RAB_Supervisi_Akademik', CetakDokumen::namaBerkas($cetak, ['rab']));

        $draf = CetakDokumen::statusDraf($cetak, ['kak']);
        $this->assertSame('DRAF — dicetak Rabu, 28 Oktober 2026 pukul 09.15 · '.$draf->alasan[0]['teks'], CetakDokumen::kakiHalaman($draf, CarbonImmutable::parse('2026-10-28 09:15', config('app.timezone'))));

        $this->assertSame(route('kegiatan.cetak', ['kegiatan' => 2]), CetakDokumen::tautan(2, null, []));
        $this->assertSame(route('kegiatan.cetak', ['kegiatan' => 2, 'sumber' => 'Komite', 'dokumen' => 'kak,rab', 'otomatis' => 1]), CetakDokumen::tautan(2, 'Komite', ['rab', 'kak'], true));
    }

    public function test_halaman_cetak_tanpa_bingkai_aplikasi(): void
    {
        $respons = $this->get(route('kegiatan.cetak', ['kegiatan' => 2]))->assertOk();
        $html = $respons->getContent();

        $this->assertStringContainsString('<title>KAK-TOR_RAB_Supervisi_Akademik_dan_Refleksi_Pembelajaran_DIPA_2026_DRAF</title>', $html);
        $this->assertSame(3, substr_count($html, 'data-lembar-cetak="'));
        $this->assertStringContainsString('class="lembar lembar-cetak lembar-sampul" data-lembar-cetak="cover"', $html);
        $this->assertStringContainsString('data-testid="pratinjau-kak"', $html);
        $this->assertStringContainsString('data-testid="tabel-rab-dipa"', $html);
        $this->assertStringContainsString('size: A4;', $html);
        $this->assertStringContainsString('@bottom-left { content: "DRAF — dicetak', $html);
        $this->assertStringContainsString('"Halaman " counter(page) " dari " counter(pages)', $html);
        $this->assertStringContainsString('data-testid="tanda-air-cetak"', $html);
        $this->assertStringContainsString('data-testid="peringatan-draf-cetak"', $html);
        $this->assertStringContainsString('data-testid="tombol-cetak"', $html);
        $this->assertStringContainsString('href="'.route('kegiatan.pratinjau', ['kegiatan' => 2, 'sumber' => 'DIPA']).'"', $html);
        $this->assertStringNotContainsString('window.print(), 300', $html);
        $this->assertStringNotContainsString('Riwayat Saran AI', $html, 'Tanpa menu samping aplikasi.');
        $this->assertStringNotContainsString('wire:snapshot', $html);

        // Satu lembar & sumber lain; tombol lembar menautkan pilihan berikutnya; otomatis membuka dialog cetak.
        $rab = $this->get(route('kegiatan.cetak', ['kegiatan' => 2, 'sumber' => 'Komite', 'dokumen' => 'rab', 'otomatis' => 1]))->assertOk()->getContent();
        $this->assertSame(1, substr_count($rab, 'data-lembar-cetak="'));
        $this->assertStringContainsString('data-testid="tabel-rab-komite"', $rab);
        $this->assertMatchesRegularExpression('/<title>RAB_Supervisi_Akademik_dan_Refleksi_Pembelajaran_KOMITE_2026/', $rab);
        $this->assertStringContainsString('window.print(), 300', $rab);
        $this->assertStringContainsString('href="'.e(CetakDokumen::tautan(2, 'Komite', ['kak', 'rab'])).'" aria-pressed="false" data-lembar-pilihan="kak"', $rab);

        $this->get(route('kegiatan.cetak', ['kegiatan' => 2, 'sumber' => 'BOS']))->assertNotFound();
        $this->get(route('kegiatan.cetak', ['kegiatan' => 999]))->assertNotFound();
        $lain = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Lomba Kreativitas Siswa', 'leading' => '1.2', 'user_id' => 4, 'tahun_anggaran' => 2026]);
        $this->get(route('kegiatan.cetak', ['kegiatan' => $lain]))->assertForbidden();
    }

    public function test_tombol_cetak_dan_unduh_di_pratinjau(): void
    {
        $html = Livewire::test(PratinjauDokumen::class, ['kegiatan' => 2])->html();

        $this->assertStringContainsString('data-testid="panel-cetak"', $html);
        $this->assertSame(3, substr_count($html, 'data-cetak-lembar="'));
        $this->assertStringContainsString('data-testid="cetak-sekarang"', $html);
        $this->assertStringContainsString('data-testid="cetak-draf"', $html);
        $this->assertMatchesRegularExpression('#kegiatan\\\\?/2\\\\?/cetak\?sumber=DIPA#', $html, 'Dasar tautan cetak sumber dana yang tampil.');
        $this->assertStringContainsString('href="'.route('kegiatan.rab.excel', ['kegiatan' => 2, 'sumber' => 'DIPA']).'"', $html);
        $this->assertStringContainsString('href="'.route('kegiatan.rab.pdf', ['kegiatan' => 2, 'sumber' => 'DIPA']).'"', $html);
        $this->assertStringContainsString('href="'.e(CetakDokumen::tautan(2, 'DIPA', [], true)).'" target="_blank"', $html);
        $this->assertMatchesRegularExpression('/KAK\/TOR — Word \(\.docx\).*?SEGERA/is', $html);

        // RAB tanpa rincian: unduh RAB belum tersedia.
        $id = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Rapat Berdana', 'leading' => '1.6', 'user_id' => 1, 'tahun_anggaran' => 2026, 'sumber_dana' => [['sumber' => 'DIPA', 'pagu' => 1_000_000]]]);
        Livewire::test(PratinjauDokumen::class, ['kegiatan' => $id])
            ->assertSeeHtml('data-testid="unduh-rab-kosong"')
            ->assertDontSeeHtml('data-testid="unduh-rab-excel"');
    }
}
