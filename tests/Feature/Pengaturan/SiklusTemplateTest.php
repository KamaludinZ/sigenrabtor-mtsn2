<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\KegiatanRepository;
use App\Livewire\Pengaturan\FormatSeragam;
use App\Support\Dokumen\DaftarTemplateMaster;
use App\Support\Dokumen\DetailTemplate;
use App\Support\Dokumen\PilihanTemplate;
use App\Support\Pengaturan\KelolaTemplateMaster;
use App\Support\Pengaturan\UnggahMasterTemplate;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\Docx;
use Tests\TestCase;

class SiklusTemplateTest extends TestCase
{
    private const ADMIN = ['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin', 'leadings' => []];

    protected function setUp(): void
    {
        parent::setUp();
        session(['stub_role' => 'admin']);
    }

    private function master(string $nama, ?string $leading = null): string
    {
        $berkas = UploadedFile::fake()->createWithContent("{$nama}.docx", Docx::isi(['Kegiatan ${nama_kegiatan}']));

        return (string) UnggahMasterTemplate::simpan(2026, $berkas, $nama, 'KAK+TOR', self::ADMIN, ['leading' => $leading])['id'];
    }

    /** @return array{asal: string, kak: string|null} */
    private function berlaku(int $id): array
    {
        return array_intersect_key(PilihanTemplate::untukKegiatan(app(KegiatanRepository::class)->temukan($id), 2026), ['asal' => 1, 'kak' => 1]);
    }

