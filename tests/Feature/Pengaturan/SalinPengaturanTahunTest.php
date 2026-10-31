<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\DaftarIsiRepository;
use App\Contracts\DasarHukumRepository;
use App\Contracts\NomorHalamanRepository;
use App\Contracts\PejabatRepository;
use App\Contracts\PenomoranDokumenRepository;
use App\Contracts\PilihanTemplateRepository;
use App\Contracts\RiwayatPengaturanRepository;
use App\Contracts\TahunAnggaranRepository;
use App\Contracts\TemplateCoverRepository;
use App\Contracts\TemplateDokumenRepository;
use App\Livewire\Pengaturan\TahunAnggaran;
use App\Support\Dokumen\DaftarIsi;
use App\Support\Dokumen\NomorHalaman;
use App\Support\Dokumen\PilihanTemplate;
use App\Support\Dokumen\TeksCover;
use App\Support\MasterKode;
use App\Support\Pengaturan\SalinPengaturanTahun;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\Docx;
use Tests\TestCase;

class SalinPengaturanTahunTest extends TestCase
{
    private const ADMIN = ['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin', 'leadings' => []];

    private const PEJABAT_KEPALA = ['jabatan' => 'Kepala MTsN 2 Kota Malang', 'nama' => 'Drs. H. Mahmud Arifin, M.Pd.I.', 'nip' => '196805121994031002'];

    protected function setUp(): void
    {
        parent::setUp();
        app(TahunAnggaranRepository::class)->tambah(2027);
    }

    /**
     * Isi TA 2026 dengan semua bagian pengaturan; mengembalikan id cover & master TA 2026.
     *
     * @return array{cover: string, master: string}
     */
    private function isiSumber(): array
    {
        $cover = app(TemplateCoverRepository::class)->tambah(2026, 'Cover biru', UploadedFile::fake()->image('cover.png', 1240, 1754), TeksCover::bawaan());
        $master = app(TemplateDokumenRepository::class)->tambah(2026, 'Master reguler', 'KAK+TOR', UploadedFile::fake()->createWithContent('master.docx', Docx::isi(['Kegiatan ${nama_kegiatan}'])), ['nama_kegiatan']);
        app(PejabatRepository::class)->simpan(2026, 'kepala', null, self::PEJABAT_KEPALA);
        app(PejabatRepository::class)->simpanAturanMengetahui(2026, ['DIPA' => true, 'Komite' => false]);
        app(DaftarIsiRepository::class)->simpanBawaan(2026, DaftarIsi::rapikan(['judul' => 'DAFTAR KEGIATAN 2026'] + DaftarIsi::BAWAAN));
        app(DaftarIsiRepository::class)->simpanKhusus(2026, '1.6', 'komite', DaftarIsi::rapikan(['judul' => 'KEGIATAN TPM'] + DaftarIsi::BAWAAN));
        app(NomorHalamanRepository::class)->simpan(2026, NomorHalaman::rapikan(['format' => 'Hal. {x}'] + NomorHalaman::BAWAAN));
        app(PilihanTemplateRepository::class)->simpan(2026, PilihanTemplate::rapikan([
            'jenis' => ['workshop' => ['kak' => $master, 'cover' => $cover]],
            'leading' => [['leading' => '1.6', 'jenis' => 'lomba', 'kak' => null, 'cover' => $cover]],
        ]));
        app(PenomoranDokumenRepository::class)->simpanAturan(2026, ['RAB' => ['pola' => 'RAB/{urut}/{tahun}', 'digit' => 4, 'aktif' => true]]);

        return ['cover' => $cover, 'master' => $master];
    }

    /** @return array<string, string> kunci bagian → status */
    private function keadaan(array $rencanaAtauHasil, string $kunci = 'bagian'): array
    {
        return array_column($rencanaAtauHasil[$kunci], 'status', 'kunci');
    }

