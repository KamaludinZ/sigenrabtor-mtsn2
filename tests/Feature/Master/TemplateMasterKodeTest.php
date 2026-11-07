<?php

namespace Tests\Feature\Master;

use App\Support\Master\Impor\BerkasMaster;
use App\Support\Master\Impor\ProsesBerkasMaster;
use App\Support\Master\TemplateMasterKode;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class TemplateMasterKodeTest extends TestCase
{
    private const ADMIN = ['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin', 'leadings' => []];

    public function test_template_kosong_memuat_sheet_dan_judul_yang_dikenali_unggahan(): void
    {
        $buku = TemplateMasterKode::buat(2026);

        $this->assertSame(['Petunjuk', 'Perkin', 'SNP', 'EDM', '5 Budaya Mutu'], $buku->getSheetNames());
        $perkin = $buku->getSheetByName('Perkin')->rangeToArray('A1:U1')[0];
        $this->assertSame(['Kode SK', 'Uraian SK', 'Kode IKSK', 'Uraian IKSK', 'Kode RKTM', 'Unit/Tim Penerima Mandat', 'Rencana Aksi', 'Anggaran DIPA', 'Anggaran Komite', ...TemplateMasterKode::BULAN], $perkin);
        $this->assertSame(['Kode', 'Komponen', 'Uraian', 'Kode RKTM'], $buku->getSheetByName('EDM')->rangeToArray('A1:D1')[0]);
        $this->assertSame(1, $buku->getSheetByName('SNP')->getHighestDataRow(), 'Template kosong tanpa baris contoh.');
        $this->assertSame(['sk' => 'Perkin', 'iksk' => 'Perkin', 'rktm' => 'SNP', 'edm' => 'EDM', 'budaya' => '5 Budaya Mutu', 'rencana_aksi' => 'Perkin'], BerkasMaster::kenali($buku->getSheetNames(), BerkasMaster::SEMUA));
        $this->assertStringContainsString('IKSK.1.2133.1', implode(' ', array_column($buku->getSheetByName('Petunjuk')->toArray(), 0)));

        $this->assertSame(['Petunjuk', 'SNP'], TemplateMasterKode::buat(2026, 'rktm')->getSheetNames());
        $this->assertSame(['Petunjuk', 'Perkin'], TemplateMasterKode::buat(2026, 'rencana_aksi')->getSheetNames());
        $this->assertSame('Template_Master_Kode_TA2026.xlsx', TemplateMasterKode::namaBerkas(2026));
        $this->assertSame('Master_Rencana_Aksi_TA2026.xlsx', TemplateMasterKode::namaBerkas(2026, 'rencana_aksi', true));

        $this->expectException(InvalidArgumentException::class);
        TemplateMasterKode::buat(2026, 'asing');
    }

    public function test_template_berisi_master_tahun_itu_kembali_tetap_saat_diunggah(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';
        (new Xlsx(TemplateMasterKode::buat(2026, BerkasMaster::SEMUA, true)))->save($path);

        $kode = IOFactory::load($path)->getSheetByName('SNP')->getCell('A2');
        $this->assertSame('s', $kode->getDataType(), 'Kode RKTM ditulis sebagai teks.');

        $hasil = ProsesBerkasMaster::proses($path, 'Master_Kode_TA2026.xlsx', BerkasMaster::SEMUA, 2026, self::ADMIN);
        $this->assertSame(['sk', 'iksk', 'rktm', 'edm', 'budaya'], array_keys($hasil['pratinjau']), 'Belum ada rencana aksi: sheet Perkin tanpa rencana aksi tidak menambah jenis.');
        foreach ($hasil['pratinjau'] as $jenis => $item) {
            $this->assertSame(count($item['baris']), $item['ringkasan']['tetap'], $jenis);
        }
        $this->assertSame([], $hasil['kendala']);
    }

    public function test_endpoint_unduh_khusus_admin(): void
    {
        $this->withSession(['stub_role' => 'admin'])->get(route('master.template'))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertDownload('Template_Master_Kode_TA2026.xlsx');
        $this->withSession(['stub_role' => 'admin'])->get(route('master.template', ['ta' => 2025, 'isi' => 1, 'cakupan' => 'edm']))
            ->assertOk()->assertDownload('Master_EDM_TA2025.xlsx');
        $this->withSession(['stub_role' => 'admin'])->get(route('master.template', ['cakupan' => 'asing']))->assertNotFound();
        $this->withSession(['stub_role' => 'admin'])->get(route('master.template', ['ta' => 1999]))->assertNotFound();
        $this->withSession(['stub_role' => 'leading'])->get(route('master.template'))->assertForbidden();
    }

    public function test_halaman_master_menautkan_template_tanpa_label_segera(): void
    {
        $this->withSession(['stub_role' => 'admin'])->get(route('master.index'))
            ->assertOk()
            ->assertSee('data-testid="unduh-template-master"', false)
            ->assertSee(route('master.template', ['ta' => 2026]), false)
            ->assertSee(route('master.template', ['ta' => 2026, 'isi' => 1]))
            ->assertDontSee('Sedang disiapkan');
    }
}
