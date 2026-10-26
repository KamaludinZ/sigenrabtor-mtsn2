<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Livewire\Kegiatan\MatrikJadwal;
use App\Support\Kegiatan\JadwalKegiatan;
use App\Support\Kegiatan\KonsistensiBulan;
use App\Support\Kegiatan\MatrikTahapan;
use App\Support\Stub\StubMatrikJadwalRepository;
use Livewire\Livewire;
use Tests\TestCase;

class KonsistensiBulanTest extends TestCase
{
    public function test_temuan_server_sama_dengan_kembaran_klien(): void
    {
        // Berkas yang sama dipakai tests/e2e/format.spec.js untuk periksaKonsistensiBulan() di resources/js/konsistensi-bulan.js.
        $kasus = json_decode(file_get_contents(base_path('tests/fixtures/konsistensi-bulan.json')), true);
        $this->assertNotEmpty($kasus);

        foreach ($kasus as $k) {
            $konsistensi = new KonsistensiBulan(
                $k['pelaksanaan'],
                $k['laporan'],
                MatrikTahapan::dari(['matrik' => $k['matrik']])->butir,
                JadwalKegiatan::dari(['jadwal' => $k['jadwal']])->baris,
            );
            $this->assertSame($k['temuan'], $konsistensi->temuan(), $k['keterangan']);
        }
    }

    public function test_ada_isian_dan_jumlah_peringatan(): void
    {
        $kosong = new KonsistensiBulan([7], 7, MatrikTahapan::dari([])->butir, JadwalKegiatan::dari([])->baris);
        $this->assertSame([[], false, 0], [$kosong->temuan(), $kosong->adaIsian(), $kosong->jumlahPeringatan()]);

        $contoh = StubMatrikJadwalRepository::contoh()[3];
        $tersalin = new KonsistensiBulan([11], 11, MatrikTahapan::dari($contoh)->butir, JadwalKegiatan::dari($contoh)->baris);
        $this->assertTrue($tersalin->adaIsian());
        $this->assertSame([6, 5], [count($tersalin->temuan()), $tersalin->jumlahPeringatan()]);
    }

    public function test_panel_konsistensi_di_halaman_matrik_jadwal(): void
    {
        // Contoh kegiatan 3 (November, laporan 27 November): matrik & jadwal sebagian tersalin dari workshop Juli.
        Livewire::test(MatrikJadwal::class, ['kegiatan' => 3])
            ->assertSeeHtml('x-data="panelKonsistensi({ pelaksanaan: JSON.parse(\'[11]\'), laporan: 11 })"')
            ->assertSeeHtml('data-testid="konsistensi-bulan" wire:ignore')
            ->assertSeeHtml('data-jumlah="6"')
            ->assertSeeHtml('data-testid="status-konsistensi">6 temuan')
            ->assertSeeHtmlInOrder([
                'Tahap Pelaksanaan belum ditandai di November (bulan pelaksanaan).',
                'Baris 4 dijadwalkan Juli, lebih dari 3 bulan sebelum pelaksanaan (November) — mungkin tersalin dari kegiatan lain.',
                'href="#baris-jadwal-4"',
                'Baris 6 dijadwalkan Desember, melewati bulan batas laporan (November).',
            ])
            ->assertSeeHtml('id="baris-jadwal-4"')
            ->assertSee('Hanya pengingat — tidak menghalangi Simpan.');

        // Matrik & jadwal belum diisi: belum ada yang diperiksa.
        Livewire::test(MatrikJadwal::class, ['kegiatan' => 1])
            ->assertSeeHtml('x-data="panelKonsistensi({ pelaksanaan: JSON.parse(\'[7]\'), laporan: 7 })"')
            ->assertSeeHtml('data-testid="status-konsistensi">Belum diisi')
            ->assertDontSeeHtml('data-tingkat="peringatan"');

        // Isi otomatis dari jadwal pelaksanaan selalu konsisten.
        $komponen = Livewire::test(MatrikJadwal::class, ['kegiatan' => 1])->call('isiDariPelaksanaan')->call('isiJadwalBaku');
        $this->assertSame([], $komponen->instance()->konsistensi->temuan());

        // Tanpa bulan pelaksanaan: satu saran menuju Data Kegiatan; tidak menghalangi simpan.
        $id = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Tanpa bulan', 'jadwal' => [['uraian' => 'Rapat', 'bulan' => [3]]]]);
        Livewire::test(MatrikJadwal::class, ['kegiatan' => $id])
            ->assertSeeHtml('x-data="panelKonsistensi({ pelaksanaan: [], laporan: null })"')
            ->assertSee('Bulan pelaksanaan belum diisi di Data Kegiatan — konsistensi bulan belum bisa diperiksa.')
            ->assertSeeHtml('href="#acuan"')
            ->call('simpan')
            ->assertHasNoErrors()
            ->assertDispatched('notify', message: 'Matrik & jadwal tersimpan.');
    }
}
