<?php

namespace Tests\Feature\Kegiatan;

use App\Support\Dokumen\ChecklistIsian;
use App\Support\Dokumen\KaidahDokumen;
use App\Support\Dokumen\SusunanKak;
use App\Support\Dokumen\SusunanRab;
use App\Support\Kegiatan\KelengkapanKode;
use App\Support\Stub\StubKegiatanRepository;
use Tests\TestCase;

class KelengkapanKodeTest extends TestCase
{
    public function test_status_tiap_kode_master(): void
    {
        $kegiatan = ['kode_rktm' => '6.17.12', 'kode_sk' => 'SK.11', 'kode_iksk' => 'IKSK 10.6', 'kode_edm' => 'Z9', 'budaya_mutu' => null];

        $this->assertSame(
            ['kode_rktm' => 'terisi', 'kode_sk' => 'terisi', 'kode_iksk' => 'tidak_sesuai', 'kode_edm' => 'asing', 'budaya_mutu' => 'kosong'],
            array_map(fn (array $k) => $k['status'], KelengkapanKode::periksa($kegiatan)),
        );
        $this->assertSame(
            ['kode_iksk' => 'IKSK 10.6 bukan indikator dari SK.11.', 'kode_edm' => 'Z9 tidak ada di master TA 2026.'],
            KelengkapanKode::galatPengajuan($kegiatan),
        );

        // SK wajib: kosong menghalangi pengajuan; kode opsional yang kosong tidak.
        $this->assertSame(['kode_sk'], array_keys(KelengkapanKode::galatPengajuan(['kode_sk' => null])));
        $this->assertSame([], KelengkapanKode::galatPengajuan(StubKegiatanRepository::contoh()));
        $this->assertSame('asing', KelengkapanKode::periksa(['kode_sk' => 'SK.10'], 2030)['kode_sk']['status'], 'Tahun tanpa master.');
    }

    public function test_checklist_dan_kaidah_menandai_iksk_yang_bukan_indikator_sk(): void
    {
        $kegiatan = ['kode_sk' => 'SK.11', 'kode_iksk' => 'IKSK 10.6'] + StubKegiatanRepository::contoh();

        $butir = collect(ChecklistIsian::untuk($kegiatan))->firstWhere('kunci', 'kode')['butir'];
        $iksk = collect($butir)->firstWhere('kunci', 'kode_iksk');
        $this->assertSame([false, 'IKSK 10.6 bukan indikator dari SK.11.'], [$iksk['terisi'], $iksk['keterangan']]);

        $kaidah = collect(KaidahDokumen::periksa($kegiatan, SusunanKak::dari($kegiatan, 'Komite'), SusunanRab::dari($kegiatan, 'Komite')))->firstWhere('kunci', 'kode');
        $this->assertSame(['gagal', 'IKSK 10.6 bukan indikator dari SK.11.'], [$kaidah['status'], $kaidah['pesan']]);
    }
}
