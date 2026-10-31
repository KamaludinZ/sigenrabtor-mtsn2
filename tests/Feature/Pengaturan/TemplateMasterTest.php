<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\KegiatanRepository;
use App\Contracts\PilihanTemplateRepository;
use App\Contracts\TemplateDokumenRepository;
use App\Livewire\Pengaturan\TemplateMaster;
use App\Support\Dokumen\DaftarTemplateMaster;
use App\Support\Dokumen\PilihanTemplate;
use App\Support\Pengaturan\UnggahMasterTemplate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\Docx;
use Tests\TestCase;

class TemplateMasterTest extends TestCase
{
    /**
     * Tiga master TA 2026: umum (default KAK+TOR), khusus leading 1.6 bercatatan, dan RAB umum yang dinonaktifkan —
     * atribut pemilik/catatan/aktif ditulis langsung ke berkas tiruan (penyimpanannya menyusul di task backend).
     *
     * @return array{umum: string, khusus: string, rab: string}
     */
    private function siapkan(): array
    {
        $repo = app(TemplateDokumenRepository::class);
        $berkas = fn (string $nama) => UploadedFile::fake()->createWithContent($nama, Docx::isi(['Kegiatan ${nama_kegiatan}', 'Total ${total}']));
        $id = [
            'umum' => $repo->tambah(2026, 'Master reguler', 'KAK+TOR', $berkas('reguler.docx'), ['nama_kegiatan', 'total']),
            'khusus' => $repo->tambah(2026, 'Master TPM narasumber', 'KAK+TOR', $berkas('tpm.docx'), ['nama_kegiatan']),
            'rab' => $repo->tambah(2026, 'Master RAB lama', 'RAB', $berkas('rab.docx'), ['total']),
        ];
        $path = config('sigenrabtor.stub.master_file');
        $semua = json_decode((string) Storage::disk('local')->get($path), true);
        foreach ($semua as &$t) {
            if ($t['id'] === $id['khusus']) {
                $t = ['leading' => '1.6', 'catatan' => 'Versi 2 — dengan narasumber eksternal', 'is_default' => true] + $t;
            }
            if ($t['id'] === $id['rab']) {
                $t = ['aktif' => false] + $t;
            }
        }
        Storage::disk('local')->put($path, json_encode($semua));
        Storage::disk('local')->delete($repo->temukan($id['rab'])['file_path']);

        return $id;
    }

    public function test_daftar_dikelompokkan_per_pemilik(): void
    {
        $id = $this->siapkan();
        app(PilihanTemplateRepository::class)->simpan(2026, PilihanTemplate::rapikan(['jenis' => ['workshop' => ['kak' => $id['umum']]], 'leading' => []]));

        $data = DaftarTemplateMaster::data(2026);
        $this->assertSame(['jumlah' => 3, 'aktif' => 2, 'nonaktif' => 1, 'umum' => 2, 'khusus' => 1, 'leading' => 1, 'dipakai' => 1, 'tanpa_berkas' => 1], $data['ringkasan']);
        $this->assertSame(['umum', '1.6'], array_column($data['kelompok'], 'kunci'));
        $this->assertSame('Umum — semua leading', $data['kelompok'][0]['label']);
        $this->assertStringStartsWith('1.6 · ', $data['kelompok'][1]['label']);
        $this->assertSame(['Master reguler', 'Master RAB lama'], array_column($data['kelompok'][0]['template'], 'nama'), 'Default & aktif dulu.');
        $this->assertSame(['KAK+TOR' => 'Master reguler'], $data['kelompok'][0]['default']);
        $this->assertSame(['KAK+TOR' => 'Master TPM narasumber'], $data['kelompok'][1]['default']);
        $this->assertNotContains('1.6', array_column($data['tanpa_khusus'], 'kode'));

        $umum = $data['kelompok'][0]['template'][0];
        $this->assertSame([2, true, true, null], [$umum['variabel'], $umum['ada_berkas'], $umum['aktif'], $umum['catatan']]);
        $this->assertSame('workshop', $umum['dipakai'][0]['jenis']);
        $this->assertGreaterThanOrEqual(1, $umum['kegiatan'], 'Contoh kegiatan workshop memakai master per jenis.');
        $rab = $data['kelompok'][0]['template'][1];
        $this->assertSame([false, false], [$rab['aktif'], $rab['ada_berkas']]);
        $this->assertSame('Versi 2 — dengan narasumber eksternal', $data['kelompok'][1]['template'][0]['catatan']);
    }

