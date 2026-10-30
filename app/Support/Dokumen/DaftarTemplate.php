<?php

namespace App\Support\Dokumen;

use App\Contracts\TemplateCoverRepository;
use App\Contracts\TemplateDokumenRepository;
use App\Support\Kegiatan\KonteksAi;
use App\Support\Kegiatan\TahunKegiatan;
use App\Support\MasterKode;
use App\Support\Pengaturan\TahunDipilih;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Daftar template yang tersedia untuk Format Seragam (PRD §9 "Pilihan Template") — satu-satunya penyusun data endpoint
 * `pengaturan.format.template` & `kegiatan.template_dokumen`:
 * - `untuk($tahun, $saringan)` = master KAK/TOR (`PilihanTemplate::JENIS_KAK`) & cover tahun itu: identitas, default,
 *   variabel/khusus leading-sumber, `ada_berkas` (berkas master/gambar ada di disk), tautan unduh/gambar admin, dan
 *   `dipakai` = di mana dipakai pilihan template (per jenis kegiatan / khusus leading); `pilihan` (`PilihanTemplate::data`),
 *   `jenis_kegiatan`, `ringkasan`, `terkunci`; saringan opsional `jenis` (KonteksAi::jenisKegiatan) & `leading`
 *   (leading terdaftar) → `berlaku` = template yang dipakai kegiatan jenis itu (`PilihanTemplate::berlakuUntuk`);
 *   saringan tak dikenal → ValidationException berkunci `jenis`/`leading`;
 * - `untukKegiatan($kegiatan)` = template yang berlaku untuk satu kegiatan (asal leading/jenis/bawaan) + rincian master
 *   & cover (tautan gambar non-admin `dokumen.cover.gambar`).
 */
final class DaftarTemplate
{
    /**
     * @param  array{jenis?: mixed, leading?: mixed}  $saringan
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public static function untuk(int $tahun, array $saringan = []): array
    {
        $jenisKegiatan = KonteksAi::jenisKegiatan();
        $jenis = is_string($saringan['jenis'] ?? null) && $saringan['jenis'] !== '' ? $saringan['jenis'] : null;
        $leading = is_string($saringan['leading'] ?? null) && $saringan['leading'] !== '' ? $saringan['leading'] : null;
        $galat = array_filter([
            'jenis' => $jenis !== null && ! isset($jenisKegiatan[$jenis]) ? 'Jenis kegiatan tidak dikenal.' : null,
            'leading' => $leading !== null && MasterKode::leading($leading) === null ? 'Leading tidak dikenal.' : null,
        ]);
        if ($galat !== []) {
            throw ValidationException::withMessages($galat);
        }

        $pilihan = PilihanTemplate::data($tahun);
        $dipakai = self::pemakaian($pilihan);
        $master = collect(app(TemplateDokumenRepository::class)->semua($tahun))->keyBy('id');
        $cover = collect(app(TemplateCoverRepository::class)->semua($tahun))->keyBy('id');
        $kak = array_map(fn (array $k) => $k + [
            'ada_berkas' => ($m = $master->get($k['id'])) !== null && Storage::disk('local')->exists((string) $m['file_path']),
            'nama_berkas' => $master->get($k['id'])['nama_berkas'] ?? null,
            'tautan_unduh' => route('pengaturan.template.master.unduh', $k['id']),
            'dipakai' => $dipakai['kak'][$k['id']] ?? [],
        ], PilihanTemplate::pilihanKak($tahun));
        $sampul = array_map(fn (array $c) => $c + [
            'leading' => $cover->get($c['id'])['leading'] ?? null,
            'sumber_dana' => $cover->get($c['id'])['sumber_dana'] ?? null,
            'ada_berkas' => ($g = $cover->get($c['id'])) !== null && Storage::disk('local')->exists((string) $g['gambar_path']),
            'tautan_gambar' => route('pengaturan.template.cover.gambar', $c['id']),
            'dipakai' => $dipakai['cover'][$c['id']] ?? [],
        ], PilihanTemplate::pilihanCover($tahun));

        return [
            'tahun' => $tahun,
            'terkunci' => TahunDipilih::terkunci($tahun),
            'saringan' => ['jenis' => $jenis, 'leading' => $leading],
            'kak' => $kak,
            'cover' => $sampul,
            'pilihan' => $pilihan,
            'jenis_kegiatan' => array_map(fn (string $kunci, string $label) => ['kunci' => $kunci, 'label' => $label], array_keys($jenisKegiatan), $jenisKegiatan),
            'berlaku' => $jenis === null ? null : self::rincian(PilihanTemplate::berlakuUntuk($jenis, $leading, $tahun), $tahun, admin: true),
            'ringkasan' => [
                'kak' => count($kak),
                'cover' => count($sampul),
                'kak_dipakai' => count(array_filter($kak, fn (array $k) => $k['dipakai'] !== [])),
                'cover_dipakai' => count(array_filter($sampul, fn (array $c) => $c['dipakai'] !== [])),
                'tanpa_berkas' => count(array_filter([...$kak, ...$sampul], fn (array $t) => ! $t['ada_berkas'])),
            ],
            'diperbarui_pada' => $pilihan['diperbarui_pada'],
        ];
    }

    /**
     * @param  array<string, mixed>  $kegiatan
     * @return array<string, mixed>
     */
    public static function untukKegiatan(array $kegiatan): array
    {
        $tahun = TahunKegiatan::dari($kegiatan);

        return ['kegiatan_id' => (int) $kegiatan['id'], 'tahun' => $tahun]
            + self::rincian(PilihanTemplate::untukKegiatan($kegiatan, $tahun), $tahun, admin: false);
    }

