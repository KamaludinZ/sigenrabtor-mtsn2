<?php

namespace App\Support\Dokumen;

use App\Support\Rab\AnggaranKegiatan;
use InvalidArgumentException;

/**
 * Pemeriksaan kelengkapan dokumen sebelum cetak (PRD aturan 8 "Cek kelengkapan saat cetak", F12): untuk satu jenis
 * dokumen (DokumenKegiatan::JENIS) × sumber dana, butir aturan 8 yang dibutuhkan jenis itu (`CEK`: tujuan, sasaran, tempat,
 * minimal 1 baris RAB, matrik, jadwal — dibaca dari ChecklistIsian beserta tautan melengkapinya), isian kurang lain dari
 * susunan dokumennya (SusunanKak/SusunanRab::$kurang), kaidah yang gagal (KaidahDokumen status `gagal`), dan keputusan
 * cetak (StatusCetak: KAK/TOR, RAB, atau set lengkap). Dokumen **selalu boleh dicetak** — yang belum lengkap/belum
 * disetujui tercetak bercap DRAF (`draf`, `alasan_draf`, `tanda_air`, `nama_berkas`); `siap` = tanpa DRAF, semua CEK
 * lengkap, dan tanpa kaidah gagal. Dipakai endpoint `kegiatan.periksa_cetak` dan kelak pembuat Word/PDF.
 */
final class PemeriksaanCetak
{
    private const KAK = ['KAK+TOR', 'KAK', 'TOR', 'lengkap'];

    /** Butir aturan 8 → [label, jenis yang memerlukannya, grup ChecklistIsian, kunci butir ({sumber} = sumber dana)]. */
    public const CEK = [
        'tujuan' => ['Tujuan', self::KAK, 'identitas', 'tujuan'],
        'sasaran' => ['Sasaran', self::KAK, 'identitas', 'sasaran'],
        'tempat' => ['Tempat pelaksanaan', self::KAK, 'pelaksanaan', 'tempat'],
        'rincian_rab' => ['Rincian RAB (minimal 1 baris)', ['RAB', 'lengkap'], 'anggaran', 'rincian_{sumber}'],
        'matrik' => ['Matrik tahapan', self::KAK, 'matrik', 'matrik'],
        'jadwal' => ['Jadwal kegiatan', self::KAK, 'matrik', 'jadwal'],
    ];

    /** Jenis dokumen → awalan nama berkas StatusCetak::namaBerkas. */
    private const NAMA = ['KAK+TOR' => 'KAK-TOR', 'KAK' => 'KAK', 'TOR' => 'TOR', 'RAB' => 'RAB', 'lengkap' => 'KAK-TOR_RAB'];

    /**
     * @param  array<string, mixed>  $kegiatan
     * @param  string|null  $sumber  null = sumber dana pertama kegiatan (seperti SusunanKak).
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException Jenis tidak dikenal atau sumber dana tidak dipilih kegiatan.
     */
    public static function periksa(array $kegiatan, string $jenis = 'lengkap', ?string $sumber = null): array
    {
        if (! isset(DokumenKegiatan::JENIS[$jenis])) {
            throw new InvalidArgumentException("Jenis dokumen {$jenis} tidak dikenal.");
        }
        $dipilih = array_map(fn ($a) => $a->sumber, AnggaranKegiatan::untuk($kegiatan));
        if ($sumber !== null && ! in_array($sumber, $dipilih, true)) {
            throw new InvalidArgumentException("Sumber dana {$sumber} tidak dipilih kegiatan ini.");
        }
        $sumber ??= $dipilih[0] ?? null;

        $kak = SusunanKak::dari($kegiatan, $sumber);
        $rab = SusunanRab::dari($kegiatan, $sumber);
        $cetak = StatusCetak::dari($kegiatan, $sumber, $kak, $rab);
        $status = match (true) {
            $jenis === 'RAB' => $cetak->rab,
            $jenis === 'lengkap' => $cetak->set,
            default => $cetak->kak,
        };

        $checklist = collect(ChecklistIsian::untuk($kegiatan))->keyBy('kunci');
        $cek = [];
        foreach (self::CEK as $kunci => [$label, $untuk, $grup, $butir]) {
            if (! in_array($jenis, $untuk, true)) {
                continue;
            }
            $temu = $sumber === null && str_contains($butir, '{sumber}') ? null
                : collect($checklist[$grup]['butir'] ?? [])->firstWhere('kunci', str_replace('{sumber}', (string) $sumber, $butir));
            $cek[] = [
                'kunci' => $kunci,
                'label' => $label,
                'lengkap' => (bool) ($temu['terisi'] ?? false),
                'keterangan' => $temu === null && $sumber === null ? 'Pilih sumber dana agar RAB dapat disusun.' : ($temu['keterangan'] ?? null),
                'tautan' => $checklist[$grup]['tautan'] ?? null,
            ];
        }

        $kurang = array_values(array_unique(match (true) {
            $jenis === 'RAB' => $rab->kurang,
            $jenis === 'lengkap' => [...$kak->kurang, ...$rab->kurang],
            default => $kak->kurang,
        }));
        $kaidahGagal = array_values(array_map(
            fn (array $k) => ['kunci' => $k['kunci'], 'nomor' => $k['nomor'], 'judul' => $k['judul'], 'pesan' => $k['pesan']],
            array_filter(KaidahDokumen::periksa($kegiatan, $kak, $rab), fn (array $k) => $k['status'] === 'gagal'),
        ));
        $lengkap = collect($cek)->every(fn (array $c) => $c['lengkap']);

        return [
            'kegiatan_id' => (int) ($kegiatan['id'] ?? 0),
            'jenis' => $jenis,
            'label_jenis' => DokumenKegiatan::JENIS[$jenis],
            'sumber' => $sumber,
            'boleh_cetak' => true,
            'lengkap' => $lengkap,
            'cek' => $cek,
            'kurang' => $kurang,
            'kaidah_gagal' => $kaidahGagal,
            'draf' => $status->draf,
            'alasan_draf' => array_map(fn (array $a) => $a + ['saran' => StatusCetak::SARAN[$a['kunci']]], $status->alasan),
            'tanda_air' => $status->draf ? StatusCetak::TANDA_AIR : null,
            'nama_berkas' => self::namaBerkas($cetak, $status->draf, self::NAMA[$jenis]),
            'siap' => ! $status->draf && $lengkap && $kaidahGagal === [],
        ];
    }

    /** Nama berkas jenis ini; sufiks _DRAF mengikuti cap dokumen itu sendiri (bukan set). */
    private static function namaBerkas(StatusCetak $cetak, bool $draf, string $awalan): string
    {
        $nama = $cetak->namaBerkas($awalan);

        return $cetak->draf() && ! $draf ? str_replace('_'.StatusCetak::TANDA_AIR.'.', '.', $nama) : $nama;
    }
}
