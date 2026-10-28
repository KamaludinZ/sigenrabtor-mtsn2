<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\ImporKegiatanRepository;
use App\Contracts\KegiatanRepository;
use App\Repositories\EloquentImporKegiatanRepository;
use App\Repositories\EloquentKegiatanRepository;
use App\Support\Kegiatan\Impor\ProsesImporMassal;
use App\Support\Kegiatan\Impor\UnggahPerkin;
use App\Support\Stub\StubData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\RujukanDb;
use Tests\TestCase;

class ProsesImporMassalTest extends TestCase
{
    use RefreshDatabase;

    private function impor(): array
    {
        return UnggahPerkin::unggah(new UploadedFile(base_path('tests/fixtures/perkin-cascade-contoh.xlsx'), 'perkin-cascade-contoh.xlsx', null, null, true), StubData::currentUser())['impor'];
    }

    public function test_batch_membuat_draf_menandai_baris_lalu_selesai(): void
    {
        $impor = $this->impor();
        $repo = app(KegiatanRepository::class);

        $pertama = ProsesImporMassal::proses($impor['id'], StubData::currentUser(), 3);
        $this->assertSame([[4, 'dibuat'], [5, 'dibuat'], [6, 'dibuat']], array_map(fn ($d) => [$d['nomor_baris'], $d['status']], $pertama['diproses']));
        $this->assertSame([1, false, 3, 'berjalan'], [$pertama['sisa'], $pertama['selesai'], $pertama['impor']['jumlah_dibuat'], $pertama['impor']['status']]);
        $kegiatan = $repo->temukan($pertama['diproses'][0]['kegiatan_id']);
        $this->assertSame(['Pelatihan Guru Pendidikan Inklusif', 'draf', 'impor_perkin', $impor['id'], 1], [$kegiatan['nama_kegiatan'], $kegiatan['status'], $kegiatan['asal'], $kegiatan['impor_id'], $kegiatan['user_id']]);
        $this->assertSame('Diimpor dari cascade Perkin (perkin-cascade-contoh.xlsx, sheet Cascade Perkin).', $kegiatan['riwayat_status'][0]['catatan']);
        $this->assertSame(route('kegiatan.edit', ['kegiatan' => $kegiatan['id']]), $pertama['diproses'][0]['tautan']);
        $this->assertSame($kegiatan['id'], collect(app(ImporKegiatanRepository::class)->baris($impor['id']))->firstWhere('nomor_baris', 4)['kegiatan_id']);

        // Sejak pratinjau nama baris terakhir sudah dibuat di tempat lain: dilewati, lalu impor selesai.
        $repo->simpan(['nama_kegiatan' => 'Bimbingan Olimpiade Sains', 'leading' => '1.6', 'user_id' => 1, 'tahun_anggaran' => 2026]);
        $kedua = ProsesImporMassal::proses($impor['id'], StubData::currentUser());
        $this->assertSame([[10, 'dilewati']], array_map(fn ($d) => [$d['nomor_baris'], $d['status']], $kedua['diproses']));
        $this->assertSame([0, true, 'selesai', 3, 0, 2], [$kedua['sisa'], $kedua['selesai'], $kedua['impor']['status'], $kedua['impor']['jumlah_dibuat'], $kedua['impor']['jumlah_siap'] - $kedua['impor']['jumlah_dibuat'], $kedua['impor']['jumlah_dilewati']]);

        $this->expectException(ValidationException::class);
        ProsesImporMassal::proses($impor['id'], StubData::currentUser());
    }

    public function test_batal_dan_endpoint(): void
    {
        $impor = $this->impor();
        $this->postJson(route('kegiatan.impor_perkin.proses', ['impor' => $impor['id']]), ['batas' => 1])->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJson(['sisa' => 3, 'selesai' => false])->assertJsonPath('diproses.0.status', 'dibuat');
        $this->postJson(route('kegiatan.impor_perkin.batal', ['impor' => $impor['id']]))->assertOk()
            ->assertJson(['impor' => ['status' => 'dibatalkan', 'jumlah_dibuat' => 1], 'pesan' => 'Impor dibatalkan — 1 kegiatan yang sudah dibuat tetap ada.']);
        $this->postJson(route('kegiatan.impor_perkin.proses', ['impor' => $impor['id']]))->assertStatus(422)->assertJsonPath('galat.impor.0', 'Impor ini sudah dibatalkan.');
        $this->postJson(route('kegiatan.impor_perkin.batal', ['impor' => $impor['id']]))->assertStatus(422);
        $this->postJson(route('kegiatan.impor_perkin.proses', ['impor' => '00000000-0000-4000-8000-000000000000']))->assertNotFound();

        $lain = app(ImporKegiatanRepository::class)->mulai(['tahun' => 2026, 'jenis' => 'excel', 'nama_berkas' => 'a.xlsx', 'lembar' => null, 'user_id' => 99, 'pengguna' => 'Akun Lain'], []);
        $this->postJson(route('kegiatan.impor_perkin.proses', ['impor' => $lain['id']]))->assertForbidden();
        $this->withSession(['stub_role' => 'admin'])->postJson(route('kegiatan.impor_perkin.proses', ['impor' => $lain['id']]))->assertOk()
            ->assertJson(['diproses' => [], 'sisa' => 0, 'selesai' => true, 'impor' => ['status' => 'selesai']]);
    }

    public function test_database_mencatat_impor_pada_kegiatan(): void
    {
        RujukanDb::siapkan();
        $this->app->singleton(KegiatanRepository::class, EloquentKegiatanRepository::class);
        $this->app->singleton(ImporKegiatanRepository::class, EloquentImporKegiatanRepository::class);
        $impor = app(ImporKegiatanRepository::class)->mulai(['tahun' => 2026, 'jenis' => 'excel', 'nama_berkas' => 'kegiatan.xlsx', 'lembar' => null, 'user_id' => 1, 'pengguna' => 'Muslimin, M.Pd.'], [
            ['nomor_baris' => 2, 'status' => 'siap', 'data' => ['nama_kegiatan' => 'Rapat Kerja Impor', 'nama_kegiatan_cover' => 'RAPAT KERJA IMPOR', 'leading' => '1.6'], 'pesan' => [], 'peringatan' => []],
        ]);

        $hasil = ProsesImporMassal::proses($impor['id'], StubData::currentUser());
        $id = $hasil['diproses'][0]['kegiatan_id'];
        $this->assertSame([$impor['id'], 'impor_excel'], [DB::table('activities')->where('id', $id)->value('impor_id'), DB::table('activities')->where('id', $id)->value('asal')]);
        $this->assertSame($impor['id'], app(KegiatanRepository::class)->temukan($id)['impor_id']);
        $this->assertSame('Diimpor dari Excel (kegiatan.xlsx).', app(KegiatanRepository::class)->temukan($id)['riwayat_status'][0]['catatan']);
        $this->assertSame(['selesai', 1], [DB::table('impor_kegiatan')->where('id', $impor['id'])->value('status'), (int) DB::table('impor_kegiatan')->where('id', $impor['id'])->value('jumlah_dibuat')]);
    }
}
