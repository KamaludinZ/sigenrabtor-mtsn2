<?php

namespace Tests\Feature\Pemeriksaan;

use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Livewire\Kegiatan\Form;
use App\Livewire\Pemeriksaan\Periksa;
use App\Support\Kegiatan\RiwayatStatus;
use Livewire\Livewire;
use Tests\TestCase;

class KeputusanPemeriksaanTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        session(['stub_role' => 'admin']);
    }

    /** Salinan contoh kegiatan 2 yang diajukan pada $pada. */
    private function diajukan(string $nama, string $pada = '2026-03-01T09:00:00+07:00'): int
    {
        $data = app(KegiatanRepository::class)->temukan(2);
        unset($data['id']);

        return app(KegiatanRepository::class)->simpan(['nama_kegiatan' => $nama, 'status' => 'diperiksa', 'riwayat_status' => [
            ['aksi' => 'ajukan', 'status' => 'diperiksa', 'oleh' => 'Muslimin, M.Pd.', 'peran' => 'leading', 'pada' => $pada],
        ]] + $data);
    }

    public function test_kembalikan_wajib_catatan_lalu_konfirmasi(): void
    {
        $id = $this->diajukan('Supervisi Kelas VII');

        Livewire::test(Periksa::class, ['kegiatan' => $id])
            ->assertSee('Keputusan pemeriksaan')
            ->assertSeeHtml('data-testid="peringatan-keputusan"')
            ->set('catatan', "   \n  ")
            ->call('minta', 'kembalikan')
            ->assertHasErrors(['catatan' => 'required'])
            ->assertSet('konfirmasi', null)
            ->assertSee('Tulis catatan perbaikan agar leading tahu apa yang harus diubah.')
            ->set('catatan', "  Sasaran   belum terukur.\n\n\n\nSesuaikan honor narasumber.  ")
            ->call('minta', 'kembalikan')
            ->assertHasNoErrors()
            ->assertSet('konfirmasi', 'kembalikan')
            ->assertSee('Kembalikan ke leading?')
            ->call('batalKonfirmasi')
            ->assertSet('konfirmasi', null)
            ->call('minta', 'kembalikan')
            ->call('putuskan', 'kembalikan')
            ->assertSet('catatan', '')
            ->assertSet('konfirmasi', null)
            ->assertDispatched('notify', message: 'Kegiatan dikembalikan ke leading beserta catatan perbaikannya.')
            ->assertSee('Menunggu perbaikan')
            ->assertDontSee('Catatan untuk leading')
            ->assertSeeInOrder(['Riwayat status', 'Dikembalikan untuk perbaikan', 'Koordinator PUSKOM', 'Sasaran belum terukur.', 'Sesuaikan honor narasumber.']);

        $kegiatan = app(KegiatanRepository::class)->temukan($id);
        $this->assertSame('dikembalikan', $kegiatan['status']);
        $terakhir = end($kegiatan['riwayat_status']);
        $this->assertSame(['aksi' => 'kembalikan', 'status' => 'dikembalikan', 'oleh' => 'Koordinator PUSKOM', 'peran' => 'admin'], array_intersect_key($terakhir, array_flip(['aksi', 'status', 'oleh', 'peran'])));
        $this->assertSame("Sasaran belum terukur.\n\nSesuaikan honor narasumber.", $terakhir['catatan']);
        $this->assertSame('Supervisi Kelas VII', $kegiatan['nama_kegiatan'], 'Data kegiatan lain tetap utuh.');
        $this->assertSame(1, $kegiatan['user_id']);

        // Leading melihat catatan di panel status Data Kegiatan.
        session(['stub_role' => 'leading']);
        Livewire::test(Form::class, ['kegiatan' => $id])
            ->assertSeeHtml('data-catatan-pemeriksa')
            ->assertSee('Sasaran belum terukur.');
    }

    public function test_setujui_dengan_catatan_opsional_dan_tautan_berikutnya(): void
    {
        $lama = $this->diajukan('Pengajuan Lama', '2026-03-01T09:00:00+07:00');
        $baru = $this->diajukan('Pengajuan Baru', '2026-03-04T09:00:00+07:00');

        Livewire::test(Periksa::class, ['kegiatan' => $baru])
            ->call('minta', 'setujui')
            ->assertHasNoErrors()
            ->assertSee('Setujui kegiatan ini?')
            ->call('putuskan', 'setujui')
            ->assertDispatched('notify', message: 'Kegiatan disetujui — leading dapat mencetak dan menandatangani dokumennya.')
            ->assertSee('Menunggu arsip final')
            ->assertSee('Perlu perbaikan lagi?')
            ->assertSee('— wajib')
            ->assertSeeHtml('data-testid="periksa-berikutnya"')
            ->assertSeeHtml('href="'.route('pemeriksaan.periksa', ['kegiatan' => $lama]).'"');

        $kegiatan = app(KegiatanRepository::class)->temukan($baru);
        $this->assertSame('disetujui', $kegiatan['status']);
        $this->assertArrayNotHasKey('catatan', end($kegiatan['riwayat_status']));

        // Disetujui masih bisa dikembalikan (dengan catatan); setujui tidak lagi tersedia.
        Livewire::test(Periksa::class, ['kegiatan' => $baru])
            ->assertSet('konfirmasi', null)
            ->call('minta', 'setujui')
            ->assertDispatched('notify', type: 'error')
            ->assertSet('konfirmasi', null)
            ->set('catatan', 'Honor narasumber melebihi SBM.')
            ->call('minta', 'kembalikan')
            ->call('putuskan', 'kembalikan');
        $this->assertSame('dikembalikan', app(KegiatanRepository::class)->temukan($baru)['status']);
    }

    public function test_keputusan_ditolak_bila_berubah_di_tempat_lain_atau_bukan_admin(): void
    {
        $id = $this->diajukan('Pengajuan Ditarik');
        $halaman = Livewire::test(Periksa::class, ['kegiatan' => $id])->set('catatan', 'Lengkapi sasaran.');

        // Leading menarik pengajuan di tab lain.
        $data = app(KegiatanRepository::class)->temukan($id);
        $data['status'] = 'draf';
        $data['riwayat_status'][] = RiwayatStatus::entri('tarik', StatusKegiatan::Draf, ['name' => 'Muslimin, M.Pd.', 'role' => 'leading']);
        app(KegiatanRepository::class)->simpan($data, $id);

        $halaman->call('putuskan', 'kembalikan')
            ->assertDispatched('notify', type: 'error')
            ->assertSet('catatan', 'Lengkapi sasaran.')
            ->assertSee('Belum diajukan')
            ->assertSee('Pengajuan ditarik');
        $this->assertSame('draf', app(KegiatanRepository::class)->temukan($id)['status']);

        $lain = $this->diajukan('Pengajuan Lain');
        session(['stub_role' => 'leading']);
        Livewire::test(Periksa::class, ['kegiatan' => $lain])
            ->assertDontSee('Catatan untuk leading')
            ->call('putuskan', 'setujui')
            ->assertDispatched('notify', type: 'error');
        $this->assertSame('diperiksa', app(KegiatanRepository::class)->temukan($lain)['status']);
    }

    public function test_catatan_dibatasi_dan_dirapikan(): void
    {
        $id = $this->diajukan('Pengajuan Panjang');

        Livewire::test(Periksa::class, ['kegiatan' => $id])
            ->set('catatan', str_repeat('a', RiwayatStatus::MAKS_CATATAN + 1))
            ->call('minta', 'setujui')
            ->assertHasErrors(['catatan' => 'max'])
            ->assertSet('konfirmasi', null);

        $this->assertSame("Baris satu\n\nBaris dua", RiwayatStatus::rapikanCatatan("  Baris   satu \r\n\r\n\r\n\t Baris dua\n"));
        $this->assertArrayNotHasKey('catatan', RiwayatStatus::entri('setujui', StatusKegiatan::Disetujui, ['name' => 'A', 'role' => 'admin'], "  \n "));
    }
}
