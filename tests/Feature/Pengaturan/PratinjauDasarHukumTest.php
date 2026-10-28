<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\DasarHukumRepository;
use App\Contracts\KegiatanRepository;
use App\Livewire\Kegiatan\Form;
use App\Livewire\Pengaturan\DasarHukum;
use App\Support\Dokumen\SusunanKak;
use App\Support\Kegiatan\KalimatKak;
use App\Support\Pengaturan\TeksBaku;
use App\Support\Stub\StubData;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Data dasar hukum & latar belakang di pratinjau dokumen hasil otomatis (halaman pengaturan & Data Kegiatan). */
class PratinjauDasarHukumTest extends TestCase
{
    private function penutupBerleading(): void
    {
        $repo = app(DasarHukumRepository::class);
        $repo->simpan(2026, ['kalimat_penutup' => 'Kegiatan {nama_kegiatan} dilaksanakan {leading}.'] + $repo->untukTahun(2026));
    }

    public function test_kurang_per_bagian_dan_tahun_dokumen(): void
    {
        $kegiatan = app(KegiatanRepository::class)->temukan(1);
        $kak = SusunanKak::dari($kegiatan);
        $this->assertSame([4 => ['matrik tahapan (tab Matrik & Jadwal)'], 7 => ['jadwal kegiatan (tab Matrik & Jadwal)']], $kak->kurangPerBagian);
        $this->assertSame($kak->kurang, array_merge(...array_values($kak->kurangPerBagian)));

        $lama = SusunanKak::dari($kegiatan, tahun: 2025);
        $this->assertSame('Tahun Anggaran 2025', $lama->cover['tahun']);
        $dasar = collect($lama->bagian)->firstWhere('nomor', 2)['isi'][0]['butir'];
        $this->assertCount(3, $dasar);
        $this->assertStringContainsString('Nomor Contoh 001/SK/RKTM/2025 tanggal 6 Januari 2025', $dasar[2]);
        $this->assertStringContainsString('Tahun Anggaran 2025', collect($lama->bagian)->firstWhere('nomor', 1)['isi'][0]['teks']);
    }

    public function test_dasar_hukum_kosong_ditandai_di_dokumen(): void
    {
        Storage::disk('local')->put(config('sigenrabtor.stub.dasar_hukum_file'), '{}');

        $kak = SusunanKak::dari(app(KegiatanRepository::class)->temukan(1));
        $this->assertSame([['jenis' => 'kosong', 'pesan' => 'Lengkapi dasar hukum TA 2026 (Pengaturan › Dasar hukum).', 'asal' => 'dasar_hukum', 'wajib' => false]], collect($kak->bagian)->firstWhere('nomor', 2)['isi']);
        $this->assertSame(['dasar hukum TA 2026 (Pengaturan › Dasar hukum)'], $kak->kurangPerBagian[2]);
        $this->assertTrue($kak->draf);
    }

    public function test_pratinjau_halaman_memakai_dokumen_hasil_otomatis_dan_contoh_terpilih(): void
    {
        session(['stub_role' => 'admin']);
        $kegiatan = app(KegiatanRepository::class)->temukan(2);
        $nama = KalimatKak::tanpaAwalanKegiatan($kegiatan['nama_kegiatan']);

        Livewire::test(DasarHukum::class)
            ->assertSet('contoh', '')
            ->assertSeeHtmlInOrder(['data-testid="pratinjau-dasar-hukum"', 'data-testid="pilih-contoh"', 'data-testid="pratinjau-kak"', 'data-bagian="1"', '1. Latar Belakang', 'data-bagian="2"', '2. Dasar Hukum'])
            ->assertDontSeeHtml('data-bagian="3"')
            ->assertDontSeeHtml('data-testid="pratinjau-kak-cover"')
            ->assertSeeHtml('value="2">'.e($nama).'</option>')
            ->set('contoh', '2')
            ->assertSet('contoh', '2')
            ->assertSee('yang dihasilkan otomatis untuk '.$nama.' · TA 2026.')
            ->assertSee('adalah kegiatan '.$nama.'.')
            ->set('contoh', '999')
            ->assertSet('contoh', '');

        Livewire::withQueryParams(['contoh' => '2', 'ta' => '2025'])->test(DasarHukum::class)
            ->assertSet('contoh', '2')
            ->assertSeeHtmlInOrder(['data-testid="pratinjau-kak"', 'Tahun Anggaran 2025', 'Nomor Contoh 001/SK/RKTM/2025 tanggal 6 Januari 2025']);
    }

    public function test_pratinjau_menunjukkan_kekurangan_bagian_awal(): void
    {
        session(['stub_role' => 'admin']);
        $this->penutupBerleading();
        $id = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Rapat Tanpa Leading']);

        Livewire::test(DasarHukum::class)
            ->set('contoh', (string) $id)
            ->assertSeeHtml('data-testid="pratinjau-dasar-kurang"')
            ->assertSee('Lengkapi unit leading (kalimat penutup latar belakang)')
            ->assertSee('Kegiatan Rapat Tanpa Leading dilaksanakan ….')
            ->set('contoh', '1')
            ->assertDontSeeHtml('data-testid="pratinjau-dasar-kurang"')
            ->assertSee('dilaksanakan Tim Penjamin Mutu.');
    }

    public function test_formulir_kegiatan_menampilkan_susunan_latar_belakang(): void
    {
        $this->penutupBerleading();
        $baku = StubData::latarBelakangBaku(2026);
        session(['stub_role' => 'admin']);

        Livewire::test(Form::class)
            ->assertSet('form.leading', '')
            ->set('form.nama_kegiatan', 'Kegiatan Rapat Koordinasi')
            ->assertSeeHtml('data-testid="susunan-latar"')
            ->assertSeeHtmlInOrder(['data-latar="baku"', e($baku), 'data-latar="khusus"', '[Paragraf khusus di atas — opsional]', 'data-latar="penutup"', 'Kegiatan Rapat Koordinasi dilaksanakan ….'])
            ->assertSeeHtml('data-testid="latar-kosong"')
            ->assertSee('Lengkapi unit leading agar kalimat penutup tercetak utuh.')
            ->set('form.latar_belakang', 'Koordinasi diperlukan karena jadwal padat.')
            ->set('form.leading', '1.1')
            ->assertSeeHtmlInOrder(['data-latar="khusus"', 'Koordinasi diperlukan karena jadwal padat.', 'data-latar="penutup"', 'Kegiatan Rapat Koordinasi dilaksanakan Waka Kurikulum.'])
            ->assertDontSeeHtml('data-testid="latar-kosong"');

        $this->assertSame(['{nama_kegiatan}' => 'Rapat Koordinasi', '{leading}' => 'Waka Kurikulum'], array_filter(TeksBaku::nilaiKegiatan(['nama_kegiatan' => 'Kegiatan Rapat Koordinasi', 'leading' => '1.1'])));
    }
}