    public function test_template_nonaktif_dilewati_aturan_otomatis_tetapi_tetap_untuk_yang_memilihnya(): void
    {
        $umum = $this->master('Umum reguler');
        $workshop = $this->master('Master Workshop');
        $tpm = $this->master('TPM reguler', '1.6');
        PilihanTemplate::simpan(2026, ['jenis' => ['workshop' => ['kak' => $workshop]], 'leading' => [['leading' => '1.3', 'jenis' => 'workshop', 'kak' => $workshop]]], self::ADMIN);

        $repo = app(KegiatanRepository::class);
        $lainnya = $repo->simpan(['nama_kegiatan' => 'Workshop Kurikulum', 'leading' => '1.1', 'user_id' => 2, 'tahun_anggaran' => 2026]);
        $khusus = $repo->simpan(['nama_kegiatan' => 'Workshop Kurikulum Lanjutan', 'leading' => '1.3', 'user_id' => 2, 'tahun_anggaran' => 2026]);
        $tpmKegiatan = $repo->simpan(['nama_kegiatan' => 'Workshop Penguatan Mutu', 'leading' => '1.6', 'user_id' => 1, 'tahun_anggaran' => 2026]);
        $memilih = $repo->simpan(['nama_kegiatan' => 'Workshop Asesmen', 'leading' => '1.1', 'user_id' => 2, 'tahun_anggaran' => 2026, 'template_kak' => $workshop]);
        $this->assertSame(['asal' => 'jenis', 'kak' => $workshop], $this->berlaku($lainnya));
        $this->assertSame(['asal' => 'leading', 'kak' => $workshop], $this->berlaku($khusus));
        $this->assertSame(['asal' => 'default_leading', 'kak' => $tpm], $this->berlaku($tpmKegiatan));

        // Peringatan sebelum menonaktifkan: kegiatan lewat aturan ikut beralih.
        $dampak = KelolaTemplateMaster::dampak($workshop);
        $this->assertEqualsCanonicalizing([$lainnya, $khusus], array_column($dampak['aturan'], 'id'));
        $this->assertSame([$memilih], array_column($dampak['dipilih'], 'id'));

        KelolaTemplateMaster::ubahStatus($workshop, false, self::ADMIN);
        // Aturan per jenis & khusus leading yang menunjuknya dilewati → aturan berikutnya (bawaan), pilihan kegiatan tetap.
        $this->assertSame(['asal' => 'bawaan', 'kak' => null], $this->berlaku($lainnya));
        $this->assertSame(['asal' => 'bawaan', 'kak' => null], $this->berlaku($khusus));
        $this->assertSame(['asal' => 'kegiatan', 'kak' => $workshop], $this->berlaku($memilih));
        $data = PilihanTemplate::data(2026);
        $this->assertTrue(collect($data['jenis'])->firstWhere('kunci', 'workshop')['kak_nonaktif']);
        $this->assertTrue($data['leading'][0]['kak_nonaktif']);
        $this->assertFalse(collect($data['jenis'])->firstWhere('kunci', 'lomba')['kak_nonaktif']);

        // Aturan lama boleh tetap tersimpan; menunjuk template nonaktif untuk aturan baru ditolak.
        $isian = ['jenis' => ['workshop' => ['kak' => $workshop], 'lomba' => ['kak' => $umum]], 'leading' => [['leading' => '1.3', 'jenis' => 'workshop', 'kak' => $workshop]]];
        $this->assertNotNull(PilihanTemplate::simpan(2026, $isian, self::ADMIN)['ringkasan']);
        foreach ([
            'jenis.siswa.kak' => ['jenis' => ['siswa' => ['kak' => $workshop]] + $isian['jenis'], 'leading' => $isian['leading']],
            'leading.1.kak' => ['jenis' => $isian['jenis'], 'leading' => [...$isian['leading'], ['leading' => '1.1', 'jenis' => 'lomba', 'kak' => $workshop]]],
        ] as $kunci => $salah) {
            try {
                PilihanTemplate::simpan(2026, $salah, self::ADMIN);
                $this->fail("Template nonaktif tidak bisa dipilih baru: {$kunci}");
            } catch (ValidationException $e) {
                $this->assertSame('Master KAK/TOR ini nonaktif — aktifkan dulu di Template Master atau pilih yang lain.', $e->errors()[$kunci][0]);
            }
        }

        // Status & aksi siklus hidup per baris daftar; detail tidak lagi bisa dipilih.
        $baris = collect(DaftarTemplateMaster::semua(2026))->keyBy('id');
        $this->assertSame(['nonaktif', 'Nonaktif', ['ubah', 'aktifkan']], [$baris[$workshop]['siklus']['status'], $baris[$workshop]['siklus']['label'], $baris[$workshop]['siklus']['aksi']]);
        $this->assertSame(['ubah', 'nonaktifkan', 'hapus'], $baris[$umum]['siklus']['aksi'], 'Default aktif tanpa pemakaian.');
        $this->assertSame([false, false], [DetailTemplate::untuk($workshop)['aktif'], DetailTemplate::untuk($workshop)['bisa_dipilih']]);

        // Tab pilihan template Format Seragam: opsi nonaktif tidak bisa dipilih baru, aturan yang menunjuknya diberi penanda.
        Livewire::test(FormatSeragam::class, ['tab' => 'template'])
            ->assertSeeHtml('data-testid="pilih-kak-workshop-nonaktif"')
            ->assertSeeHtml('<option value="'.$workshop.'" disabled>Master Workshop · KAK+TOR (nonaktif)</option>')
            ->assertSee('Template ini nonaktif — aturan ini dilewati sampai diaktifkan kembali atau diganti.');

        // Diaktifkan kembali → aturan berlaku lagi.
        KelolaTemplateMaster::ubahStatus($workshop, true, self::ADMIN);
        $this->assertSame(['asal' => 'jenis', 'kak' => $workshop], $this->berlaku($lainnya));
        $this->assertSame(['asal' => 'leading', 'kak' => $workshop], $this->berlaku($khusus));
        $this->assertSame(['ubah', 'jadikan_default', 'nonaktifkan'], collect(DaftarTemplateMaster::semua(2026))->firstWhere('id', $workshop)['siklus']['aksi'], 'Dipilih kegiatan: tidak bisa dihapus.');
    }
}
