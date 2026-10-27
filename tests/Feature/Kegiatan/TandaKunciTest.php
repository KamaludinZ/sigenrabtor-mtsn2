<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\ArsipFinalRepository;
use App\Contracts\KegiatanRepository;
use App\Livewire\Kegiatan\Daftar;
use App\Livewire\Kegiatan\Form;
use App\Livewire\Kegiatan\Klasifikasi;
use App\Livewire\Kegiatan\MatrikJadwal;
use App\Livewire\Kegiatan\Rab;
use App\Livewire\Pemeriksaan\Periksa;
use App\Support\Dokumen\SusunanKak;
use App\Support\Dokumen\SusunanRab;
use App\Support\Kegiatan\KunciDokumen;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class TandaKunciTest extends TestCase
{
    /** Salinan contoh kegiatan 2 berstatus Final, dikunci admin pada 2 Maret 2026 pukul 10.00, dengan arsip DIPA. */
    private function final(): int
    {
        $data = app(KegiatanRepository::class)->temukan(2);
        unset($data['id']);
        $id = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Supervisi Final', 'status' => 'final', 'riwayat_status' => [
            ['aksi' => 'setujui', 'status' => 'disetujui', 'oleh' => 'Koordinator PUSKOM', 'peran' => 'admin', 'pada' => '2026-03-01T09:00:00+07:00'],
            ['aksi' => 'finalkan', 'status' => 'final', 'oleh' => 'Koordinator PUSKOM', 'peran' => 'admin', 'pada' => '2026-03-02T10:00:00+07:00'],
        ]] + $data);
        app(ArsipFinalRepository::class)->simpan($id, 'DIPA', UploadedFile::fake()->createWithContent('dipa.pdf', '%PDF-1.4'), ['id' => 1, 'name' => 'Muslimin, M.Pd.']);

        return $id;
    }

    public function test_jejak_kunci_dari_riwayat(): void
    {
        $kunci = KunciDokumen::dari(app(KegiatanRepository::class)->temukan($this->final()));

        $this->assertSame(['2026-03-02T10:00:00+07:00', 'Koordinator PUSKOM'], [$kunci->pada, $kunci->oleh]);
        $this->assertSame('Dikunci 2 Maret 2026, 10.00 oleh Koordinator PUSKOM.', $kunci->kalimat());
        $this->assertNull(KunciDokumen::dari(['status' => 'disetujui']));
        $this->assertSame('Dikunci.', KunciDokumen::dari(['status' => 'final'])->kalimat(), 'Final tanpa riwayat: waktu & pelaku dilewati.');
        $this->assertSame('Dikunci oleh Muslimin, M.Pd.', (new KunciDokumen(null, 'Muslimin, M.Pd.'))->kalimat(), 'Tanpa titik ganda setelah gelar.');
    }

    public function test_banner_kunci_sama_di_semua_tab_ruang_kerja(): void
    {
        $id = $this->final();

        foreach ([[Form::class, 'Isian'], [Klasifikasi::class, 'Kode'], [Rab::class, 'RAB'], [MatrikJadwal::class, 'Matrik &amp; jadwal']] as [$komponen, $subjek]) {
            Livewire::test($komponen, ['kegiatan' => $id])
                ->assertSeeHtml('data-testid="banner-terkunci" data-status="final"')
                ->assertSeeHtml($subjek.' terkunci — status Final')
                ->assertSee('Dikunci 2 Maret 2026, 10.00 oleh Koordinator PUSKOM.')
                ->assertSeeHtml('href="'.route('kegiatan.arsip', ['kegiatan' => $id, 'sumber' => 'DIPA']));
        }

        // Status terkunci selain Final: keterangan status, tanpa jejak kunci.
        Livewire::test(Klasifikasi::class, ['kegiatan' => 2])->assertDontSeeHtml('data-testid="banner-terkunci"');
        $data = app(KegiatanRepository::class)->temukan(2);
        app(KegiatanRepository::class)->simpan(['status' => 'diperiksa'] + $data, 2);
        Livewire::test(Rab::class, ['kegiatan' => 2])
            ->assertSee('RAB terkunci — status Diperiksa')
            ->assertSee('Sedang diperiksa admin.')
            ->assertDontSeeHtml('data-testid="jejak-kunci"');
    }

    public function test_cap_final_di_pratinjau_dan_daftar(): void
    {
        $id = $this->final();
        $kegiatan = app(KegiatanRepository::class)->temukan($id);

        $this->assertNotNull(SusunanKak::dari($kegiatan)->kunci);
        $this->assertNotNull(SusunanRab::dari($kegiatan)->kunci);
        $this->assertNull(SusunanKak::dari(app(KegiatanRepository::class)->temukan(1))->kunci);

        session(['stub_role' => 'admin']);
        Livewire::test(Periksa::class, ['kegiatan' => $id])
            ->assertSeeHtml('data-testid="cap-final"')
            ->assertSeeHtml('aria-label="Dokumen final terkunci sejak 2 Maret 2026, 10.00"')
            ->assertSee('Terkunci 2 Mar 2026')
            ->assertSee('Dikunci 2 Maret 2026, 10.00 oleh Koordinator PUSKOM.')
            ->set('dokumen', 'rab')
            ->assertSeeHtml('data-testid="cap-final"');

        Livewire::test(Form::class, ['kegiatan' => $id])
            ->set('pratinjauTerbuka', true)
            ->assertSeeHtml('data-testid="cap-final"');

        Livewire::test(Daftar::class)
            ->assertSeeHtml('data-testid="jejak-kunci-baris"')
            ->assertSeeHtml('title="Dikunci 2 Maret 2026, 10.00 oleh Koordinator PUSKOM."');
    }
}
