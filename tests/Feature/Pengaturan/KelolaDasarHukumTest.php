<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\DasarHukumRepository;
use App\Contracts\RiwayatPengaturanRepository;
use App\Support\Pengaturan\KelolaDasarHukum;
use App\Support\Pengaturan\PengaturanBerubah;
use App\Support\Stub\StubDasarHukumRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class KelolaDasarHukumTest extends TestCase
{
    private const ADMIN = ['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin', 'leadings' => []];

    /** @return list<string> */
    private function teks(int $tahun = 2026): array
    {
        return array_column(app(DasarHukumRepository::class)->untukTahun($tahun)['dasar_hukum'], 'teks');
    }

    private function riwayat(): string
    {
        return app(RiwayatPengaturanRepository::class)->semua()[0]['keterangan'];
    }

    /** @return array<string, string> */
    private function galat(callable $aksi): array
    {
        try {
            $aksi();
        } catch (ValidationException $e) {
            return array_map(fn (array $p) => $p[0], $e->errors());
        }
        $this->fail('Seharusnya ditolak.');
    }

    public function test_tambah_ubah_hapus_kembalikan_dan_pindah(): void
    {
        $versi = app(DasarHukumRepository::class)->untukTahun(2026)['diperbarui_pada'];
        $hasil = KelolaDasarHukum::tambah(2026, '  Peraturan Pemerintah Nomor 57 Tahun 2021   tentang SNP. ', self::ADMIN, $versi);
        $this->assertSame([5, 'Peraturan Pemerintah Nomor 57 Tahun 2021 tentang SNP'], [$hasil['nomor'], $hasil['butir']['teks']], 'Dirapikan: spasi tunggal, tanpa tanda baca akhir.');
        $this->assertSame('Dasar hukum TA 2026 — tambah butir 5: Peraturan Pemerintah Nomor 57 Tahun 2021 tentang SNP', $this->riwayat());

        $sisip = KelolaDasarHukum::tambah(2026, 'Undang-Undang Nomor 14 Tahun 2005 tentang Guru dan Dosen', self::ADMIN, posisi: 1);
        $this->assertSame(2, $sisip['nomor']);
        $this->assertSame('Undang-Undang Nomor 14 Tahun 2005 tentang Guru dan Dosen', $this->teks()[1]);

        $this->assertNull(KelolaDasarHukum::ubah(2026, $sisip['butir']['id'], 'Undang-Undang Nomor 14 Tahun 2005 tentang Guru dan Dosen;', self::ADMIN), 'Teks sama setelah dirapikan.');
        $this->assertSame(2, KelolaDasarHukum::ubah(2026, $sisip['butir']['id'], 'UU Nomor 14 Tahun 2005', self::ADMIN)['nomor']);
        $this->assertStringStartsWith('Dasar hukum TA 2026 — ubah butir 2: Undang-Undang Nomor 14 Tahun 2005', $this->riwayat());

        $this->assertSame(['dari' => 2, 'ke' => 6], KelolaDasarHukum::pindah(2026, $sisip['butir']['id'], 99, self::ADMIN), 'Tujuan dibatasi ke akhir daftar.');
        $this->assertNull(KelolaDasarHukum::pindah(2026, $sisip['butir']['id'], 5, self::ADMIN));
        $this->assertSame('UU Nomor 14 Tahun 2005', $this->teks()[5]);

        $terhapus = KelolaDasarHukum::hapus(2026, 'dh-2026-2', self::ADMIN);
        $this->assertSame(1, $terhapus['posisi']);
        $this->assertCount(5, $this->teks());
        $this->assertSame(2, KelolaDasarHukum::kembalikan(2026, $terhapus['butir'], $terhapus['posisi'], self::ADMIN));
        $this->assertSame('dh-2026-2', app(DasarHukumRepository::class)->untukTahun(2026)['dasar_hukum'][1]['id'], 'Id semula.');
        $this->assertSame(['id' => 'Butir ini masih ada di daftar.'], $this->galat(fn () => KelolaDasarHukum::kembalikan(2026, $terhapus['butir'], 1, self::ADMIN)));
        $this->assertSame('Peraturan Menteri Agama Nomor 10 Tahun 2010 tentang Organisasi dan Tata Kerja Kementerian Agama', app(DasarHukumRepository::class)->untukTahun(2026)['dasar_hukum'][1]['teks']);
        $this->assertSame(StubDasarHukumRepository::bawaan()['latar_belakang'], app(DasarHukumRepository::class)->untukTahun(2026)['latar_belakang'], 'Latar belakang tidak berubah.');
    }

    public function test_aturan_izin_dan_versi(): void
    {
        $this->assertSame(['teks' => 'Butir dasar hukum wajib diisi.'], $this->galat(fn () => KelolaDasarHukum::tambah(2026, ' ; ', self::ADMIN)));
        $this->assertSame(['teks' => 'Butir ini sudah ada di nomor 1.'], $this->galat(fn () => KelolaDasarHukum::tambah(2026, 'undang-undang republik indonesia nomor 20 tahun 2003 tentang sistem pendidikan nasional.', self::ADMIN)));
        $this->assertArrayHasKey('teks', $this->galat(fn () => KelolaDasarHukum::ubah(2026, 'dh-2026-1', 'Kegiatan {nama_kegiatan}', self::ADMIN)));
        $this->assertSame(['id' => 'Butir dasar hukum tidak ditemukan — mungkin sudah dihapus.'], $this->galat(fn () => KelolaDasarHukum::hapus(2026, 'tidak-ada', self::ADMIN)));

        for ($i = 5; $i <= 30; $i++) {
            KelolaDasarHukum::tambah(2026, "Peraturan Nomor {$i}", self::ADMIN);
        }
        $this->assertSame(['jumlah' => 'Dasar hukum paling banyak 30 butir.'], $this->galat(fn () => KelolaDasarHukum::tambah(2026, 'Peraturan Nomor 31', self::ADMIN)));

        $versiLama = app(DasarHukumRepository::class)->untukTahun(2026)['diperbarui_pada'];
        KelolaDasarHukum::hapus(2026, 'dh-2026-4', self::ADMIN);
        try {
            KelolaDasarHukum::hapus(2026, 'dh-2026-3', self::ADMIN, $versiLama);
            $this->fail('Versi lama harus ditolak.');
        } catch (PengaturanBerubah) {
            $this->assertCount(29, $this->teks(), 'Tidak ada yang terhapus.');
        }

        foreach ([fn () => KelolaDasarHukum::tambah(2025, 'Peraturan baru', self::ADMIN), fn () => KelolaDasarHukum::hapus(2026, 'dh-2026-1', ['role' => 'leading'])] as $aksi) {
            try {
                $aksi();
                $this->fail('Seharusnya ditolak.');
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertCount(3, $this->teks(2025));
    }

    public function test_endpoint_dasar_hukum(): void
    {
        $this->withSession(['stub_role' => 'leading'])->getJson(route('pengaturan.madrasah.dasar_hukum.data'))->assertForbidden();
        $this->withSession(['stub_role' => 'admin'])->getJson(route('pengaturan.madrasah.dasar_hukum.data'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('tahun', 2026)
            ->assertJsonPath('terkunci', false)
            ->assertJsonPath('butir.0', ['id' => 'dh-2026-1', 'nomor' => 1, 'teks' => 'Undang-Undang Republik Indonesia Nomor 20 Tahun 2003 tentang Sistem Pendidikan Nasional', 'cetak' => 'Undang-Undang Republik Indonesia Nomor 20 Tahun 2003 tentang Sistem Pendidikan Nasional;'])
            ->assertJsonPath('butir.3.cetak', 'Surat Keputusan Kepala MTsN 2 Kota Malang tentang Rencana Kerja Tahunan Madrasah (RKTM) MTsN 2 Kota Malang Tahun 2026.')
            ->assertJsonPath('maks_butir', 30)
            ->assertJsonPath('sk_kurang', false)
            ->assertJsonPath('penanda', ['{tahun}', '{no_sk_rktm}', '{tanggal_sk_rktm}']);
        $this->withSession(['stub_role' => 'admin'])->getJson(route('pengaturan.madrasah.dasar_hukum.data', ['ta' => 2025]))
            ->assertJsonPath('terkunci', true)
            ->assertJsonCount(3, 'butir');
        $this->withSession(['stub_role' => 'admin'])->getJson(route('pengaturan.madrasah.dasar_hukum.data', ['ta' => 2031]))->assertNotFound();
    }
}
