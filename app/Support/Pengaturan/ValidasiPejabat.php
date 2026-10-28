<?php

namespace App\Support\Pengaturan;

use App\Contracts\PejabatRepository;

/**
 * Validasi data pejabat penanda tangan (PRD F8, aturan 4 "Penanda tangan terikat") di luar aturan isian satu pejabat
 * (KelolaPejabat::aturan — nama tanpa angka, NIP lewat Nip):
 * - `bentrok($pejabat, $kunci, $isian)`: temuan antar-peran satu tahun — NIP sama untuk nama berbeda, nama sama dengan NIP
 *   berbeda, Ketua Komite = Kepala Madrasah. Hanya peringatan (simpan tetap jalan) agar orang yang memegang dua peran
 *   tetap bisa dibetulkan satu per satu.
 * - `tahun($tahun)`: temuan tiap peran satu tahun (`kurang`, `nip` tersimpan tidak sah, `bentrok`) — tab Pejabat & endpoint.
 * - `pengesahan($kolom)`: kolom lembar pengesahan satu dokumen — gagal bila NIP tercetak tidak sah atau satu NIP untuk dua
 *   nama, peringatan bila nama/NIP Kepala kosong atau satu orang di dua kolom (kaidah dokumen 4).
 * Nama dibandingkan tanpa memandang huruf besar, spasi, dan tanda baca ("Muslimin, M.Pd." = "muslimin M.Pd").
 */
final class ValidasiPejabat
{
    /**
     * @param  array{kepala: array<string, mixed>, komite: array<string, mixed>, leading: array<string, array<string, mixed>>}  $pejabat  PejabatRepository::untukTahun().
     * @param  array<string, mixed>  $isian  {nama, nip} peran $kunci.
     * @return list<string>
     */
    public static function bentrok(array $pejabat, string $kunci, array $isian): array
    {
        $nama = self::kunciNama($isian['nama'] ?? null);
        $nip = Nip::bersihkan($isian['nip'] ?? null);
        if ($nama === '') {
            return [];
        }

        $temuan = [];
        foreach (self::peran($pejabat) as $lain => $p) {
            $namaLain = self::kunciNama($p['nama']);
            if ($lain === $kunci || $namaLain === '') {
                continue;
            }
            $nipLain = Nip::bersihkan($p['nip']);
            $judul = KelolaPejabat::judul($lain);
            if ($nip !== '' && $nip === $nipLain && $nama !== $namaLain) {
                $temuan[] = 'NIP '.Nip::format($nip)." juga tercatat untuk {$judul} ({$p['nama']}) — pastikan NIP-nya benar.";
            } elseif ($nama === $namaLain && $nip !== '' && $nipLain !== '' && $nip !== $nipLain) {
                $temuan[] = "{$p['nama']} juga tercatat sebagai {$judul} dengan NIP lain (".Nip::format($nipLain).').';
            }
            if ($nama === $namaLain && in_array([$kunci, $lain], [['kepala', 'komite'], ['komite', 'kepala']], true)) {
                $temuan[] = 'Ketua Komite dan Kepala Madrasah orang yang sama — blok "Mengetahui" dan "Menyetujui" ditandatangani satu orang.';
            }
        }

        return array_values(array_unique($temuan));
    }

