<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\DasarHukumRepository;
use App\Livewire\Pengaturan\DasarHukum;
use App\Repositories\EloquentDasarHukumRepository;
use App\Support\Stub\StubDasarHukumRepository;
use App\Support\Stub\StubData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\RujukanDb;
use Tests\TestCase;

class EloquentDasarHukumRepositoryTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{bool}> */
    public static function penyimpanan(): array
    {
        return ['tiruan' => [false], 'database' => [true]];
    }

    /** Repositori pilihan; database diisi contoh tiruan TA 2025 & 2026 lewat simpan(). */
    private function repo(bool $database): DasarHukumRepository
    {
        if ($database) {
            RujukanDb::siapkan();
            DB::table('tahun_anggaran')->insert(['tahun' => 2027, 'is_active' => false, 'is_locked' => false]);
            $this->app->singleton(DasarHukumRepository::class, EloquentDasarHukumRepository::class);
            foreach (StubDasarHukumRepository::contoh() as $tahun => $isi) {
                app(DasarHukumRepository::class)->simpan((int) $tahun, $isi);
            }
        }

        return app(DasarHukumRepository::class);
    }

    #[DataProvider('penyimpanan')]
    public function test_contoh_simpan_urutan_dan_versi(bool $database): void
    {
        $repo = $this->repo($database);
        $this->assertSame(['dh-2026-1', 'dh-2026-2', 'dh-2026-3', 'dh-2026-4'], array_column($repo->untukTahun(2026)['dasar_hukum'], 'id'));
        $this->assertSame(['dasar_hukum' => [], ...StubDasarHukumRepository::bawaan(), 'diatur' => false, 'diperbarui_pada' => null], $repo->untukTahun(2027), 'Belum diatur: teks bawaan.');

        $repo->simpan(2027, ['dasar_hukum' => [['id' => null, 'teks' => 'Undang-Undang Nomor 20 Tahun 2003'], ['teks' => 'RKTM Tahun {tahun}']], ...StubDasarHukumRepository::bawaan()]);
        $pertama = $repo->untukTahun(2027);
        $this->assertTrue($pertama['diatur']);
        $this->assertSame(['Undang-Undang Nomor 20 Tahun 2003', 'RKTM Tahun {tahun}'], array_column($pertama['dasar_hukum'], 'teks'));
        $this->assertTrue(Str::isUuid($pertama['dasar_hukum'][0]['id']), 'Butir baru diberi UUID.');
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}\+07:00$/', $pertama['diperbarui_pada']);

        $repo->simpan(2027, ['dasar_hukum' => array_reverse($pertama['dasar_hukum']), 'latar_belakang' => 'Paragraf TA {tahun}.', 'kalimat_penutup' => 'Yaitu kegiatan {nama_kegiatan}.']);
        $kedua = $repo->untukTahun(2027);
        $this->assertSame(array_reverse($pertama['dasar_hukum']), $kedua['dasar_hukum'], 'Id tetap, urutan = urutan cetak.');
        $this->assertSame(['Paragraf TA {tahun}.', 'Yaitu kegiatan {nama_kegiatan}.'], [$kedua['latar_belakang'], $kedua['kalimat_penutup']]);
        $this->assertNotSame($pertama['diperbarui_pada'], $kedua['diperbarui_pada'], 'Versi berubah tiap simpan.');

        $repo->simpan(2027, ['dasar_hukum' => [], ...StubDasarHukumRepository::bawaan()]);
        $this->assertSame([[], true], [$repo->untukTahun(2027)['dasar_hukum'], $repo->untukTahun(2027)['diatur']], 'Dasar hukum kosong tetap tercatat diatur.');
        $this->assertCount(4, $repo->untukTahun(2026)['dasar_hukum'], 'Tahun lain tidak tersentuh.');
    }

    public function test_bentuk_database_sama_dengan_tiruan(): void
    {
        $tiruan = [app(DasarHukumRepository::class)->untukTahun(2025), app(DasarHukumRepository::class)->untukTahun(2026), StubData::dasarHukum(2026), StubData::latarBelakangBaku(2026)];
        $repo = $this->repo(true);

        $this->assertSame(
            array_map(fn ($isi) => is_array($isi) && isset($isi['diatur']) ? Arr::except($isi, 'diperbarui_pada') : $isi, $tiruan),
            [Arr::except($repo->untukTahun(2025), 'diperbarui_pada'), Arr::except($repo->untukTahun(2026), 'diperbarui_pada'), StubData::dasarHukum(2026), StubData::latarBelakangBaku(2026)],
        );
        $this->assertSame(StubDasarHukumRepository::bawaan(), $repo->teksBawaan());
    }

    public function test_database_menolak_isian_tidak_sah(): void
    {
        $repo = $this->repo(true);
        $sah = $repo->untukTahun(2026);
        $butir = fn (string ...$teks) => ['dasar_hukum' => array_map(fn (string $t) => ['teks' => $t], $teks)] + $sah;

        foreach ([
            $butir(''),
            $butir('Undang-Undang Nomor 20 Tahun 2003;'),
            $butir('Undang-Undang  Nomor 20'),
            $butir("Undang-Undang\nNomor 20"),
            $butir('Kegiatan {nama_kegiatan}'),
            $butir('Undang-Undang Nomor 20', 'undang-undang nomor 20'),
            $butir(...array_map(fn (int $i) => "Peraturan nomor {$i}", range(1, 31))),
            ['latar_belakang' => "Paragraf satu.\n\nParagraf dua."] + $sah,
            ['latar_belakang' => 'Paragraf kegiatan {nama_kegiatan}.'] + $sah,
            ['kalimat_penutup' => 'Kegiatan ini.'] + $sah,
            ['kalimat_penutup' => 'Kegiatan {nama_kegiatan} dan {nama_kegiatan}.'] + $sah,
            ['kalimat_penutup' => 'Kegiatan {nama_kegiatan} oleh {kepala}.'] + $sah,
            ['kalimat_penutup' => str_repeat('a', 500).' {nama_kegiatan}'] + $sah,
        ] as $salah) {
            try {
                $repo->simpan(2026, $salah);
                $this->fail('Seharusnya ditolak database: '.json_encode(Arr::only($salah, ['latar_belakang', 'kalimat_penutup']) ?: array_column($salah['dasar_hukum'], 'teks')));
            } catch (RuntimeException $e) {
                $this->assertStringStartsWith('Dasar hukum TA 2026 gagal disimpan ke database', $e->getMessage());
            }
        }
        $this->assertSame($sah, $repo->untukTahun(2026), 'Data lama utuh.');

        $repo->simpan(2026, $butir('Peraturan Menteri Agama Nomor 90 Tahun 2013 tentang Penyelenggaraan Pendidikan Madrasah (Revisi) Tahun {tahun} Nomor {no_sk_rktm} tanggal {tanggal_sk_rktm}'));
        $this->assertCount(1, $repo->untukTahun(2026)['dasar_hukum']);
    }

    public function test_halaman_dasar_hukum_memakai_repositori_database(): void
    {
        $this->repo(true);
        $this->withSession(['stub_role' => 'admin']);

        Livewire::test(DasarHukum::class)
            ->call('tambahButir')
            ->set('teksButir', '  Peraturan Pemerintah Nomor 57 Tahun 2021   tentang Standar Nasional Pendidikan. ')
            ->call('simpanButir')
            ->assertHasNoErrors()
            ->assertSee('Peraturan Pemerintah Nomor 57 Tahun 2021 tentang Standar Nasional Pendidikan.');

        $this->assertSame('Peraturan Pemerintah Nomor 57 Tahun 2021 tentang Standar Nasional Pendidikan', DB::table('dasar_hukum')->where('tahun_anggaran', 2026)->where('urutan', 5)->value('teks'));
    }
}
