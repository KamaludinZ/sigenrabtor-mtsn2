<?php

namespace App\Support\Dokumen;

use App\Contracts\KegiatanRepository;
use App\Contracts\RiwayatDokumenRepository;
use App\Contracts\TemplateDokumenRepository;
use App\Enums\StatusKegiatan;
use App\Enums\SumberDana;
use App\Support\Format;
use App\Support\Kegiatan\TahunKegiatan;
use App\Support\MasterKode;
use App\Support\Pengaturan\TautanAkunLeading;
use DateTimeImmutable;

/**
 * Struktur riwayat dokumen (PRD §7 Riwayat Dokumen, F28) lewat RiwayatDokumenRepository: `entri()` menyusun satu catatan
 * dari kegiatan saat dicetak/diunduh/diarsipkan (cuplikan nama, leading, tahun, status, cap DRAF, isi ringkas), `kunciVersi()`
 * = kegiatan × jenis × sumber × format (penomoran versi), dan `tampil()` = catatan siap tampil (label jenis/format/asal/
 * status, ukuran terbaca, nama unit leading, ketersediaan kegiatan untuk dibuka ulang). Daftar & penyaringan di halaman
 * Riwayat Dokumen memakai struktur ini.
 */
final class RiwayatDokumen
{
    /** Format berkas → [label, ikon]. */
    public const FORMAT = [
        'pdf' => ['PDF', 'file-text'],
        'docx' => ['Word', 'file-text'],
        'xlsx' => ['Excel', 'sheet'],
    ];

    /** Asal catatan → label. */
    public const ASAL = [
        'cetak' => 'Dicetak',
        'unduh' => 'Diunduh',
        'arsip' => 'Arsip final',
    ];

    /**
     * Catatan riwayat satu berkas dokumen kegiatan (tanpa id, versi & waktu — diisi repositori).
     *
     * @param  array<string, mixed>  $kegiatan
     * @param  array{id?: int|null, name: string}  $pengguna
     * @param  array{total_rab?: int|null, bagian_lengkap?: int|null, jumlah_bagian?: int|null}  $ringkas
     * @param  array{sidik: string, cover: array<string, string|null>|null, bagian: list<array<string, mixed>>, rab: array<string, mixed>|null}|null  $isi  Cuplikan isi saat berkas dibuat (IsiRiwayatDokumen::cuplikan); null = tidak disimpan.
     * @return array<string, mixed>
     */
    public static function entri(array $kegiatan, string $jenis, ?string $sumber, string $format, string $asal, string $namaBerkas, ?int $ukuran, bool $draf, array $pengguna, array $ringkas = [], ?string $nomor = null, ?array $isi = null, ?string $template = null): array
    {
        return [
            'kegiatan_id' => isset($kegiatan['id']) ? (int) $kegiatan['id'] : null,
            'tahun_anggaran' => TahunKegiatan::dari($kegiatan),
            'kode_leading' => (string) ($kegiatan['leading'] ?? ''),
            'nama_kegiatan' => trim((string) ($kegiatan['nama_kegiatan'] ?? '')) ?: 'Tanpa nama',
            'jenis' => $jenis,
            'sumber' => $sumber,
            'format' => $format,
            'asal' => $asal,
            'nama_berkas' => $namaBerkas,
            'ukuran' => $ukuran,
            'status' => (StatusKegiatan::tryFrom((string) ($kegiatan['status'] ?? '')) ?? StatusKegiatan::Draf)->value,
            'bertanda_draf' => $draf,
            'nomor_dokumen' => $nomor,
            'ringkas' => [
                'total_rab' => $ringkas['total_rab'] ?? null,
                'bagian_lengkap' => $ringkas['bagian_lengkap'] ?? null,
                'jumlah_bagian' => $ringkas['jumlah_bagian'] ?? null,
            ],
            'user_id' => isset($pengguna['id']) ? (int) $pengguna['id'] : null,
            'dibuat_oleh' => $pengguna['name'],
            'isi' => $isi,
            'template' => $jenis === 'RAB' ? null : $template,
        ];
    }

    /** Awalan nama berkas per jenis dokumen (sama dengan StatusCetak::namaBerkas / CetakDokumen::jenisBerkas). */
    public const AWALAN = ['lengkap' => 'KAK-TOR_RAB', 'KAK+TOR' => 'KAK-TOR', 'KAK' => 'KAK', 'TOR' => 'TOR', 'RAB' => 'RAB'];

