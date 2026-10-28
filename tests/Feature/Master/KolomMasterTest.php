<?php

namespace Tests\Feature\Master;

use App\Support\Impor\LembarExcel;
use App\Support\Master\Impor\GagalPratinjauMaster;
use App\Support\Master\Impor\KolomMaster;
use App\Support\Master\Impor\PratinjauMaster;
use Tests\TestCase;

class KolomMasterTest extends TestCase
{
    /** Pratinjau satu jenis dari CSV. */
    private function pratinjau(string $jenis, string $isi): array
    {
        $path = tempnam(sys_get_temp_dir(), 'kolom');
        file_put_contents($path, $isi);

        return PratinjauMaster::susun($path, "{$jenis}.csv", [$jenis => 'CSV'], 2026)[$jenis];
    }

    public function test_kolom_wajib_yang_belum_ada_disebut(): void
    {
        $this->assertSame(
            'Kolom wajib "Uraian SK" tidak ditemukan di baris judul sheet Worksheet (baris 1) — judul yang dikenali Uraian SK: "uraian sk", "uraian sasaran kegiatan", "sasaran kegiatan".',
            $this->pratinjau('sk', "Kode SK;Keterangan\nSK.1;Sasaran\n")['galat'],
        );
        $this->assertSame(
            'Kolom wajib "Kegiatan" dan "Sub Kegiatan" tidak ditemukan di baris judul sheet Worksheet (baris 2) — judul yang dikenali Kegiatan: "kegiatan", "program"; Sub Kegiatan: "sub kegiatan", "subkegiatan", "uraian sub kegiatan".',
            $this->pratinjau('rktm', "RKTM MTsN 2 Kota Malang\nKode;SNP;Catatan\n1.1.1;Standar Isi;-\n")['galat'],
            'Baris judul terdekat = baris dengan kolom wajib terbanyak.',
        );
        $this->assertSame('Judul kolom "Kode IKSK", "Kode SK", dan "Uraian IKSK" tidak ditemukan di sheet Worksheet.', $this->pratinjau('iksk', "Nomor;Keterangan\n1;Isi\n")['galat'], 'Tanpa judul yang dikenali: semua kolom wajib.');
        $this->assertSame(['kode', 'uraian'], KolomMaster::wajib('edm'));
        $this->assertSame(['nama'], KolomMaster::wajib('budaya'));
    }

    public function test_batas_panjang_isian_dan_kolom_opsional(): void
    {
        $sk = $this->pratinjau('sk', "Kode SK;Uraian SK\nSK.90;".str_repeat('a', 1001)."\nSK.91;Sasaran pendek\n");
        $this->assertSame(['gagal', ['Uraian SK terlalu panjang (1001 karakter, maksimal 1000).']], [$sk['baris'][0]['status'], $sk['baris'][0]['pesan']]);
        $this->assertSame('baru', $sk['baris'][1]['status']);

        $budaya = $this->pratinjau('budaya', "Budaya Mutu\nJujur\n".str_repeat('b', 151)."\nPeduli\nSantun\nDisiplin\n");
        $this->assertSame(['Budaya Mutu terlalu panjang (151 karakter, maksimal 150).'], $budaya['baris'][1]['pesan']);
        $this->assertSame('Kolom "No" tidak ada — nomor mengikuti urutan baris.', $budaya['peringatan'][0]);

        $edm = $this->pratinjau('edm', "Kode;Uraian\nA1;Hadir tepat waktu\n");
        $this->assertSame(['Kolom "Komponen" tidak ada — nama komponen memakai master tahun itu.'], $edm['peringatan']);
        $this->assertSame([], $this->pratinjau('edm', "Kode;Uraian;Komponen\nA1;Hadir tepat waktu;Kedisiplinan\n")['peringatan']);
        $this->assertSame(['Komponen terlalu panjang (101 karakter, maksimal 100).'], KolomMaster::galatPanjang('edm', ['komponen' => str_repeat('k', 101), 'uraian' => 'Pendek']));

        $this->expectException(GagalPratinjauMaster::class);
        KolomMaster::judul(LembarExcel::buka(tap(tempnam(sys_get_temp_dir(), 'k'), fn ($p) => file_put_contents($p, "A;B\n1;2\n")), 'x.csv', 'CSV'), 'budaya');
    }
}
