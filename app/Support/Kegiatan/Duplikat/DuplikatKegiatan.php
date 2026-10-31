<?php

namespace App\Support\Kegiatan\Duplikat;

use App\Contracts\DataPaguRepository;
use App\Contracts\KegiatanRepository;
use App\Contracts\MatrikJadwalRepository;
use App\Contracts\RabRepository;
use App\Contracts\TahunAnggaranRepository;
use App\Enums\StatusKegiatan;
use App\Support\Dokumen\PilihanTemplate;
use App\Support\Kegiatan\DataPelaksanaan;
use App\Support\Kegiatan\Impor\PemeriksaImporKegiatan;
use App\Support\Kegiatan\JadwalPelaksanaan;
use App\Support\Kegiatan\KodeHilang;
use App\Support\Kegiatan\RiwayatStatus;
use App\Support\Kegiatan\TahunKegiatan;
use App\Support\Master\IkskPerSk;
use App\Support\MasterKode;
use App\Support\Pagu\DataPagu;
use App\Support\Pengaturan\TautanAkunLeading;
use App\Support\Rab\AnggaranKegiatan;
use App\Support\Stub\StubData;
use Carbon\CarbonImmutable;

/**
 * Duplikat kegiatan tahun lalu menjadi draf tahun aktif (PRD §19, F22): sumber = kegiatan tahun anggaran lain dalam
 * cakupan akun (admin semua, akun leading leading-nya), dengan daftar hal yang diperbarui otomatis saat disalin.
 */
final class DuplikatKegiatan
{
    /** Hal yang berubah saat kegiatan diduplikat → [judul, keterangan] ({tahun} = tahun aktif). */
    public const PERUBAHAN = [
        'tahun' => ['Tahun anggaran', 'Menjadi TA {tahun}; cover, judul, dasar hukum, dan tanggal mengikuti tahun itu.'],
        'status' => ['Status', 'Dimulai sebagai Draf — riwayat pemeriksaan, arsip final, dan kunci tidak ikut.'],
        'pejabat' => ['Penanda tangan', 'Memakai pejabat TA {tahun} (Kepala, Ketua Komite, pejabat leading).'],
        'kode' => ['Kode master', 'Dicocokkan ke master TA {tahun}; kode yang tidak ada lagi dikosongkan untuk dipilih ulang.'],
        'jadwal' => ['Tanggal & jadwal', 'Tanggal mulai, laporan, dan dokumen digeser ke tahun {tahun}; bulan jadwal & matrik tetap.'],
        'anggaran' => ['Pagu & RAB', 'Rincian RAB disalin; pagu diambil dari Data Pagu TA {tahun} bila namanya sama.'],
    ];

    /** Kolom data kegiatan yang disalin (identitas, pelaksanaan, kode, panitia, konteks, sumber dana). */
    public const KOLOM = [
        'nama_kegiatan', 'nama_kegiatan_cover', 'cover_manual', 'latar_belakang', 'tujuan', 'sasaran', 'tanggal_dokumen',
        'tanggal_laporan', 'laporan_otomatis', 'tanggal_mulai', 'lama_hari', 'hari_kerja', 'bulan_pelaksanaan', 'panitia',
        'leading', 'kode_rktm', 'kode_sk', 'kode_iksk', 'kode_edm', 'budaya_mutu', 'tempat', 'peserta_jumlah', 'peserta_jenis',
        'uraian_pelaksanaan', 'konteks', 'sumber_dana',
    ];

