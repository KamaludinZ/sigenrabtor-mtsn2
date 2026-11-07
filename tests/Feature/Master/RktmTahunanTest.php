<?php

namespace Tests\Feature\Master;

use App\Contracts\KegiatanRepository;
use App\Contracts\MasterKodeRepository;
use App\Contracts\TahunAnggaranRepository;
use App\Livewire\Kegiatan\Klasifikasi;
use App\Support\Kegiatan\Duplikat\DuplikatKegiatan;
use App\Support\Kegiatan\KodeHilang;
use App\Support\Master\Impor\BerkasMaster;
use App\Support\Master\Impor\PratinjauMaster;
use App\Support\Master\Impor\ProsesBerkasMaster;
use App\Support\Master\Impor\SimpanMasterKode;
use App\Support\Master\KelolaRencanaAksi;
use App\Support\Master\PetaRktm;
use App\Support\Master\TemplateMasterKode;
use App\Support\MasterKode;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/** Kode RKTM berubah tiap tahun: daftar baru diterima apa adanya dan kode yang dinomori ulang dikenali lewat namanya. */
class RktmTahunanTest extends TestCase
{
    private const ADMIN = ['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin', 'leadings' => []];

    private function csv(string $isi): string
    {
        $path = tempnam(sys_get_temp_dir(), 'rktm');
        file_put_contents($path, $isi);

        return $path;
    }

    /** CSV RKTM master TA 2026 dengan kode $lama dinomori ulang menjadi $baru (isi lain sama). */
    private function csvNomorUlang(string $lama, string $baru): string
    {
        $baris = ['Kode;SNP;Kegiatan;Sub Kegiatan'];
        foreach (MasterKode::kodeRktm(2026) as $r) {
            $baris[] = implode(';', [$r['kode'] === $lama ? $baru : $r['kode'], $r['snp'], $r['kegiatan'], $r['sub_kegiatan']]);
        }

        return $this->csv(implode("\n", $baris)."\n");
    }

    public function test_daftar_rktm_per_leading_tanpa_snp_dan_kegiatan_diterima_dan_digabung_dengan_sheet_lain(): void
    {
        $buku = new Spreadsheet;
        $utama = $buku->getActiveSheet()->setTitle('SNP');
        $utama->fromArray([['Kode', 'SNP', 'Kegiatan', 'Sub Kegiatan'], ['1.1.1', 'STANDAR ISI', 'Kurikulum', 'Program tahunan'], ['6.1.1', 'STANDAR PENGELOLAAN', 'Manajemen', 'Rapat kerja']]);
        $per = $buku->createSheet()->setTitle('8SNP');
        $per->fromArray([
            ['C. RENCANA KERJA TAHUNAN MADRASAH (RKTM) PER-LEADING'],
            ['No', 'kode sub kegiatan', 'Penanggung Jawab', 'Program/Kegiatan'],
            [1, 'STANDAR ISI'],
            [1, '1.1.1', 'Waka Kurikulum', 'Program tahunan'],
            [2, '1.1.2', 'Waka Kurikulum', 'Modul ajar'],
            [2, 'STANDAR PENGELOLAAN'],
            [1, '6.1.2', 'Ka. TU', 'Penyusunan profil'],
            ['No', 'kode sub kegiatan', 'Penanggung Jawab', 'Program/Kegiatan'],
            ['KEGIATAN OPERASIONAL RUTIN'],
            [1, '6.9.1', 'Ka. TU', 'Gaji GTT'],
            [60, '#REF!', null, '#REF!'],
        ]);
        foreach ([[4, '1.1.1'], [5, '1.1.2'], [7, '6.1.2'], [10, '6.9.1']] as [$b, $kode]) {
            $per->setCellValueExplicit("B{$b}", $kode, 's');
        }
        foreach ([[2, '1.1.1'], [3, '6.1.1']] as [$b, $kode]) {
            $utama->setCellValueExplicit("A{$b}", $kode, 's');
        }
        $path = tempnam(sys_get_temp_dir(), 'snp').'.xlsx';
        (new Xlsx($buku))->save($path);

        $p = PratinjauMaster::susun($path, 'rktm.xlsx', ['rktm' => 'SNP'], 2027)['rktm'];
        $baris = array_column($p['baris'], null, 'kode');

        $this->assertNull($p['galat']);
        $this->assertSame(['1.1.1', '6.1.1', '1.1.2', '6.1.2', '6.9.1'], array_keys($baris), 'Kode dari 8SNP yang belum ada di sheet utama ditambahkan; judul berulang & #REF! dilewati.');
        $this->assertContains('3 kode RKTM dilengkapi dari sheet 8SNP (tidak ada di sheet SNP).', $p['peringatan']);
        $this->assertSame(['sub_kegiatan' => 'Modul ajar', 'kegiatan' => 'Kurikulum', 'snp' => 'STANDAR ISI'], $baris['1.1.2']['tampil'], 'Kegiatan dari kode n.n sama di berkas.');
        $this->assertSame('STANDAR PENGELOLAAN', $baris['6.9.1']['tampil']['snp'], 'SNP mengikuti standar 6 walau di blok "operasional rutin".');
        $this->assertSame('Kegiatan 6.9', $baris['6.9.1']['tampil']['kegiatan']);
        $this->assertContains('Nama kegiatan 6.9 belum ada di berkas maupun master — tercetak "Kegiatan 6.9"; isi kolom Kegiatan bila perlu.', $baris['6.9.1']['pesan']);
        $this->assertNotSame('gagal', $baris['6.9.1']['status']);
        $this->assertSame(0, $p['ringkasan']['gagal']);
    }

