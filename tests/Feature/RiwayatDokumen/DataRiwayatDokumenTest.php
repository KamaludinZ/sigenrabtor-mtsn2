<?php

namespace Tests\Feature\RiwayatDokumen;

use App\Contracts\RiwayatDokumenRepository;
use App\Support\Dokumen\DataRiwayatDokumen;
use App\Support\Dokumen\RiwayatDokumen;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DataRiwayatDokumenTest extends TestCase
{
    public function test_daftar_kronologis_berpenyaring_dan_cakupan_akun(): void
    {
        $data = $this->getJson(route('dokumen.data'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('tampilan', 'kronologis')
            ->assertJsonPath('total', 9)
            ->assertJsonPath('jumlah', 9)
            ->assertJsonPath('aktif', false)
            ->assertJsonPath('keterangan', null)
            ->assertJsonPath('halaman', ['ke' => 1, 'dari' => 1, 'per' => 20, 'jumlah' => 9])
            ->assertJsonPath('dokumen.0.id', 'rd-2026-7')
            ->json();
        $this->assertSame(['rd-2026-7', 'rd-2026-6'], array_slice(array_column($data['dokumen'], 'id'), 0, 2));
        $this->assertArrayNotHasKey('user_id', $data['dokumen'][0], 'Baris tanpa rujukan akun internal.');
        $this->assertSame(['id', 'kegiatan_id', 'tahun_anggaran', 'kode_leading', 'nama_kegiatan', 'jenis', 'sumber', 'format', 'asal', 'nama_berkas', 'ukuran', 'status', 'bertanda_draf', 'nomor_dokumen', 'versi', 'ringkas', 'dibuat_oleh', 'dibuat_pada', 'label_jenis', 'label_format', 'label_asal', 'label_status', 'nama_leading', 'ukuran_teks', 'tersedia', 'tautan_kegiatan', 'izin'], array_keys($data['dokumen'][0]));
        $this->assertSame(['jenis', 'format', 'leading', 'sumber', 'status'], array_keys($data['pilihan']));
        $this->assertContains(['nilai' => 'RAB', 'label' => 'RAB', 'jumlah' => 2], $data['pilihan']['jenis']);
        $this->assertSame([['nilai' => '1.6', 'label' => '1.6 · Tim Penjamin Mutu', 'jumlah' => 9]], $data['pilihan']['leading'], 'Akun leading 1.6 hanya melihat leading-nya.');

        $this->getJson(route('dokumen.data', ['cari' => 'workshop', 'jenis' => 'RAB', 'format' => 'xlsx', 'sumber' => 'Komite', 'status' => 'draf', 'dari' => '2026-03-01', 'sampai' => '2026-03-31']))
            ->assertOk()
            ->assertJsonPath('jumlah', 1)
            ->assertJsonPath('dokumen.0.id', 'rd-2026-6')
            ->assertJsonPath('keterangan', '1 dari 9 dokumen cocok dengan “workshop” · RAB · Excel · Sumber dana Komite · Status Draf · 1 Maret 2026 – 31 Maret 2026')
            ->assertJsonPath('label.sumber', 'Sumber dana Komite');

        $terlama = $this->getJson(route('dokumen.data', ['urut' => 'terlama', 'per' => 10, 'hal' => 9]))->assertOk()->json();
        $this->assertSame(['rd-2025-4', 'rd-2025-5'], array_slice(array_column($terlama['dokumen'], 'id'), 0, 2));
        $this->assertSame(['ke' => 1, 'dari' => 1, 'per' => 10, 'jumlah' => 9], $terlama['halaman'], 'Halaman dijepit ke halaman terakhir.');

        $rapi = $this->getJson(route('dokumen.data', ['jenis' => 'SPJ', 'format' => 'exe', 'status' => 'x', 'per' => 7, 'tampilan' => 'aneh', 'urut' => 'acak', 'dari' => '2026-02-30']))->assertOk()->json();
        $this->assertSame(['', '', '', 20, 'kronologis', 'terbaru', ''], [$rapi['saringan']['jenis'], $rapi['saringan']['format'], $rapi['saringan']['status'], $rapi['saringan']['per'], $rapi['saringan']['tampilan'], $rapi['saringan']['urut'], $rapi['saringan']['dari']], 'Parameter tak sah diabaikan.');

        session(['stub_role' => 'admin']);
        $admin = $this->getJson(route('dokumen.data', ['leading' => '1.2']))->assertOk()->json();
        $this->assertSame([12, ['rd-2025-1']], [$admin['total'], array_column($admin['dokumen'], 'id')]);
        $this->assertSame(['1.2', '1.3', '1.6'], array_column($admin['pilihan']['leading'], 'nilai'));
    }

    public function test_per_kegiatan_halaman_dan_saran_tanpa_hasil(): void
    {
        $data = $this->getJson(route('dokumen.data', ['tampilan' => 'kegiatan', 'sampai' => '2025-09-30']))->assertOk()->json();
        $this->assertArrayNotHasKey('dokumen', $data);
        $this->assertSame('Lomba Kreativitas Siswa Akhir Tahun', $data['kegiatan'][0]['nama_kegiatan']);
        $this->assertSame([1, 2], [$data['kegiatan'][0]['jumlah'], $data['kegiatan'][0]['jumlah_semua']]);
        $this->assertSame(['rd-2025-4', 'rd-2025-5'], [$data['kegiatan'][0]['dokumen'][0]['terbaru']['id'], $data['kegiatan'][0]['dokumen'][0]['lebih_baru']['id']]);

        $kosong = $this->getJson(route('dokumen.data', ['cari' => 'lomba', 'format' => 'xlsx']))->assertOk()->json();
        $this->assertSame([0, []], [$kosong['jumlah'], $kosong['dokumen']]);
        $this->assertSame([['kunci' => 'format', 'label' => 'format Excel', 'jumlah' => 2], ['kunci' => 'cari', 'label' => 'kata kunci “lomba”', 'jumlah' => 1]], $kosong['saran_longgar']);

        $repo = app(RiwayatDokumenRepository::class);
        foreach (range(1, 25) as $i) {
            $repo->catat(RiwayatDokumen::entri(['nama_kegiatan' => "Kegiatan Arsip {$i}", 'leading' => '1.6', 'tahun_anggaran' => 2024, 'status' => 'final'], 'RAB', 'DIPA', 'pdf', 'arsip', "RAB_{$i}.pdf", 1000, false, ['id' => 1, 'name' => 'Muslimin, M.Pd.']));
        }
        $hal2 = $this->getJson(route('dokumen.data', ['per' => 10, 'hal' => 2]))->assertOk()->json();
        $this->assertSame(['ke' => 2, 'dari' => 4, 'per' => 10, 'jumlah' => 34], $hal2['halaman']);
        $this->assertCount(10, $hal2['dokumen']);

        Storage::disk('local')->put(config('sigenrabtor.stub.riwayat_dokumen_file'), '[]');
        $this->getJson(route('dokumen.data', ['jenis' => 'RAB']))->assertOk()->assertJsonPath('total', 0)->assertJsonPath('saran_longgar', []);
        $this->assertSame(['cari', 'jenis', 'format', 'leading', 'sumber', 'status', 'dari', 'sampai', 'tampilan', 'urut', 'hal', 'per'], DataRiwayatDokumen::PARAMETER);
    }
}
