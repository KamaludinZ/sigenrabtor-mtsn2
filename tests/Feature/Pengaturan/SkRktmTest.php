<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\DasarHukumRepository;
use App\Contracts\KegiatanRepository;
use App\Contracts\RiwayatPengaturanRepository;
use App\Contracts\TahunAnggaranRepository;
use App\Livewire\Pengaturan\DasarHukum;
use App\Support\Dokumen\SusunanKak;
use App\Support\Pengaturan\DaftarDasarHukum;
use App\Support\Pengaturan\KesiapanPengaturan;
use App\Support\Pengaturan\SkRktm;
use App\Support\Stub\StubData;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class SkRktmTest extends TestCase
{
    private const BUTIR_SK = 'Surat Keputusan Kepala MTsN 2 Kota Malang Nomor {no_sk_rktm} tanggal {tanggal_sk_rktm} tentang RKTM Tahun {tahun}';

    protected function setUp(): void
    {
        parent::setUp();
        session(['stub_role' => 'admin']);
    }

    private function riwayatTerakhir(): array
    {
        return app(RiwayatPengaturanRepository::class)->semua()[0];
    }

    public function test_aturan_isian_dan_nilai_cetak(): void
    {
        $this->assertSame(['nomor' => 'Contoh 001/SK/RKTM/2025', 'tanggal' => '2025-01-06'], SkRktm::untuk(2025));
        $this->assertSame(['nomor' => null, 'tanggal' => null], SkRktm::untuk(2026));
        $this->assertSame(['nomor' => null, 'tanggal' => null], SkRktm::untuk(1999));
        $this->assertTrue(SkRktm::lengkap(2025));
        $this->assertFalse(SkRktm::lengkap(2026));
        $this->assertSame(['{no_sk_rktm}' => 'Contoh 001/SK/RKTM/2025', '{tanggal_sk_rktm}' => '6 Januari 2025'], SkRktm::nilai(2025));

        $this->assertSame([], SkRktm::galat('', ''));
        $this->assertSame([], SkRktm::galat('12/SK/2026', '2025-12-30'));
        $this->assertSame(['nomor' => 'Nomor SK wajib diisi bersama tanggalnya.'], SkRktm::galat('', '2025-12-30'));
        $this->assertSame(['tanggal' => 'Tanggal SK wajib diisi bersama nomornya.'], SkRktm::galat('12/SK/2026', ''));
        $this->assertSame(['tanggal' => 'Tanggal tidak valid.'], SkRktm::galat('12/SK/2026', '2026-02-30'));
        $this->assertSame(['tanggal' => 'Tanggal tidak valid.'], SkRktm::galat('12/SK/2026', '30-12-2025'));
        $this->assertSame(['nomor' => 'Terlalu panjang (maksimal 100 karakter).'], SkRktm::galat(str_repeat('1', 101), '2026-01-02'));
        $this->assertSame('12/SK/2026', SkRktm::rapikanNomor("  12/SK/2026 \n"));

        $this->assertNull(SkRktm::peringatanTanggal('2025-12-30', 2026));
        $this->assertNull(SkRktm::peringatanTanggal('2026-01-05', 2026));
        $this->assertNull(SkRktm::peringatanTanggal(null, 2026));
        $this->assertSame('Tanggal SK (2024) tidak sejalan dengan TA 2026 — SK RKTM/RKAM biasanya ditetapkan akhir tahun sebelumnya atau awal tahun anggaran.', SkRktm::peringatanTanggal('2024-06-01', 2026));

        // Butir dasar hukum: penanda SK terisi, "…" selama kosong.
        $this->assertSame(['Surat Keputusan Kepala MTsN 2 Kota Malang Nomor Contoh 001/SK/RKTM/2025 tanggal 6 Januari 2025 tentang RKTM Tahun 2025.'], DaftarDasarHukum::cetak([self::BUTIR_SK], 2025));
        $this->assertSame(['Surat Keputusan Kepala MTsN 2 Kota Malang Nomor … tanggal … tentang RKTM Tahun 2026.'], DaftarDasarHukum::cetak([self::BUTIR_SK], 2026));
        $this->assertTrue(DaftarDasarHukum::skKurang([self::BUTIR_SK], 2026));
        $this->assertFalse(DaftarDasarHukum::skKurang([self::BUTIR_SK], 2025));
        $this->assertFalse(DaftarDasarHukum::skKurang(['Tanpa SK'], 2026));
        $this->assertNull(DaftarDasarHukum::galat(self::BUTIR_SK, []));
    }

    public function test_repositori_menyimpan_sk_per_tahun(): void
    {
        $repo = app(TahunAnggaranRepository::class);
        $repo->simpanSk(2026, '12/SK/2026', '2025-12-30');

        $this->assertSame(['nomor' => '12/SK/2026', 'tanggal' => '2025-12-30'], SkRktm::untuk(2026));
        $this->assertSame('Contoh 001/SK/RKTM/2025', SkRktm::untuk(2025)['nomor'], 'Tahun lain tetap.');
        $this->assertTrue($repo->aktif()['is_active']);
        $this->assertTrue(collect(KesiapanPengaturan::tahunAktif()->butir)->firstWhere('kunci', 'sk_rktm')['siap']);

        $repo->simpanSk(2026, null, null);
        $this->assertSame(['nomor' => null, 'tanggal' => null], SkRktm::untuk(2026));

        $this->expectException(InvalidArgumentException::class);
        $repo->simpanSk(2030, '1', '2030-01-01');
    }

    public function test_isi_ubah_dan_kosongkan_sk_dari_halaman(): void
    {
        $komponen = Livewire::test(DasarHukum::class)
            ->assertSeeHtml('data-testid="sk-kosong"')
            ->assertSee('Belum dipakai di dasar hukum')
            ->call('ubahSk')
            ->assertSet('suntingSk', true)
            ->assertSeeHtml('data-testid="isian-sk"')
            ->set('isianSk.nomor', '12/SK/2026')
            ->call('simpanSk')
            ->assertHasErrors(['isianSk.tanggal'])
            ->assertSee('Tanggal SK wajib diisi bersama nomornya.')
            ->set('isianSk.tanggal', '2026-13-01')
            ->call('simpanSk')
            ->assertSee('Tanggal tidak valid.')
            ->assertSet('suntingSk', true);
        $this->assertNull(SkRktm::untuk(2026)['nomor']);

        $komponen->set('isianSk', ['nomor' => '  12/SK/2026 ', 'tanggal' => '2025-12-30'])
            ->call('simpanSk')
            ->assertHasNoErrors()
            ->assertSet('suntingSk', false)
            ->assertDispatched('notify', message: 'SK RKTM/RKAM TA 2026 tersimpan.', type: 'success')
            ->assertSeeHtmlInOrder(['data-sk="nomor"', '12/SK/2026', 'data-sk="tanggal"', '30 Desember 2025']);
        $this->assertSame(['nomor' => '12/SK/2026', 'tanggal' => '2025-12-30'], SkRktm::untuk(2026));
        $this->assertSame('ubah_sk_rktm', $this->riwayatTerakhir()['aksi']);
        $this->assertSame('SK RKTM/RKAM TA 2026 — Nomor: — → 12/SK/2026; Tanggal: — → 30 Desember 2025', $this->riwayatTerakhir()['keterangan']);

        $komponen->call('ubahSk')->assertSet('isianSk', ['nomor' => '12/SK/2026', 'tanggal' => '2025-12-30'])
            ->call('simpanSk')
            ->assertDispatched('notify', message: 'Tidak ada yang berubah.', type: 'info');

        $komponen->call('ubahSk')->set('isianSk', ['nomor' => '12/SK/2026', 'tanggal' => '2024-03-01'])->call('simpanSk')
            ->assertSee('Tanggal SK (2024) tidak sejalan dengan TA 2026');

        $komponen->call('ubahSk')->set('isianSk', ['nomor' => '', 'tanggal' => ''])->call('simpanSk')
            ->assertDispatched('notify', message: 'SK RKTM/RKAM TA 2026 dikosongkan.', type: 'success')
            ->assertSeeHtml('data-testid="sk-kosong"');
        $this->assertSame(['nomor' => null, 'tanggal' => null], SkRktm::untuk(2026));
    }

    public function test_butir_berpenanda_sk_dan_dokumen_kak(): void
    {
        $repo = app(DasarHukumRepository::class);
        $pengaturan = $repo->untukTahun(2026);
        $repo->simpan(2026, ['dasar_hukum' => [...$pengaturan['dasar_hukum'], ['teks' => self::BUTIR_SK]]] + $pengaturan);
        $kegiatan = app(KegiatanRepository::class)->temukan(1);
        $penanda = 'nomor & tanggal SK RKTM/RKAM (Pengaturan › Dasar hukum)';

        Livewire::test(DasarHukum::class)
            ->assertSee('Tercetak di butir dasar hukum nomor 5 — sementara tercetak "…" dan KAK/TOR ditandai belum lengkap.')
            ->assertSee('Nomor … tanggal … tentang RKTM Tahun 2026.');
        $this->assertContains($penanda, SusunanKak::dari($kegiatan)->kurang);

        app(TahunAnggaranRepository::class)->simpanSk(2026, '12/SK/2026', '2025-12-30');
        Livewire::test(DasarHukum::class)
            ->assertSee('Tercetak di butir dasar hukum nomor 5.')
            ->assertSee('Nomor 12/SK/2026 tanggal 30 Desember 2025 tentang RKTM Tahun 2026.');
        $this->assertNotContains($penanda, SusunanKak::dari($kegiatan)->kurang);
        $this->assertSame('Surat Keputusan Kepala MTsN 2 Kota Malang Nomor 12/SK/2026 tanggal 30 Desember 2025 tentang RKTM Tahun 2026.', StubData::dasarHukum(2026)[4]);
    }

    public function test_tahun_terkunci_hanya_dilihat(): void
    {
        Livewire::test(DasarHukum::class)
            ->set('tahun', '2025')
            ->assertSeeHtmlInOrder(['data-sk="nomor"', 'Contoh 001/SK/RKTM/2025', '6 Januari 2025'])
            ->assertSee('Tercetak di butir dasar hukum nomor 3.')
            ->assertDontSeeHtml('wire:click="ubahSk"')
            ->call('ubahSk')
            ->assertSet('suntingSk', false)
            ->set('suntingSk', true)
            ->set('isianSk', ['nomor' => 'Paksa', 'tanggal' => '2025-01-01'])
            ->call('simpanSk');
        $this->assertSame('Contoh 001/SK/RKTM/2025', SkRktm::untuk(2025)['nomor']);
    }
}
