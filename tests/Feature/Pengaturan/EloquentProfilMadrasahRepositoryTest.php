<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\ProfilMadrasahRepository;
use App\Livewire\Pengaturan\ProfilMadrasah;
use App\Repositories\EloquentProfilMadrasahRepository;
use App\Support\Stub\StubData;
use App\Support\Stub\StubProfilMadrasahRepository;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EloquentProfilMadrasahRepositoryTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{bool}> */
    public static function penyimpanan(): array
    {
        return ['tiruan' => [false], 'database' => [true]];
    }

    private function repo(bool $database): ProfilMadrasahRepository
    {
        if ($database) {
            $this->app->singleton(ProfilMadrasahRepository::class, EloquentProfilMadrasahRepository::class);
        }

        return app(ProfilMadrasahRepository::class);
    }

    #[DataProvider('penyimpanan')]
    public function test_profil_contoh_simpan_dan_logo(bool $database): void
    {
        $repo = $this->repo($database);
        $this->assertSame(StubProfilMadrasahRepository::contoh(), $repo->ambil(), 'Belum disimpan: profil contoh.');

        $repo->simpan(['alamat' => 'Jl. Cinta No. 1', 'telepon' => '(0341) 491-000', 'visi' => '', 'asing' => 'diabaikan']);
        $profil = $repo->ambil();
        $this->assertSame(['Madrasah Tsanawiyah Negeri 2 Kota Malang', 'Jl. Cinta No. 1', '(0341) 491-000', null], [$profil['nama'], $profil['alamat'], $profil['telepon'], $profil['visi']]);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+07:00$/', $profil['diperbarui_pada']);
        $this->assertSame(['nama', 'nama_singkat', 'kota', 'alamat', 'telepon', 'visi', 'logo_path', 'diperbarui_pada'], array_keys($profil));

        $repo->simpanLogo(UploadedFile::fake()->image('logo.png', 300, 300));
        $pertama = $repo->ambil()['logo_path'];
        Storage::disk('local')->assertExists($pertama);
        $repo->simpanLogo(UploadedFile::fake()->image('logo.jpg', 300, 300));
        $kedua = $repo->ambil()['logo_path'];
        Storage::disk('local')->assertMissing($pertama);
        Storage::disk('local')->assertExists($kedua);
        $this->assertStringEndsWith('.jpg', $kedua);

        $repo->hapusLogo();
        $this->assertNull($repo->ambil()['logo_path']);
        Storage::disk('local')->assertMissing($kedua);
        $this->assertSame('Jl. Cinta No. 1', $repo->ambil()['alamat'], 'Hapus logo tidak mengubah isian lain.');
    }

    public function test_bentuk_database_sama_dengan_tiruan_dan_aturan_kolom(): void
    {
        $isian = ['nama' => 'MTs Negeri 2 Kota Malang', 'nama_singkat' => 'MTsN 2 Malang', 'kota' => 'Kota Malang', 'alamat' => 'Jl. Cinta 1', 'telepon' => '0341 491000', 'visi' => "Unggul.\n\nBerakhlak."];
        $this->repo(false)->simpan($isian);
        $tiruan = Arr::except($this->repo(false)->ambil(), ['diperbarui_pada']);
        $repo = $this->repo(true);
        $repo->simpan($isian);
        $this->assertSame($tiruan, Arr::except($repo->ambil(), ['diperbarui_pada']));
        $this->assertSame('MTsN 2 Malang', StubData::madrasah()['nama_singkat'], 'Data dokumen membaca profil dari database.');

        foreach ([['telepon' => '0341-ABC'], ['telepon' => str_repeat('1', 41)], ['visi' => str_repeat('v', 1001)], ['kota' => '']] as $salah) {
            try {
                DB::transaction(fn () => $repo->simpan($salah));
                $this->fail('Seharusnya ditolak database: '.json_encode($salah));
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame('0341 491000', $repo->ambil()['telepon']);
    }

    public function test_halaman_profil_memakai_repositori_database(): void
    {
        $this->repo(true);
        $this->withSession(['stub_role' => 'admin']);

        Livewire::test(ProfilMadrasah::class)
            ->call('bukaUbahProfil')
            ->set('isian.alamat', 'Jl. Cinta No. 1, Malang')
            ->set('isian.telepon', '(0341) 491-000')
            ->call('simpanProfil')
            ->assertHasNoErrors();
        $this->assertSame('Jl. Cinta No. 1, Malang', DB::table('profil_madrasah')->where('id', 1)->value('alamat'));
    }
}
