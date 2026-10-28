<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Support\Dokumen\PemeriksaanCetak;
use App\Support\Dokumen\StatusCetak;
use App\Support\Rab\RincianRab;
use InvalidArgumentException;
use Tests\TestCase;

class PemeriksaanCetakTest extends TestCase
{
    /** @param  array<string, mixed>  $isi */
    private function kegiatan(array $isi = []): int
    {
        return app(KegiatanRepository::class)->simpan($isi + ['nama_kegiatan' => 'Rapat Kerja Madrasah', 'leading' => '1.6', 'user_id' => 1, 'tahun_anggaran' => 2026, 'sumber_dana' => [['sumber' => 'DIPA', 'pagu' => 2_000_000]]]);
    }

    public function test_draf_belum_lengkap_tetap_boleh_dicetak_bercap_draf(): void
    {
        $id = $this->kegiatan();
        $kak = PemeriksaanCetak::periksa(app(KegiatanRepository::class)->temukan($id), 'KAK+TOR');

        $this->assertSame(['tujuan', 'sasaran', 'tempat', 'matrik', 'jadwal'], array_column($kak['cek'], 'kunci'), 'Butir aturan 8 untuk KAK/TOR.');
        $this->assertSame([true, false, false, true, StatusCetak::TANDA_AIR, false], [$kak['boleh_cetak'], $kak['lengkap'], collect($kak['cek'])->contains('lengkap', true), $kak['draf'], $kak['tanda_air'], $kak['siap']]);
        $this->assertSame(['status', 'kurang'], array_column($kak['alasan_draf'], 'kunci'));
        $this->assertSame(StatusCetak::SARAN['kurang'], $kak['alasan_draf'][1]['saran']);
        $this->assertSame(route('kegiatan.edit', ['kegiatan' => $id]).'#identitas', $kak['cek'][0]['tautan']);
        $this->assertNotEmpty(array_filter($kak['kurang'], fn (string $k) => str_contains(mb_strtolower($k), 'tujuan')), 'Isian kurang dari susunan KAK.');
        $this->assertMatchesRegularExpression('/^KAK-TOR_Rapat_Kerja_Madrasah_DIPA_2026_DRAF\.pdf$/', $kak['nama_berkas']);

        $rab = PemeriksaanCetak::periksa(app(KegiatanRepository::class)->temukan($id), 'RAB', 'DIPA');
        $this->assertSame([['rincian_rab', false]], array_map(fn ($c) => [$c['kunci'], $c['lengkap']], $rab['cek']));
        RincianRab::tambah($id, 'DIPA', ['uraian' => 'Konsumsi rapat', 'kategori' => 'bahan', 'volume' => '20', 'satuan' => 'kotak', 'harga_satuan' => '25000']);
        $rab = PemeriksaanCetak::periksa(app(KegiatanRepository::class)->temukan($id), 'RAB', 'DIPA');
        $this->assertTrue($rab['lengkap']);
        $this->assertSame(['tujuan', 'sasaran', 'tempat', 'rincian_rab', 'matrik', 'jadwal'], array_column(PemeriksaanCetak::periksa(app(KegiatanRepository::class)->temukan($id))['cek'], 'kunci'), 'Set lengkap memeriksa semuanya.');

        $tanpaSumber = PemeriksaanCetak::periksa(app(KegiatanRepository::class)->temukan($this->kegiatan(['sumber_dana' => []])), 'RAB');
        $this->assertSame([null, false, 'Pilih sumber dana agar RAB dapat disusun.'], [$tanpaSumber['sumber'], $tanpaSumber['cek'][0]['lengkap'], $tanpaSumber['cek'][0]['keterangan']]);
    }

    public function test_rab_disetujui_siap_dicetak_tanpa_cap_meski_kak_masih_draf(): void
    {
        $repo = app(KegiatanRepository::class);
        $repo->simpan(['status' => 'disetujui'] + $repo->temukan(2), 2);
        $kegiatan = $repo->temukan(2);

        $rab = PemeriksaanCetak::periksa($kegiatan, 'RAB', 'DIPA');
        $this->assertSame([false, true, true, [], [], null], [$rab['draf'], $rab['lengkap'], $rab['siap'], $rab['kurang'], $rab['kaidah_gagal'], $rab['tanda_air']]);
        $this->assertStringEndsWith('_DIPA_2026.pdf', $rab['nama_berkas'], 'Tanpa sufiks DRAF walau set lengkapnya masih DRAF.');
        $this->assertStringStartsWith('RAB_', $rab['nama_berkas']);

        $set = PemeriksaanCetak::periksa($kegiatan, 'lengkap', 'Komite');
        $this->assertSame(['Komite', true, false], [$set['sumber'], $set['draf'], $set['siap']]);
        $this->assertSame(['matrik', 'jadwal'], array_column(array_filter($set['cek'], fn ($c) => ! $c['lengkap']), 'kunci'));
        $this->assertStringEndsWith('_KOMITE_2026_DRAF.pdf', $set['nama_berkas']);

        $this->expectException(InvalidArgumentException::class);
        PemeriksaanCetak::periksa($kegiatan, 'Surat');
    }

    public function test_endpoint_periksa_cetak(): void
    {
        $this->getJson(route('kegiatan.periksa_cetak', ['kegiatan' => 2, 'jenis' => 'RAB', 'sumber' => 'Komite']))->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJson(['kegiatan_id' => 2, 'jenis' => 'RAB', 'label_jenis' => 'RAB', 'sumber' => 'Komite', 'boleh_cetak' => true])
            ->assertJsonStructure(['cek' => [['kunci', 'label', 'lengkap', 'keterangan', 'tautan']], 'kurang', 'kaidah_gagal', 'draf', 'alasan_draf', 'tanda_air', 'nama_berkas', 'siap']);
        $this->getJson(route('kegiatan.periksa_cetak', ['kegiatan' => 2]))->assertOk()->assertJson(['jenis' => 'lengkap', 'sumber' => 'DIPA']);
        $this->getJson(route('kegiatan.periksa_cetak', ['kegiatan' => 2, 'jenis' => 'Surat']))->assertNotFound();
        $this->getJson(route('kegiatan.periksa_cetak', ['kegiatan' => $this->kegiatan(), 'sumber' => 'Komite']))->assertNotFound();
        $this->getJson(route('kegiatan.periksa_cetak', ['kegiatan' => 999]))->assertNotFound();
        $this->getJson(route('kegiatan.periksa_cetak', ['kegiatan' => $this->kegiatan(['leading' => '1.2', 'user_id' => 4])]))->assertForbidden();
    }
}
