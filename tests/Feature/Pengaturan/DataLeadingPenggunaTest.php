<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\LeadingRepository;
use App\Contracts\PenggunaRepository;
use App\Support\MasterKode;
use App\Support\Stub\StubData;
use App\Support\Stub\StubPengaturan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DataLeadingPenggunaTest extends TestCase
{
    public function test_contoh_leading_aktif_dan_nonaktif(): void
    {
        $semua = app(LeadingRepository::class)->semua();
        $this->assertSame(['1.1', '1.2', '1.3', '1.4', '1.5', '1.6', '1.7', '1.8', '1.9'], array_column($semua, 'kode'));
        $this->assertSame(range(1, 9), array_column($semua, 'urutan'));
        $this->assertFalse(collect($semua)->firstWhere('kode', '1.9')['is_active']);
        $this->assertSame(['kode' => '1.7', 'nama_unit' => 'Mahad', 'jabatan_penanda_tangan' => 'Pengasuh Mahad', 'urutan' => 7, 'is_active' => true], $semua[6]);

        // Pilihan leading di seluruh aplikasi hanya yang aktif, bentuk lama tetap.
        $this->assertSame(['1.1', '1.2', '1.3', '1.4', '1.5', '1.6', '1.7', '1.8'], array_column(MasterKode::leadings(), 'kode'));
        $this->assertSame(['kode', 'nama_unit', 'jabatan_penanda_tangan'], array_keys(StubData::leadings()[0]));
    }

    public function test_simpan_leading_tambah_ubah_dan_urutan(): void
    {
        $repo = app(LeadingRepository::class);
        $repo->simpan(['kode' => '1.10', 'nama_unit' => 'Literasi', 'jabatan_penanda_tangan' => 'Koordinator Literasi']);
        $this->assertSame(['kode' => '1.10', 'nama_unit' => 'Literasi', 'jabatan_penanda_tangan' => 'Koordinator Literasi', 'urutan' => 10, 'is_active' => true], collect($repo->semua())->last());

        $repo->simpan(['kode' => '1.6', 'nama_unit' => 'TPM', 'jabatan_penanda_tangan' => 'Ketua TPM', 'urutan' => 0], '1.6');
        $this->assertSame('1.6', $repo->semua()[0]['kode'], 'Urutan 0 tampil paling atas.');
        $this->assertSame('TPM', collect(MasterKode::leadings())->firstWhere('kode', '1.6')['nama_unit']);

        $repo->simpan(['kode' => '1.8', 'nama_unit' => 'PMM', 'jabatan_penanda_tangan' => 'Koordinator PMM', 'is_active' => false], '1.8');
        $this->assertNotContains('1.8', array_column(MasterKode::leadings(), 'kode'));
        $this->assertTrue(Storage::disk('local')->exists(config('sigenrabtor.stub.leading_file')));
    }

    public function test_contoh_akun_dan_tautan_leading(): void
    {
        $semua = app(PenggunaRepository::class)->semua();
        $this->assertSame([1, 2, 3, 4, 5, 6], array_column($semua, 'id'));
        $this->assertSame(['leading' => 4, 'admin' => 2], array_count_values(array_column($semua, 'role')));
        $this->assertSame(['1.2', '1.4'], $semua[3]['leadings'], 'Satu akun tertaut ke dua leading.');
        $this->assertFalse($semua[5]['is_active']);
        $this->assertSame([], $semua[1]['leadings']);
        foreach ($semua as $akun) {
            $this->assertStringEndsWith('@example.com', $akun['email']);
        }
        $this->assertSame($semua, StubPengaturan::pengguna());
    }

    public function test_simpan_akun_dan_pengguna_masuk_mengikuti_akun(): void
    {
        $repo = app(PenggunaRepository::class);
        $id = $repo->simpan(['name' => 'Akun Baru', 'email' => 'baru@example.com', 'role' => 'admin', 'leadings' => ['1.1']]);
        $this->assertSame(7, $id);
        $baru = $repo->temukan(7);
        $this->assertSame([], $baru['leadings'], 'Akun admin tidak menyimpan tautan leading.');
        $this->assertTrue($baru['is_active']);
        $this->assertNotNull($baru['dibuat_pada']);

        $repo->simpan(['name' => 'Muslimin, S.Pd., M.Pd.', 'leadings' => ['1.6', '1.1']], 1);
        $akun = $repo->temukan(1);
        $this->assertSame(['Muslimin, S.Pd., M.Pd.', 'muslimin@example.com', ['1.6', '1.1']], [$akun['name'], $akun['email'], $akun['leadings']]);

        $this->assertSame(['id' => 1, 'name' => 'Muslimin, S.Pd., M.Pd.', 'role' => 'leading', 'jabatan' => 'Tim Penjamin Mutu', 'leadings' => ['1.6', '1.1']], StubData::currentUser());
        session(['stub_role' => 'admin']);
        $this->assertSame(['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin', 'jabatan' => 'Admin Aplikasi', 'leadings' => []], StubData::currentUser());
        $this->assertNull($repo->temukan(99));
    }
}
