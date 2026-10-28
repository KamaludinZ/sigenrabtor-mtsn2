<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\KegiatanRepository;
use App\Contracts\LeadingRepository;
use App\Contracts\PejabatRepository;
use App\Contracts\PenggunaRepository;
use App\Repositories\EloquentPejabatRepository;
use App\Support\Dokumen\SusunanKak;
use App\Support\Dokumen\SusunanRab;
use App\Support\Pengaturan\KelolaPejabat;
use App\Support\Pengaturan\PejabatBerlaku;
use App\Support\Pengaturan\PenandaTangan;
use App\Support\Stub\StubData;
use App\Support\Stub\StubPejabatRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\RujukanDb;
use Tests\TestCase;

class PejabatBerlakuTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN = ['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin', 'leadings' => []];

    /** @return array<string, array{bool}> */
    public static function penyimpanan(): array
    {
        return ['tiruan' => [false], 'database' => [true]];
    }

    private function pakaiDatabase(): void
    {
        RujukanDb::siapkan();
        $this->app->singleton(PejabatRepository::class, EloquentPejabatRepository::class);
        $repo = app(PejabatRepository::class);
        $contoh = StubPejabatRepository::contoh()['2025'];
        $repo->simpan(2025, 'kepala', null, $contoh['kepala']);
        $repo->simpan(2025, 'komite', null, $contoh['komite']);
        foreach ($contoh['leading'] as $kode => $p) {
            $repo->simpan(2025, 'leading', $kode, $p);
        }
    }

    /** @return array{peran: string, jabatan: string, nama: string|null, nip: string|null}[] */
    private function pengesahan(SusunanKak|SusunanRab $susunan): array
    {
        $kolom = $susunan instanceof SusunanKak ? collect($susunan->bagian)->firstWhere('nomor', 9)['isi'][0]['kolom'] : $susunan->pengesahan;

        return array_map(fn (array $k) => array_intersect_key($k, array_flip(['peran', 'jabatan', 'nama', 'nip'])), $kolom);
    }

    #[DataProvider('penyimpanan')]
    public function test_pejabat_tahun_aktif_dan_tahun_lain(bool $database): void
    {
        if ($database) {
            $this->pakaiDatabase();
        }

        $aktif = PejabatBerlaku::tahun();
        $this->assertSame(2026, $aktif->tahun);
        $this->assertSame(['jabatan' => 'Kepala MTsN 2 Kota Malang', 'nama' => null, 'nip' => null], $aktif->kepala, 'TA 2026 belum diisi: jabatan bawaan.');
        $this->assertSame(StubData::pejabat(), ['kepala' => $aktif->kepala, 'komite' => $aktif->komite]);
        $this->assertSame(StubData::pejabatLeading(), $aktif->leading);

        $lalu = PejabatBerlaku::tahun(2025);
        $this->assertSame(['jabatan' => 'Kepala MTsN 2 Kota Malang', 'nama' => 'Drs. H. Mahmud Arifin, M.Pd.I.', 'nip' => '19680512 199403 1 002'], $lalu->pimpinan()['kepala'], 'NIP berformat cetak.');
        $this->assertSame('196805121994031002', $lalu->kepala['nip'], 'Bentuk mentah tanpa pemisah.');
        $this->assertSame(['jabatan' => 'Ketua Tim Penjamin Mutu', 'nama' => 'Muslimin, M.Pd.', 'nip' => '19800110 200604 1 001'], $lalu->pembuat(['id' => 9, 'leading' => '1.6']));
        $this->assertSame(PenandaTangan::pimpinan(2025), $lalu->pimpinan());

        // Pratinjau dokumen tahun lain (mis. Pengaturan › Dasar hukum ?ta=2025) memakai pejabat tahun itu, bukan tahun aktif.
        $kegiatan = app(KegiatanRepository::class)->temukan(1);
        $this->assertSame([
            ['peran' => 'Mengetahui,', 'jabatan' => 'Ketua Komite MTsN 2 Kota Malang', 'nama' => 'H. Sugeng Riyadi, S.E.', 'nip' => null],
            ['peran' => 'Pembuat,', 'jabatan' => 'Ketua Tim Penjamin Mutu', 'nama' => 'Muslimin, M.Pd.', 'nip' => '19800110 200604 1 001'],
            ['peran' => 'Menyetujui,', 'jabatan' => 'Kepala MTsN 2 Kota Malang', 'nama' => 'Drs. H. Mahmud Arifin, M.Pd.I.', 'nip' => '19680512 199403 1 002'],
        ], $this->pengesahan(SusunanKak::dari($kegiatan, 'Komite', tahun: 2025)));
        $this->assertSame([null, 'Muslimin, M.Pd.', null], array_column($this->pengesahan(SusunanKak::dari($kegiatan, 'Komite')), 'nama'), 'Tahun aktif: Kepala & Komite TA 2026 belum diisi.');
        $this->assertSame($this->pengesahan(SusunanKak::dari($kegiatan, 'Komite')), $this->pengesahan(SusunanRab::dari($kegiatan, 'Komite')), 'RAB memakai blok yang sama.');

        app(PejabatRepository::class)->simpanAturanMengetahui(2025, ['DIPA' => false, 'Komite' => true]);
        $this->assertNull(PejabatBerlaku::tahun(2025)->mengetahui('DIPA'));
        $this->assertSame('H. Sugeng Riyadi, S.E.', PejabatBerlaku::tahun(2025)->mengetahui(null)['nama'], 'Tanpa sumber dana: tampil.');
        $this->assertNotNull(PejabatBerlaku::tahun()->mengetahui('DIPA'), 'Aturan per tahun.');
    }

    public function test_nama_akun_pembuat_sama_bagi_siapa_pun_yang_membuka(): void
    {
        $kegiatan = ['id' => 7, 'nama_kegiatan' => 'Uji', 'leading' => '1.6', 'user_id' => 1];
        $berlaku = PejabatBerlaku::tahun();
        $this->assertSame(['jabatan' => 'Ketua Tim Penjamin Mutu', 'nama' => 'Muslimin, M.Pd.', 'nip' => null], $berlaku->pembuat($kegiatan));
        session(['stub_role' => 'admin']);
        $this->assertSame('Muslimin, M.Pd.', PejabatBerlaku::tahun()->pembuat($kegiatan)['nama'], 'Admin melihat nama yang sama.');
        $this->assertSame('Muslimin, M.Pd.', PejabatBerlaku::tahun()->pembuat(['leading' => '1.6'])['nama'], 'Belum tersimpan oleh admin: satu-satunya akun leading 1.6.');

        // Akun leading kedua untuk 1.6: pembuat kegiatan menentukan; tanpa pembuat yang tertaut → kosong.
        $kedua = app(PenggunaRepository::class)->simpan(['name' => 'Laila Fitri, S.Pd.', 'email' => 'laila@example.com', 'role' => 'leading', 'nip' => null, 'jabatan' => null, 'leadings' => ['1.6'], 'is_active' => true]);
        $berlaku = PejabatBerlaku::tahun();
        $this->assertSame('Laila Fitri, S.Pd.', $berlaku->pembuat(['user_id' => $kedua] + $kegiatan)['nama']);
        $this->assertSame('Muslimin, M.Pd.', $berlaku->pembuat($kegiatan)['nama']);
        $this->assertNull($berlaku->pembuat(['user_id' => 2] + $kegiatan)['nama'], 'Dibuat admin, dua akun tertaut: admin perlu mengisi pejabat leading.');
        $this->assertNull($berlaku->pembuat(['id' => 7, 'leading' => '1.5'])['nama'], 'Akun leading 1.5 nonaktif.');
        $this->assertSame('Ahmad Fauzan, S.Pd.', $berlaku->pembuat(['id' => 7, 'leading' => '1.4', 'user_id' => 3])['nama'], 'Pembuat tidak tertaut ke 1.4: akun yang tertaut.');
        $this->assertNull($berlaku->pembuat(['id' => 7, 'leading' => '9.9']), 'Leading tidak dikenal.');
        $this->assertNull($berlaku->pembuat(['id' => 7]));

        KelolaPejabat::simpan(2026, 'leading:1.6', ['jabatan' => 'Ketua Tim Penjamin Mutu', 'nama' => 'Siti Aminah, S.Pd.', 'nip' => '19850312 201001 2 004'], self::ADMIN);
        $this->assertSame(['jabatan' => 'Ketua Tim Penjamin Mutu', 'nama' => 'Siti Aminah, S.Pd.', 'nip' => '19850312 201001 2 004'], PejabatBerlaku::tahun()->pembuat(['user_id' => $kedua] + $kegiatan), 'Pejabat leading menggantikan nama akun.');
    }

    #[DataProvider('penyimpanan')]
    public function test_leading_nonaktif_tetap_tercetak(bool $database): void
    {
        if ($database) {
            $this->pakaiDatabase();
        }
        $leading = collect(app(LeadingRepository::class)->semua())->firstWhere('kode', '===', '1.6');
        app(LeadingRepository::class)->simpan(['is_active' => false] + $leading, '1.6');

        $this->assertArrayNotHasKey('1.6', app(PejabatRepository::class)->untukTahun(2025)['leading'], 'Daftar pejabat hanya leading aktif.');
        $this->assertSame(['jabatan' => 'Ketua Tim Penjamin Mutu', 'nama' => 'Muslimin, M.Pd.', 'nip' => '198001102006041001'], app(PejabatRepository::class)->leadingTersimpan(2025, '1.6'));
        $this->assertNull(app(PejabatRepository::class)->leadingTersimpan(2026, '1.6'));

        $kegiatan = ['id' => 7, 'leading' => '1.6', 'user_id' => 2];
        $this->assertSame(['jabatan' => 'Ketua Tim Penjamin Mutu', 'nama' => 'Muslimin, M.Pd.', 'nip' => '19800110 200604 1 001'], PejabatBerlaku::tahun(2025)->pembuat($kegiatan), 'Pejabat tersimpan leading nonaktif.');
        $this->assertSame(['jabatan' => 'Ketua Tim Penjamin Mutu', 'nama' => 'Muslimin, M.Pd.', 'nip' => null], PejabatBerlaku::tahun()->pembuat($kegiatan), 'Belum tersimpan: jabatan master + akun tertaut.');
    }
}
