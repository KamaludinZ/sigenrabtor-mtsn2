<?php

namespace Tests\Feature\RiwayatDokumen;

use App\Contracts\KegiatanRepository;
use App\Contracts\RiwayatDokumenRepository;
use App\Support\Dokumen\CatatRiwayatDokumen;
use App\Support\Dokumen\RiwayatDokumen;
use Tests\TestCase;

class PerbaruiDokumenTest extends TestCase
{
    private const LEADING = ['id' => 1, 'name' => 'Muslimin, M.Pd.', 'role' => 'leading', 'leadings' => ['1.6']];

    private function catatan(string $id): array
    {
        return app(RiwayatDokumenRepository::class)->temukan($id);
    }

    public function test_pencatat_riwayat_dan_unduhan_server_tercatat(): void
    {
        $kegiatan = app(KegiatanRepository::class)->temukan(2);
        $c = CatatRiwayatDokumen::catat($kegiatan, 'lengkap', 'DIPA', 'pdf', 'cetak', self::LEADING);
        $this->assertSame(['lengkap', 'DIPA', 'pdf', 'cetak', 2, true], [$c['jenis'], $c['sumber'], $c['format'], $c['asal'], $c['versi'], $c['bertanda_draf']], 'Contoh rd-2026-2 = v1.');
        $this->assertSame('KAK-TOR_RAB_Supervisi_Akademik_dan_Refleksi_Pembelajaran_DIPA_2026_DRAF.pdf', $c['nama_berkas']);
        $this->assertSame([5_400_000, 9], [$c['ringkas']['total_rab'], $c['ringkas']['jumlah_bagian']]);
        $this->assertCount(9, $c['isi']['bagian']);
        $this->assertSame(3, CatatRiwayatDokumen::catat($kegiatan, 'lengkap', 'DIPA', 'pdf', 'unduh', self::LEADING)['versi'], 'Versi berikutnya.');
        $this->assertNull(CatatRiwayatDokumen::catat($kegiatan, 'KAK+TOR', 'DIPA', 'docx', 'unduh', self::LEADING)['ringkas']['total_rab'], 'Tanpa lembar RAB.');
        $this->assertNull(CatatRiwayatDokumen::dariLembar($kegiatan, 'DIPA', ['cover', 'rab'], 'pdf', 10, self::LEADING), 'Pilihan lembar bukan jenis.');

        $jumlah = count(RiwayatDokumen::semua());
        $unduh = $this->get(route('kegiatan.berkas.unduh', ['kegiatan' => 2, 'format' => 'xlsx', 'sumber' => 'DIPA']))->assertOk();
        $id = $unduh->headers->get('X-Riwayat-Dokumen');
        $this->assertNotNull($id);
        $tercatat = $this->catatan($id);
        $this->assertSame(['RAB', 'xlsx', 'unduh', strlen($unduh->getContent())], [$tercatat['jenis'], $tercatat['format'], $tercatat['asal'], $tercatat['ukuran']]);

        $this->get(route('kegiatan.cetak.pdf', ['kegiatan' => 2, 'sumber' => 'DIPA']))->assertOk()->assertHeader('X-Riwayat-Dokumen');
        $this->get(route('kegiatan.kak.word', ['kegiatan' => 2]))->assertOk()->assertHeader('X-Riwayat-Dokumen');
        $this->get(route('kegiatan.rab.excel', ['kegiatan' => 2, 'sumber' => 'DIPA']))->assertOk()->assertHeader('X-Riwayat-Dokumen');
        $this->get(route('kegiatan.rab.excel', ['kegiatan' => 2]))->assertOk()->assertHeaderMissing('X-Riwayat-Dokumen');
        $this->assertSame($jumlah + 4, count(RiwayatDokumen::semua()));

        config(['sigenrabtor.riwayat.catat_unduhan' => false]);
        $this->get(route('kegiatan.cetak.pdf', ['kegiatan' => 2, 'sumber' => 'DIPA']))->assertOk()->assertHeaderMissing('X-Riwayat-Dokumen');
        $this->assertSame($jumlah + 4, count(RiwayatDokumen::semua()));
    }

