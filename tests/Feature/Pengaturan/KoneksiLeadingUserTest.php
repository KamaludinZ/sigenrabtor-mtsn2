<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\LeadingRepository;
use App\Contracts\PenggunaRepository;
use App\Livewire\Pengaturan\LeadingUser;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

/** Halaman Leading & User terhadap penyimpanan: perubahan dari tab/admin lain tidak tertimpa diam-diam. */
class KoneksiLeadingUserTest extends TestCase
{
    private const PESAN = 'Data leading & akun baru saja diubah di tempat lain. Tampilan sudah dimuat ulang — periksa lalu ulangi perubahan Anda.';

    protected function setUp(): void
    {
        parent::setUp();
        session(['stub_role' => 'admin']);
    }

    /** Tab lain mengubah nama unit leading 1.8. */
    private function tabLainMengubah(string $nama = 'PMM Madrasah'): void
    {
        $repo = app(LeadingRepository::class);
        $repo->simpan(['nama_unit' => $nama] + collect($repo->semua())->firstWhere('kode', '===', '1.8'), '1.8');
    }

    public function test_versi_terkunci_dan_mengikuti_simpan_sendiri(): void
    {
        $komponen = Livewire::test(LeadingUser::class);
        $awal = $komponen->get('versi');
        $this->assertNotNull($awal);

        $komponen->call('ubahLeading', '1.7')->set('isianLeading.urutan', '20')->call('simpanLeading')->assertHasNoErrors()
            ->assertDispatched('notify', message: 'Leading 1.7 Mahad diperbarui.', type: 'success');
        $this->assertNotSame($awal, $komponen->get('versi'), 'Simpan sendiri memperbarui versi sehingga aksi berikutnya tidak ditolak.');
        $komponen->call('ubahLeading', '1.7')->set('isianLeading.urutan', '7')->call('simpanLeading')
            ->assertDispatched('notify', message: 'Leading 1.7 Mahad diperbarui.', type: 'success');

        $this->expectException(CannotUpdateLockedPropertyException::class);
        $komponen->set('versi', 'palsu');
    }

    public function test_formulir_leading_ditolak_bila_data_berubah_dan_isian_tetap(): void
    {
        $komponen = Livewire::test(LeadingUser::class)
            ->call('ubahLeading', '1.7')
            ->set('isianLeading.jabatan_penanda_tangan', 'Pengasuh Mahad Baru');
        $this->tabLainMengubah();

        $komponen->call('simpanLeading')
            ->assertDispatched('notify', message: self::PESAN, type: 'error')
            ->assertSet('suntingLeading', '1.7')
            ->assertSet('isianLeading.jabatan_penanda_tangan', 'Pengasuh Mahad Baru')
            ->assertSee('PMM Madrasah');
        $this->assertSame('Pengasuh Mahad', collect(app(LeadingRepository::class)->semua())->firstWhere('kode', '===', '1.7')['jabatan_penanda_tangan']);

        $komponen->call('simpanLeading')->assertDispatched('notify', message: 'Leading 1.7 Mahad diperbarui.', type: 'success');
        $this->assertSame('Pengasuh Mahad Baru', collect(app(LeadingRepository::class)->semua())->firstWhere('kode', '===', '1.7')['jabatan_penanda_tangan']);
    }

    public function test_status_tautan_dan_akun_ditolak_bila_data_berubah(): void
    {
        $komponen = Livewire::test(LeadingUser::class)->call('ubahStatusLeading', '1.7');
        $this->tabLainMengubah();
        $komponen->call('ubahStatusLeading', '1.7')
            ->assertDispatched('notify', message: self::PESAN, type: 'error')
            ->assertSet('konfirmasiNonaktif', null);
        $this->assertTrue(collect(app(LeadingRepository::class)->semua())->firstWhere('kode', '===', '1.7')['is_active']);

        $komponen->call('bukaTautan', '1.3')->set('akunDitautkan', '4');
        $this->tabLainMengubah('PMM Terpadu');
        $komponen->call('tautkanAkun')->assertDispatched('notify', message: self::PESAN, type: 'error');
        $this->assertNotContains('1.3', app(PenggunaRepository::class)->temukan(4)['leadings']);

        $komponen->call('ubahAkun', 3)->set('isianAkun.jabatan', 'Jabatan baru');
        app(PenggunaRepository::class)->simpan(['jabatan' => 'Diubah tab lain'], 3);
        $komponen->call('simpanAkun')
            ->assertDispatched('notify', message: self::PESAN, type: 'error')
            ->assertSet('isianAkun.jabatan', 'Jabatan baru');
        $this->assertSame('Diubah tab lain', app(PenggunaRepository::class)->temukan(3)['jabatan']);
    }

    public function test_masuk_di_tempat_lain_bukan_perubahan(): void
    {
        $komponen = Livewire::test(LeadingUser::class)->call('ubahLeading', '1.7')->set('isianLeading.urutan', '30');
        app(PenggunaRepository::class)->catatMasuk(1);

        $komponen->call('simpanLeading')->assertDispatched('notify', message: 'Leading 1.7 Mahad diperbarui.', type: 'success');
    }
}
