<?php

namespace Tests\Feature\Pengaturan;

use App\Livewire\Pengaturan\TemplateMaster;
use App\Support\Dokumen\ValidasiVariabelTemplate;
use App\Support\Pengaturan\UnggahMasterTemplate;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\Docx;
use Tests\TestCase;

class ValidasiVariabelTemplateTest extends TestCase
{
    private const ADMIN = ['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin', 'leadings' => []];

    private function berkas(array $paragraf, string $nama = 'master.docx'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nama, Docx::isi($paragraf));
    }

    public function test_galat_dan_peringatan_penanda(): void
    {
        // Berkas tiruan disimpan di variabel: berkas sementaranya hilang begitu objeknya dibuang.
        $berkas = $this->berkas(['Kegiatan ${nama_kegiatan}', 'Dasar hukum: ${dasar_hukum}', '${jadwal}', 'Total ${totl}']);
        $hasil = ValidasiVariabelTemplate::periksa($berkas->getRealPath(), 'KAK+TOR');
        $this->assertFalse($hasil['bisa_disimpan']);
        $this->assertSame([
            'berkas' => 'Perbaiki variabel yang tidak terdaftar di Word, lalu unggah ulang.',
            'variabel.totl' => '${totl} tidak terdaftar — maksud Anda ${total}?',
        ], $hasil['galat']);
        $this->assertSame(['sendiri.dasar_hukum', 'dianjurkan'], array_column($hasil['peringatan'], 'kunci'), '${jadwal} sendirian di paragrafnya tidak diperingatkan.');
        $this->assertStringContainsString('“Dasar hukum: ${dasar_hukum}”', $hasil['peringatan'][0]['pesan']);
        $this->assertSame('Dianjurkan memakai ${tahun_anggaran}, ${total}, ${terbilang} agar identitas & angka tidak diketik manual.', $hasil['peringatan'][1]['pesan']);

        $berkasRab = $this->berkas(['Total ${total}']);
        $rab = ValidasiVariabelTemplate::periksa($berkasRab->getRealPath(), 'RAB');
        $this->assertTrue($rab['bisa_disimpan']);
        $this->assertSame(['rab'], array_column($rab['peringatan'], 'kunci'));

        $berkasKosong = $this->berkas(['Tanpa penanda']);
        $kosong = ValidasiVariabelTemplate::periksa($berkasKosong->getRealPath(), 'KAK');
        $this->assertSame(['berkas'], array_keys($kosong['galat']));
        $this->assertSame([], $kosong['peringatan']);
    }

    public function test_simpan_menolak_penanda_tidak_terdaftar_dengan_saran(): void
    {
        try {
            UnggahMasterTemplate::simpan(2026, $this->berkas(['${nama_kegiatn}', '${total}']), 'Master', 'KAK+TOR', self::ADMIN);
            $this->fail('Penanda tidak terdaftar ditolak.');
        } catch (ValidationException $e) {
            $this->assertSame(['berkas', 'variabel.nama_kegiatn'], array_keys($e->errors()));
            $this->assertSame('${nama_kegiatn} tidak terdaftar — maksud Anda ${nama_kegiatan}?', $e->errors()['variabel.nama_kegiatn'][0]);
        }
    }

    public function test_endpoint_periksa_dan_simpan(): void
    {
        $this->withSession(['stub_role' => 'admin']);
        $periksa = $this->post(route('pengaturan.template.master.periksa'), ['berkas' => $this->berkas(['${nama_kegiatan} ${totl}']), 'jenis' => 'KAK+TOR'], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('bisa_disimpan', false)
            ->assertJsonPath('variabel.1.nama', 'totl')
            ->assertJsonPath('variabel.1.saran', 'total');
        $this->assertSame('${totl} tidak terdaftar — maksud Anda ${total}?', $periksa->json('galat')['variabel.totl']);
        $this->post(route('pengaturan.template.master.periksa'), ['berkas' => UploadedFile::fake()->create('a.pdf', 5, 'application/pdf'), 'jenis' => 'KAK'], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonPath('galat.berkas', 'Pilih dokumen Word .docx (bukan .doc atau PDF).');
        $this->post(route('pengaturan.template.master.simpan'), ['berkas' => $this->berkas(['Total ${total}']), 'nama' => 'RAB umum', 'jenis' => 'RAB'], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('peringatan.0.kunci', 'rab');

        Livewire::test(TemplateMaster::class)
            ->call('bukaUnggah')
            ->set('berkas', $this->berkas(['${nama_kegiatan}', 'Hukum: ${dasar_hukum}']))
            ->assertSeeHtml('data-testid="peringatan-penanda"')
            ->assertSeeHtml('data-kunci="sendiri.dasar_hukum"')
            ->set('jenisBaru', 'RAB')
            ->assertSeeHtml('data-kunci="rab"');
    }
}