    /** Nama berkas baku dengan tahun dokumen itu sendiri, mis. "RAB_Supervisi_Akademik_DIPA_2025_DRAF.xlsx". */
    public static function namaBerkas(string $jenis, string $namaKegiatan, ?string $sumber, int $tahun, bool $draf, string $format): string
    {
        $nama = rtrim(mb_substr(trim((string) preg_replace('/[^A-Za-z0-9]+/', '_', $namaKegiatan), '_') ?: 'Kegiatan', 0, 60), '_');

        return implode('_', [self::AWALAN[$jenis] ?? $jenis, $nama, ...($sumber ? [mb_strtoupper($sumber)] : []), (string) $tahun]).($draf ? '_'.StatusCetak::TANDA_AIR : '').'.'.$format;
    }

    /** @param  array<string, mixed>  $entri */
    public static function kunciVersi(array $entri): string
    {
        return implode('|', [$entri['kegiatan_id'] ?? 'x:'.mb_strtolower($entri['nama_kegiatan'] ?? ''), $entri['jenis'], $entri['sumber'] ?? '', $entri['format']]);
    }

    /**
     * Catatan siap tampil.
     *
     * @param  array<string, mixed>  $entri
     * @param  list<int>  $kegiatanAda  Id kegiatan yang masih tersimpan (untuk dibuka ulang).
     * @return array<string, mixed>
     */
    public static function tampil(array $entri, array $kegiatanAda = []): array
    {
        $status = StatusKegiatan::tryFrom((string) $entri['status']) ?? StatusKegiatan::Draf;

        return $entri + [
            'label_jenis' => DokumenKegiatan::JENIS[$entri['jenis']] ?? $entri['jenis'],
            'label_format' => self::FORMAT[$entri['format']][0] ?? strtoupper($entri['format']),
            'ikon_format' => self::FORMAT[$entri['format']][1] ?? 'file-text',
            'label_asal' => self::ASAL[$entri['asal']] ?? $entri['asal'],
            'label_status' => $status->label(),
            'nama_leading' => MasterKode::leading($entri['kode_leading'])['nama_unit'] ?? null,
            'ukuran_teks' => $entri['ukuran'] ? self::ukuran((int) $entri['ukuran']) : null,
            'tersedia' => $entri['kegiatan_id'] !== null && in_array((int) $entri['kegiatan_id'], $kegiatanAda, true),
            // Template Word yang berlaku saat dokumen dibuat (dicatat CatatRiwayatDokumen; null = susunan baku/RAB/catatan lama).
            'template_nama' => filled($entri['template'] ?? null) ? (app(TemplateDokumenRepository::class)->temukan((string) $entri['template'])['nama'] ?? null) : null,
        ];
    }

    /** "245 KB", "1,2 MB" (sama dengan klien `ukuranBerkas`). */
    public static function ukuran(int $bita): string
    {
        return $bita < 1024 * 1024 ? max(1, (int) round($bita / 1024)).' KB' : str_replace('.', ',', number_format($bita / (1024 * 1024), 1)).' MB';
    }

    /** @return list<array<string, mixed>> Semua catatan, terbaru dulu. */
    public static function semua(): array
    {
        return app(RiwayatDokumenRepository::class)->semua();
    }

    /**
     * Catatan dalam cakupan akun: admin semua; akun leading dokumen leading-nya (TautanAkunLeading) atau buatannya.
     * Otorisasi lengkap per aksi (akun aktif, buka ulang, unduh arsip, duplikat) lewat OtorisasiDokumen.
     *
     * @param  array<string, mixed>  $entri
     * @param  array{id?: int|null, role: string, leadings?: list<string>}  $pengguna
     */
    public static function boleh(array $entri, array $pengguna): bool
    {
        return TautanAkunLeading::bolehAkses($pengguna, ($entri['kode_leading'] ?? '') ?: null)
            || ($entri['user_id'] !== null && $entri['user_id'] === ($pengguna['id'] ?? null));
    }

    /**
     * Catatan siap tampil dalam cakupan akun, terbaru dulu (+ `tautan_kegiatan` pratinjau bila kegiatannya tersimpan); akun
     * nonaktif tidak melihat apa pun (OtorisasiDokumen::akunAktif).
     *
     * @param  array{id?: int|null, role: string, leadings?: list<string>}  $pengguna
     * @return list<array<string, mixed>>
     */
    public static function untuk(array $pengguna): array
    {
        if (! OtorisasiDokumen::akunAktif($pengguna)) {
            return [];
        }
        $ada = array_map(fn (array $k) => (int) $k['id'], app(KegiatanRepository::class)->semua());

        return array_values(array_map(function (array $c) use ($ada) {
            $t = self::tampil($c, $ada);

            return $t + ['tautan_kegiatan' => $t['tersedia'] ? route('kegiatan.pratinjau', array_filter(['kegiatan' => $c['kegiatan_id'], 'sumber' => $c['sumber']])) : null];
        }, array_filter(self::semua(), fn (array $c) => self::boleh($c, $pengguna))));
    }

