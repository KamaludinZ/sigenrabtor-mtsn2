<?php

namespace Tests\Feature\RiwayatDokumen;

use App\Contracts\RiwayatDokumenRepository;
use App\Contracts\TahunAnggaranRepository;
use App\Livewire\Dokumen\Riwayat;
use App\Support\Dokumen\PenelusuranRiwayat;
use App\Support\Dokumen\RiwayatDokumen;
use App\Support\Dokumen\RiwayatKosong;
use App\Support\Navigation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class HalamanRiwayatDokumenTest extends TestCase
{
    private const LEADING = ['id' => 1, 'name' => 'Muslimin, M.Pd.', 'role' => 'leading', 'leadings' => ['1.6']];

    private const ADMIN = ['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin', 'leadings' => []];

    public function test_cakupan_akun_kelompok_tahun_dan_ringkasan(): void
    {
        $admin = RiwayatDokumen::untuk(self::ADMIN);
        $leading = RiwayatDokumen::untuk(self::LEADING);
        $this->assertCount(12, $admin);
        $this->assertSame(['rd-2025-4', 'rd-2025-5'], array_values(array_intersect(['rd-2025-4', 'rd-2025-5'], array_column($leading, 'id'))), 'Dokumen leading 1.6 tahun lalu ikut.');
        $this->assertSame([], array_values(array_intersect(['rd-2025-1', 'rd-2025-2', 'rd-2025-3'], array_column($leading, 'id'))), 'Leading 1.2 & 1.3 buatan akun lain di luar cakupan akun leading 1.6.');
        $this->assertCount(9, $leading);
        $this->assertContains('rd-2026-6', array_column($leading, 'id'), 'Dibuat admin, tetapi leading-nya milik akun.');

        $this->assertSame([2026, 2025], array_keys(RiwayatDokumen::perTahun($admin)));
        $this->assertSame(['jumlah' => 12, 'kegiatan' => 6, 'tahun' => 2, 'arsip' => 2, 'draf' => 9, 'format' => ['pdf' => 8, 'docx' => 2, 'xlsx' => 2]], RiwayatDokumen::ringkasan($admin));

        $satu = collect($admin)->firstWhere('id', 'rd-2026-1');
        $this->assertSame(route('kegiatan.pratinjau', ['kegiatan' => 2, 'sumber' => 'DIPA']), $satu['tautan_kegiatan']);
        $this->assertNull(collect($admin)->firstWhere('id', 'rd-2025-3')['tautan_kegiatan'], 'Kegiatan tahun lalu tidak tersimpan.');
    }

    public function test_halaman_riwayat_dokumen(): void
    {
        $this->get(route('dokumen.index'))->assertOk()->assertSee('Riwayat Dokumen')->assertSee('data-testid="ringkasan-riwayat"', false);

        Livewire::test(Riwayat::class)
            ->assertSet('tampilan', 'kegiatan')
            ->assertSeeInOrder(['Tahun Anggaran 2026', 'Tahun Anggaran 2025'])
            ->assertSee('KAK-TOR_RAB_Lomba_Kreativitas_Siswa_Akhir_Tahun_KOMITE_2025.pdf')
            ->assertSee('KAK/031/2025')
            ->assertDontSee('Pelatihan Penulisan Karya Ilmiah Guru')
            ->assertSee('data-testid="dokumen-kegiatan" data-versi="2" data-id="rd-2025-5"', false)
            ->assertSee('data-testid="versi-sebelumnya" data-versi="1" data-id="rd-2025-4"', false)
            ->assertSee('data-testid="kegiatan-tidak-tersimpan"', false)
            ->assertDontSee('data-testid="dokumen-riwayat"', false)
            ->set('tampilan', 'kronologis')
            ->assertSee('data-testid="dokumen-riwayat" data-id="rd-2026-7"', false)
            ->assertSee('data-testid="kartu-riwayat" data-id="rd-2026-7"', false)
            ->assertSeeInOrder(['rd-2026-7', 'rd-2026-6'])
            ->set('urut', 'terlama')
            ->assertSeeInOrder(['Tahun Anggaran 2025', 'Tahun Anggaran 2026'])
            ->assertSeeHtmlInOrder(['data-id="rd-2025-4"', 'data-id="rd-2025-5"'])
            ->set('tampilan', 'asing')
            ->assertSet('tampilan', 'kegiatan');

        session(['stub_role' => 'admin']);
        Livewire::test(Riwayat::class)->assertSee('Pelatihan Penulisan Karya Ilmiah Guru');

        $menu = collect(Navigation::groups('leading'))->flatMap(fn (array $g) => $g['items'])->firstWhere('route', 'dokumen.index');
        $this->assertTrue($menu['available']);
    }

    public function test_riwayat_kosong(): void
    {
        Storage::disk('local')->put(config('sigenrabtor.stub.riwayat_dokumen_file'), '[]');
        $this->assertSame([], app(RiwayatDokumenRepository::class)->semua());

        $kosong = RiwayatKosong::untuk(self::LEADING);
        $this->assertSame(2026, $kosong['tahun']);
        $this->assertCount(RiwayatKosong::MAKS_CONTOH, $kosong['contoh']);
        $this->assertSame(3, $kosong['dokumen']['kegiatan']);
        $this->assertGreaterThan(RiwayatKosong::MAKS_CONTOH, $kosong['dokumen']['jumlah']);
        $this->assertNull($kosong['buat'], 'Ada kegiatan tahun aktif: arahkan ke cetak, bukan membuat kegiatan.');
        $keadaan = array_column($kosong['contoh'], 'keadaan');
        $this->assertSame($keadaan, array_values(array_merge(array_filter($keadaan, fn ($k) => $k === 'siap'), array_filter($keadaan, fn ($k) => $k === 'draf'))), 'Siap dicetak dulu.');
        $this->assertStringContainsString('/kegiatan/', $kosong['contoh'][0]['tautan_cetak']);

        Livewire::test(Riwayat::class)
            ->assertSee('data-testid="riwayat-kosong"', false)
            ->assertSee('Belum ada dokumen yang tercatat.')
            ->assertDontSee('data-testid="ringkasan-riwayat"', false)
            ->assertDontSee('data-testid="alat-riwayat"', false)
            ->assertSeeHtml('data-langkah="cetak"')
            ->assertSeeHtml('data-langkah="unduh"')
            ->assertSeeHtml('data-langkah="arsip"')
            ->assertSeeText('Dokumen TA 2026 yang bisa dicetak sekarang')
            ->assertSee('data-testid="contoh-cetak-tautan"', false)
            ->assertDontSee('data-testid="kosong-buat-kegiatan"', false);
    }

    public function test_riwayat_kosong_tanpa_kegiatan_tahun_aktif(): void
    {
        Storage::disk('local')->put(config('sigenrabtor.stub.riwayat_dokumen_file'), '[]');
        Storage::disk('local')->put(config('sigenrabtor.stub.kegiatan_file'), '[]');

        $kosong = RiwayatKosong::untuk(self::LEADING);
        $this->assertSame([[], 0], [$kosong['contoh'], $kosong['dokumen']['jumlah']]);
        $this->assertSame(route('kegiatan.create'), $kosong['buat']);
        $this->assertNull(RiwayatKosong::untuk(['id' => 9, 'role' => 'leading', 'leadings' => []])['buat'], 'Akun tanpa leading tidak bisa membuat kegiatan.');
        Livewire::test(Riwayat::class)
            ->assertSee('data-testid="kosong-buat-kegiatan"', false)
            ->assertSeeText('Belum ada kegiatan TA 2026.');

        app(TahunAnggaranRepository::class)->kunci(2026, true);
        $terkunci = RiwayatKosong::untuk(self::ADMIN);
        $this->assertTrue($terkunci['terkunci']);
        $this->assertNull($terkunci['buat']);
        Livewire::test(Riwayat::class)
            ->assertDontSee('data-testid="kosong-buat-kegiatan"', false)
            ->assertSeeText('Tahun Anggaran 2026 dikunci');
    }

    public function test_tanpa_hasil_menyarankan_kriteria_yang_dilepas(): void
    {
        $telusur = PenelusuranRiwayat::dari(RiwayatDokumen::untuk(self::LEADING), ['cari' => 'lomba', 'format' => 'xlsx', 'sampai' => '2025-12-31']);
        $this->assertSame([], $telusur->cocok);
        $this->assertSame([['kunci' => 'format', 'label' => 'format Excel', 'jumlah' => 2]], $telusur->saranLonggar(), 'Hanya melepas format yang menghasilkan dokumen.');
        $this->assertSame(
            [['kunci' => 'cari', 'label' => 'kata kunci “workshop”', 'jumlah' => 1], ['kunci' => 'jenis', 'label' => 'jenis RAB', 'jumlah' => 1], ['kunci' => 'format', 'label' => 'format PDF', 'jumlah' => 1]],
            PenelusuranRiwayat::dari(RiwayatDokumen::untuk(self::LEADING), ['cari' => 'workshop', 'jenis' => 'RAB', 'format' => 'pdf'])->saranLonggar(),
            'Setiap kriteria yang dilepas menghasilkan dokumen; jumlah sama tetap urut kriteria.',
        );
        $this->assertSame([], PenelusuranRiwayat::dari(RiwayatDokumen::untuk(self::LEADING), [])->saranLonggar());

        Livewire::test(Riwayat::class)
            ->set('cari', 'lomba')
            ->set('format', 'xlsx')
            ->assertSee('data-testid="riwayat-tanpa-hasil"', false)
            ->assertSeeHtml('data-testid="saran-longgar" data-kunci="format" data-jumlah="2"')
            ->assertSeeText('Lepas format Excel')
            ->call('lepasSaringan', 'format')
            ->assertDontSee('data-testid="riwayat-tanpa-hasil"', false)
            ->assertSet('cari', 'lomba');
    }

    public function test_per_kegiatan_versi_terbaru_dan_lama(): void
    {
        $kegiatan = RiwayatDokumen::perKegiatan(RiwayatDokumen::untuk(self::ADMIN));
        $this->assertSame(['k3', 'k1', 'k2', 'x2025|lomba kreativitas siswa akhir tahun', 'x2025|pelatihan penulisan karya ilmiah guru', 'x2025|studi banding madrasah unggulan'], array_column($kegiatan, 'kunci'), 'Kegiatan dengan dokumen terbaru dulu.');
        $this->assertSame(12, array_sum(array_column($kegiatan, 'jumlah')));

        $lomba = $kegiatan[3];
        $this->assertSame([1, 'rd-2025-5', ['rd-2025-4'], false], [count($lomba['dokumen']), $lomba['dokumen'][0]['terbaru']['id'], array_column($lomba['dokumen'][0]['lama'], 'id'), $lomba['tersedia']]);
        $supervisi = collect($kegiatan)->firstWhere('kunci', 'k2');
        $this->assertSame(['rd-2026-2', 'rd-2026-1', 'rd-2026-3'], array_map(fn ($d) => $d['terbaru']['id'], $supervisi['dokumen']), 'Urut sumber dana lalu jenis dokumen.');
        $this->assertTrue($supervisi['tersedia']);
    }

    public function test_tampilkan_lebih_banyak(): void
    {
        $repo = app(RiwayatDokumenRepository::class);
        foreach (range(1, 25) as $i) {
            $repo->catat(RiwayatDokumen::entri(['nama_kegiatan' => "Kegiatan Arsip {$i}", 'leading' => '1.6', 'tahun_anggaran' => 2024, 'status' => 'final'], 'RAB', 'DIPA', 'pdf', 'arsip', "RAB_{$i}.pdf", 1000, false, ['id' => 1, 'name' => 'Muslimin, M.Pd.']));
        }

        Livewire::test(Riwayat::class)
            ->assertSee('data-testid="tampilkan-lebih-riwayat"', false)
            ->assertSee('(20 dari 29 kegiatan)')
            ->call('tampilkanLebih')
            ->assertDontSee('data-testid="tampilkan-lebih-riwayat"', false)
            ->set('tampilan', 'kronologis')
            ->assertSet('batas', Riwayat::PER_HALAMAN)
            ->assertSee('(20 dari 34 dokumen)');
    }

    public function test_pencarian_kata_kunci_dan_sorotan(): void
    {
        $semua = RiwayatDokumen::untuk(self::ADMIN);
        $this->assertSame(['rd-2025-5', 'rd-2025-4'], array_column(RiwayatDokumen::cari($semua, '  LOMBA   kreativitas '), 'id'));
        $this->assertSame(['rd-2025-5'], array_column(RiwayatDokumen::cari($semua, 'KAK/031'), 'id'), 'Nomor dokumen.');
        $this->assertSame(['rd-2026-6'], array_column(RiwayatDokumen::cari($semua, 'puskom excel'), 'id'), 'Pembuat & format, semua kata wajib cocok.');
        $this->assertCount(2, RiwayatDokumen::cari($semua, 'word'), 'Label format.');
        $this->assertSame([], RiwayatDokumen::cari($semua, 'lomba dipa'));
        $this->assertSame($semua, RiwayatDokumen::cari($semua, '   '));
        $this->assertSame(['lomba', 'siswa'], RiwayatDokumen::kataKunci(' Lomba  siswa lomba '));

        $this->assertSame('Lomba <mark class="rounded-sm bg-gold-100 px-0.5 text-inherit">Kreativ</mark>itas &lt;b&gt;', RiwayatDokumen::sorot('Lomba Kreativitas <b>', 'kreativ'));
        $this->assertSame('a &amp; b', RiwayatDokumen::sorot('a & b', ''));
        $this->assertSame('<mark class="rounded-sm bg-gold-100 px-0.5 text-inherit">KAK/031</mark>/2025', RiwayatDokumen::sorot('KAK/031/2025', 'kak/031'));

        Livewire::test(Riwayat::class)
            ->set('cari', 'lomba')
            ->assertSee('data-testid="cari-riwayat"', false)
            ->assertSeeText('2 dokumen cocok dengan “lomba” dari 9.')
            ->assertSeeHtml('<mark class="rounded-sm bg-gold-100 px-0.5 text-inherit">Lomba</mark> Kreativitas Siswa Akhir Tahun')
            ->assertDontSee('Review RKJM dan Penyusunan RKTM')
            ->assertSee('data-testid="cari-riwayat-hapus"', false)
            ->set('tampilan', 'kronologis')
            ->assertSet('cari', 'lomba')
            ->assertSee('data-testid="dokumen-riwayat" data-id="rd-2025-4"', false)
            ->assertDontSee('data-id="rd-2026-7"', false)
            ->set('cari', 'tidak ada sama sekali')
            ->assertSee('data-testid="riwayat-tanpa-hasil"', false)
            ->set('cari', str_repeat('a', 150))
            ->call('$refresh');
        $this->assertSame(100, mb_strlen(Livewire::withQueryParams(['cari' => str_repeat('b', 150)])->test(Riwayat::class)->get('cari')));
    }

    public function test_saringan_jenis_format_dan_rentang_tanggal(): void
    {
        $semua = RiwayatDokumen::untuk(self::ADMIN);
        $this->assertSame(['rd-2026-6', 'rd-2026-1', 'rd-2025-2'], array_column(RiwayatDokumen::saring($semua, ['jenis' => 'RAB']), 'id'));
        $this->assertSame(['rd-2026-5', 'rd-2025-1'], array_column(RiwayatDokumen::saring($semua, ['format' => 'docx']), 'id'));
        $this->assertSame(['rd-2026-1'], array_column(RiwayatDokumen::saring($semua, ['jenis' => 'RAB', 'format' => 'pdf', 'dari' => '2026-02-01', 'sampai' => '2026-02-28']), 'id'), 'Semua saringan berlaku bersama.');
        $this->assertSame(['rd-2026-3', 'rd-2026-2', 'rd-2026-1'], array_column(RiwayatDokumen::saring($semua, ['dari' => '2026-02-10', 'sampai' => '2026-02-12']), 'id'), 'Rentang inklusif.');
        $this->assertCount(5, RiwayatDokumen::saring($semua, ['sampai' => '2025-12-31']));
        $this->assertCount(12, RiwayatDokumen::saring($semua, ['dari' => '2026-03-01', 'sampai' => '2026-01-01']), 'Rentang terbalik tidak diterapkan.');
        $this->assertSame(['jenis' => '', 'format' => '', 'leading' => '', 'sumber' => '', 'status' => '', 'dari' => '', 'sampai' => '2026-02-28', 'terbalik' => false], RiwayatDokumen::rapikanSaringan(['jenis' => 'Surat', 'format' => 'exe', 'leading' => '9.9', 'sumber' => 'BOS', 'status' => 'ditolak', 'dari' => '2026-02-30', 'sampai' => '2026-02-28']));
        $this->assertSame(['leading' => '1.2', 'sumber' => 'Komite', 'status' => 'final'], array_intersect_key(RiwayatDokumen::rapikanSaringan(['leading' => ' 1.2 ', 'sumber' => 'Komite', 'status' => 'final']), ['leading' => 0, 'sumber' => 0, 'status' => 0]));
        $this->assertSame(['rd-2025-5', 'rd-2025-3'], array_column(RiwayatDokumen::saring($semua, ['status' => 'final']), 'id'), 'Status saat dibuat.');
        $this->assertSame(['rd-2025-5'], array_column(RiwayatDokumen::saring($semua, ['status' => 'final', 'sumber' => 'Komite']), 'id'), 'Status & sumber dana.');
        $this->assertSame(['rd-2025-1'], array_column(RiwayatDokumen::saring($semua, ['leading' => '1.2']), 'id'));
        $this->assertSame(['leading' => 'Leading 1.2', 'sumber' => 'Sumber dana DIPA', 'status' => 'Status Final'], RiwayatDokumen::labelSaringan(['leading' => '1.2', 'sumber' => 'DIPA', 'status' => 'final']));
        $this->assertSame(['jenis' => 'RAB', 'format' => 'Excel', 'tanggal' => '1 Februari 2026 – 28 Februari 2026'], RiwayatDokumen::labelSaringan(['jenis' => 'RAB', 'format' => 'xlsx', 'dari' => '2026-02-01', 'sampai' => '2026-02-28']));
        $this->assertSame(['tanggal' => 'Sejak 1 Maret 2026'], RiwayatDokumen::labelSaringan(['dari' => '2026-03-01']));

        $this->travelTo(CarbonImmutable::parse('2026-03-20 10:00', config('app.timezone')));
        Livewire::test(Riwayat::class)
            ->set('jenis', 'RAB')
            ->assertSee('data-testid="chip-saringan" data-kunci="jenis"', false)
            ->assertDontSee('Review RKJM dan Penyusunan RKTM')
            ->set('dari', '2026-03-01')->set('sampai', '2026-02-01')
            ->assertSee('data-testid="rentang-terbalik"', false)
            ->assertDontSee('data-kunci="tanggal"', false)
            ->call('pintasanTanggal', '30hari')
            ->assertSet('dari', '2026-02-19')->assertSet('sampai', '2026-03-20')
            ->assertSee('data-kunci="tanggal"', false)
            ->call('lepasSaringan', 'tanggal')
            ->assertSet('dari', '')->assertSet('sampai', '')
            ->call('pintasanTanggal', 'ta')
            ->assertSet('dari', '2026-01-01')->assertSet('sampai', '2026-12-31')
            ->set('format', 'docx')
            ->assertSee('data-testid="riwayat-tanpa-hasil"', false)
            ->assertSee('Tidak ada dokumen yang cocok dengan saringan yang dipilih.')
            ->call('hapusSaringan')
            ->assertSet('jenis', '')->assertSet('format', '')->assertSet('dari', '')
            ->assertDontSee('data-testid="chip-saringan-riwayat"', false);

        $this->assertSame('', Livewire::withQueryParams(['jenis' => 'Surat', 'dari' => 'kemarin'])->test(Riwayat::class)->get('dari'));
    }
}
