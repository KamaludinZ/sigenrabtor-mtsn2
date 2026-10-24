<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Livewire\Kegiatan\Klasifikasi;
use Livewire\Livewire;
use Tests\TestCase;

class SimpanKodeTest extends TestCase
{
    private function kegiatan(array $kode = []): int
    {
        return app(KegiatanRepository::class)->simpan([
            'nama_kegiatan' => 'Kegiatan Simpan Kode',
            'nama_kegiatan_cover' => 'KEGIATAN SIMPAN KODE',
            'leading' => '1.6',
            'kode_sk' => 'SK.12',
            'kode_iksk' => 'IKSK 12.1',
            'tujuan' => 'tujuan yang tidak boleh hilang.',
        ] + $kode);
    }

    public function test_perubahan_dihitung_dari_nilai_bukan_dari_sentuhan(): void
    {
        Livewire::test(Klasifikasi::class, ['kegiatan' => $this->kegiatan()])
            ->set('form.kode_edm', 'C1')
            ->assertSet('adaPerubahan', true)
            ->assertSee('Belum disimpan')
            ->assertSee('Batalkan')
            ->set('form.kode_edm', '')
            ->assertSet('adaPerubahan', false)
            ->assertDontSee('Belum disimpan');
    }

    public function test_simpan_tanpa_perubahan_tidak_menulis_ulang(): void
    {
        $id = $this->kegiatan();
        $repo = app(KegiatanRepository::class);
        $sebelum = $repo->temukan($id)['diperbarui_pada'];
        $this->travel(5)->minutes();

        Livewire::test(Klasifikasi::class, ['kegiatan' => $id])
            ->call('simpan')
            ->assertDispatched('notify', message: 'Tidak ada perubahan kode untuk disimpan.', type: 'info');

        $this->assertSame($sebelum, $repo->temukan($id)['diperbarui_pada']);
        $this->assertArrayNotHasKey('riwayat_kode', $repo->temukan($id));
    }

    public function test_simpan_mencatat_riwayat_dan_menjaga_isian_lain(): void
    {
        $id = $this->kegiatan();

        Livewire::test(Klasifikasi::class, ['kegiatan' => $id])
            ->set('form.kode_edm', 'C1')
            ->set('form.budaya_mutu', '2')
            ->call('simpan')
            ->assertHasNoErrors()
            ->assertSet('adaPerubahan', false)
            ->assertSee('Terakhir disimpan')
            ->assertSeeHtml('data-testid="riwayat-kode"')
            ->assertSee('Riwayat perubahan (1)')
            ->assertSee('Kode EDM')
            ->assertSee('kosong → C1')
            ->assertSee('kosong → 2. Profesionalitas');

        $tersimpan = app(KegiatanRepository::class)->temukan($id);
        $this->assertSame('tujuan yang tidak boleh hilang.', $tersimpan['tujuan']);
        $this->assertSame(['kode_edm' => [null, 'C1'], 'budaya_mutu' => [null, 2]], $tersimpan['riwayat_kode'][0]['perubahan']);
        $this->assertSame('Muslimin, M.Pd.', $tersimpan['riwayat_kode'][0]['oleh']);
    }

    public function test_riwayat_dibatasi(): void
    {
        $riwayat = array_fill(0, Klasifikasi::BATAS_RIWAYAT, ['pada' => '2026-01-01T08:00:00+07:00', 'oleh' => 'Lama', 'perubahan' => ['kode_edm' => [null, 'C1']]]);
        $id = $this->kegiatan(['riwayat_kode' => $riwayat]);

        Livewire::test(Klasifikasi::class, ['kegiatan' => $id])
            ->set('form.kode_edm', 'C2')
            ->call('simpan');

        $tersimpan = app(KegiatanRepository::class)->temukan($id)['riwayat_kode'];
        $this->assertCount(Klasifikasi::BATAS_RIWAYAT, $tersimpan);
        $this->assertSame(['kode_edm' => [null, 'C2']], end($tersimpan)['perubahan']);
    }

    public function test_bentrok_ditahan_lalu_bisa_muat_terbaru_atau_timpa(): void
    {
        $id = $this->kegiatan();
        $repo = app(KegiatanRepository::class);

        $layarA = Livewire::test(Klasifikasi::class, ['kegiatan' => $id]);

        // Pengguna lain menyimpan EDM lebih dulu.
        Livewire::test(Klasifikasi::class, ['kegiatan' => $id])->set('form.kode_edm', 'C1')->call('simpan');

        $layarA->set('form.budaya_mutu', '3')
            ->call('simpan')
            ->assertDispatched('notify', type: 'error')
            ->assertSeeHtml('data-testid="banner-bentrok"')
            ->assertSee('Kode EDM: kosong → C1')
            ->assertSet('adaPerubahan', true);
        $this->assertNull($repo->temukan($id)['budaya_mutu'] ?? null, 'Penyimpanan ditahan saat bentrok.');

        $layarA->call('simpan', false, true)
            ->assertDontSeeHtml('data-testid="banner-bentrok"')
            ->assertSet('adaPerubahan', false);
        $this->assertSame([null, 3], [$repo->temukan($id)['kode_edm'] ?? null, $repo->temukan($id)['budaya_mutu']]);

        // Muat versi terbaru membuang pilihan di layar.
        $layarB = Livewire::test(Klasifikasi::class, ['kegiatan' => $id]);
        Livewire::test(Klasifikasi::class, ['kegiatan' => $id])->set('form.kode_edm', 'C2')->call('simpan');
        $layarB->set('form.budaya_mutu', '4')
            ->call('simpan')
            ->assertSeeHtml('data-testid="banner-bentrok"')
            ->call('muatVersiTerbaru')
            ->assertDontSeeHtml('data-testid="banner-bentrok"')
            ->assertSet('form.kode_edm', 'C2')
            ->assertSet('form.budaya_mutu', '3');
    }

    public function test_batalkan_perubahan_kembali_ke_tersimpan(): void
    {
        Livewire::test(Klasifikasi::class, ['kegiatan' => $this->kegiatan()])
            ->set('form.kode_sk', 'SK.9')
            ->assertSet('form.kode_iksk', '')
            ->call('batalkanPerubahan')
            ->assertSet('form.kode_sk', 'SK.12')
            ->assertSet('form.kode_iksk', 'IKSK 12.1')
            ->assertSet('adaPerubahan', false)
            ->assertHasNoErrors()
            ->assertDispatched('notify', message: 'Perubahan kode dibatalkan.');
    }

    public function test_pintasan_ctrl_s_hanya_saat_bisa_diubah(): void
    {
        Livewire::test(Klasifikasi::class, ['kegiatan' => $this->kegiatan()])
            ->assertSeeHtml('x-on:keydown.window.ctrl.s.prevent="$wire.simpan()"');

        Livewire::test(Klasifikasi::class, ['kegiatan' => $this->kegiatan(['status' => 'final'])])
            ->assertDontSeeHtml('x-on:keydown.window.ctrl.s.prevent');
    }
}