    public function test_peta_rktm_mengenali_kode_yang_dinomori_ulang(): void
    {
        $this->assertSame('pengadaan map ijasah', PetaRktm::kunci('  Pengadaan Map Ijasah [1 KEG x 300 Siswa].'));
        $banding = PetaRktm::banding(
            [['kode' => '1.1.1', 'sub_kegiatan' => 'Prota'], ['kode' => '1.1.2', 'sub_kegiatan' => 'Modul'], ['kode' => '2.1.1', 'sub_kegiatan' => 'Lama'], ['kode' => '3.1.1', 'sub_kegiatan' => 'Nama A']],
            [['kode' => '1.1.1', 'sub_kegiatan' => 'prota'], ['kode' => '1.1.5', 'sub_kegiatan' => 'Modul'], ['kode' => '4.1.1', 'sub_kegiatan' => 'Baru'], ['kode' => '3.1.1', 'sub_kegiatan' => 'Nama B']],
        );
        $this->assertSame(['tetap' => 1, 'pindah' => [['lama' => '1.1.2', 'baru' => '1.1.5', 'nama' => 'Modul']], 'ganti_nama' => ['3.1.1'], 'baru' => ['4.1.1'], 'hilang' => ['2.1.1']], $banding);

        $r = MasterKode::kodeRktm(2026)[0];
        $this->assertNull(PetaRktm::pengganti($r['kode'], 2026), 'Kode masih ada.');
        app(MasterKodeRepository::class)->ganti(2026, ['kode_rktm' => [['kode' => '9.9.9'] + $r]]);
        $this->assertSame('9.9.9', PetaRktm::pengganti($r['kode'], 2026, 2025, $r['sub_kegiatan']));
        $this->assertSame('9.9.9', PetaRktm::pengganti('5.5.5', 2026, nama: $r['sub_kegiatan']));
        $this->assertNull(PetaRktm::pengganti('5.5.5', 2026, nama: 'Tidak ada'));
    }

