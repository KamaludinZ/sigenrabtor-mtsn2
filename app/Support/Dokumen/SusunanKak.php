<?php

namespace App\Support\Dokumen;

use App\Contracts\MatrikJadwalRepository;
use App\Enums\StatusKegiatan;
use App\Support\Format;
use App\Support\Kegiatan\DataPelaksanaan;
use App\Support\Kegiatan\JadwalKegiatan;
use App\Support\Kegiatan\JadwalPelaksanaan;
use App\Support\Kegiatan\KalimatKak;
use App\Support\Kegiatan\MatrikTahapan;
use App\Support\MasterKode;
use App\Support\Pengaturan\DaftarDasarHukum;
use App\Support\Pengaturan\PenandaTangan;
use App\Support\Pengaturan\TeksBaku;
use App\Support\Rab\AnggaranKegiatan;
use App\Support\Rab\AnggaranSumber;
use App\Support\Rab\PeringatanPagu;
use App\Support\Stub\StubData;
use Carbon\CarbonImmutable;

/**
 * Susunan isi KAK/TOR satu kegiatan × sumber dana (PRD 7B): cover lalu sembilan bagian baku, siap ditampilkan
 * oleh <x-dokumen.kak-tor>. Isian yang belum lengkap menjadi blok "kosong" dan tercatat di $kurang; dokumen
 * yang belum disetujui atau belum lengkap ditandai DRAF (PRD aturan 8), begitu pula selama RAB-nya melebihi pagu.
 *
 * Jenis blok isi: paragraf {teks}, subjudul {teks}, daftar {butir, bernomor}, kosong {pesan},
 * menunggu {pesan} (bagian yang datanya berasal dari fitur yang belum tersedia), pengesahan {kolom},
 * matrik {tahun, tahapan: [{nomor, judul, butir: [{uraian, bulan}]}]} (bagian 4), jadwal {baris: [{nomor, uraian, waktu}]}
 * (bagian 7), pagu (penanda batas pagu di bagian Pembiayaan; isinya dari $peringatanPagu, tidak ikut tercetak).
 *
 * Matrik & jadwal dibaca dari MatrikJadwalRepository (kegiatan tersimpan) kecuali diberikan langsung — halaman
 * Matrik & Jadwal memberikan isian yang sedang disunting agar pratinjaunya ikut perubahan yang belum disimpan.
 */
