<?php

namespace App\Support\Dokumen;

use App\Contracts\PilihanTemplateRepository;
use App\Contracts\TemplateCoverRepository;
use App\Contracts\TemplateDokumenRepository;
use App\Support\Kegiatan\KonteksAi;
use App\Support\MasterKode;
use App\Support\Pengaturan\RiwayatPengaturan;
use App\Support\Pengaturan\TahunDipilih;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Pilihan template per jenis kegiatan & leading (PRD §9 "Pilihan Template"; kontrak PilihanTemplateRepository per tahun) —
 * dipakai tab Pilihan template halaman Format Seragam:
 * - `pilihanKak($tahun)` = master KAK/TOR tahun itu berjenis `JENIS_KAK` (TemplateDokumenRepository), `pilihanCover($tahun)` =
 *   cover tahun itu (TemplateCoverRepository; cover khusus leading/sumber dana diberi keterangan);
 * - `data($tahun)` = pilihan tersimpan terurai per jenis kegiatan (KonteksAi::jenisKegiatan, urutannya) dan khusus leading,
 *   beserta nama template & penanda `hilang` (template sudah dihapus);
 * - `rapikan($isian)` (jenis dikenal saja, '' = null, baris leading kosong dibuang), `galat($pilihan, $tahun)` (template
 *   harus ada di pilihan tahun itu sesuai jenisnya, leading aktif, jenis dikenal, khusus leading minimal satu template, tanpa
 *   leading × jenis ganda, maks. MAKS_LEADING baris) berkunci `jenis.{k}.kak|cover`/`leading.{i}.leading|jenis|kak|cover`;
 * - `simpan($tahun, $isian, $pengguna)` (admin; tahun terkunci → AuthorizationException; ValidationException; tanpa
 *   perubahan tidak ditulis) + riwayat `ubah_pilihan_template` (`ringkasan()`);
 * - `untukKegiatan($kegiatan)` = template yang berlaku untuk satu kegiatan: khusus leading × jenis tebakan nama
 *   (KonteksAi::tebakJenis) → per jenis → bawaan (null = susunan baku / cover default).
 */
final class PilihanTemplate
{
    /** Jenis master yang memuat KAK/TOR (RAB tidak dipilih per jenis kegiatan). */
    public const JENIS_KAK = ['KAK+TOR', 'KAK', 'TOR'];

    public const MAKS_LEADING = 50;

    /** @return list<array{id: string, nama: string, jenis: string, label: string, default: bool, variabel: int}> */
    public static function pilihanKak(int $tahun): array
    {
        $daftar = array_filter(app(TemplateDokumenRepository::class)->semua($tahun), fn (array $t) => in_array($t['jenis'], self::JENIS_KAK, true));

        return array_values(array_map(fn (array $t) => [
            'id' => (string) $t['id'], 'nama' => (string) $t['nama'], 'jenis' => (string) $t['jenis'],
            'label' => $t['nama'].' · '.$t['jenis'].($t['is_default'] ? (filled($t['leading'] ?? null) ? " (default {$t['leading']})" : ' (default)') : ''), 'default' => (bool) $t['is_default'], 'variabel' => count($t['variabel'] ?? []),
        ], $daftar));
    }

    /** @return list<array{id: string, nama: string, label: string, default: bool, khusus: string|null}> */
    public static function pilihanCover(int $tahun): array
    {
        return array_values(array_map(function (array $c) {
            $khusus = trim(implode(' · ', array_filter([$c['leading'] ? 'leading '.$c['leading'] : null, $c['sumber_dana'] ?? null])));

            return [
                'id' => (string) $c['id'], 'nama' => (string) $c['nama'],
                'label' => $c['nama'].($c['is_default'] ? ' (default)' : '').($khusus !== '' ? " — {$khusus}" : ''),
                'default' => (bool) $c['is_default'], 'khusus' => $khusus !== '' ? $khusus : null,
            ];
        }, app(TemplateCoverRepository::class)->semua($tahun)));
    }