    /**
     * Rencana salinan satu kegiatan ke tahun aktif tanpa menyimpan apa pun: data draf baru beserta catatan perubahan
     * yang perlu diperhatikan (kode yang tidak ada di master tahun aktif dikosongkan, pagu dari Data Pagu atau disalin).
     *
     * @param  array<string, mixed>  $kegiatan  Kegiatan sumber.
     * @return array{data: array<string, mixed>, catatan: list<array{jenis: string, teks: string}>}
     */
    public static function rencana(array $kegiatan): array
    {
        $tahun = StubData::tahunAktif()['tahun'];
        $selisih = $tahun - TahunKegiatan::dari($kegiatan);
        $data = array_intersect_key($kegiatan, array_flip(self::KOLOM));
        $catatan = [];

        foreach (['tanggal_dokumen', 'tanggal_laporan', 'tanggal_mulai'] as $kolom) {
            $data[$kolom] = self::geserTahun($data[$kolom] ?? null, $selisih);
        }

        // Kode yang tidak ada di master tahun aktif dikosongkan; kode lamanya dicatat sebagai penanda (KodeHilang).
        $data['kode_hilang'] = [];
        foreach (KodeHilang::tidakAdaDi($data, $tahun) as ['kolom' => $kolom, 'label' => $label, 'kode' => $kode]) {
            $data[$kolom] = null;
            $data['kode_hilang'][$kolom] = KodeHilang::catatan($kode, 'duplikat', TahunKegiatan::dari($kegiatan));
            $catatan[] = ['jenis' => 'kode', 'teks' => "{$label} {$kode} tidak ada di master TA {$tahun} — pilih ulang."];
        }
        // IKSK yang ada di master tahun aktif tetapi bukan indikator SK-nya di tahun itu juga dikosongkan.
        if (filled($data['kode_iksk'] ?? null) && IkskPerSk::galat($data['kode_sk'] ?? null, (string) $data['kode_iksk'], $tahun) !== null) {
            $catatan[] = ['jenis' => 'kode', 'teks' => "{$data['kode_iksk']} bukan indikator ".($data['kode_sk'] ?? 'SK yang dipilih')." di master TA {$tahun} — pilih ulang."];
            $data['kode_iksk'] = null;
        }

        $pagu = self::dataPagu((string) ($data['nama_kegiatan'] ?? ''), $tahun);
        if ($pagu !== null) {
            $data['sumber_dana'] = DataPagu::sumberDana($pagu);
            $data['data_pagu_id'] = $pagu['id'];
            $catatan[] = ['jenis' => 'pagu', 'teks' => 'Pagu diambil dari Data Pagu TA '.$tahun.'.'];
        } elseif (filled($data['sumber_dana'] ?? null)) {
            $catatan[] = ['jenis' => 'pagu_lama', 'teks' => 'Pagu disalin dari TA '.TahunKegiatan::dari($kegiatan).' — periksa pagu TA '.$tahun.'.'];
        }

        return ['data' => $data, 'catatan' => $catatan];
    }

    /**
     * Salin satu kegiatan menjadi draf tahun aktif: data kegiatan (rencana()), rincian & termin RAB per sumber dana,
     * matrik & jadwal. Mengembalikan id draf baru beserta catatannya. `$keterangan` = catatan entri riwayat `dibuat`
     * (bawaan "Diduplikat dari TA …"; salinan dokumen di tahun yang sama memakai keterangannya sendiri).
     *
     * @param  array<string, mixed>  $kegiatan
     * @param  array{name: string, role: string}  $pengguna
     * @return array{id: int, nama: string, catatan: list<array{jenis: string, teks: string}>}
     */
    public static function jalankan(array $kegiatan, array $pengguna, ?string $keterangan = null): array
    {
        ['data' => $data, 'catatan' => $catatan] = self::rencana($kegiatan);
        $sumberId = (int) $kegiatan['id'];
        $selisih = StubData::tahunAktif()['tahun'] - TahunKegiatan::dari($kegiatan);

        // Pilihan template ikut tersalin hanya di tahun yang sama (id template berlaku per tahun) — selain itu default leading.
        $id = app(KegiatanRepository::class)->simpan(PilihanTemplate::isiAwal($data + [
            'template_kak' => (int) $selisih === 0 ? ($kegiatan['template_kak'] ?? null) : null,
            'status' => 'draf',
            'riwayat_status' => [RiwayatStatus::entri('dibuat', StatusKegiatan::Draf, $pengguna, $keterangan ?? 'Diduplikat dari TA '.TahunKegiatan::dari($kegiatan).'.')],
            'duplikat_dari' => ['id' => $sumberId, 'tahun' => TahunKegiatan::dari($kegiatan)],
        ]));

        $rab = app(RabRepository::class);
        foreach (AnggaranKegiatan::untuk($kegiatan) as $anggaran) {
            foreach ($rab->rincian($sumberId, $anggaran->sumber) as $item) {
                $rab->tambahItem($id, $anggaran->sumber, array_intersect_key($item, array_flip(['kode', 'uraian', 'kategori', 'volume', 'satuan', 'harga_satuan'])));
            }
            if ($anggaran->adaTermin()) {
                $rab->simpanTermin($id, $anggaran->sumber, array_map(fn (array $t) => ['tanggal' => self::geserTahun($t['tanggal'] ?? null, $selisih)] + $t, $anggaran->termin));
            }
        }

        $matrikJadwal = app(MatrikJadwalRepository::class);
        $matrikJadwal->simpan($id, $matrikJadwal->matrik($sumberId), $matrikJadwal->jadwal($sumberId));

        return ['id' => $id, 'nama' => (string) $data['nama_kegiatan'], 'catatan' => $catatan];
    }

