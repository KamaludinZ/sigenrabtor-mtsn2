<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\DasarHukumRepository;
use App\Contracts\RiwayatPengaturanRepository;
use App\Contracts\TahunAnggaranRepository;
use App\Livewire\Pengaturan\DasarHukum;
use App\Support\Pengaturan\SkRktm;
use App\Support\Stub\StubDasarHukumRepository;
use App\Support\Stub\StubTahunAnggaranRepository;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

/** Halaman Dasar hukum terhadap kontrak penyimpanan: gagal simpan dan perubahan dari tab/pengguna lain. */
class KoneksiDasarHukumTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        session(['stub_role' => 'admin']);
    }

    private function jumlahRiwayat(): int
    {
        return count(app(RiwayatPengaturanRepository::class)->semua());
    }

    /** Penyimpanan yang selalu gagal menulis; membaca tetap dari tiruan. */
    private function penyimpananGagal(): void
    {
        $this->app->instance(DasarHukumRepository::class, new class extends StubDasarHukumRepository
        {
            public function simpan(int $tahun, array $data): void
            {
                throw new RuntimeException('Disk penuh.');
            }
        });
        $this->app->instance(TahunAnggaranRepository::class, new class extends StubTahunAnggaranRepository
        {
            public function simpanSk(int $tahun, ?string $nomor, ?string $tanggal): void
            {
                throw new RuntimeException('Disk penuh.');
            }
        });
    }

    public function test_versi_mengikuti_penyimpanan_dan_terkunci(): void
    {
        $komponen = Livewire::test(DasarHukum::class);
        $this->assertSame(app(DasarHukumRepository::class)->untukTahun(2026)['diperbarui_pada'], $komponen->get('versi'));

        $komponen->call('hapusButir', 'dh-2026-4');
        $baru = app(DasarHukumRepository::class)->untukTahun(2026)['diperbarui_pada'];
        $this->assertNotSame('2026-01-05T09:00:00+07:00', $baru);
        $this->assertSame($baru, $komponen->get('versi'));

        $this->expectException(CannotUpdateLockedPropertyException::class);
        $komponen->set('versi', 'palsu');
    }

    public function test_gagal_simpan_dilaporkan_tanpa_membuang_isian(): void
    {
        $this->penyimpananGagal();
        $riwayat = $this->jumlahRiwayat();

        $komponen = Livewire::test(DasarHukum::class)
            ->call('tambahButir')
            ->set('teksButir', 'Peraturan baru')
            ->call('simpanButir')
            ->assertDispatched('notify', message: 'Dasar hukum TA 2026 gagal disimpan. Perubahan belum tersimpan — coba lagi.', type: 'error')
            ->assertNotDispatched('notify', type: 'success')
            ->assertSet('suntingButir', 'baru')
            ->assertSet('teksButir', 'Peraturan baru')
            ->call('batalButir')
            ->call('hapusButir', 'dh-2026-2')
            ->assertSet('butirTerhapus', null)
            ->assertSee('4 butir · tercetak bernomor')
            ->call('geserButir', 'dh-2026-2', 1)
            ->assertNotDispatched('butir-dipindah')
            ->assertSet('pengumuman', '');

        $komponen->call('ubahLatar')
            ->set('isianLatar.kalimat_penutup', 'Kegiatan {nama_kegiatan} baru.')
            ->call('simpanLatar')
            ->assertDispatched('notify', message: 'Latar belakang baku TA 2026 gagal disimpan. Isian masih ada di layar — coba simpan lagi.', type: 'error')
            ->assertSet('suntingLatar', true)
            ->assertSet('isianLatar.kalimat_penutup', 'Kegiatan {nama_kegiatan} baru.');

        $komponen->call('ubahSk')
            ->set('isianSk', ['nomor' => '12/SK/2026', 'tanggal' => '2025-12-30'])
            ->call('simpanSk')
            ->assertDispatched('notify', message: 'SK RKTM/RKAM TA 2026 gagal disimpan. Isian masih ada di layar — coba simpan lagi.', type: 'error')
            ->assertSet('suntingSk', true)
            ->assertSet('isianSk.nomor', '12/SK/2026');

        $this->assertCount(4, app(DasarHukumRepository::class)->untukTahun(2026)['dasar_hukum']);
        $this->assertNull(SkRktm::untuk(2026)['nomor']);
        $this->assertSame($riwayat, $this->jumlahRiwayat(), 'Gagal simpan tidak dicatat di riwayat.');
    }

    public function test_perubahan_dari_tab_lain_tidak_tertimpa(): void
    {
        $repo = app(DasarHukumRepository::class);
        $komponen = Livewire::test(DasarHukum::class)
            ->call('tambahButir')
            ->set('teksButir', 'Butir dari tab ini');

        // Tab lain menambah butir lebih dulu.
        $pengaturan = $repo->untukTahun(2026);
        $repo->simpan(2026, ['dasar_hukum' => [...$pengaturan['dasar_hukum'], ['teks' => 'Butir dari tab lain']]] + $pengaturan);

        $komponen->call('simpanButir')
            ->assertDispatched('notify', message: 'Dasar hukum & latar belakang TA 2026 baru saja diubah di tempat lain. Tampilan sudah dimuat ulang — periksa lalu ulangi perubahan Anda.', type: 'error')
            ->assertSet('suntingButir', 'baru')
            ->assertSet('teksButir', 'Butir dari tab ini')
            ->assertSee('Butir dari tab lain.');
        $this->assertCount(5, $repo->untukTahun(2026)['dasar_hukum']);

        // Setelah dimuat ulang, simpan berikutnya berhasil dan menyertakan butir tab lain.
        $komponen->call('simpanButir')->assertDispatched('notify', message: 'Butir dasar hukum ditambahkan untuk TA 2026.', type: 'success');
        $this->assertSame(['Butir dari tab lain', 'Butir dari tab ini'], array_slice(array_column($repo->untukTahun(2026)['dasar_hukum'], 'teks'), 4));
    }

    public function test_butir_yang_diubah_sudah_dihapus_di_tab_lain_menjadi_butir_baru(): void
    {
        $repo = app(DasarHukumRepository::class);
        $komponen = Livewire::test(DasarHukum::class)
            ->call('ubahButir', 'dh-2026-2')
            ->set('teksButir', 'Ketikan yang tidak boleh hilang');

        $pengaturan = $repo->untukTahun(2026);
        $repo->simpan(2026, ['dasar_hukum' => array_values(array_filter($pengaturan['dasar_hukum'], fn ($b) => $b['id'] !== 'dh-2026-2'))] + $pengaturan);

        $komponen->call('simpanButir')
            ->assertSet('suntingButir', 'baru')
            ->assertSet('teksButir', 'Ketikan yang tidak boleh hilang')
            ->assertSeeHtml('data-testid="butir-baru"');
    }

    public function test_urutan_hapus_dan_latar_ditolak_saat_data_berubah(): void
    {
        $repo = app(DasarHukumRepository::class);
        $komponen = Livewire::test(DasarHukum::class)->call('ubahLatar')->set('isianLatar.kalimat_penutup', 'Versi tab ini {nama_kegiatan}.');

        $pengaturan = $repo->untukTahun(2026);
        $repo->simpan(2026, ['kalimat_penutup' => 'Versi tab lain {nama_kegiatan}.'] + $pengaturan);

        $komponen->call('simpanLatar')
            ->assertDispatched('notify', type: 'error')
            ->assertSet('suntingLatar', true)
            ->assertSet('isianLatar.kalimat_penutup', 'Versi tab ini {nama_kegiatan}.');
        $this->assertSame('Versi tab lain {nama_kegiatan}.', $repo->untukTahun(2026)['kalimat_penutup']);

        $komponen->call('batalLatar');
        $repo->simpan(2026, ['kalimat_penutup' => 'Lagi {nama_kegiatan}.'] + $repo->untukTahun(2026));
        $komponen->call('pindahkanButir', 'dh-2026-4', 0)->assertDispatched('notify', type: 'error');
        $this->assertSame('dh-2026-1', $repo->untukTahun(2026)['dasar_hukum'][0]['id']);

        $repo->simpan(2026, $repo->untukTahun(2026));
        $komponen->call('hapusButir', 'dh-2026-1')->assertSet('butirTerhapus', null);
        $this->assertCount(4, $repo->untukTahun(2026)['dasar_hukum']);

        // Tanpa perubahan lain, aksi berikutnya berjalan.
        $komponen->call('hapusButir', 'dh-2026-1')->assertSet('butirTerhapus.butir.id', 'dh-2026-1');
        $this->assertCount(3, $repo->untukTahun(2026)['dasar_hukum']);
    }
}
