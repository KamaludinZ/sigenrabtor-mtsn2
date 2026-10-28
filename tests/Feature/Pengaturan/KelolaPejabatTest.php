<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\PejabatRepository;
use App\Contracts\RiwayatPengaturanRepository;
use App\Support\Pengaturan\KelolaPejabat;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class KelolaPejabatTest extends TestCase
{
    private const ADMIN = ['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin', 'leadings' => []];

    private function riwayat(): array
    {
        return app(RiwayatPengaturanRepository::class)->semua()[0];
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

    public function test_simpan_pejabat_dan_aturan(): void
    {
        $hasil = KelolaPejabat::simpan(2026, 'kepala', ['jabatan' => ' Kepala  MTsN 2 Kota Malang ', 'nama' => 'Dra. Hj. Siti Aminah, M.Pd.', 'nip' => '19700101 199503 2 001'], self::ADMIN);
        $this->assertSame(['jabatan' => 'Kepala MTsN 2 Kota Malang', 'nama' => 'Dra. Hj. Siti Aminah, M.Pd.', 'nip' => '197001011995032001'], $hasil['pejabat']);
        $this->assertSame('197001011995032001', app(PejabatRepository::class)->untukTahun(2026)['kepala']['nip']);
        $this->assertStringStartsWith('Kepala Madrasah TA 2026 — ', $this->riwayat()['keterangan']);
        $this->assertNull(KelolaPejabat::simpan(2026, 'kepala', $hasil['pejabat'], self::ADMIN)['ringkasan'], 'Tanpa perubahan.');

        KelolaPejabat::simpan(2026, 'leading:1.6', ['jabatan' => 'Ketua Tim Penjamin Mutu', 'nama' => 'Muslimin, M.Pd.', 'nip' => ''], self::ADMIN);
        $this->assertSame('Muslimin, M.Pd.', app(PejabatRepository::class)->untukTahun(2026)['leading']['1.6']['nama']);
        $this->assertSame('Pejabat 1.6 Tim Penjamin Mutu', KelolaPejabat::judul('leading:1.6'));

        $this->assertSame(['nip' => 'NIP Kepala Madrasah wajib diisi — tercetak di kolom "Menyetujui".'], $this->galat(fn () => KelolaPejabat::simpan(2026, 'kepala', ['jabatan' => 'Kepala', 'nama' => 'X', 'nip' => ''], self::ADMIN)));
        $this->assertArrayHasKey('nip', $this->galat(fn () => KelolaPejabat::simpan(2026, 'komite', ['jabatan' => 'Ketua Komite', 'nama' => 'X', 'nip' => '123'], self::ADMIN)));
        $this->assertSame(['kunci' => 'Peran pejabat tidak dikenal.'], $this->galat(fn () => KelolaPejabat::simpan(2026, 'leading:9.9', ['jabatan' => 'X', 'nama' => 'X'], self::ADMIN)));

        foreach ([fn () => KelolaPejabat::simpan(2025, 'komite', ['jabatan' => 'Ketua Komite', 'nama' => 'X'], self::ADMIN), fn () => KelolaPejabat::simpan(2026, 'komite', ['jabatan' => 'Ketua Komite', 'nama' => 'X'], ['role' => 'leading'])] as $aksi) {
            try {
                $aksi();
                $this->fail('Seharusnya ditolak.');
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_salin_dari_tahun_lain_dan_mengetahui(): void
    {
        $this->assertSame(2025, KelolaPejabat::sumberSalin(2026), 'TA 2026 masih kosong; TA 2025 berpejabat.');
        $this->assertNull(KelolaPejabat::sumberSalin(2025), 'Tahun terkunci tidak disarankan.');

        KelolaPejabat::simpan(2026, 'komite', ['jabatan' => 'Ketua Komite MTsN 2 Kota Malang', 'nama' => 'Komite Baru', 'nip' => ''], self::ADMIN);
        $this->assertSame(3, KelolaPejabat::salin(2025, 2026, self::ADMIN), 'Kepala & dua leading; komite sudah terisi.');
        $pejabat = app(PejabatRepository::class)->untukTahun(2026);
        $this->assertSame(['Drs. H. Mahmud Arifin, M.Pd.I.', 'Komite Baru'], [$pejabat['kepala']['nama'], $pejabat['komite']['nama']]);
        $this->assertSame('3 pejabat TA 2025 disalin ke TA 2026 (yang sudah terisi tidak diubah).', $this->riwayat()['keterangan']);
        $this->assertSame(0, KelolaPejabat::salin(2025, 2026, self::ADMIN));
        $this->assertArrayHasKey('dari', $this->galat(fn () => KelolaPejabat::salin(2026, 2026, self::ADMIN)));

        $hasil = KelolaPejabat::simpanMengetahui(2026, ['DIPA' => false, 'Komite' => true], self::ADMIN);
        $this->assertSame(['DIPA' => false, 'Komite' => true], $hasil['aturan']);
        $this->assertSame('TA 2026 — Mengetahui di dokumen DIPA: ya → tidak', $this->riwayat()['keterangan']);
    }

    public function test_endpoint_pejabat(): void
    {
        $this->withSession(['stub_role' => 'leading'])->getJson(route('pengaturan.madrasah.pejabat'))->assertForbidden();
        $this->withSession(['stub_role' => 'admin'])->getJson(route('pengaturan.madrasah.pejabat', ['ta' => 2025]))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('tahun', 2025)
            ->assertJsonPath('terkunci', true)
            ->assertJsonPath('kepala.nip_format', '19680512 199403 1 002')
            ->assertJsonPath('leading.5.kode', '1.6')
            ->assertJsonPath('mengetahui', ['DIPA' => true, 'Komite' => true]);
        $this->withSession(['stub_role' => 'admin'])->getJson(route('pengaturan.madrasah.pejabat'))
            ->assertJsonPath('tahun', 2026)
            ->assertJsonPath('sumber_salin', 2025)
            ->assertJsonPath('kurang.0', 'kepala');
    }
}
