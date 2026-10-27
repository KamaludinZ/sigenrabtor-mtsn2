<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Contracts\PenyusunSaranKalimat;
use App\Livewire\Kegiatan\KalimatAi;
use App\Livewire\Kegiatan\MatrikJadwal;
use App\Support\Kegiatan\BatasAi;
use App\Support\Kegiatan\GagalUsulanAi;
use App\Support\Kegiatan\PermintaanSaranKalimat;
use App\Support\Kegiatan\TombolAi;
use App\Support\Stub\StubData;
use App\Support\Stub\StubSaranKalimat;
use Livewire\Livewire;
use Tests\TestCase;

class BuatDenganAiTest extends TestCase
{
    private function habiskanBatas(): void
    {
        foreach (range(1, BatasAi::batas()) as $_) {
            BatasAi::catat(StubData::currentUser(), 'tujuan');
        }
    }

    public function test_keadaan_tombol_menurut_urutan_aturan(): void
    {
        $nama = ['nama_kegiatan' => 'Rapat'];
        $saran = [['judul' => 'Versi 1', 'ciri' => '', 'teks' => 'a']];

        $this->assertSame(['tautan', 'Usulkan di Matrik & Jadwal'], array_slice(array_values(TombolAi::keadaan('jadwal', $nama, true, 5)), 0, 2));
        $this->assertSame('Kegiatan terkunci — kalimatnya tidak bisa diubah.', TombolAi::keadaan('tujuan', $nama, false, 5, $saran)['keterangan']);
        $this->assertSame('Isi nama kegiatan dulu di Data Kegiatan.', TombolAi::keadaan('tujuan', ['nama_kegiatan' => ' '], true, 5)['keterangan']);
        $this->assertSame(['ada', 'Lihat saran (1)', 1], [...array_slice(array_values(TombolAi::keadaan('tujuan', $nama, true, 0, $saran)), 0, 2), TombolAi::keadaan('tujuan', $nama, true, 0, $saran)['jumlah']]);
        $this->assertSame(['nonaktif', BatasAi::pesanHabis()], [TombolAi::keadaan('tujuan', $nama, true, 0, [], 'x')['status'], TombolAi::keadaan('tujuan', $nama, true, 0, [], 'x')['keterangan']]);
        $this->assertSame(['gagal', 'Coba lagi', 'Server AI sibuk.'], array_slice(array_values(TombolAi::keadaan('tujuan', $nama, true, 3, [], 'Server AI sibuk.')), 0, 3));
        $this->assertSame(['siap', 'Buat dengan AI', 'Sisa 3 permintaan AI hari ini.'], array_slice(array_values(TombolAi::keadaan('tujuan', $nama, true, 3)), 0, 3));
    }

    public function test_buat_dengan_ai_menampilkan_saran_dan_menghitung_batas(): void
    {
        $halaman = Livewire::test(KalimatAi::class, ['kegiatan' => 1])
            ->assertSeeHtml('data-tombol-ai="tujuan" data-keadaan="siap"')
            ->assertSeeHtml('data-tombol-ai="jadwal" data-keadaan="tautan"')
            ->assertSee('Sisa 20 dari 20 permintaan AI hari ini.')
            ->call('mintaSaran', 'tujuan')
            ->assertSet('saranTerbuka', 'tujuan')
            ->assertSeeHtml('data-testid="saran-tujuan"')
            ->assertSeeHtml('data-tombol-ai="tujuan" data-keadaan="ada"')
            ->assertSee('Lihat saran (3)')
            ->assertSee('3 saran AI untuk tujuan')
            ->assertSee('Sisa 19 dari 20 permintaan AI hari ini.');

        $this->assertCount(3, $halaman->get('saran')['tujuan']);
        $this->assertSame(1, BatasAi::terpakai(StubData::currentUser()));
        // Teks di layar tidak berubah sebelum saran dipakai.
        $this->assertSame(app(KegiatanRepository::class)->temukan(1)['tujuan'], $halaman->get('form.tujuan'));

        $halaman->call('bukaSaran', 'tujuan')
            ->assertSet('saranTerbuka', null)
            ->assertDontSeeHtml('data-testid="saran-tujuan"')
            ->call('bukaSaran', 'tujuan')
            ->assertSet('saranTerbuka', 'tujuan')
            ->call('tutupSaran')
            ->assertSet('saranTerbuka', null)
            ->call('bukaSaran', 'sasaran')
            ->assertNotFound();

        Livewire::test(KalimatAi::class, ['kegiatan' => 1])->call('mintaSaran', 'jadwal')->assertNotFound();
    }