    public function test_saringan(): void
    {
        $this->siapkan();
        $tersaring = fn (array $nilai) => array_column(DaftarTemplateMaster::saring(DaftarTemplateMaster::semua(2026), DaftarTemplateMaster::saringan($nilai)), 'nama');

        $this->assertSame(['cari' => '', 'leading' => '', 'jenis' => '', 'status' => ''], DaftarTemplateMaster::saringan(['leading' => '9.9', 'jenis' => 'BUKU', 'status' => 'hapus']));
        $this->assertSame(['Master TPM narasumber'], $tersaring(['leading' => '1.6']));
        $this->assertSame(['Master reguler', 'Master RAB lama'], $tersaring(['leading' => 'umum']));
        $this->assertSame(['Master RAB lama'], $tersaring(['jenis' => 'RAB']));
        $this->assertSame(['Master RAB lama'], $tersaring(['status' => 'nonaktif']));
        $this->assertSame(['Master TPM narasumber'], $tersaring(['cari' => 'NARASUMBER  eksternal']));
        $this->assertSame([], $tersaring(['cari' => 'tidak ada', 'status' => 'aktif']));
    }

    public function test_halaman(): void
    {
        $this->withSession(['stub_role' => 'admin']);
        $this->get(route('pengaturan.template.master.index'))->assertOk()->assertSee('Template Master KAK/TOR');

        Livewire::test(TemplateMaster::class)
            ->assertSeeHtml('data-testid="template-master-kosong"')
            ->assertDontSeeHtml('data-testid="saringan-template-master"');

        $id = $this->siapkan();
        Livewire::withQueryParams(['leading' => '9.9', 'status' => 'nonaktif'])->test(TemplateMaster::class)
            ->assertSet('leading', '')
            ->assertSet('status', 'nonaktif')
            ->assertSeeHtml('data-id="'.$id['rab'].'" data-status="nonaktif" data-default="tidak"')
            ->assertDontSeeHtml('data-id="'.$id['umum'].'"')
            ->assertSeeHtml('data-testid="berkas-hilang"')
            ->set('status', '')
            ->set('leading', '1.6')
            ->assertSeeHtml('data-testid="kelompok-template" data-kunci="1.6"')
            ->assertDontSeeHtml('data-kunci="umum"')
            ->assertSee('Versi 2 — dengan narasumber eksternal')
            ->set('jenis', 'RAB')
            ->assertSeeHtml('data-testid="template-master-tanpa-hasil"')
            ->call('hapusSaringan')
            ->assertSet('leading', '')
            ->assertSeeHtml('data-kunci="umum"')
            ->set('cari', 'reguler ')
            ->assertSet('cari', 'reguler ')
            ->assertSeeHtml('data-id="'.$id['umum'].'"')
            ->assertDontSeeHtml('data-id="'.$id['khusus'].'"');

        $this->withSession(['stub_role' => 'leading'])->get(route('pengaturan.template.master.index'))->assertForbidden();
    }

