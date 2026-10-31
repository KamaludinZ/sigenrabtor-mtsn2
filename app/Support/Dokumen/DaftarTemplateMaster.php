<?php

namespace App\Support\Dokumen;

use App\Contracts\KegiatanRepository;
use App\Contracts\TemplateDokumenRepository;
use App\Support\Kegiatan\TahunKegiatan;
use App\Support\MasterKode;
use App\Support\Pengaturan\TahunDipilih;
use Illuminate\Support\Facades\Storage;

/**
 * Halaman utama Template Master (PRD Fase 5 "Template KAK/TOR Variabel (Multi per Leading)"): semua master .docx ber-variabel
 * satu tahun anggaran, dikelompokkan per pemilik — umum (semua leading) lalu per leading — dengan penanda default per
 * pemilik × jenis, status aktif/nonaktif, catatan versi, variabel, berkas, dan pemakaian (pilihan template Format Seragam
 * & kegiatan tahun itu yang memakainya lewat PilihanTemplate::untukKegiatan).
 * - `semua($tahun)` = baris tiap master (`leading` null = umum, `catatan` null, `aktif` bawaan true — atribut yang belum
 *   ditulis repositori dibaca bawaannya);
 * - `saringan($nilai)` merapikan `SARINGAN` (cari ≤ `MAKS_CARI` nama/catatan/berkas, leading ''|umum|kode, jenis
 *   TemplateDokumenRepository::JENIS, status `STATUS`), `saring()`, `ringkasan()`, `kelompok()`;
 * - `data($tahun, $nilai)` = semuanya untuk halaman (dan kelak endpoint).
 */
final class DaftarTemplateMaster
{
    public const SARINGAN = ['cari', 'leading', 'jenis', 'status'];

    /** Status → [label, kelas lencana]. */
    public const STATUS = [
        'aktif' => ['Aktif', 'bg-brand-50 text-brand-800 ring-brand-200'],
        'nonaktif' => ['Nonaktif', 'bg-stone-100 text-stone-600 ring-stone-200'],
    ];

    public const UMUM = 'umum';

    public const MAKS_CARI = 100;

    /**
     * @return list<array{id: string, nama: string, jenis: string, label_jenis: string, leading: string|null, label_leading: string, catatan: string|null, aktif: bool, default: bool, variabel: int, nama_berkas: string|null, ada_berkas: bool, diperbarui_pada: string|null, pengunggah: string|null, dipakai: list<array<string, string|null>>, kegiatan: int, dokumen: int, boleh_dihapus: bool, tautan_unduh: string}>
     */
    public static function semua(int $tahun): array
    {
        $pakai = DaftarTemplate::pemakaian(PilihanTemplate::data($tahun))['kak'];
        $kegiatan = self::kegiatanPerTemplate($tahun);
        $semua = app(TemplateDokumenRepository::class)->semua($tahun);
        $pemakaian = PemakaianTemplate::jumlah(array_map(fn (array $t) => (string) $t['id'], $semua));

        return array_map(function (array $t) use ($pakai, $kegiatan, $pemakaian) {
            $leading = filled($t['leading'] ?? null) ? (string) $t['leading'] : null;

            return [
                'id' => (string) $t['id'],
                'nama' => (string) $t['nama'],
                'jenis' => (string) $t['jenis'],
                'label_jenis' => TemplateDokumenRepository::JENIS[$t['jenis']] ?? (string) $t['jenis'],
                'leading' => $leading,
                'label_leading' => self::labelPemilik($leading),
                'catatan' => filled($t['catatan'] ?? null) ? (string) $t['catatan'] : null,
                'aktif' => (bool) ($t['aktif'] ?? true),
                // Template nonaktif tidak dipakai untuk kegiatan baru, jadi tidak dihitung default.
                'default' => (bool) ($t['is_default'] ?? false) && (bool) ($t['aktif'] ?? true),
                'variabel' => count((array) ($t['variabel'] ?? [])),
                'nama_berkas' => $t['nama_berkas'] ?? null,
                'ada_berkas' => filled($t['file_path'] ?? null) && Storage::disk('local')->exists((string) $t['file_path']),
                'diperbarui_pada' => $t['diperbarui_pada'] ?? null,
                'pengunggah' => $t['pengunggah'] ?? null,
                'dipakai' => $pakai[$t['id']] ?? [],
                'kegiatan' => $kegiatan[$t['id']] ?? 0,
                // Dokumen tercatat (hasil susunan & Riwayat Dokumen) — template yang sudah dipakai dokumen/dipilih kegiatan tidak bisa dihapus.
                'dokumen' => $pemakaian[$t['id']]['dokumen'],
                'boleh_dihapus' => $pemakaian[$t['id']] === ['kegiatan' => 0, 'dokumen' => 0],
                'tautan_unduh' => route('pengaturan.template.master.unduh', (string) $t['id']),
            ];
        }, $semua);
    }

