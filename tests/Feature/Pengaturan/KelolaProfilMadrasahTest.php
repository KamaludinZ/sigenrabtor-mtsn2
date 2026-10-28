<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\ProfilMadrasahRepository;
use App\Contracts\RiwayatPengaturanRepository;
use App\Support\Pengaturan\KelolaProfilMadrasah;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class KelolaProfilMadrasahTest extends TestCase
{
    private const ADMIN = ['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin', 'leadings' => []];

    private function riwayat(): array
    {
        return app(RiwayatPengaturanRepository::class)->semua();
    }

    public function test_simpan_profil_dirapikan_dan_riwayat_hanya_bila_berubah(): void
    {
        $isian = ['nama' => '  Madrasah  Tsanawiyah Negeri 2 Kota Malang ', 'nama_singkat' => 'MTsN 2 Kota Malang', 'kota' => 'Malang', 'alamat' => " Jl. Cinta\nNo. 1 ", 'telepon' => '(0341) 491-000', 'visi' => "Unggul.\n\n\n\n  Berakhlak  mulia. "];
        $hasil = KelolaProfilMadrasah::simpan($isian, self::ADMIN);

        $this->assertSame(['Madrasah Tsanawiyah Negeri 2 Kota Malang', 'Jl. Cinta No. 1', "Unggul.\n\nBerakhlak mulia."], [$hasil['profil']['nama'], $hasil['profil']['alamat'], $hasil['profil']['visi']]);
        $this->assertStringContainsString('Alamat:', $hasil['ringkasan']);
        $this->assertSame('ubah_profil', $this->riwayat()[0]['aksi']);

        $jumlah = count($this->riwayat());
        $this->assertNull(KelolaProfilMadrasah::simpan($isian, self::ADMIN)['ringkasan'], 'Tanpa perubahan.');
        $this->assertCount($jumlah, $this->riwayat());
    }

    public function test_aturan_isian_logo_dan_hak(): void
    {
        try {
            KelolaProfilMadrasah::simpan(['nama' => ' ', 'nama_singkat' => 'MTsN 2', 'kota' => '', 'telepon' => '0341-ABC'], self::ADMIN);
            $this->fail('Seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertSame(['nama', 'kota', 'telepon'], array_keys($e->errors()));
            $this->assertSame('Telepon hanya boleh berisi angka, spasi, dan tanda + ( ) - . /', $e->errors()['telepon'][0]);
        }

        try {
            KelolaProfilMadrasah::simpanLogo(UploadedFile::fake()->image('logo.png', 100, 100), self::ADMIN);
            $this->fail('Logo kecil seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertStringStartsWith('Logo terlalu kecil', $e->errors()['logo'][0]);
        }
        $this->assertFalse(KelolaProfilMadrasah::hapusLogo(self::ADMIN), 'Belum ada logo.');
        KelolaProfilMadrasah::simpanLogo(UploadedFile::fake()->image('logo-mtsn2.png', 400, 400), self::ADMIN);
        $this->assertSame(['unggah_logo', 'Logo kop diunggah (logo-mtsn2.png).'], [$this->riwayat()[0]['aksi'], $this->riwayat()[0]['keterangan']]);
        $this->assertTrue(KelolaProfilMadrasah::hapusLogo(self::ADMIN));
        $this->assertNull(app(ProfilMadrasahRepository::class)->ambil()['logo_path']);

        foreach ([fn () => KelolaProfilMadrasah::simpan(['nama' => 'X'], ['role' => 'leading']), fn () => KelolaProfilMadrasah::hapusLogo(['role' => 'leading'])] as $aksi) {
            try {
                $aksi();
                $this->fail('Seharusnya ditolak.');
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_endpoint_profil(): void
    {
        $this->getJson(route('madrasah.profil'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('nama', 'Madrasah Tsanawiyah Negeri 2 Kota Malang')
            ->assertJsonPath('logo', null)
            ->assertJsonPath('kurang', ['alamat', 'telepon', 'logo']);

        KelolaProfilMadrasah::simpan(['nama' => 'Madrasah Tsanawiyah Negeri 2 Kota Malang', 'nama_singkat' => 'MTsN 2 Kota Malang', 'kota' => 'Malang', 'alamat' => 'Jl. Cinta 1', 'telepon' => '0341 491000'], self::ADMIN);
        KelolaProfilMadrasah::simpanLogo(UploadedFile::fake()->image('logo.png', 300, 300), self::ADMIN);
        $respons = $this->getJson(route('madrasah.profil'))->assertJsonPath('kurang', []);
        $this->assertStringStartsWith(route('madrasah.logo'), $respons->json('logo'));
    }
}
