<?php

namespace App\Support\Dokumen;

use App\Contracts\MatrikJadwalRepository;
use App\Contracts\TahunAnggaranRepository;
use App\Livewire\Forms\KegiatanForm;
use App\Support\Kegiatan\DataPelaksanaan;
use App\Support\Kegiatan\JadwalKegiatan;
use App\Support\Kegiatan\KelengkapanKode;
use App\Support\Kegiatan\MatrikTahapan;
use App\Support\Pengaturan\PenandaTangan;
use App\Support\Rab\AnggaranKegiatan;
use App\Support\Rab\MekanismePembayaran;
use App\Support\Stub\StubData;

/**
 * Checklist isian wajib dokumen (PRD aturan 7 & 8 "Kode wajib valid" / "Cek kelengkapan saat cetak") per tempat
 * mengisinya: Identitas, Pelaksanaan, Kode, Anggaran (per sumber dana), Matrik & Jadwal, dan pengaturan admin. Butir
 * `ajukan` = syarat pengajuan pemeriksaan (KegiatanForm::PELAKSANAAN_WAJIB, KODE_WAJIB, termin pas); butir lain membuat
 * dokumen tercetak dengan cap DRAF selama kosong.
 */
final class ChecklistIsian
{
    /** Kolom kode → [label, kunci pencarian di master]. */
    /** Urutan butir kode di checklist (status & pesan dari KelengkapanKode). */
    private const KODE = ['kode_sk', 'kode_iksk', 'kode_rktm', 'kode_edm', 'budaya_mutu'];

    /** Label isian pelaksanaan wajib (urutan KegiatanForm::PELAKSANAAN_WAJIB). */
    private const PELAKSANAAN = [
        'tanggal_mulai' => 'Tanggal mulai',
        'lama_hari' => 'Lama kegiatan',
        'bulan_pelaksanaan' => 'Bulan pelaksanaan',
        'tempat' => 'Tempat pelaksanaan',
        'peserta_jumlah' => 'Jumlah peserta',
        'peserta_jenis' => 'Jenis peserta',
    ];

