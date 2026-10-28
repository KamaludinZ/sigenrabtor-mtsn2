<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Livewire\Kegiatan\Form;
use App\Repositories\EloquentKegiatanRepository;
use App\Support\Kegiatan\RiwayatStatus;
use App\Support\Kegiatan\UbahDataKegiatan;
use App\Support\Pengaturan\PengaturanBerubah;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\RujukanDb;
use Tests\TestCase;

class UbahDataKegiatanTest extends TestCase
{
    use RefreshDatabase;

    private const LEADING = ['id' => 1, 'name' => 'Muslimin, M.Pd.', 'role' => 'leading', 'leadings' => ['1.6']];

    private const ADMIN = ['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin', 'leadings' => []];

    /** @return array<string, array{bool}> */
    public static function penyimpanan(): array
    {
        return ['tiruan' => [false], 'database' => [true]];
    }

    /** @param  array<string, mixed>  $isi */
    private function kegiatan(array $isi = []): int
    {
        return app(KegiatanRepository::class)->simpan($isi + [
            'nama_kegiatan' => 'Rapat Kerja Madrasah',
            'nama_kegiatan_cover' => 'RAPAT KERJA MADRASAH',
            'cover_manual' => false,
            'leading' => '1.6',
            'user_id' => 1,
            'tahun_anggaran' => 2026,
            'tujuan' => 'Menyusun program kerja.',
            'kode_sk' => 'SK.10',
            'kode_iksk' => 'IKSK 10.6',
            'tanggal_mulai' => '2026-07-14',
            'lama_hari' => 1,
            'hari_kerja' => false,
            'laporan_otomatis' => false,
            'tanggal_laporan' => '2026-07-31',
            'bulan_pelaksanaan' => [7],
            'sumber_dana' => [['sumber' => 'Komite', 'pagu' => 1_000_000]],
            'panitia' => [['nama' => 'Siti Rohmah', 'jabatan' => 'Guru', 'peran' => 'Ketua']],
            'riwayat_status' => [RiwayatStatus::entri('dibuat', StatusKegiatan::Draf, self::LEADING)],
            'status' => 'draf',
        ]);
    }

    #[DataProvider('penyimpanan')]
    public function test_hanya_isian_yang_dikirim_berubah(bool $database): void
    {
        if ($database) {
            RujukanDb::siapkan();
            $this->app->singleton(KegiatanRepository::class, EloquentKegiatanRepository::class);
        }
        $repo = app(KegiatanRepository::class);
        $id = $this->kegiatan();
        $awal = $repo->temukan($id);

        $hasil = UbahDataKegiatan::ubah($id, ['tujuan' => "  Menyusun   program\n\n\n\nkerja tahunan. ", 'tempat' => '  Aula   Madrasah '], self::LEADING, UbahDataKegiatan::versi($awal));
        $this->assertSame(['tujuan', 'tempat'], $hasil['diubah']);
        $this->assertSame(['Identitas kegiatan', 'Pelaksanaan'], $hasil['bagian']);
        $baru = $repo->temukan($id);
        $this->assertSame(["Menyusun program\n\nkerja tahunan.", 'Aula Madrasah'], [$baru['tujuan'], $baru['tempat']]);
        $this->assertSame([$awal['nama_kegiatan'], $awal['sumber_dana'], $awal['panitia'], $awal['status'], $awal['riwayat_status'], $awal['kode_iksk']],
            [$baru['nama_kegiatan'], $baru['sumber_dana'], $baru['panitia'], $baru['status'], $baru['riwayat_status'], $baru['kode_iksk']], 'Isian lain, status, dan riwayat tidak tersentuh.');
        $this->assertSame(UbahDataKegiatan::versi($baru), $hasil['versi']);

        // Nama cover mengikuti nama kegiatan selama tidak manual; cover diketik berbeda = manual.
        UbahDataKegiatan::ubah($id, ['nama_kegiatan' => 'Rapat  Kerja Tahunan'], self::LEADING);
        $this->assertSame(['Rapat Kerja Tahunan', 'RAPAT KERJA TAHUNAN', false], [$repo->temukan($id)['nama_kegiatan'], $repo->temukan($id)['nama_kegiatan_cover'], $repo->temukan($id)['cover_manual']]);
        UbahDataKegiatan::ubah($id, ['nama_kegiatan_cover' => 'raker tahunan'], self::LEADING);
        $this->assertSame(['RAKER TAHUNAN', true], [$repo->temukan($id)['nama_kegiatan_cover'], $repo->temukan($id)['cover_manual']]);
        UbahDataKegiatan::ubah($id, ['nama_kegiatan' => 'Rapat Kerja Madrasah'], self::LEADING);
        $this->assertSame('RAKER TAHUNAN', $repo->temukan($id)['nama_kegiatan_cover'], 'Cover manual tidak ikut nama.');
        UbahDataKegiatan::ubah($id, ['cover_manual' => false], self::LEADING);
        $this->assertSame(['RAPAT KERJA MADRASAH', false], [$repo->temukan($id)['nama_kegiatan_cover'], $repo->temukan($id)['cover_manual']]);

        // Isian turunan: bulan & tanggal pelaporan dari jadwal, IKSK menyesuaikan SK baru.
        $jadwal = UbahDataKegiatan::ubah($id, ['tanggal_mulai' => '2026-08-03', 'lama_hari' => '3', 'bulan_pelaksanaan' => [], 'laporan_otomatis' => true], self::LEADING);
        $this->assertContains('bulan_pelaksanaan', $jadwal['diubah']);
        $this->assertSame([[8], 3], [$repo->temukan($id)['bulan_pelaksanaan'], $repo->temukan($id)['lama_hari']]);
        $this->assertNotSame('2026-07-31', $repo->temukan($id)['tanggal_laporan'], 'Pelaporan otomatis mengikuti jadwal baru.');
        UbahDataKegiatan::ubah($id, ['kode_sk' => 'SK.8'], self::LEADING);
        $this->assertSame(['SK.8', 'IKSK 8.1'], [$repo->temukan($id)['kode_sk'], $repo->temukan($id)['kode_iksk']], 'Satu-satunya indikator SK baru dipilih.');
        UbahDataKegiatan::ubah($id, ['kode_sk' => 'SK.9'], self::LEADING);
        $this->assertNull($repo->temukan($id)['kode_iksk']);

        $sebelum = $repo->temukan($id);
        $tetap = UbahDataKegiatan::ubah($id, ['tempat' => 'Aula Madrasah', 'sumber_dana' => ['Komite' => '1.000.000']], self::LEADING);
        $this->assertSame([[], 'Tidak ada perubahan data kegiatan.'], [$tetap['diubah'], $tetap['pesan']]);
        $this->assertSame($sebelum, $repo->temukan($id), 'Tanpa perubahan tidak ditulis.');
    }

