<?php

namespace Tests\Feature\RiwayatDokumen;

use App\Contracts\KegiatanRepository;
use App\Contracts\RabRepository;
use App\Contracts\RiwayatDokumenRepository;
use App\Support\Dokumen\IsiRiwayatDokumen;
use App\Support\Dokumen\RiwayatDokumen;
use Tests\TestCase;

class IsiRiwayatDokumenTest extends TestCase
{
    private const LEADING = ['id' => 1, 'name' => 'Muslimin, M.Pd.', 'role' => 'leading', 'leadings' => ['1.6']];

    public function test_cuplikan_isi_per_jenis(): void
    {
        $kegiatan = app(KegiatanRepository::class)->temukan(2);
        $set = IsiRiwayatDokumen::cuplikan($kegiatan, 'lengkap', 'DIPA', self::LEADING);
        $this->assertSame(['sidik', 'cover', 'bagian', 'rab'], array_keys($set));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $set['sidik']);
        $this->assertCount(9, $set['bagian']);
        $this->assertSame([1, 'Latar Belakang'], [$set['bagian'][0]['nomor'], $set['bagian'][0]['judul']]);
        $this->assertSame('TOTAL KESELURUHAN', $set['rab']['tabel']['label_total'] ?? 'TOTAL KESELURUHAN');
        $this->assertNotNull($set['cover']);

        $rab = IsiRiwayatDokumen::cuplikan($kegiatan, 'RAB', 'DIPA', self::LEADING);
        $this->assertSame([[], null], [$rab['bagian'], $rab['cover']]);
        $this->assertNotNull($rab['rab']);
        $kak = IsiRiwayatDokumen::cuplikan($kegiatan, 'KAK+TOR', 'DIPA', self::LEADING);
        $this->assertNull($kak['rab']);
        $this->assertCount(9, $kak['bagian']);
    }

    public function test_sumber_isi_terbaru_tersimpan_arsip_dan_tidak_ada(): void
    {
        $terbaru = IsiRiwayatDokumen::untuk('rd-2026-1', self::LEADING);
        $this->assertSame(['terbaru', 'Disusun dari data kegiatan terbaru', null, null], [$terbaru['sumber_isi'], $terbaru['label_sumber'], $terbaru['sama_dengan_terbaru'], $terbaru['berkas']]);
        $this->assertStringContainsString('tidak disimpan', $terbaru['catatan']);
        $this->assertNotNull($terbaru['isi']['rab']);
        $this->assertSame('rd-2026-1', $terbaru['dokumen']['id']);
        $this->assertArrayHasKey('izin', $terbaru['dokumen']);

        $beda = IsiRiwayatDokumen::untuk('rd-2026-6', self::LEADING);
        $this->assertSame(['total'], array_column($beda['perbedaan'], 'kunci'), 'Perbedaan dengan catatan ikut.');

        $tidakAda = IsiRiwayatDokumen::untuk('rd-2025-4', self::LEADING);
        $this->assertSame(['tidak_ada', null], [$tidakAda['sumber_isi'], $tidakAda['isi']]);
        $this->assertStringContainsString('tidak lagi tersimpan', $tidakAda['catatan']);

        $kegiatan = app(KegiatanRepository::class)->temukan(1);
        $isi = IsiRiwayatDokumen::cuplikan($kegiatan, 'lengkap', 'Komite', self::LEADING);
        $tersimpan = app(RiwayatDokumenRepository::class)->catat(RiwayatDokumen::entri($kegiatan, 'lengkap', 'Komite', 'pdf', 'cetak', 'SET.pdf', 1000, true, self::LEADING, isi: $isi))['id'];
        $hasil = IsiRiwayatDokumen::untuk($tersimpan, self::LEADING);
        $this->assertSame(['tersimpan', true, []], [$hasil['sumber_isi'], $hasil['sama_dengan_terbaru'], $hasil['perbedaan']]);
        $this->assertSame($isi, $hasil['isi']);

        $rincian = app(RabRepository::class)->rincian(1, 'Komite');
        app(RabRepository::class)->hapusItem(1, 'Komite', $rincian[0]['id']);
        $sesudah = IsiRiwayatDokumen::untuk($tersimpan, self::LEADING);
        $this->assertFalse($sesudah['sama_dengan_terbaru'], 'Isi tersimpan tetap; data kegiatan sudah berubah.');
        $this->assertSame($isi, $sesudah['isi']);

        $arsip = app(RiwayatDokumenRepository::class)->catat(RiwayatDokumen::entri($kegiatan, 'lengkap', 'Komite', 'pdf', 'arsip', 'ARSIP.pdf', 2048, false, self::LEADING))['id'];
        $hasilArsip = IsiRiwayatDokumen::untuk($arsip, self::LEADING);
        $this->assertSame('arsip', $hasilArsip['sumber_isi']);
        $this->assertSame(route('kegiatan.arsip', ['kegiatan' => 1, 'sumber' => 'Komite']), $hasilArsip['berkas']['tautan']);
        $this->assertNotNull($hasilArsip['isi'], 'Isi data terbaru sebagai rujukan.');
    }

    public function test_endpoint_isi_dokumen(): void
    {
        $this->getJson(route('dokumen.isi', ['dokumen' => 'rd-2026-1']))
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('sumber_isi', 'terbaru')
            ->assertJsonPath('dokumen.id', 'rd-2026-1')
            ->assertJsonStructure(['isi' => ['sidik', 'cover', 'bagian', 'rab'], 'perbedaan', 'catatan']);
        $this->getJson(route('dokumen.detail', ['dokumen' => 'rd-2026-1']))->assertOk()->assertJsonPath('dokumen.ada_isi', false)->assertJsonMissingPath('dokumen.isi');
        $this->getJson(route('dokumen.isi', ['dokumen' => 'rd-2025-1']))->assertForbidden();
        $this->getJson(route('dokumen.isi', ['dokumen' => 'rd-tidak-ada']))->assertNotFound();
    }
}
