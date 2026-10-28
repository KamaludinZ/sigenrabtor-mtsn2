<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\KegiatanRepository;
use App\Contracts\RiwayatPengaturanRepository;
use App\Contracts\TahunAnggaranRepository;
use App\Livewire\Kegiatan\Form;
use App\Livewire\Kegiatan\MatrikJadwal;
use App\Livewire\Kegiatan\Rab;
use App\Livewire\Pemeriksaan\Periksa;
use App\Livewire\Pengaturan\TahunAnggaran;
use App\Support\Kegiatan\KunciKegiatan;
use App\Support\Kegiatan\SimpanKonteks;
use App\Support\Kegiatan\SimpanMatrikJadwal;
use App\Support\Pengaturan\KelolaTahunAnggaran;
use App\Support\Pengaturan\KontrolTahunAktif;
use App\Support\Rab\RincianRab;
use App\Support\Rab\SimpanSumberDana;
use App\Support\Rab\SimpanTermin;
use App\Support\Stub\StubData;
use App\Support\Stub\StubKegiatanRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Livewire\Livewire;
use Tests\TestCase;

class KunciTahunTest extends TestCase
{
    private const ADMIN = ['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin', 'leadings' => []];

    /** Salinan contoh kegiatan 2 milik TA 2025 (contoh: terkunci); mengembalikan id. */
    private function kegiatan2025(): int
    {
        return app(KegiatanRepository::class)->simpan(['tahun_anggaran' => 2025] + Arr::except(StubKegiatanRepository::contohDuaSumber(), ['id', 'status', 'tahun_anggaran']));
    }

    private function ditolak(callable $aksi): string
    {
        try {
            $aksi();
        } catch (AuthorizationException $e) {
            return $e->getMessage();
        }
        $this->fail('Seharusnya ditolak.');
    }

    public function test_tahun_kegiatan_yang_terkunci(): void
    {
        $repo = app(KegiatanRepository::class);
        $lama = $repo->temukan($this->kegiatan2025());

        $this->assertNull(KunciKegiatan::tahunTerkunci($repo->temukan(1)));
        $this->assertTrue(KunciKegiatan::dapatDiubah($repo->temukan(1)));
        $this->assertSame(2025, KunciKegiatan::tahunTerkunci($lama));
        $this->assertFalse(KunciKegiatan::dapatDiubah($lama));
        $this->assertNull(KunciKegiatan::tahunTerkunci([]), 'Kegiatan baru memakai tahun aktif.');

        app(TahunAnggaranRepository::class)->kunci(2026, true);
        $this->assertSame(2026, KunciKegiatan::tahunTerkunci([]));
        $this->assertSame('Tahun Anggaran 2026 terkunci — kegiatan tahun itu hanya bisa dilihat.', KunciKegiatan::pesanTahun(2026));
    }

    public function test_layanan_simpan_menolak_kegiatan_tahun_terkunci(): void
    {
        $id = $this->kegiatan2025();
        $pesan = KunciKegiatan::pesanTahun(2025);

        $this->assertSame($pesan, $this->ditolak(fn () => SimpanKonteks::simpan($id, 'Catatan baru.')));
        $this->assertSame($pesan, $this->ditolak(fn () => SimpanSumberDana::simpan($id, ['DIPA' => '1.000.000'])));
        $this->assertSame($pesan, $this->ditolak(fn () => RincianRab::tambah($id, 'DIPA', ['uraian' => 'Konsumsi', 'kategori' => 'konsumsi', 'volume' => '1', 'satuan' => 'paket', 'harga_satuan' => '10.000'])));
        $this->assertSame($pesan, $this->ditolak(fn () => SimpanTermin::simpan($id, 'DIPA', [])));
        $this->assertSame($pesan, $this->ditolak(fn () => SimpanMatrikJadwal::simpanJadwal($id, [])));
        $this->assertFalse($this->getJson(route('kegiatan.sumber_dana', ['kegiatan' => $id]))->json('dapat_diubah'));
        $this->assertFalse($this->getJson(route('kegiatan.matrik_jadwal', ['kegiatan' => $id]))->json('dapat_diubah'));

        // Dibuka kuncinya: bisa diubah lagi.
        KelolaTahunAnggaran::kunci(2025, false, self::ADMIN);
        $this->assertSame('Catatan baru.', SimpanKonteks::simpan($id, 'Catatan baru.')['konteks']);
    }

