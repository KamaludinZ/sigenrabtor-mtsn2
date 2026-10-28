<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\KegiatanRepository;
use App\Support\Kegiatan\AksesKegiatan;
use App\Support\Pengaturan\KontrolTahunAktif;
use App\Support\Stub\StubData;
use Tests\TestCase;

class DataTahunAktifTest extends TestCase
{
    public function test_akun_leading_melihat_tahun_aktif_tanpa_rincian_admin(): void
    {
        $tahun = $this->getJson(route('tahun_aktif'))->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJson([
                'tahun' => 2026,
                'tercatat' => true,
                'terkunci' => false,
                'sk_rktm' => ['nomor' => null, 'tanggal' => null, 'tanggal_teks' => null, 'lengkap' => false],
                'master_tersedia' => true,
                'bisa_ganti' => false,
            ])
            ->assertJsonMissingPath('kesiapan')
            ->json();

        $terlihat = AksesKegiatan::saring(app(KegiatanRepository::class)->semua(), StubData::currentUser());
        $this->assertSame(count($terlihat), $tahun['jumlah_kegiatan']);
        $this->assertSame(array_keys(KontrolTahunAktif::DIPAKAI), array_column($tahun['dipakai'], 'kunci'));
        $this->assertSame([2026, 2025], array_column($tahun['pilihan'], 'tahun'));
        $this->assertSame(['tahun', 'is_active', 'is_locked', 'master_tersedia'], array_keys($tahun['pilihan'][0]), 'Jumlah kegiatan semua leading hanya untuk admin.');
    }

    public function test_admin_melihat_kesiapan_dan_mengikuti_ganti_tahun(): void
    {
        $this->withSession(['stub_role' => 'admin']);
        $tahun = $this->getJson(route('tahun_aktif'))->assertOk()
            ->assertJson(['tahun' => 2026, 'bisa_ganti' => true, 'jumlah_kegiatan' => $this->jumlahContohKegiatan()])
            ->assertJsonStructure(['kesiapan' => ['wajib_siap', 'wajib', 'butir' => [['kunci', 'judul', 'siap', 'wajib', 'keterangan']]]])
            ->json();
        $this->assertArrayHasKey('jumlah_kegiatan', $tahun['pilihan'][0]);

        KontrolTahunAktif::aktifkan(2025, ['name' => 'Koordinator PUSKOM']);
        $lalu = $this->getJson(route('tahun_aktif'))->assertOk()
            ->assertJson(['tahun' => 2025, 'terkunci' => true, 'jumlah_kegiatan' => 0, 'diaktifkan' => ['oleh' => 'Koordinator PUSKOM']])
            ->json();
        $this->assertTrue($lalu['sk_rktm']['lengkap']);
        $this->assertMatchesRegularExpression('/^\d{1,2} \S+ 2025$/', $lalu['sk_rktm']['tanggal_teks']);
        $this->assertSame(2025, collect($lalu['pilihan'])->firstWhere('is_active', true)['tahun']);
    }
}