    /**
     * Satu baris semua() untuk template $id (tahun template itu); null bila tidak ada / bukan master.
     *
     * @return array<string, mixed>|null
     */
    public static function baris(string $id): ?array
    {
        $master = app(TemplateDokumenRepository::class)->temukan($id);

        return $master === null ? null : collect(self::semua((int) $master['tahun']))->firstWhere('id', $id);
    }

    /** Label pemilik: "Umum — semua leading" atau "{kode} · {nama unit}" (leading nonaktif ditandai). */
    public static function labelPemilik(?string $leading): string
    {
        if ($leading === null) {
            return 'Umum — semua leading';
        }
        $l = MasterKode::leading($leading);

        return $l === null ? $leading : "{$leading} · {$l['nama_unit']}".(($l['is_active'] ?? true) ? '' : ' (nonaktif)');
    }

    /**
     * Saringan rapi; nilai tak dikenal = '' (semua).
     *
     * @param  array<string, mixed>  $nilai
     * @return array{cari: string, leading: string, jenis: string, status: string}
     */
    public static function saringan(array $nilai): array
    {
        $teks = fn (string $k) => is_string($nilai[$k] ?? null) ? trim($nilai[$k]) : '';
        $leading = $teks('leading');

        return [
            'cari' => mb_substr((string) preg_replace('/\s+/u', ' ', $teks('cari')), 0, self::MAKS_CARI),
            'leading' => $leading === self::UMUM || ($leading !== '' && MasterKode::leading($leading) !== null) ? $leading : '',
            'jenis' => isset(TemplateDokumenRepository::JENIS[$teks('jenis')]) ? $teks('jenis') : '',
            'status' => isset(self::STATUS[$teks('status')]) ? $teks('status') : '',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $baris  semua()
     * @param  array{cari: string, leading: string, jenis: string, status: string}  $saringan
     * @return list<array<string, mixed>>
     */
    public static function saring(array $baris, array $saringan): array
    {
        $kata = array_values(array_filter(explode(' ', mb_strtolower($saringan['cari']))));

        return array_values(array_filter($baris, function (array $t) use ($saringan, $kata) {
            $teks = mb_strtolower(implode(' ', [$t['nama'], $t['catatan'] ?? '', $t['nama_berkas'] ?? '', $t['label_jenis']]));

            return match (true) {
                $saringan['leading'] === self::UMUM && $t['leading'] !== null => false,
                $saringan['leading'] !== '' && $saringan['leading'] !== self::UMUM && $t['leading'] !== $saringan['leading'] => false,
                $saringan['jenis'] !== '' && $t['jenis'] !== $saringan['jenis'] => false,
                $saringan['status'] === 'aktif' && ! $t['aktif'], $saringan['status'] === 'nonaktif' && $t['aktif'] => false,
                default => collect($kata)->every(fn (string $k) => str_contains($teks, $k)),
            };
        }));
    }

    /**
     * @param  list<array<string, mixed>>  $baris
     * @return array{jumlah: int, aktif: int, nonaktif: int, umum: int, khusus: int, leading: int, dipakai: int, tanpa_berkas: int}
     */
    public static function ringkasan(array $baris): array
    {
        $hitung = fn (callable $syarat) => count(array_filter($baris, $syarat));

        return [
            'jumlah' => count($baris),
            'aktif' => $hitung(fn (array $t) => $t['aktif']),
            'nonaktif' => $hitung(fn (array $t) => ! $t['aktif']),
            'umum' => $hitung(fn (array $t) => $t['leading'] === null),
            'khusus' => $hitung(fn (array $t) => $t['leading'] !== null),
            'leading' => count(array_unique(array_filter(array_column($baris, 'leading')))),
            'dipakai' => $hitung(fn (array $t) => $t['dipakai'] !== [] || $t['kegiatan'] > 0),
            'tanpa_berkas' => $hitung(fn (array $t) => ! $t['ada_berkas']),
        ];
    }

    /**
     * Kelompok per pemilik — umum dulu, lalu leading menurut urutan master — template di dalamnya default dulu, lalu
     * aktif, lalu nama; `default` = nama template default tiap jenis pemilik itu.
     *
     * @param  list<array<string, mixed>>  $baris
     * @return list<array{kunci: string, leading: string|null, label: string, template: list<array<string, mixed>>, default: array<string, string>}>
     */
    public static function kelompok(array $baris): array
    {
        $urutLeading = array_flip(array_column(MasterKode::semuaLeading(), 'kode'));
        $kelompok = [];
        foreach ($baris as $t) {
            $kelompok[$t['leading'] ?? self::UMUM][] = $t;
        }
        uksort($kelompok, fn (string $a, string $b) => [$a !== self::UMUM, $urutLeading[$a] ?? PHP_INT_MAX, $a] <=> [$b !== self::UMUM, $urutLeading[$b] ?? PHP_INT_MAX, $b]);

        return array_map(function (string $kunci, array $template) {
            usort($template, fn (array $a, array $b) => [! $a['default'], ! $a['aktif'], mb_strtolower($a['nama'])] <=> [! $b['default'], ! $b['aktif'], mb_strtolower($b['nama'])]);
            $leading = $kunci === self::UMUM ? null : $kunci;

            return [
                'kunci' => $kunci,
                'leading' => $leading,
                'label' => self::labelPemilik($leading),
                'template' => $template,
                'default' => array_column(array_filter($template, fn (array $t) => $t['default']), 'nama', 'jenis'),
            ];
        }, array_keys($kelompok), array_values($kelompok));
    }

    /**
     * @param  array<string, mixed>  $nilai  Saringan mentah (URL).
     * @return array{tahun: int, terkunci: bool, saringan: array<string, string>, ringkasan: array<string, int>, tersaring: int, kelompok: list<array<string, mixed>>, tanpa_khusus: list<array{kode: string, nama_unit: string}>}
     */
    public static function data(int $tahun, array $nilai = []): array
    {
        $semua = self::semua($tahun);
        $saringan = self::saringan($nilai);
        $cocok = self::saring($semua, $saringan);
        $berpemilik = array_unique(array_filter(array_column($semua, 'leading')));

        return [
            'tahun' => $tahun,
            'terkunci' => TahunDipilih::terkunci($tahun),
            'saringan' => $saringan,
            'ringkasan' => self::ringkasan($semua),
            'tersaring' => count($cocok),
            'kelompok' => self::kelompok($cocok),
            // Leading aktif yang belum punya template khusus: dokumennya memakai template umum / susunan baku.
            'tanpa_khusus' => array_values(array_map(fn (array $l) => ['kode' => $l['kode'], 'nama_unit' => $l['nama_unit']], array_filter(MasterKode::leadings(), fn (array $l) => ! in_array($l['kode'], $berpemilik, true)))),
        ];
    }

    /**
     * Banyak kegiatan tahun itu yang memakai tiap master lewat pilihan template (PilihanTemplate::untukKegiatan).
     *
     * @return array<string, int>
     */
    private static function kegiatanPerTemplate(int $tahun): array
    {
        $hitung = [];
        foreach (app(KegiatanRepository::class)->semua() as $kegiatan) {
            if (TahunKegiatan::dari($kegiatan) !== $tahun) {
                continue;
            }
            $kak = PilihanTemplate::untukKegiatan($kegiatan, $tahun)['kak'] ?? null;
            if ($kak !== null) {
                $hitung[$kak] = ($hitung[$kak] ?? 0) + 1;
            }
        }

        return $hitung;
    }
}
