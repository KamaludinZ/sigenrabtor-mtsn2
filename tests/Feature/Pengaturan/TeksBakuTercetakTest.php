<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\DasarHukumRepository;
use App\Contracts\KegiatanRepository;
use App\Support\Dokumen\NilaiVariabel;
use App\Support\Dokumen\SusunanKak;
use App\Support\Kegiatan\KalimatKak;
use App\Support\Pengaturan\TeksBaku;
use App\Support\Pengaturan\TeksBakuTercetak;
use App\Support\Stub\StubData;
use Tests\TestCase;

class TeksBakuTercetakTest extends TestCase
{
    public function test_substitusi_cetak_satu_lintasan(): void
    {
        $this->assertSame(
            'TA 2026 — Lomba {tahun} Sains untuk …; … dan … {tanpa tutup',
            TeksBaku::cetak('TA {tahun} — {nama_kegiatan} untuk {uraian_iksk}; {asing} dan {} {tanpa tutup', 2026, [TeksBaku::NAMA_KEGIATAN => 'Lomba {tahun} Sains', '{uraian_iksk}' => null]),
            'Nilai tidak dipindai ulang; nilai kosong & penanda asing tercetak "…".',
        );
        $this->assertSame('Tanpa penanda.', TeksBaku::cetak('Tanpa penanda.', 2026));
        $this->assertSame('Nomor 001/SK tanggal …', TeksBaku::cetak('Nomor {no_sk_rktm} tanggal {tanggal_sk_rktm}', 2026, ['{no_sk_rktm}' => '001/SK', '{tanggal_sk_rktm}' => null]));

        $nilai = TeksBaku::nilaiKegiatan(['nama_kegiatan' => "  Lomba\n  Sains  ", 'leading' => '1.6']);
        $this->assertSame(['Lomba Sains', 'Tim Penjamin Mutu', null], [$nilai['{nama_kegiatan}'], $nilai['{leading}'], $nilai['{uraian_iksk}']], 'Satu baris berspasi tunggal.');
    }

    public function test_teks_baku_kegiatan_sama_dengan_dokumen(): void
    {
        $kegiatan = app(KegiatanRepository::class)->temukan(1);
        $teks = TeksBakuTercetak::dari($kegiatan, 2026);

        $this->assertSame(StubData::latarBelakangBaku(2026), $teks->latarBelakang);
        $this->assertSame(KalimatKak::latarBelakangKegiatan($kegiatan, 2026), $teks->kalimatPenutup);
        $this->assertSame(StubData::dasarHukum(2026), $teks->dasarHukum);
        $this->assertSame([[], false, []], [$teks->kosong, $teks->skKurang, $teks->kurang()]);
        $this->assertSame($teks->latarLengkap(), NilaiVariabel::untuk($kegiatan, 'Komite')['latar_belakang']);

        $tanpaNama = TeksBakuTercetak::dari(['leading' => '1.6'], 2026);
        $this->assertSame([null, []], [$tanpaNama->kalimatPenutup, $tanpaNama->kosong]);
        $this->assertSame($tanpaNama->latarBelakang, $tanpaNama->latarLengkap());
    }

    public function test_penanda_asing_tersimpan_tidak_tercetak_dan_dicatat(): void
    {
        app(DasarHukumRepository::class)->simpan(2026, [
            'dasar_hukum' => [['id' => 'a', 'teks' => 'Undang-Undang Nomor {nomor}'], ['id' => 'b', 'teks' => 'RKTM Tahun {tahun}']],
            'latar_belakang' => 'Paragraf {visi} TA {tahun}.',
            'kalimat_penutup' => 'Kegiatan {nama_kegiatan} untuk {uraian_iksk} {kepala}.',
        ]);
        $kegiatan = ['nama_kegiatan' => 'Rapat Kerja', 'leading' => '1.6'];
        $teks = TeksBakuTercetak::dari($kegiatan, 2026);

        $this->assertSame(['Paragraf … TA 2026.', 'Kegiatan Rapat Kerja untuk … ….', ['Undang-Undang Nomor …;', 'RKTM Tahun 2026.']], [$teks->latarBelakang, $teks->kalimatPenutup, $teks->dasarHukum]);
        $this->assertSame([
            1 => [
                'indikator (iksk) (kalimat penutup latar belakang)',
                'penanda {visi} tidak dikenal di paragraf latar belakang baku (Pengaturan › Dasar hukum)',
                'penanda {kepala} tidak dikenal di kalimat penutup latar belakang (Pengaturan › Dasar hukum)',
            ],
            2 => ['penanda {nomor} tidak dikenal di dasar hukum butir 1 (Pengaturan › Dasar hukum)'],
        ], $teks->kurang());

        $susunan = SusunanKak::dari($kegiatan + ['id' => null]);
        $this->assertSame($teks->kurang()[1], array_values(array_intersect($susunan->kurangPerBagian[1], $teks->kurang()[1])));
        $this->assertSame($teks->kurang()[2], $susunan->kurangPerBagian[2]);
        $bagian1 = collect($susunan->bagian)->firstWhere('nomor', 1)['isi'];
        $this->assertSame(['Paragraf … TA 2026.', 'Kegiatan Rapat Kerja untuk … ….'], array_column($bagian1, 'teks'));
        $this->assertSame('Paragraf … TA 2026. Kegiatan Rapat Kerja untuk … ….', NilaiVariabel::untuk($kegiatan, null)['latar_belakang']);
    }
}