    /** @return array{jenis: list<array<string, mixed>>, leading: list<array<string, mixed>>, diperbarui_pada: string|null} */
    public static function data(int $tahun): array
    {
        $tersimpan = app(PilihanTemplateRepository::class)->untuk($tahun);
        $kak = array_column(self::pilihanKak($tahun), 'nama', 'id');
        $cover = array_column(self::pilihanCover($tahun), 'nama', 'id');
        $urai = fn (array $p) => [
            'kak' => $p['kak'] ?? null, 'cover' => $p['cover'] ?? null,
            'nama_kak' => isset($p['kak']) ? ($kak[$p['kak']] ?? null) : null, 'nama_cover' => isset($p['cover']) ? ($cover[$p['cover']] ?? null) : null,
            'kak_hilang' => isset($p['kak']) && ! isset($kak[$p['kak']]), 'cover_hilang' => isset($p['cover']) && ! isset($cover[$p['cover']]),
        ];
        $jenis = KonteksAi::jenisKegiatan();

        return [
            'jenis' => array_map(fn (string $kunci, string $label) => ['kunci' => $kunci, 'label' => $label] + $urai($tersimpan['jenis'][$kunci] ?? []), array_keys($jenis), $jenis),
            'leading' => array_map(fn (array $p) => [
                'leading' => $p['leading'], 'jenis' => $p['jenis'],
                'nama_leading' => MasterKode::leading($p['leading'])['nama_unit'] ?? '', 'label_jenis' => $jenis[$p['jenis']] ?? $p['jenis'],
            ] + $urai($p), $tersimpan['leading']),
            'diperbarui_pada' => $tersimpan['diperbarui_pada'],
        ];
    }

    /**
     * @param  array<string, mixed>  $isian  {jenis: {kunci: {kak, cover}}, leading: list {leading, jenis, kak, cover}}
     * @return array{jenis: array<string, array{kak: string|null, cover: string|null}>, leading: list<array{leading: string, jenis: string, kak: string|null, cover: string|null}>}
     */
    public static function rapikan(array $isian): array
    {
        $teks = fn (mixed $v) => is_string($v) && trim($v) !== '' ? trim($v) : null;
        $jenis = [];
        foreach (array_keys(KonteksAi::jenisKegiatan()) as $kunci) {
            $p = $isian['jenis'][$kunci] ?? [];
            $jenis[$kunci] = ['kak' => $teks($p['kak'] ?? null), 'cover' => $teks($p['cover'] ?? null)];
        }
        $leading = [];
        foreach (array_values(is_array($isian['leading'] ?? null) ? $isian['leading'] : []) as $p) {
            $baris = ['leading' => (string) $teks($p['leading'] ?? null), 'jenis' => (string) $teks($p['jenis'] ?? null), 'kak' => $teks($p['kak'] ?? null), 'cover' => $teks($p['cover'] ?? null)];
            if (array_filter($baris) !== []) {
                $leading[] = $baris;
            }
        }

        return ['jenis' => $jenis, 'leading' => $leading];
    }

