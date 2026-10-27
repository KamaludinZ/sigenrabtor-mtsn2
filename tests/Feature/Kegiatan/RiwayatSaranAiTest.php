<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Contracts\RiwayatSaranAiRepository;
use App\Livewire\Kegiatan\KalimatAi;
use App\Livewire\Kegiatan\MatrikJadwal;
use App\Livewire\SaranAi\Riwayat;
use App\Support\Kegiatan\RiwayatSaranAi;
use App\Support\Stub\StubData;
use Livewire\Livewire;
use Tests\TestCase;

class RiwayatSaranAiTest extends TestCase
{
    public function test_halaman_riwayat_dengan_contoh_ringkasan_dan_menu(): void
    {
        $this->get(route('dashboard'))->assertSeeHtml('href="'.route('saran_ai.index').'"');
        $this->get(route('saran_ai.index'))->assertOk()->assertSeeLivewire(Riwayat::class)->assertSee('<title>Riwayat Saran AI · ', false);

        $this->assertSame(['permintaan' => 4, 'versi' => 10, 'status' => ['menunggu' => 2, 'diterima' => 1, 'diedit' => 1, 'ditolak' => 6], 'dipakai' => 2], RiwayatSaranAi::ringkasan(RiwayatSaranAi::untuk(StubData::currentUser())));

        Livewire::test(Riwayat::class)
            ->assertSeeHtmlInOrder(['data-permintaan="contoh-saran-4"', 'data-permintaan="contoh-saran-3"', 'data-permintaan="contoh-saran-2"', 'data-permintaan="contoh-saran-1"'])
            ->assertSee('Dipakai: Versi 2 (diedit)')
            ->assertSee('Semua ditolak')
            ->assertSee('Belum diputuskan')
            ->assertSeeHtml('data-testid="teks-dipakai"')
            ->assertSee('dokumen kurikulum madrasah yang lengkap, disahkan, dan siap diterapkan.')
            ->assertSee('Instruksi: lebih singkat')
            ->assertSee('Koordinator PUSKOM')
            ->assertSee('AI tiruan')
            ->assertSeeHtml('href="'.route('kegiatan.kalimat', ['kegiatan' => 1]).'#kalimat-tujuan"')
            ->assertSeeHtml('href="'.route('kegiatan.matrik', ['kegiatan' => 3]).'"');
    }

    public function test_saringan_dan_cakupan_akun(): void
    {
        Livewire::test(Riwayat::class)
            ->set('bagian', 'sasaran')
            ->assertSeeHtml('data-permintaan="contoh-saran-2"')
            ->assertDontSeeHtml('data-permintaan="contoh-saran-1"')
            ->set('bagian', '')
            ->set('status', 'diedit')
            ->assertSeeHtml('data-permintaan="contoh-saran-2"')
            ->assertDontSeeHtml('data-permintaan="contoh-saran-3"')
            ->set('status', '')
            ->set('kegiatanId', 2)
            ->assertSeeHtml('data-permintaan="contoh-saran-3"')
            ->assertDontSeeHtml('data-permintaan="contoh-saran-1"')
            ->set('kegiatanId', 0)
            ->set('cari', 'perangkat pembelajaran')
            ->assertSeeHtml('data-permintaan="contoh-saran-1"')
            ->assertDontSeeHtml('data-permintaan="contoh-saran-4"')
            ->set('cari', 'tidak ada yang cocok')
            ->assertSeeHtml('data-testid="saran-ai-kosong"')
            ->assertSee('Tidak ada saran yang cocok dengan saringan.')
            ->call('aturUlang')
            ->assertSet('cari', '')
            ->assertSeeHtml('data-permintaan="contoh-saran-1"');

        // Kegiatan leading lain hanya terlihat oleh admin.
        $lain = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Lomba Sains', 'leading' => '1.1']);
        $id = RiwayatSaranAi::catat($lain, 'tujuan', 'Bagian: Tujuan', '', [['judul' => 'Versi 1', 'ciri' => '', 'teks' => 'menumbuhkan minat sains.']], ['id' => 3]);
        $this->assertNotContains($id, array_column(RiwayatSaranAi::untuk(StubData::currentUser()), 'id'));
        session(['stub_role' => 'admin']);
        $this->assertContains($id, array_column(RiwayatSaranAi::untuk(StubData::currentUser()), 'id'));

        Livewire::withQueryParams(['bagian' => 'asing', 'status' => 'x', 'kegiatan' => -4])->test(Riwayat::class)
            ->assertSet('bagian', '')
            ->assertSet('status', '')
            ->assertSet('kegiatanId', 0);
    }

