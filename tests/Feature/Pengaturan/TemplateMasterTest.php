<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\KegiatanRepository;
use App\Contracts\PilihanTemplateRepository;
use App\Contracts\TemplateDokumenRepository;
use App\Livewire\Pengaturan\TemplateMaster;
use App\Support\Dokumen\DaftarTemplateMaster;
use App\Support\Dokumen\PilihanTemplate;
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

    public function test_tautan_dari_template_dokumen(): void
    {
        $this->withSession(['stub_role' => 'admin'])
            ->get(route('pengaturan.template.index', ['tab' => 'kak']))
            ->assertOk()
            ->assertSee(route('pengaturan.template.master.index'), false);
        $this->assertNotEmpty(app(KegiatanRepository::class)->semua());
    }
}
