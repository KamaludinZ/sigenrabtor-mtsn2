<?php

namespace App\Support\Dokumen;

use App\Enums\StatusKegiatan;
use App\Support\Kegiatan\AlurStatus;
use App\Support\Rab\AnggaranKegiatan;
use InvalidArgumentException;

/**
 * Layanan validasi kelengkapan bagian dokumen (PRD F5 "Periksa Kelengkapan — bagian kosong dan data wajib yang belum
 * terisi ditandai", aturan 8): satu pemeriksaan per kegiatan × jenis dokumen (DokumenKegiatan::JENIS, bagian yang
 * dimuat JenisDokumen::ISI) × sumber dana, disusun dari aturan yang sudah ada — status per bagian KelengkapanDokumen
 * (cover, sembilan bagian KAK/TOR dari SusunanOtomatis, RAB), penanda kosong PenandaKosong (tingkat wajib/dokumen, tempat
 * melengkapi, bidang yang bisa disunting di pratinjau), asal isi bagian (SusunanOtomatis::ASAL), syarat pengajuan
 * (AlurStatus::syaratAjukan, selama status bisa diajukan) dan kesiapan cetak (PemeriksaanCetak). Dipakai endpoint
 * `kegiatan.kelengkapan` (`periksa()` atau `bagian()` satu kunci cover|kak-{n}|rab). Jenis/sumber/kunci asing →
 * InvalidArgumentException.
 */
final class ValidasiKelengkapan
{
    /**
     * @param  array<string, mixed>  $kegiatan
     * @param  array{id?: int|null, role?: string}  $pengguna
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    public static function periksa(array $kegiatan, ?string $sumber, array $pengguna, string $jenis = 'lengkap'): array
    {
        if (! isset(DokumenKegiatan::JENIS[$jenis])) {
            throw new InvalidArgumentException("Jenis dokumen {$jenis} tidak dikenal.");
        }
        if ($sumber !== null && ! array_key_exists($sumber, AnggaranKegiatan::perSumber($kegiatan))) {
            throw new InvalidArgumentException("Sumber dana {$sumber} tidak dipilih untuk kegiatan ini.");
        }
        $id = filter_var($kegiatan['id'] ?? null, FILTER_VALIDATE_INT) ?: null;
        $kak = SusunanKak::dari($kegiatan, $sumber);
        $rab = SusunanRab::dari($kegiatan, $sumber);
        $isi = JenisDokumen::ISI[$jenis];
        $kelengkapan = KelengkapanDokumen::dari($kak, $rab, $kegiatan, $pengguna);
        $asal = array_column(SusunanOtomatis::dari($kak, $rab, $kegiatan, $pengguna)->bagian, 'asal', 'nomor');
        $penanda = PenandaKosong::daftar($kak, $rab);

        $bagian = [];
        foreach ($kelengkapan->bagian as $b) {
            if (! in_array($b['dokumen'], $isi, true)) {
                continue;
            }
            $nomor = $b['dokumen'] === 'kak' ? (int) substr($b['kunci'], 4) : null;
            $milik = array_values(array_filter($penanda, fn (array $p) => $p['dokumen'] === $b['dokumen'] && ($b['dokumen'] !== 'kak' || $p['bagian'] === $nomor)));
            if ($b['kunci'] === 'cover' && $b['kurang'] !== []) {
                $milik[] = ['dokumen' => 'cover', 'bagian' => null, 'pesan' => 'Isi nama kegiatan agar tercetak di cover.', 'tingkat' => 'wajib', 'asal' => 'identitas', 'sunting' => null];
            }
            $bagian[] = [
                'kunci' => $b['kunci'],
                'dokumen' => $b['dokumen'],
                'nomor' => $nomor,
                'label' => $b['label'],
                'jangkar' => $b['jangkar'],
                'status' => $b['status'],
                'label_status' => KelengkapanDokumen::STATUS[$b['status']][0],
                'kurang' => $b['kurang'],
                'periksa' => $b['periksa'],
                'penanda' => array_map(fn (array $p) => [
                    'pesan' => $p['pesan'],
                    'tingkat' => $p['tingkat'],
                    'label_tingkat' => PenandaKosong::TINGKAT[$p['tingkat']][0],
                    'tempat' => PenandaKosong::tempat($p['asal'], $id, $pengguna),
                    'sunting' => $p['sunting'] === null ? null : ['kunci' => $p['sunting'], 'label' => SuntingDokumen::label($p['sunting'], $kak->format)],
                ], $milik),
                'wajib' => count(array_filter($milik, fn (array $p) => $p['tingkat'] === 'wajib')),
                'asal' => $nomor !== null ? ($asal[$nomor] ?? []) : [PenandaKosong::tempat($b['dokumen'] === 'rab' ? 'rab' : 'identitas', $id, $pengguna)],
                'sunting' => $nomor !== null ? array_map(fn (string $k) => ['kunci' => $k, 'label' => SuntingDokumen::label($k, $kak->format)], SuntingDokumen::untukBagian($nomor, $kak->format)) : [],
            ];
        }

        $jumlah = array_map(fn (string $s) => count(array_filter($bagian, fn (array $b) => $b['status'] === $s)), array_combine(array_keys(KelengkapanDokumen::STATUS), array_keys(KelengkapanDokumen::STATUS)));
        $cetak = PemeriksaanCetak::periksa($kegiatan, $jenis, $kak->sumber);
        $status = StatusKegiatan::tryFrom((string) ($kegiatan['status'] ?? '')) ?? StatusKegiatan::Draf;
        $bisaDiajukan = in_array($status, StatusKegiatan::semuaAksi()['ajukan']['dari'], true);
        $syarat = $bisaDiajukan ? array_values(AlurStatus::syaratAjukan($kegiatan)) : [];

        return [
            'kegiatan_id' => $id,
            'jenis' => $jenis,
            'label_jenis' => DokumenKegiatan::JENIS[$jenis],
            'sumber' => $kak->sumber,
            'lengkap' => $jumlah['lengkap'] === count($bagian),
            'persen' => $bagian === [] ? 100 : intdiv($jumlah['lengkap'] * 100, count($bagian)),
            'jumlah' => $jumlah + ['bagian' => count($bagian), 'wajib' => array_sum(array_column($bagian, 'wajib'))],
            'bagian' => $bagian,
            'ajukan' => ['berlaku' => $bisaDiajukan, 'siap' => $bisaDiajukan && $syarat === [], 'halangan' => $syarat],
            'cetak' => ['siap' => $cetak['siap'], 'draf' => $cetak['draf'], 'alasan_draf' => $cetak['alasan_draf'], 'kaidah_gagal' => $cetak['kaidah_gagal'], 'nama_berkas' => $cetak['nama_berkas']],
        ];
    }

    /**
     * Satu bagian (kunci cover|kak-{n}|rab) set dokumen lengkap sumber dana itu.
     *
     * @param  array<string, mixed>  $kegiatan
     * @param  array{id?: int|null, role?: string}  $pengguna
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    public static function bagian(array $kegiatan, ?string $sumber, string $kunci, array $pengguna): array
    {
        return collect(self::periksa($kegiatan, $sumber, $pengguna)['bagian'])->firstWhere('kunci', $kunci)
            ?? throw new InvalidArgumentException("Bagian dokumen {$kunci} tidak dikenal.");
    }
}