    public function test_pratinjau_menandai_kode_berganti_nomor_dan_membandingkan_dengan_tahun_sebelumnya(): void
    {
        $lama = MasterKode::kodeRktm(2026)[0]['kode'];
        $p = ProsesBerkasMaster::proses($this->csvNomorUlang($lama, '9.99.1'), 'rktm.csv', 'rktm', 2026, self::ADMIN)['pratinjau']['rktm'];
        $baris = array_column($p['baris'], null, 'kode');

        $this->assertSame('hilang', $baris[$lama]['status']);
        $this->assertStringStartsWith('Kemungkinan berganti kode menjadi 9.99.1 (nama sub kegiatan sama)', end($baris[$lama]['pesan']));
        $this->assertSame(['baru', ['Sebelumnya kode '.$lama.'.']], [$baris['9.99.1']['status'], $baris['9.99.1']['pesan']]);

        app(TahunAnggaranRepository::class)->tambah(2027);
        $ringkasan = SimpanMasterKode::ringkasanJenis('rktm', ProsesBerkasMaster::proses($this->csvNomorUlang($lama, '9.99.1'), 'rktm.csv', 'rktm', 2027, self::ADMIN)['pratinjau']['rktm']['baris'], 2027);
        $this->assertContains('Dibanding TA 2026: '.(count(MasterKode::kodeRktm(2026)) - 1)." tetap, 1 berganti kode ({$lama} → 9.99.1), 0 berganti nama, 0 baru, 0 tidak ada lagi", $ringkasan['butir']);
    }

    public function test_kegiatan_yang_kodenya_dinomori_ulang_mendapat_saran_pengganti(): void
    {
        session(['stub_role' => 'admin']);
        $kegiatan = app(KegiatanRepository::class)->temukan(1);
        $lama = $kegiatan['kode_rktm'];
        // Master TA 2026 diunggah ulang dengan kode itu dinomori ulang: pergantiannya tercatat di jejak unggahan.
        SimpanMasterKode::simpan($this->csvNomorUlang($lama, '9.99.1'), 'rktm.csv', 'rktm', 2026, self::ADMIN);
        $this->assertSame([['lama' => $lama, 'baru' => '9.99.1']], app(MasterKodeRepository::class)->unggahan(2026)[0]['ringkasan']['rktm']['pindah']);

        $hilang = collect(KodeHilang::dari($kegiatan))->firstWhere('kolom', 'kode_rktm');
        $this->assertSame([$lama, '9.99.1'], [$hilang['kode'], $hilang['pengganti']]);

        Livewire::test(Klasifikasi::class, ['kegiatan' => 1])
            ->assertSeeHtml('data-testid="pakai-pengganti-kode_rktm"')
            ->call('pakaiPengganti', 'kode_rktm', '9.99.1')
            ->assertSet('form.kode_rktm', '9.99.1')
            ->assertSet('adaPerubahan', true);
    }

    public function test_duplikat_tahun_lalu_mengikuti_kode_rktm_yang_dinomori_ulang(): void
    {
        $r = MasterKode::kodeRktm(2025)[0];
        app(MasterKodeRepository::class)->ganti(2026, ['kode_rktm' => [['kode' => '9.1.1'] + $r]]);

        $rencana = DuplikatKegiatan::rencana(['id' => 50, 'tahun_anggaran' => 2025, 'nama_kegiatan' => 'Kegiatan lama', 'kode_rktm' => $r['kode'], 'leading' => '1.6', 'sumber_dana' => []]);

        $this->assertSame('9.1.1', $rencana['data']['kode_rktm']);
        $this->assertArrayNotHasKey('kode_rktm', $rencana['data']['kode_hilang']);
        $this->assertContains("Kode RKTM {$r['kode']} TA 2025 menjadi 9.1.1 di TA 2026 (nama sub kegiatan sama).", array_column($rencana['catatan'], 'teks'));
    }

