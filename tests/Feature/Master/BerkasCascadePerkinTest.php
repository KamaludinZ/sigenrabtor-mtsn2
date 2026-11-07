<?php

namespace Tests\Feature\Master;

use App\Support\Master\Impor\BerkasMaster;
use App\Support\Master\Impor\PratinjauMaster;
use App\Support\Master\Impor\ProsesBerkasMaster;
use App\Support\Master\Impor\SimpanMasterKode;
use App\Support\Master\KelolaRencanaAksi;
use App\Support\Master\RencanaAksi;
use App\Support\Master\TemplateMasterKode;
use App\Support\MasterKode;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Berkas master asli madrasah (cascade Perjanjian Kinerja): sheet Perkin bergaya Kemenag (judul KEGIATAN/SASARAN/
 * Indikator Kinerja, kode sub kegiatan tanpa judul, judul bulan berumus, baris BIDANG) dan sheet 5BM_SNP (EDM ↔ RKTM).
 */
class BerkasCascadePerkinTest extends TestCase
{
    private const ADMIN = ['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin', 'leadings' => []];

    private function berkas(): string
    {
        $buku = new Spreadsheet;
        $snp = $buku->getActiveSheet()->setTitle('5BM_SNP');
        $snp->fromArray(['EVALUASI DIRI MADRASAH'], null, 'A1');
        foreach (['A4' => 'kode', 'B4' => 'kode_ref_', 'C4' => 'Kategori', 'D4' => 'No. Urut', 'E4' => 'Pertanyaan Per Indikator', 'N4' => 'Kegiatan Prioritas yang direkomendasikan',
            'S4' => 'Kode', 'T4' => 'SNP ', 'U4' => 'Kegiatan ', 'V4' => 'Sub Kegiatan', 'N5' => 'Kode', 'O5' => 'SNP', 'P5' => 'Kegiatan', 'Q5' => 'Sub Kegiatan'] as $sel => $isi) {
            $snp->setCellValue($sel, $isi);
        }
        $baris = [
            7 => ['A' => 1, 'B' => 'A', 'C' => 'A. Kedisiplinan', 'D' => 'A1', 'E' => 'Siswa religius', 'S' => '2.3.1', 'T' => 'PENGEMBANGAN STANDAR PROSES', 'U' => 'Pengelolaan Program Kesiswaan', 'V' => 'Pembiasaan Shalat'],
            8 => ['S' => '2.3.2', 'T' => 'PENGEMBANGAN STANDAR PROSES', 'U' => 'Pengelolaan Program Kesiswaan', 'V' => 'Penanganan disiplin'],
            9 => ['T' => 0, 'U' => 0, 'V' => 0],
            10 => ['A' => 2, 'B' => 'A', 'D' => 'A2', 'E' => 'Guru hadir', 'S' => '1.1.1', 'T' => 'PENGEMBANGAN STANDAR ISI', 'U' => 'Pengembangan Kurikulum', 'V' => 'Penyusunan Prota'],
            11 => ['A' => 3, 'B' => 'B', 'C' => 'B. Pengembangan Diri', 'D' => 'B1', 'E' => 'Ekstrakurikuler', 'S' => '2.8.12', 'T' => 'PENGEMBANGAN STANDAR PROSES', 'U' => 'Pendidikan Karakter', 'V' => 'Pramuka'],
            12 => ['S' => 'n', 'T' => 'n', 'U' => 'n', 'V' => 'n'],
        ];
        foreach ($baris as $b => $isi) {
            foreach ($isi as $k => $v) {
                $snp->setCellValueExplicit("{$k}{$b}", (string) $v, is_int($v) ? 'n' : 's');
            }
        }

        $perkin = $buku->createSheet()->setTitle('Perkin');
        foreach (['A1' => 'CASCADE / MATRIK PEMBAGIAN PERAN DAN HASIL', 'A5' => 'KEGIATAN', 'B5' => 'SASARAN/INDIKATOR', 'C5' => 'Indikator Kinerja', 'I5' => 'Unit/Tim Penerima Mandat',
            'Q5' => 'Unit/Tim Penerima Mandat', 'R5' => 'Rencana Aksi', 'S5' => "Anggaran Kegiatan DIPA\n(Rp)", 'T5' => "Anggaran Kegiatan KOMITE\n(Rp)", 'U5' => "Jumlah\nBiaya", 'V5' => 'Time Line (Bulan Pelaksanaan Kegiatan)',
            'V6' => 1, 'A7' => 1, 'B7' => 2, 'C7' => 3, 'A8' => 'BIDANG  PENDIDIKAN DASAR MENENGAH'] as $sel => $isi) {
            $perkin->setCellValue($sel, $isi);
        }
        foreach (['W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG'] as $i => $kolom) {
            $sebelum = $i === 0 ? 'V' : ['W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD', 'AE', 'AF'][$i - 1];
            $perkin->setCellValue("{$kolom}6", "={$sebelum}6+1");
        }
        $data = [
            9 => ['A' => 'SK.1.2133', 'B' => "Meningkatnya pemerataan guru\npada madrasah", 'C' => 'IKSK.1.2133.1', 'D' => 'Rasio guru', 'I' => 1.5, 'J' => 'Ka. TU',
                'P' => '1.1.1', 'Q' => 'Waka Kurikulum', 'R' => 'Penyusunan Program Tahunan', 'AA' => 'X', 'AG' => 'X'],
            10 => ['P' => '2.3.1', 'Q' => 'Tim Manajemen Mutu', 'R' => 'Pembiasaan Shalat Berjamaah', 'S' => 12450000, 'V' => 'X', 'W' => '-'],
            11 => ['A' => 'SK.1.2133', 'B' => 'Meningkatnya pemerataan guru pada madrasah', 'C' => 'IKSK.1.2133.2', 'D' => 'Persentase tendik', 'Q' => 'Ka. TU', 'R' => 'Tendik memenuhi kualifikasi', 'AG' => 'X'],
            12 => ['A' => 'BIDANG TATA KELOLA'],
            13 => ['A' => 'SK 6708.1', 'B' => 'Meningkatnya tata kelola', 'C' => 'IKSK 6708.1.4', 'D' => 'Indeks tata kelola', 'P' => '2.8.12', 'Q' => "Ma'had Nawaina", 'R' => 'Pembuatan poster', 'T' => 6000000, 'X' => 'X'],
            14 => ['A' => 'SK.2129.2', 'B' => 'Sarpras berkualitas', 'C' => 'IKSK.2129.5.6', 'D' => 'Layanan kesehatan', 'Q' => 'Waka Sarpras', 'R' => 'Perawatan UKS', 'T' => 'Rp1.500.000'],
        ];
        foreach ($data as $b => $isi) {
            foreach ($isi as $k => $v) {
                is_string($v) ? $perkin->setCellValueExplicit("{$k}{$b}", $v, 's') : $perkin->setCellValue("{$k}{$b}", $v);
            }
        }
        $buku->createSheet()->setTitle('8SNP')->setCellValue('A5', 'No');

        $path = tempnam(sys_get_temp_dir(), 'perkin').'.xlsx';
        (new Xlsx($buku))->save($path);

        return $path;
    }