    /**
     * @param  array{jenis: array<string, array{kak: string|null, cover: string|null}>, leading: list<array<string, mixed>>}  $pilihan  Sudah dirapikan.
     * @return array<string, string>
     */
    public static function galat(array $pilihan, int $tahun): array
    {
        $kak = array_column(self::pilihanKak($tahun), 'id');
        $cover = array_column(self::pilihanCover($tahun), 'id');
        $jenisKegiatan = KonteksAi::jenisKegiatan();
        $aktif = array_column(MasterKode::leadings(), 'kode');
        $galat = [];
        $periksaTemplate = function (string $awalan, array $p) use (&$galat, $kak, $cover) {
            if ($p['kak'] !== null && ! in_array($p['kak'], $kak, true)) {
                $galat["{$awalan}.kak"] = 'Master KAK/TOR ini tidak ada di tahun anggaran ini — pilih yang lain.';
            }
            if ($p['cover'] !== null && ! in_array($p['cover'], $cover, true)) {
                $galat["{$awalan}.cover"] = 'Cover ini tidak ada di tahun anggaran ini — pilih yang lain.';
            }
        };
        foreach ($pilihan['jenis'] as $kunci => $p) {
            $periksaTemplate("jenis.{$kunci}", $p);
        }
        if (count($pilihan['leading']) > self::MAKS_LEADING) {
            $galat['leading'] = 'Paling banyak '.self::MAKS_LEADING.' template khusus leading.';
        }
        $pasangan = [];
        foreach ($pilihan['leading'] as $i => $p) {
            if (! in_array($p['leading'], $aktif, true)) {
                $galat["leading.{$i}.leading"] = $p['leading'] === '' ? 'Pilih leading.' : "Leading {$p['leading']} tidak aktif.";
            }
            if (! isset($jenisKegiatan[$p['jenis']])) {
                $galat["leading.{$i}.jenis"] = 'Pilih jenis kegiatan.';
            }
            if ($p['kak'] === null && $p['cover'] === null) {
                $galat["leading.{$i}.kak"] = 'Pilih master KAK/TOR atau cover untuk leading ini.';
            }
            $periksaTemplate("leading.{$i}", $p);
            $kunciPasangan = $p['leading'].'|'.$p['jenis'];
            if (isset($pasangan[$kunciPasangan]) && $p['leading'] !== '' && $p['jenis'] !== '') {
                $galat["leading.{$i}.jenis"] = 'Leading & jenis kegiatan ini sudah diatur di baris '.($pasangan[$kunciPasangan] + 1).'.';
            }
            $pasangan[$kunciPasangan] ??= $i;
        }

        return $galat;
    }

    /**
     * @param  array<string, mixed>  $isian
     * @param  array{id?: int|null, name?: string, role: string}  $pengguna
     * @return array{pilihan: array<string, mixed>, ringkasan: string|null}
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public static function simpan(int $tahun, array $isian, array $pengguna): array
    {
        if ($pengguna['role'] !== 'admin') {
            throw new AuthorizationException('Hanya admin yang bisa mengubah pilihan template.');
        }
        if (TahunDipilih::terkunci($tahun)) {
            throw new AuthorizationException("Tahun Anggaran {$tahun} dikunci — pilihan template hanya bisa dilihat.");
        }
        $pilihan = self::rapikan($isian);
        if (($galat = self::galat($pilihan, $tahun)) !== []) {
            throw ValidationException::withMessages($galat);
        }
        $lama = app(PilihanTemplateRepository::class)->untuk($tahun);
        $ringkasan = self::ringkasan(self::rapikan($lama), $pilihan, $tahun);
        if ($ringkasan !== null) {
            app(PilihanTemplateRepository::class)->simpan($tahun, $pilihan);
            RiwayatPengaturan::catat('ubah_pilihan_template', "TA {$tahun} — {$ringkasan}");
        }

        return ['pilihan' => $pilihan, 'ringkasan' => $ringkasan];
    }

    /**
     * Ringkasan perubahan untuk riwayat ("Workshop: KAK/TOR susunan baku → Master X; khusus leading 1.6 · Supervisi ditambah");
     * null = tidak berubah.
     *
     * @param  array<string, mixed>  $lama
     * @param  array<string, mixed>  $baru
     */
    public static function ringkasan(array $lama, array $baru, int $tahun): ?string
    {
        $nama = array_column(self::pilihanKak($tahun), 'nama', 'id') + array_column(self::pilihanCover($tahun), 'nama', 'id');
        $teks = fn (?string $id, string $bawaan) => $id === null ? $bawaan : ($nama[$id] ?? $id);
        $jenis = KonteksAi::jenisKegiatan();
        $bagian = [];
        foreach ($baru['jenis'] as $kunci => $p) {
            $l = $lama['jenis'][$kunci] ?? ['kak' => null, 'cover' => null];
            if ($l['kak'] !== $p['kak']) {
                $bagian[] = ($jenis[$kunci] ?? $kunci).': KAK/TOR '.$teks($l['kak'], 'susunan baku').' → '.$teks($p['kak'], 'susunan baku');
            }
            if ($l['cover'] !== $p['cover']) {
                $bagian[] = ($jenis[$kunci] ?? $kunci).': cover '.$teks($l['cover'], 'default').' → '.$teks($p['cover'], 'default');
            }
        }
        $kunciLeading = fn (array $daftar) => array_column(array_map(fn (array $p) => ['k' => $p['leading'].'|'.$p['jenis'], 'p' => $p], $daftar), 'p', 'k');
        [$lamaL, $baruL] = [$kunciLeading($lama['leading']), $kunciLeading($baru['leading'])];
        foreach ($baruL as $k => $p) {
            $label = 'khusus leading '.$p['leading'].' · '.($jenis[$p['jenis']] ?? $p['jenis']);
            if (! isset($lamaL[$k])) {
                $bagian[] = $label.' ditambah';
            } elseif ($lamaL[$k]['kak'] !== $p['kak'] || $lamaL[$k]['cover'] !== $p['cover']) {
                $bagian[] = $label.' diubah';
            }
        }
        foreach (array_diff_key($lamaL, $baruL) as $p) {
            $bagian[] = 'khusus leading '.$p['leading'].' · '.($jenis[$p['jenis']] ?? $p['jenis']).' dihapus';
        }

        return $bagian === [] ? null : implode('; ', $bagian);
    }

