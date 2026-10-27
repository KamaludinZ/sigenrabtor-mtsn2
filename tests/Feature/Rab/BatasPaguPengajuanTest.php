<?php

namespace Tests\Feature\Rab;

use App\Contracts\KegiatanRepository;
use App\Livewire\Kegiatan\Form;
use App\Livewire\Pemeriksaan\Periksa;
use App\Support\Dokumen\ChecklistIsian;
use App\Support\Rab\AnggaranKegiatan;
use App\Support\Rab\BatasPagu;
use Livewire\Livewire;
use Tests\TestCase;

/** PRD aturan 5: RAB yang melebihi pagu tidak bisa diajukan maupun disetujui; selama menyusun hanya peringatan. */
class BatasPaguPengajuanTest extends TestCase
{
    private const PESAN = 'RAB Komite Rp7.950.000 melebihi pagu Rp7.500.000 sebesar Rp450.000 — kurangi rincian di halaman RAB atau perbarui pagu.';

    public function test_hanya_rab_terpilih_yang_melebihi_pagu(): void
    {
        $repo = app(KegiatanRepository::class);

        $this->assertSame(['Komite' => self::PESAN], BatasPagu::terlampaui(AnggaranKegiatan::untuk($repo->temukan(3))), 'DIPA pagu belum diisi tidak menghalangi.');
        $this->assertSame([], BatasPagu::terlampaui(AnggaranKegiatan::untuk($repo->temukan(1))));
        $this->assertSame([], BatasPagu::terlampaui(AnggaranKegiatan::untuk($repo->temukan(2))), 'Sisa pagu tidak menghalangi.');

        $butir = collect(array_merge(...array_column(ChecklistIsian::untuk($repo->temukan(3)), 'butir')))->keyBy('kunci');
        $this->assertSame([false, true, self::PESAN], [$butir['batas_pagu_Komite']['terisi'], $butir['batas_pagu_Komite']['ajukan'], $butir['batas_pagu_Komite']['keterangan']]);
    }

    public function test_pengajuan_ditolak_sampai_rab_dalam_pagu(): void
    {
        Livewire::test(Form::class, ['kegiatan' => 3])
            // Pagu DIPA (wajib saat sumber dipilih) diisi lebih dulu; tinggal RAB Komite yang melebihi pagunya.
            ->set('form.pagu_dipa', '100.000.000')
            ->call('ubahStatus', 'ajukan')
            ->assertHasErrors(['form.pagu_komite'])
            ->assertSee(self::PESAN)
            ->assertSet('status', 'draf')
            // Pagu diperbarui sesuai RAB → boleh diajukan.
            ->set('form.pagu_komite', '7.950.000')
            ->call('ubahStatus', 'ajukan')
            ->assertHasNoErrors()
            ->assertSet('status', 'diperiksa');
    }

    public function test_persetujuan_ditolak_bila_rab_melebihi_pagu(): void
    {
        $repo = app(KegiatanRepository::class);
        $repo->simpan(['status' => 'diperiksa'] + $repo->temukan(3), 3);
        session(['stub_role' => 'admin']);

        Livewire::test(Periksa::class, ['kegiatan' => 3])
            ->call('minta', 'setujui')
            ->assertHasErrors(['keputusan'])
            ->assertSeeHtml('data-testid="galat-keputusan"')
            ->assertSee(self::PESAN)
            ->assertSet('konfirmasi', null)
            ->call('putuskan', 'setujui')
            ->assertHasErrors(['keputusan']);
        $this->assertSame('diperiksa', $repo->temukan(3)['status']);

        Livewire::test(Periksa::class, ['kegiatan' => 3])
            ->set('catatan', 'Sesuaikan rincian RAB Komite dengan pagu.')
            ->call('minta', 'kembalikan')
            ->assertHasNoErrors()
            ->assertSet('konfirmasi', 'kembalikan');
    }
}
