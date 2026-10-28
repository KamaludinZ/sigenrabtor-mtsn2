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

    public function test_buat_dengan_ai_membuka_modal_saran_dan_menghitung_batas(): void
    {
        $halaman = Livewire::test(KalimatAi::class, ['kegiatan' => 1])
            ->assertSeeHtml('data-tombol-ai="tujuan" data-keadaan="siap"')
            ->assertSeeHtml('data-tombol-ai="jadwal" data-keadaan="tautan"')
            ->assertSee('Sisa 20 dari 20 permintaan AI hari ini.')
            ->call('mintaSaran', 'tujuan')
            ->assertSet('saranTerbuka', 'tujuan')
            ->assertSet('versiDipilih', 0)
            ->assertSeeHtml('data-testid="modal-saran-tujuan"')
            ->assertSee('Saran AI · Tujuan')
            ->assertSee('Pilih versi (3)')
            ->assertSeeHtml('data-tombol-ai="tujuan" data-keadaan="ada"')
            ->assertSee('Lihat saran (3)')
            ->assertSee('Sisa 19 dari 20 permintaan AI hari ini.');

        $saran = $halaman->get('saran')['tujuan'];
        $this->assertCount(3, $saran);
        $this->assertSame($saran[0]['teks'], $halaman->get('suntingSaran'));
        $this->assertSame(1, BatasAi::terpakai(StubData::currentUser()));
        // Teks di layar tidak berubah sebelum saran dipakai.
        $this->assertSame(app(KegiatanRepository::class)->temukan(1)['tujuan'], $halaman->get('form.tujuan'));

        $halaman->call('pilihVersi', 2)
            ->assertSet('versiDipilih', 2)
            ->assertSet('suntingSaran', $saran[2]['teks']);

        $halaman->call('tutupSaran')
            ->assertSet('saranTerbuka', null)
            ->assertDontSeeHtml('data-testid="modal-saran-tujuan"')
            ->call('bukaSaran', 'tujuan')
            ->assertSet('saranTerbuka', 'tujuan')
            ->assertSet('versiDipilih', 0)
            ->call('bukaSaran', 'sasaran')
            ->assertNotFound();

        Livewire::test(KalimatAi::class, ['kegiatan' => 1])->call('mintaSaran', 'jadwal')->assertNotFound();
        Livewire::test(KalimatAi::class, ['kegiatan' => 1])->call('mintaSaran', 'tujuan')->call('pilihVersi', 7)->assertNotFound();
    }

    public function test_pakai_sunting_urungkan_dan_tolak_saran(): void
    {
        $repo = app(KegiatanRepository::class);
        $id = $repo->simpan(['nama_kegiatan' => 'Workshop Penyusunan Kurikulum Madrasah dan Perangkat Pembelajaran', 'nama_kegiatan_cover' => 'WORKSHOP PENYUSUNAN KURIKULUM MADRASAH DAN PERANGKAT PEMBELAJARAN', 'leading' => '1.6', 'tujuan' => 'lama.']);

        $halaman = Livewire::test(KalimatAi::class, ['kegiatan' => $id])
            ->call('mintaSaran', 'tujuan')
            ->assertSee('Kegiatan Workshop Penyusunan Kurikulum Madrasah dan Perangkat Pembelajaran bertujuan')
            ->call('pilihVersi', 1)
            ->set('suntingSaran', '  meningkatkan mutu   pembelajaran.  ')
            ->call('pakaiSaran')
            ->assertSet('form.tujuan', 'meningkatkan mutu pembelajaran.')
            ->assertSet('saranTerbuka', null)
            ->assertSet('adaPerubahan', true)
            ->assertSet('teksSebelum', ['tujuan' => 'lama.'])
            ->assertSeeHtml('data-testid="saran-dipakai-tujuan"')
            ->assertDispatched('notify', message: 'Saran AI dipakai untuk tujuan — periksa lalu simpan.', type: 'success');
        $this->assertSame('lama.', $repo->temukan($id)['tujuan'], 'Belum tersimpan sampai Simpan.');

        // Pakai lagi versi lain lalu urungkan → kembali ke teks sebelum saran pertama dipakai.
        $halaman->call('bukaSaran', 'tujuan')
            ->call('pakaiSaran')
            ->call('urungkanSaran', 'tujuan')
            ->assertSet('form.tujuan', 'lama.')
            ->assertSet('teksSebelum', [])
            ->assertSet('adaPerubahan', false);

        // Teks kosong ditolak; nama cover dikapitalkan dan menjadi manual; simpan menetapkan saran.
        $halaman->call('mintaSaran', 'nama_cover')
            ->set('suntingSaran', '   ')
            ->call('pakaiSaran')
            ->assertHasErrors('suntingSaran')
            ->assertSet('saranTerbuka', 'nama_cover')
            ->call('pilihVersi', 2)
            ->assertHasNoErrors('suntingSaran')
            ->call('pakaiSaran')
            ->assertSet('form.nama_kegiatan_cover', 'WORKSHOP PENYUSUNAN KURIKULUM MADRASAH')
            ->assertSet('form.cover_manual', true)
            ->call('simpan')
            ->assertSet('teksSebelum', []);
        $this->assertSame('WORKSHOP PENYUSUNAN KURIKULUM MADRASAH', $repo->temukan($id)['nama_kegiatan_cover']);

        // Tolak: saran dibuang, isian tetap.
        $halaman->call('mintaSaran', 'sasaran')
            ->call('tolakSaran')
            ->assertSet('saranTerbuka', null)
            ->assertDispatched('notify', message: 'Saran AI ditolak — isian tidak berubah.', type: 'info')
            ->assertSeeHtml('data-tombol-ai="sasaran" data-keadaan="siap"');
        $this->assertArrayNotHasKey('sasaran', $halaman->get('saran'));

        Livewire::test(KalimatAi::class, ['kegiatan' => $id])->call('urungkanSaran', 'tujuan')->assertNotFound();
        $repo->simpan(['status' => 'final'] + $repo->temukan($id), $id);
        Livewire::test(KalimatAi::class, ['kegiatan' => $id])->call('urungkanSaran', 'tujuan')->assertForbidden();
    }

    public function test_penyedia_gagal_batas_habis_dan_kegiatan_terkunci(): void
    {
        $this->app->instance(PenyusunSaranKalimat::class, new class implements PenyusunSaranKalimat
        {
            public function sarankan(PermintaanSaranKalimat $permintaan, string $penyedia = 'tiruan'): array
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