    public function test_sheet_berkas_asli_dikenali_dari_namanya(): void
    {
        $this->assertSame(
            ['sk' => 'Perkin', 'iksk' => 'Perkin', 'rktm' => '5BM_SNP', 'edm' => '5BM_SNP', 'budaya' => null, 'rencana_aksi' => 'Perkin'],
            BerkasMaster::kenali(['5BM_SNP', 'Perkin', '8SNP'], BerkasMaster::SEMUA),
        );
    }

    public function test_pratinjau_membaca_kode_kemenag_rencana_aksi_dan_relasi_edm(): void
    {
        $sheet = array_filter(BerkasMaster::kenali(['5BM_SNP', 'Perkin', '8SNP'], BerkasMaster::SEMUA));
        $p = PratinjauMaster::susun($this->berkas(), 'Breakdown.xlsx', $sheet, 2026, BerkasMaster::OPSIONAL_SEMUA);
        $baris = fn (string $jenis) => array_column(array_filter($p[$jenis]['baris'], fn (array $b) => $b['status'] !== 'hilang'), null, 'kode');

        foreach ($p as $jenis => $item) {
            $this->assertNull($item['galat'], $jenis);
            $this->assertSame(0, $item['ringkasan']['gagal'], $jenis);
        }
        $this->assertSame(['SK.1.2133', 'SK.6708.1', 'SK.2129.2'], array_keys($baris('sk')), 'Baris BIDANG dilewati; uraian berganti baris digabung.');
        $this->assertSame('Meningkatnya pemerataan guru pada madrasah', $baris('sk')['SK.1.2133']['tampil']['uraian']);
        $iksk = $baris('iksk');
        $this->assertSame(['IKSK.1.2133.1', 'IKSK.1.2133.2', 'IKSK.6708.1.4', 'IKSK.2129.5.6'], array_keys($iksk));
        $this->assertSame('SK.6708.1', $iksk['IKSK.6708.1.4']['tampil']['kode_sk']);
        $this->assertSame(['Nomor IKSK.2129.5.6 tidak sejalan dengan SK induk SK.2129.2 — periksa lagi.'], $iksk['IKSK.2129.5.6']['pesan']);

        $rktm = $baris('rktm');
        $this->assertSame(['2.3.1', '2.3.2', '1.1.1', '2.8.12'], array_keys($rktm), 'Baris rumus "0" & penanda "n" dilewati; kolom "kode" nomor indikator tidak dipakai.');
        $this->assertSame(['sub_kegiatan' => 'Penyusunan Prota', 'kegiatan' => 'Pengembangan Kurikulum', 'snp' => 'PENGEMBANGAN STANDAR ISI'], $rktm['1.1.1']['tampil']);

        $edm = $baris('edm');
        $this->assertSame(['A1', 'A2', 'B1'], array_keys($edm));
        $this->assertSame(['uraian' => 'Siswa religius', 'komponen' => 'Kedisiplinan', 'rktm' => '2.3.1, 2.3.2'], $edm['A1']['tampil']);
        $this->assertSame('Kedisiplinan', $edm['A2']['tampil']['komponen'], 'Komponen mengikuti baris di atasnya.');
        $this->assertSame(['uraian' => 'Ekstrakurikuler', 'komponen' => 'Pengembangan Diri', 'rktm' => '2.8.12'], $edm['B1']['tampil']);

        $ra = $baris('rencana_aksi');
        $this->assertSame(['RA.1', 'RA.2', 'RA.3', 'RA.4', 'RA.5'], array_keys($ra));
        $this->assertSame([
            'uraian' => 'Penyusunan Program Tahunan', 'kode_sk' => 'SK.1.2133', 'kode_iksk' => 'IKSK.1.2133.1', 'kode_rktm' => '1.1.1',
            'unit' => 'Waka Kurikulum', 'leading' => '1.1', 'pagu_dipa' => '0', 'pagu_komite' => '0', 'bulan' => '6, 12',
        ], $ra['RA.1']['tampil']);
        $this->assertSame(['IKSK.1.2133.1', '', '12450000', '1'], [$ra['RA.2']['tampil']['kode_iksk'], $ra['RA.2']['tampil']['leading'], $ra['RA.2']['tampil']['pagu_dipa'], $ra['RA.2']['tampil']['bulan']], 'IKSK mengikuti baris di atasnya; "-" bukan tanda bulan.');
        $this->assertSame(['Unit "Tim Manajemen Mutu" belum cocok dengan leading — pilih leading-nya di tag Master Kode.'], $ra['RA.2']['pesan']);
        $this->assertSame(['1.5', 'Tanpa kode RKTM — tag RKTM & EDM diisi manual.'], [$ra['RA.3']['tampil']['leading'], $ra['RA.3']['pesan'][0]]);
        $this->assertSame(['SK.6708.1', 'IKSK.6708.1.4', '1.7', '6000000', '3'], [$ra['RA.4']['tampil']['kode_sk'], $ra['RA.4']['tampil']['kode_iksk'], $ra['RA.4']['tampil']['leading'], $ra['RA.4']['tampil']['pagu_komite'], $ra['RA.4']['tampil']['bulan']], "Ma'had Nawaina → Mahad.");
        $this->assertSame('1500000', $ra['RA.5']['tampil']['pagu_komite']);
    }