    public function test_penyedia_gagal_batas_habis_dan_kegiatan_terkunci(): void
    {
        $this->app->instance(PenyusunSaranKalimat::class, new class implements PenyusunSaranKalimat
        {
            public function sarankan(PermintaanSaranKalimat $permintaan): array
            {
                throw new GagalUsulanAi('Server AI sekolah tidak menjawab.');
            }
        });

        Livewire::test(KalimatAi::class, ['kegiatan' => 1])
            ->call('mintaSaran', 'sasaran')
            ->assertSet('galatAi', ['sasaran' => 'Server AI sekolah tidak menjawab.'])
            ->assertSeeHtml('data-testid="galat-ai-sasaran"')
            ->assertSeeHtml('data-tombol-ai="sasaran" data-keadaan="gagal"')
            ->assertSee('Coba lagi');
        $this->assertSame(0, BatasAi::terpakai(StubData::currentUser()), 'Permintaan gagal tidak dihitung.');

        $this->app->forgetInstance(PenyusunSaranKalimat::class);
        $this->app->singleton(PenyusunSaranKalimat::class, StubSaranKalimat::class);
        $this->habiskanBatas();
        Livewire::test(KalimatAi::class, ['kegiatan' => 1])
            ->assertSeeHtml('data-tombol-ai="tujuan" data-keadaan="nonaktif"')
            ->assertSee(BatasAi::pesanHabis())
            ->call('mintaSaran', 'tujuan')
            ->assertSet('saran', [])
            ->assertSet('galatAi', ['tujuan' => BatasAi::pesanHabis()]);
        // Batas yang sama berlaku untuk usulan jadwal AI.
        Livewire::test(MatrikJadwal::class, ['kegiatan' => 1])
            ->set('usulJenis', 'umum')
            ->call('buatUsulJadwal')
            ->assertSet('usulGagal', BatasAi::pesanHabis());

        $repo = app(KegiatanRepository::class);
        $id = $repo->simpan(['nama_kegiatan' => 'Rapat Final', 'leading' => '1.6', 'status' => 'final']);
        Livewire::test(KalimatAi::class, ['kegiatan' => $id])
            ->assertSeeHtml('data-tombol-ai="tujuan" data-keadaan="nonaktif"')
            ->call('mintaSaran', 'tujuan')
            ->assertForbidden();
    }

    public function test_saran_dirapikan_dan_saran_tiruan_per_bagian(): void
    {
        $this->assertSame([
            ['judul' => 'A', 'ciri' => 'Formal', 'teks' => 'RAPAT KERJA TAHUNAN'],
            ['judul' => 'Versi 2', 'ciri' => '', 'teks' => 'RAPAT KERJA'],
        ], KalimatAi::rapikanSaran('nama_cover', [
            ['judul' => ' A ', 'ciri' => 'Formal', 'teks' => "rapat   kerja\ntahunan"],
            ['judul' => '', 'teks' => 'Rapat kerja'],
            ['judul' => 'B', 'teks' => 'RAPAT KERJA TAHUNAN'],
            ['judul' => 'C', 'teks' => '  '],
            'bukan versi',
        ]));
        $this->assertCount(3, KalimatAi::rapikanSaran('tujuan', array_map(fn ($i) => ['teks' => "teks {$i}"], range(1, 5))));

        $permintaan = fn (string $bagian, array $bahan = []) => new PermintaanSaranKalimat(1, $bagian, '', '', $bahan, '', 'Kegiatan Workshop Penyusunan Kurikulum Madrasah dan Perangkat Pembelajaran');
        $stub = new StubSaranKalimat;
        $cover = $stub->sarankan($permintaan('nama_cover'));
        $this->assertSame(['WORKSHOP PENYUSUNAN KURIKULUM MADRASAH DAN PERANGKAT PEMBELAJARAN', 'WORKSHOP PENYUSUNAN KURIKULUM MADRASAH'], [$cover[0]['teks'], $cover[2]['teks']]);
        $tujuan = $stub->sarankan($permintaan('tujuan', ['Output RKTM' => 'Kegiatan Pengembangan Manajemen Sekolah']));
        $this->assertStringStartsWith('menghasilkan kegiatan Pengembangan Manajemen Sekolah', $tujuan[0]['teks']);
        $this->assertSame([], $stub->sarankan($permintaan('jadwal')));
    }
}
