<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\JejakDuplikatRepository;
use App\Contracts\KegiatanRepository;
use App\Contracts\RabRepository;
use App\Repositories\EloquentJejakDuplikatRepository;
use App\Support\Kegiatan\AksesKegiatan;
use App\Support\Kegiatan\Duplikat\LayananDuplikat;
use App\Support\Pengaturan\KontrolTahunAktif;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\RujukanDb;
use Tests\TestCase;

class LayananDuplikatTest extends TestCase
{
    use RefreshDatabase;

    private const LEADING = ['id' => 1, 'name' => 'Muslimin, M.Pd.', 'role' => 'leading', 'leadings' => ['1.6']];

    private const ADMIN = ['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin', 'leadings' => []];

    /** @param  array<string, mixed>  $isi */
    private function tahunLalu(string $nama, array $isi = []): int
    {
        return app(KegiatanRepository::class)->simpan($isi + ['nama_kegiatan' => $nama, 'leading' => '1.6', 'user_id' => 1, 'tahun_anggaran' => 2025, 'tanggal_mulai' => '2025-08-04', 'sumber_dana' => [['sumber' => 'DIPA', 'pagu' => 1_000_000]]]);
    }

    public function test_duplikat_per_kegiatan_dengan_alasan_dan_jejak(): void
    {
        $repo = app(KegiatanRepository::class);
        $baru = $this->tahunLalu('Lomba Literasi Madrasah');
        app(RabRepository::class)->tambahItem($baru, 'DIPA', ['kode' => '', 'uraian' => 'Hadiah lomba', 'kategori' => 'lain', 'volume' => 3, 'satuan' => 'paket', 'harga_satuan' => 150_000]);
        $sudahAda = $this->tahunLalu($repo->temukan(2)['nama_kegiatan']);
        $kembar = $this->tahunLalu('lomba  literasi madrasah');
        $lain = $this->tahunLalu('Pentas Seni', ['leading' => '1.2', 'user_id' => 4]);

        $hasil = LayananDuplikat::jalankan([$baru, (string) $baru, $sudahAda, $kembar, 1, $lain, 999], self::LEADING);
        $this->assertSame([$baru], array_column($hasil['dibuat'], 'id'));
        $this->assertSame([$sudahAda, $kembar], array_column($hasil['dilewati'], 'id'), 'Nama sama di tahun aktif, termasuk yang baru dibuat di permintaan ini.');
        $this->assertSame([1, $lain, 999], array_column($hasil['gagal'], 'id'));
        $this->assertStringContainsString('bukan kegiatan tahun lalu', $hasil['gagal'][0]['alasan']);
        $this->assertSame([AksesKegiatan::PESAN_TOLAK, 'Kegiatan tidak ditemukan.'], [$hasil['gagal'][1]['alasan'], $hasil['gagal'][2]['alasan']]);
        $this->assertSame('1 kegiatan diduplikat menjadi draf TA 2026, 2 dilewati (sudah ada), 3 gagal.', $hasil['pesan']);

        $draf = $repo->temukan($hasil['dibuat'][0]['hasil_id']);
        $this->assertSame([2026, 'draf', '2026-08-04', ['id' => $baru, 'tahun' => 2025]], [$draf['tahun_anggaran'], $draf['status'], $draf['tanggal_mulai'], $draf['duplikat_dari']]);
        $this->assertCount(1, app(RabRepository::class)->rincian($draf['id'], 'DIPA'), 'Rincian RAB ikut disalin.');
        $jejak = app(JejakDuplikatRepository::class)->untukHasil($draf['id']);
        $this->assertSame([$baru, 2025, 2026, 'Muslimin, M.Pd.', $hasil['dibuat'][0]['catatan']], [$jejak['sumber_id'], $jejak['sumber_tahun'], $jejak['tahun'], $jejak['pengguna'], $jejak['catatan']]);
        $this->assertCount(1, app(JejakDuplikatRepository::class)->semua(2026));
    }

    public function test_aturan_permintaan_dan_tahun_aktif(): void
    {
        $nonaktif = $this->tahunLalu('Kegiatan Lama', ['leading' => '1.9', 'user_id' => 2]);
        $hasil = LayananDuplikat::jalankan([$nonaktif], self::ADMIN);
        $this->assertStringStartsWith('Leading 1.9', $hasil['gagal'][0]['alasan']);

        foreach ([[], ['abc'], range(1, LayananDuplikat::MAKS + 1)] as $ids) {
            try {
                LayananDuplikat::jalankan($ids, self::ADMIN);
                $this->fail('Seharusnya ditolak.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('kegiatan', $e->errors());
            }
        }

        KontrolTahunAktif::aktifkan(2025, ['name' => 'Koordinator PUSKOM']);
        $this->expectException(AuthorizationException::class);
        LayananDuplikat::jalankan([$this->tahunLalu('Apa Saja')], self::ADMIN);
    }

    /** @return array<string, array{bool}> */
    public static function penyimpanan(): array
    {
        return ['tiruan' => [false], 'database' => [true]];
    }

    #[DataProvider('penyimpanan')]
    public function test_repositori_jejak_duplikat(bool $database): void
    {
        [$sumber, $hasilA, $hasilB] = [1, 2, 3];
        if ($database) {
            RujukanDb::siapkan();
            $this->app->singleton(JejakDuplikatRepository::class, EloquentJejakDuplikatRepository::class);
            $sumber = DB::table('activities')->insertGetId(['tahun_anggaran' => 2025, 'nama_kegiatan' => 'Sumber']);
            $hasilA = DB::table('activities')->insertGetId(['tahun_anggaran' => 2026, 'nama_kegiatan' => 'Hasil A']);
            $hasilB = DB::table('activities')->insertGetId(['tahun_anggaran' => 2026, 'nama_kegiatan' => 'Hasil B']);
        }
        $repo = app(JejakDuplikatRepository::class);
        $catatan = [['jenis' => 'pagu_lama', 'teks' => 'Pagu disalin dari TA 2025 — periksa pagu TA 2026.']];
        $a = $repo->catat(['sumber_id' => $sumber, 'sumber_tahun' => 2025, 'hasil_id' => $hasilA, 'tahun' => 2026, 'catatan' => $catatan, 'user_id' => 1, 'pengguna' => 'Muslimin, M.Pd.']);
        $repo->catat(['sumber_id' => $sumber, 'sumber_tahun' => 2025, 'hasil_id' => $hasilB, 'tahun' => 2026, 'catatan' => [], 'user_id' => null, 'pengguna' => 'Koordinator PUSKOM']);

        $this->assertSame([$sumber, 2025, $hasilA, 2026, $catatan, 1, 'Muslimin, M.Pd.'], [$a['sumber_id'], $a['sumber_tahun'], $a['hasil_id'], $a['tahun'], $a['catatan'], $a['user_id'], $a['pengguna']]);
        $this->assertMatchesRegularExpression('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d\.\d{6}\+07:00$/', $a['dibuat_pada']);
        $this->assertSame($a, $repo->untukHasil($hasilA));
        $this->assertNull($repo->untukHasil(999));
        $this->assertSame([$hasilB, $hasilA], array_column($repo->semua(2026), 'hasil_id'), 'Terbaru dulu.');
        $this->assertSame([], $repo->semua(2025));
    }
}
