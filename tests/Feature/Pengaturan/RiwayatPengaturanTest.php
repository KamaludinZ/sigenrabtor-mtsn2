<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\RiwayatPengaturanRepository;
use App\Contracts\TemplateDokumenRepository;
use App\Livewire\Pengaturan\Ikhtisar;
use App\Livewire\Pengaturan\Riwayat;
use App\Livewire\Pengaturan\TahunAnggaran;
use App\Livewire\Pengaturan\TemplateDaftarIsi;
use App\Livewire\Pengaturan\TemplateDokumen;
use App\Livewire\Pengaturan\TemplateNomorHalaman;
use App\Support\Dokumen\TeksCover;
use App\Support\Pengaturan\RiwayatPengaturan;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\Support\Docx;
use Tests\TestCase;

class RiwayatPengaturanTest extends TestCase
{
    /** @return list<array<string, mixed>> */
    private function riwayat(): array
    {
        return app(RiwayatPengaturanRepository::class)->semua();
    }

    public function test_ringkasan_perubahan(): void
    {
        $this->assertSame(
            'Format: Halaman {x} dari {y} → - {x} -; Sampul tanpa nomor: ya → tidak; Kolom: No, Nama → No',
            RiwayatPengaturan::perubahan(
                ['format' => 'Halaman {x} dari {y}', 'sampul' => true, 'kolom' => ['No', 'Nama'], 'catatan' => ''],
                ['format' => '- {x} -', 'sampul' => false, 'kolom' => ['No'], 'catatan' => ''],
                ['format' => 'Format', 'sampul' => 'Sampul tanpa nomor', 'kolom' => 'Kolom', 'catatan' => 'Catatan'],
            ),
        );
        $this->assertNull(RiwayatPengaturan::perubahan(['a' => 1], ['a' => 1], ['a' => 'A']));
    }

    public function test_contoh_awal_dan_catat_atas_nama_pengguna(): void
    {
        $this->assertSame(['contoh-2', 'contoh-1'], array_column($this->riwayat(), 'id'), 'Terbaru lebih dulu.');

        session(['stub_role' => 'admin']);
        $this->travelTo(now()->setDate(2026, 10, 26)->setTime(13, 5));
        RiwayatPengaturan::catat('ubah_nomor_halaman', 'Aturan nomor halaman diubah.');

        $terbaru = $this->riwayat()[0];
        $this->assertSame(['ubah_nomor_halaman', 'Aturan nomor halaman diubah.', 'Koordinator PUSKOM', 2, 2026], [$terbaru['aksi'], $terbaru['keterangan'], $terbaru['pengguna'], $terbaru['user_id'], $terbaru['tahun']]);
        $this->assertCount(3, $this->riwayat());
    }