    public function test_salin_semua_bagian_ke_tahun_kosong(): void
    {
        $sumber = $this->isiSumber();
        $this->assertSame(2026, SalinPengaturanTahun::sumber(2027), 'Tahun berisi terdekat sebelum tujuan.');

        $rencana = SalinPengaturanTahun::rencana(2026, 2027);
        $this->assertSame(array_fill_keys(array_keys(SalinPengaturanTahun::BAGIAN), 'salin'), $this->keadaan($rencana));
        $this->assertSame([count(SalinPengaturanTahun::BAGIAN), '1 cover', null], [$rencana['jumlah_bisa'], $rencana['bagian'][3]['sumber'], $rencana['bagian'][3]['tujuan']]);

        $hasil = SalinPengaturanTahun::salin(2026, 2027, null, self::ADMIN);
        $this->assertSame(array_fill_keys(array_keys(SalinPengaturanTahun::BAGIAN), 'disalin'), $this->keadaan($hasil, 'hasil'));
        $this->assertSame([9, '9 bagian pengaturan TA 2026 disalin ke TA 2027.'], [$hasil['disalin'], $hasil['pesan']]);

        // Master kode & pejabat.
        $this->assertSame([MasterKode::kodeSk(2026), MasterKode::kodeRktm(2026), MasterKode::budayaMutu(2026)], [MasterKode::kodeSk(2027), MasterKode::kodeRktm(2027), MasterKode::budayaMutu(2027)]);
        $pejabat = app(PejabatRepository::class);
        $this->assertSame(self::PEJABAT_KEPALA, $pejabat->untukTahun(2027)['kepala']);
        $this->assertSame(['DIPA' => true, 'Komite' => false], $pejabat->aturanMengetahui(2027));

        // Dasar hukum: teks mentah sama (penanda {tahun} tetap), id butir baru milik tahun tujuan.
        $dhLama = app(DasarHukumRepository::class)->untukTahun(2026);
        $dhBaru = app(DasarHukumRepository::class)->untukTahun(2027);
        $this->assertTrue($dhBaru['diatur']);
        $this->assertSame(array_column($dhLama['dasar_hukum'], 'teks'), array_column($dhBaru['dasar_hukum'], 'teks'));
        $this->assertSame([$dhLama['latar_belakang'], $dhLama['kalimat_penutup']], [$dhBaru['latar_belakang'], $dhBaru['kalimat_penutup']]);
        $this->assertEmpty(array_intersect(array_column($dhLama['dasar_hukum'], 'id'), array_column($dhBaru['dasar_hukum'], 'id')));

        // Template: berkas ikut tersalin, default tetap default, pilihan template menunjuk template TA 2027.
        $cover = app(TemplateCoverRepository::class)->semua(2027);
        $master = app(TemplateDokumenRepository::class)->semua(2027);
        $this->assertSame([['Cover biru', true, app(TemplateCoverRepository::class)->semua(2026)[0]['teks_items']], ['Master reguler', 'KAK+TOR', true, ['nama_kegiatan']]],
            [[$cover[0]['nama'], $cover[0]['is_default'], $cover[0]['teks_items']], [$master[0]['nama'], $master[0]['jenis'], $master[0]['is_default'], $master[0]['variabel']]]);
        $this->assertNotSame([$sumber['cover'], $sumber['master']], [$cover[0]['id'], $master[0]['id']]);
        Storage::disk('local')->assertExists([$cover[0]['gambar_path'], $master[0]['file_path']]);
        $pilihan = app(PilihanTemplateRepository::class)->untuk(2027);
        $this->assertSame(['kak' => $master[0]['id'], 'cover' => $cover[0]['id']], $pilihan['jenis']['workshop']);
        $this->assertSame([['leading' => '1.6', 'jenis' => 'lomba', 'kak' => null, 'cover' => $cover[0]['id']]], $pilihan['leading']);

        // Daftar isi, nomor halaman, penomoran (nomor urut tidak ikut).
        $this->assertSame(app(DaftarIsiRepository::class)->bawaan(2026), app(DaftarIsiRepository::class)->bawaan(2027));
        $this->assertSame(app(DaftarIsiRepository::class)->khusus(2026), app(DaftarIsiRepository::class)->khusus(2027));
        $this->assertSame('Hal. {x}', app(NomorHalamanRepository::class)->untuk(2027)['format']);
        $this->assertSame(['RAB' => ['pola' => 'RAB/{urut}/{tahun}', 'digit' => 4, 'aktif' => true]], app(PenomoranDokumenRepository::class)->aturan(2027));
        $this->assertSame([], app(PenomoranDokumenRepository::class)->terakhir(2027));

        $riwayat = collect(app(RiwayatPengaturanRepository::class)->semua())->firstWhere('aksi', 'salin_pengaturan_tahun');
        $this->assertStringStartsWith('Pengaturan TA 2026 disalin ke TA 2027: Master kode (SK ', $riwayat['keterangan']);
        $this->assertStringContainsString('Template cover (1 cover); Master KAK/TOR (1 master)', $riwayat['keterangan']);

        // Menyalin ulang tidak menimpa apa pun.
        $ulang = SalinPengaturanTahun::salin(2026, 2027, null, self::ADMIN);
        $this->assertSame(array_fill_keys(array_keys(SalinPengaturanTahun::BAGIAN), 'dilewati'), $this->keadaan($ulang, 'hasil'));
        $this->assertSame([0, 'Tidak ada yang disalin — pengaturan TA 2027 yang dipilih sudah terisi atau tidak ada di TA 2026.'], [$ulang['disalin'], $ulang['pesan']]);
        $this->assertCount(1, app(TemplateCoverRepository::class)->semua(2027));
        $this->assertSame(0, SalinPengaturanTahun::rencana(2026, 2027)['jumlah_bisa']);
    }

