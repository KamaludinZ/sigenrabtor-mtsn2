<?php

namespace App\Support\Dokumen;

use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Support\Format;
use App\Support\Kegiatan\AksesKegiatan;
use App\Support\Kegiatan\Duplikat\DuplikatKegiatan;
use App\Support\Kegiatan\Duplikat\LayananDuplikat;
use App\Support\Kegiatan\Impor\PemeriksaImporKegiatan;
use App\Support\Kegiatan\KunciKegiatan;
use App\Support\Kegiatan\TahunKegiatan;
use App\Support\MasterKode;
use App\Support\Rab\AnggaranKegiatan;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Aksi satu catatan Riwayat Dokumen (PRD §7 "Buka Ulang & Duplikat", F28) — dipakai modal Lihat & Duplikat halaman
 * `Dokumen\Riwayat` (dan kelak endpoint-nya):
 *
 * - `lihat($id, $pengguna)` = rincian catatan (cakupan RiwayatDokumen::boleh), semua versi jenis × sumber × format yang
 *   sama (terbaru dulu), keadaan kegiatan sekarang (status, total RAB sumber itu dibanding saat dibuat), dan cara membuka
 *   ulang: PDF arsip final (hanya versi arsip terbaru — arsip lama sudah diganti), pratinjau, cetak ulang & unduh PDF/Word/
 *   Excel dengan data terbaru (berkas cetak/unduhan lama tidak disimpan, hanya catatannya). Tanpa kegiatan tersimpan atau
 *   sumber dananya tidak dipilih lagi → `alasan_buka`.
 * - `rencanaDuplikat()` = apakah dokumen bisa disalin sebagai dasar kegiatan baru: kegiatan tahun lalu → draf tahun aktif
 *   lewat LayananDuplikat (nama tetap, sudah ada → ditolak dengan tautan); kegiatan tahun aktif → kegiatan baru bernama lain
 *   (bawaan "… (salinan)") lewat DuplikatKegiatan::jalankan; kegiatan tidak tersimpan, di luar cakupan, leading nonaktif,
 *   atau tahun aktif terkunci → `bisa` false + `alasan` (+ `tautan`).
 * - `duplikat($id, $nama, $pengguna)` = jalankan rencana itu → {id, nama, catatan, tautan (Data Kegiatan), tautan_dokumen
 *   (Susun KAK/TOR dengan jenis & sumber dokumen asal), pesan}; galat `nama` (wajib, ≤ MAKS_NAMA, unik di tahun aktif) atau
 *   `duplikat` (tidak bisa diduplikat).
 */
final class AksiRiwayatDokumen
{
    /** Lembar per jenis dokumen saat dibuka/dicetak ulang. */
    public const LEMBAR = ['lengkap' => ['cover', 'kak', 'rab'], 'KAK+TOR' => ['cover', 'kak'], 'KAK' => ['cover', 'kak'], 'TOR' => ['cover', 'kak'], 'RAB' => ['rab']];

    public const MAKS_NAMA = 255;

    public const PESAN_TIDAK_ADA = 'Catatan dokumen tidak ditemukan.';

    public const PESAN_TOLAK = 'Dokumen ini di luar cakupan akun Anda.';

    public const PESAN_PAGU_SALINAN = 'Sumber dana & pagu disalin dari kegiatan asal — sesuaikan bila kegiatan baru memakai pagu lain.';

    /**
     * Catatan siap tampil dalam cakupan akun.
     *
     * @param  array{id?: int|null, role: string, leadings?: list<string>}  $pengguna
     * @return array<string, mixed>
     *
     * @throws RuntimeException Tidak ada.
     * @throws AuthorizationException Di luar cakupan akun.
     */
    public static function temukan(string $id, array $pengguna): array
    {
        $mentah = collect(RiwayatDokumen::semua())->firstWhere('id', $id);
        if ($mentah === null) {
            throw new RuntimeException(self::PESAN_TIDAK_ADA);
        }
        if (! RiwayatDokumen::boleh($mentah, $pengguna)) {
            throw new AuthorizationException(self::PESAN_TOLAK);
        }

        return collect(RiwayatDokumen::untuk($pengguna))->firstWhere('id', $id);
    }

