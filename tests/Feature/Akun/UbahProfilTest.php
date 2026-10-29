<?php

namespace Tests\Feature\Akun;

use App\Contracts\PenggunaRepository;
use App\Contracts\RiwayatPengaturanRepository;
use App\Livewire\Akun\Profil;
use App\Support\Akun\UbahProfil;
use App\Support\Pengaturan\PengaturanBerubah;
use App\Support\Stub\StubData;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class UbahProfilTest extends TestCase
{
    private function riwayatProfil(): array
    {
        return array_values(array_filter(app(RiwayatPengaturanRepository::class)->semua(), fn (array $r) => $r['aksi'] === 'ubah_profil_akun'));
    }

    public function test_simpan_nama_dan_jabatan_dengan_riwayat(): void
    {
        $repo = app(PenggunaRepository::class);
        $versi = UbahProfil::versi($repo->temukan(1));

        $hasil = UbahProfil::simpan(StubData::currentUser(), ['nama' => '  Drs.  Muslimin,   M.Pd. ', 'jabatan' => ' Ketua  Tim Penjamin Mutu '], $versi);

        $this->assertSame('Nama: Muslimin, M.Pd. → Drs. Muslimin, M.Pd.; Jabatan: Tim Penjamin Mutu → Ketua Tim Penjamin Mutu', $hasil['ringkasan']);
        $this->assertSame(['Drs. Muslimin, M.Pd.', 'Ketua Tim Penjamin Mutu'], [$repo->temukan(1)['name'], $repo->temukan(1)['jabatan']]);
        $this->assertSame('Drs. Muslimin, M.Pd.', StubData::currentUser()['name']);
        $this->assertNotSame($versi, $hasil['versi']);
        $this->assertSame(['muslimin@example.com — '.$hasil['ringkasan']], array_column($this->riwayatProfil(), 'keterangan'));
        // Surel, peran, NIP, dan leading tidak berubah.
        $this->assertSame(['muslimin@example.com', 'leading', ['1.6']], [$repo->temukan(1)['email'], $repo->temukan(1)['role'], $repo->temukan(1)['leadings']]);

        // Tanpa perubahan: tidak ditulis, tanpa riwayat.
        $this->assertNull(UbahProfil::simpan(StubData::currentUser(), ['nama' => 'Drs. Muslimin, M.Pd.', 'jabatan' => 'Ketua Tim Penjamin Mutu'], $hasil['versi'])['ringkasan']);
        $this->assertCount(1, $this->riwayatProfil());
        // Jabatan dikosongkan disimpan null.
        UbahProfil::simpan(StubData::currentUser(), ['nama' => 'Drs. Muslimin, M.Pd.', 'jabatan' => '   ']);
        $this->assertNull($repo->temukan(1)['jabatan']);
    }

    public function test_galat_dan_bentrok_versi(): void
    {
        $galat = fn (array $isian) => UbahProfil::galat(UbahProfil::rapikan($isian));
        $this->assertSame(['nama' => 'Nama lengkap wajib diisi.'], $galat(['nama' => '  ', 'jabatan' => '']));
        $this->assertSame(['nama' => 'Nama harus berisi huruf.'], $galat(['nama' => '1234', 'jabatan' => '']));
        $this->assertArrayHasKey('jabatan', $galat(['nama' => 'Ani', 'jabatan' => str_repeat('a', 151)]));

        try {
            UbahProfil::simpan(StubData::currentUser(), ['nama' => '', 'jabatan' => '']);
            $this->fail('Nama kosong seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('nama', $e->errors());
        }

        $this->expectException(PengaturanBerubah::class);
        UbahProfil::simpan(StubData::currentUser(), ['nama' => 'Muslimin', 'jabatan' => ''], 'versi-lama');
    }

    public function test_formulir_ubah_profil_di_halaman_akun(): void
    {
        Livewire::test(Profil::class)
            ->assertSee('data-testid="ubah-profil"', false)
            ->call('bukaUbah')
            ->assertSet('ubah', true)
            ->assertSet('isian', ['nama' => 'Muslimin, M.Pd.', 'jabatan' => 'Tim Penjamin Mutu'])
            ->assertSee('data-testid="form-profil"', false)
            ->assertSee('<option value="Ketua Tim Penjamin Mutu">', false)
            ->set('isian.nama', '')
            ->assertHasErrors(['isian.nama'])
            ->set('isian.nama', 'Muslimin, S.Pd., M.Pd.')
            ->assertHasNoErrors(['isian.nama'])
            // Seluruh isian terkirim sekaligus (dua kolom berubah sebelum satu permintaan).
            ->set('isian', ['nama' => '', 'jabatan' => 'Tim Penjamin Mutu'])
            ->assertHasErrors(['isian.nama'])
            ->set('isian', ['nama' => 'Muslimin, S.Pd., M.Pd.', 'jabatan' => 'Tim Penjamin Mutu'])
            ->assertHasNoErrors(['isian.nama'])
            ->call('simpanProfil')
            ->assertSet('ubah', false)
            ->assertDispatched('notify', message: 'Profil disimpan.', type: 'success')
            ->assertDispatched('profil-diperbarui', nama: 'Muslimin, S.Pd., M.Pd.', jabatan: 'Tim Penjamin Mutu')
            ->assertSee('Muslimin, S.Pd., M.Pd.');
    }

    public function test_bentrok_mempertahankan_isian_dan_menawarkan_data_terbaru(): void
    {
        $uji = Livewire::test(Profil::class)->call('bukaUbah')->set('isian.jabatan', 'Koordinator Mutu');
        $repo = app(PenggunaRepository::class);
        $repo->simpan(['name' => 'Muslimin (diubah admin)'], 1);

        $uji->call('simpanProfil')
            ->assertSet('ubah', true)
            ->assertSet('isian.jabatan', 'Koordinator Mutu')
            ->assertSet('bentrok', ['nama' => 'Muslimin (diubah admin)', 'jabatan' => 'Tim Penjamin Mutu'])
            ->assertSee('data-testid="profil-bentrok"', false)
            ->assertDispatched('notify', type: 'warning')
            ->call('pakaiTerbaru')
            ->assertSet('isian', ['nama' => 'Muslimin (diubah admin)', 'jabatan' => 'Tim Penjamin Mutu'])
            ->set('isian.jabatan', 'Koordinator Mutu')
            ->call('simpanProfil')
            ->assertSet('ubah', false);
        $this->assertSame('Koordinator Mutu', $repo->temukan(1)['jabatan']);
    }
}
