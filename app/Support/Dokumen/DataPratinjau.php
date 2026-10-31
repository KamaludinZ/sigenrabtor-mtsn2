<?php

namespace App\Support\Dokumen;

use App\Enums\StatusKegiatan;
use App\Support\Kegiatan\UbahDataKegiatan;
use App\Support\Rab\AnggaranKegiatan;
use InvalidArgumentException;

/**
 * Data pratinjau dokumen satu kegiatan × sumber dana (PRD F15 "Pratinjau = hasil cetak", §5 Pratinjau & Edit) untuk
 * endpoint `kegiatan.pratinjau.data` — disusun dari jalur yang sama dengan tab Pratinjau (`Kegiatan\PratinjauDokumen`):
 * SusunanKak/SusunanRab dari data terbaru, cover (CoverDokumen::desain + TeksCover::nilai), daftar isi penampil
 * (PenampilDokumen::isi), cap DRAF set & per lembar (StatusCetak + saran), penanda isian kosong (PenandaKosong),
 * kelengkapan per bagian (KelengkapanDokumen), kaidah dokumen (KaidahDokumen), dan bidang yang bisa disunting langsung
 * per bagian (SuntingDokumen: label, jenis, batas, petunjuk, teks penyunting, tampil di dokumen) beserta jejak sunting
 * terakhir (RiwayatSunting). `lembar` membatasi lembar yang diisi (CetakDokumen::pilihan; kosong = semua); sumber yang
 * tidak dipilih kegiatan → InvalidArgumentException. `template` = master KAK/TOR terpilih Format Seragam (PilihanTemplate::masterKak;
 * Word kegiatan itu disusun server dari master). `versi` = UbahDataKegiatan::versi untuk dikirim balik saat menyunting.
 */
final class DataPratinjau
{
    /** Jumlah jejak sunting terakhir yang ikut. */
    public const BATAS_RIWAYAT = 10;

    /**
     * @param  array<string, mixed>  $kegiatan
     * @param  array{id?: int|null, name?: string, role?: string}  $pengguna
     * @param  string|list<string>|null  $lembar
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException Sumber dana tidak dipilih kegiatan.
     */
    public static function untuk(array $kegiatan, ?string $sumber, array $pengguna, string|array|null $lembar = null): array
    {
        if ($sumber !== null && ! array_key_exists($sumber, AnggaranKegiatan::perSumber($kegiatan))) {
            throw new InvalidArgumentException("Sumber dana {$sumber} tidak dipilih untuk kegiatan ini.");
        }
        $id = (int) $kegiatan['id'];
        $lembar = CetakDokumen::pilihan($lembar);
        $kak = SusunanKak::dari($kegiatan, $sumber);
        $rab = SusunanRab::dari($kegiatan, $sumber);
        $cetak = StatusCetak::dari($kegiatan, $kak->sumber, $kak, $rab);
        $cover = CoverDokumen::untukKegiatan($kegiatan, $kak->sumber) + ['nilai' => TeksCover::nilai($kegiatan, $kak->sumber)];
        $penanda = PenandaKosong::daftar($kak, $rab);
        $kaidah = KaidahDokumen::periksa($kegiatan, $kak, $rab, $cover);
        $kelengkapan = KelengkapanDokumen::dari($kak, $rab, $kegiatan, $pengguna, $cetak->set);
        $status = StatusKegiatan::tryFrom((string) ($kegiatan['status'] ?? '')) ?? StatusKegiatan::Draf;
        $alasanTerkunci = UbahDataKegiatan::alasanTerkunci($kegiatan);
        $cap = fn (StatusDraf $s) => ['draf' => $s->draf, 'alasan' => array_column($s->alasan, 'teks')];

        return [
            'kegiatan_id' => $id,
            'nama_kegiatan' => (string) ($kegiatan['nama_kegiatan'] ?? ''),
            'status' => $status->value,
            'label_status' => $status->label(),
            'sumber' => $kak->sumber,
            'daftar_sumber' => array_keys(AnggaranKegiatan::perSumber($kegiatan)),
            'lembar' => $lembar,
            'template_dipakai' => TemplateDipakai::untuk($kegiatan, $kak->sumber, $kak->format),
            'template' => ($master = PilihanTemplate::masterKak($kegiatan)) !== null ? ['kak' => $master['id'], 'nama' => $master['nama']] : ['kak' => null, 'nama' => null],
            'dapat_diubah' => $alasanTerkunci === null,
            'alasan_terkunci' => $alasanTerkunci,
            'versi' => UbahDataKegiatan::versi($kegiatan),
            'cetak' => [
                'keadaan' => $cetak->keadaan(),
                'draf' => $cetak->set->draf,
                'alasan_draf' => $cetak->saran(),
                'nama_berkas' => CetakDokumen::namaBerkas($cetak, $lembar),
                'tautan' => CetakDokumen::tautan($id, $kak->sumber, $lembar),
            ],
            'daftar_isi' => array_values(array_filter(PenampilDokumen::isi($kak, $rab), fn (array $i) => in_array($i['lembar'], $lembar, true))),
            'dokumen' => [
                'cover' => in_array('cover', $lembar, true) ? $cover + $cap($cetak->set) : null,
                'kak' => in_array('kak', $lembar, true) ? self::kak($kegiatan, $kak, $rab, $pengguna) + $cap($cetak->kak) : null,
                'rab' => in_array('rab', $lembar, true) ? PembuatDokumen::rab($rab) + $cap($cetak->rab) : null,
            ],
            'penanda_kosong' => ['ringkasan' => PenandaKosong::ringkasan($penanda), 'daftar' => $penanda],
            'kelengkapan' => [
                'persen' => $kelengkapan->persen(),
                'jumlah' => array_combine(array_keys(KelengkapanDokumen::STATUS), array_map(fn (string $s) => $kelengkapan->jumlah($s), array_keys(KelengkapanDokumen::STATUS))),
                'wajib' => $kelengkapan->wajib,
                'bagian' => $kelengkapan->bagian,
            ],
            'kaidah' => ['ringkasan' => KaidahDokumen::ringkasan($kaidah), 'aturan' => $kaidah],
            'riwayat_sunting' => RiwayatSunting::untuk($id, batas: self::BATAS_RIWAYAT),
        ];
    }

