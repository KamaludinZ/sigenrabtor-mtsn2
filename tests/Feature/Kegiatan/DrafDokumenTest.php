<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\DrafDokumenRepository;
use App\Contracts\KegiatanRepository;
use App\Livewire\Kegiatan\SusunDokumen;
use App\Support\Dokumen\DrafDokumen;
use App\Support\Dokumen\SusunanKak;
use App\Support\Dokumen\SusunanRab;
use App\Support\Dokumen\TeksCover;
use App\Support\Pengaturan\PengaturanBerubah;
use App\Support\Rab\RincianRab;
use App\Support\Stub\StubDrafDokumenRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class DrafDokumenTest extends TestCase
{
    private const LEADING = ['id' => 1, 'name' => 'Muslimin, M.Pd.', 'role' => 'leading', 'leadings' => ['1.6']];

    private function kegiatanBaru(array $data = []): int
    {
        return app(KegiatanRepository::class)->simpan($data + ['nama_kegiatan' => 'Rapat Kerja Madrasah', 'leading' => '1.6', 'user_id' => 1, 'tahun_anggaran' => 2026, 'sumber_dana' => [['sumber' => 'Komite', 'pagu' => 3_000_000]]]);
    }

    /** @return array{cover?: string, kak?: array<int, string>, rab?: string} */
    private function sidik(int $id, string $jenis, ?string $sumber = null): array
    {
        $kegiatan = app(KegiatanRepository::class)->temukan($id);
        $kak = SusunanKak::dari($kegiatan, $sumber);

        return DrafDokumen::sidik($jenis, $kak, SusunanRab::dari($kegiatan, $sumber), TeksCover::nilai($kegiatan, $kak->sumber));
    }

    private function keadaan(int $id, ?array $draf, string $jenis): array
    {
        $kegiatan = app(KegiatanRepository::class)->temukan($id);

        return DrafDokumen::keadaan($draf, $this->sidik($id, $jenis), SusunanKak::dari($kegiatan));
    }

    public function test_rekam_sidik_dan_keadaan_sejak_draf(): void
    {
        $repo = app(KegiatanRepository::class);
        $id = $this->kegiatanBaru();
        $draf = DrafDokumen::rekam($repo->temukan($id), 'lengkap', null, self::LEADING);

        $this->assertSame(['lengkap', 'Komite', 'draf', true, 9, 0, 1, 'Muslimin, M.Pd.'],
            [$draf['jenis'], $draf['sumber'], $draf['status'], $draf['bertanda_draf'], $draf['jumlah_bagian'], $draf['total_rab'], $draf['user_id'], $draf['oleh']]);
        $this->assertSame(['cover', 'kak', 'rab'], array_keys($draf['sidik']));
        $this->assertSame(range(1, 9), array_keys($draf['sidik']['kak']));
        $this->assertContains('Belum disetujui admin (status Draf).', $draf['alasan_draf']);
        $this->assertSame(['kak'], array_keys(DrafDokumen::rekam($repo->temukan($id), 'KAK', null, self::LEADING)['sidik']));
        $rab = DrafDokumen::rekam($repo->temukan($id), 'RAB', 'Komite', self::LEADING);
        $this->assertSame([null, null, ['rab']], [$rab['lengkap'], $rab['jumlah_bagian'], array_keys($rab['sidik'])]);

        $this->assertSame('belum', $this->keadaan($id, null, 'lengkap')['keadaan']);
        $this->assertSame(['keadaan' => 'sesuai', 'label' => 'Draf sesuai data terbaru', 'keterangan' => DrafDokumen::KEADAAN['sesuai'][1], 'berubah' => []], $this->keadaan($id, $draf, 'lengkap'));

        // Sidik tidak bergantung waktu membuka.
        $this->travel(3)->days();
        $this->assertSame($draf['sidik'], $this->sidik($id, 'lengkap'));

        // Tempat diganti → hanya bagian 5 yang berubah.
        $repo->simpan(['tempat' => 'Aula Utama MTsN 2 Kota Malang'] + $repo->temukan($id), $id);
        $keadaan = $this->keadaan($id, $draf, 'lengkap');
        $this->assertSame('berubah', $keadaan['keadaan']);
        $this->assertSame([['kunci' => 'kak-5', 'label' => '5. Tempat Pelaksanaan', 'jangkar' => 'susun-bagian-5']], $keadaan['berubah']);

        // Rincian RAB bertambah → RAB & Pembiayaan berubah.
        RincianRab::tambah($id, 'Komite', ['uraian' => 'Konsumsi rapat koordinasi', 'kategori' => '', 'volume' => '20', 'satuan' => 'kotak', 'harga_satuan' => '25.000']);
        $kunci = array_column($this->keadaan($id, $draf, 'lengkap')['berubah'], 'kunci');
        $this->assertContains('kak-8', $kunci);
        $this->assertContains('rab', $kunci);
        $drafKak = DrafDokumen::rekam(['tempat' => null] + $repo->temukan($id), 'KAK', null, self::LEADING);
        $this->assertSame(['kak-5'], array_column($this->keadaan($id, $drafKak, 'KAK')['berubah'], 'kunci'), 'Jenis KAK tidak membandingkan RAB & cover.');

        // Draf lama tanpa sidik: semua bagian dianggap berubah.
        $this->assertCount(11, $this->keadaan($id, ['sidik' => []] + $draf, 'lengkap')['berubah']);
    }

    public function test_simpan_timpa_hapus_dan_aturan(): void
    {
        $repo = app(DrafDokumenRepository::class);
        $id = $this->kegiatanBaru();

        $hasil = DrafDokumen::simpan($id, 'lengkap', null, self::LEADING);
        $this->assertSame([true, 'Draf Set lengkap (cover, KAK/TOR, RAB) · Komite tersimpan — lanjutkan kapan saja dari tab Susun KAK/TOR.'], [$hasil['baru'], $hasil['pesan']]);
        $pertama = $hasil['draf'];
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $pertama['id']);
        $this->assertSame([$id, 'Komite'], [$pertama['kegiatan_id'], $pertama['sumber']]);
        $this->travel(1)->minutes();
        $hasil = DrafDokumen::simpan($id, 'lengkap', 'Komite', self::LEADING);
        $this->assertSame([false, 'Draf Set lengkap (cover, KAK/TOR, RAB) · Komite diperbarui.'], [$hasil['baru'], $hasil['pesan']]);
        $kedua = $hasil['draf'];
        $this->assertSame($pertama['id'], $kedua['id'], 'Jenis × sumber yang sama menimpa draf.');
        $this->assertGreaterThan($pertama['disimpan_pada'], $kedua['disimpan_pada']);
        $this->travel(1)->minutes();
        DrafDokumen::simpan($id, 'RAB', 'Komite', self::LEADING);
        $this->assertSame(['RAB', 'lengkap'], array_column($repo->untukKegiatan($id), 'jenis'), 'Terbaru dulu.');
        $this->assertSame($kedua['id'], $repo->temukan($id, 'lengkap', 'Komite')['id']);

        foreach ([['BOS', null, 'jenis'], ['KAK', 'DIPA', 'sumber']] as [$jenis, $sumber, $galat]) {
            try {
                DrafDokumen::simpan($id, $jenis, $sumber, self::LEADING);
                $this->fail("{$jenis}/{$sumber} harus ditolak.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey($galat, $e->errors());
            }
        }
        $tanpaSumber = $this->kegiatanBaru(['nama_kegiatan' => 'Rapat Tanpa Dana', 'sumber_dana' => []]);
        try {
            DrafDokumen::simpan($tanpaSumber, 'RAB', null, self::LEADING);
            $this->fail('RAB tanpa sumber dana harus ditolak.');
        } catch (ValidationException $e) {
            $this->assertSame('Pilih sumber dana kegiatan dulu — RAB disusun per sumber dana.', $e->errors()['sumber'][0]);
        }
        $this->assertNull(DrafDokumen::simpan($tanpaSumber, 'KAK+TOR', null, self::LEADING)['draf']['sumber']);

        $this->assertTrue(DrafDokumen::hapus($id, 'RAB', 'Komite', self::LEADING));
        $this->assertFalse(DrafDokumen::hapus($id, 'RAB', 'Komite', self::LEADING));
        $this->assertSame(['lengkap'], array_column($repo->untukKegiatan($id), 'jenis'));

        $lain = $this->kegiatanBaru(['nama_kegiatan' => 'Lomba Kreativitas Siswa', 'leading' => '1.2', 'user_id' => 4]);
        $terkunci = $this->kegiatanBaru(['nama_kegiatan' => 'Rapat Diajukan', 'status' => 'diperiksa']);
        foreach ([[$lain, AuthorizationException::class], [$terkunci, AuthorizationException::class], [999, RuntimeException::class]] as [$kegiatan, $kelas]) {
            try {
                DrafDokumen::simpan($kegiatan, 'lengkap', null, self::LEADING);
                $this->fail("Kegiatan {$kegiatan} harus ditolak.");
            } catch (AuthorizationException|RuntimeException $e) {
                $this->assertInstanceOf($kelas, $e);
            }
        }
        $this->expectException(AuthorizationException::class);
        DrafDokumen::hapus($terkunci, 'lengkap', 'Komite', self::LEADING);
    }

    public function test_contoh_draf_kegiatan_1(): void
    {
        $draf = app(DrafDokumenRepository::class)->untukKegiatan(1);
        $this->assertCount(1, $draf);
        $this->assertSame(['KAK+TOR', 'Komite', 'Muslimin, M.Pd.'], [$draf[0]['jenis'], $draf[0]['sumber'], $draf[0]['oleh']]);
        $this->assertSame(['kak-5'], array_column($this->keadaan(1, $draf[0], 'KAK+TOR')['berubah'], 'kunci'));
        $this->assertSame([], app(DrafDokumenRepository::class)->untukKegiatan(2));
        $this->assertSame('/kegiatan/1/dokumen?jenis=KAK%2BTOR&sumber=Komite', parse_url(DrafDokumen::tautan($draf[0]), PHP_URL_PATH).'?'.parse_url(DrafDokumen::tautan($draf[0]), PHP_URL_QUERY));
        $this->assertSame('/kegiatan/1/dokumen?sumber=Komite', parse_url($t = DrafDokumen::tautan(['jenis' => 'lengkap'] + $draf[0]), PHP_URL_PATH).'?'.parse_url($t, PHP_URL_QUERY));
    }

    public function test_simpan_draf_dari_tab_susun(): void
    {
        $id = $this->kegiatanBaru();

        $halaman = Livewire::test(SusunDokumen::class, ['kegiatan' => $id])
            ->assertSeeHtml('data-testid="status-draf-dokumen" data-keadaan="belum"')
            ->assertSeeHtml('data-testid="draf-dokumen" data-ada="tidak" data-keadaan="belum"')
            ->assertDontSeeHtml('data-testid="hapus-draf"')
            ->assertSeeHtml('data-testid="alasan-draf-susun"')
            ->assertSeeHtml('data-alasan="status"')
            ->call('simpanDraf')
            ->assertDispatched('notify', message: 'Draf Set lengkap (cover, KAK/TOR, RAB) · Komite tersimpan — lanjutkan kapan saja dari tab Susun KAK/TOR.', type: 'success')
            ->assertDispatched('draf-dokumen-berubah', kegiatan: $id)
            ->assertSeeHtml('data-testid="draf-dokumen" data-ada="ya" data-keadaan="sesuai"')
            ->assertSeeHtml('data-testid="banding-draf"')
            ->assertSee('Perbarui draf');

        // Lengkapi tempat dari kartu bagian 5 → bagian itu berubah sejak draf.
        $halaman->call('bukaIsian', 5)->set('isian.tempat', 'Aula Utama MTsN 2 Kota Malang')->call('simpanIsian')
            ->assertSeeHtml('data-keadaan="berubah"')
            ->assertSeeHtml('data-berubah="kak-5"')
            ->assertSeeHtml('href="#susun-bagian-5"');

        // Jenis lain: draf set lengkap tampil sebagai draf lain yang bisa dilanjutkan.
        $halaman->set('jenis', 'RAB')
            ->assertSeeHtml('data-testid="draf-dokumen" data-ada="ya" data-keadaan="belum"')
            ->assertSeeHtml('data-draf="lengkap|Komite"')
            ->assertSeeHtml('data-testid="lanjutkan-draf"')
            ->set('jenis', 'lengkap')
            ->call('simpanDraf')
            ->assertSeeHtml('data-keadaan="sesuai"')
            ->call('hapusDraf')
            ->assertDispatched('notify', message: 'Draf Set lengkap (cover, KAK/TOR, RAB) · Komite dihapus.', type: 'info')
            ->assertSeeHtml('data-testid="draf-dokumen" data-ada="tidak" data-keadaan="belum"');

        // Terkunci: draf hanya dilihat.
        $repo = app(KegiatanRepository::class);
        DrafDokumen::simpan($id, 'lengkap', null, self::LEADING);
        $repo->simpan(['status' => 'diperiksa'] + $repo->temukan($id), $id);
        Livewire::test(SusunDokumen::class, ['kegiatan' => $id])
            ->assertSeeHtml('data-testid="draf-dokumen" data-ada="ya" data-keadaan="sesuai"')
            ->assertDontSeeHtml('data-testid="simpan-draf-dokumen"')
            ->assertDontSeeHtml('data-testid="hapus-draf"')
            ->call('simpanDraf')
            ->assertDispatched('notify', message: 'Kegiatan berstatus Diperiksa sehingga terkunci — draf dokumen hanya bisa dilihat.', type: 'error');
    }

    public function test_hapus_menolak_draf_yang_berubah_di_tempat_lain(): void
    {
        $id = $this->kegiatanBaru();
        $versi = DrafDokumen::versi(DrafDokumen::simpan($id, 'lengkap', null, self::LEADING)['draf']);
        $this->travel(1)->seconds();
        DrafDokumen::simpan($id, 'lengkap', null, self::LEADING);

        try {
            DrafDokumen::hapus($id, 'lengkap', 'Komite', self::LEADING, $versi);
            $this->fail('Draf yang disimpan ulang tidak boleh terhapus dengan versi lama.');
        } catch (PengaturanBerubah $e) {
            $this->assertStringContainsString('disimpan ulang di tempat lain', $e->getMessage());
        }
        $this->assertNotNull(app(DrafDokumenRepository::class)->temukan($id, 'lengkap', 'Komite'));
        $terbaru = DrafDokumen::versi(app(DrafDokumenRepository::class)->temukan($id, 'lengkap', 'Komite'));
        $this->assertTrue(DrafDokumen::hapus($id, 'lengkap', 'Komite', self::LEADING, $terbaru));
        try {
            DrafDokumen::hapus($id, 'lengkap', 'Komite', self::LEADING, $terbaru);
            $this->fail('Draf yang sudah dihapus di tempat lain harus dikabarkan.');
        } catch (PengaturanBerubah $e) {
            $this->assertSame('Draf ini sudah dihapus di tempat lain.', $e->getMessage());
        }

        // Tab Susun: versi dicatat saat dirender; draf disimpan ulang di tab lain → hapus ditolak & tampilan dimuat ulang.
        DrafDokumen::simpan($id, 'lengkap', null, self::LEADING);
        $halaman = Livewire::test(SusunDokumen::class, ['kegiatan' => $id])->assertSeeHtml('data-keadaan="sesuai"');
        $this->assertSame(DrafDokumen::versi(app(DrafDokumenRepository::class)->temukan($id, 'lengkap', 'Komite')), $halaman->get('versiDraf'));
        $this->travel(1)->seconds();
        DrafDokumen::simpan($id, 'lengkap', null, self::LEADING);
        $halaman->call('hapusDraf')
            ->assertDispatched('notify', type: 'error')
            ->assertNotDispatched('draf-dokumen-berubah');
        $this->assertNotNull(app(DrafDokumenRepository::class)->temukan($id, 'lengkap', 'Komite'));
        $halaman->call('hapusDraf')->assertDispatched('draf-dokumen-berubah', kegiatan: $id);
        $this->assertNull(app(DrafDokumenRepository::class)->temukan($id, 'lengkap', 'Komite'));

        // Kabar dari tab lain memuat ulang daftar draf.
        DrafDokumen::simpan($id, 'RAB', 'Komite', self::LEADING);
        $halaman->assertDontSeeHtml('data-draf="RAB|Komite"')
            ->dispatch('draf-dokumen-diperbarui', kegiatan: $id)
            ->assertSeeHtml('data-draf="RAB|Komite"');
    }

    public function test_penyimpanan_gagal_ditulis(): void
    {
        $id = $this->kegiatanBaru();
        $this->app->instance(DrafDokumenRepository::class, new class extends StubDrafDokumenRepository
        {
            public function simpan(int $kegiatanId, array $draf): array
            {
                throw new RuntimeException('Draf dokumen gagal disimpan — coba lagi.');
            }
        });

        Livewire::test(SusunDokumen::class, ['kegiatan' => $id])
            ->call('simpanDraf')
            ->assertDispatched('notify', message: 'Draf dokumen gagal disimpan — coba lagi.', type: 'error')
            ->assertNotDispatched('draf-dokumen-berubah')
            ->assertSeeHtml('data-keadaan="belum"');
        $this->putJson(route('kegiatan.draf.simpan', ['kegiatan' => $id]), ['jenis' => 'lengkap'])
            ->assertStatus(503)
            ->assertJson(['pesan' => 'Draf dokumen gagal disimpan — coba lagi.']);
    }

    public function test_endpoint_draf_dokumen(): void
    {
        $id = $this->kegiatanBaru();

        $this->getJson(route('kegiatan.draf', ['kegiatan' => $id]))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJson(['kegiatan_id' => $id, 'dapat_diubah' => true, 'alasan_terkunci' => null, 'draf' => []])
            ->assertJsonPath('keadaan.berubah.label', 'Berubah sejak draf disimpan');

        $baru = $this->putJson(route('kegiatan.draf.simpan', ['kegiatan' => $id]), ['jenis' => 'lengkap'])
            ->assertCreated()
            ->assertJsonPath('baru', true)
            ->assertJsonPath('draf.sumber', 'Komite')
            ->assertJsonPath('draf.nama', 'Set lengkap (cover, KAK/TOR, RAB) · Komite')
            ->assertJsonPath('draf.keadaan.keadaan', 'sesuai')
            ->assertJsonMissingPath('draf.sidik');
        $this->putJson(route('kegiatan.draf.simpan', ['kegiatan' => $id]), ['jenis' => 'lengkap', 'sumber' => 'Komite'])
            ->assertOk()
            ->assertJsonPath('baru', false)
            ->assertJsonPath('draf.id', $baru->json('draf.id'));
        $this->putJson(route('kegiatan.draf.simpan', ['kegiatan' => $id]), ['jenis' => 'BOS'])
            ->assertStatus(422)
            ->assertJsonPath('galat.jenis.0', 'Jenis dokumen tidak dikenal.');
        $this->putJson(route('kegiatan.draf.simpan', ['kegiatan' => $id]), ['jenis' => 'RAB', 'sumber' => 'DIPA'])
            ->assertStatus(422)
            ->assertJsonStructure(['pesan', 'galat' => ['sumber']]);

        // Data berubah → keadaan draf berubah beserta bagiannya.
        $repo = app(KegiatanRepository::class);
        $repo->simpan(['tempat' => 'Aula Utama MTsN 2 Kota Malang'] + $repo->temukan($id), $id);
        $data = $this->getJson(route('kegiatan.draf', ['kegiatan' => $id]))->assertOk();
        $this->assertSame(['berubah', ['kak-5']], [$data->json('draf.0.keadaan.keadaan'), array_column($data->json('draf.0.keadaan.berubah'), 'kunci')]);
        $versi = $data->json('draf.0.versi');
        $this->assertStringContainsString('/kegiatan/'.$id.'/dokumen?sumber=Komite', $data->json('draf.0.tautan'));

        // Hapus: versi usang 409, tepat 200, sudah tidak ada 404.
        $this->travel(1)->seconds();
        $this->putJson(route('kegiatan.draf.simpan', ['kegiatan' => $id]), ['jenis' => 'lengkap'])->assertOk();
        $this->deleteJson(route('kegiatan.draf.hapus', ['kegiatan' => $id]), ['jenis' => 'lengkap', 'sumber' => 'Komite', 'versi' => $versi])
            ->assertStatus(409)
            ->assertJsonPath('draf.jenis', 'lengkap')
            ->assertJsonStructure(['pesan', 'draf', 'versi']);
        $terbaru = $this->getJson(route('kegiatan.draf', ['kegiatan' => $id]))->json('draf.0.versi');
        $this->deleteJson(route('kegiatan.draf.hapus', ['kegiatan' => $id]), ['jenis' => 'lengkap', 'sumber' => 'Komite', 'versi' => $terbaru])
            ->assertOk()
            ->assertJson(['pesan' => 'Draf Set lengkap (cover, KAK/TOR, RAB) · Komite dihapus.']);
        $this->deleteJson(route('kegiatan.draf.hapus', ['kegiatan' => $id]), ['jenis' => 'lengkap', 'sumber' => 'Komite'])->assertNotFound();

        // Terkunci: dibaca boleh, simpan/hapus 403; di luar cakupan 403; tidak ada 404.
        $repo->simpan(['status' => 'diperiksa'] + $repo->temukan($id), $id);
        $this->getJson(route('kegiatan.draf', ['kegiatan' => $id]))->assertOk()->assertJsonPath('dapat_diubah', false)
            ->assertJsonPath('alasan_terkunci', 'Kegiatan berstatus Diperiksa sehingga terkunci — draf dokumen hanya bisa dilihat.');
        $this->putJson(route('kegiatan.draf.simpan', ['kegiatan' => $id]), ['jenis' => 'lengkap'])->assertForbidden();
        $this->deleteJson(route('kegiatan.draf.hapus', ['kegiatan' => $id]), ['jenis' => 'lengkap'])->assertForbidden();
        $lain = $this->kegiatanBaru(['nama_kegiatan' => 'Lomba Kreativitas Siswa', 'leading' => '1.2', 'user_id' => 4]);
        $this->getJson(route('kegiatan.draf', ['kegiatan' => $lain]))->assertForbidden();
        $this->putJson(route('kegiatan.draf.simpan', ['kegiatan' => $lain]), ['jenis' => 'lengkap'])->assertForbidden();
        $this->getJson(route('kegiatan.draf', ['kegiatan' => 999]))->assertNotFound();
        $this->putJson(route('kegiatan.draf.simpan', ['kegiatan' => 999]), ['jenis' => 'lengkap'])->assertNotFound();
        $this->deleteJson(route('kegiatan.draf.hapus', ['kegiatan' => 999]), ['jenis' => 'lengkap'])->assertNotFound();
    }
}
