<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Contracts\PilihanTemplateRepository;
use App\Contracts\TemplateDokumenRepository;
use App\Livewire\Kegiatan\SusunDokumen;
use App\Support\Dokumen\DaftarTemplate;
use App\Support\Dokumen\PilihanTemplate;
use App\Support\Kegiatan\UbahDataKegiatan;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\Docx;
use Tests\TestCase;

class TemplateKegiatanTest extends TestCase
{
    private const LEADING = ['id' => 1, 'name' => 'Muslimin, M.Pd.', 'role' => 'leading', 'leadings' => ['1.6']];

    /** @return array<string, string> nama pendek → id master TA 2026 */
    private function siapkan(): array
    {
        $repo = app(TemplateDokumenRepository::class);
        $tambah = fn (string $nama, ?string $leading, array $atribut = []) => $repo->tambah(2026, $nama, 'KAK+TOR', UploadedFile::fake()->createWithContent("{$nama}.docx", Docx::isi(['Kegiatan ${nama_kegiatan}'])), ['nama_kegiatan'], ['leading' => $leading] + $atribut);
        $id = [
            'umum' => $tambah('Umum reguler', null),
            'tpm' => $tambah('TPM reguler', '1.6', ['catatan' => 'Versi 1']),
            'narasumber' => $tambah('TPM narasumber', '1.6', ['catatan' => 'Versi 2 — narasumber eksternal']),
            'lama' => $tambah('TPM lama', '1.6'),
            'kurikulum' => $tambah('Kurikulum reguler', '1.1'),
        ];
        $path = config('sigenrabtor.stub.master_file');
        $semua = array_map(fn (array $t) => $t['id'] === $id['lama'] ? ['aktif' => false] + $t : $t, json_decode((string) Storage::disk('local')->get($path), true));
        Storage::disk('local')->put($path, json_encode($semua));

        return $id;
    }

    private function kegiatanBaru(array $data = []): int
    {
        return app(KegiatanRepository::class)->simpan($data + ['nama_kegiatan' => 'Rapat Evaluasi Mutu', 'leading' => '1.6', 'user_id' => 1, 'tahun_anggaran' => 2026]);
    }

    public function test_pilihan_dan_template_berlaku(): void
    {
        $id = $this->siapkan();
        $kegiatan = app(KegiatanRepository::class)->temukan($this->kegiatanBaru());

        $this->assertSame([$id['tpm'], $id['narasumber'], $id['umum']], array_column(PilihanTemplate::pilihanKegiatan($kegiatan), 'id'), 'Milik leading (default dulu) lalu umum; nonaktif & leading lain tidak ada.');
        $this->assertSame(['asal' => 'default_leading', 'kak' => $id['tpm']], array_intersect_key(PilihanTemplate::untukKegiatan($kegiatan), ['asal' => 1, 'kak' => 1]));
        $this->assertSame('Default template leading', DaftarTemplate::untukKegiatan($kegiatan)['label_asal']);

        // Aturan khusus leading × jenis Format Seragam lebih spesifik daripada default leading.
        app(PilihanTemplateRepository::class)->simpan(2026, PilihanTemplate::rapikan(['jenis' => [], 'leading' => [['leading' => '1.6', 'jenis' => 'supervisi', 'kak' => $id['umum']]]]));
        $this->assertSame(['asal' => 'leading', 'kak' => $id['umum']], array_intersect_key(PilihanTemplate::untukKegiatan($kegiatan), ['asal' => 1, 'kak' => 1]));

        // Pilihan kegiatan paling diutamakan; disimpan lewat UbahDataKegiatan.
        $this->assertSame(['template_kak'], UbahDataKegiatan::ubah($kegiatan['id'], ['template_kak' => $id['narasumber']], self::LEADING)['diubah']);
        $kegiatan = app(KegiatanRepository::class)->temukan($kegiatan['id']);
        $this->assertSame(['asal' => 'kegiatan', 'kak' => $id['narasumber']], array_intersect_key(PilihanTemplate::untukKegiatan($kegiatan), ['asal' => 1, 'kak' => 1]));
        $this->assertSame('TPM narasumber', PilihanTemplate::masterKak($kegiatan)['nama']);

        foreach ([$id['kurikulum'], $id['lama'], 'bukan-template'] as $tidakSah) {
            try {
                UbahDataKegiatan::ubah($kegiatan['id'], ['template_kak' => $tidakSah], self::LEADING);
                $this->fail('Template di luar pilihan ditolak.');
            } catch (ValidationException $e) {
                $this->assertSame('Pilih template KAK/TOR milik leading kegiatan ini atau template umum yang aktif.', $e->errors()['template_kak'][0]);
            }
        }

        // Pilihan yang tidak lagi sah (leading berganti) diabaikan: kembali otomatis.
        $this->assertSame('default_leading', PilihanTemplate::untukKegiatan(['leading' => '1.1'] + $kegiatan)['asal']);
        $this->assertSame($id['kurikulum'], PilihanTemplate::untukKegiatan(['leading' => '1.1'] + $kegiatan)['kak']);
        $this->assertSame('bawaan', PilihanTemplate::untukKegiatan(['leading' => '1.2', 'template_kak' => null] + $kegiatan)['asal'], 'Leading tanpa template & tanpa aturan = susunan baku.');
    }

    public function test_dropdown_di_susun_dokumen(): void
    {
        $id = $this->siapkan();
        $kegiatanId = $this->kegiatanBaru();
        $this->withSession(['stub_role' => 'admin']);

        Livewire::test(SusunDokumen::class, ['kegiatan' => $kegiatanId])
            ->assertSeeHtml('data-testid="template-kak-kegiatan" data-asal="default_leading"')
            ->assertSee('Otomatis — TPM reguler')
            ->assertSee('Umum — semua leading')
            ->assertDontSee('Kurikulum reguler')
            ->assertDontSee('TPM lama')
            ->call('ubahTemplateKak', $id['narasumber'])
            ->assertDispatched('notify', message: 'Template KAK/TOR kini TPM narasumber.', type: 'success')
            ->assertSeeHtml('data-asal="kegiatan"')
            ->assertSee('Versi 2 — narasumber eksternal')
            ->call('ubahTemplateKak', '')
            ->assertDispatched('notify', message: 'Template KAK/TOR kini TPM reguler (otomatis).', type: 'success')
            ->assertSeeHtml('data-asal="default_leading"')
            ->call('ubahTemplateKak', $id['kurikulum'])
            ->assertNotFound();
        $this->assertArrayNotHasKey('template_kak', array_filter(app(KegiatanRepository::class)->temukan($kegiatanId)));

        // Kegiatan terkunci (sudah diajukan) tidak bisa mengganti template.
        $repo = app(KegiatanRepository::class);
        $repo->simpan(['status' => 'diperiksa'] + $repo->temukan($kegiatanId), $kegiatanId);
        Livewire::test(SusunDokumen::class, ['kegiatan' => $kegiatanId])
            ->assertSeeHtml('data-testid="pilih-template-kak"')
            ->call('ubahTemplateKak', $id['narasumber'])
            ->assertForbidden();
    }
}
