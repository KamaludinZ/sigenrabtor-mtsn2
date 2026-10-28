<?php

namespace Tests\Feature\Buku;

use App\Contracts\KegiatanRepository;
use App\Support\Dokumen\KegiatanBuku;
use Tests\TestCase;

class KegiatanBukuTest extends TestCase
{
    public function test_kegiatan_per_buku_dengan_nomor_dan_halaman_dari_susunannya(): void
    {
        $semua = $this->getJson(route('buku.kegiatan'))->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJson(['tahun' => 2026, 'jumlah' => 5, 'jumlah_kegiatan' => 3, 'leading' => [['kode' => '1.6', 'nama_unit' => 'Tim Penjamin Mutu', 'aktif' => true]]])
            ->json();
        $this->assertSame(['DIPA', 'DIPA', 'Komite', 'Komite', 'Komite'], array_column($semua['kegiatan'], 'sumber'), 'Satu baris per buku yang membiayai kegiatan, urut sumber dana baku.');
        $this->assertSame($semua['total'], array_sum(array_column($semua['kegiatan'], 'total')));

        $komite = $this->getJson(route('buku.kegiatan', ['sumber' => 'Komite']))->assertOk()->json();
        $this->assertSame([[1, 1, 3], [2, 3, 11], [3, 2, 19]], array_map(fn ($b) => [$b['no'], $b['id'], $b['halaman']], $komite['kegiatan']));
        $this->assertSame(route('kegiatan.pratinjau', ['kegiatan' => 1, 'sumber' => 'Komite']), $komite['kegiatan'][0]['tautan']);
        $this->assertSame(route('buku.index', ['leading' => '1.6', 'sumber' => 'Komite', 'kegiatan' => 1]), $komite['kegiatan'][0]['tautan_buku']);

        // Saringan tidak mengubah nomor & halaman di buku.
        $repo = app(KegiatanRepository::class);
        $repo->simpan(['status' => 'disetujui'] + $repo->temukan(2), 2);
        $disetujui = $this->getJson(route('buku.kegiatan', ['status' => 'disetujui,acak', 'sumber' => 'Komite']))->assertOk()->json();
        $this->assertSame([['disetujui'], [[3, 2, 19]]], [$disetujui['saringan']['status'], array_map(fn ($b) => [$b['no'], $b['id'], $b['halaman']], $disetujui['kegiatan'])]);
        $this->assertSame(1, $disetujui['per_status']['disetujui']);

        $this->getJson(route('buku.kegiatan', ['cari' => 'REVIEW']))->assertOk()->assertJson(['jumlah' => 2, 'jumlah_kegiatan' => 1]);
        $this->getJson(route('buku.kegiatan', ['cari' => '6.19']))->assertOk()->assertJsonPath('kegiatan.0.id', 2);
        $this->getJson(route('buku.kegiatan', ['draf' => 'ya']))->assertOk()->assertJson(['draf' => 5, 'jumlah' => 5]);
        $this->getJson(route('buku.kegiatan', ['draf' => 'tidak']))->assertOk()->assertJson(['jumlah' => 0, 'kegiatan' => []]);
        $this->getJson(route('buku.kegiatan', ['sumber' => 'BOS']))->assertOk()->assertJson(['saringan' => ['sumber' => ''], 'jumlah' => 5]);
    }

    public function test_cakupan_leading_per_akun(): void
    {
        $this->getJson(route('buku.kegiatan', ['leading' => '1.2']))->assertForbidden();

        $this->withSession(['stub_role' => 'admin']);
        $this->getJson(route('buku.kegiatan', ['leading' => '1.2']))->assertOk()->assertJson(['jumlah' => 0, 'leading' => [['kode' => '1.2']]]);
        $this->assertGreaterThan(1, count($this->getJson(route('buku.kegiatan'))->json('leading')), 'Admin melihat semua leading aktif.');
        $this->assertSame(['leading' => '1.6', 'sumber' => 'DIPA', 'status' => ['draf', 'final'], 'draf' => '', 'cari' => 'rapat'], KegiatanBuku::saringan(['leading' => ' 1.6 ', 'sumber' => 'DIPA', 'status' => ['final', 'draf', 'x'], 'draf' => 'mungkin', 'cari' => 'Rapat']));
    }
}