    public function test_rencana_aksi_tag_rktm_disesuaikan_dan_leading_per_unit_diingat(): void
    {
        $rktm = MasterKode::kodeRktm(2026);
        $ra = fn (string $kode, string $uraian, array $isi = []) => $isi + ['kode' => $kode, 'uraian' => $uraian, 'kode_sk' => null, 'kode_iksk' => null, 'kode_rktm' => null,
            'unit' => 'Tim Manajemen Mutu', 'kode_leading' => null, 'pagu_dipa' => 0, 'pagu_komite' => 0, 'bulan' => [], 'kode_edm' => null, 'budaya_mutu' => []];
        app(MasterKodeRepository::class)->ganti(2026, ['rencana_aksi' => [
            $ra('RA.1', 'Workshop', ['kode_rktm' => '8.88.8']),
            $ra('RA.2', $rktm[1]['sub_kegiatan'], ['kode_rktm' => '8.88.9']),
            $ra('RA.3', 'Lain', ['unit' => 'Waka Humas', 'kode_leading' => '1.4']),
        ]]);
        app(MasterKodeRepository::class)->ganti(2025, ['kode_rktm' => [['kode' => '8.88.8', 'snp' => 'X', 'kegiatan' => 'Y', 'sub_kegiatan' => $rktm[0]['sub_kegiatan']]]]);

        $hasil = KelolaRencanaAksi::sesuaikanRktm(2026, self::ADMIN);
        $this->assertSame([['kode' => 'RA.1', 'lama' => '8.88.8', 'baru' => $rktm[0]['kode']], ['kode' => 'RA.2', 'lama' => '8.88.9', 'baru' => $rktm[1]['kode']]], $hasil['diubah']);
        $this->assertSame(0, $hasil['tersisa']);

        $this->assertSame(2, KelolaRencanaAksi::terapkanLeadingUnit(2026, 'tim manajemen MUTU', '1.6', self::ADMIN));
        $this->assertSame(['1.6', '1.6', '1.4'], array_column(MasterKode::rencanaAksi(2026), 'kode_leading'));

        $p = ProsesBerkasMaster::proses($this->csv("Rencana Aksi;Unit/Tim Penerima Mandat\nWorkshop;Tim Manajemen Mutu\nRencana baru;Tim Manajemen Mutu\n"), 'ra.csv', 'rencana_aksi', 2026, self::ADMIN)['pratinjau']['rencana_aksi'];
        $baru = collect($p['baris'])->firstWhere('tampil.uraian', 'Rencana baru');
        $this->assertSame('1.6', $baru['tampil']['leading']);
        $this->assertContains('Leading 1.6 mengikuti rencana aksi lain ber-unit "Tim Manajemen Mutu".', $baru['pesan']);
    }

    public function test_template_berisi_master_tahun_sebelumnya_sebagai_titik_awal(): void
    {
        app(TahunAnggaranRepository::class)->tambah(2027);
        $buku = TemplateMasterKode::buat(2027, BerkasMaster::SEMUA, true, 2026);

        $this->assertSame(count(MasterKode::kodeRktm(2026)) + 1, $buku->getSheetByName('SNP')->getHighestDataRow());
        $this->assertStringContainsString('Berisi master TA 2026 sebagai titik awal', (string) $buku->getSheetByName('Petunjuk')->getCell('A2')->getValue());
        $this->assertSame('Master_Kode_TA2027_dari_TA2026.xlsx', TemplateMasterKode::namaBerkas(2027, BerkasMaster::SEMUA, true, 2026));

        $this->withSession(['stub_role' => 'admin'])->get(route('master.template', ['ta' => 2027, 'isi' => 1, 'dari' => 2026]))->assertOk()->assertDownload('Master_Kode_TA2027_dari_TA2026.xlsx');
        $this->withSession(['stub_role' => 'admin'])->get(route('master.template', ['ta' => 2027, 'isi' => 1, 'dari' => 1999]))->assertNotFound();
        $this->withSession(['stub_role' => 'admin'])->get(route('master.index', ['ta' => 2027]))->assertSeeHtml('data-testid="template-dari-sebelumnya"');
    }
}
