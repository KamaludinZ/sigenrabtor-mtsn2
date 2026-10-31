<?php

namespace Tests\Feature\Akun;

use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Livewire\Dashboard;
use App\Support\Akun\PanduanLangkah;
use App\Support\Kegiatan\RiwayatStatus;
use App\Support\Stub\StubData;
use Livewire\Livewire;
use Tests\TestCase;

class PanduanLangkahTest extends TestCase
{
    private const ADMIN = ['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin', 'leadings' => []];

    /** Akun leading 1.5 — tanpa contoh kegiatan (contoh kegiatan dibuat akun 1 & 2). */
    private const LEADING_BARU = ['id' => 99, 'name' => 'Rina Kusuma, S.Pd.', 'role' => 'leading', 'leadings' => ['1.5']];

    private function kegiatan(array $data): int
    {
        return app(KegiatanRepository::class)->simpan($data + ['nama_kegiatan' => 'Lomba Kreativitas Siswa', 'leading' => '1.5', 'user_id' => 99, 'tahun_anggaran' => 2026]);
    }

    /** @return array<string, string> kunci tahap → status */
    private function statusTahap(array $panduan): array
    {
        return array_column($panduan['tahap'], 'status', 'kunci');
    }

    public function test_langkah_persiapan_admin_berurutan_dengan_langkah_berikutnya(): void
    {
        $p = PanduanLangkah::admin();
        $this->assertSame(array_keys(PanduanLangkah::LANGKAH_ADMIN), array_column($p['langkah'], 'kunci'));
        $this->assertSame(range(1, count($p['langkah'])), array_column($p['langkah'], 'nomor'));

        // Langkah berikutnya = langkah wajib pertama yang belum siap; hanya satu yang "sekarang".
        $belumWajib = collect($p['langkah'])->first(fn (array $l) => $l['wajib'] && ! $l['siap']);
        $this->assertSame($belumWajib['kunci'], $p['berikutnya']);
        $this->assertSame(['sekarang'], array_values(array_unique(array_column(array_filter($p['langkah'], fn (array $l) => $l['kunci'] === $p['berikutnya']), 'status'))));
        $this->assertCount(1, array_filter($p['langkah'], fn (array $l) => $l['status'] === 'sekarang'));
        foreach ($p['langkah'] as $l) {
            $this->assertSame($l['siap'], $l['status'] === 'selesai', $l['kunci']);
        }
        $this->assertFalse($p['lengkap']);
        $this->assertSame(count(array_filter($p['langkah'], fn (array $l) => $l['wajib'] && $l['siap'])), $p['wajib_siap']);

        // Tautan menuju tempat mengaturnya; opsional ditandai.
        $langkah = collect($p['langkah'])->keyBy('kunci');
        $this->assertSame(route('pengaturan.madrasah.index', ['tab' => 'pejabat']), $langkah['pejabat']['tautan']);
        $this->assertSame(route('master.index'), $langkah['master']['tautan']);
        $this->assertSame(route('pengaturan.template.master.index'), $langkah['kak']['tautan']);
        $this->assertSame([false, false, false, false], [$langkah['data_pagu']['wajib'], $langkah['cover']['wajib'], $langkah['kak']['wajib'], $langkah['ai']['wajib']]);
    }

    public function test_leading_tanpa_kegiatan_dimulai_dari_tahap_pertama(): void
    {
        $p = PanduanLangkah::leading(self::LEADING_BARU);
        $this->assertSame([0, null, [], true], [$p['jumlah_kegiatan'], $p['fokus'], $p['pilihan'], $p['bisa_buat']]);
        $this->assertSame(array_keys(PanduanLangkah::TAHAP), array_column($p['tahap'], 'kunci'));
        $this->assertSame(['identitas' => 'sekarang'] + array_fill_keys(array_slice(array_keys(PanduanLangkah::TAHAP), 1), 'belum'), $this->statusTahap($p));
        $this->assertSame([route('kegiatan.create'), 'Buat kegiatan'], [$p['tahap'][0]['tautan'], $p['tahap'][0]['label_tautan']]);
        $this->assertSame(route('pagu.index'), $p['tautan_pagu']);
    }

