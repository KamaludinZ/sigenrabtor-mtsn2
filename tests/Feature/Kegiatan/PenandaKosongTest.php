<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Livewire\Kegiatan\PratinjauDokumen;
use App\Support\Dokumen\PenandaKosong;
use App\Support\Dokumen\SusunanKak;
use App\Support\Dokumen\SusunanRab;
use Livewire\Livewire;
use Tests\TestCase;

class PenandaKosongTest extends TestCase
{
    private const LEADING = ['id' => 1, 'name' => 'Muslimin, M.Pd.', 'role' => 'leading', 'leadings' => ['1.6']];

    private const ADMIN = ['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin', 'leadings' => []];

    private function kegiatanBaru(array $data = []): int
    {
        return app(KegiatanRepository::class)->simpan($data + ['nama_kegiatan' => 'Rapat Kerja Madrasah', 'leading' => '1.6', 'user_id' => 1, 'tahun_anggaran' => 2026]);
    }

    /** @return list<array<string, mixed>> */
    private function daftar(int $id): array
    {
        $kegiatan = app(KegiatanRepository::class)->temukan($id);

        return PenandaKosong::daftar(SusunanKak::dari($kegiatan), SusunanRab::dari($kegiatan));
    }

    public function test_daftar_penanda_kak_dan_rab_beserta_tingkat_dan_asal(): void
    {
        $daftar = $this->daftar($this->kegiatanBaru());
        $kak = array_values(array_filter($daftar, fn (array $p) => $p['dokumen'] === 'kak'));
        $perPesan = array_column($kak, null, 'pesan');

        $this->assertSame(['dokumen', 'identitas', 'tujuan'], [$perPesan['Lengkapi tujuan kegiatan.']['tingkat'], $perPesan['Lengkapi tujuan kegiatan.']['asal'], $perPesan['Lengkapi tujuan kegiatan.']['sunting']]);
        $this->assertSame(['wajib', 'pelaksanaan', 'tempat'], [$perPesan['Lengkapi tempat pelaksanaan.']['tingkat'], $perPesan['Lengkapi tempat pelaksanaan.']['asal'], $perPesan['Lengkapi tempat pelaksanaan.']['sunting']]);
        $this->assertSame(['wajib', 'pelaksanaan', null], [$perPesan['Lengkapi tanggal mulai & lama kegiatan.']['tingkat'], $perPesan['Lengkapi tanggal mulai & lama kegiatan.']['asal'], $perPesan['Lengkapi tanggal mulai & lama kegiatan.']['sunting']]);
        $this->assertSame(['matrik', 'jadwal', 'sumber_dana'], [$perPesan['Lengkapi matrik tahapan (tab Matrik & Jadwal).']['asal'], $perPesan['Lengkapi jadwal kegiatan (tab Matrik & Jadwal).']['asal'], $perPesan['Lengkapi sumber dana (bagian Sumber Dana).']['asal']]);
        $this->assertSame([3, 4, 5, 8], array_values(array_unique(array_column(array_filter($kak, fn ($p) => in_array($p['asal'], ['identitas', 'pelaksanaan', 'sumber_dana'], true) && $p['bagian'] !== 9), 'bagian'))));
        $this->assertSame(array_column($kak, 'bagian'), collect($kak)->pluck('bagian')->sort()->values()->all(), 'Urut lembar.');

        // RAB tanpa sumber dana: header kosong + sumber dana.
        $rab = array_values(array_filter($daftar, fn (array $p) => $p['dokumen'] === 'rab'));
        $this->assertContains('Pilih sumber dana kegiatan agar RAB dapat disusun.', array_column($rab, 'pesan'));
        $this->assertContains('Kode RKTM belum diisi.', array_column($rab, 'pesan'));
        $this->assertSame(['dokumen'], array_values(array_unique(array_column($rab, 'tingkat'))));

        $ringkasan = PenandaKosong::ringkasan($daftar);
        $this->assertSame(count($daftar), $ringkasan['jumlah']);
        $this->assertSame([2, count($kak), count($rab)], [$ringkasan['wajib'], $ringkasan['kak'], $ringkasan['rab']]);

        // Bersumber dana tanpa rincian: penanda rincian di RAB menggantikan penanda sumber dana.
        $berdana = $this->daftar($this->kegiatanBaru(['nama_kegiatan' => 'Rapat Berdana', 'sumber_dana' => [['sumber' => 'DIPA', 'pagu' => 1_000_000]]]));
        $this->assertContains('Belum ada rincian belanja RAB DIPA.', array_column($berdana, 'pesan'));
        $this->assertNotContains('Pilih sumber dana kegiatan agar RAB dapat disusun.', array_column($berdana, 'pesan'));
    }

