<?php

namespace App\Support\Dokumen;

use App\Contracts\MatrikJadwalRepository;
use App\Support\Format;
use App\Support\Kegiatan\JadwalKegiatan;
use App\Support\Kegiatan\KalimatKak;
use App\Support\Kegiatan\MatrikTahapan;
use App\Support\MasterKode;
use App\Support\Rab\AnggaranKegiatan;
use App\Support\Stub\StubData;

/**
 * Nilai setiap variabel terdaftar (VariabelTemplate::DAFTAR) untuk satu kegiatan × sumber dana — sumber yang sama dengan
 * pratinjau KAK: angka dari AngkaDokumen, kalimat dari KalimatKak, kode dari MasterKode, pejabat & profil dari master.
 * Null berarti datanya belum ada (pratinjau menandainya "kosong"); daftar/blok berupa list baris; tabel berupa ringkasan
 * karena tabel utuh baru disusun saat dokumen dibuat.
 */
final class NilaiVariabel
{
    /** Tempat mengisi data tiap variabel bila kosong. */
    public const ASAL = [
        'nama_kegiatan' => 'Data Kegiatan › Nama kegiatan',
        'nama_kegiatan_cover' => 'Data Kegiatan › Nama kegiatan',
        'tanggal_dokumen' => 'Data Kegiatan › Tanggal dokumen',
        'tanggal_laporan' => 'Data Kegiatan › Pelaksanaan',
        'tujuan' => 'Data Kegiatan › Tujuan',
        'sasaran' => 'Data Kegiatan › Sasaran',
        'tempat' => 'Data Kegiatan › Pelaksanaan',
        'lama_hari' => 'Data Kegiatan › Pelaksanaan',
        'peserta' => 'Data Kegiatan › Pelaksanaan',
        'kode_rktm' => 'Kode & Klasifikasi', 'uraian_rktm' => 'Kode & Klasifikasi', 'kode_sk' => 'Kode & Klasifikasi', 'uraian_sk' => 'Kode & Klasifikasi',
        'kode_iksk' => 'Kode & Klasifikasi', 'uraian_iksk' => 'Kode & Klasifikasi', 'kode_edm' => 'Kode & Klasifikasi', 'uraian_edm' => 'Kode & Klasifikasi',
        'budaya_mutu' => 'Kode & Klasifikasi',
        'rab' => 'RAB', 'total' => 'RAB', 'terbilang' => 'RAB', 'sumber_dana' => 'Data Kegiatan › Sumber dana',
        'matrik' => 'Matrik & Jadwal', 'jadwal' => 'Matrik & Jadwal',
        'ttd_kepala' => 'Pengaturan › Profil & Pejabat', 'ttd_leading' => 'Pengaturan › Profil & Pejabat', 'ttd_komite' => 'Pengaturan › Profil & Pejabat',
        'kop' => 'Pengaturan › Profil & Pejabat', 'logo' => 'Pengaturan › Profil & Pejabat',
    ];

