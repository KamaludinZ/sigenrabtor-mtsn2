<?php

namespace App\Support\Dokumen;

use App\Contracts\MatrikJadwalRepository;
use App\Enums\StatusKegiatan;
use App\Support\Format;
use App\Support\Kegiatan\DataPelaksanaan;
use App\Support\Kegiatan\JadwalKegiatan;
use App\Support\Kegiatan\JadwalPelaksanaan;
use App\Support\Kegiatan\KalimatKak;
use App\Support\Kegiatan\KunciDokumen;
use App\Support\Kegiatan\MatrikTahapan;
use App\Support\Kegiatan\TeksKhusus;
use App\Support\MasterKode;
use App\Support\Pengaturan\PejabatBerlaku;
use App\Support\Pengaturan\TeksBakuTercetak;
use App\Support\Rab\AnggaranKegiatan;
use App\Support\Rab\AnggaranSumber;
use App\Support\Rab\PeringatanPagu;
use App\Support\Stub\StubData;
use Carbon\CarbonImmutable;

/**
 * Susunan isi KAK/TOR satu kegiatan × sumber dana (PRD 7B): cover lalu bagian baku menurut format dokumennya
 * (FormatKakTor: sembilan bagian bernomor 1–9, atau lima bab I–V format TOR 2027 — bagiannya membawa `label` "I"…"V"),
 * siap ditampilkan oleh <x-dokumen.kak-tor>. Isian yang belum lengkap menjadi blok "kosong" dan tercatat di $kurang; dokumen
 * yang belum disetujui atau belum lengkap ditandai DRAF (PRD aturan 8), begitu pula selama RAB-nya melebihi pagu.
 *
 * Jenis blok isi: paragraf {teks}, subjudul {teks, tingkat?: 2 = sub-butir menjorok}, daftar {butir, bernomor}, kosong {pesan, asal, wajib},
 * menunggu {pesan} (bagian yang datanya berasal dari fitur yang belum tersedia), pengesahan {kolom},
 * matrik {tahun, tahapan: [{nomor, judul, butir: [{uraian, bulan}]}]} (bagian 4), jadwal {baris: [{nomor, uraian, waktu}]}
 * (bagian 7), pagu (penanda batas pagu di bagian Pembiayaan; isinya dari $peringatanPagu, tidak ikut tercetak).
 * Blok yang isinya berasal dari satu isian/teks khusus membawa `sunting` (kunci SuntingDokumen::BIDANG; daftar:
 * `sunting_butir` per butir) agar bisa disunting langsung di pratinjau — tidak ikut tercetak.
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
     * @param  KunciDokumen|null  $kunci  Jejak kunci selama kegiatan Final (cap "Final · terkunci" di pratinjau).
     * @param  StatusDraf|null  $statusDraf  Cap DRAF beserta alasannya (StatusDraf::hitung; $draf = $statusDraf->draf).
     * @param  string  $format  Format susunan (FormatKakTor::FORMAT).
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
        public ?KunciDokumen $kunci = null,
        public ?StatusDraf $statusDraf = null,
        public string $format = FormatKakTor::SEMBILAN_BAGIAN,
    ) {}

    /**
     * @param  array<string, mixed>  $kegiatan  Data kegiatan (bentuk penyimpanan; boleh belum tersimpan).
     * @param  string|null  $format  FormatKakTor; null = format yang berlaku untuk kegiatan itu (FormatKakTor::untuk).
     */
    public static function dari(array $kegiatan, ?string $sumber = null, ?MatrikTahapan $matrik = null, ?JadwalKegiatan $jadwal = null, ?int $tahun = null, ?string $format = null): self
    {
        $tahun ??= StubData::tahunAktif()['tahun'];
        $format = FormatKakTor::sah($format) ? $format : FormatKakTor::untuk($kegiatan, $tahun);
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
        // Blok kosong membawa `asal` (tempat mengisi, SusunanOtomatis::TEMPAT) dan `wajib` (menghalangi pengajuan) untuk
        // penanda di pratinjau (PenandaKosong).
        $kosong = function (string $apa, ?string $asal = null, bool $wajib = false) use ($catat): array {
            $catat($apa);

            return ['jenis' => 'kosong', 'pesan' => 'Lengkapi '.$apa.'.', 'asal' => $asal, 'wajib' => $wajib];
        };
        $isi = fn (?string $teks, string $apa, ?string $asal = null, bool $wajib = false) => filled($teks) ? ['jenis' => 'paragraf', 'teks' => $teks] : $kosong($apa, $asal, $wajib);

        // Isi bersama kedua format.
        $teksBaku = TeksBakuTercetak::dari($kegiatan, $tahun);
        $kurangBaku = $teksBaku->kurang();
        $latar = [['jenis' => 'paragraf', 'teks' => $teksBaku->latarBelakang]];
        if (filled($kegiatan['latar_belakang'] ?? null)) {
            $latar[] = ['jenis' => 'paragraf', 'teks' => trim($kegiatan['latar_belakang']), 'sunting' => 'latar_belakang'];
        }
        $penutupLatar = $teksBaku->kalimatPenutup !== null ? [['jenis' => 'paragraf', 'teks' => $teksBaku->kalimatPenutup]] : [];
        $dasarHukum = fn () => $teksBaku->dasarHukum === []
            ? $kosong('dasar hukum TA '.$tahun.' (Pengaturan › Dasar hukum)', 'dasar_hukum')
            : ['jenis' => 'daftar', 'bernomor' => true, 'butir' => $teksBaku->dasarHukum];
        $pelaksanaan = DataPelaksanaan::dari($kegiatan);
        $batasLaporan = self::tanggal($kegiatan['tanggal_laporan'] ?? null);
        $leading = MasterKode::leading($kegiatan['leading'] ?? null);
        $pelaksana = fn () => $isi($leading ? KalimatKak::pelaksana($nama, $leading['jabatan_penanda_tangan']) : null, 'leading penanggung jawab (Kode & Klasifikasi)', 'kode', true);
        // sunting_butir: butir ke-n yang bisa disunting di pratinjau (SuntingDokumen) — butir 1 = analisis hasil.
        $pelaporan = fn () => ['jenis' => 'daftar', 'bernomor' => true, 'tempat' => 'kak-pelaporan', 'sunting_butir' => ['analisis_hasil'], 'butir' => array_values(array_filter([
            TeksKhusus::cetak('analisis_hasil', $kegiatan),
            $batasLaporan ? KalimatKak::pelaporan($nama, $batasLaporan) : null,
        ]))];
        $angka = $sumber ? AngkaDokumen::dari($anggaran[$sumber]->hasil, $sumber, $tahun, $madrasah['nama_singkat']) : null;
        $peringatanPagu = $sumber ? PeringatanPagu::untuk($sumber, $anggaran[$sumber]->hasil) : null;
        $pembiayaan = function () use ($angka, $sumber, $peringatanPagu, $kosong): array {
            $blok = [match (true) {
                $angka === null => $kosong('sumber dana (bagian Sumber Dana)', 'sumber_dana'),
                ! $angka->adaRincian() => $kosong("rincian RAB {$sumber}", 'rab'),
                // `tempat` = kunci AngkaDokumen::tempatCetak(): jangkar #cetak-… di pratinjau.
                default => ['jenis' => 'paragraf', 'teks' => $angka->kalimatPembiayaan(), 'tempat' => 'kak-pembiayaan'],
            }];
            if ($peringatanPagu) {
                $blok[] = ['jenis' => 'pagu'];
            }

            return $blok;
        };

        // Bagian disusun berurutan supaya $kurang mengikuti urutan dokumen.
        $bagian = [];

        if ($format === FormatKakTor::LIMA_BAB) {
            // I. Pendahuluan: latar belakang, dasar hukum, maksud & tujuan.
            $nomor = 1;
            foreach ([...($kurangBaku[1] ?? []), ...($kurangBaku[2] ?? [])] as $apa) {
                $catat($apa);
            }
            $bagian[] = ['nomor' => 1, 'label' => 'I', 'judul' => FormatKakTor::BAGIAN[$format][1][0], 'isi' => [
                ['jenis' => 'subjudul', 'teks' => 'A. Latar Belakang'],
                ...$latar,
                ...$penutupLatar,
                ['jenis' => 'subjudul', 'teks' => 'B. Dasar Hukum'],
                $dasarHukum(),
                ['jenis' => 'subjudul', 'teks' => 'C. Maksud dan Tujuan'],
                ['sunting' => 'tujuan'] + $isi(filled($kegiatan['tujuan'] ?? null) ? KalimatKak::maksudTujuan($nama, $kegiatan['tujuan']) : null, 'tujuan kegiatan', 'identitas'),
            ]];

            // II. Kegiatan yang dilaksanakan.
            $nomor = 2;
            $selesai = $pelaksanaan->selesai();
            $waktuTempat = KalimatKak::waktuTempat($nama, $pelaksanaan->mulai, $selesai, $pelaksanaan->tempat);
            $isiBab = [
                ['jenis' => 'subjudul', 'teks' => '1. Nama Kegiatan'],
                $nama === '…' ? $kosong('nama kegiatan', 'identitas', true) : ['jenis' => 'paragraf', 'teks' => $nama],
                ['jenis' => 'subjudul', 'teks' => '2. Peserta'],
                $isi(KalimatKak::peserta($pelaksanaan->pesertaJumlah, $pelaksanaan->pesertaJenis), 'peserta kegiatan (jumlah & jenis)', 'pelaksanaan', true),
                ['jenis' => 'subjudul', 'teks' => '3. Waktu dan Tempat'],
            ];
            if ($waktuTempat !== null) {
                $isiBab[] = ['jenis' => 'paragraf', 'teks' => $waktuTempat, 'tempat' => 'kak-pelaksanaan'] + ($pelaksanaan->tempat !== '' ? ['sunting' => 'tempat'] : []);
            }
            if ($selesai === null) {
                $isiBab[] = $kosong('tanggal mulai & lama kegiatan', 'pelaksanaan', true);
            }
            if ($pelaksanaan->tempat === '') {
                $isiBab[] = ['sunting' => 'tempat'] + $kosong('tempat pelaksanaan', 'pelaksanaan', true);
            }
            $bagian[] = ['nomor' => 2, 'label' => 'II', 'judul' => FormatKakTor::BAGIAN[$format][2][0], 'isi' => [
                ...$isiBab,
                ['jenis' => 'subjudul', 'teks' => '4. Biaya'],
                ...$pembiayaan(),
                ['jenis' => 'subjudul', 'teks' => '5. Penyelenggaraan Kegiatan'],
                ['jenis' => 'subjudul', 'teks' => 'a. Penanggung Jawab', 'tingkat' => 2],
                ['jenis' => 'paragraf', 'teks' => TeksKhusus::cetak('penanggung_jawab', $kegiatan), 'sunting' => 'penanggung_jawab'],
                ['jenis' => 'subjudul', 'teks' => 'b. Pelaksana', 'tingkat' => 2],
                $pelaksana(),
                ['jenis' => 'subjudul', 'teks' => '6. Narasumber / Fasilitator dan Materi'],
                ['jenis' => 'paragraf', 'teks' => TeksKhusus::cetak('narasumber', $kegiatan), 'sunting' => 'narasumber'],
            ]];

            // III. Tahapan kegiatan.
            $nomor = 3;
            $tahapan = [
                ['jenis' => 'subjudul', 'teks' => '1. Persiapan'],
                ['jenis' => 'daftar', 'bernomor' => true, 'butir' => TeksKhusus::cetak('persiapan', $kegiatan), 'sunting' => 'persiapan'],
                ['jenis' => 'subjudul', 'teks' => '2. Pelaksanaan'],
                $pelaksanaan->lamaHari
                    ? ['jenis' => 'paragraf', 'teks' => KalimatKak::pelaksanaanLama($nama, $pelaksanaan->lamaHari, $pelaksanaan->uraian ?: null), 'sunting' => 'uraian_pelaksanaan']
                    : $kosong('tanggal mulai & lama kegiatan', 'pelaksanaan', true),
                ['jenis' => 'subjudul', 'teks' => '3. Pelaporan'],
                ['jenis' => 'paragraf', 'teks' => 'Tahap penyusunan pelaporan kegiatan adalah:'],
                $pelaporan(),
            ];
            if (! $batasLaporan) {
                $tahapan[] = $kosong('tanggal pelaporan', 'pelaksanaan');
            }
            $bagian[] = ['nomor' => 3, 'label' => 'III', 'judul' => FormatKakTor::BAGIAN[$format][3][0], 'isi' => [...$tahapan, ...self::blokMatrik($matrik, $tahun, $kosong, pengantar: true)]];

            // IV. Hasil yang akan dicapai.
            $nomor = 4;
            $bagian[] = ['nomor' => 4, 'label' => 'IV', 'judul' => FormatKakTor::BAGIAN[$format][4][0], 'isi' => [
                ['jenis' => 'subjudul', 'teks' => '1. Output'],
                ['jenis' => 'paragraf', 'teks' => TeksKhusus::cetak('output', $kegiatan), 'sunting' => 'output'],
                ['jenis' => 'subjudul', 'teks' => '2. Outcome'],
                ['jenis' => 'paragraf', 'teks' => TeksKhusus::cetak('outcome', $kegiatan), 'sunting' => 'outcome'],
            ]];

            // V. Penutup & tanda tangan.
            $nomor = 5;
            $bagian[] = ['nomor' => 5, 'label' => 'V', 'judul' => FormatKakTor::BAGIAN[$format][5][0], 'isi' => [
                ['jenis' => 'paragraf', 'teks' => KalimatKak::penutup($nama, $madrasah['nama_singkat'])],
                self::pengesahan($kegiatan, $sumber, $madrasah['kota'], $kosong, $tahun, $format),
            ]];
        } else {
            $nomor = 1;
            if ($nama === '…') {
                $latar[] = $kosong('nama kegiatan', 'identitas', true);
            }
            foreach ($kurangBaku[1] ?? [] as $apa) {
                $catat($apa);
            }
            $bagian[] = ['nomor' => 1, 'judul' => 'Latar Belakang', 'isi' => [...$latar, ...$penutupLatar]];

            $nomor = 2;
            foreach ($kurangBaku[2] ?? [] as $apa) {
                $catat($apa);
            }
            $bagian[] = ['nomor' => 2, 'judul' => 'Dasar Hukum', 'isi' => [$dasarHukum()]];

            $nomor = 3;
            $bagian[] = ['nomor' => 3, 'judul' => 'Tujuan dan Sasaran', 'isi' => [
                ['jenis' => 'subjudul', 'teks' => 'A. Tujuan'],
                ['sunting' => 'tujuan'] + $isi(filled($kegiatan['tujuan'] ?? null) ? KalimatKak::tujuan($nama, $kegiatan['tujuan']) : null, 'tujuan kegiatan', 'identitas'),
                ['jenis' => 'subjudul', 'teks' => 'B. Sasaran'],
                ['sunting' => 'sasaran'] + $isi(filled($kegiatan['sasaran'] ?? null) ? KalimatKak::sasaran($kegiatan['sasaran']) : null, 'sasaran kegiatan', 'identitas'),
            ]];

            // 4. Tahapan: pelaksanaan & pelaporan bergantung pada jadwal.
            $nomor = 4;
            $kalimatPelaksanaan = $pelaksanaan->kalimatPelaksanaan($nama);
            $tahapan = [
                ['jenis' => 'subjudul', 'teks' => 'a. Persiapan'],
                // Teks baku yang bisa disesuaikan per kegiatan (TeksKhusus); tanpa penyesuaian = KalimatKak.
                ['jenis' => 'daftar', 'bernomor' => true, 'butir' => TeksKhusus::cetak('persiapan', $kegiatan), 'sunting' => 'persiapan'],
                ['jenis' => 'subjudul', 'teks' => 'b. Pelaksanaan'],
                ['tempat' => 'kak-pelaksanaan'] + ($kalimatPelaksanaan !== null
                    ? ['jenis' => 'paragraf', 'teks' => $kalimatPelaksanaan, 'sunting' => 'uraian_pelaksanaan']
                    : $kosong('tanggal mulai & lama kegiatan', 'pelaksanaan', true)),
                ['jenis' => 'subjudul', 'teks' => 'c. Pelaporan'],
                $pelaporan(),
            ];
            if (! $batasLaporan) {
                $tahapan[] = $kosong('tanggal pelaporan', 'pelaksanaan');
            }
            $tahapan = [...$tahapan, ...self::blokMatrik($matrik, $tahun, $kosong)];
            $bagian[] = ['nomor' => 4, 'judul' => 'Tahapan Kegiatan', 'isi' => $tahapan];

            $nomor = 5;
            $bagian[] = ['nomor' => 5, 'judul' => 'Tempat Pelaksanaan', 'isi' => [
                ['sunting' => 'tempat'] + $isi($pelaksanaan->kalimatTempat($nama), 'tempat pelaksanaan', 'pelaksanaan', true),
            ]];

            $nomor = 6;
            $bagian[] = ['nomor' => 6, 'judul' => 'Penyelenggara Kegiatan', 'isi' => [
                ['jenis' => 'subjudul', 'teks' => 'a. Pelaksana'],
                $pelaksana(),
                ['jenis' => 'subjudul', 'teks' => 'b. Pengarah'],
                ['jenis' => 'paragraf', 'teks' => TeksKhusus::cetak('pengarah', $kegiatan), 'sunting' => 'pengarah'],
            ]];

            $nomor = 7;
            $bagian[] = ['nomor' => 7, 'judul' => 'Jadwal Kegiatan', 'isi' => self::blokJadwal($jadwal, $tahun, $kosong)];

            $nomor = 8;
            $bagian[] = ['nomor' => 8, 'judul' => 'Pembiayaan', 'isi' => $pembiayaan()];

            $nomor = 9;
            $bagian[] = ['nomor' => 9, 'judul' => 'Lembar Pengesahan', 'isi' => [self::pengesahan($kegiatan, $sumber, $madrasah['kota'], $kosong, $tahun)]];
        }

        $status = StatusKegiatan::tryFrom($kegiatan['status'] ?? '') ?? StatusKegiatan::Draf;
        $cover = [
            'judul' => 'Kerangka Acuan Kerja (KAK) / Term of Reference (TOR)',
            'kegiatan' => 'Kegiatan '.(trim((string) ($kegiatan['nama_kegiatan_cover'] ?? '')) ?: mb_strtoupper($nama)),
            'madrasah' => mb_strtoupper($madrasah['nama']),
            'tahun' => "Tahun Anggaran {$tahun}",
            'sumber' => $sumber ? 'Sumber dana: '.mb_strtoupper($sumber) : null,
        ];

        $statusDraf = StatusDraf::hitung($status, array_values(array_unique($kurang)), $peringatanPagu);

        return new self(
            $cover,
            $bagian,
            array_values(array_unique($kurang)),
            $statusDraf->draf,
            $sumber,
            $daftarSumber,
            $peringatanPagu,
            array_map(fn (AnggaranSumber $a) => $a->jumlahItem() > 0 ? $a->statusPagu() : null, $anggaran),
            array_map(fn (array $daftar) => array_values(array_unique($daftar)), $kurangPerBagian),
            KunciDokumen::dari($kegiatan),
            $statusDraf,
            $format,
        );
    }

    /**
     * Nomor bagian susunan ini yang memuat kunci isi tertentu (FormatKakTor::BAGIAN, mis. 'matrik', 'jadwal',
     * 'latar_belakang') — untuk pratinjau sebagian; isi yang tidak ada di format ini dilewati.
     *
     * @return list<int>
     */
    public function nomorUntuk(string ...$isi): array
    {
        return array_values(array_unique(array_filter(array_map(fn (string $k) => FormatKakTor::nomor($this->format, $k), $isi))));
    }

    /** Label nomor tercetak bagian ("4" / "IV"). */
    public static function labelBagian(array $bagian): string
    {
        return (string) ($bagian['label'] ?? $bagian['nomor']);
    }

    /**
     * Matrik Tahapan di akhir bagian 4 (format 5 bab: bab III, diantar kalimat "Adapun matrik …"): tabel tahapan × bulan
     * 1–12 (tanda "v"), tampil bila ada tanda; penanda "Lengkapi" selama ada tahapan tanpa bulan (syarat kelengkapan cetak,
     * PRD aturan 8).
     *
     * @return list<array<string, mixed>>
     */
    private static function blokMatrik(MatrikTahapan $matrik, int $tahun, callable $kosong, bool $pengantar = false): array
    {
        $blok = [$pengantar
            ? ['jenis' => 'paragraf', 'teks' => 'Adapun matrik tahapan kegiatan adalah sebagai berikut:']
            : ['jenis' => 'subjudul', 'teks' => 'Matrik Tahapan Kegiatan']];

        if ($matrik->jumlahTanda() > 0) {
            $blok[] = ['jenis' => 'matrik', 'tahun' => $tahun, 'tahapan' => array_values(array_map(
                fn (string $kunci, string $judul, int $i) => ['nomor' => $i + 1, 'judul' => $judul, 'butir' => $matrik->butir[$kunci] ?? []],
                array_keys(MatrikTahapan::TAHAPAN), MatrikTahapan::TAHAPAN, range(0, count(MatrikTahapan::TAHAPAN) - 1),
            ))];
        }

        if (! $matrik->lengkap()) {
            $blok[] = $kosong('matrik tahapan (tab Matrik & Jadwal)', 'matrik');
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
            $blok[] = $kosong('jadwal kegiatan (tab Matrik & Jadwal)', 'jadwal');
        }

        return $blok;
    }

    /**
     * Blok tanda tangan — pejabat yang berlaku pada tahun $tahun (bawaan tahun aktif); Mengetahui (Ketua Komite) per sumber
     * dana menurut PejabatBerlaku::mengetahui (bawaan DIPA & Komite). Lembar pengesahan RAB memakai blok yang sama
     * (SusunanRab, $dokumen 'rab').
     * - Format 9 bagian: Mengetahui, Pembuat (pejabat leading, tempat & tanggal), Menyetujui (Kepala) sejajar.
     * - Format 5 bab (TOR & Form RAB 2027): baris 1 Menyetujui (Kepala) di kiri & pembuat di kanan (tempat & tanggal;
     *   RAB berperan "Penanggungjawab"), baris 2 Mengetahui di tengah — kolom membawa `baris` (0|1), `sebutan` (nama peran
     *   untuk pemeriksaan & pesan, ValidasiPejabat), dan `tanpa_nip` (Ketua Komite tanpa NIP: baris NIP tidak dicetak).
     *
     * @param  array<string, mixed>  $kegiatan
     * @param  callable(string): mixed  $kosong  Pencatat isian yang belum lengkap (tanggal dokumen).
     * @return array{jenis: string, kolom: list<array{peran: string, jabatan: string, nama: string|null, nip: string|null, tempat_tanggal: string|null, baris?: int, sebutan?: string, tanpa_nip?: bool}>}
     */
    public static function pengesahan(array $kegiatan, ?string $sumber, string $kota, callable $kosong, ?int $tahun = null, string $format = FormatKakTor::SEMBILAN_BAGIAN, string $dokumen = 'kak'): array
    {
        $berlaku = PejabatBerlaku::tahun($tahun);
        $pejabat = $berlaku->pimpinan();
        $pembuat = $berlaku->pembuat($kegiatan);
        $tanggal = Format::tanggal($kegiatan['tanggal_dokumen'] ?? null);

        if (! $tanggal) {
            $kosong('tanggal dokumen', 'identitas');
        }
        $tempatTanggal = $kota.', '.($tanggal ?? '……………');
        $mengetahui = $berlaku->mengetahui($sumber);

        if ($format === FormatKakTor::LIMA_BAB) {
            $kolom = [
                ['peran' => 'Menyetujui', 'jabatan' => $pejabat['kepala']['jabatan'], 'nama' => $pejabat['kepala']['nama'], 'nip' => $pejabat['kepala']['nip'], 'tempat_tanggal' => null, 'baris' => 0, 'sebutan' => 'Menyetujui'],
                ['peran' => $dokumen === 'rab' ? 'Penanggungjawab' : '', 'jabatan' => $pembuat['jabatan'] ?? 'Pejabat leading', 'nama' => $pembuat['nama'] ?? null, 'nip' => $pembuat['nip'] ?? null, 'tempat_tanggal' => $tempatTanggal, 'baris' => 0, 'sebutan' => $dokumen === 'rab' ? 'Penanggungjawab' : 'Pembuat'],
            ];
            if ($mengetahui !== null) {
                $kolom[] = ['peran' => 'Mengetahui', 'jabatan' => $mengetahui['jabatan'], 'nama' => $mengetahui['nama'], 'nip' => $mengetahui['nip'], 'tempat_tanggal' => null, 'baris' => 1, 'sebutan' => 'Mengetahui', 'tanpa_nip' => blank($mengetahui['nip'])];
            }

            return ['jenis' => 'pengesahan', 'kolom' => $kolom];
        }

        $kolom = [];
        if ($mengetahui !== null) {
            $kolom[] = ['peran' => 'Mengetahui,', 'jabatan' => $mengetahui['jabatan'], 'nama' => $mengetahui['nama'], 'nip' => $mengetahui['nip'], 'tempat_tanggal' => null];
        }
        $kolom[] = ['peran' => 'Pembuat,', 'jabatan' => $pembuat['jabatan'] ?? 'Pejabat leading', 'nama' => $pembuat['nama'] ?? null, 'nip' => $pembuat['nip'] ?? null, 'tempat_tanggal' => $tempatTanggal];
        $kolom[] = ['peran' => 'Menyetujui,', 'jabatan' => $pejabat['kepala']['jabatan'], 'nama' => $pejabat['kepala']['nama'], 'nip' => $pejabat['kepala']['nip'], 'tempat_tanggal' => null];

        return ['jenis' => 'pengesahan', 'kolom' => $kolom];
    }

    /**
     * Kolom tanda tangan dikelompokkan per baris tampil (format 9 bagian: satu baris; format 5 bab: dua baris).
     *
     * @param  list<array<string, mixed>>  $kolom
     * @return list<list<array<string, mixed>>>
     */
    public static function barisPengesahan(array $kolom): array
    {
        $baris = [];
        foreach ($kolom as $k) {
            $baris[(int) ($k['baris'] ?? 0)][] = $k;
        }
        ksort($baris);

        return array_values($baris);
    }

    private static function tanggal(?string $nilai): ?CarbonImmutable
    {
        return filled($nilai) ? rescue(fn () => CarbonImmutable::parse($nilai)->startOfDay(), null, false) : null;
    }
}
