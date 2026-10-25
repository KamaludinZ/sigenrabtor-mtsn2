<?php

namespace App\Support\Dokumen;

use App\Enums\StatusKegiatan;
use App\Support\Format;
use App\Support\Kegiatan\DataPelaksanaan;
use App\Support\Kegiatan\KalimatKak;
use App\Support\MasterKode;
use App\Support\Rab\AnggaranKegiatan;
use App\Support\Stub\StubData;
use Carbon\CarbonImmutable;

/**
 * Susunan isi KAK/TOR satu kegiatan × sumber dana (PRD 7B): cover lalu sembilan bagian baku, siap ditampilkan
 * oleh <x-dokumen.kak-tor>. Isian yang belum lengkap menjadi blok "kosong" dan tercatat di $kurang; dokumen
 * yang belum disetujui atau belum lengkap ditandai DRAF (PRD aturan 8).
 *
 * Jenis blok isi: paragraf {teks}, subjudul {teks}, daftar {butir, bernomor}, kosong {pesan},
 * menunggu {pesan} (bagian yang datanya berasal dari fitur yang belum tersedia), pengesahan {kolom}.
 */
final readonly class SusunanKak
{
    /**
     * @param  array{judul: string, kegiatan: string, madrasah: string, tahun: string, sumber: string|null}  $cover
     * @param  list<array{nomor: int, judul: string, isi: list<array<string, mixed>>}>  $bagian
     * @param  list<string>  $kurang
     * @param  list<string>  $daftarSumber
     */
    public function __construct(
        public array $cover,
        public array $bagian,
        public array $kurang,
        public bool $draf,
        public ?string $sumber,
        public array $daftarSumber,
    ) {}

    /**
     * @param  array<string, mixed>  $kegiatan  Data kegiatan (bentuk penyimpanan; boleh belum tersimpan).
     */
    public static function dari(array $kegiatan, ?string $sumber = null): self
    {
        $tahun = StubData::tahunAktif()['tahun'];
        $madrasah = StubData::madrasah();
        $nama = trim((string) ($kegiatan['nama_kegiatan'] ?? '')) ?: '…';
        $kurang = [];

        $anggaran = AnggaranKegiatan::perSumber($kegiatan);
        $daftarSumber = array_keys($anggaran);
        $sumber = in_array($sumber, $daftarSumber, true) ? $sumber : ($daftarSumber[0] ?? null);

        $kosong = function (string $apa) use (&$kurang): array {
            $kurang[] = $apa;

            return ['jenis' => 'kosong', 'pesan' => 'Lengkapi '.$apa.'.'];
        };
        $isi = fn (?string $teks, string $apa) => filled($teks) ? ['jenis' => 'paragraf', 'teks' => $teks] : $kosong($apa);

        // Bagian disusun berurutan supaya $kurang mengikuti urutan dokumen.
        $bagian = [];

        $latar = [['jenis' => 'paragraf', 'teks' => StubData::latarBelakangBaku($tahun)]];
        if (filled($kegiatan['latar_belakang'] ?? null)) {
            $latar[] = ['jenis' => 'paragraf', 'teks' => trim($kegiatan['latar_belakang'])];
        }
        $latar[] = $nama === '…' ? $kosong('nama kegiatan') : ['jenis' => 'paragraf', 'teks' => KalimatKak::latarBelakangKegiatan($nama)];
        $bagian[] = ['nomor' => 1, 'judul' => 'Latar Belakang', 'isi' => $latar];

        $bagian[] = ['nomor' => 2, 'judul' => 'Dasar Hukum', 'isi' => [['jenis' => 'daftar', 'bernomor' => true, 'butir' => StubData::dasarHukum($tahun)]]];

        $bagian[] = ['nomor' => 3, 'judul' => 'Tujuan dan Sasaran', 'isi' => [
            ['jenis' => 'subjudul', 'teks' => 'A. Tujuan'],
            $isi(filled($kegiatan['tujuan'] ?? null) ? KalimatKak::tujuan($nama, $kegiatan['tujuan']) : null, 'tujuan kegiatan'),
            ['jenis' => 'subjudul', 'teks' => 'B. Sasaran'],
            $isi(filled($kegiatan['sasaran'] ?? null) ? KalimatKak::sasaran($kegiatan['sasaran']) : null, 'sasaran kegiatan'),
        ]];

        // 4. Tahapan: pelaksanaan & pelaporan bergantung pada jadwal.
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
        $tahapan[] = ['jenis' => 'menunggu', 'pesan' => 'Matrik tahapan (Persiapan, Pelaksanaan, Evaluasi & Pelaporan × bulan) diisi di tab Matrik & Jadwal.'];
        $bagian[] = ['nomor' => 4, 'judul' => 'Tahapan Kegiatan', 'isi' => $tahapan];

        $bagian[] = ['nomor' => 5, 'judul' => 'Tempat Pelaksanaan', 'isi' => [
            $isi($pelaksanaan->kalimatTempat($nama), 'tempat pelaksanaan'),
        ]];

        $leading = MasterKode::cari(MasterKode::leadings(), $kegiatan['leading'] ?? null);
        $bagian[] = ['nomor' => 6, 'judul' => 'Penyelenggara Kegiatan', 'isi' => [
            ['jenis' => 'subjudul', 'teks' => 'a. Pelaksana'],
            $isi($leading ? KalimatKak::pelaksana($nama, $leading['jabatan_penanda_tangan']) : null, 'leading penanggung jawab (Kode & Klasifikasi)'),
            ['jenis' => 'subjudul', 'teks' => 'b. Pengarah'],
            ['jenis' => 'paragraf', 'teks' => KalimatKak::pengarah($nama, $madrasah['nama'])],
        ]];

        $bagian[] = ['nomor' => 7, 'judul' => 'Jadwal Kegiatan', 'isi' => [
            ['jenis' => 'menunggu', 'pesan' => 'Tabel jadwal (No, Uraian, Waktu Pelaksanaan) diisi di tab Matrik & Jadwal.'],
        ]];

        $angka = $sumber ? AngkaDokumen::dari($anggaran[$sumber]->hasil, $sumber, $tahun, $madrasah['nama_singkat']) : null;
        $bagian[] = ['nomor' => 8, 'judul' => 'Pembiayaan', 'isi' => [match (true) {
            $angka === null => $kosong('sumber dana (bagian Sumber Dana)'),
            ! $angka->adaRincian() => $kosong("rincian RAB {$sumber}"),
            default => ['jenis' => 'paragraf', 'teks' => $angka->kalimatPembiayaan()],
        }]];

        $bagian[] = ['nomor' => 9, 'judul' => 'Lembar Pengesahan', 'isi' => [self::pengesahan($kegiatan, $leading, $sumber, $madrasah['kota'], $kosong)]];

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
            ! in_array($status, [StatusKegiatan::Disetujui, StatusKegiatan::Final], true) || $kurang !== [],
            $sumber,
            $daftarSumber,
        );
    }

    /**
     * Blok tanda tangan: Mengetahui (Ketua Komite, khusus dana Komite), Pembuat (pejabat leading), Menyetujui (Kepala).
     *
     * @param  array<string, mixed>  $kegiatan
     * @param  array{jabatan_penanda_tangan: string}|null  $leading
     * @return array{jenis: string, kolom: list<array{peran: string, jabatan: string, nama: string|null, nip: string|null, tempat_tanggal: string|null}>}
     */
    private static function pengesahan(array $kegiatan, ?array $leading, ?string $sumber, string $kota, callable $kosong): array
    {
        $pejabat = StubData::pejabat();
        $pengguna = StubData::currentUser();
        $tanggal = Format::tanggal($kegiatan['tanggal_dokumen'] ?? null);

        if (! $tanggal) {
            $kosong('tanggal dokumen');
        }

        // Selama data tiruan: pembuat = akun leading yang memegang leading kegiatan ini.
        $pembuat = $leading && $pengguna['role'] === 'leading' && in_array($kegiatan['leading'] ?? null, $pengguna['leadings'], true)
            ? $pengguna['name'] : null;

        $kolom = [];
        if ($sumber === 'Komite') {
            $kolom[] = ['peran' => 'Mengetahui,', 'jabatan' => $pejabat['komite']['jabatan'], 'nama' => $pejabat['komite']['nama'], 'nip' => $pejabat['komite']['nip'], 'tempat_tanggal' => null];
        }
        $kolom[] = ['peran' => 'Pembuat,', 'jabatan' => $leading['jabatan_penanda_tangan'] ?? 'Pejabat leading', 'nama' => $pembuat, 'nip' => null, 'tempat_tanggal' => $kota.', '.($tanggal ?? '……………')];
        $kolom[] = ['peran' => 'Menyetujui,', 'jabatan' => $pejabat['kepala']['jabatan'], 'nama' => $pejabat['kepala']['nama'], 'nip' => $pejabat['kepala']['nip'], 'tempat_tanggal' => null];

        return ['jenis' => 'pengesahan', 'kolom' => $kolom];
    }

    private static function tanggal(?string $nilai): ?CarbonImmutable
    {
        return filled($nilai) ? rescue(fn () => CarbonImmutable::parse($nilai)->startOfDay(), null, false) : null;
    }
}