    public function test_hanya_bagian_terpilih_dan_isian_tujuan_tidak_tertimpa(): void
    {
        $this->isiSumber();
        app(PejabatRepository::class)->simpan(2026, 'komite', null, ['jabatan' => 'Ketua Komite', 'nama' => 'H. Sugeng Riyadi, S.E.', 'nip' => null]);
        app(PejabatRepository::class)->simpan(2027, 'komite', null, ['jabatan' => 'Ketua Komite', 'nama' => 'Hj. Komite Baru', 'nip' => null]);
        app(DasarHukumRepository::class)->simpan(2027, ['dasar_hukum' => [['teks' => 'Peraturan khusus {tahun}']], 'latar_belakang' => 'Paragraf {tahun}', 'kalimat_penutup' => 'Kegiatan {nama_kegiatan} dilaksanakan']);

        $rencana = $this->keadaan(SalinPengaturanTahun::rencana(2026, 2027));
        $this->assertSame(['sebagian', 'terisi'], [$rencana['pejabat'], $rencana['dasar_hukum']]);

        $hasil = $this->keadaan(SalinPengaturanTahun::salin(2026, 2027, ['pejabat', 'dasar_hukum', 'cover'], self::ADMIN), 'hasil');
        $this->assertSame(['disalin', 'dilewati', 'disalin', 'dilewati'], [$hasil['pejabat'], $hasil['dasar_hukum'], $hasil['cover'], $hasil['master_kak']]);
        $pejabat = app(PejabatRepository::class)->untukTahun(2027);
        $this->assertSame(['Drs. H. Mahmud Arifin, M.Pd.I.', 'Hj. Komite Baru'], [$pejabat['kepala']['nama'], $pejabat['komite']['nama']]);
        $this->assertSame(['Peraturan khusus {tahun}'], array_column(app(DasarHukumRepository::class)->untukTahun(2027)['dasar_hukum'], 'teks'));
        $this->assertSame([], app(TemplateDokumenRepository::class)->semua(2027));
        $this->assertFalse(MasterKode::tersedia(2027));

        // Pilihan template tanpa master/cover bernama sama di tujuan: dicocokkan seadanya (cover sudah tersalin).
        $pilihan = SalinPengaturanTahun::salin(2026, 2027, ['pilihan_template'], self::ADMIN)['hasil'][7];
        $this->assertSame(['pilihan_template', 'disalin', 2], [$pilihan['kunci'], $pilihan['status'], $pilihan['jumlah']]);
        $this->assertNull(app(PilihanTemplateRepository::class)->untuk(2027)['jenis']['workshop']['kak']);
    }