    public function test_terkunci_bentrok_dan_isian_ditolak(): void
    {
        $id = $this->kegiatan();
        $tolak = function (callable $aksi, string $jenis): string {
            try {
                $aksi();
            } catch (\Throwable $e) {
                $this->assertInstanceOf($jenis, $e);

                return $e instanceof ValidationException ? json_encode(array_map(fn ($p) => $p[0], $e->errors()), JSON_UNESCAPED_UNICODE) : $e->getMessage();
            }
            $this->fail('Seharusnya ditolak.');
        };

        $this->assertStringContainsString('"isian":"Isian status, riwayat_status tidak bisa diubah di sini', $tolak(fn () => UbahDataKegiatan::ubah($id, ['status' => 'final', 'riwayat_status' => []], self::LEADING), ValidationException::class));
        $this->assertStringContainsString('Tidak ada isian yang dikirim.', $tolak(fn () => UbahDataKegiatan::ubah($id, [], self::LEADING), ValidationException::class));
        $galat = $tolak(fn () => UbahDataKegiatan::ubah($id, ['nama_kegiatan' => 'Abc', 'tanggal_mulai' => '2027-01-05', 'kode_edm' => 'Z9'], self::LEADING), ValidationException::class);
        foreach (['nama_kegiatan', 'tanggal_mulai', 'kode_edm'] as $kunci) {
            $this->assertStringContainsString('"'.$kunci.'"', $galat);
        }
        $this->assertSame('Rapat Kerja Madrasah', app(KegiatanRepository::class)->temukan($id)['nama_kegiatan']);

        $versi = UbahDataKegiatan::versi(app(KegiatanRepository::class)->temukan($id));
        UbahDataKegiatan::ubah($id, ['tempat' => 'Aula'], self::ADMIN);
        $this->assertStringContainsString('baru saja diubah di tempat lain', $tolak(fn () => UbahDataKegiatan::ubah($id, ['tempat' => 'Lab'], self::LEADING, $versi), PengaturanBerubah::class));

        $lain = ['id' => 4, 'name' => 'Ahmad Fauzan, S.Pd.', 'role' => 'leading', 'leadings' => ['1.2']];
        $this->assertStringContainsString('milik leading lain', $tolak(fn () => UbahDataKegiatan::ubah($id, ['tempat' => 'Lab'], $lain), AuthorizationException::class));

        $diperiksa = $this->kegiatan(['status' => 'diperiksa', 'riwayat_status' => [RiwayatStatus::entri('ajukan', StatusKegiatan::Diperiksa, self::LEADING)]]);
        $this->assertSame('Kegiatan berstatus Diperiksa sehingga isiannya terkunci.', $tolak(fn () => UbahDataKegiatan::ubah($diperiksa, ['tempat' => 'Lab'], self::LEADING), AuthorizationException::class));
        $lalu = $this->kegiatan(['tahun_anggaran' => 2025]);
        $this->assertSame('Tahun Anggaran 2025 terkunci — kegiatan tahun itu hanya bisa dilihat.', $tolak(fn () => UbahDataKegiatan::ubah($lalu, ['tempat' => 'Lab'], self::LEADING), AuthorizationException::class));
        $dikembalikan = $this->kegiatan(['status' => 'dikembalikan']);
        $this->assertSame(['tempat'], UbahDataKegiatan::ubah($dikembalikan, ['tempat' => 'Lab'], self::LEADING)['diubah'], 'Dikembalikan = diperbaiki di data.');
    }

