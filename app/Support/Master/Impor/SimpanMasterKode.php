<?php

namespace App\Support\Master\Impor;

use App\Contracts\MasterKodeRepository;
use App\Support\Format;
use App\Support\Impor\GagalBacaImpor;
use App\Support\Master\JenisKode;
use App\Support\Master\PetaRktm;
use App\Support\Master\RencanaAksi;
use App\Support\Master\SalinMasterKode;
use App\Support\MasterKode;
use App\Support\Pengaturan\PengaturanBerubah;
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
 * baru/berubah/tetap urut berkas (MasterKodeRepository::ganti, seluruh jenis sekaligus; EDM beserta nama komponen per
 * huruf — `komponenEdm()`, kendala bila nama komponen tidak diketahui); kode yang hilang keluar dari
 * master dan kegiatan pemakainya ditandai KodeHilang. Jejak: catatan unggahan (ringkasan per jenis) + riwayat
 * `unggah_master_kode`.
 */
final class SimpanMasterKode
{
    /** Jenis yang sudah bisa disimpan dari unggahan (kini semua jenis JenisKode — berkas master lengkap pun bisa disimpan). */
    public const DIDUKUNG = ['sk', 'iksk', 'rktm', 'edm', 'budaya', 'rencana_aksi'];

    /** Jenis → bagian master (MasterKodeRepository::ganti). */
    public const BAGIAN = ['sk' => 'kode_sk', 'iksk' => 'kode_iksk', 'rktm' => 'kode_rktm', 'edm' => 'kode_edm', 'budaya' => 'budaya_mutu', 'rencana_aksi' => 'rencana_aksi'];

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

