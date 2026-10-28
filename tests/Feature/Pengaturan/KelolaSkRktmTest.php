<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\RiwayatPengaturanRepository;
use App\Support\Pengaturan\KelolaSkRktm;
use App\Support\Pengaturan\SkRktm;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class KelolaSkRktmTest extends TestCase
{
    private const ADMIN = ['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin', 'leadings' => []];

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

    public function test_simpan_kosongkan_dan_aturan(): void
    {
        $hasil = KelolaSkRktm::simpan(2026, '  B-123/Mts.13.25.02/PP.00/01/2026 ', '2026-01-06', self::ADMIN);
        $this->assertSame(['nomor' => 'B-123/Mts.13.25.02/PP.00/01/2026', 'tanggal' => '2026-01-06'], $hasil['sk']);
        $this->assertSame(['nomor' => 'B-123/Mts.13.25.02/PP.00/01/2026', 'tanggal' => '2026-01-06'], SkRktm::untuk(2026));
        $this->assertNull($hasil['peringatan']);
        $this->assertSame('SK RKTM/RKAM TA 2026 — Nomor: — → B-123/Mts.13.25.02/PP.00/01/2026; Tanggal: — → 6 Januari 2026', app(RiwayatPengaturanRepository::class)->semua()[0]['keterangan']);

        $jumlah = count(app(RiwayatPengaturanRepository::class)->semua());
        $this->assertNull(KelolaSkRktm::simpan(2026, 'B-123/Mts.13.25.02/PP.00/01/2026', '2026-01-06', self::ADMIN)['ringkasan']);
        $this->assertCount($jumlah, app(RiwayatPengaturanRepository::class)->semua(), 'Tanpa perubahan: tidak tercatat.');

        $this->assertSame('Tanggal SK (2023) tidak sejalan dengan TA 2026 — SK RKTM/RKAM biasanya ditetapkan akhir tahun sebelumnya atau awal tahun anggaran.', KelolaSkRktm::simpan(2026, 'B-123', '2023-12-30', self::ADMIN)['peringatan'], 'Hanya peringatan; tetap tersimpan.');
        $this->assertSame('2023-12-30', SkRktm::untuk(2026)['tanggal']);

        $this->assertSame(['nomor' => null, 'tanggal' => null], KelolaSkRktm::simpan(2026, ' ', '', self::ADMIN)['sk'], 'Keduanya kosong = dikosongkan.');
        $this->assertFalse(SkRktm::lengkap(2026));

        $this->assertSame(['tanggal' => 'Tanggal SK wajib diisi bersama nomornya.'], $this->galat(fn () => KelolaSkRktm::simpan(2026, 'B-1', '', self::ADMIN)));
        $this->assertSame(['nomor' => 'Nomor SK wajib diisi bersama tanggalnya.', 'tanggal' => 'Tanggal tidak valid.'], $this->galat(fn () => KelolaSkRktm::simpan(2026, '', '2026-02-30', self::ADMIN)));
        $this->assertSame(['tahun' => 'Tahun Anggaran 2031 belum dibuat.'], $this->galat(fn () => KelolaSkRktm::simpan(2031, 'B-1', '2031-01-02', self::ADMIN)));

        foreach ([[2025, self::ADMIN], [2026, ['role' => 'leading']]] as [$tahun, $pengguna]) {
            try {
                KelolaSkRktm::simpan($tahun, 'B-1', "{$tahun}-01-02", $pengguna);
                $this->fail('Seharusnya ditolak.');
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_endpoint_sk_rktm(): void
    {
        $this->withSession(['stub_role' => 'leading'])->getJson(route('pengaturan.madrasah.sk_rktm.data'))->assertForbidden();
        $this->withSession(['stub_role' => 'admin'])->getJson(route('pengaturan.madrasah.sk_rktm.data'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('tahun', 2026)
            ->assertJsonPath('nomor', null)
            ->assertJsonPath('lengkap', false)
            ->assertJsonPath('butir_memakai', [])
            ->assertJsonPath('kurang', false);

        $sk = SkRktm::untuk(2025);
        $respons = $this->withSession(['stub_role' => 'admin'])->getJson(route('pengaturan.madrasah.sk_rktm.data', ['ta' => 2025]))
            ->assertJsonPath('terkunci', true)
            ->assertJsonPath('nomor', $sk['nomor'])
            ->assertJsonPath('butir_memakai', [3]);
        $this->assertSame(! SkRktm::lengkap(2025), $respons->json('kurang'));
        $this->withSession(['stub_role' => 'admin'])->getJson(route('pengaturan.madrasah.sk_rktm.data', ['ta' => 2031]))->assertNotFound();
    }
}
