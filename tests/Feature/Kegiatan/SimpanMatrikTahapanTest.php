<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Contracts\MatrikJadwalRepository;
use App\Livewire\Kegiatan\MatrikJadwal;
use App\Repositories\EloquentKegiatanRepository;
use App\Repositories\EloquentMatrikJadwalRepository;
use App\Support\Kegiatan\JadwalKegiatan;
use App\Support\Kegiatan\MatrikTahapan;
use App\Support\Kegiatan\SimpanMatrikJadwal;
use App\Support\Stub\StubKegiatanRepository;
use App\Support\Stub\StubMatrikJadwalRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class SimpanMatrikTahapanTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{bool}> */
    public static function penyimpanan(): array
    {
        return ['tiruan' => [false], 'database' => [true]];
    }

    /** Kegiatan contoh 3 (matrik & jadwal tersalin) di penyimpanan pilihan; mengembalikan id. */
    private function kegiatan(bool $database): int
    {
        if (! $database) {
            return 3;
        }
        $this->app->singleton(KegiatanRepository::class, EloquentKegiatanRepository::class);
        $this->app->singleton(MatrikJadwalRepository::class, EloquentMatrikJadwalRepository::class);
        $id = app(KegiatanRepository::class)->simpan(Arr::except(StubKegiatanRepository::contohMelebihiPagu(), ['id', 'status']));
        $contoh = StubMatrikJadwalRepository::contoh()[3];
        app(MatrikJadwalRepository::class)->simpan($id, MatrikTahapan::dari($contoh), JadwalKegiatan::dari($contoh));

        return $id;
    }

    /** @return array<string, string> */
    private function galat(int $id, array $matrik): array
    {
        try {
            SimpanMatrikJadwal::simpanMatrik($id, $matrik);
        } catch (ValidationException $e) {
            return array_map(fn (array $p) => $p[0], $e->errors());
        }
        $this->fail('Seharusnya ditolak: '.json_encode($matrik));
    }

    #[DataProvider('penyimpanan')]
    public function test_hanya_tahapan_yang_dikirim_yang_diganti_dan_dirapikan(bool $database): void
    {
        $id = $this->kegiatan($database);
        $repo = app(MatrikJadwalRepository::class);
        $sebelum = $repo->matrik($id)->butir;
        $jadwal = $repo->jadwal($id)->baris;

        $hasil = SimpanMatrikJadwal::simpanMatrik($id, ['pelaksanaan' => [
            ['uraian' => '  Persiapan pelaksanaan ', 'bulan' => [11, 10, 11]],
            ['uraian' => 'Pengawasan kegiatan', 'bulan' => ['11']],
        ]]);

        $this->assertSame([['uraian' => 'Persiapan pelaksanaan', 'bulan' => [10, 11]], ['uraian' => 'Pengawasan kegiatan', 'bulan' => [11]]], $repo->matrik($id)->butir['pelaksanaan']);
        $this->assertSame([$sebelum['persiapan'], $sebelum['evaluasi_pelaporan']], [$repo->matrik($id)->butir['persiapan'], $repo->matrik($id)->butir['evaluasi_pelaporan']]);
        $this->assertSame($jadwal, $repo->jadwal($id)->baris, 'Jadwal tidak berubah.');
        $this->assertSame($repo->matrik($id)->butir, $hasil['matrik']->butir);

        // Temuan matrik Pelaksanaan (belum ditandai di November, Juli terlalu dini) hilang; temuan lain tetap.
        $kunci = array_column($hasil['konsistensi']->temuan(), 'kunci');
        $this->assertNotContains('matrik-pelaksanaan-kurang', $kunci);
        $this->assertNotContains('matrik-pelaksanaan-dini', $kunci);
        $this->assertContains('matrik-evaluasi-lewat', $kunci);

        // Tahapan boleh dikosongkan (tersimpan kosong, bukan kembali ke butir baku).
        SimpanMatrikJadwal::simpanMatrik($id, ['evaluasi_pelaporan' => []]);
        $this->assertSame([], $repo->matrik($id)->butir['evaluasi_pelaporan']);
        $this->assertFalse($repo->matrik($id)->lengkap());
    }

    public function test_kegiatan_yang_belum_pernah_menyimpan_matrik(): void
    {
        SimpanMatrikJadwal::simpanMatrik(1, ['persiapan' => [['uraian' => 'Rapat panitia', 'bulan' => [6]]]]);

        $repo = app(MatrikJadwalRepository::class);
        $this->assertSame([['uraian' => 'Rapat panitia', 'bulan' => [6]]], $repo->matrik(1)->butir['persiapan']);
        $this->assertSame(array_map(fn (string $u) => ['uraian' => $u, 'bulan' => []], MatrikTahapan::BUTIR_BAKU['pelaksanaan']), $repo->matrik(1)->butir['pelaksanaan']);
        $this->assertSame(JadwalKegiatan::dari([])->baris, $repo->jadwal(1)->baris, 'Jadwal tetap 6 baris baku.');
    }

    public function test_aturan_isian_dan_kegiatan_terkunci(): void
    {
        $this->assertSame(['matrik' => 'Tahapan matrik hanya Persiapan, Pelaksanaan, Evaluasi dan Pelaporan.'], $this->galat(3, ['penutupan' => []]));
        $this->assertSame([
            'matrik.persiapan.0.uraian' => 'Uraian butir tahapan wajib diisi.',
            'matrik.persiapan.1.uraian' => 'Uraian butir tahapan maksimal 150 karakter.',
            'matrik.persiapan.2.bulan.0' => 'Bulan matrik harus 1–12.',
        ], $this->galat(3, ['persiapan' => [
            ['uraian' => '   ', 'bulan' => []],
            ['uraian' => str_repeat('a', MatrikTahapan::MAKS_URAIAN + 1), 'bulan' => []],
            ['uraian' => 'Rapat', 'bulan' => [13]],
        ]]));
        $this->assertSame(StubMatrikJadwalRepository::contoh()[3]['matrik'], app(MatrikJadwalRepository::class)->matrik(3)->butir, 'Isian yang ditolak tidak tersimpan.');

        try {
            SimpanMatrikJadwal::simpanMatrik(999, []);
            $this->fail('Kegiatan tidak ada seharusnya ditolak.');
        } catch (RuntimeException) {
            $this->addToAssertionCount(1);
        }

        $repo = app(KegiatanRepository::class);
        $repo->simpan(['status' => 'diperiksa'] + $repo->temukan(3), 3);
        $this->expectException(AuthorizationException::class);
        SimpanMatrikJadwal::simpanMatrik(3, ['persiapan' => []]);
    }

    public function test_halaman_memakai_aturan_yang_sama(): void
    {
        $halaman = Livewire::test(MatrikJadwal::class, ['kegiatan' => 3])
            ->set('matrik.persiapan.0.uraian', str_repeat('a', MatrikTahapan::MAKS_URAIAN + 1))
            ->call('simpan')
            ->assertHasErrors(['matrik.persiapan.0.uraian']);
        $this->assertSame('Uraian butir tahapan maksimal 150 karakter.', $halaman->errors()->first('matrik.persiapan.0.uraian'));
    }
}