    /**
     * @param  array{id?: int|null, role: string, leadings?: list<string>}  $pengguna
     * @return array<string, mixed>
     */
    public static function lihat(string $id, array $pengguna): array
    {
        $c = self::temukan($id, $pengguna);
        $versi = array_values(array_filter(RiwayatDokumen::untuk($pengguna), fn (array $v) => RiwayatDokumen::kunciVersi($v) === RiwayatDokumen::kunciVersi($c)));
        usort($versi, fn (array $a, array $b) => $b['dibuat_pada'] <=> $a['dibuat_pada']);
        $terbaru = ($versi[0]['id'] ?? null) === $c['id'];

        $kegiatan = $c['tersedia'] ? app(KegiatanRepository::class)->temukan((int) $c['kegiatan_id']) : null;
        $buka = ['arsip' => null, 'pratinjau' => null, 'cetak' => null, 'pdf' => null, 'word' => false, 'excel' => false];
        $alasan = null;
        $sekarang = null;
        if ($kegiatan === null) {
            $alasan = 'Data kegiatan TA '.$c['tahun_anggaran'].' tidak lagi tersimpan — hanya catatan dokumennya yang tersisa, sehingga dokumen tidak bisa disusun ulang.';
        } elseif ($c['sumber'] !== null && ! in_array($c['sumber'], array_column($kegiatan['sumber_dana'] ?? [], 'sumber'), true)) {
            $alasan = 'Sumber dana '.$c['sumber'].' tidak lagi dipilih di kegiatan ini, sehingga dokumen sumber dana itu tidak bisa disusun ulang.';
        } else {
            $lembar = $c['sumber'] === null ? array_values(array_diff(self::LEMBAR[$c['jenis']] ?? ['cover', 'kak'], ['rab'])) : (self::LEMBAR[$c['jenis']] ?? ['cover', 'kak', 'rab']);
            $lembar = $lembar === [] ? ['cover', 'kak'] : $lembar;
            $cetak = StatusCetak::dari($kegiatan, $c['sumber']);
            $buka = [
                'arsip' => $c['asal'] === 'arsip' && $terbaru && $c['sumber'] !== null ? route('kegiatan.arsip', ['kegiatan' => $c['kegiatan_id'], 'sumber' => $c['sumber']]) : null,
                'pratinjau' => $c['tautan_kegiatan'],
                'cetak' => CetakDokumen::tautan((int) $c['kegiatan_id'], $c['sumber'], $lembar),
                'pdf' => CetakDokumen::tautanPdf((int) $c['kegiatan_id'], $c['sumber'], $lembar),
                'word' => in_array('kak', $lembar, true),
                'excel' => in_array('rab', $lembar, true) && $c['sumber'] !== null,
                'lembar' => $lembar,
                'nama_pdf' => CetakDokumen::namaBerkas($cetak, $lembar),
                'nama_word' => CetakDokumen::namaBerkas($cetak, ['cover', 'kak']),
                'nama_excel' => $cetak->namaBerkas('RAB', 'xlsx'),
                'draf' => $cetak->draf(),
            ];
            $anggaran = $c['sumber'] !== null ? (AnggaranKegiatan::perSumber($kegiatan)[$c['sumber']] ?? null) : null;
            $status = StatusKegiatan::tryFrom((string) ($kegiatan['status'] ?? '')) ?? StatusKegiatan::Draf;
            $sekarang = [
                'status' => $status->value,
                'label_status' => $status->label(),
                'total_rab' => $anggaran?->total(),
                'total_teks' => $anggaran !== null ? Format::rupiah($anggaran->total()) : null,
                'total_berubah' => $anggaran !== null && $c['ringkas']['total_rab'] !== null && $anggaran->total() !== (int) $c['ringkas']['total_rab'],
            ];
        }

        return [
            'dokumen' => $c + ['total_teks' => $c['ringkas']['total_rab'] !== null ? Format::rupiah((int) $c['ringkas']['total_rab']) : null],
            'terbaru' => $terbaru,
            'versi' => $versi,
            'kegiatan' => $kegiatan === null ? null : ['id' => (int) $kegiatan['id'], 'tautan' => route('kegiatan.edit', ['kegiatan' => $kegiatan['id']])],
            'sekarang' => $sekarang,
            'buka' => $buka,
            'alasan_buka' => $alasan,
        ];
    }