    /**
     * Kelompok per tahun anggaran, tahun terbaru dulu (urutan catatan di dalamnya tetap).
     *
     * @param  list<array<string, mixed>>  $baris
     * @return array<int, list<array<string, mixed>>>
     */
    public static function perTahun(array $baris): array
    {
        $kelompok = [];
        foreach ($baris as $b) {
            $kelompok[(int) $b['tahun_anggaran']][] = $b;
        }
        krsort($kelompok);

        return $kelompok;
    }

    /** Kolom catatan siap tampil yang dicari kata kunci. */
    public const KOLOM_CARI = ['nama_kegiatan', 'nama_berkas', 'nomor_dokumen', 'kode_leading', 'nama_leading', 'dibuat_oleh', 'label_jenis', 'label_format', 'sumber', 'label_status', 'label_asal'];

    /** Kata kunci → daftar kata (huruf kecil, tanpa spasi ganda); maksimal 100 karakter. */
    public static function kataKunci(?string $kata): array
    {
        $kata = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', mb_substr((string) $kata, 0, 100))));

        return $kata === '' ? [] : array_values(array_unique(explode(' ', $kata)));
    }

    /**
     * Catatan yang setiap kata kuncinya muncul di salah satu `KOLOM_CARI` (tanpa beda huruf besar); kata kosong = semua.
     *
     * @param  list<array<string, mixed>>  $baris  Catatan siap tampil.
     * @return list<array<string, mixed>>
     */
    public static function cari(array $baris, ?string $kata): array
    {
        $daftar = self::kataKunci($kata);
        if ($daftar === []) {
            return $baris;
        }

        return array_values(array_filter($baris, function (array $b) use ($daftar) {
            $teks = mb_strtolower(implode(' ', array_map(fn (string $k) => (string) ($b[$k] ?? ''), self::KOLOM_CARI)));

            return collect($daftar)->every(fn (string $k) => str_contains($teks, $k));
        }));
    }

    /** Teks dengan kata kunci yang cocok dibungkus <mark> (semua bagian di-escape lebih dulu). */
    public static function sorot(?string $teks, ?string $kata): string
    {
        $teks = (string) $teks;
        $daftar = self::kataKunci($kata);
        if ($daftar === [] || $teks === '') {
            return e($teks);
        }
        usort($daftar, fn (string $a, string $b) => mb_strlen($b) <=> mb_strlen($a));
        $pola = '/('.implode('|', array_map(fn (string $k) => preg_quote($k, '/'), $daftar)).')/iu';
        $bagian = preg_split($pola, $teks, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$teks];

        return implode('', array_map(fn (string $p, int $i) => $i % 2 === 1 ? '<mark class="rounded-sm bg-gold-100 px-0.5 text-inherit">'.e($p).'</mark>' : e($p), $bagian, array_keys($bagian)));
    }

    /** Saringan halaman & endpoint Riwayat Dokumen (selain kata kunci; PRD §7 "Cari & Saring"). */
    public const SARINGAN = ['jenis', 'format', 'leading', 'sumber', 'status', 'dari', 'sampai'];

    /** Saringan pilihan → kolom catatan yang dibandingkan. */
    public const KOLOM_SARINGAN = ['jenis' => 'jenis', 'format' => 'format', 'leading' => 'kode_leading', 'sumber' => 'sumber', 'status' => 'status'];