    /**
     * @param  array<string, mixed>  $kegiatan
     * @return list<array{kunci: string, judul: string, tautan: string|null, butir: list<array{kunci: string, label: string, terisi: bool, ajukan: bool, keterangan: string|null}>}>
     */
    public static function untuk(array $kegiatan): array
    {
        $tahun = StubData::tahunAktif()['tahun'];
        $id = filter_var($kegiatan['id'] ?? null, FILTER_VALIDATE_INT) ?: null;
        $tautan = fn (string $rute, array $param = [], string $jangkar = '') => $id ? route($rute, ['kegiatan' => $id] + $param).$jangkar : null;
        $butir = fn (string $kunci, string $label, bool $terisi, bool $ajukan = false, ?string $keterangan = null) => compact('kunci', 'label', 'terisi', 'ajukan', 'keterangan');
        $isi = fn (string $kolom) => filled(is_string($kegiatan[$kolom] ?? null) ? trim($kegiatan[$kolom]) : ($kegiatan[$kolom] ?? null));

        $grup = [];

        $grup[] = ['kunci' => 'identitas', 'judul' => 'Identitas kegiatan', 'tautan' => $tautan('kegiatan.edit', [], '#identitas'), 'butir' => [
            $butir('nama_kegiatan', 'Nama kegiatan', $isi('nama_kegiatan'), true),
            $butir('tujuan', 'Tujuan', $isi('tujuan')),
            $butir('sasaran', 'Sasaran', $isi('sasaran')),
            $butir('tanggal_dokumen', 'Tanggal dokumen (lembar pengesahan)', $isi('tanggal_dokumen')),
            $butir('tanggal_laporan', 'Tanggal pelaporan', $isi('tanggal_laporan')),
        ]];

        $pelaksanaan = DataPelaksanaan::dari($kegiatan);
        $terisiPelaksanaan = [
            'tanggal_mulai' => $pelaksanaan->mulai !== null,
            'lama_hari' => (bool) $pelaksanaan->lamaHari,
            'bulan_pelaksanaan' => $pelaksanaan->bulan !== [],
            'tempat' => $pelaksanaan->tempat !== '',
            'peserta_jumlah' => (bool) $pelaksanaan->pesertaJumlah,
            'peserta_jenis' => $pelaksanaan->pesertaJenis !== '',
        ];
        $grup[] = ['kunci' => 'pelaksanaan', 'judul' => 'Pelaksanaan', 'tautan' => $tautan('kegiatan.edit', [], '#pelaksanaan'), 'butir' => array_map(
            fn (string $kolom) => $butir($kolom, self::PELAKSANAAN[$kolom], $terisiPelaksanaan[$kolom], isset(KegiatanForm::PELAKSANAAN_WAJIB[$kolom])),
            array_keys(self::PELAKSANAAN),
        )];

        $kode = KelengkapanKode::periksa($kegiatan, $tahun);
        $grup[] = ['kunci' => 'kode', 'judul' => 'Kode & klasifikasi', 'tautan' => $tautan('kegiatan.kode'), 'butir' => array_map(
            fn (string $kolom) => $butir($kolom, $kode[$kolom]['label'], $kode[$kolom]['status'] === 'terisi', $kode[$kolom]['wajib'], $kode[$kolom]['status'] === 'kosong' ? null : $kode[$kolom]['pesan']),
            self::KODE,
        )];

        $anggaran = AnggaranKegiatan::untuk($kegiatan);
        $butirAnggaran = [$butir('sumber_dana', 'Sumber dana dipilih', $anggaran !== [])];
        foreach ($anggaran as $a) {
            $butirAnggaran[] = $butir("pagu_{$a->sumber}", "Pagu {$a->sumber}", $a->pagu() !== null);
            $butirAnggaran[] = $butir("rincian_{$a->sumber}", "Rincian RAB {$a->sumber} (minimal 1 baris)", $a->jumlahItem() > 0);
            if ($a->adaTermin()) {
                $mekanisme = MekanismePembayaran::dari($a);
                $butirAnggaran[] = $butir("termin_{$a->sumber}", "Termin {$a->sumber} = total RAB", $a->terminSesuai(), true, $a->terminSesuai() ? null : $mekanisme->teksStatus().'.');
            }
        }
        $grup[] = ['kunci' => 'anggaran', 'judul' => 'Sumber dana & RAB', 'tautan' => $tautan('kegiatan.rab'), 'butir' => $butirAnggaran];

        $matrik = $id ? app(MatrikJadwalRepository::class)->matrik($id) : MatrikTahapan::dari($kegiatan);
        $jadwal = $id ? app(MatrikJadwalRepository::class)->jadwal($id) : JadwalKegiatan::dari($kegiatan);
        $grup[] = ['kunci' => 'matrik', 'judul' => 'Matrik & jadwal', 'tautan' => $tautan('kegiatan.matrik'), 'butir' => [
            $butir('matrik', 'Matrik tahapan (tiap tahapan punya bulan)', $matrik->lengkap()),
            $butir('jadwal', 'Jadwal kegiatan', $jadwal->lengkap()),
        ]];

        $pimpinan = PenandaTangan::pimpinan();
        $pembuat = PenandaTangan::pembuat($kegiatan);
        $sk = app(TahunAnggaranRepository::class)->aktif();
        $grup[] = ['kunci' => 'pengaturan', 'judul' => 'Diatur admin', 'tautan' => StubData::currentUser()['role'] === 'admin' ? route('pengaturan.madrasah.index', ['tab' => 'pejabat']) : null, 'butir' => [
            $butir('kepala', 'Kepala Madrasah (nama & NIP)', filled($pimpinan['kepala']['nama']) && filled($pimpinan['kepala']['nip'])),
            $butir('komite', 'Ketua Komite', filled($pimpinan['komite']['nama'])),
            $butir('pejabat_leading', 'Pejabat leading penanda tangan', filled($pembuat['nama'] ?? null)),
            $butir('dasar_hukum', "Dasar hukum TA {$tahun}", StubData::dasarHukum($tahun) !== []),
            $butir('sk_rktm', 'Nomor & tanggal SK RKTM/RKAM', filled($sk['no_sk_rktm'] ?? null) && filled($sk['tanggal_sk_rktm'] ?? null)),
        ]];

        return $grup;
    }

    /**
     * @param  list<array{butir: list<array{terisi: bool, ajukan: bool}>}>  $grup
     * @return array{jumlah: int, terisi: int, ajukan_kurang: int}
     */
    public static function ringkasan(array $grup): array
    {
        $butir = array_merge(...array_column($grup, 'butir'));

        return [
            'jumlah' => count($butir),
            'terisi' => count(array_filter($butir, fn (array $b) => $b['terisi'])),
            'ajukan_kurang' => count(array_filter($butir, fn (array $b) => $b['ajukan'] && ! $b['terisi'])),
        ];
    }
}
