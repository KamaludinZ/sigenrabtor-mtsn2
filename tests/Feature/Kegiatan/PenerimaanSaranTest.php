<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Contracts\PejabatRepository;
use App\Contracts\PenyusunSaranKalimat;
use App\Contracts\RiwayatSaranAiRepository;
use App\Livewire\Kegiatan\KalimatAi;
use App\Support\Kegiatan\BatasAi;
use App\Support\Kegiatan\BatasAmanSaran;
use App\Support\Kegiatan\PermintaanSaranKalimat;
use App\Support\Stub\StubData;
use Livewire\Livewire;
use Tests\TestCase;

class PenerimaanSaranTest extends TestCase
{
    private function kegiatan(array $data = []): int
    {
        return app(KegiatanRepository::class)->simpan($data + ['nama_kegiatan' => 'Rapat Kerja Tahunan', 'nama_kegiatan_cover' => 'RAPAT KERJA TAHUNAN', 'leading' => '1.6', 'peserta_jumlah' => 40]);
    }

    public function test_minta_perbaikan_dari_teks_yang_disunting(): void
    {
        $riwayat = app(RiwayatSaranAiRepository::class);
        $halaman = Livewire::test(KalimatAi::class, ['kegiatan' => $this->kegiatan()])
            ->call('mintaSaran', 'tujuan');
        $pertama = $riwayat->semua()[0]['id'];

        $halaman->call('mintaPerbaikan')
            ->assertHasErrors(['instruksiSaran' => 'required'])
            ->set('suntingSaran', 'menghasilkan dokumen kurikulum yang lengkap, tepat waktu, dan sesuai kebutuhan.')
            ->set('instruksiSaran', '  lebih   singkat ')
            ->call('mintaPerbaikan')
            ->assertHasNoErrors()
            ->assertSet('instruksiSaran', '')
            ->assertSet('saranTerbuka', 'tujuan')
            ->assertSet('versiDipilih', 0);

        $this->assertSame(['menghasilkan dokumen kurikulum yang lengkap, tepat waktu, dan sesuai kebutuhan.', 'menghasilkan dokumen kurikulum yang lengkap.'], array_column($halaman->get('saran')['tujuan'], 'teks'));
        $baru = $riwayat->semua()[0];
        $this->assertSame('lebih singkat', $baru['instruksi']);
        $this->assertStringStartsWith('Teks yang diperbaiki: menghasilkan dokumen kurikulum', explode("\n", $baru['prompt'])[1]);
        $this->assertSame(['ditolak', 'ditolak', 'ditolak'], array_column(collect($riwayat->semua())->firstWhere('id', $pertama)['versi'], 'status'), 'Versi lama dianggap ditolak.');

        $halaman->set('suntingSaran', 'meningkatkan mutu pembelajaran.')
            ->set('instruksiSaran', 'tambahkan moderasi beragama')
            ->call('mintaPerbaikan');
        $this->assertSame('meningkatkan mutu pembelajaran, dengan menjunjung nilai moderasi beragama.', $halaman->get('saran')['tujuan'][0]['teks']);

        $halaman->set('suntingSaran', '  ')->set('instruksiSaran', 'lebih formal')->call('mintaPerbaikan')
            ->assertHasErrors('suntingSaran');

        // Batas harian habis saat memperbaiki: pesan tampil di modal, saran lama tetap.
        foreach (range(1, BatasAi::sisa(StubData::currentUser())) as $_) {
            BatasAi::catat(StubData::currentUser(), 'tujuan');
        }
        $halaman->call('pilihVersi', 0)->set('instruksiSaran', 'lebih singkat')->call('mintaPerbaikan')
            ->assertSeeHtml('data-testid="galat-modal-saran"')
            ->assertSee(BatasAi::pesanHabis())
            ->assertSet('saranTerbuka', 'tujuan')
            ->assertSet('instruksiSaran', 'lebih singkat');
    }