    public function test_pilihan_template_tanpa_template_tujuan_dilewati(): void
    {
        $this->isiSumber();
        $hasil = SalinPengaturanTahun::salin(2026, 2027, ['pilihan_template'], self::ADMIN);
        $this->assertSame(['dilewati', 'Template bernama sama belum ada di TA 2027 — salin template cover/master KAK/TOR dulu.'], [$hasil['hasil'][7]['status'], $hasil['hasil'][7]['pesan']]);
        $this->assertSame([], app(PilihanTemplateRepository::class)->untuk(2027)['leading']);
    }

    public function test_berkas_template_hilang_dilaporkan(): void
    {
        $this->isiSumber();
        Storage::disk('local')->delete(app(TemplateCoverRepository::class)->semua(2026)[0]['gambar_path']);

        $hasil = SalinPengaturanTahun::salin(2026, 2027, ['cover'], self::ADMIN)['hasil'][3];
        $this->assertSame(['dilewati', '0 cover; berkas tidak ditemukan: Cover biru'], [$hasil['status'], $hasil['pesan']]);
    }

    public function test_izin_dan_validasi(): void
    {
        $this->isiSumber();
        $galat = function (callable $aksi, string $kunci): string {
            try {
                $aksi();
            } catch (ValidationException $e) {
                return $e->errors()[$kunci][0];
            }
            $this->fail('Seharusnya ditolak.');
        };

        $this->assertSame('Pilih tahun anggaran lain sebagai sumber salinan.', $galat(fn () => SalinPengaturanTahun::salin(2027, 2027, null, self::ADMIN), 'dari'));
        $this->assertSame('Pilih tahun anggaran lain sebagai sumber salinan.', $galat(fn () => SalinPengaturanTahun::rencana(2019, 2027), 'dari'));
        $this->assertSame('Tahun Anggaran 2030 belum dibuat.', $galat(fn () => SalinPengaturanTahun::rencana(2026, 2030), 'tahun'));
        $this->assertSame('Bagian tidak dikenal: logo.', $galat(fn () => SalinPengaturanTahun::salin(2026, 2027, ['logo'], self::ADMIN), 'bagian'));

        try {
            SalinPengaturanTahun::salin(2026, 2027, null, ['role' => 'leading']);
            $this->fail('Akun leading tidak boleh menyalin.');
        } catch (AuthorizationException $e) {
            $this->assertSame('Pengaturan tahun anggaran diatur admin.', $e->getMessage());
        }
        try {
            SalinPengaturanTahun::salin(2026, 2025, null, self::ADMIN);
            $this->fail('Tahun terkunci tidak boleh diisi.');
        } catch (AuthorizationException $e) {
            $this->assertStringStartsWith('Tahun Anggaran 2025 terkunci', $e->getMessage());
        }
        $this->assertSame([], SalinPengaturanTahun::rencana(2026, 2027)['terkunci'] ? ['terkunci'] : []);
    }

