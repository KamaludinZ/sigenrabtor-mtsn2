<?php

namespace Tests\Feature\Master;

use App\Support\Impor\GagalBacaImpor;
use App\Support\Master\Impor\BerkasMaster;
use App\Support\Master\Impor\PratinjauMaster;
use App\Support\Master\Impor\ProsesBerkasMaster;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ProsesBerkasMasterTest extends TestCase
{
    private const ADMIN = ['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin', 'leadings' => []];

    private function fixture(string $nama): UploadedFile
    {
        return new UploadedFile(base_path("tests/fixtures/{$nama}"), $nama, null, null, true);
    }

    private function galat(callable $aksi): string
    {
        try {
            $aksi();
        } catch (GagalBacaImpor $e) {
            return $e->getMessage();
        }
        $this->fail('Seharusnya ditolak.');
    }

    public function test_validasi_berkas_dan_cakupan(): void
    {
        $contoh = ProsesBerkasMaster::periksaBerkas($this->fixture('master-kode-contoh.xlsx'), BerkasMaster::SEMUA);
        $this->assertSame('master-kode-contoh.xlsx', $contoh['nama']);
        $this->assertSame(['sk', 'iksk', 'rktm', 'edm', 'budaya'], array_keys(array_filter($contoh['sheet'])), 'Sheet tiap jenis dikenali.');

        $csv = UploadedFile::fake()->createWithContent('rktm.csv', "Kode;SNP;Kegiatan;Sub Kegiatan\n1.1.1;Standar Isi;Kurikulum;Workshop\n");
        $this->assertSame(['rktm' => 'CSV'], ProsesBerkasMaster::periksaBerkas($csv, 'rktm')['sheet']);
        $this->assertSame(['CSV'], ProsesBerkasMaster::bacaBerkas($csv)['lembar'], 'Terbaca walau cakupannya belum dipilih.');
        $this->assertSame('Berkas CSV hanya untuk satu jenis kode. Pilih jenisnya, atau unggah berkas .xlsx berisi semua sheet.', $this->galat(fn () => ProsesBerkasMaster::periksaBerkas($csv, BerkasMaster::SEMUA)));

        $this->assertSame('Tidak ada sheet yang dikenali. Beri nama sheet Perkin (SK & IKSK), SNP (RKTM), EDM, dan 5 Budaya Mutu seperti template.', $this->galat(fn () => ProsesBerkasMaster::periksaBerkas($this->fixture('master-kode-asing.xlsx'), BerkasMaster::SEMUA)));
        $this->assertSame('Pilih berkas master kode.', $this->galat(fn () => ProsesBerkasMaster::bacaBerkas(null)));
        $this->assertSame('Pilih berkas Excel (.xlsx, .xls) atau CSV.', $this->galat(fn () => ProsesBerkasMaster::bacaBerkas(UploadedFile::fake()->create('master.pdf', 10, 'application/pdf'))));
        $this->assertSame('Ukuran berkas maksimal 5 MB.', $this->galat(fn () => ProsesBerkasMaster::bacaBerkas(UploadedFile::fake()->create('besar.xlsx', BerkasMaster::MAKS_KB + 1))));
        $this->assertSame('Berkas "rusak.xlsx" tidak dapat dibaca sebagai Excel. Simpan ulang sebagai .xlsx lalu unggah lagi.', $this->galat(fn () => ProsesBerkasMaster::bacaBerkas(UploadedFile::fake()->createWithContent('rusak.xlsx', 'bukan excel'))));
    }

    public function test_proses_pratinjau_ringkasan_dan_kendala(): void
    {
        $path = base_path('tests/fixtures/master-kode-contoh.xlsx');
        $hasil = ProsesBerkasMaster::proses($path, 'master-kode-contoh.xlsx', BerkasMaster::SEMUA, 2026, self::ADMIN);

        $this->assertSame(PratinjauMaster::susun($path, 'master-kode-contoh.xlsx', $hasil['sheet'], 2026), $hasil['pratinjau'], 'Isi sama dengan pratinjau panel.');
        foreach (array_keys(PratinjauMaster::STATUS) as $status) {
            $this->assertSame(array_sum(array_map(fn (array $i) => $i['ringkasan'][$status], $hasil['pratinjau'])), $hasil['ringkasan'][$status]);
        }
        $this->assertSame(ProsesBerkasMaster::kendala($hasil['pratinjau']), $hasil['kendala']);
        $this->assertSame($hasil['kendala'] === [], $hasil['siap']);

        $this->assertSame(['rktm'], array_keys(ProsesBerkasMaster::proses($path, 'master-kode-contoh.xlsx', 'rktm', 2026, self::ADMIN)['pratinjau']));

        foreach ([[2025, self::ADMIN, 'terkunci'], [2031, self::ADMIN, 'belum dibuat'], [2026, ['role' => 'leading'], 'admin']] as [$tahun, $pengguna, $pesan]) {
            try {
                ProsesBerkasMaster::proses($path, 'master-kode-contoh.xlsx', BerkasMaster::SEMUA, $tahun, $pengguna);
                $this->fail('Seharusnya ditolak.');
            } catch (AuthorizationException $e) {
                $this->assertStringContainsString($pesan, $e->getMessage());
            }
        }
        $this->assertSame('Cakupan unggahan tidak dikenal.', $this->galat(fn () => ProsesBerkasMaster::proses($path, 'master-kode-contoh.xlsx', 'bos', 2026, self::ADMIN)));
    }

    public function test_kendala_dan_peringatan_dari_pratinjau(): void
    {
        $item = fn (array $ringkasan, ?string $galat = null, array $baris = [], array $peringatan = []) => ['galat' => $galat, 'peringatan' => $peringatan, 'baris' => $baris, 'ringkasan' => $ringkasan + array_fill_keys(array_keys(PratinjauMaster::STATUS), 0)];
        $pratinjau = [
            'sk' => $item(['baru' => 2, 'tetap' => 3]),
            'iksk' => $item(['baru' => 1, 'gagal' => 2]),
            'rktm' => $item([], 'Judul kolom Kode tidak ditemukan.'),
            'edm' => $item(['hilang' => 2], baris: [['kode' => 'A1', 'status' => 'hilang', 'dipakai' => 3], ['kode' => 'A2', 'status' => 'hilang', 'dipakai' => 0]]),
            'budaya' => $item(['tetap' => 4], peringatan: ['Jumlah butir 4, biasanya 5.']),
        ];

        $this->assertSame([
            'IKSK: 2 baris gagal — perbaiki berkasnya lalu unggah lagi.',
            'RKTM: Judul kolom Kode tidak ditemukan.',
            'EDM: tidak ada baris yang bisa disimpan.',
        ], ProsesBerkasMaster::kendala($pratinjau));
        $this->assertSame([
            'EDM: 1 kode yang dipakai kegiatan tidak ada di berkas (A1).',
            '5 Budaya Mutu: Jumlah butir 4, biasanya 5.',
        ], ProsesBerkasMaster::peringatan($pratinjau));
        $this->assertSame([], ProsesBerkasMaster::kendala(['sk' => $item(['baru' => 1])]));
    }
}