    public function test_form_unggah_template_berpenanda(): void
    {
        $this->withSession(['stub_role' => 'admin']);
        $docx = fn (string $nama, array $paragraf) => UploadedFile::fake()->createWithContent($nama, Docx::isi($paragraf));

        $halaman = Livewire::withQueryParams(['leading' => '1.6'])->test(TemplateMaster::class)
            ->call('bukaUnggah')
            ->assertSet('unggahTerbuka', true)
            ->assertSet('pemilik', '1.6', 'Pemilik bawaan = leading yang sedang disaring.')
            ->assertSeeHtml('data-testid="form-unggah-master"')
            ->assertSeeHtml('data-testid="simpan-template-belum"')
            ->set('berkas', $docx('kak-tpm.docx', ['Kegiatan ${nama_kegiatan}', 'Jumlah ${totl}']))
            ->assertSet('nama', 'kak-tpm')
            ->assertSeeHtml('data-penanda-nama="totl" data-terdaftar="tidak"')
            ->assertSeeHtml('data-penanda-nama="nama_kegiatan" data-terdaftar="ya"')
            ->assertSee('maksud Anda')
            ->assertSeeHtml('data-testid="penanda-tidak-terdaftar"')
            ->assertSeeHtml('data-testid="simpan-template-belum"')
            ->set('berkas', $docx('kak-tpm.docx', ['Kegiatan ${nama_kegiatan}', 'Jumlah ${total} (${terbilang})']))
            ->assertSeeHtml('data-testid="ringkasan-penanda" data-jumlah="3" data-tidak-terdaftar="0"')
            ->assertSeeHtml('data-testid="isi-berpenanda"')
            ->assertSeeHtml('data-penanda="terdaftar"')
            ->assertSeeHtml('data-testid="simpan-template"')
            ->assertSeeHtml('data-ada-default="tidak"')
            ->set('nama', 'KAK/TOR TPM reguler')
            ->set('catatan', '  Versi 1   untuk kegiatan reguler ')
            ->call('simpanUnggah')
            ->assertHasNoErrors()
            ->assertSet('unggahTerbuka', false)
            ->assertDispatched('notify', message: 'Template “KAK/TOR TPM reguler” tersimpan untuk '.mb_lcfirst(DaftarTemplateMaster::labelPemilik('1.6')).' dan menjadi default KAK+TOR.', type: 'success');

        $tersimpan = collect(app(TemplateDokumenRepository::class)->semua(2026))->firstWhere('nama', 'KAK/TOR TPM reguler');
        $this->assertSame(['1.6', 'Versi 1 untuk kegiatan reguler', true, ['nama_kegiatan', 'total', 'terbilang']], [$tersimpan['leading'], $tersimpan['catatan'], $tersimpan['is_default'], $tersimpan['variabel']]);
        $halaman->assertSeeHtml('data-id="'.$tersimpan['id'].'"')->assertSee('Versi 1 untuk kegiatan reguler');

        // Template kedua pemilik yang sama: pilihan "jadikan default" muncul; galat aturan tampil di isiannya.
        $halaman->call('bukaUnggah', '1.6')
            ->assertSeeHtml('data-ada-default="ya"')
            ->set('berkas', $docx('kak-tpm-2.docx', ['Kegiatan ${nama_kegiatan}']))
            ->set('catatan', str_repeat('a', UnggahMasterTemplate::MAKS_CATATAN + 1))
            ->call('simpanUnggah')
            ->assertHasErrors(['catatan'])
            ->set('catatan', 'Versi 2')
            ->set('jadikanDefault', true)
            ->call('simpanUnggah')
            ->assertHasNoErrors();
        $this->assertEquals(['KAK/TOR TPM reguler' => false, 'kak-tpm-2' => true], array_column(app(TemplateDokumenRepository::class)->semua(2026), 'is_default', 'nama'));

        $halaman->call('bukaUnggah')
            ->set('berkas', UploadedFile::fake()->create('kak.pdf', 10, 'application/pdf'))
            ->assertHasErrors(['berkas'])
            ->assertSeeHtml('data-testid="galat-berkas-template"')
            ->call('tutupUnggah')
            ->assertSet('unggahTerbuka', false)
            ->assertSet('berkas', null);
    }

    public function test_tautan_dari_template_dokumen(): void
    {
        $this->withSession(['stub_role' => 'admin'])
            ->get(route('pengaturan.template.index', ['tab' => 'kak']))
            ->assertOk()
            ->assertSee(route('pengaturan.template.master.index'), false);
        $this->assertNotEmpty(app(KegiatanRepository::class)->semua());
    }
}