    /**
     * @param  array{jenis: string, asal: string, kak: string|null, cover: string|null}  $berlaku
     * @return array<string, mixed>
     */
    private static function rincian(array $berlaku, int $tahun, bool $admin): array
    {
        $master = $berlaku['kak'] !== null ? app(TemplateDokumenRepository::class)->temukan($berlaku['kak']) : null;
        $cover = $berlaku['cover'] !== null ? app(TemplateCoverRepository::class)->temukan($berlaku['cover']) : null;
        $label = ['leading' => 'Khusus leading', 'jenis' => 'Pilihan per jenis kegiatan', 'bawaan' => 'Bawaan (susunan baku & cover default)'];

        return [
            'jenis' => $berlaku['jenis'],
            'label_jenis' => KonteksAi::jenisKegiatan()[$berlaku['jenis']] ?? $berlaku['jenis'],
            'asal' => $berlaku['asal'],
            'label_asal' => $label[$berlaku['asal']] ?? $berlaku['asal'],
            'kak' => $master === null ? null : [
                'id' => (string) $master['id'], 'nama' => (string) $master['nama'], 'jenis' => (string) $master['jenis'],
                'ada_berkas' => Storage::disk('local')->exists((string) $master['file_path']),
            ],
            'kak_hilang' => $berlaku['kak'] !== null && $master === null,
            'cover' => $cover === null ? null : [
                'id' => (string) $cover['id'], 'nama' => (string) $cover['nama'],
                'tautan_gambar' => route($admin ? 'pengaturan.template.cover.gambar' : 'dokumen.cover.gambar', (string) $cover['id']),
            ],
            'cover_hilang' => $berlaku['cover'] !== null && $cover === null,
            'susunan_baku' => $master === null,
        ];
    }

    /**
     * Di mana tiap template dipakai pilihan template: {kak|cover: {id: list {asal, jenis, label_jenis, leading?}}}.
     *
     * @param  array<string, mixed>  $pilihan  PilihanTemplate::data
     * @return array{kak: array<string, list<array<string, string|null>>>, cover: array<string, list<array<string, string|null>>>}
     */
    public static function pemakaian(array $pilihan): array
    {
        $hasil = ['kak' => [], 'cover' => []];
        foreach ($pilihan['jenis'] as $p) {
            foreach (['kak', 'cover'] as $k) {
                if ($p[$k] !== null) {
                    $hasil[$k][$p[$k]][] = ['asal' => 'jenis', 'jenis' => $p['kunci'], 'label_jenis' => $p['label'], 'leading' => null];
                }
            }
        }
        foreach ($pilihan['leading'] as $p) {
            foreach (['kak', 'cover'] as $k) {
                if ($p[$k] !== null) {
                    $hasil[$k][$p[$k]][] = ['asal' => 'leading', 'jenis' => $p['jenis'], 'label_jenis' => $p['label_jenis'], 'leading' => $p['leading']];
                }
            }
        }

        return $hasil;
    }
}
