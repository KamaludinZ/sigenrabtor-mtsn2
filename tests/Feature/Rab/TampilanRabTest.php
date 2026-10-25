<?php

namespace Tests\Feature\Rab;

use App\Contracts\KegiatanRepository;
use App\Livewire\Kegiatan\Rab;
use App\Support\Rab\AnggaranKegiatan;
use App\Support\Rab\TampilanRab;
use Livewire\Livewire;
use Tests\TestCase;

class TampilanRabTest extends TestCase
{
    private function tampilan(array $kegiatan, ?string $sumber = null, ?string $halaman = null): TampilanRab
    {
        return TampilanRab::tentukan(AnggaranKegiatan::untuk($kegiatan, sertakanTerlepas: true), $sumber, $halaman);
    }

    private function kegiatanDenganSumber(array $sumber): int
    {
        return app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Uji Tampilan', 'sumber_dana' => $sumber]);
    }

    public function test_mode_mengikuti_jumlah_sumber_dana(): void
    {
        $tanpa = $this->tampilan(['id' => 99, 'sumber_dana' => []]);
        $this->assertSame(['tanpa_sumber', [], null, []], [$tanpa->mode, $tanpa->sumber, $tanpa->aktif, $tanpa->halamanTersedia()]);

        $tunggal = $this->tampilan(app(KegiatanRepository::class)->temukan(1));
        $this->assertSame(['tunggal', ['Komite'], 'Komite'], [$tunggal->mode, $tunggal->sumber, $tunggal->aktif]);
        $this->assertSame(['rincian', 'rekap', 'termin'], array_keys($tunggal->halamanTersedia()));

        $ganda = $this->tampilan(app(KegiatanRepository::class)->temukan(2));
        $this->assertSame(['ganda', ['DIPA', 'Komite']], [$ganda->mode, $ganda->sumber]);
        $this->assertSame(['rincian', 'semua', 'rekap', 'termin'], array_keys($ganda->halamanTersedia()));
    }

    public function test_urutan_baku_dan_permintaan_tidak_sah_dinormalkan(): void
    {
        $kegiatan = ['id' => 99, 'sumber_dana' => [['sumber' => 'Komite', 'pagu' => 1], ['sumber' => 'DIPA', 'pagu' => 1]]];

        $this->assertSame(['DIPA', 'Komite'], $this->tampilan($kegiatan)->sumber, 'DIPA selalu didahulukan.');
        $this->assertSame('DIPA', $this->tampilan($kegiatan, 'BOS')->aktif);
        $this->assertSame('Komite', $this->tampilan($kegiatan, 'Komite')->aktif);
        $this->assertSame('rincian', $this->tampilan($kegiatan, null, 'tidak-ada')->halaman);
        $this->assertSame('rincian', $this->tampilan(['id' => 99, 'sumber_dana' => [['sumber' => 'DIPA', 'pagu' => 1]]], null, 'semua')->halaman);
    }

    public function test_rab_sumber_terlepas_tercatat_terpisah(): void
    {
        $repo = app(KegiatanRepository::class);
        $kegiatan = $repo->temukan(2);
        $kegiatan['sumber_dana'] = [['sumber' => 'Komite', 'pagu' => 2_500_000]];

        $tampilan = $this->tampilan($kegiatan, 'DIPA');
        $this->assertSame(['tunggal', ['Komite'], ['DIPA'], 'Komite'], [$tampilan->mode, $tampilan->sumber, $tampilan->terlepas, $tampilan->aktif]);
    }

    public function test_halaman_semua_rab_menampilkan_kedua_rab_baca_saja(): void
    {
        Livewire::test(Rab::class, ['kegiatan' => 2])
            ->assertSee('Semua RAB')
            ->set('halaman', 'semua')
            ->assertSet('halaman', 'semua')
            ->assertSeeHtml('data-testid="semua-rab"')
            ->assertSeeHtml('data-testid="tabel-rab-dipa"')
            ->assertSeeHtml('data-testid="tabel-rab-komite"')
            ->assertSee('Ubah RAB Komite')
            ->assertDontSee('Sedang dibuka')
            ->call('pilihSumber', 'Komite')
            ->assertSet('halaman', 'rincian')
            ->assertSet('sumber', 'Komite');
    }

    public function test_kegiatan_satu_sumber_tanpa_halaman_semua(): void
    {
        Livewire::withQueryParams(['halaman' => 'semua', 'sumber' => 'DIPA'])
            ->test(Rab::class, ['kegiatan' => 1])
            ->assertSet('halaman', 'rincian')
            ->assertSet('sumber', 'Komite')
            ->assertDontSee('Semua RAB')
            ->set('halaman', 'semua')
            ->assertSet('halaman', 'rincian');
    }

    public function test_url_sumber_dihormati_dan_urutan_tab_baku(): void
    {
        $id = $this->kegiatanDenganSumber([['sumber' => 'Komite', 'pagu' => 1_000_000], ['sumber' => 'DIPA', 'pagu' => 2_000_000]]);

        Livewire::withQueryParams(['sumber' => 'Komite'])
            ->test(Rab::class, ['kegiatan' => $id])
            ->assertSet('sumber', 'Komite')
            ->assertSeeInOrder(['RAB DIPA', 'RAB Komite']);

        // Tanpa ?sumber: sumber pertama menurut urutan baku (parameter kueri tes direset).
        Livewire::withQueryParams([])->test(Rab::class, ['kegiatan' => $id])->assertSet('sumber', 'DIPA');
    }

    public function test_isi_rab_terlepas_bisa_dilihat_baca_saja(): void
    {
        $repo = app(KegiatanRepository::class);
        $kegiatan = $repo->temukan(2);
        $kegiatan['sumber_dana'] = [['sumber' => 'Komite', 'pagu' => 2_500_000]];
        $repo->simpan($kegiatan, 2);

        Livewire::test(Rab::class, ['kegiatan' => 2])
            ->assertSeeHtml('data-testid="isi-rab-terlepas"')
            ->assertSee('Lihat isi RAB DIPA (baca-saja)')
            ->assertSeeHtml('data-testid="tabel-rab-dipa"')
            ->call('pilihSumber', 'DIPA')
            ->assertSet('sumber', 'Komite');
    }
}