    public function test_tab_ruang_kerja_dan_status_hanya_bisa_dilihat(): void
    {
        $id = $this->kegiatan2025();

        $form = Livewire::test(Form::class, ['kegiatan' => $id])
            ->assertSeeHtml('data-testid="banner-terkunci" data-status="tahun-terkunci"')
            ->assertSee('Isian terkunci — Tahun Anggaran 2025 dikunci')
            ->call('ubahStatus', 'ajukan')
            ->assertDispatched('notify', message: KunciKegiatan::pesanTahun(2025), type: 'error');
        $this->assertFalse($form->instance()->dapatDiedit());
        $this->assertSame('draf', app(KegiatanRepository::class)->temukan($id)['status']);

        Livewire::test(Rab::class, ['kegiatan' => $id])->assertSeeHtml('data-status="tahun-terkunci"')->assertSee('RAB terkunci — Tahun Anggaran 2025 dikunci');
        $this->assertFalse(Livewire::test(MatrikJadwal::class, ['kegiatan' => $id])->instance()->dapatDiedit());
        Livewire::test(Form::class, ['kegiatan' => 1])->assertDontSeeHtml('data-status="tahun-terkunci"');

        // Keputusan admin atas kegiatan tahun terkunci ditolak.
        $repo = app(KegiatanRepository::class);
        $repo->simpan(['status' => 'diperiksa'] + $repo->temukan(2), 2);
        app(TahunAnggaranRepository::class)->kunci(2026, true);
        $this->withSession(['stub_role' => 'admin']);
        Livewire::test(Periksa::class, ['kegiatan' => 2])
            ->call('minta', 'kembalikan')
            ->assertHasErrors(['catatan'])
            ->set('catatan', 'Lengkapi sasaran.')
            ->call('minta', 'kembalikan')
            ->assertHasErrors(['keputusan'])
            ->assertSee(KunciKegiatan::pesanTahun(2026));
        $this->assertSame('diperiksa', $repo->temukan(2)['status']);
    }

    public function test_aktifkan_tahun_baru_sekaligus_mengunci_tahun_sebelumnya(): void
    {
        KelolaTahunAnggaran::tambah(2027, self::ADMIN);
        $this->withSession(['stub_role' => 'admin']);

        Livewire::test(TahunAnggaran::class)
            ->call('pilih', 2025)
            ->assertSet('kunciSebelumnya', false)
            ->call('pilih', 2027)
            ->assertSet('kunciSebelumnya', true)
            ->assertSeeHtml('data-testid="kunci-sebelumnya"')
            ->assertSee('Kunci TA 2026 sesudah beralih')
            ->call('aktifkan')
            ->assertRedirect(route('pengaturan.tahun.index'));

        $tahun = array_column(app(TahunAnggaranRepository::class)->semua(), null, 'tahun');
        $this->assertSame([true, false], [$tahun[2027]['is_active'], $tahun[2027]['is_locked']]);
        $this->assertSame([false, true], [$tahun[2026]['is_active'], $tahun[2026]['is_locked']]);
        $this->assertSame('Tahun Anggaran 2027 diaktifkan (sebelumnya 2026); 2026 dikunci.', app(RiwayatPengaturanRepository::class)->semua()[0]['keterangan']);
        $this->assertStringContainsString('Tahun 2026 dikunci', session('notify')['message']);
        $this->assertSame(2026, KunciKegiatan::tahunTerkunci(app(KegiatanRepository::class)->temukan(1)));

        // Tanpa pilihan kunci: tahun sebelumnya tetap terbuka.
        KelolaTahunAnggaran::kunci(2026, false, self::ADMIN);
        $this->assertTrue(KontrolTahunAktif::aktifkan(2026, StubData::currentUser()));
        $this->assertFalse(collect(app(TahunAnggaranRepository::class)->semua())->firstWhere('tahun', 2027)['is_locked']);
        $this->assertSame('Tahun Anggaran 2026 diaktifkan (sebelumnya 2027).', app(RiwayatPengaturanRepository::class)->semua()[0]['keterangan']);
    }
}