    public function test_simpan_berkas_asli_tag_edm_otomatis_dan_tag_manual_bertahan_saat_unggah_ulang(): void
    {
        $path = $this->berkas();
        $hasil = SimpanMasterKode::simpan($path, 'Breakdown.xlsx', BerkasMaster::SEMUA, 2026, self::ADMIN);
        $this->assertTrue($hasil['disimpan']);
        $this->assertSame(['sk', 'iksk', 'rktm', 'edm', 'rencana_aksi'], array_keys($hasil['pratinjau']), 'Tanpa sheet 5 Budaya Mutu: master budaya tetap.');
        $this->assertCount(5, MasterKode::budayaMutu(2026));
        $this->assertSame([['kode_edm' => 'A1', 'kode_rktm' => '2.3.1'], ['kode_edm' => 'A1', 'kode_rktm' => '2.3.2'], ['kode_edm' => 'A2', 'kode_rktm' => '1.1.1'], ['kode_edm' => 'B1', 'kode_rktm' => '2.8.12']], MasterKode::relasiEdmRktm(2026));

        $ra = array_column(RencanaAksi::baris(2026), null, 'kode');
        $this->assertCount(5, $ra);
        $this->assertSame([6, 12], $ra['RA.1']['bulan']);
        $this->assertSame(['A2'], $ra['RA.1']['tag']['kode_edm'], 'EDM otomatis dari relasi RKTM 1.1.1.');
        $this->assertTrue($ra['RA.1']['tag']['edm_otomatis']);
        $this->assertSame(['A1'], $ra['RA.2']['tag']['kode_edm']);
        $this->assertSame([], $ra['RA.3']['tag']['kode_edm']);
        $this->assertSame([], $ra['RA.1']['asing']);

        // Tag manual di Master Kode, lalu berkas yang sama diunggah ulang: semua tetap, tag manual tidak hilang.
        session(['stub_role' => 'admin']);
        KelolaRencanaAksi::ubahTag(2026, 'RA.2', ['kode_sk' => 'SK.1.2133', 'kode_iksk' => 'IKSK.1.2133.1', 'kode_rktm' => '2.3.1', 'kode_leading' => '1.6', 'kode_edm' => ['B1'], 'budaya_mutu' => ['1', '3']], self::ADMIN);
        $ulang = ProsesBerkasMaster::proses($path, 'Breakdown.xlsx', BerkasMaster::SEMUA, 2026, self::ADMIN);
        $this->assertSame(5, $ulang['pratinjau']['rencana_aksi']['ringkasan']['tetap']);
        SimpanMasterKode::simpan($path, 'Breakdown.xlsx', 'rencana_aksi', 2026, self::ADMIN);
        $ra2 = collect(MasterKode::rencanaAksi(2026))->firstWhere('kode', 'RA.2');
        $this->assertSame(['1.6', ['B1'], [1, 3]], [$ra2['kode_leading'], $ra2['kode_edm'], $ra2['budaya_mutu']]);

        // Template berisi master: diunggah ulang tanpa perubahan → semua "tetap" (termasuk relasi EDM & rencana aksi).
        $tpl = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';
        (new Xlsx(TemplateMasterKode::buat(2026, BerkasMaster::SEMUA, true)))->save($tpl);
        $balik = ProsesBerkasMaster::proses($tpl, 'Master_Kode_TA2026.xlsx', BerkasMaster::SEMUA, 2026, self::ADMIN);
        foreach ($balik['pratinjau'] as $jenis => $item) {
            $this->assertSame(count($item['baris']), $item['ringkasan']['tetap'], "Template {$jenis} kembali tetap.");
        }
        $this->assertSame([], $balik['kendala']);
    }

    public function test_unggah_edm_tanpa_kolom_rktm_mempertahankan_relasi(): void
    {
        SimpanMasterKode::simpan($this->berkas(), 'Breakdown.xlsx', BerkasMaster::SEMUA, 2026, self::ADMIN);
        $relasi = MasterKode::relasiEdmRktm(2026);

        $csv = tempnam(sys_get_temp_dir(), 'edm');
        file_put_contents($csv, "Kode;Uraian;Komponen\nA1;Siswa religius;Kedisiplinan\nA2;Guru hadir;Kedisiplinan\nB1;Ekstrakurikuler;Pengembangan Diri\n");
        $hasil = SimpanMasterKode::simpan($csv, 'edm.csv', 'edm', 2026, self::ADMIN);

        $this->assertSame(3, $hasil['pratinjau']['edm']['ringkasan']['tetap'], 'Tanpa kolom Kode RKTM, relasi tidak ikut dibandingkan.');
        $this->assertSame($relasi, MasterKode::relasiEdmRktm(2026));
    }
}
