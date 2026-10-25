<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Livewire\Kegiatan\Form;
use App\Support\Stub\StubKegiatanRepository;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class SimpanMuatKegiatanTest extends TestCase
{
    private function repo(): KegiatanRepository
    {
        return app(KegiatanRepository::class);
    }

    public function test_repository_tiruan_menyiapkan_contoh_kegiatan(): void
    {
        $contoh = $this->repo()->temukan(1);

        $this->assertSame('Workshop Penyusunan Kurikulum Madrasah dan Perangkat Pembelajaran', $contoh['nama_kegiatan']);
        $this->assertCount($this->jumlahContohKegiatan(), $this->repo()->terbaru());
    }

    public function test_simpan_kegiatan_baru_lalu_dialihkan_ke_halaman_edit(): void
    {
        Livewire::test(Form::class)
            ->set('form.nama_kegiatan', 'Pelatihan Moderasi Beragama')
            ->set('form.tanggal_mulai', '2026-08-10')
            ->set('form.lama_hari', 2)
            ->call('tambahPanitia')
            ->set('form.panitia.0.nama', 'Budi Santoso')
            ->assertSet('adaPerubahan', true)
            ->call('saveDraft')
            ->assertHasNoErrors()
            ->assertRedirect(route('kegiatan.edit', ['kegiatan' => $this->idKegiatanBaru()]));

        $tersimpan = $this->repo()->temukan($this->idKegiatanBaru());
        $this->assertSame('Pelatihan Moderasi Beragama', $tersimpan['nama_kegiatan']);
        $this->assertSame('PELATIHAN MODERASI BERAGAMA', $tersimpan['nama_kegiatan_cover']);
        $this->assertSame(2, $tersimpan['lama_hari']);
        $this->assertSame([8], $tersimpan['bulan_pelaksanaan']);
        $this->assertSame('2026-08-21', $tersimpan['tanggal_laporan']);
        $this->assertSame([['nama' => 'Budi Santoso', 'jabatan' => '', 'peran' => 'Anggota']], $tersimpan['panitia']);
        $this->assertSame('draf', $tersimpan['status']);
        $this->assertSame('Kegiatan baru tersimpan sebagai draf.', session('notify')['message']);
    }

    public function test_halaman_edit_memuat_data_tersimpan(): void
    {
        $this->get(route('kegiatan.edit', ['kegiatan' => 1]))
            ->assertOk()
            ->assertSee('Workshop Penyusunan Kurikulum Madrasah dan Perangkat Pembelajaran')
            ->assertSee('Terakhir disimpan')
            ->assertSee('Ahmad Fauzi, S.Pd.', false);

        Livewire::test(Form::class, ['kegiatan' => 1])
            ->assertSet('kegiatanId', 1)
            ->assertSet('form.tanggal_mulai', '2026-07-14')
            ->assertSet('form.lama_hari', 1)
            ->assertSet('form.bulan_pelaksanaan', [7])
            ->assertSet('form.laporan_otomatis', false)
            ->assertSet('adaPerubahan', false)
            ->assertCount('form.panitia', 3);
    }

    public function test_simpan_ulang_memperbarui_data_tanpa_membuat_kegiatan_baru(): void
    {
        Livewire::test(Form::class, ['kegiatan' => 1])
            ->set('form.sasaran', 'dokumen kurikulum yang disahkan kepala madrasah.')
            ->call('saveDraft')
            ->assertHasNoErrors()
            ->assertNoRedirect()
            ->assertSet('adaPerubahan', false)
            ->assertDispatched('notify');

        $this->assertSame('dokumen kurikulum yang disahkan kepala madrasah.', $this->repo()->temukan(1)['sasaran']);
        $this->assertCount($this->jumlahContohKegiatan(), $this->repo()->terbaru());
    }

    public function test_kegiatan_tidak_ditemukan_menghasilkan_404(): void
    {
        $this->get(route('kegiatan.edit', ['kegiatan' => 999]))->assertNotFound();
    }

    public function test_id_kegiatan_tidak_bisa_diubah_dari_browser(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(Form::class)->set('kegiatanId', 1);
    }

    public function test_menu_buka_draf_menampilkan_draf_terbaru(): void
    {
        $this->repo()->simpan(['nama_kegiatan' => 'Kegiatan MGMP IPA', 'status' => 'draf']);

        Livewire::test(Form::class)
            ->assertSee('Buka draf')
            ->assertSee('Kegiatan MGMP IPA')
            ->assertSee('Workshop Penyusunan Kurikulum Madrasah dan Perangkat Pembelajaran');
    }

    public function test_data_tersimpan_lama_dengan_tipe_tidak_lengkap_tetap_bisa_dimuat(): void
    {
        $this->repo()->simpan(['nama_kegiatan' => 'Data Lama', 'latar_belakang' => null, 'lama_hari' => '3', 'bulan_pelaksanaan' => ['7'], 'panitia' => [['nama' => 'X']]]);

        Livewire::test(Form::class, ['kegiatan' => $this->idKegiatanBaru()])
            ->assertSet('form.latar_belakang', '')
            ->assertSet('form.lama_hari', 3)
            ->assertSet('form.bulan_pelaksanaan', [7])
            ->assertSet('form.panitia.0.peran', 'Anggota');
    }

    public function test_repository_tiruan_terikat_ke_kontrak(): void
    {
        $this->assertInstanceOf(StubKegiatanRepository::class, $this->repo());
    }
}
