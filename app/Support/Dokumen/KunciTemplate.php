<?php

namespace App\Support\Dokumen;

use App\Contracts\TemplateDokumenRepository;
use App\Enums\StatusKegiatan;
use App\Support\Kegiatan\KunciDokumen;
use App\Support\Kegiatan\TahunKegiatan;
use App\Support\Kegiatan\UbahDataKegiatan;

/**
 * Penguncian pilihan template dokumen final (PRD §9 "Pilihan Template per Kegiatan": "Setelah dokumen final dikunci,
 * pilihan template tidak dapat diubah kecuali dibuka admin (perubahan dicatat siapa & kapan)"):
 * - `bekukan($kegiatan, $pengguna)` — dipanggil AlurStatus::terapkan saat kegiatan menjadi Final: template Word & format
 *   yang berlaku saat itu (PilihanTemplate::untukKegiatan, FormatKakTor::untuk — termasuk yang otomatis) disimpan sebagai
 *   pilihan kegiatan (`template_kak`, `format_dokumen`) sehingga dokumen final tidak ikut berubah bila admin kemudian
 *   mengganti default leading, aturan Format Seragam, atau format tahun; dicatat entri `kunci`;
 * - `berlaku($kegiatan)` — selama Final, template yang dibekukan tetap dipakai walau kini nonaktif/bukan pilihan leading
 *   (PilihanTemplate::untukKegiatan);
 * - `alasan($kegiatan)` — kenapa pilihan template tidak bisa diubah (Final: terkunci bersama dokumen final, minta admin
 *   membuka kunci; status/tahun terkunci lain: UbahDataKegiatan::alasanTerkunci); selama Final UbahDataKegiatan sudah
 *   menolak perubahan;
 * - `catat($sebelum, $sesudah, $aksi, $pengguna)` — riwayat pilihan template di kegiatan `riwayat_template` (siapa &
 *   kapan; dicatat UbahDataKegiatan setiap template/format berubah, maks. BATAS_RIWAYAT terbaru);
 * - `riwayat($kegiatan)` — terbaru dulu + `label_aksi` & `teks`.
 */
final class KunciTemplate
{
    public const BATAS_RIWAYAT = 20;

    public const AKSI = [
        'ubah' => 'Pilihan diubah',
        'kunci' => 'Dikunci bersama dokumen final',
    ];

    /** @param  array<string, mixed>  $kegiatan */
    public static function terkunci(array $kegiatan): bool
    {
        return ($kegiatan['status'] ?? null) === StatusKegiatan::Final->value;
    }

    /** @param  array<string, mixed>  $kegiatan */
    public static function alasan(array $kegiatan): ?string
    {
        if (self::terkunci($kegiatan)) {
            $jejak = KunciDokumen::dari($kegiatan);

            return 'Template & format dikunci bersama dokumen final'.($jejak ? ' ('.lcfirst($jejak->kalimat()).')' : '')
                .' — minta admin membuka kunci untuk menggantinya.';
        }

        return UbahDataKegiatan::alasanTerkunci($kegiatan);
    }

    /**
     * Template yang dibekukan untuk kegiatan Final (null = bukan Final, tanpa pilihan, atau templatenya sudah tidak ada).
     *
     * @param  array<string, mixed>  $kegiatan
     */
    public static function berlaku(array $kegiatan): ?string
    {
        $id = $kegiatan['template_kak'] ?? null;

        return self::terkunci($kegiatan) && filled($id) && app(TemplateDokumenRepository::class)->temukan((string) $id) !== null ? (string) $id : null;
    }

    /**
     * @param  array<string, mixed>  $kegiatan  Sebelum statusnya menjadi Final.
     * @param  array{id?: int, name: string}  $pengguna
     * @return array<string, mixed>
     */
    public static function bekukan(array $kegiatan, array $pengguna): array
    {
        $tahun = TahunKegiatan::dari($kegiatan);
        $beku = ['template_kak' => PilihanTemplate::untukKegiatan($kegiatan, $tahun)['kak'], 'format_dokumen' => FormatKakTor::untuk($kegiatan, $tahun)] + $kegiatan;

        return self::catat($kegiatan, $beku, 'kunci', $pengguna);
    }

    /**
     * $sesudah dengan entri riwayat bila template/format berubah (aksi `kunci` selalu dicatat).
     *
     * @param  array<string, mixed>  $sebelum
     * @param  array<string, mixed>  $sesudah
     * @param  array{id?: int, name: string}  $pengguna
     * @return array<string, mixed>
     */
    public static function catat(array $sebelum, array $sesudah, string $aksi, array $pengguna): array
    {
        $nilai = fn (array $k, string $kunci) => filled($k[$kunci] ?? null) ? (string) $k[$kunci] : null;
        [$templateLama, $templateBaru] = [$nilai($sebelum, 'template_kak'), $nilai($sesudah, 'template_kak')];
        [$formatLama, $formatBaru] = [$nilai($sebelum, 'format_dokumen'), $nilai($sesudah, 'format_dokumen')];
        if ($aksi !== 'kunci' && $templateLama === $templateBaru && $formatLama === $formatBaru) {
            return $sesudah;
        }

        $repo = app(TemplateDokumenRepository::class);
        $entri = [
            'aksi' => $aksi,
            'pada' => now()->toIso8601String(),
            'oleh' => $pengguna['name'],
            'user_id' => isset($pengguna['id']) ? (int) $pengguna['id'] : null,
            'template_lama' => $templateLama,
            'template_baru' => $templateBaru,
            // Nama disalin agar riwayat tetap terbaca walau templatenya kelak dihapus.
            'nama_lama' => $templateLama !== null ? ($repo->temukan($templateLama)['nama'] ?? null) : null,
            'nama_baru' => $templateBaru !== null ? ($repo->temukan($templateBaru)['nama'] ?? null) : null,
            'format_lama' => $formatLama,
            'format_baru' => $formatBaru,
        ];
        $sesudah['riwayat_template'] = array_slice([...array_values((array) ($sebelum['riwayat_template'] ?? [])), $entri], -self::BATAS_RIWAYAT);

        return $sesudah;
    }

    /**
     * @param  array<string, mixed>  $kegiatan
     * @return list<array<string, mixed>>
     */
    public static function riwayat(array $kegiatan): array
    {
        return array_map(fn (array $e) => $e + ['label_aksi' => self::AKSI[$e['aksi']] ?? $e['aksi'], 'teks' => self::teks($e)],
            array_reverse(array_values((array) ($kegiatan['riwayat_template'] ?? []))));
    }

    /** @param  array<string, mixed>  $e */
    public static function teks(array $e): string
    {
        $template = fn (?string $id, ?string $nama) => $id === null ? 'otomatis' : '“'.($nama ?? 'template terhapus').'”';
        $format = fn (?string $f) => $f === null ? 'ikut tahun anggaran' : FormatKakTor::label($f);
        if ($e['aksi'] === 'kunci') {
            return 'Template Word '.($e['template_baru'] === null ? 'susunan baku' : $template($e['template_baru'], $e['nama_baru'])).', '.mb_strtolower($format($e['format_baru'])).'.';
        }

        return implode('; ', array_filter([
            $e['template_lama'] !== $e['template_baru'] ? 'Template Word: '.$template($e['template_lama'], $e['nama_lama']).' → '.$template($e['template_baru'], $e['nama_baru']) : null,
            $e['format_lama'] !== $e['format_baru'] ? 'Format: '.$format($e['format_lama']).' → '.$format($e['format_baru']) : null,
        ])).'.';
    }
}