    public function test_setiap_aksi_pengaturan_tercatat(): void
    {
        session(['stub_role' => 'admin']);

        Livewire::test(TahunAnggaran::class)->call('pilih', 2025)->call('aktifkan');
        $this->assertSame('Tahun Anggaran 2025 diaktifkan (sebelumnya 2026).', $this->riwayat()[0]['keterangan']);
        Livewire::test(TahunAnggaran::class)->call('pilih', 2026)->call('aktifkan');

        $cover = Livewire::test(TemplateDokumen::class)
            ->set('gambar', UploadedFile::fake()->image('c.png', 1240, 1754))
            ->call('simpanCover');
        $this->assertSame('Cover “Cover default 2026” diunggah (1240 × 1754 piksel), menjadi cover default.', $this->riwayat()[0]['keterangan']);

        $id = $cover->get('coverDipilih');
        $kotak = TeksCover::bawaan();
        $kotak[1]['y'] = 30;
        unset($kotak[4]);
        $cover->call('aturKotak', $id)->set('kotak', array_values($kotak))->call('simpanTataLetak');
        $this->assertSame(['ubah_tata_letak_cover', 'Tata letak “Cover default 2026” diubah — 1 kotak diubah, disembunyikan: “Disusun oleh”.'], [$this->riwayat()[0]['aksi'], $this->riwayat()[0]['keterangan']]);

        $cover->call('mintaHapus', $id)->call('hapusCover');
        $this->assertSame('Cover “Cover default 2026” dihapus.', $this->riwayat()[0]['keterangan']);

        $master = Livewire::test(TemplateDokumen::class)->set('tab', 'kak')
            ->set('berkasMaster', UploadedFile::fake()->createWithContent('m.docx', Docx::isi(['${nama_kegiatan} ${total}'])))
            ->call('simpanMaster');
        $this->assertSame('Master “Master KAK/TOR 2026” (KAK+TOR) diunggah — 2 variabel, menjadi default jenis ini.', $this->riwayat()[0]['keterangan']);
        $idMaster = app(TemplateDokumenRepository::class)->semua(2026)[0]['id'];
        $master->call('mintaHapusMaster', $idMaster)->call('hapusMaster');
        $this->assertSame('hapus_master', $this->riwayat()[0]['aksi']);

        Livewire::test(TemplateDaftarIsi::class)->set('konfigurasi.judul', 'DAFTAR ISI')->set('konfigurasi.kolom', ['kode_rktm'])->call('simpan');
        $this->assertSame('Pengaturan bawaan daftar isi diubah — Judul: DAFTAR KEGIATAN → DAFTAR ISI; Kolom: No, Kode RKTM, Nama Kegiatan, Jumlah Anggaran, Halaman → No, Kode RKTM, Nama Kegiatan, Halaman; Baris jumlah: ya → tidak.', $this->riwayat()[0]['keterangan']);
        Livewire::test(TemplateDaftarIsi::class)->call('pilih', '1.6', 'DIPA')->call('simpan')->call('kembalikanBawaan');
        $this->assertSame(['hapus_daftar_isi_khusus', 'ubah_daftar_isi'], array_column(array_slice($this->riwayat(), 0, 2), 'aksi'));
        $this->assertSame('Pengaturan khusus daftar isi Tim Penjamin Mutu · DIPA disimpan (sama dengan bawaan).', $this->riwayat()[1]['keterangan']);

        Livewire::test(TemplateNomorHalaman::class)->set('pilihanFormat', '{x}')->set('aturan.posisi', 'bawah_kanan')->call('simpan');
        $this->assertSame('Aturan nomor halaman diubah — Format: Halaman {x} dari {y} → {x}; Posisi: Bawah tengah → Bawah kanan.', $this->riwayat()[0]['keterangan']);
    }

    public function test_halaman_riwayat_dengan_saringan(): void
    {
        $this->get(route('pengaturan.riwayat.index'))->assertForbidden();
        $this->withSession(['stub_role' => 'admin'])->get(route('pengaturan.riwayat.index'))->assertOk()->assertSeeLivewire(Riwayat::class)
            ->assertSeeHtml('href="'.route('pengaturan.riwayat.index').'"');

        session(['stub_role' => 'admin']);
        RiwayatPengaturan::catat('ubah_nomor_halaman', 'Aturan nomor halaman diubah — Format: A → B.');

        Livewire::test(Riwayat::class)
            ->assertSee('3 perubahan')
            ->assertSeeHtmlInOrder(['data-riwayat="ubah_nomor_halaman"', 'Ubah nomor halaman', 'Template Dokumen', 'Format: A → B.', 'data-riwayat="ganti_tahun"', 'Tahun Anggaran 2026 diaktifkan (sebelumnya 2025); 2025 dikunci.'])
            ->set('bagian', 'tahun')
            ->assertSee('2 perubahan cocok')
            ->assertDontSeeHtml('data-riwayat="ubah_nomor_halaman"')
            ->set('bagian', '')
            ->set('cari', 'nomor')
            ->assertSee('1 perubahan cocok')
            ->set('cari', 'tidak ada apa-apa')
            ->assertSeeHtml('data-testid="riwayat-kosong"')
            ->assertSee('Tidak ada perubahan yang cocok')
            ->call('hapusSaringan')
            ->assertSee('3 perubahan')
            ->assertSet('cari', '');

        Livewire::test(Ikhtisar::class)
            ->assertSeeHtml('data-testid="perubahan-terakhir"')
            ->assertSeeInOrder(['Perubahan terakhir', 'Ubah nomor halaman', 'Ganti tahun aktif']);
    }
}
