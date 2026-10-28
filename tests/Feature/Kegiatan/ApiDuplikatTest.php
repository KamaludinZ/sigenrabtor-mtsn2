<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Support\Pengaturan\KontrolTahunAktif;
use Tests\TestCase;

class ApiDuplikatTest extends TestCase
{
    private function tahunLalu(array $isi = []): int
    {
        return app(KegiatanRepository::class)->simpan($isi + ['nama_kegiatan' => 'Lomba Literasi Madrasah', 'leading' => '1.6', 'user_id' => 1, 'tahun_anggaran' => 2025,
            'kode_rktm' => '6.19.1', 'kode_sk' => 'SK.10', 'kode_iksk' => 'IKSK 11.5', 'kode_edm' => 'Z9', 'sumber_dana' => [['sumber' => 'DIPA', 'pagu' => 1_000_000]]]);
    }

    public function test_rencana_memvalidasi_kode_terhadap_master_tahun_aktif(): void
    {
        $id = $this->tahunLalu();
        $rencana = $this->postJson(route('kegiatan.duplikat.rencana'), ['kegiatan' => [$id, 1]])->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJson(['tahun' => 2026, 'dilewati' => []])
            ->assertJsonPath('gagal.0.id', 1)
            ->json();

        $siap = $rencana['siap'][0];
        $kode = array_column($siap['kode'], null, 'isian');
        $this->assertSame(['tetap', 'tetap', 'dikosongkan', 'dikosongkan', 'kosong'], array_column($siap['kode'], 'status'));
        $this->assertSame(['IKSK 11.5', null], [$kode['kode_iksk']['kode_sumber'], $kode['kode_iksk']['kode_draf']]);
        $this->assertContains('IKSK 11.5 bukan indikator SK.10 di master TA 2026 — pilih ulang.', array_column($siap['catatan'], 'teks'));
        $this->assertContains('Kode EDM Z9 tidak ada di master TA 2026 — pilih ulang.', array_column($siap['catatan'], 'teks'));
        $this->assertSame([], $siap['galat_kode'], 'Kode draf sah di master tahun aktif.');
        $this->assertSame([['sumber' => 'DIPA', 'pagu' => 1_000_000]], $siap['sumber_dana']);
        $this->assertSame(1, count(app(KegiatanRepository::class)->semua()) - $this->jumlahContohKegiatan(), 'Rencana tidak menyimpan apa pun.');
    }

    public function test_proses_duplikat_lalu_dilewati_bila_diulang(): void
    {
        $id = $this->tahunLalu();
        $hasil = $this->postJson(route('kegiatan.duplikat.proses'), ['kegiatan' => [$id]])->assertCreated()
            ->assertJson(['dilewati' => [], 'gagal' => [], 'pesan' => '1 kegiatan diduplikat menjadi draf TA 2026.'])
            ->json('dibuat.0');
        $this->assertSame([$id, route('kegiatan.edit', ['kegiatan' => $hasil['hasil_id']])], [$hasil['id'], $hasil['tautan']]);
        $draf = app(KegiatanRepository::class)->temukan($hasil['hasil_id']);
        $this->assertSame(['SK.10', null, null], [$draf['kode_sk'], $draf['kode_iksk'], $draf['kode_edm']]);

        $this->postJson(route('kegiatan.duplikat.proses'), ['kegiatan' => [$id]])->assertOk()
            ->assertJson(['dibuat' => [], 'pesan' => '1 dilewati (sudah ada).'])->assertJsonPath('dilewati.0.id', $id);
        $this->postJson(route('kegiatan.duplikat.proses'), ['kegiatan' => []])->assertStatus(422)->assertJsonPath('galat.kegiatan.0', 'Pilih minimal satu kegiatan tahun lalu.');
        $this->postJson(route('kegiatan.duplikat.rencana'), [])->assertStatus(422);

        KontrolTahunAktif::aktifkan(2025, ['name' => 'Koordinator PUSKOM']);
        $this->postJson(route('kegiatan.duplikat.proses'), ['kegiatan' => [$id]])->assertForbidden()->assertJsonPath('pesan', 'Tahun Anggaran 2025 terkunci — kegiatan tahun itu hanya bisa dilihat.');
    }
}
