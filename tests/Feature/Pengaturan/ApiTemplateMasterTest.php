<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\KegiatanRepository;
use App\Contracts\TemplateDokumenRepository;
use App\Livewire\Pengaturan\TemplateDokumen;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\Support\Docx;
use Tests\TestCase;

class ApiTemplateMasterTest extends TestCase
{
    private function docx(string $nama, array $paragraf = ['Kegiatan ${nama_kegiatan}', 'Total ${total}', 'Lagi ${nama_kegiatan}']): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nama, Docx::isi($paragraf));
    }

    public function test_crud_template_master(): void
    {
        $this->withSession(['stub_role' => 'admin']);

        // Unggah: galat isian lalu berhasil (pemilik leading, catatan versi, otomatis default).
        $this->post(route('pengaturan.template.master.simpan'), ['berkas' => $this->docx('salah.docx', ['${totl}']), 'nama' => 'X', 'jenis' => 'KAK+TOR'], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonPath('galat.berkas', 'Perbaiki variabel yang tidak terdaftar di Word, lalu unggah ulang.');
        $this->post(route('pengaturan.template.master.simpan'), ['berkas' => $this->docx('a.docx'), 'nama' => '', 'jenis' => 'BUKU', 'leading' => '9.9'], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonStructure(['galat' => ['nama', 'jenis', 'leading']]);
        $id = $this->post(route('pengaturan.template.master.simpan'), ['berkas' => $this->docx('tpm.docx'), 'nama' => ' TPM   reguler ', 'jenis' => 'KAK+TOR', 'leading' => '1.6', 'catatan' => 'Versi 1'], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('template.nama', 'TPM reguler')
            ->assertJsonPath('template.leading', '1.6')
            ->assertJsonPath('template.default', true)
            ->assertJsonPath('template.pengunggah', 'Koordinator PUSKOM')
            ->json('template.id');
        $kedua = $this->post(route('pengaturan.template.master.simpan'), ['berkas' => $this->docx('tpm2.docx'), 'nama' => 'TPM ringkas', 'jenis' => 'KAK+TOR', 'leading' => '1.6'], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('template.default', false)->json('template.id');

        // Daftar & detail.
        $this->getJson(route('pengaturan.template.master.data', ['leading' => '1.6']))
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('ringkasan.jumlah', 2)->assertJsonPath('tersaring', 2)
            ->assertJsonPath('kelompok.0.kunci', '1.6')
            ->assertJsonPath('kelompok.0.default', ['KAK+TOR' => 'TPM reguler']);
        $this->getJson(route('pengaturan.template.master.data', ['ta' => 2099]))->assertNotFound();
        $this->getJson(route('pengaturan.template.master.detail', $id))
            ->assertOk()
            ->assertJsonPath('template.catatan', 'Versi 1')
            ->assertJsonPath('jumlah', ['nama_kegiatan' => 2, 'total' => 1])
            ->assertJsonPath('isi.jenis_template', 'master')
            ->assertJsonPath('dampak_nonaktif.default', true)
            ->assertJsonPath('dampak_nonaktif.pengganti', 'TPM ringkas');
        $this->getJson(route('pengaturan.template.master.detail', '00000000-0000-4000-8000-000000000000'))->assertNotFound();

        // Ubah sebagian: nama/catatan, default, nonaktif.
        $this->patchJson(route('pengaturan.template.master.ubah', $kedua), ['catatan' => 'Versi 2', 'default' => true])
            ->assertOk()
            ->assertJsonPath('perubahan', ['Catatan versi: — → Versi 2', 'Menjadi default'])
            ->assertJsonPath('template.nama', 'TPM ringkas')
            ->assertJsonPath('template.default', true);
        $this->patchJson(route('pengaturan.template.master.ubah', $kedua), ['nama' => ''])->assertStatus(422)->assertJsonStructure(['galat' => ['nama']]);
        $this->patchJson(route('pengaturan.template.master.ubah', $kedua), ['aktif' => false])
            ->assertOk()->assertJsonPath('perubahan', ['Dinonaktifkan'])->assertJsonPath('template.aktif', false);
        $this->assertTrue(app(TemplateDokumenRepository::class)->temukan($id)['is_default'], 'Default pindah ke template aktif lain.');
        $this->patchJson(route('pengaturan.template.master.ubah', $kedua), ['default' => true])
            ->assertStatus(422)->assertJsonPath('galat.default', 'Template nonaktif tidak bisa menjadi default — aktifkan dulu.');
        $this->patchJson(route('pengaturan.template.master.ubah', $kedua), [])->assertOk()->assertJsonPath('pesan', 'Template tidak berubah.');

        // Hapus: ditolak selama dipilih kegiatan.
        $kegiatan = app(KegiatanRepository::class);
        $kegiatanId = $kegiatan->simpan(['nama_kegiatan' => 'Rapat Mutu', 'leading' => '1.6', 'user_id' => 1, 'tahun_anggaran' => 2026, 'template_kak' => $id]);
        $this->deleteJson(route('pengaturan.template.master.hapus', $id))
            ->assertStatus(409)->assertJsonPath('kegiatan', [['id' => $kegiatanId, 'nama' => 'Rapat Mutu']]);
        $this->deleteJson(route('pengaturan.template.master.hapus', $kedua))->assertOk()->assertJsonPath('pesan', 'Template “TPM ringkas” dihapus.');
        $this->deleteJson(route('pengaturan.template.master.hapus', $kedua))->assertNotFound();

        // Tab KAK/TOR Template Dokumen memakai penjaga yang sama.
        Livewire::test(TemplateDokumen::class, ['tab' => 'kak'])
            ->call('mintaHapusMaster', $id)
            ->call('hapusMaster')
            ->assertDispatched('notify', type: 'error');
        $this->assertNotNull(app(TemplateDokumenRepository::class)->temukan($id));

        // Bukan admin.
        $this->withSession(['stub_role' => 'leading']);
        $this->getJson(route('pengaturan.template.master.data'))->assertForbidden();
        $this->patchJson(route('pengaturan.template.master.ubah', $id), ['nama' => 'Y'])->assertForbidden();
        $this->deleteJson(route('pengaturan.template.master.hapus', $id))->assertForbidden();
    }
}