    /** "2025-07-14" → tanggal yang sama $selisih tahun kemudian (29 Februari → 28 Februari bila bukan kabisat). */
    public static function geserTahun(?string $tanggal, int $selisih): ?string
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', (string) $tanggal, $b)) {
            return blank($tanggal) ? null : $tanggal;
        }
        $tahun = (int) $b[1] + $selisih;
        $hari = min((int) $b[3], CarbonImmutable::create($tahun, (int) $b[2], 1)->daysInMonth);

        return sprintf('%04d-%02d-%02d', $tahun, (int) $b[2], $hari);
    }

    /** Data Pagu tahun $tahun bernama sama yang belum dipakai kegiatan lain. */
    private static function dataPagu(string $nama, int $tahun): ?array
    {
        $semua = app(DataPaguRepository::class)->semua($tahun);
        $pemakaian = DataPagu::pemakaian($semua);

        return collect($semua)->first(fn (array $p) => DataPagu::kunci($p['nama_kegiatan']) === DataPagu::kunci($nama) && $pemakaian[$p['id']] === null);
    }

    /**
     * Tahun anggaran yang bisa menjadi sumber duplikat (selain tahun aktif), terbaru dulu.
     *
     * @return list<int>
     */
    public static function tahunSumber(): array
    {
        $aktif = StubData::tahunAktif()['tahun'];

        return array_values(array_filter(array_column(app(TahunAnggaranRepository::class)->semua(), 'tahun'), fn (int $t) => $t !== $aktif));
    }

    /**
     * Kegiatan tahun $tahun dalam cakupan akun, sebagai baris pilihan: identitas, kode RKTM, sumber dana & jumlah rincian
     * RAB, bulan, ringkasan isian, dan `sudah_ada` (id kegiatan tahun aktif bernama sama — tidak bisa diduplikat lagi).
     *
     * @param  list<array<string, mixed>>  $kegiatan  Semua kegiatan (semua tahun).
     * @param  array{role: string, leadings: list<string>}  $pengguna
     * @return list<array<string, mixed>>
     */
    public static function sumber(array $kegiatan, int $tahun, array $pengguna): array
    {
        $tahunAktif = StubData::tahunAktif()['tahun'];
        $aktif = [];
        foreach ($kegiatan as $k) {
            if (TahunKegiatan::dari($k) === $tahunAktif) {
                $aktif[PemeriksaImporKegiatan::kunciNama((string) ($k['nama_kegiatan'] ?? ''))] ??= (int) $k['id'];
            }
        }

        $baris = [];
        AnggaranKegiatan::muat($kegiatan);
        foreach ($kegiatan as $k) {
            if (TahunKegiatan::dari($k) !== $tahun || ! TautanAkunLeading::bolehAkses($pengguna, $k['leading'] ?? null)) {
                continue;
            }
            $anggaran = AnggaranKegiatan::untuk($k);
            $nama = trim((string) ($k['nama_kegiatan'] ?? '')) ?: 'Kegiatan tanpa nama';
            $pelaksanaan = DataPelaksanaan::dari($k);
            $baris[] = [
                'id' => (int) $k['id'],
                'nama' => $nama,
                'leading' => $k['leading'] ?? null,
                'nama_leading' => MasterKode::leading($k['leading'] ?? null)['nama_unit'] ?? null,
                'kode_rktm' => $k['kode_rktm'] ?? null,
                'sumber' => array_map(fn ($a) => ['sumber' => $a->sumber, 'total' => $a->total(), 'item' => $a->jumlahItem()], $anggaran),
                'total' => array_sum(array_map(fn ($a) => $a->total(), $anggaran)),
                'bulan' => JadwalPelaksanaan::teksBulan($pelaksanaan->bulan, $tahun),
                'tujuan' => trim((string) ($k['tujuan'] ?? '')) ?: null,
                'tempat' => $pelaksanaan->tempat ?: null,
                'peserta' => $pelaksanaan->pesertaJumlah ? $pelaksanaan->pesertaJumlah.' orang'.($pelaksanaan->pesertaJenis ? " ({$pelaksanaan->pesertaJenis})" : '') : ($pelaksanaan->pesertaJenis ?: null),
                'lama_hari' => $pelaksanaan->lamaHari,
                'status' => (string) ($k['status'] ?? 'draf'),
                'sudah_ada' => $aktif[PemeriksaImporKegiatan::kunciNama($nama)] ?? null,
                // Kode yang akan dikosongkan saat diduplikat (tidak ada di master tahun aktif).
                'kode_hilang' => KodeHilang::tidakAdaDi($k, $tahunAktif),
            ];
        }
        usort($baris, fn (array $a, array $b) => [$a['leading'] ?? '~', $a['nama']] <=> [$b['leading'] ?? '~', $b['nama']]);

        return $baris;
    }
}
