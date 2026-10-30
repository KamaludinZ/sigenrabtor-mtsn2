<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\ArsipFinalRepository;
use App\Contracts\KegiatanRepository;
use App\Livewire\Kegiatan\Form;
use App\Livewire\Kegiatan\PanelArsipFinal;
use App\Livewire\Pemeriksaan\Periksa;
use App\Support\Kegiatan\ArsipFinal;
use App\Support\Kegiatan\KelolaArsipFinal;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ArsipFinalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Pencatatan arsip ke Riwayat Dokumen (bernomor otomatis) diuji di PenomoranOtomatisTest.
        config(['sigenrabtor.riwayat.catat_unduhan' => false]);
    }

    /** Salinan contoh kegiatan 2 (DIPA + Komite) berstatus $status. */
    private function kegiatan(string $status = 'disetujui'): int
    {
        $data = app(KegiatanRepository::class)->temukan(2);
        unset($data['id']);

        return app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Supervisi Arsip', 'status' => $status, 'riwayat_status' => []] + $data);
    }

    private function pdf(string $nama = 'arsip-dipa.pdf', int $kb = 0): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nama, '%PDF-1.4 '.str_repeat('x', $kb * 1024));
    }

    public function test_panel_hanya_tampil_setelah_disetujui(): void
    {
        Livewire::test(Form::class, ['kegiatan' => 2])->assertDontSeeHtml('data-testid="panel-arsip-final"');

        $id = $this->kegiatan();
        Livewire::test(Form::class, ['kegiatan' => $id])
            ->assertSeeLivewire(PanelArsipFinal::class)
            ->assertSeeHtml('data-testid="final-terhalang"')
            ->assertSee('Unggah arsip final bertanda tangan untuk RAB DIPA dan RAB Komite sebelum menandai final.');

        Livewire::test(PanelArsipFinal::class, ['kegiatanId' => $id])
            ->assertSee('0 dari 2 diunggah')
            ->assertSeeHtml('data-testid="input-arsip-dipa"')
            ->assertSeeHtml('data-testid="input-arsip-komite"');
    }

    public function test_unggah_ganti_dan_hapus_arsip_per_sumber_dana(): void
    {
        $id = $this->kegiatan();
        $repo = app(ArsipFinalRepository::class);

        $panel = Livewire::test(PanelArsipFinal::class, ['kegiatanId' => $id])
            ->set('berkas.DIPA', $this->pdf('KAK RAB DIPA ttd.pdf', 300))
            ->assertHasNoErrors()
            ->assertDispatched('arsip-final-berubah')
            ->assertDispatched('notify', message: 'Arsip final RAB DIPA tersimpan.')
            ->assertSet('berkas', [])
            ->assertSee('KAK RAB DIPA ttd.pdf')
            ->assertSee('300 KB')
            ->assertSee('Diunggah Muslimin, M.Pd.')
            ->assertSee('1 dari 2 diunggah');

        $lama = $repo->untukKegiatan($id)['DIPA'];
        $this->assertSame(['sumber' => 'DIPA', 'nama_berkas' => 'KAK RAB DIPA ttd.pdf', 'diunggah_oleh' => 'Muslimin, M.Pd.', 'diunggah_oleh_id' => 1], array_intersect_key($lama, array_flip(['sumber', 'nama_berkas', 'diunggah_oleh', 'diunggah_oleh_id'])));
        Storage::disk('local')->assertExists($lama['path']);

        $panel->set('berkas.DIPA', $this->pdf('revisi.pdf'))
            ->assertDispatched('notify', message: 'Arsip final RAB DIPA diganti.');
        Storage::disk('local')->assertMissing($lama['path']);
        $this->assertSame('revisi.pdf', $repo->untukKegiatan($id)['DIPA']['nama_berkas']);

        $path = $repo->untukKegiatan($id)['DIPA']['path'];
        $panel->call('$set', 'konfirmasiHapus', 'DIPA')
            ->assertSee('Hapus arsip RAB DIPA?')
            ->call('hapus', 'DIPA')
            ->assertDispatched('notify', message: 'Arsip final RAB DIPA dihapus.')
            ->assertSee('0 dari 2 diunggah');
        Storage::disk('local')->assertMissing($path);
        $this->assertSame([], $repo->untukKegiatan($id));

        // Arsip terakhir yang melengkapi semua sumber dana langsung memfinalkan kegiatan; panel menjadi baca-saja.
        $panel->set('berkas.DIPA', $this->pdf('dipa.pdf'))
            ->set('berkas.Komite', $this->pdf('komite.pdf'))
            ->assertDispatched('notify', message: 'Arsip final RAB Komite tersimpan. '.KelolaArsipFinal::PESAN_FINAL)
            ->assertSee('2 dari 2 diunggah')
            ->assertDontSeeHtml('data-testid="arsip-lengkap"')
            ->assertSee('Data terkunci; hanya admin yang dapat membuka kunci.')
            ->assertDontSeeHtml('data-testid="input-arsip-dipa"');
        $this->assertSame('final', app(KegiatanRepository::class)->temukan($id)['status']);
    }

    public function test_berkas_selain_pdf_ditolak(): void
    {
        $id = $this->kegiatan();

        // Pemeriksaan server sama dengan klien (galatArsip): berlaku juga bila klien dilewati.
        Livewire::test(PanelArsipFinal::class, ['kegiatanId' => $id])
            ->set('berkas.DIPA', UploadedFile::fake()->image('foto.jpg'))
            ->assertHasErrors('berkas.DIPA')
            ->assertSee('Arsip final harus berupa berkas PDF.')
            ->set('berkas.DIPA', UploadedFile::fake()->createWithContent('palsu.pdf', 'bukan pdf'))
            ->assertSee('Berkas ini bukan PDF yang sah')
            ->set('berkas.DIPA', $this->pdf('besar.pdf', ArsipFinal::MAKS_KB + 1))
            ->assertSee('Ukuran arsip maksimal 10 MB')
            ->set('berkas.DIPA', $this->pdf('benar.pdf'))
            ->assertHasNoErrors();

        $this->assertSame(['DIPA'], array_keys(app(ArsipFinalRepository::class)->untukKegiatan($id)));
    }

    public function test_aturan_berkas_sama_dengan_klien(): void
    {
        $kasus = json_decode((string) file_get_contents(base_path('tests/fixtures/arsip-final.json')), true);

        foreach ($kasus as $k) {
            $this->assertSame($k['galat'], ArsipFinal::galatBerkas($k['nama'], $k['ukuran'], $k['kepala']), $k['nama']);
        }
    }

    public function test_arsip_terkunci_selama_final_dan_bisa_dibuka(): void
    {
        $id = $this->kegiatan('final');
        app(ArsipFinalRepository::class)->simpan($id, 'DIPA', $this->pdf('dipa.pdf'), ['id' => 1, 'name' => 'Muslimin, M.Pd.']);

        Livewire::test(PanelArsipFinal::class, ['kegiatanId' => $id])
            ->assertSee('Data terkunci; hanya admin yang dapat membuka kunci.')
            ->assertDontSeeHtml('data-testid="input-arsip-dipa"')
            ->assertSee('Lihat')
            ->set('berkas.Komite', $this->pdf('komite.pdf'))
            ->assertDispatched('notify', type: 'error');
        $this->assertSame(['DIPA'], array_keys(app(ArsipFinalRepository::class)->untukKegiatan($id)));

        $this->get(route('kegiatan.arsip', ['kegiatan' => $id, 'sumber' => 'DIPA']))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename=dipa.pdf');
        $this->get(route('kegiatan.arsip', ['kegiatan' => $id, 'sumber' => 'Komite']))->assertNotFound();

        session(['stub_role' => 'admin']);
        Livewire::test(Periksa::class, ['kegiatan' => $id])
            ->assertSeeHtml('data-testid="arsip-pemeriksaan"')
            ->assertSeeInOrder(['Arsip RAB DIPA', 'Muslimin, M.Pd.', 'Lihat PDF', 'Arsip RAB Komite', 'Belum diunggah']);
    }

    public function test_aturan_arsip(): void
    {
        $this->assertSame([], ArsipFinal::kurang(['nama_kegiatan' => 'Tanpa sumber'], []), 'Tanpa sumber dana tidak ada arsip wajib.');
        $this->assertSame(['Komite'], ArsipFinal::kurang(app(KegiatanRepository::class)->temukan(2), ['DIPA' => ['nama_berkas' => 'a.pdf']]));
        $this->assertSame('Unggah arsip final bertanda tangan untuk RAB DIPA sebelum menandai final.', ArsipFinal::pesanKurang(['DIPA']));
        $this->assertSame(['1 KB', '245 KB', '1,2 MB'], [ArsipFinal::ukuran(200), ArsipFinal::ukuran(245 * 1024), ArsipFinal::ukuran((int) (1.2 * 1024 * 1024))]);
    }
}