    public function test_pakai_diperiksa_aturan_isian_dan_tambah_di_akhir(): void
    {
        $id = $this->kegiatan(['latar_belakang' => 'Paragraf lama.', 'sasaran' => 'guru yang kompeten.']);

        // Kalimat terpotong ditolak oleh aturan isian: pesan di modal, isian tidak berubah.
        Livewire::test(KalimatAi::class, ['kegiatan' => $id])
            ->call('mintaSaran', 'sasaran')
            ->set('suntingSaran', 'dokumen yang dibutuhkan oleh')
            ->call('pakaiSaran')
            ->assertHasErrors('suntingSaran')
            ->assertHasNoErrors('form.sasaran')
            ->assertSet('form.sasaran', 'guru yang kompeten.')
            ->assertSet('saranTerbuka', 'sasaran')
            ->assertSet('teksSebelum', [])
            ->assertDontSeeHtml('data-testid="tambah-saran"')
            ->call('pakaiSaran', 'tambah')
            ->assertNotFound();

        $halaman = Livewire::test(KalimatAi::class, ['kegiatan' => $id])
            ->call('mintaSaran', 'latar_belakang')
            ->assertSeeHtml('data-testid="tambah-saran"')
            ->assertSee('Ganti dengan versi ini');
        $saran = $halaman->get('saran')['latar_belakang'][0]['teks'];
        $halaman->call('pakaiSaran', 'tambah')
            ->assertSet('form.latar_belakang', 'Paragraf lama. '.$saran)
            ->assertSet('teksSebelum', ['latar_belakang' => 'Paragraf lama.'])
            ->assertDispatched('notify', message: 'Saran AI ditambahkan ke latar belakang — periksa lalu simpan.', type: 'success')
            ->call('urungkanSaran', 'latar_belakang')
            ->assertSet('form.latar_belakang', 'Paragraf lama.');
    }

    public function test_peringatan_batas_aman_saran(): void
    {
        $kegiatan = ['nama_kegiatan' => 'Rapat Kerja 2026', 'peserta_jumlah' => 40, 'lama_hari' => 2];
        $this->assertSame([], BatasAmanSaran::peringatan('rapat kerja diikuti 40 peserta selama 2 hari pada tahun 2026.', $kegiatan));

        app(PejabatRepository::class)->simpan(StubData::tahunAktif()['tahun'], 'kepala', null, ['jabatan' => 'Kepala MTsN 2 Kota Malang', 'nama' => 'Drs. Ahmad Fiktif', 'nip' => '197001011995031001']);
        $peringatan = BatasAmanSaran::peringatan('Mendukung SK.9 dan IKSK 9.1 program 6.17.12 untuk 75 peserta bersama Drs. Ahmad Fiktif, NIP 197001011995031001.', $kegiatan);
        $this->assertSame([
            'Saran memuat NIP — hapus sebelum dipakai; NIP hanya tercetak di lembar pengesahan.',
            'Saran menyebut kode IKSK 9.1, SK.9, 6.17.12 — kode dipilih di Kode & Klasifikasi, bukan ditulis di kalimat.',
            'Saran memuat angka 75 yang tidak ada di data kegiatan — pastikan benar sebelum dipakai.',
            'Saran menyebut nama pejabat (Drs. Ahmad Fiktif) — nama pejabat tercetak otomatis di lembar pengesahan.',
        ], $peringatan);

        $this->app->instance(PenyusunSaranKalimat::class, new class implements PenyusunSaranKalimat
        {
            public function sarankan(PermintaanSaranKalimat $permintaan, string $penyedia = 'tiruan'): array
            {
                return [['judul' => 'Versi 1', 'ciri' => '', 'teks' => 'melibatkan 99 guru.'], ['judul' => 'Versi 2', 'ciri' => '', 'teks' => 'melibatkan seluruh guru.']];
            }
        });
        Livewire::test(KalimatAi::class, ['kegiatan' => $this->kegiatan()])
            ->call('mintaSaran', 'sasaran')
            ->assertSeeHtml('data-testid="peringatan-saran"')
            ->assertSee('Saran memuat angka 99 yang tidak ada di data kegiatan')
            ->call('pilihVersi', 1)
            ->assertDontSeeHtml('data-testid="peringatan-saran"');
    }
}
