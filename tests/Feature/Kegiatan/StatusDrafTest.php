<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Livewire\Kegiatan\PratinjauDokumen;
use App\Support\Dokumen\StatusDraf;
use App\Support\Dokumen\SusunanKak;
use App\Support\Dokumen\SusunanRab;
use App\Support\Rab\AnggaranKegiatan;
use App\Support\Rab\PeringatanPagu;
use Livewire\Livewire;
use Tests\TestCase;

class StatusDrafTest extends TestCase
{
    public function test_alasan_cap_draf(): void
    {
        $komite = AnggaranKegiatan::perSumber(app(KegiatanRepository::class)->temukan(3))['Komite'];
        $status = StatusDraf::hitung(StatusKegiatan::Diperiksa, ['tujuan kegiatan', 'sasaran kegiatan', 'tempat', 'tanggal dokumen'], PeringatanPagu::untuk('Komite', $komite->hasil));

        $this->assertTrue($status->draf);
        $this->assertSame([
            ['kunci' => 'status', 'teks' => 'Belum disetujui admin (status Diperiksa).'],
            ['kunci' => 'kurang', 'teks' => '4 isian belum lengkap: tujuan kegiatan, sasaran kegiatan, tempat, dan 1 lainnya.'],
            ['kunci' => 'pagu', 'teks' => 'RAB Komite melebihi pagu Rp450.000.'],
        ], $status->alasan);

        $siap = StatusDraf::hitung(StatusKegiatan::Disetujui, []);
        $this->assertSame([false, []], [$siap->draf, $siap->alasan]);
        $this->assertFalse(StatusDraf::hitung(StatusKegiatan::Final, [])->draf);

        $gabung = StatusDraf::gabung($siap, StatusDraf::hitung(StatusKegiatan::Disetujui, ['rincian belanja RAB DIPA']), StatusDraf::hitung(StatusKegiatan::Disetujui, ['rincian belanja RAB DIPA']));
        $this->assertTrue($gabung->draf);
        $this->assertCount(1, $gabung->alasan, 'Alasan yang sama dari beberapa dokumen tidak dobel.');
    }

    public function test_dokumen_memakai_aturan_yang_sama(): void
    {
        $kegiatan = app(KegiatanRepository::class)->temukan(3);

        foreach ([SusunanKak::dari($kegiatan, 'Komite'), SusunanRab::dari($kegiatan, 'Komite')] as $susunan) {
            $this->assertSame($susunan->draf, $susunan->statusDraf->draf);
            $this->assertContains('pagu', array_column($susunan->statusDraf->alasan, 'kunci'));
        }
    }

    public function test_tampilan_draf_di_pratinjau(): void
    {
        Livewire::test(PratinjauDokumen::class, ['kegiatan' => 3])
            ->set('sumber', 'Komite')
            ->assertSeeHtml('data-testid="keadaan-cetak" data-draf="ya"')
            ->assertSee('Mengapa tercetak sebagai DRAF?')
            ->assertSeeHtml('data-alasan="status"')
            ->assertSeeHtml('data-alasan="pagu"')
            ->assertSee('RAB Komite melebihi pagu Rp450.000.')
            ->assertSeeHtml('data-testid="kepala-draf" data-draf="ya"')
            ->assertSeeHtml('data-testid="cap-draf"')
            ->assertSeeHtml('href="'.route('kegiatan.rab', ['kegiatan' => 3, 'sumber' => 'Komite']).'"');

        // Cover ikut bercap DRAF bersama KAK/TOR.
        $this->assertSame(3, substr_count(Livewire::test(PratinjauDokumen::class, ['kegiatan' => 3])->html(), 'data-testid="cap-draf"'));
    }
}
