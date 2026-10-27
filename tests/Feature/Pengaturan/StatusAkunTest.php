<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\LeadingRepository;
use App\Contracts\PenggunaRepository;
use App\Contracts\RiwayatPengaturanRepository;
use App\Livewire\Pengaturan\LeadingUser;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class StatusAkunTest extends TestCase
{
    private function aktif(int $id): bool
    {
        return app(PenggunaRepository::class)->temukan($id)['is_active'];
    }

    public function test_nonaktifkan_dengan_konfirmasi_dan_aktifkan_kembali(): void
    {
        session(['stub_role' => 'admin']);
        $komponen = Livewire::withQueryParams(['tab' => 'akun'])->test(LeadingUser::class)
            ->call('ubahStatusAkun', 1)
            ->assertSet('konfirmasiNonaktifAkun', '1')
            ->assertSeeHtml('data-testid="konfirmasi-nonaktif-akun"')
            ->assertSeeHtmlInOrder(['Nonaktifkan Muslimin, M.Pd.?', 'Akun tidak bisa masuk lagi. 2 kegiatan buatannya tetap ada dan tetap tercatat atas namanya.'])
            ->set('konfirmasiNonaktifAkun', null);
        $this->assertTrue($this->aktif(1));

        $komponen->call('ubahStatusAkun', 1)->call('ubahStatusAkun', 1)
            ->assertDispatched('notify', message: 'Akun Muslimin, M.Pd. dinonaktifkan — tidak bisa masuk lagi.', type: 'success')
            ->assertSet('konfirmasiNonaktifAkun', null);
        $this->assertFalse($this->aktif(1));
        $this->assertSame(['ubah_status_akun', 'Akun Muslimin, M.Pd. dinonaktifkan'], [app(RiwayatPengaturanRepository::class)->semua()[0]['aksi'], app(RiwayatPengaturanRepository::class)->semua()[0]['keterangan']]);

        $komponen->call('ubahStatusAkun', 1)
            ->assertDispatched('notify', message: 'Akun Muslimin, M.Pd. aktif kembali — bisa masuk lagi.', type: 'success');
        $this->assertTrue($this->aktif(1));
        $komponen->call('ubahStatusAkun', 99)->assertSet('konfirmasiNonaktifAkun', null);
    }

    public function test_akun_sendiri_dan_admin_terakhir_dilindungi(): void
    {
        session(['stub_role' => 'admin']);
        Livewire::withQueryParams(['tab' => 'akun'])->test(LeadingUser::class)
            ->assertSeeHtml('aria-label="Nonaktifkan akun Koordinator PUSKOM (akun Anda)"')
            ->call('ubahStatusAkun', 2)
            ->assertDispatched('notify', message: 'Akun Anda sendiri tidak bisa dinonaktifkan.', type: 'error')
            ->assertSet('konfirmasiNonaktifAkun', null);
        $this->assertTrue($this->aktif(2));

        // Admin lain (5) adalah admin aktif terakhir bila akun 2 nonaktif.
        app(PenggunaRepository::class)->simpan(['is_active' => false], 2);
        Livewire::withQueryParams(['tab' => 'akun'])->test(LeadingUser::class)
            ->call('ubahStatusAkun', 5)
            ->assertDispatched('notify', message: 'Minimal satu admin aktif harus tetap ada.', type: 'error');
        $this->assertTrue($this->aktif(5));
    }

    public function test_mengaktifkan_akun_tanpa_leading_aktif_diberi_tahu(): void
    {
        session(['stub_role' => 'admin']);
        $repo = app(LeadingRepository::class);
        $repo->simpan(['is_active' => false] + collect($repo->semua())->firstWhere('kode', '===', '1.5'), '1.5');

        Livewire::withQueryParams(['tab' => 'akun'])->test(LeadingUser::class)
            ->call('ubahStatusAkun', 6)
            ->assertDispatched('notify', message: 'Akun Dewi Kartika, S.E. aktif kembali, tetapi belum tertaut ke leading aktif — tautkan dulu agar bisa membuat kegiatan.', type: 'info');
        $this->assertTrue($this->aktif(6));
    }

    public function test_masuk_lewat_sakelar_demo_mencatat_waktu_masuk(): void
    {
        Carbon::setTestNow('2026-03-10 09:15:00');
        $this->get('/demo/peran/admin')->assertRedirect();
        $this->assertStringStartsWith('2026-03-10T09:15:00', app(PenggunaRepository::class)->temukan(2)['terakhir_masuk']);
        $this->assertSame('2026-01-05T07:45:00+07:00', app(PenggunaRepository::class)->temukan(1)['terakhir_masuk'], 'Akun lain tidak berubah.');

        $this->get('/demo/peran/leading')->assertRedirect();
        $this->assertStringStartsWith('2026-03-10T09:15:00', app(PenggunaRepository::class)->temukan(1)['terakhir_masuk']);
        $this->assertSame('Muslimin, M.Pd.', app(PenggunaRepository::class)->temukan(1)['name'], 'Data lain akun tetap.');
        Carbon::setTestNow();
    }
}