    public function test_pencatatan_dari_kalimat_kak_dan_usulan_jadwal(): void
    {
        $repo = app(KegiatanRepository::class);
        $repo->simpan(['konteks' => 'Narasumber: pengawas madrasah, NIP 198001102006041001.'] + $repo->temukan(1), 1);
        $riwayat = app(RiwayatSaranAiRepository::class);

        $halaman = Livewire::test(KalimatAi::class, ['kegiatan' => 1])
            ->assertSeeHtml('href="'.route('saran_ai.index', ['kegiatan' => 1]).'"')
            ->call('mintaSaran', 'tujuan');
        $baru = $riwayat->semua()[0];
        $this->assertSame(['tujuan', 1, ['menunggu', 'menunggu', 'menunggu']], [$baru['bagian'], $baru['kegiatan_id'], array_column($baru['versi'], 'status')]);
        $this->assertStringContainsString("Bagian: Tujuan (Bagian 3A · Tujuan)\nTeks saat ini: ", $baru['prompt']);
        $this->assertStringContainsString('- Nama kegiatan: Workshop Penyusunan Kurikulum', $baru['prompt']);
        $this->assertStringNotContainsString('198001102006041001', $baru['prompt'], 'NIP tidak pernah terkirim.');

        $halaman->call('pilihVersi', 1)->set('suntingSaran', 'meningkatkan mutu pembelajaran.')->call('pakaiSaran');
        $baru = $riwayat->semua()[0];
        $this->assertSame(['ditolak', 'diedit', 'ditolak'], array_column($baru['versi'], 'status'));
        $this->assertSame('meningkatkan mutu pembelajaran.', $baru['versi'][1]['teks_dipakai']);
        $this->assertNotNull($baru['diputuskan_pada']);

        $halaman->call('mintaSaran', 'sasaran')->call('pakaiSaran');
        $this->assertSame(['diterima', 'ditolak', 'ditolak'], array_column($riwayat->semua()[0]['versi'], 'status'));
        $halaman->call('mintaSaran', 'pelaksanaan')->call('tolakSaran');
        $this->assertSame(['ditolak', 'ditolak', 'ditolak'], array_column($riwayat->semua()[0]['versi'], 'status'));

        // Usulan jadwal AI ikut tercatat (bagian jadwal), versinya berupa baris "uraian — bulan".
        Livewire::test(MatrikJadwal::class, ['kegiatan' => 1])
            ->set('usulJenis', 'workshop')
            ->call('buatUsulJadwal')
            ->call('pakaiUsulan', 1);
        $jadwal = $riwayat->semua()[0];
        $this->assertSame('jadwal', $jadwal['bagian']);
        $this->assertStringContainsString('Pelaksanaan workshop — ', $jadwal['versi'][0]['teks']);
        $this->assertSame('diterima', $jadwal['versi'][1]['status']);
        $this->assertStringContainsString('Jenis kegiatan: ', $jadwal['prompt']);

        Livewire::test(MatrikJadwal::class, ['kegiatan' => 1])->set('usulJenis', 'umum')->call('buatUsulJadwal')->call('tolakUsulan');
        $this->assertSame(['ditolak'], array_values(array_unique(array_column($riwayat->semua()[0]['versi'], 'status'))));
    }
}
