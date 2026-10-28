<?php

namespace App\Support\Master\Impor;

use App\Contracts\MasterKodeRepository;
use App\Support\Impor\GagalBacaImpor;
use App\Support\Master\JenisKode;
use App\Support\Pengaturan\RiwayatPengaturan;
use Illuminate\Auth\Access\AuthorizationException;
use LogicException;

/**
 * Simpan unggahan Excel menjadi master kode tahun anggaran (PRD §14, F7 "pratinjau sebelum disimpan") — dipakai tombol
 * *Simpan master* panel Unggah Excel dan endpoint `master.unggah`. Berkas diproses ulang lewat ProsesBerkasMaster::proses
 * (izin admin & tahun terbuka) sehingga yang tersimpan selalu isi berkas itu; disimpan hanya bila `kendala()` kosong —
 * kendala ProsesBerkasMaster (termasuk IKSK yang SK induknya tidak ada di SK berkas/master — PratinjauMaster::periksaInduk),
 * jenis yang penyimpanannya belum tersedia (DIDUKUNG), dan aturan antar-jenis (SK yang hilang padahal masih menjadi induk
 * IKSK master tahun itu, kecuali IKSK ikut diunggah). Yang tersimpan per jenis: baris
 * baru/berubah/tetap urut berkas (MasterKodeRepository::ganti, seluruh jenis sekaligus); kode yang hilang keluar dari
 * master dan kegiatan pemakainya ditandai KodeHilang. Jejak: catatan unggahan (ringkasan per jenis) + riwayat
 * `unggah_master_kode`.
 */
final class SimpanMasterKode
{
    /** Jenis yang sudah bisa disimpan dari unggahan. */
    public const DIDUKUNG = ['sk', 'iksk', 'rktm'];

    /** Jenis → bagian master (MasterKodeRepository::ganti). */
    public const BAGIAN = ['sk' => 'kode_sk', 'iksk' => 'kode_iksk', 'rktm' => 'kode_rktm', 'edm' => 'kode_edm', 'budaya' => 'budaya_mutu'];

    /** Status pratinjau yang ikut tersimpan. */
    private const TERSIMPAN = ['baru', 'berubah', 'tetap'];

    /** @param  list<string>  $jenis */
    public static function didukung(array $jenis): bool
    {
        return $jenis !== [] && array_diff($jenis, self::DIDUKUNG) === [];
    }

    /**
     * Alasan pratinjau belum bisa disimpan (kosong = siap).
     *
     * @param  array<string, array<string, mixed>>  $pratinjau  PratinjauMaster::susun().
     * @return list<string>
     */
    public static function kendala(array $pratinjau, int $tahun): array
    {
        $hasil = ProsesBerkasMaster::kendala($pratinjau);
        foreach (array_keys($pratinjau) as $jenis) {
            if (! in_array($jenis, self::DIDUKUNG, true)) {
                $hasil[] = JenisKode::DAFTAR[$jenis]['label'].': penyimpanan dari unggahan belum tersedia.';
            }
        }

        if (isset($pratinjau['sk']) && ! isset($pratinjau['iksk'])) {
            $hilang = array_column(array_filter($pratinjau['sk']['baris'], fn (array $b) => $b['status'] === 'hilang'), 'kode');
            $induk = array_values(array_intersect($hilang, array_unique(array_column(JenisKode::baris('iksk', $tahun), 'kode_sk'))));
            if ($induk !== []) {
                $hasil[] = 'SK: '.implode(', ', $induk)." tidak ada di berkas padahal masih menjadi induk IKSK master TA {$tahun} — unggah IKSK bersama SK (berkas master lengkap) atau pertahankan kodenya.";
            }
        }

        return $hasil;
    }

    /**
     * @param  array{id?: int, name?: string, role: string}  $pengguna
     * @return array{sheet: array<string, string>, pratinjau: array<string, array<string, mixed>>, ringkasan: array<string, int>, kendala: list<string>, peringatan: list<string>, siap: bool, disimpan: bool, unggahan: string}
     *
     * @throws GagalSimpanMaster
     * @throws GagalBacaImpor
     * @throws AuthorizationException
     * @throws \RuntimeException Penyimpanan gagal (master lama utuh).
     */
    public static function simpan(string $path, string $namaAsli, string $cakupan, int $tahun, array $pengguna): array
    {
        $hasil = ProsesBerkasMaster::proses($path, $namaAsli, $cakupan, $tahun, $pengguna);
        if (($kendala = self::kendala($hasil['pratinjau'], $tahun)) !== []) {
            throw new GagalSimpanMaster($kendala);
        }

        $master = [];
        foreach ($hasil['pratinjau'] as $jenis => $item) {
            $master[self::BAGIAN[$jenis]] = array_values(array_map(
                fn (array $b) => self::barisMaster($jenis, $b),
                array_filter($item['baris'], fn (array $b) => in_array($b['status'], self::TERSIMPAN, true)),
            ));
        }
        $repo = app(MasterKodeRepository::class);
        $repo->ganti($tahun, $master);

        $ringkasan = array_map(fn (array $item) => $item['ringkasan'], $hasil['pratinjau']);
        $id = $repo->catatUnggahan([
            'tahun' => $tahun, 'cakupan' => $cakupan, 'nama_berkas' => $namaAsli, 'ringkasan' => $ringkasan,
            'user_id' => $pengguna['id'] ?? null, 'pengguna' => $pengguna['name'] ?? 'Admin',
        ]);
        RiwayatPengaturan::catat('unggah_master_kode', "Master kode TA {$tahun} dari {$namaAsli} — ".self::teksRingkasan($ringkasan));

        return ['siap' => true, 'disimpan' => true, 'unggahan' => $id] + $hasil;
    }

