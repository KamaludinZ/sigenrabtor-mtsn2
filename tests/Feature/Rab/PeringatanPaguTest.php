<?php

namespace Tests\Feature\Rab;

use App\Contracts\KegiatanRepository;
use App\Livewire\Kegiatan\Rab;
use App\Support\Rab\AnggaranKegiatan;
use App\Support\Rab\HasilRab;
use App\Support\Rab\PeringatanPagu;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Komponen visual peringatan status pagu (PRD F12): melebihi, pagu belum diisi, dan di bawah pagu.
 */
class PeringatanPaguTest extends TestCase
{
    private function hasil(int $kegiatanId, string $sumber): HasilRab
    {
        return AnggaranKegiatan::perSumber(app(KegiatanRepository::class)->temukan($kegiatanId))[$sumber]->hasil;
    }

    public function test_isi_peringatan_per_kondisi_pagu(): void
    {
        $melebihi = PeringatanPagu::untuk('Komite', $this->hasil(3, 'Komite'));
        $this->assertSame(['bahaya', 'RAB Komite melebihi pagu Rp450.000'], [$melebihi->nada(), $melebihi->judul()]);
        $this->assertSame('Total RAB Rp7.950.000 dari pagu Rp7.500.000 (106%). Kurangi volume atau harga satuan, atau hapus item, sampai total tidak melebihi pagu sebelum diajukan.', $melebihi->pesan());
        $this->assertSame([
            ['uraian' => 'Honorarium narasumber penyusunan RKTM', 'jumlah' => 1_800_000],
            ['uraian' => 'Nasi kotak rapat kerja (30 orang x 2 hari)', 'jumlah' => 1_800_000],
            ['uraian' => 'Penyelesaian tugas penyusunan RKTM di luar jam kerja (10 orang x 3 kali)', 'jumlah' => 1_500_000],
        ], $melebihi->itemTerbesar());

        $tanpaPagu = PeringatanPagu::untuk('DIPA', $this->hasil(3, 'DIPA'));
        $this->assertSame(['perhatian', 'Pagu DIPA belum diisi', []], [$tanpaPagu->nada(), $tanpaPagu->judul(), $tanpaPagu->itemTerbesar()]);
        $this->assertSame('Total RAB Rp900.000 belum bisa dibandingkan dengan pagu. Isi pagu DIPA di Data Kegiatan sesuai Cascade Perkin.', $tanpaPagu->pesan());

        $sisa = PeringatanPagu::untuk('DIPA', $this->hasil(2, 'DIPA'));
        $this->assertSame(['info', 'RAB DIPA di bawah pagu — sisa Rp600.000'], [$sisa->nada(), $sisa->judul()]);
        $this->assertSame('Total RAB Rp5.400.000 dari pagu Rp6.000.000 (90%). Pastikan tidak ada belanja yang terlewat; sisa pagu tidak ikut diajukan.', $sisa->pesan());
        $this->assertSame([], $sisa->itemTerbesar());
    }

    public function test_tanpa_peringatan_bila_sesuai_pagu_atau_rab_kosong(): void
    {
        $this->assertNull(PeringatanPagu::untuk('Komite', $this->hasil(1, 'Komite')));
        $this->assertNull(PeringatanPagu::untuk('DIPA', new HasilRab([], 0, [], [], 6_000_000)));
        $this->assertNull(PeringatanPagu::untuk('DIPA', new HasilRab([], 0, [], [], null)));
    }

    public function test_peringatan_tampil_di_rincian_rab_dengan_aksi(): void
    {
        Livewire::test(Rab::class, ['kegiatan' => 3])
            ->assertSeeHtml('data-testid="peringatan-pagu-dipa" data-status="tanpa_pagu"')
            ->assertSeeHtml('role="status"')
            ->assertSee('Pagu DIPA belum diisi')
            ->assertSee('Isi pagu DIPA')
            ->assertSeeHtml('href="'.route('kegiatan.edit', ['kegiatan' => 3]).'#sumber-dana"')
            ->call('pilihSumber', 'Komite')
            ->assertSeeHtml('role="alert"')
            ->assertSeeHtml('data-testid="peringatan-pagu-komite" data-status="melebihi"')
            ->assertSeeInOrder(['RAB Komite melebihi pagu Rp450.000', '(106%)', 'Item terbesar', 'Honorarium narasumber penyusunan RKTM', 'Rp1.800.000', 'Lihat Pagu &amp; RAB', 'Periksa pagu Komite'], false)
            ->assertDontSeeHtml('data-testid="peringatan-pagu-dipa"');

        Livewire::test(Rab::class, ['kegiatan' => 1])->assertDontSeeHtml('data-testid="peringatan-pagu-');
    }

    public function test_semua_rab_memakai_peringatan_ringkas(): void
    {
        Livewire::test(Rab::class, ['kegiatan' => 3])
            ->set('halaman', 'semua')
            ->assertSeeHtml('data-testid="peringatan-pagu-dipa"')
            ->assertSeeHtml('data-testid="peringatan-pagu-komite"')
            ->assertSee('RAB Komite melebihi pagu Rp450.000')
            ->assertDontSeeHtml('data-testid="item-terbesar"');
    }
}
