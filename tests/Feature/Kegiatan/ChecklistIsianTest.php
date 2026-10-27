<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Contracts\RabRepository;
use App\Livewire\Forms\KegiatanForm;
use App\Livewire\Kegiatan\PratinjauDokumen;
use App\Support\Dokumen\ChecklistIsian;
use Livewire\Livewire;
use Tests\TestCase;

class ChecklistIsianTest extends TestCase
{
    /** @return array<string, bool> Kunci butir → terisi. */
    private function terisi(array $grup): array
    {
        return array_column(array_merge(...array_column($grup, 'butir')), 'terisi', 'kunci');
    }

    public function test_checklist_kegiatan_contoh_per_tempat_mengisi(): void
    {
        $grup = ChecklistIsian::untuk(app(KegiatanRepository::class)->temukan(1));

        $this->assertSame(['identitas', 'pelaksanaan', 'kode', 'anggaran', 'matrik', 'pengaturan'], array_column($grup, 'kunci'));
        $terisi = $this->terisi($grup);
        $this->assertTrue($terisi['tujuan'] && $terisi['tempat'] && $terisi['kode_sk'] && $terisi['rincian_Komite'] && $terisi['termin_Komite']);
        $this->assertFalse($terisi['kepala'], 'Contoh TA 2026: Kepala Madrasah belum diisi.');
        $this->assertFalse($terisi['sk_rktm']);
        $this->assertSame(0, ChecklistIsian::ringkasan($grup)['ajukan_kurang'], 'Contoh 1 memenuhi syarat pengajuan.');
        $this->assertSame(route('kegiatan.edit', ['kegiatan' => 1]).'#pelaksanaan', $grup[1]['tautan']);
        $this->assertNull($grup[5]['tautan'], 'Akun leading tidak diarahkan ke pengaturan admin.');

        session(['stub_role' => 'admin']);
        $this->assertSame(route('pengaturan.madrasah.index', ['tab' => 'pejabat']), ChecklistIsian::untuk(app(KegiatanRepository::class)->temukan(1))[5]['tautan']);
    }

    public function test_syarat_pengajuan_sama_dengan_form(): void
    {
        $grup = ChecklistIsian::untuk(['nama_kegiatan' => 'Rapat Kerja', 'kode_iksk' => 'IKSK 99.9']);
        $butir = collect(array_merge(...array_column($grup, 'butir')))->keyBy('kunci');

        $wajib = $butir->where('ajukan', true)->keys()->all();
        $this->assertSame(['nama_kegiatan', ...array_keys(KegiatanForm::PELAKSANAAN_WAJIB), ...array_keys(KegiatanForm::KODE_WAJIB)], $wajib);
        $this->assertSame(7, ChecklistIsian::ringkasan($grup)['ajukan_kurang']);
        $this->assertFalse($butir['sumber_dana']['terisi']);
        $this->assertSame('IKSK 99.9 tidak ada di master TA 2026.', $butir['kode_iksk']['keterangan']);

        // Termin yang tidak pas menjadi syarat pengajuan.
        app(RabRepository::class)->simpanTermin(2, 'DIPA', [['tanggal' => '2026-06-01', 'nominal' => 1_000_000]]);
        $termin = collect(array_merge(...array_column(ChecklistIsian::untuk(app(KegiatanRepository::class)->temukan(2)), 'butir')))->firstWhere('kunci', 'termin_DIPA');
        $this->assertSame([false, true], [$termin['terisi'], $termin['ajukan']]);
        $this->assertStringStartsWith('Termin kurang', $termin['keterangan']);
    }

    public function test_checklist_di_pratinjau(): void
    {
        Livewire::test(PratinjauDokumen::class, ['kegiatan' => 1])
            ->assertSeeHtml('data-testid="checklist-isian"')
            ->assertSee('Syarat pengajuan terpenuhi.')
            ->assertSeeHtml('data-grup="kode" data-kurang="0"')
            ->assertSeeHtml('data-butir="kepala" data-terisi="tidak"')
            ->assertSee('Diatur admin di Pengaturan.');

        $data = app(KegiatanRepository::class)->temukan(2);
        app(KegiatanRepository::class)->simpan(['tempat' => '', 'kode_sk' => null] + $data, 2);
        Livewire::test(PratinjauDokumen::class, ['kegiatan' => 2])
            ->assertSee('2 syarat pengajuan belum terpenuhi.')
            ->assertSeeHtml('data-butir="tempat" data-terisi="tidak"')
            ->assertSee('Wajib diajukan')
            ->assertSeeHtml('href="'.route('kegiatan.edit', ['kegiatan' => 2]).'#pelaksanaan"');
    }
}