    /**
     * Bagian KAK/TOR menurut format (FormatKakTor; `label` nomor tercetak 1–9 / I–V): blok isi SusunanKak (penunjuk `sunting`/`sunting_butir` & `asal`/`wajib` penanda kosong
     * ikut), status kelengkapan SusunanOtomatis, dan bidang yang bisa disunting di bagian itu.
     *
     * @param  array<string, mixed>  $kegiatan
     * @param  array<string, mixed>  $pengguna
     * @return array{bagian: list<array<string, mixed>>, kurang: list<string>, peringatan_pagu: string|null, format: array{kunci: string, label: string}}
     */
    private static function kak(array $kegiatan, SusunanKak $kak, SusunanRab $rab, array $pengguna): array
    {
        $status = array_column(SusunanOtomatis::dari($kak, $rab, $kegiatan, $pengguna)->bagian, null, 'nomor');

        return [
            'bagian' => array_map(function (array $b) use ($kegiatan, $status, $kak) {
                $s = $status[$b['nomor']] ?? ['status' => 'lengkap', 'kurang' => [], 'periksa' => []];
                $tambahan = SuntingDokumen::tambahan($b, $kak->format);

                return [
                    'nomor' => $b['nomor'],
                    'label' => SusunanKak::labelBagian($b),
                    'judul' => $b['judul'],
                    'jangkar' => "cetak-kak-bagian-{$b['nomor']}",
                    'isi' => array_values($b['isi']),
                    'status' => $s['status'],
                    'kurang' => array_values($s['kurang']),
                    'periksa' => $s['status'] === 'kurang' ? [] : array_values($s['periksa']),
                    'kosong' => PenandaKosong::jumlahBagian($b),
                    'sunting' => array_map(fn (string $kunci) => [
                        'kunci' => $kunci,
                        'label' => SuntingDokumen::label($kunci, $kak->format),
                        'jenis' => SuntingDokumen::jenis($kunci),
                        'maks' => SuntingDokumen::maks($kunci),
                        'petunjuk' => SuntingDokumen::petunjuk($kunci),
                        'teks' => SuntingDokumen::teks($kegiatan, $kunci),
                        'tampil' => ! in_array($kunci, $tambahan, true),
                    ], SuntingDokumen::untukBagian($b['nomor'], $kak->format)),
                ];
            }, $kak->bagian),
            'kurang' => array_values(array_unique($kak->kurang)),
            'peringatan_pagu' => $kak->peringatanPagu?->judul(),
            'format' => ['kunci' => $kak->format, 'label' => FormatKakTor::label($kak->format)],
        ];
    }
}