final readonly class SusunanKak
{
    /**
     * @param  array{judul: string, kegiatan: string, madrasah: string, tahun: string, sumber: string|null}  $cover
     * @param  list<array{nomor: int, judul: string, isi: list<array<string, mixed>>}>  $bagian
     * @param  list<string>  $kurang
     * @param  list<string>  $daftarSumber
     * @param  PeringatanPagu|null  $peringatanPagu  Peringatan batas pagu RAB sumber ini; null bila tepat pagu / RAB kosong.
     * @param  array<string, string|null>  $statusPagu  Sumber → status pagu RAB-nya (null bila RAB belum berisi), untuk tab dokumen.
     * @param  array<int, list<string>>  $kurangPerBagian  Nomor bagian → isian yang belum lengkap di bagian itu (pratinjau sebagian).
     */
    public function __construct(
        public array $cover,
        public array $bagian,
        public array $kurang,
        public bool $draf,
        public ?string $sumber,
        public array $daftarSumber,
        public ?PeringatanPagu $peringatanPagu = null,
        public array $statusPagu = [],
        public array $kurangPerBagian = [],
    ) {}

    /**
     * @param  array<string, mixed>  $kegiatan  Data kegiatan (bentuk penyimpanan; boleh belum tersimpan).
     */
    public static function dari(array $kegiatan, ?string $sumber = null, ?MatrikTahapan $matrik = null, ?JadwalKegiatan $jadwal = null, ?int $tahun = null): self
    {
        $tahun ??= StubData::tahunAktif()['tahun'];
        $id = filter_var($kegiatan['id'] ?? null, FILTER_VALIDATE_INT) ?: null;
        $matrik ??= $id ? app(MatrikJadwalRepository::class)->matrik($id) : MatrikTahapan::dari($kegiatan);
        $jadwal ??= $id ? app(MatrikJadwalRepository::class)->jadwal($id) : JadwalKegiatan::dari($kegiatan);
        $madrasah = StubData::madrasah();
        $nama = trim((string) ($kegiatan['nama_kegiatan'] ?? '')) ?: '…';
        $kurang = [];

        $anggaran = AnggaranKegiatan::perSumber($kegiatan);
        $daftarSumber = array_keys($anggaran);
        $sumber = in_array($sumber, $daftarSumber, true) ? $sumber : ($daftarSumber[0] ?? null);

        // Kekurangan dicatat juga per bagian ($nomor = bagian yang sedang disusun) untuk pratinjau sebagian.
        $nomor = 0;
        $kurangPerBagian = [];
        $catat = function (string $apa) use (&$kurang, &$kurangPerBagian, &$nomor): void {
            $kurang[] = $apa;
            $kurangPerBagian[$nomor][] = $apa;
        };
        $kosong = function (string $apa) use ($catat): array {
            $catat($apa);

            return ['jenis' => 'kosong', 'pesan' => 'Lengkapi '.$apa.'.'];
        };
        $isi = fn (?string $teks, string $apa) => filled($teks) ? ['jenis' => 'paragraf', 'teks' => $teks] : $kosong($apa);

        // Bagian disusun berurutan supaya $kurang mengikuti urutan dokumen.
        $bagian = [];

        $nomor = 1;
        $latar = [['jenis' => 'paragraf', 'teks' => StubData::latarBelakangBaku($tahun)]];
        if (filled($kegiatan['latar_belakang'] ?? null)) {
            $latar[] = ['jenis' => 'paragraf', 'teks' => trim($kegiatan['latar_belakang'])];
        }
        if ($nama === '…') {
            $latar[] = $kosong('nama kegiatan');
        } else {
            foreach (TeksBaku::kosong(StubData::kalimatPenutupLatar($tahun), TeksBaku::nilaiKegiatan($kegiatan)) as $label) {
                $catat(mb_strtolower($label).' (kalimat penutup latar belakang)');
            }
            $latar[] = ['jenis' => 'paragraf', 'teks' => KalimatKak::latarBelakangKegiatan($kegiatan, $tahun)];
        }
        $bagian[] = ['nomor' => 1, 'judul' => 'Latar Belakang', 'isi' => $latar];

        $nomor = 2;
        $dasarHukum = StubData::dasarHukum($tahun);
        if (DaftarDasarHukum::skKurang(StubData::dasarHukumMentah($tahun), $tahun)) {
            $catat('nomor & tanggal SK RKTM/RKAM (Pengaturan › Dasar hukum)');
        }
        $bagian[] = ['nomor' => 2, 'judul' => 'Dasar Hukum', 'isi' => [$dasarHukum === []
            ? $kosong('dasar hukum TA '.$tahun.' (Pengaturan › Dasar hukum)')
            : ['jenis' => 'daftar', 'bernomor' => true, 'butir' => $dasarHukum]]];

        $nomor = 3;
        $bagian[] = ['nomor' => 3, 'judul' => 'Tujuan dan Sasaran', 'isi' => [
            ['jenis' => 'subjudul', 'teks' => 'A. Tujuan'],
            $isi(filled($kegiatan['tujuan'] ?? null) ? KalimatKak::tujuan($nama, $kegiatan['tujuan']) : null, 'tujuan kegiatan'),
            ['jenis' => 'subjudul', 'teks' => 'B. Sasaran'],
            $isi(filled($kegiatan['sasaran'] ?? null) ? KalimatKak::sasaran($kegiatan['sasaran']) : null, 'sasaran kegiatan'),
        ]];

        // 4. Tahapan: pelaksanaan & pelaporan bergantung pada jadwal.
        $nomor = 4;
        $pelaksanaan = DataPelaksanaan::dari($kegiatan);
        $batasLaporan = self::tanggal($kegiatan['tanggal_laporan'] ?? null);

        $tahapan = [
            ['jenis' => 'subjudul', 'teks' => 'a. Persiapan'],
            ['jenis' => 'daftar', 'bernomor' => true, 'butir' => KalimatKak::persiapan($nama)],
            ['jenis' => 'subjudul', 'teks' => 'b. Pelaksanaan'],
            $isi($pelaksanaan->kalimatPelaksanaan($nama), 'tanggal mulai & lama kegiatan'),
            ['jenis' => 'subjudul', 'teks' => 'c. Pelaporan'],
            ['jenis' => 'daftar', 'bernomor' => true, 'butir' => array_values(array_filter([
                KalimatKak::analisisHasil($nama),
                $batasLaporan ? KalimatKak::pelaporan($nama, $batasLaporan) : null,
            ]))],
        ];
        if (! $batasLaporan) {
            $tahapan[] = $kosong('tanggal pelaporan');
        }
        $tahapan = [...$tahapan, ...self::blokMatrik($matrik, $tahun, $kosong)];
        $bagian[] = ['nomor' => 4, 'judul' => 'Tahapan Kegiatan', 'isi' => $tahapan];

        $nomor = 5;
        $bagian[] = ['nomor' => 5, 'judul' => 'Tempat Pelaksanaan', 'isi' => [
            $isi($pelaksanaan->kalimatTempat($nama), 'tempat pelaksanaan'),
        ]];

        $nomor = 6;
        $leading = MasterKode::cari(MasterKode::leadings(), $kegiatan['leading'] ?? null);
        $bagian[] = ['nomor' => 6, 'judul' => 'Penyelenggara Kegiatan', 'isi' => [
            ['jenis' => 'subjudul', 'teks' => 'a. Pelaksana'],
            $isi($leading ? KalimatKak::pelaksana($nama, $leading['jabatan_penanda_tangan']) : null, 'leading penanggung jawab (Kode & Klasifikasi)'),
            ['jenis' => 'subjudul', 'teks' => 'b. Pengarah'],
            ['jenis' => 'paragraf', 'teks' => KalimatKak::pengarah($nama, $madrasah['nama'])],
        ]];

        $nomor = 7;
        $bagian[] = ['nomor' => 7, 'judul' => 'Jadwal Kegiatan', 'isi' => self::blokJadwal($jadwal, $tahun, $kosong)];

        $nomor = 8;
        $angka = $sumber ? AngkaDokumen::dari($anggaran[$sumber]->hasil, $sumber, $tahun, $madrasah['nama_singkat']) : null;
        $peringatanPagu = $sumber ? PeringatanPagu::untuk($sumber, $anggaran[$sumber]->hasil) : null;
        $pembiayaan = [match (true) {
            $angka === null => $kosong('sumber dana (bagian Sumber Dana)'),
            ! $angka->adaRincian() => $kosong("rincian RAB {$sumber}"),
            default => ['jenis' => 'paragraf', 'teks' => $angka->kalimatPembiayaan()],
        }];
        if ($peringatanPagu) {
            $pembiayaan[] = ['jenis' => 'pagu'];
        }
        $bagian[] = ['nomor' => 8, 'judul' => 'Pembiayaan', 'isi' => $pembiayaan];

        $nomor = 9;
        $bagian[] = ['nomor' => 9, 'judul' => 'Lembar Pengesahan', 'isi' => [self::pengesahan($kegiatan, $sumber, $madrasah['kota'], $kosong)]];

        $status = StatusKegiatan::tryFrom($kegiatan['status'] ?? '') ?? StatusKegiatan::Draf;
        $cover = [
            'judul' => 'Kerangka Acuan Kerja (KAK) / Term of Reference (TOR)',
            'kegiatan' => 'Kegiatan '.(trim((string) ($kegiatan['nama_kegiatan_cover'] ?? '')) ?: mb_strtoupper($nama)),
            'madrasah' => mb_strtoupper($madrasah['nama']),
            'tahun' => "Tahun Anggaran {$tahun}",
            'sumber' => $sumber ? 'Sumber dana: '.mb_strtoupper($sumber) : null,
        ];

        return new self(
            $cover,
            $bagian,
            array_values(array_unique($kurang)),
            ! in_array($status, [StatusKegiatan::Disetujui, StatusKegiatan::Final], true) || $kurang !== [] || ($peringatanPagu?->status->melebihi() ?? false),
            $sumber,
            $daftarSumber,
            $peringatanPagu,
            array_map(fn (AnggaranSumber $a) => $a->jumlahItem() > 0 ? $a->statusPagu() : null, $anggaran),
            array_map(fn (array $daftar) => array_values(array_unique($daftar)), $kurangPerBagian),
        );
    }

    /**
     * Matrik Tahapan di akhir bagian 4: tabel tahapan × bulan 1–12 (tanda "v"), tampil bila ada tanda; penanda
     * "Lengkapi" selama ada tahapan tanpa bulan (syarat kelengkapan cetak, PRD aturan 8).
     *
     * @return list<array<string, mixed>>
     */
    private static function blokMatrik(MatrikTahapan $matrik, int $tahun, callable $kosong): array
    {
        $blok = [['jenis' => 'subjudul', 'teks' => 'Matrik Tahapan Kegiatan']];

        if ($matrik->jumlahTanda() > 0) {
            $blok[] = ['jenis' => 'matrik', 'tahun' => $tahun, 'tahapan' => array_values(array_map(
                fn (string $kunci, string $judul, int $i) => ['nomor' => $i + 1, 'judul' => $judul, 'butir' => $matrik->butir[$kunci] ?? []],
                array_keys(MatrikTahapan::TAHAPAN), MatrikTahapan::TAHAPAN, range(0, count(MatrikTahapan::TAHAPAN) - 1),
            ))];
        }

        if (! $matrik->lengkap()) {
            $blok[] = $kosong('matrik tahapan (tab Matrik & Jadwal)');
        }

        return $blok;
    }

    /**
     * Tabel Jadwal Kegiatan (bagian 7): No, Uraian, Waktu Pelaksanaan — baris yang berisi saja, tahunnya tahun aktif
     * (PRD aturan 3); penanda "Lengkapi" selama ada baris tanpa uraian/waktu atau jadwal kosong.
     *
     * @return list<array<string, mixed>>
     */
    private static function blokJadwal(JadwalKegiatan $jadwal, int $tahun, callable $kosong): array
    {
        $blok = [];
        $terisi = array_values(array_filter($jadwal->baris, fn (array $b) => $b['uraian'] !== '' || $b['bulan'] !== []));

        if ($terisi !== []) {
            $blok[] = ['jenis' => 'jadwal', 'baris' => array_map(
                fn (array $b, int $i) => ['nomor' => $i + 1, 'uraian' => $b['uraian'], 'waktu' => JadwalPelaksanaan::teksBulan($b['bulan'], $tahun)],
                $terisi, array_keys($terisi),
            )];
        }

        if (! $jadwal->lengkap()) {
            $blok[] = $kosong('jadwal kegiatan (tab Matrik & Jadwal)');
        }

        return $blok;
    }

    /**
     * Blok tanda tangan: Mengetahui (Ketua Komite; per sumber dana menurut PenandaTangan::mengetahui, bawaan DIPA & Komite),
     * Pembuat (pejabat leading), Menyetujui (Kepala).
     *
     * @param  array<string, mixed>  $kegiatan
     * @return array{jenis: string, kolom: list<array{peran: string, jabatan: string, nama: string|null, nip: string|null, tempat_tanggal: string|null}>}
     */
    private static function pengesahan(array $kegiatan, ?string $sumber, string $kota, callable $kosong): array
    {
        $pejabat = PenandaTangan::pimpinan();
        $pembuat = PenandaTangan::pembuat($kegiatan);
        $tanggal = Format::tanggal($kegiatan['tanggal_dokumen'] ?? null);

        if (! $tanggal) {
            $kosong('tanggal dokumen');
        }

        $kolom = [];
        if (($mengetahui = PenandaTangan::mengetahui($sumber)) !== null) {
            $kolom[] = ['peran' => 'Mengetahui,', 'jabatan' => $mengetahui['jabatan'], 'nama' => $mengetahui['nama'], 'nip' => $mengetahui['nip'], 'tempat_tanggal' => null];
        }
        $kolom[] = ['peran' => 'Pembuat,', 'jabatan' => $pembuat['jabatan'] ?? 'Pejabat leading', 'nama' => $pembuat['nama'] ?? null, 'nip' => $pembuat['nip'] ?? null, 'tempat_tanggal' => $kota.', '.($tanggal ?? '……………')];
        $kolom[] = ['peran' => 'Menyetujui,', 'jabatan' => $pejabat['kepala']['jabatan'], 'nama' => $pejabat['kepala']['nama'], 'nip' => $pejabat['kepala']['nip'], 'tempat_tanggal' => null];

        return ['jenis' => 'pengesahan', 'kolom' => $kolom];
    }

    private static function tanggal(?string $nilai): ?CarbonImmutable
    {
        return filled($nilai) ? rescue(fn () => CarbonImmutable::parse($nilai)->startOfDay(), null, false) : null;
    }
}