    /**
     * Saringan dirapikan: jenis `DokumenKegiatan::JENIS`, format `FORMAT`, leading yang dikenal master (termasuk nonaktif),
     * sumber dana `SumberDana`, status `StatusKegiatan` (status kegiatan saat dokumen dibuat), tanggal Y-m-d sah (lainnya
     * kosong); `terbalik` = sampai sebelum dari (rentang tidak diterapkan sampai diperbaiki).
     *
     * @param  array<string, mixed>  $nilai
     * @return array{jenis: string, format: string, leading: string, sumber: string, status: string, dari: string, sampai: string, terbalik: bool}
     */
    public static function rapikanSaringan(array $nilai): array
    {
        $tanggal = function (mixed $t): string {
            $t = is_string($t) ? trim($t) : '';
            $tanggal = preg_match('/^\d{4}-\d{2}-\d{2}$/', $t) ? DateTimeImmutable::createFromFormat('!Y-m-d', $t) : false;

            return $tanggal && $tanggal->format('Y-m-d') === $t ? $t : '';
        };
        $teks = fn (string $kunci) => is_string($nilai[$kunci] ?? null) ? trim($nilai[$kunci]) : '';
        $hasil = [
            'jenis' => isset(DokumenKegiatan::JENIS[$teks('jenis')]) ? $teks('jenis') : '',
            'format' => isset(self::FORMAT[$teks('format')]) ? $teks('format') : '',
            'leading' => $teks('leading') !== '' && MasterKode::leading($teks('leading')) !== null ? MasterKode::leading($teks('leading'))['kode'] : '',
            'sumber' => SumberDana::tryFrom($teks('sumber'))?->value ?? '',
            'status' => StatusKegiatan::tryFrom($teks('status'))?->value ?? '',
            'dari' => $tanggal($nilai['dari'] ?? null),
            'sampai' => $tanggal($nilai['sampai'] ?? null),
        ];

        return $hasil + ['terbalik' => $hasil['dari'] !== '' && $hasil['sampai'] !== '' && $hasil['sampai'] < $hasil['dari']];
    }

    /**
     * Catatan yang cocok dengan saringan pilihan (`KOLOM_SARINGAN`, sama persis) dan rentang tanggal dibuat (inklusif,
     * tanggal zona aplikasi); rentang terbalik diabaikan.
     *
     * @param  list<array<string, mixed>>  $baris
     * @param  array<string, mixed>  $saringan
     * @return list<array<string, mixed>>
     */
    public static function saring(array $baris, array $saringan): array
    {
        $s = self::rapikanSaringan($saringan);
        $dari = $s['terbalik'] ? '' : $s['dari'];
        $sampai = $s['terbalik'] ? '' : $s['sampai'];

        return array_values(array_filter($baris, function (array $b) use ($s, $dari, $sampai) {
            $tanggal = substr((string) $b['dibuat_pada'], 0, 10);

            foreach (self::KOLOM_SARINGAN as $kunci => $kolom) {
                if ($s[$kunci] !== '' && (string) ($b[$kolom] ?? '') !== $s[$kunci]) {
                    return false;
                }
            }

            return ($dari === '' || $tanggal >= $dari)
                && ($sampai === '' || $tanggal <= $sampai);
        }));
    }

    /**
     * Saringan aktif sebagai chip: kunci (jenis|format|leading|sumber|status|tanggal) → label.
     *
     * @param  array<string, mixed>  $saringan
     * @return array<string, string>
     */
    public static function labelSaringan(array $saringan): array
    {
        $s = self::rapikanSaringan($saringan);
        $label = [];
        if ($s['jenis'] !== '') {
            $label['jenis'] = DokumenKegiatan::JENIS[$s['jenis']];
        }
        if ($s['format'] !== '') {
            $label['format'] = self::FORMAT[$s['format']][0];
        }
        if ($s['leading'] !== '') {
            $label['leading'] = 'Leading '.$s['leading'];
        }
        if ($s['sumber'] !== '') {
            $label['sumber'] = 'Sumber dana '.$s['sumber'];
        }
        if ($s['status'] !== '') {
            $label['status'] = 'Status '.StatusKegiatan::from($s['status'])->label();
        }
        if (! $s['terbalik'] && ($s['dari'] !== '' || $s['sampai'] !== '')) {
            $label['tanggal'] = match (true) {
                $s['dari'] !== '' && $s['sampai'] !== '' => Format::tanggal($s['dari']).' – '.Format::tanggal($s['sampai']),
                $s['dari'] !== '' => 'Sejak '.Format::tanggal($s['dari']),
                default => 'Sampai '.Format::tanggal($s['sampai']),
            };
        }

        return $label;
    }

    /** Urutan jenis di kartu kegiatan: set lengkap dulu, lalu KAK/TOR, lalu RAB. */
    public const URUT_JENIS = ['lengkap', 'KAK+TOR', 'KAK', 'TOR', 'RAB'];

    /** Urutan tampilan kronologis → label. */
    public const URUT = ['terbaru' => 'Terbaru dulu', 'terlama' => 'Terlama dulu'];

