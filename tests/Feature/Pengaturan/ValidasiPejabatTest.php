<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\KegiatanRepository;
use App\Contracts\PejabatRepository;
use App\Livewire\Pengaturan\ProfilMadrasah;
use App\Repositories\EloquentPejabatRepository;
use App\Support\Dokumen\KaidahDokumen;
use App\Support\Dokumen\SusunanKak;
use App\Support\Dokumen\SusunanRab;
use App\Support\Pengaturan\KelolaPejabat;
use App\Support\Pengaturan\ValidasiPejabat;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\RujukanDb;
use Tests\TestCase;

class ValidasiPejabatTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN = ['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin', 'leadings' => []];

    private const KEPALA = ['jabatan' => 'Kepala MTsN 2 Kota Malang', 'nama' => 'Dr. H. Ahmad Fauzi, M.Pd.', 'nip' => '19700101 199503 1 001'];

    private function kaidah(int $id): array
    {
        $kegiatan = app(KegiatanRepository::class)->temukan($id);

        return collect(KaidahDokumen::periksa($kegiatan, SusunanKak::dari($kegiatan, 'Komite'), SusunanRab::dari($kegiatan, 'Komite')))->firstWhere('kunci', 'penanda_tangan');
    }

    public function test_aturan_isian_nama_jabatan_dan_nip(): void
    {
        $galat = function (array $isian, string $kunci = 'komite'): array {
            try {
                KelolaPejabat::simpan(2026, $kunci, $isian + ['jabatan' => 'Ketua Komite', 'nama' => 'H. Sugeng', 'nip' => ''], self::ADMIN);
            } catch (ValidationException $e) {
                return array_map(fn (array $p) => $p[0], $e->errors());
            }

            return [];
        };

        $this->assertSame(['nama' => 'Nama pejabat tidak boleh berisi angka — NIP diisi di kolom NIP.'], $galat(['nama' => 'Sugeng 197001011995031001']));
        $this->assertSame(['jabatan' => 'Jabatan harus berisi huruf.', 'nama' => 'Nama pejabat harus berisi huruf.'], $galat(['nama' => '-', 'jabatan' => '...']));
        $this->assertSame(['nip' => 'Nomor urut NIP (3 angka terakhir) tidak boleh 000.'], $galat(['nip' => '197001011995031000']));
        $this->assertSame(['nip' => 'TMT pada NIP (angka ke-9 sampai 14) lebih awal dari usia 18 tahun menurut tanggal lahirnya.'], $galat(['nip' => '200001012010031001']));
        $this->assertSame(['nip' => 'TMT pada NIP (angka ke-9 sampai 14) belum terjadi.'], $galat(['nip' => '19800615 '.now()->addMonth()->format('Ym').' 1 001']));
        $this->assertSame([], $galat(['nama' => 'H. Sugeng Riyadi, S.E. (Ketua)']));

        $this->withSession(['stub_role' => 'admin']);
        Livewire::withQueryParams(['tab' => 'pejabat'])->test(ProfilMadrasah::class)
            ->call('bukaUbahPejabat', 'komite')
            ->set('isianPejabat.nama', 'Komite 2026')
            ->call('simpanPejabat')
            ->assertHasErrors(['isianPejabat.nama'])
            ->assertSee('Nama pejabat tidak boleh berisi angka');
    }

    public function test_bentrok_antar_peran_hanya_peringatan(): void
    {
        KelolaPejabat::simpan(2026, 'kepala', self::KEPALA, self::ADMIN);
        $hasil = KelolaPejabat::simpan(2026, 'leading:1.6', ['jabatan' => 'Ketua Tim Penjamin Mutu', 'nama' => 'Siti Aminah, S.Pd.', 'nip' => '197001011995031001'], self::ADMIN);
        $this->assertSame(['NIP 19700101 199503 1 001 juga tercatat untuk Kepala Madrasah (Dr. H. Ahmad Fauzi, M.Pd.) — pastikan NIP-nya benar.'], $hasil['peringatan']);
        $this->assertSame('Siti Aminah, S.Pd.', app(PejabatRepository::class)->untukTahun(2026)['leading']['1.6']['nama'], 'Tetap tersimpan.');

        $this->assertSame(
            ['Dr. H. Ahmad Fauzi, M.Pd. juga tercatat sebagai Kepala Madrasah dengan NIP lain (19700101 199503 1 001).', 'Ketua Komite dan Kepala Madrasah orang yang sama — blok "Mengetahui" dan "Menyetujui" ditandatangani satu orang.'],
            KelolaPejabat::simpan(2026, 'komite', ['jabatan' => 'Ketua Komite', 'nama' => 'dr h ahmad FAUZI M.Pd', 'nip' => '19710202 199603 1 002'], self::ADMIN)['peringatan'],
            'Nama dibandingkan tanpa huruf besar & tanda baca.',
        );

        $tahun = ValidasiPejabat::tahun(2026);
        $this->assertSame(['bentrok', 'bentrok', 'bentrok'], array_column($tahun['kepala'], 'jenis'), 'NIP lain dengan Komite, orang yang sama dengan Komite, NIP dipakai pejabat 1.6.');
        $this->assertSame('Pejabat 1.6 Tim Penjamin Mutu: NIP 19700101 199503 1 001 juga tercatat untuk Kepala Madrasah (Dr. H. Ahmad Fauzi, M.Pd.) — pastikan NIP-nya benar.', $tahun['leading:1.6'][0]['pesan']);
        $this->assertSame([['jenis' => 'kurang', 'pesan' => 'Nama Pejabat 1.1 Waka Kurikulum belum diisi.']], $tahun['leading:1.1'] ?? null);

        $this->withSession(['stub_role' => 'admin']);
        Livewire::withQueryParams(['tab' => 'pejabat'])->test(ProfilMadrasah::class)
            ->assertSeeHtml('data-testid="temuan-pejabat"')
            ->assertSee('Periksa data pejabat TA 2026')
            ->assertSee('Ketua Komite dan Kepala Madrasah orang yang sama')
            ->assertDontSee('Nama Pejabat 1.1 Waka Kurikulum belum diisi.')
            ->call('bukaUbahPejabat', 'leading:1.6')
            ->set('isianPejabat.nip', '19850312 201001 2 004')
            ->call('simpanPejabat')
            ->assertHasNoErrors()
            ->assertNotDispatched('notify', type: 'info')
            ->assertDontSee('pastikan NIP-nya benar')
            ->call('bukaUbahPejabat', 'leading:1.1')
            ->set('isianPejabat.nama', 'Dr. H. Ahmad Fauzi, M.Pd.')
            ->set('isianPejabat.nip', '19850312 201001 2 004')
            ->call('simpanPejabat')
            ->assertDispatched('notify', type: 'info');

        $this->withSession(['stub_role' => 'admin'])->getJson(route('pengaturan.madrasah.pejabat'))->assertJsonPath('temuan.komite.0.jenis', 'bentrok');
    }

    public function test_tahun_komite_dan_nip_tersimpan_tidak_sah(): void
    {
        app(PejabatRepository::class)->simpan(2026, 'kepala', null, ['jabatan' => 'Kepala MTsN 2 Kota Malang', 'nama' => 'Data Lama', 'nip' => '197001011995033001']);
        app(PejabatRepository::class)->simpanAturanMengetahui(2026, ['DIPA' => false, 'Komite' => false]);

        $tahun = ValidasiPejabat::tahun(2026);
        $this->assertSame([['jenis' => 'nip', 'pesan' => 'NIP Kepala Madrasah tidak sah: Angka ke-15 NIP (jenis kelamin) harus 1 atau 2.']], $tahun['kepala']);
        $this->assertArrayNotHasKey('komite', $tahun, 'Blok Mengetahui tidak dipakai: Ketua Komite tidak wajib.');

        $kaidah = $this->kaidah(3);
        $this->assertSame(['gagal', 'NIP Menyetujui tidak sah — Angka ke-15 NIP (jenis kelamin) harus 1 atau 2 (Pengaturan › Profil & Pejabat).'], [$kaidah['status'], $kaidah['pesan']], 'Tanpa blok Mengetahui; Pembuat dari akun leading 1.6.');
    }

    public function test_pengesahan_dokumen(): void
    {
        $kolom = fn (array ...$isi) => array_map(fn (array $k) => $k + ['jabatan' => 'Jabatan', 'tempat_tanggal' => null], $isi);
        $mengetahui = ['peran' => 'Mengetahui,', 'nama' => 'H. Sugeng Riyadi, S.E.', 'nip' => null];
        $pembuat = ['peran' => 'Pembuat,', 'nama' => 'Muslimin, M.Pd.', 'nip' => '19800110 200604 1 001'];
        $kepala = ['peran' => 'Menyetujui,', 'nama' => 'Dr. H. Ahmad Fauzi, M.Pd.', 'nip' => '19700101 199503 1 001'];

        $this->assertSame(['status' => 'lulus', 'temuan' => []], ValidasiPejabat::pengesahan($kolom($mengetahui, $pembuat, $kepala)));
        $this->assertSame(['status' => 'peringatan', 'temuan' => ['Nama penanda tangan belum diisi: Mengetahui', 'NIP Kepala Madrasah (Menyetujui) belum diisi']],
            ValidasiPejabat::pengesahan($kolom(['nama' => null] + $mengetahui, $pembuat, ['nip' => null] + $kepala)));
        $this->assertSame(['status' => 'gagal', 'temuan' => ['Pembuat dan Menyetujui memakai NIP yang sama untuk nama berbeda']],
            ValidasiPejabat::pengesahan($kolom($mengetahui, ['nip' => $kepala['nip']] + $pembuat, $kepala)));
        $this->assertSame(['status' => 'peringatan', 'temuan' => ['Mengetahui dan Menyetujui orang yang sama (Dr. H. Ahmad Fauzi, M.Pd.)']],
            ValidasiPejabat::pengesahan($kolom(['nama' => 'Dr. H. Ahmad Fauzi, M.Pd.'] + $mengetahui, $pembuat, $kepala)));

        // Kaidah dokumen 4 memakai pemeriksaan yang sama: lengkap → lulus.
        KelolaPejabat::simpan(2026, 'kepala', self::KEPALA, self::ADMIN);
        KelolaPejabat::simpan(2026, 'komite', ['jabatan' => 'Ketua Komite MTsN 2 Kota Malang', 'nama' => 'H. Sugeng Riyadi, S.E.', 'nip' => ''], self::ADMIN);
        $this->assertSame('lulus', $this->kaidah(1)['status']);
    }

    public function test_database_menolak_isian_pejabat_tidak_sah(): void
    {
        RujukanDb::siapkan();
        $this->app->singleton(PejabatRepository::class, EloquentPejabatRepository::class);
        $repo = app(PejabatRepository::class);
        $repo->simpan(2026, 'kepala', null, ['jabatan' => 'Kepala MTsN 2 Kota Malang', 'nama' => 'Dr. H. Ahmad Fauzi, M.Pd.', 'nip' => '197001011995031001']);

        foreach ([['nama' => 'Ahmad 1970'], ['jabatan' => '...'], ['nip' => '197001011995033001'], ['nip' => '197001011995031000'], ['nip' => '197001011995131001']] as $salah) {
            try {
                DB::transaction(fn () => $repo->simpan(2026, 'kepala', null, $salah + ['jabatan' => 'Kepala', 'nama' => 'Ahmad', 'nip' => null]));
                $this->fail('Seharusnya ditolak database: '.json_encode($salah));
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame('Dr. H. Ahmad Fauzi, M.Pd.', $repo->untukTahun(2026)['kepala']['nama']);
    }
}