    /**
     * Master KAK/TOR terpilih untuk kegiatan itu yang berkasnya ada (dipakai unduhan Word lewat PengisiTemplateDocx); null =
     * susunan baku.
     *
     * @param  array<string, mixed>  $kegiatan
     * @return array{id: string, nama: string, jenis: string, path: string}|null
     */
    public static function masterKak(array $kegiatan, ?int $tahun = null): ?array
    {
        $id = self::untukKegiatan($kegiatan, $tahun)['kak'];
        $master = $id !== null ? app(TemplateDokumenRepository::class)->temukan($id) : null;
        if ($master === null || ! Storage::disk('local')->exists($master['file_path'])) {
            return null;
        }

        return ['id' => (string) $master['id'], 'nama' => (string) $master['nama'], 'jenis' => (string) $master['jenis'], 'path' => Storage::disk('local')->path($master['file_path'])];
    }

    /**
     * Template yang berlaku untuk satu kegiatan (khusus leading → per jenis → bawaan); `$pilihan` = pilihan yang belum
     * disimpan (pratinjau Format Seragam), null = tersimpan.
     *
     * @param  array<string, mixed>  $kegiatan
     * @param  array<string, mixed>|null  $pilihan
     * @return array{jenis: string, asal: string, kak: string|null, cover: string|null}
     */
    public static function untukKegiatan(array $kegiatan, ?int $tahun = null, ?array $pilihan = null): array
    {
        return self::berlakuUntuk(KonteksAi::tebakJenis((string) ($kegiatan['nama_kegiatan'] ?? '')), $kegiatan['leading'] ?? null, $tahun, $pilihan);
    }

    /**
     * Template yang berlaku untuk jenis kegiatan $jenis milik leading $leading (aturan sama dengan untukKegiatan).
     *
     * @param  array<string, mixed>|null  $pilihan
     * @return array{jenis: string, asal: string, kak: string|null, cover: string|null}
     */
    public static function berlakuUntuk(string $jenis, ?string $leading, ?int $tahun = null, ?array $pilihan = null): array
    {
        $tahun ??= (int) StubData::tahunAktif()['tahun'];
        $pilihan = self::rapikan($pilihan ?? app(PilihanTemplateRepository::class)->untuk($tahun));
        $khusus = collect($pilihan['leading'])->first(fn (array $p) => $p['leading'] === $leading && $p['jenis'] === $jenis);
        $perJenis = $pilihan['jenis'][$jenis] ?? ['kak' => null, 'cover' => null];

        return match (true) {
            $khusus !== null => ['jenis' => $jenis, 'asal' => 'leading', 'kak' => $khusus['kak'] ?? $perJenis['kak'], 'cover' => $khusus['cover'] ?? $perJenis['cover']],
            $perJenis['kak'] !== null || $perJenis['cover'] !== null => ['jenis' => $jenis, 'asal' => 'jenis'] + $perJenis,
            default => ['jenis' => $jenis, 'asal' => 'bawaan', 'kak' => null, 'cover' => null],
        };
    }
}
