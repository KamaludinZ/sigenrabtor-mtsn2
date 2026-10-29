<?php

namespace Tests\Feature\RiwayatDokumen;

use App\Contracts\DrafDokumenRepository;
use App\Contracts\KegiatanRepository;
use App\Contracts\RiwayatDokumenRepository;
use App\Contracts\TahunAnggaranRepository;
use App\Support\Dokumen\RiwayatDokumen;
use Tests\TestCase;

class DuplikatDokumenApiTest extends TestCase
{
    private const LEADING = ['id' => 1, 'name' => 'Muslimin, M.Pd.', 'role' => 'leading', 'leadings' => ['1.6']];

    private function catat(int $kegiatan, string $jenis = 'KAK+TOR', ?string $sumber = null): string
    {
        return app(RiwayatDokumenRepository::class)->catat(RiwayatDokumen::entri(app(KegiatanRepository::class)->temukan($kegiatan), $jenis, $sumber, 'pdf', 'cetak', 'BERKAS.pdf', 100, true, self::LEADING))['id'];
    }

    public function test_rencana_duplikat(): void
    {
        $this->getJson(route('dokumen.duplikat.rencana', ['dokumen' => 'rd-2026-1']))
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('bisa', true)->assertJsonPath('mode', 'tahun_ini')
            ->assertJsonPath('nama', 'Supervisi Akademik dan Refleksi Pembelajaran (salinan)')
            ->assertJsonPath('nama_terkunci', false)
            ->assertJsonStructure(['perubahan', 'catatan', 'ringkasan' => ['rab', 'matrik', 'jadwal'], 'salinan_sebelumnya', 'dokumen']);
        $this->getJson(route('dokumen.duplikat.rencana', ['dokumen' => 'rd-2025-5']))->assertOk()->assertJsonPath('bisa', false)->assertJsonPath('tautan.url', route('kegiatan.create'));
        $this->getJson(route('dokumen.duplikat.rencana', ['dokumen' => 'rd-2025-1']))->assertForbidden();
        $this->getJson(route('dokumen.duplikat.rencana', ['dokumen' => 'rd-tidak-ada']))->assertNotFound();
    }

    public function test_duplikat_tahun_ini_beserta_draf_dokumen(): void
    {
        $this->postJson(route('dokumen.duplikat', ['dokumen' => 'rd-2026-1']), ['nama' => 'Review RKJM dan Penyusunan RKTM'])
            ->assertStatus(422)->assertJsonPath('galat.nama', 'Sudah ada kegiatan bernama “Review RKJM dan Penyusunan RKTM” di TA 2026 — pakai nama lain.');
        $this->postJson(route('dokumen.duplikat', ['dokumen' => 'rd-2026-1']), ['nama' => '  '])->assertStatus(422)->assertJsonPath('galat.nama', 'Nama kegiatan baru wajib diisi.');
        $this->postJson(route('dokumen.duplikat', ['dokumen' => 'rd-2026-1']), ['nama' => ['bukan', 'teks']])->assertStatus(422)->assertJsonStructure(['galat' => ['nama']]);

        $baru = $this->idKegiatanBaru();
        $hasil = $this->postJson(route('dokumen.duplikat', ['dokumen' => 'rd-2026-1']), ['nama' => 'Supervisi Akademik Semester Genap'])
            ->assertCreated()
            ->assertJsonPath('id', $baru)
            ->assertJsonPath('mode', 'tahun_ini')
            ->assertJsonPath('draf.jenis', 'RAB')
            ->assertJsonPath('draf.sumber', 'DIPA')
            ->assertJsonPath('draf.nama', 'RAB · DIPA')
            ->assertJsonPath('pesan', 'Kegiatan “Supervisi Akademik Semester Genap” dibuat sebagai draf TA 2026 beserta draf RAB · DIPA.')
            ->json();
        $this->assertSame(route('kegiatan.dokumen', ['kegiatan' => $baru, 'jenis' => 'RAB', 'sumber' => 'DIPA']), $hasil['tautan_dokumen']);
        $this->assertNotNull(app(DrafDokumenRepository::class)->temukan($baru, 'RAB', 'DIPA'), 'Draf dokumen kegiatan salinan tersimpan.');
        $this->assertSame('Supervisi Akademik Semester Genap', app(KegiatanRepository::class)->temukan($baru)['nama_kegiatan']);

        // Salinan set lengkap tanpa sumber dana (kegiatan tanpa sumber dana tidak punya RAB): draf belum bisa, ada catatan.
        $tanpaSumber = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Kegiatan Tanpa Sumber', 'leading' => '1.6', 'user_id' => 1]);
        $dokumen = $this->catat($tanpaSumber);
        $tanpa = $this->postJson(route('dokumen.duplikat', ['dokumen' => $dokumen]), ['nama' => 'Kegiatan Tanpa Sumber Kedua'])->assertCreated()->json();
        $this->assertSame('KAK+TOR', $tanpa['draf']['jenis'], 'KAK/TOR tidak butuh sumber dana.');
    }

    public function test_duplikat_tahun_lalu_dan_penolakan(): void
    {
        $repo = app(KegiatanRepository::class);
        $lama = $repo->simpan(['nama_kegiatan' => 'Pentas Seni Akhir Tahun', 'leading' => '1.6', 'tahun_anggaran' => 2025, 'user_id' => 1]);
        $dokumen = $this->catat($lama);
        $this->postJson(route('dokumen.duplikat', ['dokumen' => $dokumen]), ['nama' => 'nama diabaikan'])
            ->assertCreated()->assertJsonPath('mode', 'tahun_lalu')->assertJsonPath('nama', 'Pentas Seni Akhir Tahun');
        $this->postJson(route('dokumen.duplikat', ['dokumen' => $dokumen]))->assertStatus(422)->assertJsonStructure(['galat' => ['duplikat']]);

        $this->postJson(route('dokumen.duplikat', ['dokumen' => 'rd-2025-5']))->assertStatus(422)->assertJsonPath('galat.duplikat', fn ($p) => str_contains($p, 'tidak lagi tersimpan'));
        $this->postJson(route('dokumen.duplikat', ['dokumen' => 'rd-2025-1']))->assertForbidden();
        $this->postJson(route('dokumen.duplikat', ['dokumen' => 'rd-tidak-ada']))->assertNotFound();

        $buatan = $repo->simpan(['nama_kegiatan' => 'Kegiatan Buatan Sendiri', 'leading' => '1.2', 'user_id' => 1]);
        $this->postJson(route('dokumen.duplikat', ['dokumen' => $this->catat($buatan)]), ['nama' => 'Salinan'])
            ->assertForbidden()->assertJsonPath('pesan', 'Kegiatan baru hanya bisa dibuat untuk leading yang tertaut ke akun Anda — leading 1.2 tidak lagi tertaut.');

        app(TahunAnggaranRepository::class)->kunci(2026, true);
        $this->postJson(route('dokumen.duplikat', ['dokumen' => 'rd-2026-1']), ['nama' => 'Salinan Saat Terkunci'])->assertForbidden();
    }
}