    public function test_endpoint_rencana_dan_salin(): void
    {
        $this->isiSumber();
        $this->withSession(['stub_role' => 'admin']);

        $this->getJson(route('pengaturan.tahun.salin.rencana', ['tahun' => 2027]))
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('dari', 2026)->assertJsonPath('ke', 2027)->assertJsonPath('sumber_disarankan', 2026)
            ->assertJsonPath('bagian.0.kunci', 'master_kode')->assertJsonPath('bagian.0.status', 'salin');
        $this->getJson(route('pengaturan.tahun.salin.rencana', ['tahun' => 2030]))->assertNotFound();
        $this->getJson(route('pengaturan.tahun.salin.rencana', ['tahun' => 2027, 'dari' => 2027]))->assertStatus(422)->assertJsonPath('galat.dari.0', 'Pilih tahun anggaran lain sebagai sumber salinan.');

        $this->postJson(route('pengaturan.tahun.salin', ['tahun' => 2027]), ['dari' => 2026, 'bagian' => ['dasar_hukum', 'nomor_halaman']])
            ->assertCreated()->assertJsonPath('disalin', 2)->assertJsonPath('hasil.2.status', 'disalin')->assertJsonPath('hasil.0.pesan', 'Tidak dipilih.');
        $this->postJson(route('pengaturan.tahun.salin', ['tahun' => 2027]), ['bagian' => ['dasar_hukum']])->assertOk()->assertJsonPath('disalin', 0);
        $this->postJson(route('pengaturan.tahun.salin', ['tahun' => 2027]), ['bagian' => 'semua'])->assertStatus(422)->assertJsonPath('pesan', 'Bagian berupa daftar.');
        $this->postJson(route('pengaturan.tahun.salin', ['tahun' => 2027]), ['bagian' => ['logo']])->assertStatus(422)->assertJsonPath('galat.bagian.0', 'Bagian tidak dikenal: logo.');
        $this->postJson(route('pengaturan.tahun.salin', ['tahun' => 2025]), ['dari' => 2026])->assertForbidden();
        $this->postJson(route('pengaturan.tahun.salin', ['tahun' => 2030]))->assertNotFound();

        $this->withSession(['stub_role' => 'leading'])->postJson(route('pengaturan.tahun.salin', ['tahun' => 2027]))->assertForbidden();
    }

    public function test_dialog_salin_di_halaman_tahun_anggaran(): void
    {
        $this->isiSumber();
        $this->withSession(['stub_role' => 'admin']);

        Livewire::test(TahunAnggaran::class)
            ->assertSeeHtml('data-testid="buka-salin-2027"')
            ->assertDontSeeHtml('data-testid="buka-salin-2025"')
            ->call('bukaSalin', 2025)
            ->assertSet('salinKe', null)
            ->call('bukaSalin', 2027)
            ->assertSet('salinKe', 2027)
            ->assertSet('salinDari', 2026)
            ->assertSet('salinBagian', array_keys(SalinPengaturanTahun::BAGIAN))
            ->assertSeeHtml('data-testid="dialog-salin-pengaturan"')
            ->assertSeeHtml('data-kunci="pejabat" data-status="salin"')
            ->set('salinBagian', [])
            ->call('salinPengaturan')
            ->assertDispatched('notify', message: 'Centang minimal satu bagian yang akan disalin.')
            ->set('salinBagian', ['dasar_hukum', 'penomoran'])
            ->call('salinPengaturan')
            ->assertDispatched('notify', message: '2 bagian pengaturan TA 2026 disalin ke TA 2027.', type: 'success')
            ->assertSeeHtml('data-testid="hasil-salin"')
            ->assertSeeHtml('data-kunci="dasar_hukum" data-status="disalin"')
            ->assertSeeHtml('data-kunci="dasar_hukum" data-status="terisi"')
            ->assertSet('salinBagian', ['master_kode', 'pejabat', 'cover', 'master_kak', 'daftar_isi', 'nomor_halaman', 'pilihan_template'])
            ->set('salinDari', '2027')
            ->assertSet('salinDari', null)
            ->assertSet('salinBagian', [])
            ->set('salinDari', '2025')
            ->assertSet('salinDari', 2025)
            ->call('tutupSalin')
            ->assertSet('salinKe', null)
            ->assertDontSeeHtml('data-testid="dialog-salin-pengaturan"');

        $this->assertTrue(app(DasarHukumRepository::class)->untukTahun(2027)['diatur']);
    }

    public function test_tambah_tahun_menyarankan_salin(): void
    {
        $this->withSession(['stub_role' => 'admin']);

        Livewire::test(TahunAnggaran::class)
            ->set('tahunBaru', '2028')
            ->call('tambahTahun')
            ->assertDispatched('notify', message: 'Tahun Anggaran 2028 ditambahkan. Salin master & pengaturan TA 2026 lewat tombol Salin pengaturan, lalu aktifkan bila sudah siap.');
    }
}