    public function test_tahapan_mengikuti_isian_dan_status_kegiatan(): void
    {
        // Draf dengan nama saja: tahap identitas sekarang beserta yang kurang.
        $draf = $this->kegiatan([]);
        $p = PanduanLangkah::leading(self::LEADING_BARU);
        $this->assertSame([$draf, 'identitas'], [$p['fokus']['id'], $p['fokus']['sekarang']]);
        $this->assertSame('sekarang', $this->statusTahap($p)['identitas']);
        $this->assertContains('Tujuan', $p['tahap'][0]['kurang']);
        $this->assertSame(route('kegiatan.edit', $draf).'#identitas', $p['tahap'][0]['tautan']);
        $this->assertSame(route('kegiatan.pratinjau', $draf).'#checklist-isian', collect($p['tahap'])->firstWhere('kunci', 'ajukan')['tautan'], 'Syarat ajukan belum lengkap → checklist.');

        // Dikembalikan: perlu perbaikan + catatan admin; menjadi kegiatan yang dipandu lebih dulu.
        $kembali = $this->kegiatan(['nama_kegiatan' => 'Workshop Penguatan Mutu', 'status' => 'dikembalikan',
            'riwayat_status' => [RiwayatStatus::entri('kembalikan', StatusKegiatan::Dikembalikan, self::ADMIN, 'Rincian konsumsi terlalu besar.')]]);
        $p = PanduanLangkah::leading(self::LEADING_BARU);
        $this->assertSame([$kembali, 'Rincian konsumsi terlalu besar.'], [$p['fokus']['id'], $p['fokus']['catatan']]);
        $this->assertSame('perbaiki', $this->statusTahap($p)['ajukan']);
        $this->assertSame([$kembali, $draf], array_column($p['pilihan'], 'id'), 'Paling perlu perhatian dulu.');

        // Diperiksa: tahap 1–6 selesai, menunggu pemeriksaan.
        $periksa = $this->kegiatan(['nama_kegiatan' => 'Rapat Evaluasi Program', 'status' => 'diperiksa']);
        $p = PanduanLangkah::leading(self::LEADING_BARU, $periksa);
        $this->assertSame(['selesai', 'selesai', 'selesai', 'selesai', 'selesai', 'selesai', 'sekarang', 'belum'], array_values($this->statusTahap($p)));
        $this->assertSame(75, $p['fokus']['persen']);

        // Final: semua selesai, tahap terakhir menuju Cetak & Unduh; sebaran per tahap.
        $final = $this->kegiatan(['nama_kegiatan' => 'Kemah Pramuka', 'status' => 'final']);
        $p = PanduanLangkah::leading(self::LEADING_BARU, $final);
        $this->assertSame(array_fill_keys(array_keys(PanduanLangkah::TAHAP), 'selesai'), $this->statusTahap($p));
        $this->assertSame([null, 100], [$p['fokus']['sekarang'], $p['fokus']['persen']]);
        $this->assertSame([route('cetak.index'), 'Cetak & unduh'], [$p['tahap'][7]['tautan'], $p['tahap'][7]['label_tautan']]);
        $this->assertSame(['identitas' => 2, 'pemeriksaan' => 1, 'selesai' => 1], array_filter($p['sebaran']), 'Kegiatan dikembalikan yang identitasnya belum lengkap berada di tahap identitas.');
        $this->assertSame('Final', collect($p['pilihan'])->firstWhere('id', $final)['tahap']);
    }

    public function test_dashboard_menampilkan_panduan_per_peran(): void
    {
        session(['stub_role' => 'admin']);
        $admin = PanduanLangkah::admin();
        Livewire::test(Dashboard::class)
            ->assertSeeHtml('data-testid="panduan-admin" data-lengkap="0"')
            ->assertSeeHtml('data-kunci="'.$admin['berikutnya'].'" data-status="sekarang"')
            ->assertSee('Langkah persiapan TA 2026')
            ->assertDontSeeHtml('data-testid="panduan-leading"');

        session(['stub_role' => 'leading']);
        $komponen = Livewire::test(Dashboard::class)
            ->assertSeeHtml('data-testid="panduan-leading"')
            ->assertSeeHtml('data-testid="pilih-kegiatan-panduan"')
            ->assertSee('Panduan menyusun dokumen kegiatan')
            ->assertDontSeeHtml('data-testid="panduan-admin"');
        $kegiatan = array_column(PanduanLangkah::leading(StubData::currentUser())['pilihan'], 'id');
        $komponen->set('kegiatanPanduan', end($kegiatan))
            ->assertSeeHtml('data-fokus="'.end($kegiatan).'"');
    }
}