    /**
     * "SK: 2 baru, 1 berubah, 10 tetap, 1 dihapus" (status kosong tidak disebut; per jenis dipisah titik koma).
     *
     * @param  array<string, array<string, int>>  $ringkasan  Jenis → jumlah per status.
     */
    public static function teksRingkasan(array $ringkasan): string
    {
        $label = ['baru' => 'baru', 'berubah' => 'berubah', 'tetap' => 'tetap', 'hilang' => 'dihapus'];

        return implode('; ', array_map(
            fn (string $jenis, array $n) => JenisKode::DAFTAR[$jenis]['label'].': '.(implode(', ', array_filter(array_map(fn (string $s, string $teks) => ($n[$s] ?? 0) > 0 ? "{$n[$s]} {$teks}" : null, array_keys($label), $label))) ?: 'tidak ada kode'),
            array_keys($ringkasan),
            $ringkasan,
        ));
    }

    /**
     * Ringkasan IKSK per SK induk untuk pratinjau IKSK: kode & uraian SK (master tahun itu; null bila tidak ada), jumlah
     * IKSK berkas per status (tanpa yang hilang), urut kode SK.
     *
     * @param  list<array<string, mixed>>  $baris  Baris pratinjau IKSK.
     * @return list<array{kode_sk: string, uraian_sk: string|null, jumlah: int, status: array<string, int>}>
     */
    public static function indukIksk(array $baris, int $tahun): array
    {
        $uraian = array_column(JenisKode::baris('sk', $tahun), 'uraian', 'kode');
        $hasil = [];
        foreach ($baris as $b) {
            if ($b['status'] === 'hilang') {
                continue;
            }
            $sk = (string) $b['tampil']['kode_sk'];
            $hasil[$sk] ??= ['kode_sk' => $sk, 'uraian_sk' => $uraian[$sk] ?? null, 'jumlah' => 0, 'status' => []];
            $hasil[$sk]['jumlah']++;
            $hasil[$sk]['status'][$b['status']] = ($hasil[$sk]['status'][$b['status']] ?? 0) + 1;
        }
        uksort($hasil, fn (string $a, string $b) => version_compare(substr($a, 3), substr($b, 3)) ?: strcmp($a, $b));

        return array_values($hasil);
    }

    /**
     * Struktur RKTM untuk pratinjau RKTM (PRD §14 "SNP, kegiatan, sub kegiatan"): standar (bagian pertama kode) → kegiatan
     * (dua bagian pertama) → jumlah sub kegiatan beserta statusnya, urut nomor; nama standar/kegiatan dari baris pertama
     * kelompoknya. Baris gagal yang kodenya tidak berformat dan baris hilang tidak dihitung.
     *
     * @param  list<array<string, mixed>>  $baris  Baris pratinjau RKTM.
     * @return list<array{nomor: string, snp: string, jumlah: int, kegiatan: list<array{nomor: string, kegiatan: string, jumlah: int, status: array<string, int>}>}>
     */
    public static function strukturRktm(array $baris): array
    {
        $standar = [];
        foreach ($baris as $b) {
            if ($b['status'] === 'hilang' || ! preg_match('/^(\d+)\.(\d+)\.\d+$/', (string) $b['kode'], $nomor)) {
                continue;
            }
            [, $s, $k] = $nomor;
            $standar[$s] ??= ['nomor' => $s, 'snp' => (string) $b['tampil']['snp'], 'jumlah' => 0, 'kegiatan' => []];
            $standar[$s]['kegiatan']["{$s}.{$k}"] ??= ['nomor' => "{$s}.{$k}", 'kegiatan' => (string) $b['tampil']['kegiatan'], 'jumlah' => 0, 'status' => []];
            $kelompok = &$standar[$s]['kegiatan']["{$s}.{$k}"];
            $kelompok['jumlah']++;
            $kelompok['status'][$b['status']] = ($kelompok['status'][$b['status']] ?? 0) + 1;
            unset($kelompok);
            $standar[$s]['jumlah']++;
        }
        ksort($standar, SORT_NUMERIC);

        return array_values(array_map(function (array $s) {
            uksort($s['kegiatan'], 'version_compare');
            $s['kegiatan'] = array_values($s['kegiatan']);

            return $s;
        }, $standar));
    }

    /**
     * Baris pratinjau → baris master jenisnya.
     *
     * @param  array<string, mixed>  $b
     * @return array<string, mixed>
     */
    private static function barisMaster(string $jenis, array $b): array
    {
        return match ($jenis) {
            'sk' => ['kode' => $b['kode'], 'uraian' => $b['tampil']['uraian']],
            'iksk' => ['kode' => $b['kode'], 'kode_sk' => $b['tampil']['kode_sk'], 'uraian' => $b['tampil']['uraian']],
            'rktm' => ['kode' => $b['kode'], 'snp' => $b['tampil']['snp'], 'kegiatan' => $b['tampil']['kegiatan'], 'sub_kegiatan' => $b['tampil']['sub_kegiatan']],
            default => throw new LogicException("Penyimpanan master {$jenis} belum tersedia."),
        };
    }
}