        if (isset($pratinjau['rencana_aksi']) && count(array_filter($pratinjau['rencana_aksi']['baris'], fn (array $b) => in_array($b['status'], self::TERSIMPAN, true))) > RencanaAksi::MAKS) {
            $hasil[] = 'Rencana Aksi: lebih dari '.RencanaAksi::MAKS.' baris — bagi berkasnya.';
        }
        if (isset($pratinjau['edm']) && $pratinjau['edm']['galat'] === null) {
            foreach (self::komponenEdm($pratinjau['edm']['baris'], $tahun) as $k) {
                if ($k['nama'] === $k['huruf']) {
                    $hasil[] = "EDM: nama komponen {$k['huruf']} belum ada di berkas maupun master — isi kolom Komponen.";
                }
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
    public static function simpan(string $path, string $namaAsli, string $cakupan, int $tahun, array $pengguna, ?string $sidik = null): array
    {
        $hasil = ProsesBerkasMaster::proses($path, $namaAsli, $cakupan, $tahun, $pengguna);
        if ($sidik !== null && $sidik !== self::sidik($tahun, array_keys($hasil['pratinjau']))) {
            throw new PengaturanBerubah("Master kode TA {$tahun} baru saja diubah di tempat lain.");
        }
        if (($kendala = self::kendala($hasil['pratinjau'], $tahun)) !== []) {
            throw new GagalSimpanMaster($kendala);
        }

        $master = [];
        $lamaRa = array_column(JenisKode::baris('rencana_aksi', $tahun), null, 'kode');
        foreach ($hasil['pratinjau'] as $jenis => $item) {
            $master[self::BAGIAN[$jenis]] = array_values(array_map(
                fn (array $b) => self::barisMaster($jenis, $b, $lamaRa),
                array_filter($item['baris'], fn (array $b) => in_array($b['status'], self::TERSIMPAN, true)),
            ));
        }
        if (isset($hasil['pratinjau']['edm'])) {
            // Nama komponen per huruf ikut diganti: dari baris pertama huruf itu di berkas.
            $master['komponen_edm'] = array_column(self::komponenEdm($hasil['pratinjau']['edm']['baris'], $tahun), 'nama', 'huruf');
            if (($relasi = self::relasiEdm($hasil['pratinjau']['edm']['baris'])) !== null) {
                $master['relasi_edm_rktm'] = $relasi;
            }
        }
        $repo = app(MasterKodeRepository::class);
        $repo->ganti($tahun, $master);

        $ringkasan = array_map(fn (array $item) => $item['ringkasan'], $hasil['pratinjau']);
        // Kode RKTM yang dinomori ulang (nama sub kegiatan sama) dicatat bersama unggahan: dasar saran kode pengganti
        // kegiatan yang masih memakai kode lama (PetaRktm::pengganti) walau master lamanya sudah diganti.
        if (isset($hasil['pratinjau']['rktm'])) {
            $pindah = array_values(array_map(fn (array $b) => ['lama' => (string) $b['kode'], 'baru' => (string) $b['pindah']],
                array_filter($hasil['pratinjau']['rktm']['baris'], fn (array $b) => $b['status'] === 'hilang' && isset($b['pindah']))));
            if ($pindah !== []) {
                $ringkasan['rktm']['pindah'] = $pindah;
            }
        }
        $id = $repo->catatUnggahan([
            'tahun' => $tahun, 'cakupan' => $cakupan, 'nama_berkas' => $namaAsli, 'ringkasan' => $ringkasan,
            'user_id' => $pengguna['id'] ?? null, 'pengguna' => $pengguna['name'] ?? 'Admin',
        ]);
        RiwayatPengaturan::catat('unggah_master_kode', "Master kode TA {$tahun} dari {$namaAsli} — ".self::teksRingkasan($ringkasan));

        return ['siap' => true, 'disimpan' => true, 'unggahan' => $id] + $hasil;
    }

    /**
     * Sidik master tahun $tahun yang menentukan hasil penyimpanan jenis $jenis (barisnya, nama komponen EDM, dan IKSK
     * untuk aturan induk SK) — pratinjau yang disetujui hanya disimpan selama sidiknya sama.
     *
     * @param  list<string>  $jenis
     */
    public static function sidik(int $tahun, array $jenis): string
    {
        $data = [];
        foreach (array_unique([...$jenis, ...(in_array('sk', $jenis, true) ? ['iksk'] : [])]) as $j) {
            $data[$j] = JenisKode::baris($j, $tahun);
        }
        if (in_array('edm', $jenis, true)) {
            $data['komponen_edm'] = app(MasterKodeRepository::class)->komponenEdm($tahun);
            $data['relasi_edm_rktm'] = app(MasterKodeRepository::class)->relasiEdmRktm($tahun);
        }
        ksort($data);

        return md5((string) json_encode($data));
    }

    /**
     * Ringkasan khusus jenis untuk tab pratinjau (dari indukIksk/strukturRktm/komponenEdm/jumlah butir): judul + butir
     * kalimat; null untuk SK.
     *
     * @param  list<array<string, mixed>>  $baris
     * @return array{judul: string, butir: list<string>}|null
     */
    public static function ringkasanJenis(string $jenis, array $baris, int $tahun): ?array
    {
        return match ($jenis) {
            'iksk' => ['judul' => 'IKSK per SK induk', 'butir' => array_map(
                fn (array $s) => "{$s['kode_sk']} — {$s['jumlah']} indikator".($s['uraian_sk'] === null ? ' · SK induk tidak ada di master' : ''),
                self::indukIksk($baris, $tahun),
            )],
            'rktm' => ['judul' => 'Struktur standar (SNP)', 'butir' => [...array_map(
                fn (array $s) => "Standar {$s['nomor']} {$s['snp']} — ".count($s['kegiatan'])." kegiatan, {$s['jumlah']} sub kegiatan",
                self::strukturRktm($baris),
            ), ...self::bandingTahunRktm($baris, $tahun)]],
            'edm' => ['judul' => 'Komponen yang tersimpan', 'butir' => [...array_map(
                fn (array $k) => "{$k['huruf']} {$k['nama']} — {$k['jumlah']} indikator".($k['nama_lama'] !== null && $k['nama_lama'] !== $k['nama'] ? " (sebelumnya {$k['nama_lama']})" : ($k['nama_lama'] === null ? ' (komponen baru)' : '')),
                self::komponenEdm($baris, $tahun),
            ), ...(($relasi = self::relasiEdm($baris)) !== null ? ['Relasi EDM ↔ RKTM: '.count($relasi).' pasangan ('.count(array_unique(array_column($relasi, 'kode_edm'))).' indikator)'] : [])]],
            'budaya' => ['judul' => 'Jumlah butir', 'butir' => [count(array_filter($baris, fn (array $b) => in_array($b['status'], self::TERSIMPAN, true))).' butir tersimpan (baku '.PemeriksaBudayaMutu::JUMLAH_BAKU.')']],
            'rencana_aksi' => ['judul' => 'Rencana aksi per leading', 'butir' => self::ringkasanRencanaAksi($baris)],
            default => null,
        };
    }

    /**
     * Perbandingan RKTM berkas dengan master tahun bermaster sebelumnya (kode RKTM dinomori ulang tiap tahun): jumlah
     * tetap, berganti kode (nama sub kegiatan sama), baru, dan tidak ada lagi (PetaRktm::banding). Kosong bila tidak ada
     * tahun sebelumnya.
     *
     * @param  list<array<string, mixed>>  $baris  Baris pratinjau RKTM.
     * @return list<string>
     */
    public static function bandingTahunRktm(array $baris, int $tahun): array
    {
        $sebelum = SalinMasterKode::sumber($tahun);
        if ($sebelum === null || $sebelum > $tahun) {
            return [];
        }
        $berkas = array_values(array_map(fn (array $b) => ['kode' => (string) $b['kode'], 'sub_kegiatan' => (string) $b['tampil']['sub_kegiatan']],
            array_filter($baris, fn (array $b) => in_array($b['status'], self::TERSIMPAN, true))));
        $banding = PetaRktm::banding(MasterKode::kodeRktm($sebelum), $berkas);
        $contoh = array_slice(array_map(fn (array $p) => "{$p['lama']} → {$p['baru']}", $banding['pindah']), 0, 5);

        return ["Dibanding TA {$sebelum}: {$banding['tetap']} tetap, ".count($banding['pindah']).' berganti kode'.($contoh !== [] ? ' ('.implode(', ', $contoh).(count($banding['pindah']) > 5 ? ', …' : '').')' : '')
            .', '.count($banding['ganti_nama']).' berganti nama, '.count($banding['baru']).' baru, '.count($banding['hilang']).' tidak ada lagi'];
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
     * Komponen EDM dari pratinjau EDM: per huruf (urut huruf) nama dari baris tersimpan pertama huruf itu (kolom Komponen
     * berkas, atau nama master bila kosong; huruf itu sendiri bila keduanya tidak ada), nama lama di master, jumlah
     * indikator berkas per status. Hanya huruf yang punya baris baru/berubah/tetap — huruf lain keluar dari master.
     *
     * @param  list<array<string, mixed>>  $baris  Baris pratinjau EDM.
     * @return list<array{huruf: string, nama: string, nama_lama: string|null, jumlah: int, status: array<string, int>}>
     */
    public static function komponenEdm(array $baris, int $tahun): array
    {
        $lama = app(MasterKodeRepository::class)->komponenEdm($tahun);
        $hasil = [];
        foreach ($baris as $b) {
            if (! in_array($b['status'], self::TERSIMPAN, true)) {
                continue;
            }
            $huruf = substr((string) $b['kode'], 0, 1);
            $hasil[$huruf] ??= ['huruf' => $huruf, 'nama' => (string) $b['tampil']['komponen'], 'nama_lama' => $lama[$huruf] ?? null, 'jumlah' => 0, 'status' => []];
            $hasil[$huruf]['jumlah']++;
            $hasil[$huruf]['status'][$b['status']] = ($hasil[$huruf]['status'][$b['status']] ?? 0) + 1;
        }
        ksort($hasil);

        return array_values($hasil);
    }

    /**
     * Relasi EDM ↔ RKTM dari baris pratinjau EDM yang tersimpan (urut berkas); null bila berkas tidak memuat kolom Kode
     * RKTM (relasi master tetap).
     *
     * @param  list<array<string, mixed>>  $baris
     * @return list<array{kode_edm: string, kode_rktm: string}>|null
     */
    public static function relasiEdm(array $baris): ?array
    {
        $tersimpan = array_filter($baris, fn (array $b) => in_array($b['status'], self::TERSIMPAN, true));
        if (array_filter($tersimpan, fn (array $b) => array_key_exists('rktm', $b['tampil'])) === []) {
            return null;
        }
        $hasil = [];
        foreach ($tersimpan as $b) {
            foreach (PemeriksaEdm::daftarRktm((string) ($b['tampil']['rktm'] ?? '')) as $rktm) {
                $hasil[] = ['kode_edm' => (string) $b['kode'], 'kode_rktm' => $rktm];
            }
        }

        return $hasil;
    }

    /**
     * Ringkasan rencana aksi tersimpan per leading (+ tanpa leading) beserta total anggaran.
     *
     * @param  list<array<string, mixed>>  $baris
     * @return list<string>
     */
    private static function ringkasanRencanaAksi(array $baris): array
    {
        $per = [];
        [$dipa, $komite] = [0, 0];
        foreach ($baris as $b) {
            if (! in_array($b['status'], self::TERSIMPAN, true)) {
                continue;
            }
            $kode = (string) $b['tampil']['leading'];
            $per[$kode] = ($per[$kode] ?? 0) + 1;
            $dipa += (int) $b['tampil']['pagu_dipa'];
            $komite += (int) $b['tampil']['pagu_komite'];
        }
        uksort($per, fn (string $a, string $b) => ($a === '') <=> ($b === '') ?: version_compare($a, $b));
        $butir = array_map(fn (string $kode, int $n) => ($kode === '' ? 'Tanpa leading' : $kode.' '.(MasterKode::leading($kode)['nama_unit'] ?? ''))." — {$n} rencana aksi", array_keys($per), $per);
        if ($per !== []) {
            $butir[] = 'Anggaran DIPA '.Format::rupiah($dipa).' · Komite '.Format::rupiah($komite);
        }

        return $butir;
    }

    /**
     * Baris pratinjau → baris master jenisnya. Rencana aksi membawa tag EDM & 5 Budaya Mutu manual dari rencana aksi lama
     * yang dicocokkan (`cocok`).
     *
     * @param  array<string, mixed>  $b
     * @param  array<string, array<string, mixed>>  $lamaRa  Kode → rencana aksi tersimpan.
     * @return array<string, mixed>
     */
    private static function barisMaster(string $jenis, array $b, array $lamaRa = []): array
    {
        if ($jenis === 'rencana_aksi') {
            $lama = $b['cocok'] !== null ? ($lamaRa[$b['cocok']] ?? null) : null;
            $t = $b['tampil'];

            return [
                'kode' => $b['kode'], 'uraian' => $t['uraian'],
                'kode_sk' => $t['kode_sk'] ?: null, 'kode_iksk' => $t['kode_iksk'] ?: null, 'kode_rktm' => $t['kode_rktm'] ?: null,
                'unit' => $t['unit'] ?: null, 'kode_leading' => $t['leading'] ?: null,
                'pagu_dipa' => (int) $t['pagu_dipa'], 'pagu_komite' => (int) $t['pagu_komite'],
                'bulan' => RencanaAksi::bulanDariTeks($t['bulan']),
                'kode_edm' => $lama['kode_edm'] ?? null, 'budaya_mutu' => array_values($lama['budaya_mutu'] ?? []),
            ];
        }

        return match ($jenis) {
            'sk' => ['kode' => $b['kode'], 'uraian' => $b['tampil']['uraian']],
            'iksk' => ['kode' => $b['kode'], 'kode_sk' => $b['tampil']['kode_sk'], 'uraian' => $b['tampil']['uraian']],
            'rktm' => ['kode' => $b['kode'], 'snp' => $b['tampil']['snp'], 'kegiatan' => $b['tampil']['kegiatan'], 'sub_kegiatan' => $b['tampil']['sub_kegiatan']],
            'edm' => ['kode' => $b['kode'], 'komponen' => substr($b['kode'], 0, 1), 'uraian' => $b['tampil']['uraian']],
            'budaya' => ['nomor' => (int) $b['kode'], 'nama' => $b['tampil']['nama']],
            default => throw new LogicException("Penyimpanan master {$jenis} belum tersedia."),
        };
    }
}