    /**
     * Temuan tiap peran tahun $tahun; peran tanpa temuan tidak dicantumkan. Ketua Komite wajib bila blok "Mengetahui"
     * tampil di dokumen sumber dana mana pun.
     *
     * @return array<string, list<array{jenis: 'kurang'|'nip'|'bentrok', pesan: string}>>
     */
    public static function tahun(int $tahun): array
    {
        $repo = app(PejabatRepository::class);
        $pejabat = $repo->untukTahun($tahun);
        $komiteDipakai = in_array(true, $repo->aturanMengetahui($tahun), true);

        $hasil = [];
        foreach (self::peran($pejabat) as $kunci => $p) {
            $judul = KelolaPejabat::judul($kunci);
            $temuan = [];
            if (blank($p['nama']) && ($kunci !== 'komite' || $komiteDipakai)) {
                $temuan[] = ['jenis' => 'kurang', 'pesan' => "Nama {$judul} belum diisi."];
            }
            if ($kunci === 'kepala' && blank($p['nip'])) {
                $temuan[] = ['jenis' => 'kurang', 'pesan' => 'NIP Kepala Madrasah belum diisi.'];
            }
            if (filled($p['nip']) && ($galat = Nip::galat($p['nip'])) !== null) {
                $temuan[] = ['jenis' => 'nip', 'pesan' => "NIP {$judul} tidak sah: {$galat}"];
            }
            foreach (self::bentrok($pejabat, $kunci, $p) as $pesan) {
                $temuan[] = ['jenis' => 'bentrok', 'pesan' => "{$judul}: {$pesan}"];
            }
            if ($temuan !== []) {
                $hasil[$kunci] = $temuan;
            }
        }

        return $hasil;
    }

    /**
     * @param  list<array{peran: string, nama: string|null, nip: string|null}>  $kolom  SusunanKak::pengesahan()['kolom'] / SusunanRab::$pengesahan.
     * @return array{status: 'lulus'|'peringatan'|'gagal', temuan: list<string>}
     */
    public static function pengesahan(array $kolom): array
    {
        $kolom = array_values($kolom);
        $gagal = [];
        $peringatan = [];
        $kosong = [];
        foreach ($kolom as $i => $k) {
            $peran = rtrim($k['peran'], ',');
            if (blank($k['nama'])) {
                $kosong[] = $peran;

                continue;
            }
            if ($peran === 'Menyetujui' && blank($k['nip'])) {
                $peringatan[] = 'NIP Kepala Madrasah (Menyetujui) belum diisi';
            }
            if (filled($k['nip']) && ($galat = Nip::galat($k['nip'])) !== null) {
                $gagal[] = "NIP {$peran} tidak sah — ".rtrim($galat, '.');
            }
            foreach (array_slice($kolom, $i + 1) as $l) {
                if (blank($l['nama'])) {
                    continue;
                }
                $lain = rtrim($l['peran'], ',');
                if (filled($k['nip']) && Nip::bersihkan($k['nip']) === Nip::bersihkan($l['nip']) && self::kunciNama($k['nama']) !== self::kunciNama($l['nama'])) {
                    $gagal[] = "{$peran} dan {$lain} memakai NIP yang sama untuk nama berbeda";
                } elseif (self::kunciNama($k['nama']) === self::kunciNama($l['nama'])) {
                    $peringatan[] = "{$peran} dan {$lain} orang yang sama ({$k['nama']})";
                }
            }
        }
        if ($kosong !== []) {
            array_unshift($peringatan, 'Nama penanda tangan belum diisi: '.implode(', ', $kosong));
        }

        return [
            'status' => $gagal !== [] ? 'gagal' : ($peringatan !== [] ? 'peringatan' : 'lulus'),
            'temuan' => [...$gagal, ...$peringatan],
        ];
    }

    /** Pembanding nama: huruf kecil tanpa spasi & tanda baca. */
    public static function kunciNama(?string $nama): string
    {
        return mb_strtolower((string) preg_replace('/[^\p{L}\p{N}]+/u', '', (string) $nama));
    }

    /**
     * @param  array{kepala: array<string, mixed>, komite: array<string, mixed>, leading: array<string, array<string, mixed>>}  $pejabat
     * @return array<string, array<string, mixed>> kunci peran (`kepala`, `komite`, `leading:{kode}`) → pejabat.
     */
    private static function peran(array $pejabat): array
    {
        $hasil = ['kepala' => $pejabat['kepala'], 'komite' => $pejabat['komite']];
        foreach ($pejabat['leading'] as $kode => $p) {
            $hasil["leading:{$kode}"] = $p;
        }

        return $hasil;
    }
}
