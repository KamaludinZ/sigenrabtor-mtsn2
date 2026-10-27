<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\PenggunaRepository;
use App\Contracts\RiwayatPengaturanRepository;
use App\Livewire\Pengaturan\LeadingUser;
use App\Support\Pengaturan\AturanAkun;
use App\Support\Stub\StubData;
use App\Support\Stub\StubPenggunaRepository;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class FormAkunTest extends TestCase
{
    private const AKTIF = ['1.1', '1.2', '1.3', '1.4', '1.5', '1.6', '1.7', '1.8'];

    protected function setUp(): void
    {
        parent::setUp();
        session(['stub_role' => 'admin']);
    }

    private function riwayatTerakhir(): array
    {
        return app(RiwayatPengaturanRepository::class)->semua()[0];
    }

    public function test_aturan_isian_akun(): void
    {
        $akun = app(PenggunaRepository::class)->semua();
        $isian = fn (array $ubah) => $ubah + ['name' => 'Akun Baru', 'email' => 'baru@example.com', 'role' => 'leading', 'jabatan' => '', 'nip' => '', 'leadings' => ['1.3']];

        $this->assertSame([], AturanAkun::galat($isian([]), $akun, self::AKTIF, null, 2));
        $this->assertSame(['name' => 'Nama wajib diisi.', 'email' => 'Surel wajib diisi — dipakai untuk masuk.', 'leadings' => 'Akun leading wajib ditautkan ke minimal satu leading.'],
            AturanAkun::galat($isian(['name' => '', 'email' => '', 'leadings' => []]), $akun, self::AKTIF, null, 2));
        $this->assertSame(['email' => 'Surel tidak valid.'], AturanAkun::galat($isian(['email' => 'bukan-surel']), $akun, self::AKTIF, null, 2));
        $this->assertSame(['email' => 'Surel sudah dipakai akun Muslimin, M.Pd.'], AturanAkun::galat($isian(['email' => 'MUSLIMIN@example.com']), $akun, self::AKTIF, null, 2));
        $this->assertSame(['leadings' => 'Leading yang dipilih tidak dikenal atau nonaktif.'], AturanAkun::galat($isian(['leadings' => ['1.9']]), $akun, self::AKTIF, null, 2));
        $this->assertSame([], AturanAkun::galat($isian(['role' => 'admin', 'leadings' => []]), $akun, self::AKTIF, null, 2), 'Admin tanpa tautan leading.');
        $this->assertArrayHasKey('nip', AturanAkun::galat($isian(['nip' => '123']), $akun, self::AKTIF, null, 2));
        $this->assertSame(['role' => 'Pilih peran Admin atau Leading.'], AturanAkun::galat($isian(['role' => 'tamu']), $akun, self::AKTIF, null, 2));

        // Peran akun sendiri & admin aktif terakhir.
        $this->assertSame(['role' => 'Peran akun Anda sendiri tidak bisa diubah.'], AturanAkun::galat($isian(['email' => 'puskom@example.com']), $akun, self::AKTIF, 2, 2));
        $hanyaSatuAdmin = array_map(fn (array $a) => $a['id'] === 5 ? ['is_active' => false] + $a : $a, $akun);
        $this->assertSame(['role' => 'Minimal satu admin aktif harus tetap ada.'], AturanAkun::galat($isian(['email' => 'puskom@example.com']), $hanyaSatuAdmin, self::AKTIF, 2, 1));
        // Tautan lama ke leading nonaktif boleh dipertahankan.
        $this->assertSame([], AturanAkun::galat($isian(['email' => 'dewi.kartika@example.com', 'leadings' => ['1.5']]), $akun, array_diff(self::AKTIF, ['1.5']), 6, 2));
    }

    public function test_tambah_akun_leading(): void
    {
        Livewire::test(LeadingUser::class)
            ->call('tambahAkun')
            ->assertSet('tab', 'akun')
            ->assertSet('suntingAkun', 'baru')
            ->assertSeeHtml('data-testid="formulir-akun"')
            ->assertSeeHtml('data-testid="tautan-leading"')
            ->call('simpanAkun')
            ->assertHasErrors(['isianAkun.name', 'isianAkun.email', 'isianAkun.leadings'])
            ->set('isianAkun.name', '  Hasan   Basri, S.Pd. ')
            ->set('isianAkun.email', ' Hasan.Basri@Example.com ')
            ->set('isianAkun.leadings', ['1.7', '1.3', '1.3'])
            ->set('isianAkun.nip', '19700101 199503 1 001')
            ->call('simpanAkun')
            ->assertHasNoErrors()
            ->assertSet('suntingAkun', null)
            ->assertDispatched('notify', message: 'Akun Hasan Basri, S.Pd. ditambahkan.', type: 'success')
            ->assertDispatched('akun-disimpan', id: 7)
            ->assertSeeHtml('data-akun="7"');

        $akun = app(PenggunaRepository::class)->temukan(7);
        $this->assertSame(['Hasan Basri, S.Pd.', 'hasan.basri@example.com', 'leading', ['1.3', '1.7'], '197001011995031001', true],
            [$akun['name'], $akun['email'], $akun['role'], $akun['leadings'], $akun['nip'], $akun['is_active']]);
        $this->assertSame(['tambah_akun', 'Akun Hasan Basri, S.Pd. (hasan.basri@example.com) — Leading 1.3, 1.7'], [$this->riwayatTerakhir()['aksi'], $this->riwayatTerakhir()['keterangan']]);
    }

    public function test_ubah_akun_peran_dan_pengaman(): void
    {
        $komponen = Livewire::test(LeadingUser::class)
            ->call('ubahAkun', 4)
            ->assertSet('isianAkun', ['name' => 'Ahmad Fauzan, S.Pd.', 'email' => 'ahmad.fauzan@example.com', 'role' => 'leading', 'jabatan' => 'Wakil Kepala Bidang Kesiswaan', 'nip' => '', 'leadings' => ['1.2', '1.4']])
            ->set('isianAkun.role', 'admin')
            ->assertDontSeeHtml('data-testid="tautan-leading"')
            ->call('simpanAkun')
            ->assertHasNoErrors()
            ->assertDispatched('notify', message: 'Akun Ahmad Fauzan, S.Pd. diperbarui.', type: 'success');
        $this->assertSame(['admin', []], [app(PenggunaRepository::class)->temukan(4)['role'], app(PenggunaRepository::class)->temukan(4)['leadings']]);
        $this->assertSame('Akun Ahmad Fauzan, S.Pd. — Peran: Leading → Admin; Leading: 1.2, 1.4 → —', $this->riwayatTerakhir()['keterangan']);

        // Akun sendiri (admin 2): peran tidak bisa diganti.
        $komponen->call('ubahAkun', 2)
            ->assertSee('Peran akun Anda sendiri tidak bisa diubah.')
            ->set('isianAkun.role', 'leading')
            ->set('isianAkun.leadings', ['1.1'])
            ->call('simpanAkun')
            ->assertHasErrors(['isianAkun.role']);
        $this->assertSame('admin', app(PenggunaRepository::class)->temukan(2)['role']);

        $jumlah = count(app(RiwayatPengaturanRepository::class)->semua());
        $komponen->call('batalAkun')->call('ubahAkun', 3)->call('simpanAkun')
            ->assertDispatched('notify', message: 'Tidak ada yang berubah.', type: 'info');
        $this->assertCount($jumlah, app(RiwayatPengaturanRepository::class)->semua());

        // Nama akun yang sedang masuk ikut berubah.
        $komponen->call('ubahAkun', 1)->set('isianAkun.name', 'Muslimin, S.Pd., M.Pd.')->call('simpanAkun');
        session(['stub_role' => 'leading']);
        $this->assertSame('Muslimin, S.Pd., M.Pd.', StubData::currentUser()['name']);
    }

    public function test_tautan_lama_ke_leading_nonaktif_tetap_tampil_dan_gagal_simpan(): void
    {
        Livewire::test(LeadingUser::class)
            ->call('ubahAkun', 6)
            ->assertSeeHtmlInOrder(['data-testid="tautan-leading"', '1.5', 'Ka. TU'])
            ->call('ubahAkun', 99)
            ->assertSet('suntingAkun', '6');

        $this->app->instance(PenggunaRepository::class, new class extends StubPenggunaRepository
        {
            public function simpan(array $data, ?int $id = null): int
            {
                throw new RuntimeException('Disk penuh.');
            }
        });
        Livewire::test(LeadingUser::class)
            ->call('tambahAkun')
            ->set('isianAkun', ['name' => 'Akun', 'email' => 'akun@example.com', 'role' => 'admin', 'jabatan' => '', 'nip' => '', 'leadings' => []])
            ->call('simpanAkun')
            ->assertDispatched('notify', message: 'Akun gagal disimpan. Isian masih ada di layar — coba simpan lagi.', type: 'error')
            ->assertSet('suntingAkun', 'baru');
    }
}