    public function test_endpoint_baca_dan_ubah(): void
    {
        $id = $this->kegiatan();
        $baca = route('kegiatan.data', ['kegiatan' => $id]);
        $ubah = route('kegiatan.ubah', ['kegiatan' => $id]);

        $data = $this->getJson($baca)->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJson(['id' => $id, 'status' => 'draf', 'label_status' => 'Draf', 'tahun_anggaran' => 2026, 'dapat_diubah' => true, 'alasan_terkunci' => null])
            ->assertJsonPath('isian.nama_kegiatan', 'Rapat Kerja Madrasah')
            ->json();
        $this->assertSame(UbahDataKegiatan::ISIAN, array_keys($data['isian']));
        $this->getJson(route('kegiatan.data', ['kegiatan' => 999]))->assertNotFound();
        $this->patchJson(route('kegiatan.ubah', ['kegiatan' => 999]), ['tempat' => 'Aula'])->assertNotFound();

        $this->patchJson($ubah, ['sasaran' => 'Guru dan tenaga kependidikan.', 'versi' => $data['versi']])->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJson(['id' => $id, 'status' => 'draf', 'diubah' => ['sasaran'], 'bagian' => ['Identitas kegiatan'], 'pesan' => 'Data kegiatan tersimpan.'])
            ->assertJsonPath('isian.sasaran', 'Guru dan tenaga kependidikan.');
        $this->patchJson($ubah, ['sasaran' => 'Lain.', 'versi' => $data['versi']])->assertStatus(409)
            ->assertJsonPath('isian.sasaran', 'Guru dan tenaga kependidikan.');
        $this->patchJson($ubah, ['lama_hari' => 0])->assertStatus(422)->assertJsonStructure(['pesan', 'galat' => ['lama_hari']]);
        $this->patchJson($ubah, ['status' => 'final'])->assertStatus(422)->assertJsonStructure(['galat' => ['isian']]);

        $lain = $this->kegiatan(['leading' => '1.2', 'user_id' => 4]);
        $this->getJson(route('kegiatan.data', ['kegiatan' => $lain]))->assertForbidden();
        $this->patchJson(route('kegiatan.ubah', ['kegiatan' => $lain]), ['tempat' => 'Aula'])->assertForbidden();

        $diperiksa = $this->kegiatan(['status' => 'diperiksa', 'riwayat_status' => [RiwayatStatus::entri('ajukan', StatusKegiatan::Diperiksa, self::LEADING)]]);
        $this->getJson(route('kegiatan.data', ['kegiatan' => $diperiksa]))->assertJson(['dapat_diubah' => false, 'alasan_terkunci' => 'Kegiatan berstatus Diperiksa sehingga isiannya terkunci.']);
        $this->patchJson(route('kegiatan.ubah', ['kegiatan' => $diperiksa]), ['tempat' => 'Aula'])->assertForbidden()
            ->assertJsonPath('pesan', 'Kegiatan berstatus Diperiksa sehingga isiannya terkunci.');
    }

    public function test_simpan_draf_tidak_menimpa_status_dan_kolom_lain(): void
    {
        $repo = app(KegiatanRepository::class);
        $id = $this->kegiatan(['riwayat_kode' => [['pada' => '2026-03-01T08:00:00+07:00', 'oleh' => 'Muslimin, M.Pd.', 'perubahan' => []]], 'duplikat_dari' => ['id' => 9, 'tahun' => 2025]]);

        $halaman = Livewire::test(Form::class, ['kegiatan' => $id])
            ->set('form.tempat', 'Aula Madrasah')
            ->call('saveDraft')
            ->assertHasNoErrors()
            ->assertDispatched('notify', fn (string $event, array $p) => str_starts_with($p['message'], 'Draf tersimpan pukul'));
        $tersimpan = $repo->temukan($id);
        $this->assertSame('Aula Madrasah', $tersimpan['tempat']);
        $this->assertSame([1, ['id' => 9, 'tahun' => 2025]], [count($tersimpan['riwayat_kode']), $tersimpan['duplikat_dari']], 'Kolom di luar form tetap tersimpan.');

        // Diajukan dari tab lain sementara form masih terbuka: Simpan Draf ditolak, status tersimpan tetap.
        $repo->simpan(['status' => 'diperiksa', 'riwayat_status' => [...$tersimpan['riwayat_status'], RiwayatStatus::entri('ajukan', StatusKegiatan::Diperiksa, self::LEADING)]] + $tersimpan, $id);
        $halaman->set('form.tempat', 'Laboratorium')
            ->call('saveDraft')
            ->assertDispatched('notify', message: 'Kegiatan berstatus Diperiksa sehingga isiannya terkunci.', type: 'error')
            ->assertSet('status', 'diperiksa');
        $this->assertSame(['diperiksa', 'Aula Madrasah', 2], [$repo->temukan($id)['status'], $repo->temukan($id)['tempat'], count($repo->temukan($id)['riwayat_status'])]);
    }
}
