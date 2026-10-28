<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\DrafDokumenRepository;
use App\Contracts\HasilDokumenRepository;
use App\Contracts\KegiatanRepository;
use App\Contracts\TahunAnggaranRepository;
use App\Livewire\Forms\KegiatanForm;
use App\Livewire\Kegiatan\Form;
use App\Support\Dokumen\PembuatDokumen;
use App\Support\Dokumen\StatusDokumen;
use App\Support\Kegiatan\AlurStatus;
use App\Support\Kegiatan\ArsipFinal;
use App\Support\Kegiatan\KunciKegiatan;
use App\Support\Kegiatan\RiwayatStatus;
use App\Support\Pengaturan\PengaturanBerubah;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class AlurStatusTest extends TestCase
{
    private const LEADING = ['id' => 1, 'name' => 'Muslimin, M.Pd.', 'role' => 'leading', 'leadings' => ['1.6']];

    private const ADMIN = ['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin', 'leadings' => []];

    /** @return list<string> */
    private function aksiRiwayat(int $id = 1): array
    {
        return array_column(app(KegiatanRepository::class)->temukan($id)['riwayat_status'] ?? [], 'aksi');
    }

    private function galat(callable $aksi): array
    {
        try {
            $aksi();
        } catch (ValidationException $e) {
            return $e->errors();
        }
        $this->fail('Seharusnya ditolak validasi.');
    }

    public function test_data_alur_untuk_leading(): void
    {
        $kegiatan = app(KegiatanRepository::class)->temukan(1);
        $data = AlurStatus::data($kegiatan, self::LEADING);

        $this->assertSame(['draf', 'Draf', true, null], [$data['status'], $data['label_status'], $data['dapat_diubah'], $data['tahun_terkunci']]);
        $this->assertSame(['draf', 'diperiksa', 'disetujui', 'final'], array_column($data['langkah'], 'kunci'));
        $this->assertSame([true, false, false, false], array_column($data['langkah'], 'aktif'));
        $this->assertSame(['ajukan'], array_column($data['aksi'], 'aksi'));
        $this->assertSame(['diperiksa', 'Diperiksa', false, true, []], [$data['aksi'][0]['ke'], $data['aksi'][0]['label_ke'], $data['aksi'][0]['catatan_wajib'], $data['aksi'][0]['bisa'], $data['aksi'][0]['halangan']]);
        $this->assertSame(AlurStatus::versi($kegiatan), $data['versi']);
        $this->assertSame(['Draf', 'KAK+TOR'], [$data['dokumen']['arti']['label'], $data['dokumen']['dokumen'][0]['jenis']], 'Draf dokumen contoh kegiatan 1 ikut terdaftar.');

        AlurStatus::ubah(1, 'ajukan', null, self::LEADING);
        $admin = AlurStatus::data(app(KegiatanRepository::class)->temukan(1), self::ADMIN);
        $this->assertSame(['setujui', 'kembalikan'], array_column($admin['aksi'], 'aksi'));
        $this->assertSame([false, true], array_column($admin['aksi'], 'catatan_wajib'));
        $this->assertSame([[true, false, false, false], [false, true, false, false]], [array_column($admin['langkah'], 'selesai'), array_column($admin['langkah'], 'aktif')], 'Langkah Draf selesai, Diperiksa aktif.');
        $this->assertSame('ajukan', $admin['riwayat'][0]['aksi'], 'Riwayat terbaru dulu.');
        $this->assertSame('Diajukan untuk diperiksa', $admin['riwayat'][0]['label']);
    }

    public function test_halangan_pengajuan_dibaca_dari_data_tersimpan(): void
    {
        $repo = app(KegiatanRepository::class);
        $ajukan = collect(AlurStatus::data($repo->temukan(3), self::LEADING)['aksi'])->firstWhere('aksi', 'ajukan');
        $this->assertFalse($ajukan['bisa']);
        $this->assertStringContainsString('melebihi pagu', implode(' ', $ajukan['halangan']), 'RAB Komite contoh 3 melebihi pagu.');

        $id = $repo->simpan(['nama_kegiatan' => 'Rapat Koordinasi Guru', 'leading' => '1.6', 'user_id' => 1, 'status' => 'draf']);
        $syarat = AlurStatus::syaratAjukan($repo->temukan($id));
        foreach (array_keys(KegiatanForm::PELAKSANAAN_WAJIB + KegiatanForm::KODE_WAJIB) as $isian) {
            $this->assertArrayHasKey($isian, $syarat);
        }
        $this->assertSame(KegiatanForm::PELAKSANAAN_WAJIB['tempat'], $syarat['tempat']);

        $galat = $this->galat(fn () => AlurStatus::ubah($id, 'ajukan', null, self::LEADING));
        $this->assertArrayHasKey('tanggal_mulai', $galat);
        $this->assertSame('draf', $repo->temukan($id)['status']);
    }

    public function test_ajukan_tarik_menolak_aksi_basi_dan_versi_usang(): void
    {
        $hasil = AlurStatus::ubah(1, 'ajukan', null, self::LEADING);
        $this->assertSame(['diperiksa', 'ajukan', 'diperiksa', AlurStatus::PESAN['ajukan']], [$hasil['kegiatan']['status'], $hasil['entri']['aksi'], $hasil['entri']['status'], $hasil['pesan']]);
        $this->assertSame(AlurStatus::versi($hasil['kegiatan']), $hasil['versi']);

        // Tab lain yang belum dimuat ulang mengajukan lagi: ditolak, riwayat tidak dobel.
        $this->assertSame(['Aksi “Ajukan pemeriksaan” tidak tersedia untuk kegiatan berstatus Diperiksa.'], $this->galat(fn () => AlurStatus::ubah(1, 'ajukan', null, self::LEADING))['aksi']);
        $this->assertSame(['Aksi status tidak dikenal.'], $this->galat(fn () => AlurStatus::ubah(1, 'hapus', null, self::LEADING))['aksi']);

        try {
            AlurStatus::ubah(1, 'tarik', null, self::LEADING, 'usang');
            $this->fail('Versi usang harus ditolak.');
        } catch (PengaturanBerubah $e) {
            $this->assertSame(AlurStatus::PESAN_BENTROK, $e->getMessage());
        }

        $tarik = AlurStatus::ubah(1, 'tarik', null, self::LEADING, $hasil['versi']);
        $this->assertSame(['draf', AlurStatus::PESAN['tarik']], [$tarik['kegiatan']['status'], $tarik['pesan']]);
        $this->assertSame(['ajukan', 'tarik'], array_slice($this->aksiRiwayat(), -2));
    }

    public function test_peran_catatan_dan_tahun_terkunci(): void
    {
        AlurStatus::ubah(1, 'ajukan', null, self::LEADING);
        try {
            AlurStatus::ubah(1, 'setujui', null, self::LEADING);
            $this->fail('Leading tidak bisa menyetujui.');
        } catch (AuthorizationException) {
            $this->assertSame('diperiksa', app(KegiatanRepository::class)->temukan(1)['status']);
        }

        $this->assertSame([RiwayatStatus::CATATAN_WAJIB['kembalikan']], $this->galat(fn () => AlurStatus::ubah(1, 'kembalikan', '  ', self::ADMIN))['catatan']);
        $kembali = AlurStatus::ubah(1, 'kembalikan', "Perbaiki   sasaran.\n\n\n\nLengkapi tempat.", self::ADMIN);
        $this->assertSame(['dikembalikan', "Perbaiki sasaran.\n\nLengkapi tempat."], [$kembali['kegiatan']['status'], $kembali['entri']['catatan']]);
        $this->assertArrayHasKey('sidik', $kembali['entri'], 'Titik awal perbaikan dicatat.');

        app(TahunAnggaranRepository::class)->kunci(2026, true);
        try {
            AlurStatus::ubah(1, 'ajukan', null, self::LEADING);
            $this->fail('Tahun terkunci harus ditolak.');
        } catch (AuthorizationException $e) {
            $this->assertSame(KunciKegiatan::pesanTahun(2026), $e->getMessage());
        }
        $ajukan = collect(AlurStatus::data(app(KegiatanRepository::class)->temukan(1), self::LEADING)['aksi'])->firstWhere('aksi', 'ajukan');
        $this->assertSame([false, KunciKegiatan::pesanTahun(2026)], [$ajukan['bisa'], $ajukan['halangan'][0]]);
        app(TahunAnggaranRepository::class)->kunci(2026, false);
    }

    public function test_perpindahan_status_menyamakan_dokumen_tersimpan(): void
    {
        $hasilRepo = app(HasilDokumenRepository::class);
        $drafRepo = app(DrafDokumenRepository::class);
        PembuatDokumen::susun(1, 'lengkap', null, self::LEADING);
        PembuatDokumen::susun(1, 'RAB', 'Komite', self::LEADING);
        $draf = $drafRepo->temukan(1, 'KAK+TOR', 'Komite');
        $this->assertSame('draf', $draf['status']);

        $this->travel(1)->seconds();
        $ajukan = AlurStatus::ubah(1, 'ajukan', null, self::LEADING);
        $this->assertSame(['hasil' => 2, 'draf' => 1, 'gagal' => null], $ajukan['dokumen']);
        foreach ($hasilRepo->untukKegiatan(1) as $h) {
            $this->assertSame(['diperiksa', true], [$h['status'], $h['bertanda_draf']]);
            $this->assertContains('Belum disetujui admin (status Diperiksa).', $h['alasan_draf']);
        }
        $sesudah = $drafRepo->temukan(1, 'KAK+TOR', 'Komite');
        $this->assertSame(['diperiksa', true], [$sesudah['status'], $sesudah['bertanda_draf']]);
        $this->assertSame([$draf['sidik'], $draf['disimpan_pada'], $draf['oleh']], [$sesudah['sidik'], $sesudah['disimpan_pada'], $sesudah['oleh']], 'Titik lanjut draf tidak berubah.');

        $this->travel(1)->seconds();
        $setujui = AlurStatus::ubah(1, 'setujui', null, self::ADMIN);
        $this->assertSame(2, $setujui['dokumen']['hasil']);
        foreach ($hasilRepo->untukKegiatan(1) as $h) {
            $this->assertSame(['disetujui', 'Koordinator PUSKOM'], [$h['status'], $h['oleh']], 'Hasil disusun ulang saat status berpindah.');
            $this->assertNotContains('Belum disetujui admin (status Disetujui).', $h['alasan_draf']);
            $this->assertSame($h['alasan_draf'] !== [], $h['bertanda_draf']);
        }
        $status = StatusDokumen::untuk(app(KegiatanRepository::class)->temukan(1));
        $this->assertTrue($status['sinkron']);
        $this->assertSame(['disetujui', 'Siap ditandatangani'], [$status['status'], $status['arti']['label']]);
        $this->assertSame(['KAK+TOR', 'RAB', 'lengkap'], array_column($status['dokumen'], 'jenis'), 'Urut DokumenKegiatan::JENIS.');
        $this->assertSame('sesuai', PembuatDokumen::data(1, 'lengkap', null, self::LEADING)['dokumen']['keadaan']['keadaan']);
    }

    public function test_dokumen_bersumber_yang_dilepas_tetap_bercap_draf(): void
    {
        $repo = app(KegiatanRepository::class);
        PembuatDokumen::susun(2, 'RAB', 'DIPA', self::LEADING);
        $kegiatan = $repo->temukan(2);
        $kegiatan['sumber_dana'] = array_values(array_filter($kegiatan['sumber_dana'], fn (array $s) => $s['sumber'] !== 'DIPA'));
        $kegiatan['status'] = 'diperiksa';
        $repo->simpan($kegiatan, 2);

        $this->assertSame(['hasil' => 1, 'draf' => 0], StatusDokumen::sinkronkan(2, self::LEADING));
        $hasil = app(HasilDokumenRepository::class)->temukan(2, 'RAB', 'DIPA');
        $this->assertSame(['diperiksa', true, [sprintf(StatusDokumen::ALASAN_TERLEPAS, 'DIPA')]], [$hasil['status'], $hasil['bertanda_draf'], $hasil['alasan_draf']]);
        $this->assertTrue(collect(StatusDokumen::untuk($repo->temukan(2))['dokumen'])->firstWhere('sumber', 'DIPA')['hasil']['sinkron']);
        $this->assertSame(['hasil' => 0, 'draf' => 0], StatusDokumen::sinkronkan(2, self::LEADING), 'Sudah sama: tidak ditulis ulang.');
    }

    public function test_keadaan_hasil_mengenali_status_yang_berubah(): void
    {
        $repo = app(KegiatanRepository::class);
        PembuatDokumen::susun(1, 'lengkap', null, self::LEADING);
        // Status berubah tanpa lewat AlurStatus (data lama): hasil tidak lagi sesuai.
        $repo->simpan(['status' => 'diperiksa'] + $repo->temukan(1), 1);

        $this->assertSame('status', PembuatDokumen::data(1, 'lengkap', null, self::LEADING)['dokumen']['keadaan']['keadaan']);
        $this->assertFalse(StatusDokumen::untuk($repo->temukan(1))['sinkron']);
    }

    public function test_endpoint_status_dan_perpindahan(): void
    {
        $repo = app(KegiatanRepository::class);
        $this->getJson(route('kegiatan.status', 1))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('status', 'draf')
            ->assertJsonPath('aksi.0.aksi', 'ajukan')
            ->assertJsonPath('aksi.0.bisa', true)
            ->assertJsonStructure(['langkah', 'riwayat', 'dokumen' => ['arti', 'dokumen', 'sinkron'], 'versi']);
        $this->getJson(route('kegiatan.status', 999))->assertNotFound();
        $lain = $repo->simpan(['nama_kegiatan' => 'Kegiatan Leading Lain', 'leading' => '1.2', 'user_id' => 3, 'status' => 'draf']);
        $this->getJson(route('kegiatan.status', $lain))->assertForbidden();
        $this->postJson(route('kegiatan.status.ubah', $lain), ['aksi' => 'ajukan'])->assertForbidden();

        $versi = AlurStatus::versi($repo->temukan(1));
        $this->postJson(route('kegiatan.status.ubah', 1), ['aksi' => 'ajukan', 'versi' => 'usang'])
            ->assertStatus(409)->assertJsonPath('versi', $versi);
        $this->postJson(route('kegiatan.status.ubah', 1), ['aksi' => 'ajukan', 'versi' => $versi])
            ->assertOk()
            ->assertJsonPath('status', 'diperiksa')
            ->assertJsonPath('label_status', 'Diperiksa')
            ->assertJsonPath('entri.aksi', 'ajukan')
            ->assertJsonPath('dokumen.gagal', null)
            ->assertJsonPath('pesan', AlurStatus::PESAN['ajukan']);
        $this->postJson(route('kegiatan.status.ubah', 1), ['aksi' => 'ajukan'])
            ->assertStatus(422)->assertJsonPath('galat.aksi.0', 'Aksi “Ajukan pemeriksaan” tidak tersedia untuk kegiatan berstatus Diperiksa.');
        $this->postJson(route('kegiatan.status.ubah', 1), ['aksi' => 'setujui'])->assertForbidden();

        $this->withSession(['stub_role' => 'admin'])->postJson(route('kegiatan.status.ubah', 1), ['aksi' => 'kembalikan'])
            ->assertStatus(422)->assertJsonPath('galat.catatan.0', RiwayatStatus::CATATAN_WAJIB['kembalikan']);
        $this->withSession(['stub_role' => 'admin'])->postJson(route('kegiatan.status.ubah', 1), ['aksi' => 'setujui'])
            ->assertOk()->assertJsonPath('status', 'disetujui');
        $this->withSession(['stub_role' => 'admin'])->postJson(route('kegiatan.status.ubah', 1), ['aksi' => 'setujui'])
            ->assertStatus(422)->assertJsonPath('galat.aksi.0', 'Keputusan ini tidak tersedia untuk status kegiatan saat ini.');
        $this->withSession(['stub_role' => 'admin'])->postJson(route('kegiatan.status.ubah', 1), ['aksi' => 'finalkan'])
            ->assertStatus(422)->assertJsonPath('galat.arsip.0', ArsipFinal::pesanKurang(['Komite']));
        $this->assertSame(['ajukan', 'setujui'], array_slice($this->aksiRiwayat(), -2));
    }

    public function test_panel_status_menolak_aksi_yang_sudah_basi(): void
    {
        $tabLain = Livewire::test(Form::class, ['kegiatan' => 1]);
        Livewire::test(Form::class, ['kegiatan' => 1])->call('ubahStatus', 'ajukan')->assertSet('status', 'diperiksa');

        $tabLain->call('ubahStatus', 'ajukan')
            ->assertDispatched('notify', type: 'error')
            ->assertSet('status', 'diperiksa');
        $this->assertSame(1, count(array_keys($this->aksiRiwayat(), 'ajukan')), 'Pengajuan tidak tercatat dua kali.');

        // Leading menarik pengajuan yang sudah disetujui admin di tab lain: ditolak, status tersimpan dimuat.
        AlurStatus::ubah(1, 'setujui', null, self::ADMIN);
        $tabLain->call('ubahStatus', 'tarik')
            ->assertDispatched('notify', message: 'Aksi “Tarik pengajuan” tidak tersedia untuk kegiatan berstatus Disetujui.', type: 'error')
            ->assertSet('status', 'disetujui');
        $this->assertSame('disetujui', app(KegiatanRepository::class)->temukan(1)['status']);
    }
}