    public function test_put_terbitkan_ulang_versi_baru(): void
    {
        $hasil = $this->putJson(route('dokumen.perbarui', ['dokumen' => 'rd-2026-6']))
            ->assertCreated()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('sebelumnya', ['id' => 'rd-2026-6', 'versi' => 1])
            ->assertJsonPath('dokumen.versi', 2)
            ->assertJsonPath('dokumen.asal', 'cetak')
            ->assertJsonPath('dokumen.ringkas.total_rab', 10_000_000)
            ->assertJsonPath('perbedaan.0.kunci', 'total')
            ->assertJsonPath('pesan', 'RAB Excel diterbitkan ulang sebagai v2 dari data terbaru.')
            ->json();
        $this->assertNotNull($this->catatan($hasil['dokumen']['id'])['isi'], 'Isi saat diterbitkan ikut tersimpan.');
        $this->assertSame(9_250_000, $this->catatan('rd-2026-6')['ringkas']['total_rab'], 'Catatan lama tidak berubah.');

        $this->putJson(route('dokumen.perbarui', ['dokumen' => 'rd-2026-6']), ['versi' => 1])->assertStatus(409);
        $this->putJson(route('dokumen.perbarui', ['dokumen' => 'rd-2026-6']), ['asal' => 'arsip'])->assertStatus(422)->assertJsonStructure(['galat' => ['asal']]);
        $this->putJson(route('dokumen.perbarui', ['dokumen' => 'rd-2026-6']), ['versi' => 2, 'asal' => 'unduh', 'nomor_dokumen' => 'RAB/007/2026'])
            ->assertCreated()->assertJsonPath('dokumen.versi', 3)->assertJsonPath('dokumen.nomor_dokumen', 'RAB/007/2026');
        $this->putJson(route('dokumen.perbarui', ['dokumen' => 'rd-2026-6']))->assertCreated()->assertJsonPath('dokumen.nomor_dokumen', 'RAB/007/2026', 'Nomor versi sebelumnya terbawa.');
        $this->putJson(route('dokumen.perbarui', ['dokumen' => 'rd-2025-5']))->assertForbidden();
        $this->putJson(route('dokumen.perbarui', ['dokumen' => 'rd-2025-1']))->assertForbidden();
        $this->putJson(route('dokumen.perbarui', ['dokumen' => 'rd-tidak-ada']))->assertNotFound();
    }

    public function test_patch_beri_nomor_sekali(): void
    {
        $this->patchJson(route('dokumen.perbarui', ['dokumen' => 'rd-2026-1']), ['nomor_dokumen' => '  RAB/001/2026 '])
            ->assertOk()->assertJsonPath('dokumen.nomor_dokumen', 'RAB/001/2026')->assertJsonPath('pesan', 'Nomor RAB/001/2026 diberikan pada RAB v1.');
        $this->assertSame('RAB/001/2026', $this->catatan('rd-2026-1')['nomor_dokumen']);
        $this->patchJson(route('dokumen.perbarui', ['dokumen' => 'rd-2026-1']), ['nomor_dokumen' => 'RAB/002/2026'])->assertStatus(409);

        $this->patchJson(route('dokumen.perbarui', ['dokumen' => 'rd-2026-6']), ['nomor_dokumen' => 'rab/001/2026'])
            ->assertStatus(422)->assertJsonPath('galat.nomor_dokumen', 'Nomor rab/001/2026 sudah dipakai dokumen lain di TA 2026.');
        $this->patchJson(route('dokumen.perbarui', ['dokumen' => 'rd-2026-6']), ['nomor_dokumen' => 'RAB#9'])->assertStatus(422);
        $this->patchJson(route('dokumen.perbarui', ['dokumen' => 'rd-2026-6']), ['nomor_dokumen' => ''])->assertStatus(422)->assertJsonPath('galat.nomor_dokumen', 'Nomor dokumen wajib diisi.');
        $this->patchJson(route('dokumen.perbarui', ['dokumen' => 'rd-2026-6']), ['nomor_dokumen' => str_repeat('A', 101)])->assertStatus(422);
        $this->patchJson(route('dokumen.perbarui', ['dokumen' => 'rd-2026-6']), ['nomor_dokumen' => 'KAK/031/2025'])->assertOk()->assertJsonPath('dokumen.nomor_dokumen', 'KAK/031/2025', 'Nomor tahun lain boleh sama.');

        // Dokumen buatan sendiri untuk leading yang tidak tertaut: terlihat, tetapi tidak boleh diberi nomor.
        $milik = app(RiwayatDokumenRepository::class)->catat(RiwayatDokumen::entri(['nama_kegiatan' => 'Lomba Leading Lain', 'leading' => '1.2', 'tahun_anggaran' => 2025, 'status' => 'final'], 'RAB', 'DIPA', 'pdf', 'cetak', 'RAB.pdf', 10, false, self::LEADING))['id'];
        $this->patchJson(route('dokumen.perbarui', ['dokumen' => $milik]), ['nomor_dokumen' => 'X/1'])->assertForbidden();
        session(['stub_role' => 'admin']);
        $this->patchJson(route('dokumen.perbarui', ['dokumen' => $milik]), ['nomor_dokumen' => 'X/1'])->assertOk();
    }
}