    /**
     * @param  array<string, mixed>  $kegiatan  Data kegiatan (boleh tanpa id untuk kegiatan belum tersimpan).
     * @return array<string, string|list<string>|null>
     */
    public static function untuk(array $kegiatan, ?string $sumber): array
    {
        $tahun = StubData::tahunAktif()['tahun'];
        $madrasah = StubData::madrasah();
        $pejabat = StubData::pejabat();
        $teks = fn (string $kunci) => ($isi = trim((string) ($kegiatan[$kunci] ?? ''))) === '' ? null : $isi;
        $nama = $teks('nama_kegiatan');
        $kode = fn (array $daftar, string $kunci, string $cari = 'kode') => MasterKode::cari($daftar, $kegiatan[$kunci] ?? null, $cari);
        $rktm = $kode(MasterKode::kodeRktm(), 'kode_rktm');
        $sk = $kode(MasterKode::kodeSk(), 'kode_sk');
        $iksk = $kode(MasterKode::kodeIksk(), 'kode_iksk');
        $edm = $kode(MasterKode::kodeEdm(), 'kode_edm');
        $budaya = $kode(MasterKode::budayaMutu(), 'budaya_mutu', 'nomor');
        $leading = MasterKode::cari(MasterKode::leadings(), $kegiatan['leading'] ?? null);

        $anggaran = $sumber ? (AnggaranKegiatan::perSumber($kegiatan)[$sumber] ?? null) : null;
        $angka = $anggaran ? AngkaDokumen::dari($anggaran->hasil, $sumber, $tahun, $madrasah['nama_singkat']) : null;

        $id = filter_var($kegiatan['id'] ?? null, FILTER_VALIDATE_INT) ?: null;
        $matrik = $id ? app(MatrikJadwalRepository::class)->matrik($id) : MatrikTahapan::dari($kegiatan);
        $jadwal = $id ? app(MatrikJadwalRepository::class)->jadwal($id) : JadwalKegiatan::dari($kegiatan);

        $lama = filter_var($kegiatan['lama_hari'] ?? null, FILTER_VALIDATE_INT) ?: null;
        $jumlahPeserta = filter_var($kegiatan['peserta_jumlah'] ?? null, FILTER_VALIDATE_INT) ?: null;
        $jenisPeserta = $teks('peserta_jenis');
        $ttd = fn (array $p) => $p['nama'] ? array_values(array_filter([$p['jabatan'], $p['nama'], $p['nip'] ? 'NIP. '.$p['nip'] : null])) : null;
        $pembuat = StubData::currentUser();

        return [
            'tahun_anggaran' => (string) $tahun,
            'nama_kegiatan' => $nama,
            'nama_kegiatan_cover' => $teks('nama_kegiatan_cover') ?? ($nama ? mb_strtoupper($nama) : null),
            'tanggal_dokumen' => Format::tanggal($kegiatan['tanggal_dokumen'] ?? null),
            'tanggal_laporan' => Format::tanggal($kegiatan['tanggal_laporan'] ?? null),
            'tujuan' => $teks('tujuan'),
            'sasaran' => $teks('sasaran'),
            'tempat' => $teks('tempat'),
            'lama_hari' => $lama ? (string) $lama : null,
            'peserta' => $jumlahPeserta ? $jumlahPeserta.' orang'.($jenisPeserta ? " ({$jenisPeserta})" : '') : $jenisPeserta,
            'latar_belakang' => StubData::latarBelakangBaku($tahun).($nama ? ' '.KalimatKak::latarBelakangKegiatan($nama) : ''),
            'dasar_hukum' => StubData::dasarHukum($tahun),
            'kode_rktm' => $rktm['kode'] ?? null,
            'uraian_rktm' => $rktm ? ($rktm['sub_kegiatan'] ?? $rktm['kegiatan'] ?? null) : null,
            'kode_sk' => $sk['kode'] ?? null,
            'uraian_sk' => $sk['uraian'] ?? null,
            'kode_iksk' => $iksk['kode'] ?? null,
            'uraian_iksk' => $iksk['uraian'] ?? null,
            'kode_edm' => $edm['kode'] ?? null,
            'uraian_edm' => $edm['uraian'] ?? null,
            'budaya_mutu' => $budaya ? "{$budaya['nomor']}. {$budaya['nama']}" : null,
            'rab' => $anggaran && $anggaran->jumlahItem() > 0 ? "[Tabel rincian RAB {$sumber} — {$anggaran->jumlahItem()} baris]" : null,
            'total' => $angka?->adaRincian() ? $angka->nominal() : null,
            'terbilang' => $angka?->adaRincian() ? $angka->terbilang() : null,
            'sumber_dana' => $angka?->sebutanSumber(),
            'matrik' => $matrik->jumlahTanda() > 0 ? "[Tabel matrik tahapan — {$matrik->jumlahTanda()} tanda bulan]" : null,
            'jadwal' => $jadwal->jumlahTerisi() > 0 ? "[Tabel jadwal kegiatan — {$jadwal->jumlahTerisi()} baris]" : null,
            'ttd_kepala' => $ttd($pejabat['kepala']),
            'ttd_leading' => $leading && $pembuat['role'] === 'leading' && in_array($kegiatan['leading'] ?? null, $pembuat['leadings'], true)
                ? [$leading['jabatan_penanda_tangan'], $pembuat['name']] : null,
            'ttd_komite' => $ttd($pejabat['komite']),
            'nama_madrasah' => $madrasah['nama'],
            'kop' => blank($madrasah['alamat'] ?? null) ? null : array_values(array_filter([mb_strtoupper($madrasah['nama']), $madrasah['alamat'], $madrasah['telepon'] ?? null])),
            'kota' => $madrasah['kota'],
            'logo' => blank($madrasah['logo_path'] ?? null) ? null : '[Logo madrasah]',
        ];
    }
}