    /**
     * @param  array{id?: int|null, name?: string, role: string, leadings?: list<string>}  $pengguna
     * @return array{bisa: bool, mode: string|null, alasan: string|null, tautan: array{label: string, url: string}|null, nama: string, nama_terkunci: bool, tahun: int, perubahan: list<array{0: string, 1: string}>, catatan: list<array{jenis: string, teks: string}>, dokumen: array<string, mixed>}
     */
    public static function rencanaDuplikat(string $id, array $pengguna): array
    {
        $c = self::temukan($id, $pengguna);
        $tahun = (int) StubData::tahunAktif()['tahun'];
        $hasil = [
            'bisa' => false, 'mode' => null, 'alasan' => null, 'tautan' => null, 'nama' => $c['nama_kegiatan'], 'nama_terkunci' => true,
            'tahun' => $tahun, 'perubahan' => [], 'catatan' => [],
            'dokumen' => ['id' => $c['id'], 'jenis' => $c['jenis'], 'label_jenis' => $c['label_jenis'], 'sumber' => $c['sumber'], 'nama_kegiatan' => $c['nama_kegiatan'], 'tahun_anggaran' => $c['tahun_anggaran'], 'versi' => $c['versi']],
        ];
        $tolak = fn (string $alasan, ?array $tautan = null) => ['alasan' => $alasan, 'tautan' => $tautan] + $hasil;

        if (($terkunci = KunciKegiatan::tahunTerkunci(['tahun_anggaran' => $tahun])) !== null) {
            return $tolak(KunciKegiatan::pesanTahun($terkunci));
        }
        $kegiatan = $c['tersedia'] ? app(KegiatanRepository::class)->temukan((int) $c['kegiatan_id']) : null;
        if ($kegiatan === null) {
            $bolehBuat = $pengguna['role'] === 'admin' || MasterKode::pilihanLeading($pengguna, null) !== [];

            return $tolak('Data kegiatan TA '.$c['tahun_anggaran'].' tidak lagi tersimpan — hanya catatan dokumennya yang tersisa, sehingga isinya tidak bisa disalin. Buat kegiatan baru dari awal.', $bolehBuat ? ['label' => 'Buat kegiatan baru', 'url' => route('kegiatan.create')] : null);
        }
        if (! AksesKegiatan::bolehLihat($kegiatan, $pengguna)) {
            return $tolak(AksesKegiatan::PESAN_TOLAK);
        }
        $leading = MasterKode::leading($kegiatan['leading'] ?? null);
        if ($leading !== null && ! $leading['is_active']) {
            return $tolak('Leading '.$leading['kode'].' kini nonaktif — kegiatannya tidak bisa disalin.');
        }

        $dataTahun = TahunKegiatan::dari($kegiatan);
        if ($dataTahun !== $tahun) {
            $rencana = LayananDuplikat::rencana([(int) $kegiatan['id']], $pengguna);
            if ($rencana['siap'] === []) {
                $ada = self::idNama((string) $kegiatan['nama_kegiatan'], $tahun);

                return $tolak(($rencana['dilewati'][0]['alasan'] ?? $rencana['gagal'][0]['alasan'] ?? 'Kegiatan tidak bisa diduplikat.'), $ada !== null ? ['label' => 'Buka kegiatan TA '.$tahun, 'url' => route('kegiatan.edit', ['kegiatan' => $ada])] : null);
            }

            return ['bisa' => true, 'mode' => 'tahun_lalu', 'perubahan' => self::perubahan($tahun), 'catatan' => $rencana['siap'][0]['catatan']] + $hasil;
        }

        $nama = self::namaSalinan((string) $kegiatan['nama_kegiatan'], $tahun);

        return [
            'bisa' => true, 'mode' => 'tahun_ini', 'nama' => $nama, 'nama_terkunci' => false,
            'perubahan' => array_values(array_filter(self::perubahan($tahun), fn (array $p) => in_array($p[0], [DuplikatKegiatan::PERUBAHAN['status'][0], DuplikatKegiatan::PERUBAHAN['anggaran'][0]], true))),
            'catatan' => self::catatanTahunIni(DuplikatKegiatan::rencana(['nama_kegiatan' => $nama] + $kegiatan)['catatan']),
        ] + $hasil;
    }