    /**
     * Kelompok per kegiatan (kegiatan tersimpan per id, kegiatan yang tidak tersimpan per nama × tahun), kegiatan yang
     * dokumennya terakhir dibuat paling baru dulu; di dalamnya satu baris per jenis × sumber × format (`kunciVersi`) berisi
     * versi terbaru dan versi lama (terbaru dulu), urut sumber lalu jenis (`URUT_JENIS`) lalu format.
     *
     * @param  list<array<string, mixed>>  $baris  Catatan siap tampil (`untuk`), terbaru dulu.
     * @return list<array<string, mixed>>
     */
    public static function perKegiatan(array $baris): array
    {
        $kegiatan = [];
        foreach ($baris as $b) {
            $kunci = $b['kegiatan_id'] !== null ? 'k'.$b['kegiatan_id'] : 'x'.$b['tahun_anggaran'].'|'.mb_strtolower($b['nama_kegiatan']);
            $kegiatan[$kunci] ??= [
                'kunci' => $kunci,
                'kegiatan_id' => $b['kegiatan_id'],
                'nama_kegiatan' => $b['nama_kegiatan'],
                'tahun_anggaran' => (int) $b['tahun_anggaran'],
                'kode_leading' => $b['kode_leading'],
                'nama_leading' => $b['nama_leading'] ?? null,
                'tersedia' => $b['tersedia'] ?? false,
                'tautan_kegiatan' => $b['tautan_kegiatan'] ?? null,
                'terakhir' => $b['dibuat_pada'],
                'dokumen' => [],
            ];
            $kegiatan[$kunci]['terakhir'] = max($kegiatan[$kunci]['terakhir'], $b['dibuat_pada']);
            $versi = self::kunciVersi($b);
            if (! isset($kegiatan[$kunci]['dokumen'][$versi])) {
                $kegiatan[$kunci]['dokumen'][$versi] = ['kunci' => $versi, 'terbaru' => $b, 'lama' => []];
            } elseif ($b['dibuat_pada'] > $kegiatan[$kunci]['dokumen'][$versi]['terbaru']['dibuat_pada']) {
                array_unshift($kegiatan[$kunci]['dokumen'][$versi]['lama'], $kegiatan[$kunci]['dokumen'][$versi]['terbaru']);
                $kegiatan[$kunci]['dokumen'][$versi]['terbaru'] = $b;
            } else {
                $kegiatan[$kunci]['dokumen'][$versi]['lama'][] = $b;
            }
        }
        $jenis = array_flip(self::URUT_JENIS);
        $format = array_flip(array_keys(self::FORMAT));
        foreach ($kegiatan as &$k) {
            foreach ($k['dokumen'] as &$d) {
                usort($d['lama'], fn (array $a, array $b) => $b['dibuat_pada'] <=> $a['dibuat_pada']);
            }
            unset($d);
            $k['dokumen'] = array_values($k['dokumen']);
            usort($k['dokumen'], fn (array $a, array $b) => [(string) $a['terbaru']['sumber'], $jenis[$a['terbaru']['jenis']] ?? 9, $format[$a['terbaru']['format']] ?? 9]
                <=> [(string) $b['terbaru']['sumber'], $jenis[$b['terbaru']['jenis']] ?? 9, $format[$b['terbaru']['format']] ?? 9]);
            $k['jumlah'] = array_sum(array_map(fn (array $d) => 1 + count($d['lama']), $k['dokumen']));
        }
        unset($k);
        $kegiatan = array_values($kegiatan);
        usort($kegiatan, fn (array $a, array $b) => $b['terakhir'] <=> $a['terakhir']);

        return $kegiatan;
    }

    /**
     * @param  list<array<string, mixed>>  $baris
     * @return array{jumlah: int, kegiatan: int, tahun: int, arsip: int, draf: int, format: array<string, int>}
     */
    public static function ringkasan(array $baris): array
    {
        return [
            'jumlah' => count($baris),
            'kegiatan' => count(array_unique(array_map(fn (array $b) => $b['kegiatan_id'] ?? 'x:'.mb_strtolower($b['nama_kegiatan']), $baris))),
            'tahun' => count(array_unique(array_column($baris, 'tahun_anggaran'))),
            'arsip' => count(array_filter($baris, fn (array $b) => $b['asal'] === 'arsip')),
            'draf' => count(array_filter($baris, fn (array $b) => $b['bertanda_draf'])),
            'format' => array_map(fn (string $f) => count(array_filter($baris, fn (array $b) => $b['format'] === $f)), array_combine(array_keys(self::FORMAT), array_keys(self::FORMAT))),
        ];
    }
}
