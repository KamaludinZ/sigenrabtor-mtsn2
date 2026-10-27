<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Livewire\Kegiatan\ImporPerkin;
use App\Livewire\Kegiatan\SalinImpor;
use App\Support\Impor\GagalBacaImpor;
use App\Support\Kegiatan\Impor\PembacaPerkin;
use App\Support\Kegiatan\Impor\PemeriksaPerkin;
use App\Support\MasterKode;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ImporPerkinTest extends TestCase
{
    private const CONTOH = 'tests/fixtures/perkin-cascade-contoh.xlsx';

    /** @param  array<string, list<list<mixed>>>  $lembar  Nama sheet → baris. */
    private function berkas(array $lembar): string
    {
        $buku = new Spreadsheet;
        $buku->removeSheetByIndex(0);
        foreach ($lembar as $nama => $baris) {
            $buku->createSheet()->setTitle($nama)->fromArray($baris);
        }
        $path = tempnam(sys_get_temp_dir(), 'perkin').'.xlsx';
        (new Xlsx($buku))->save($path);

        return $path;
    }

    private function galatBaca(string $path): string
    {
        try {
            PembacaPerkin::baca($path, 'perkin.xlsx');
        } catch (GagalBacaImpor $e) {
            return $e->getMessage();
        } finally {
            @unlink($path);
        }

        $this->fail('Berkas seharusnya ditolak.');
    }

    public function test_pembaca_memilih_sheet_cascade_dan_mengisi_kode_yang_digabung(): void
    {
        $bacaan = PembacaPerkin::baca(base_path(self::CONTOH), 'MASTER 2026.xlsx');

        // Sheet "Perkin" master kode (tanpa kegiatan) dilewati; judul kolom di baris 3.
        $this->assertSame('Cascade Perkin', $bacaan['lembar']);
        $this->assertSame(['kode_sk', 'kode_iksk', 'nama_kegiatan', 'leading', 'pagu_dipa', 'pagu_komite', 'bulan'], $bacaan['kolom']);
        // Baris 8 hanya membawa SK.8 (tanpa kegiatan) sehingga tidak dihitung.
        $this->assertSame([4, 5, 6, 7, 9, 10, 11, 12, 13], array_column($bacaan['baris'], 'baris'));

        $kode = array_map(fn (array $b) => [$b['nilai']['kode_sk'], $b['nilai']['kode_iksk']], $bacaan['baris']);
        $this->assertSame(['SK.9', 'IKSK 9.1'], $kode[1], 'Sel SK & IKSK yang digabung berlaku ke bawah.');
        $this->assertSame(['SK.9', 'IKSK 9.2'], $kode[2]);
        $this->assertSame(['SK.8', 'IKSK 9.3'], $kode[4], 'SK baru dari baris judul kelompok.');
        $this->assertSame(['SK.10', 'IKSK 10.3'], $kode[7]);
    }

    public function test_pembaca_menjelaskan_berkas_yang_bukan_cascade(): void
    {
        $this->assertStringContainsString('sheet Perkin master kode hanya berisi SK & IKSK', $this->galatBaca($this->berkas(['Perkin' => [['Kode SK', 'Uraian SK'], ['SK.9', 'Sarana']]])));
        $this->assertStringContainsString('tidak ada kolom "Kode SK" atau "Kode IKSK"', $this->galatBaca($this->berkas(['Data' => [['Nama Kegiatan', 'Leading'], ['Rapat', '1.6']]])));
        $this->assertSame(
            'Sheet cascade Perkin tidak ditemukan. Sheet di berkas ini: Satu, Dua. Pastikan ada sheet berjudul kolom "Kode SK", "Kode IKSK", dan "Nama Kegiatan".',
            $this->galatBaca($this->berkas(['Satu' => [['A', 'B']], 'Dua' => [['C']]])),
        );
        $this->assertStringContainsString('tidak berisi baris kegiatan', $this->galatBaca($this->berkas(['Cascade' => [['Kode SK', 'Kode IKSK', 'Nama Kegiatan'], ['SK.9', 'IKSK 9.1', null]]])));
    }

    public function test_pemeriksa_mencocokkan_kode_dengan_master_dan_menyusun_cascade(): void
    {
        $leading = MasterKode::leadingsUntuk(['role' => 'leading', 'leadings' => ['1.6']]);
        $baris = collect(PemeriksaPerkin::periksa(PembacaPerkin::baca(base_path(self::CONTOH), 'x.xlsx')['baris'], ['supervisi akademik dan refleksi pembelajaran'], $leading, MasterKode::leadings(), '1.6'))->keyBy('baris');

        $this->assertSame(['siap', 'siap', 'siap', 'dilewati', 'gagal', 'siap', 'gagal', 'gagal', 'gagal'], $baris->pluck('status')->values()->all());
        $this->assertSame(['IKSK 9.3 adalah indikator SK.9, bukan SK.8.'], $baris[9]['pesan']);
        // IKSK di luar master dikosongkan (peringatan), SK-nya tetap.
        $this->assertSame(['SK.8', null], [$baris[10]['kode_sk'], $baris[10]['kode_iksk']]);
        $this->assertSame(['IKSK 8.9 tidak ada di master TA 2026 — dikosongkan, pilih nanti di Data Kegiatan.'], $baris[10]['peringatan']);
        $this->assertStringContainsString('bukan leading akun Anda', $baris[11]['pesan'][0]);
        $this->assertCount(2, $baris[13]['pesan'], 'Format SK & IKSK salah.');
        // Asal kode: tertulis di baris itu, ikut sel gabung di atas, atau (SK) dari induk IKSK.
        $this->assertSame([['berkas', 'berkas'], ['atas', 'atas'], ['atas', 'berkas']], [[$baris[4]['asal_sk'], $baris[4]['asal_iksk']], [$baris[5]['asal_sk'], $baris[5]['asal_iksk']], [$baris[6]['asal_sk'], $baris[6]['asal_iksk']]]);
        $this->assertSame([['IKSK 8.9'], null], [$baris[10]['dikosongkan'], $baris[10]['asal_iksk']]);

        $data = PemeriksaPerkin::dataKegiatan($baris[4]);
        $this->assertSame(['Pelatihan Guru Pendidikan Inklusif', 'SK.9', 'IKSK 9.1', 'draf', 'impor_perkin', [7], [['sumber' => 'DIPA', 'pagu' => 4_500_000]]], [
            $data['nama_kegiatan'], $data['kode_sk'], $data['kode_iksk'], $data['status'], $data['asal'], $data['bulan_pelaksanaan'], $data['sumber_dana'],
        ]);

        $cascade = PemeriksaPerkin::cascade($baris->values()->all());
        $this->assertSame(['SK.8', 'SK.9'], array_column($cascade, 'kode'));
        $this->assertSame([3, ['DIPA' => 10_500_000, 'Komite' => 2_500_000]], [$cascade[1]['kegiatan'], $cascade[1]['pagu']]);
        $this->assertSame([['IKSK 9.1', 2], ['IKSK 9.2', 1]], array_map(fn ($i) => [$i['kode'], $i['kegiatan']], $cascade[1]['iksk']));
        $this->assertNull($cascade[0]['iksk'][0]['kode']);

        // SK kosong diisi dari induk IKSK; kode di luar master SK dikosongkan.
        $lain = PemeriksaPerkin::periksa([
            ['baris' => 2, 'nilai' => ['kode_sk' => null, 'kode_iksk' => 'iksk 10-2', 'nama_kegiatan' => 'A', 'leading' => '1.6']],
            ['baris' => 3, 'nilai' => ['kode_sk' => 'SK 99', 'kode_iksk' => null, 'nama_kegiatan' => 'B', 'leading' => '1.6']],
            ['baris' => 4, 'nilai' => ['kode_sk' => null, 'kode_iksk' => null, 'nama_kegiatan' => 'C', 'leading' => '1.6']],
        ], [], $leading, MasterKode::leadings());
        $this->assertSame([['SK.10', 'IKSK 10.2'], [null, null], [null, null]], array_map(fn ($b) => [$b['kode_sk'], $b['kode_iksk']], $lain));
        $this->assertSame(['induk', ['SK.99']], [$lain[0]['asal_sk'], $lain[1]['dikosongkan']]);
        $this->assertSame('SK.99 tidak ada di master TA 2026 — dikosongkan, pilih nanti di Data Kegiatan.', $lain[1]['peringatan'][0]);
        $this->assertSame('Kode SK & IKSK kosong — pilih nanti di Data Kegiatan.', $lain[2]['peringatan'][0]);
    }

    public function test_unggah_dan_pratinjau_di_tab_perkin(): void
    {
        // Nama yang sama di tahun lalu tidak menghalangi kegiatan tahun aktif.
        app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Pelatihan Guru Pendidikan Inklusif', 'leading' => '1.6', 'tahun_anggaran' => 2025]);
        $jumlah = count(app(KegiatanRepository::class)->semua());

        Livewire::withQueryParams(['tab' => 'perkin'])->test(SalinImpor::class)
            ->assertSeeLivewire(ImporPerkin::class)
            ->assertSeeHtml('data-testid="kolom-perkin"');

        Livewire::test(ImporPerkin::class)
            ->assertSee('Unggah sheet cascade Perkin')
            ->set('berkas', UploadedFile::fake()->createWithContent('MASTER 2026.xlsx', (string) file_get_contents(base_path(self::CONTOH))))
            ->assertSet('tahap', 'periksa')
            ->assertSet('lembar', 'Cascade Perkin')
            ->assertSet('kolomHilang', [])
            ->assertSeeHtml('data-testid="cascade-perkin"')
            ->assertSeeHtml('data-sk="SK.9"')
            ->assertSeeHtml('data-iksk="IKSK 9.2"')
            ->assertSee('Sheet Cascade Perkin')
            ->assertSee('Siap (4)')
            ->assertSee('Gagal (4)')
            ->assertSee('IKSK 9.3 adalah indikator SK.9, bukan SK.8.')
            ->assertSeeHtml('data-testid="tabel-perkin"')
            ->assertSeeHtml('data-baris-perkin="5"')
            ->assertSee('dari sel gabung di atas')
            ->assertSeeHtml('title="Tidak ada di master — dikosongkan">IKSK 8.9</span>')
            ->assertSee('Jumlah baris siap (4 kegiatan)')
            ->assertSeeInOrder(['Jumlah baris siap', 'Rp12.500.000', 'Rp2.500.000'])
            ->assertSeeHtml('data-testid="mulai-impor"')
            ->assertSee('Impor 4 kegiatan')
            ->call('mulaiImpor')
            ->assertSet('tahap', 'periksa', 'Proses impor masih Segera.')
            ->call('ulangi')
            ->assertSet('tahap', 'unggah')
            ->assertSet('lembar', '');

        $this->assertCount($jumlah, app(KegiatanRepository::class)->semua(), 'Pratinjau tidak menyimpan apa pun.');

        // Berkas tanpa kolom opsional: pratinjau tetap jalan dengan keterangan kolom yang hilang.
        $path = $this->berkas(['Perkin 2026' => [['Kode SK', 'Nama Kegiatan'], ['SK.9', 'Rapat Sarana']]]);
        Livewire::test(ImporPerkin::class)
            ->set('berkas', UploadedFile::fake()->createWithContent('perkin.xlsx', (string) file_get_contents($path)))
            ->assertSet('kolomHilang', ['Kode IKSK', 'Leading', 'Pagu DIPA', 'Pagu Komite', 'Bulan Pelaksanaan'])
            ->assertSeeHtml('data-testid="kolom-hilang-perkin"')
            ->assertSet('baris.0.status', 'siap');
        @unlink($path);

        Livewire::test(ImporPerkin::class)
            ->set('berkas', UploadedFile::fake()->createWithContent('master.xlsx', (string) file_get_contents(base_path('tests/fixtures/master-kode-contoh.xlsx'))))
            ->assertSet('tahap', 'unggah')
            ->assertSeeHtml('data-testid="galat-impor"');
    }
}
