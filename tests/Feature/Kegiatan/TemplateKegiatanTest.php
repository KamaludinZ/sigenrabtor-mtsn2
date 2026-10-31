<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Contracts\PilihanTemplateRepository;
use App\Contracts\TemplateDokumenRepository;
use App\Livewire\Kegiatan\Form;
use App\Livewire\Kegiatan\PratinjauDokumen;
use App\Livewire\Kegiatan\SusunDokumen;
use App\Support\Dokumen\DaftarTemplate;
use App\Support\Dokumen\DataPratinjau;
use App\Support\Dokumen\PilihanTemplate;
use App\Support\Dokumen\TemplateDipakai;
use App\Support\Kegiatan\Duplikat\DuplikatKegiatan;
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

    public function test_nama_template_tampil_di_pratinjau(): void
    {
        $this->withSession(['stub_role' => 'admin']);
        $kosong = $this->kegiatanBaru();
        Livewire::test(PratinjauDokumen::class, ['kegiatan' => $kosong])
            ->assertSeeHtml('data-testid="kepala-template" data-kepala-lembar="kak" data-format="sembilan_bagian" data-template=""')
            ->assertSee('Format 9 bagian · Word: susunan baku')
            ->assertSee('Disusun dari susunan baku (format 9 bagian).')
            ->assertSee('Cover bawaan');

        $id = $this->siapkan();
        $kegiatanId = $this->kegiatanBaru(['nama_kegiatan' => 'Workshop Mutu Pembelajaran']);
        $kegiatan = app(KegiatanRepository::class)->temukan($kegiatanId);
        $dipakai = TemplateDipakai::untuk($kegiatan);
        $this->assertSame(['kunci' => 'sembilan_bagian', 'label' => 'Format 9 bagian'], $dipakai['format']);
        $this->assertSame(['id' => $id['tpm'], 'nama' => 'TPM reguler', 'jenis' => 'KAK+TOR', 'catatan' => 'Versi 1', 'leading' => '1.6', 'asal' => 'default_leading', 'label_asal' => 'default template leading', 'ada_berkas' => true], $dipakai['kak']);
        $this->assertNull($dipakai['cover']);
        $this->assertSame($dipakai, DataPratinjau::untuk($kegiatan, null, self::LEADING, ['kak'])['template_dipakai']);

        UbahDataKegiatan::ubah($kegiatanId, ['template_kak' => $id['narasumber'], 'format_dokumen' => 'lima_bab'], self::LEADING);
        Livewire::test(PratinjauDokumen::class, ['kegiatan' => $kegiatanId])
            ->assertSeeHtml('data-format="lima_bab" data-template="'.$id['narasumber'].'"')
            ->assertSee('Format 5 bab · Word: TPM narasumber')
            ->assertSee('Versi 2 — narasumber eksternal')
            ->assertSeeHtml('data-testid="template-word-pratinjau"')
            ->assertSee('dipilih untuk kegiatan ini');
    }

    public function test_endpoint_pilihan_template_kegiatan(): void
    {
        $id = $this->siapkan();
        $kegiatan = $this->kegiatanBaru();
        $alamat = route('kegiatan.template.pilihan', ['kegiatan' => $kegiatan]);

        $data = $this->getJson($alamat)->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJson(['kegiatan_id' => $kegiatan, 'tahun' => 2026, 'dapat_diubah' => true, 'alasan_terkunci' => null])
            ->assertJsonPath('template.dipilih', null)
            ->assertJsonPath('template.otomatis', ['id' => $id['tpm'], 'nama' => 'TPM reguler', 'asal' => 'default_leading', 'label_asal' => 'Default template leading'])
            ->assertJsonPath('berlaku.kak.id', $id['tpm'])
            ->assertJsonPath('format.dipilih', null)
            ->assertJsonPath('format.tahun', 'sembilan_bagian')
            ->json();
        $this->assertSame([$id['tpm'], $id['narasumber'], $id['umum']], array_column($data['template']['pilihan'], 'id'));
        $this->assertSame(['', 'sembilan_bagian', 'lima_bab'], array_column($data['format']['pilihan'], 'nilai'));

        // Ubah template & format sekaligus; versi dicek.
        $baru = $this->putJson($alamat, ['template' => $id['narasumber'], 'format' => 'lima_bab', 'versi' => $data['versi']])->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJson(['diubah' => ['format_dokumen', 'template_kak'], 'pesan' => 'Pilihan template tersimpan — TPM narasumber, format 5 bab.'])
            ->assertJsonPath('template.dipilih', $id['narasumber'])
            ->assertJsonPath('berlaku.kak.asal', 'kegiatan')
            ->assertJsonPath('berlaku.format.kunci', 'lima_bab')
            ->json();
        $this->assertNotSame($data['versi'], $baru['versi']);
        $this->putJson($alamat, ['template' => $id['umum'], 'versi' => $data['versi']])->assertStatus(409)->assertJsonPath('versi', $baru['versi']);
        $this->putJson($alamat, ['template' => $id['narasumber']])->assertOk()->assertJson(['diubah' => [], 'pesan' => 'Pilihan template tidak berubah.']);

        // Kosong = kembali otomatis / mengikuti tahun anggaran; kunci yang tidak dikirim tetap.
        $this->putJson($alamat, ['template' => ''])->assertOk()
            ->assertJson(['diubah' => ['template_kak']])
            ->assertJsonPath('berlaku.kak.asal', 'default_leading')
            ->assertJsonPath('format.dipilih', 'lima_bab');
        $this->putJson($alamat, ['format' => null])->assertOk()->assertJsonPath('format.dipilih', null);

        // Galat berkunci API: template di luar pilihan (leading lain / nonaktif), format tak dikenal, tanpa kiriman.
        $this->putJson($alamat, ['template' => $id['kurikulum']])->assertStatus(422)
            ->assertJsonPath('galat.template', 'Pilih template KAK/TOR milik leading kegiatan ini atau template umum yang aktif.');
        $this->putJson($alamat, ['template' => $id['lama']])->assertStatus(422)->assertJsonStructure(['pesan', 'galat' => ['template']]);
        $this->putJson($alamat, ['format' => 'tujuh_bab'])->assertStatus(422)->assertJsonStructure(['galat' => ['format']]);
        $this->putJson($alamat, ['versi' => 'x'])->assertStatus(422)->assertJsonStructure(['galat' => ['template']]);

        // Hak akses & keadaan kegiatan.
        $this->getJson(route('kegiatan.template.pilihan', ['kegiatan' => 999]))->assertNotFound();
        $this->putJson(route('kegiatan.template.pilihan', ['kegiatan' => 999]), ['template' => ''])->assertNotFound();
        $lain = $this->kegiatanBaru(['leading' => '1.2', 'user_id' => 4]);
        $this->getJson(route('kegiatan.template.pilihan', ['kegiatan' => $lain]))->assertForbidden();
        $this->putJson(route('kegiatan.template.pilihan', ['kegiatan' => $lain]), ['template' => ''])->assertForbidden();
        $diperiksa = $this->kegiatanBaru(['status' => 'diperiksa']);
        $this->getJson(route('kegiatan.template.pilihan', ['kegiatan' => $diperiksa]))
            ->assertJson(['dapat_diubah' => false, 'alasan_terkunci' => 'Kegiatan berstatus Diperiksa sehingga isiannya terkunci.']);
        $this->putJson(route('kegiatan.template.pilihan', ['kegiatan' => $diperiksa]), ['template' => $id['umum']])->assertForbidden()
            ->assertJsonPath('pesan', 'Kegiatan berstatus Diperiksa sehingga isiannya terkunci.');
    }

    public function test_default_leading_terisi_saat_kegiatan_dibuat(): void
    {
        $id = $this->siapkan();
        $repo = app(KegiatanRepository::class);

        // Default leading menjadi pilihan tersimpan; leading tanpa default / tanpa leading tetap kosong (otomatis).
        $this->assertSame($id['tpm'], PilihanTemplate::isiAwal(['nama_kegiatan' => 'Rapat Evaluasi Mutu', 'leading' => '1.6'])['template_kak']);
        $this->assertSame($id['kurikulum'], PilihanTemplate::isiAwal(['nama_kegiatan' => 'Rapat Evaluasi Mutu', 'leading' => '1.1'])['template_kak']);
        $this->assertNull(PilihanTemplate::isiAwal(['nama_kegiatan' => 'Rapat Evaluasi Mutu', 'leading' => '1.3'])['template_kak']);
        $this->assertNull(PilihanTemplate::isiAwal(['nama_kegiatan' => 'Rapat Evaluasi Mutu'])['template_kak']);
        // Pilihan bawaan yang aktif & sah dipertahankan; nonaktif / milik leading lain diganti default.
        $this->assertSame($id['narasumber'], PilihanTemplate::isiAwal(['leading' => '1.6', 'template_kak' => $id['narasumber']])['template_kak']);
        $this->assertSame($id['tpm'], PilihanTemplate::isiAwal(['leading' => '1.6', 'template_kak' => $id['lama']])['template_kak']);
        $this->assertSame($id['tpm'], PilihanTemplate::isiAwal(['leading' => '1.6', 'template_kak' => $id['kurikulum']])['template_kak']);

        // Aturan khusus leading × jenis Format Seragam tetap dinamis: tidak dibekukan saat dibuat.
        app(PilihanTemplateRepository::class)->simpan(2026, PilihanTemplate::rapikan(['jenis' => [], 'leading' => [['leading' => '1.6', 'jenis' => 'supervisi', 'kak' => $id['umum']]]]));
        $this->assertNull(PilihanTemplate::isiAwal(['nama_kegiatan' => 'Supervisi Akademik Guru', 'leading' => '1.6'])['template_kak']);
        $this->assertSame($id['tpm'], PilihanTemplate::isiAwal(['nama_kegiatan' => 'Workshop Kurikulum', 'leading' => '1.6'])['template_kak']);

        // Formulir Data Kegiatan: kegiatan baru menyimpan default leading; mengganti default kemudian tidak mengubahnya.
        $sebelum = array_column($repo->semua(), 'id');
        Livewire::test(Form::class)
            ->set('form.nama_kegiatan', 'Workshop Penguatan Mutu')
            ->set('form.leading', '1.6')
            ->call('saveDraft')
            ->assertHasNoErrors();
        $baru = $repo->temukan((int) collect(array_column($repo->semua(), 'id'))->diff($sebelum)->first());
        $this->assertSame($id['tpm'], $baru['template_kak']);
        app(TemplateDokumenRepository::class)->jadikanDefault($id['narasumber']);
        $this->assertSame(['asal' => 'kegiatan', 'kak' => $id['tpm']], array_intersect_key(PilihanTemplate::untukKegiatan($repo->temukan($baru['id'])), ['asal' => 1, 'kak' => 1]));

        // Duplikat di tahun yang sama membawa pilihan sumbernya; dari tahun lain memakai default leading tahun aktif.
        $sumber = $repo->temukan($this->kegiatanBaru(['nama_kegiatan' => 'Workshop Narasumber', 'template_kak' => $id['umum']]));
        $salin = DuplikatKegiatan::jalankan($sumber, self::LEADING, 'Disalin.');
        $this->assertSame($id['umum'], $repo->temukan($salin['id'])['template_kak']);
        $lalu = $repo->temukan($this->kegiatanBaru(['nama_kegiatan' => 'Workshop Lama', 'tahun_anggaran' => 2025, 'template_kak' => 'template-2025']));
        $salin = DuplikatKegiatan::jalankan($lalu, self::LEADING);
        $this->assertSame($id['narasumber'], $repo->temukan($salin['id'])['template_kak']);
    }
}