    /**
     * @param  array{id?: int|null, name: string, role: string, leadings?: list<string>}  $pengguna
     * @return array{id: int, nama: string, catatan: list<array{jenis: string, teks: string}>, tautan: string, tautan_dokumen: string, pesan: string}
     *
     * @throws ValidationException Berkunci `nama` atau `duplikat`.
     */
    public static function duplikat(string $id, ?string $nama, array $pengguna): array
    {
        $rencana = self::rencanaDuplikat($id, $pengguna);
        if (! $rencana['bisa']) {
            throw ValidationException::withMessages(['duplikat' => $rencana['alasan']]);
        }
        $c = self::temukan($id, $pengguna);
        $kegiatan = app(KegiatanRepository::class)->temukan((int) $c['kegiatan_id']);

        if ($rencana['mode'] === 'tahun_lalu') {
            $jalan = LayananDuplikat::jalankan([(int) $kegiatan['id']], $pengguna);
            if ($jalan['dibuat'] === []) {
                throw ValidationException::withMessages(['duplikat' => $jalan['dilewati'][0]['alasan'] ?? $jalan['gagal'][0]['alasan'] ?? $jalan['pesan']]);
            }
            ['hasil_id' => $baru, 'nama' => $namaBaru, 'catatan' => $catatan] = $jalan['dibuat'][0];
        } else {
            $namaBaru = trim((string) preg_replace('/\s+/u', ' ', (string) $nama));
            if (($galat = self::galatNama($namaBaru, $rencana['tahun'])) !== null) {
                throw ValidationException::withMessages(['nama' => $galat]);
            }
            $sumber = ['nama_kegiatan' => $namaBaru] + $kegiatan;
            if (! ($kegiatan['cover_manual'] ?? false)) {
                $sumber['nama_kegiatan_cover'] = mb_strtoupper($namaBaru);
            }
            ['id' => $baru, 'catatan' => $catatan] = DuplikatKegiatan::jalankan($sumber, $pengguna, 'Disalin dari kegiatan “'.$kegiatan['nama_kegiatan'].'” (dokumen '.$c['label_jenis'].' v'.$c['versi'].').');
            $catatan = self::catatanTahunIni($catatan);
        }

        $baruData = app(KegiatanRepository::class)->temukan($baru) ?? [];
        $sumberDipilih = array_column($baruData['sumber_dana'] ?? [], 'sumber');

        return [
            'id' => $baru,
            'nama' => $namaBaru,
            'catatan' => $catatan,
            'tautan' => route('kegiatan.edit', ['kegiatan' => $baru]),
            'tautan_dokumen' => route('kegiatan.dokumen', array_filter(['kegiatan' => $baru, 'jenis' => $c['jenis'], 'sumber' => in_array($c['sumber'], $sumberDipilih, true) ? $c['sumber'] : null])),
            'pesan' => 'Kegiatan “'.$namaBaru.'” dibuat sebagai draf TA '.$rencana['tahun'].'.',
        ];
    }

    /** Pesan galat nama kegiatan salinan (null = sah). */
    public static function galatNama(string $nama, int $tahun): ?string
    {
        return match (true) {
            $nama === '' => 'Nama kegiatan baru wajib diisi.',
            mb_strlen($nama) > self::MAKS_NAMA => 'Nama kegiatan paling banyak '.self::MAKS_NAMA.' karakter.',
            self::idNama($nama, $tahun) !== null => 'Sudah ada kegiatan bernama “'.$nama.'” di TA '.$tahun.' — pakai nama lain.',
            default => null,
        };
    }

    /** Nama bawaan salinan di tahun yang sama: "… (salinan)", lalu "… (salinan 2)", dst. bila sudah dipakai. */
    public static function namaSalinan(string $nama, int $tahun): string
    {
        $dasar = trim((string) preg_replace('/\s*\(salinan(?: \d+)?\)$/u', '', $nama));
        for ($n = 1; ; $n++) {
            $calon = mb_substr($dasar, 0, self::MAKS_NAMA - 14).' (salinan'.($n > 1 ? ' '.$n : '').')';
            if (self::idNama($calon, $tahun) === null) {
                return $calon;
            }
        }
    }

    /** Id kegiatan tahun $tahun bernama sama (PemeriksaImporKegiatan::kunciNama), null bila belum ada. */
    private static function idNama(string $nama, int $tahun): ?int
    {
        $kunci = PemeriksaImporKegiatan::kunciNama($nama);
        foreach (app(KegiatanRepository::class)->semua() as $k) {
            if (TahunKegiatan::dari($k) === $tahun && PemeriksaImporKegiatan::kunciNama((string) ($k['nama_kegiatan'] ?? '')) === $kunci) {
                return (int) $k['id'];
            }
        }

        return null;
    }

    /**
     * Catatan salinan di tahun yang sama: pagu bukan "dari TA lalu", melainkan dari kegiatan asal.
     *
     * @param  list<array{jenis: string, teks: string}>  $catatan
     * @return list<array{jenis: string, teks: string}>
     */
    private static function catatanTahunIni(array $catatan): array
    {
        return array_map(fn (array $c) => $c['jenis'] === 'pagu_lama' ? ['jenis' => 'pagu_lama', 'teks' => self::PESAN_PAGU_SALINAN] : $c, $catatan);
    }

    /** @return list<array{0: string, 1: string}> */
    private static function perubahan(int $tahun): array
    {
        return array_values(array_map(fn (array $p) => [$p[0], str_replace('{tahun}', (string) $tahun, $p[1])], DuplikatKegiatan::PERUBAHAN));
    }
}