    public function test_tempat_melengkapi_mengikuti_peran(): void
    {
        $this->assertSame(['Data Kegiatan › Pelaksanaan', route('kegiatan.edit', ['kegiatan' => 7]).'#pelaksanaan', false],
            array_values(array_intersect_key(PenandaKosong::tempat('pelaksanaan', 7, self::LEADING), array_flip(['label', 'tautan', 'admin']))));
        $this->assertSame(route('kegiatan.edit', ['kegiatan' => 7]).'#sumber-dana', PenandaKosong::tempat('sumber_dana', 7, self::LEADING)['tautan']);
        $this->assertNull(PenandaKosong::tempat('dasar_hukum', 7, self::LEADING)['tautan'], 'Diatur admin.');
        $this->assertSame(route('pengaturan.madrasah.dasar_hukum'), PenandaKosong::tempat('dasar_hukum', 7, self::ADMIN)['tautan']);
        $this->assertNull(PenandaKosong::tempat('pelaksanaan', null, self::LEADING)['tautan'], 'Kegiatan belum tersimpan.');
        $this->assertNull(PenandaKosong::tempat(null, 7, self::LEADING));
        $this->assertNull(PenandaKosong::tempat('asing', 7, self::LEADING));
    }

    public function test_penanda_di_pratinjau_sejalan_dengan_daftar(): void
    {
        foreach ([$this->kegiatanBaru(), $this->kegiatanBaru(['nama_kegiatan' => 'Rapat Berdana', 'sumber_dana' => [['sumber' => 'Komite', 'pagu' => 500_000]]]), 2, 3] as $id) {
            $html = Livewire::test(PratinjauDokumen::class, ['kegiatan' => $id])->html();
            $ringkasan = PenandaKosong::ringkasan($this->daftar($id));
            $this->assertSame($ringkasan['jumlah'], substr_count($html, ' data-kosong'), "Kegiatan {$id}: penanda di lembar = daftar.");
            $this->assertStringContainsString('data-testid="navigasi-kosong" data-jumlah="'.$ringkasan['jumlah'].'"', $html);
        }

        $id = $this->kegiatanBaru();
        $halaman = Livewire::test(PratinjauDokumen::class, ['kegiatan' => $id]);
        $html = $halaman->html();
        $this->assertStringContainsString('data-tingkat="wajib"', $html);
        $this->assertStringContainsString('Wajib sebelum diajukan', $html);
        $this->assertStringContainsString('data-testid="isi-di-sini-tempat"', $html);
        $this->assertStringContainsString('href="'.route('kegiatan.edit', ['kegiatan' => $id]).'#pelaksanaan"', $html);
        $this->assertMatchesRegularExpression('/data-testid="penanda-bagian">\d+ belum lengkap/', $html);
        $this->assertStringContainsString('data-wajib="2"', $html);

        // "Isi di sini" membuka penyunting isian itu di mode sunting.
        $halaman->call('isiDiSini', 'tempat')
            ->assertSet('mode', 'sunting')
            ->assertSet('suntingTerbuka', 'tempat')
            ->set('teksSunting', 'Aula Madrasah')
            ->call('simpanSunting')
            ->assertHasNoErrors()
            ->assertDontSeeHtml('data-testid="isi-di-sini-tempat"');
        $this->assertSame('Aula Madrasah', app(KegiatanRepository::class)->temukan($id)['tempat']);
        $halaman->call('isiDiSini', 'asing')->assertNotFound();

        // Terkunci: tanpa "Isi di sini"; aksinya ditolak lewat notifikasi.
        $terkunci = $this->kegiatanBaru(['nama_kegiatan' => 'Rapat Diajukan', 'status' => 'diperiksa']);
        Livewire::test(PratinjauDokumen::class, ['kegiatan' => $terkunci])
            ->assertDontSeeHtml('data-testid="isi-di-sini-')
            ->call('isiDiSini', 'tujuan')
            ->assertDispatched('notify', type: 'error')
            ->assertSet('mode', 'baca');
    }
}
