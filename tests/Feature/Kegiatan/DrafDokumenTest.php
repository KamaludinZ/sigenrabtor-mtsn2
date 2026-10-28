<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\DrafDokumenRepository;
use App\Contracts\KegiatanRepository;
use App\Livewire\Kegiatan\SusunDokumen;
use App\Support\Dokumen\DrafDokumen;
use App\Support\Dokumen\SusunanKak;
use App\Support\Dokumen\SusunanRab;
use App\Support\Dokumen\TeksCover;
use App\Support\Rab\RincianRab;
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

        $pertama = DrafDokumen::simpan($id, 'lengkap', null, self::LEADING);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $pertama['id']);
        $this->assertSame([$id, 'Komite'], [$pertama['kegiatan_id'], $pertama['sumber']]);
        $this->travel(1)->minutes();
        $kedua = DrafDokumen::simpan($id, 'lengkap', 'Komite', self::LEADING);
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
        $this->assertNull(DrafDokumen::simpan($tanpaSumber, 'KAK+TOR', null, self::LEADING)['sumber']);

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
            ->assertDispatched('notify', message: 'Draf Set lengkap (cover, KAK/TOR, RAB) · Komite tersimpan — lanjutkan kapan saja dari tab ini.', type: 'success')
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
}
