<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\KegiatanRepository;
use App\Contracts\LeadingRepository;
use App\Contracts\RiwayatPengaturanRepository;
use App\Livewire\Kegiatan\Klasifikasi;
use App\Livewire\Pengaturan\LeadingUser;
use App\Support\Dokumen\SusunanKak;
use App\Support\MasterKode;
use App\Support\Monitoring\RingkasanAnggaran;
use Livewire\Livewire;
use Tests\TestCase;

class StatusLeadingTest extends TestCase
{
    private function nonaktifkan(string $kode): void
    {
        $repo = app(LeadingRepository::class);
        $repo->simpan(['is_active' => false] + collect($repo->semua())->firstWhere('kode', '===', $kode), $kode);
    }

    public function test_nonaktifkan_dengan_konfirmasi_dan_aktifkan_kembali(): void
    {
        session(['stub_role' => 'admin']);
        $komponen = Livewire::test(LeadingUser::class)
            ->call('ubahStatusLeading', '1.6')
            ->assertSet('konfirmasiNonaktif', '1.6')
            ->assertSeeHtml('data-testid="konfirmasi-nonaktif-leading"')
            ->assertSee('Nonaktifkan 1.6 Tim Penjamin Mutu?')
            ->assertSee('kegiatan tetap memakainya.')
            ->assertSee('Akun yang hanya tertaut ke leading ini (Muslimin, M.Pd.) tidak bisa membuat kegiatan baru.')
            ->set('konfirmasiNonaktif', null);
        $this->assertTrue(MasterKode::leading('1.6')['is_active'], 'Batal tidak mengubah status.');

        $komponen->call('ubahStatusLeading', '1.7')
            ->call('ubahStatusLeading', '1.7')
            ->assertSet('konfirmasiNonaktif', null)
            ->assertDispatched('notify', message: 'Leading 1.7 Mahad dinonaktifkan — tidak bisa dipilih untuk kegiatan baru.', type: 'success')
            ->assertSeeHtmlInOrder(['data-leading="1.7"', 'Nonaktif']);
        $this->assertFalse(MasterKode::leading('1.7')['is_active']);
        $this->assertNotContains('1.7', array_column(MasterKode::leadings(), 'kode'));
        $this->assertSame(['ubah_status_leading', 'Leading 1.7 Mahad dinonaktifkan'], [app(RiwayatPengaturanRepository::class)->semua()[0]['aksi'], app(RiwayatPengaturanRepository::class)->semua()[0]['keterangan']]);

        // Mengaktifkan kembali tanpa konfirmasi.
        $komponen->call('ubahStatusLeading', '1.7')
            ->assertDispatched('notify', message: 'Leading 1.7 Mahad aktif kembali — bisa dipilih untuk kegiatan.', type: 'success');
        $this->assertTrue(MasterKode::leading('1.7')['is_active']);
        $komponen->call('ubahStatusLeading', 'tidak-ada')->assertSet('konfirmasiNonaktif', null);
    }

    public function test_leading_aktif_terakhir_tidak_bisa_dinonaktifkan(): void
    {
        session(['stub_role' => 'admin']);
        foreach (['1.1', '1.2', '1.3', '1.4', '1.5', '1.6', '1.7'] as $kode) {
            $this->nonaktifkan($kode);
        }

        Livewire::test(LeadingUser::class)
            ->call('ubahStatusLeading', '1.8')
            ->call('ubahStatusLeading', '1.8')
            ->assertDispatched('notify', message: 'Minimal satu leading harus tetap aktif.', type: 'error');
        $this->assertTrue(MasterKode::leading('1.8')['is_active']);
    }

    public function test_data_lama_tetap_terbaca_dan_kegiatan_tetap_bisa_disimpan(): void
    {
        $this->nonaktifkan('1.6');
        $kegiatan = app(KegiatanRepository::class)->temukan(1);

        // Lookup nama/jabatan memakai semua leading: dokumen & rekap tidak kehilangan leading nonaktif.
        $this->assertSame('Tim Penjamin Mutu', MasterKode::leading('1.6')['nama_unit']);
        $pelaksana = collect(collect(SusunanKak::dari($kegiatan)->bagian)->firstWhere('nomor', 6)['isi'])->firstWhere('jenis', 'paragraf');
        $this->assertStringContainsString('Ketua Tim Penjamin Mutu', $pelaksana['teks']);
        $rekap = RingkasanAnggaran::dari(app(KegiatanRepository::class)->semua())->perLeading();
        $this->assertContains('1.6', array_column($rekap, 'kode'), 'Leading nonaktif yang punya kegiatan tetap direkap.');
        $this->assertNotContains('1.9', array_column($rekap, 'kode'), 'Leading nonaktif tanpa kegiatan tidak direkap.');

        // Pilihan isian: leading tersimpan tetap ada (ditandai), leading nonaktif lain tidak.
        $pilihan = MasterKode::pilihanLeading(['role' => 'admin', 'leadings' => []], '1.6');
        $this->assertSame(['kode' => '1.6', 'is_active' => false], array_intersect_key(collect($pilihan)->last(), ['kode' => 1, 'is_active' => 1]));
        $this->assertNotContains('1.9', array_column($pilihan, 'kode'));
        $this->assertNotContains('1.6', array_column(MasterKode::pilihanLeading(['role' => 'admin', 'leadings' => []], null), 'kode'));

        session(['stub_role' => 'admin']);
        Livewire::test(Klasifikasi::class, ['kegiatan' => 1])
            ->assertSeeHtml('data-testid="leading-nonaktif"')
            ->assertSee('sudah dinonaktifkan admin')
            ->assertSeeHtmlInOrder(['data-leading="1.6"', 'nonaktif'])
            ->call('simpan')
            ->assertHasNoErrors(['form.leading']);
        $this->assertSame('1.6', app(KegiatanRepository::class)->temukan(1)['leading']);

        Livewire::test(Klasifikasi::class, ['kegiatan' => 1])
            ->set('form.leading', '1.9')
            ->call('simpan')
            ->assertHasErrors(['form.leading']);
    }
}
