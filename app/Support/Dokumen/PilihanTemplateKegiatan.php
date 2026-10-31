<?php

namespace App\Support\Dokumen;

use App\Support\Kegiatan\TahunKegiatan;
use App\Support\Kegiatan\UbahDataKegiatan;
use App\Support\Pengaturan\PengaturanBerubah;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Pilihan template & format dokumen satu kegiatan (PRD §9 "Pilihan Template per Kegiatan"; endpoint
 * `kegiatan.template.pilihan`, padanan dropdown tab Susun KAK/TOR):
 * - `data($kegiatan)` = template & format yang berlaku (TemplateDipakai), pilihan template (PilihanTemplate::pilihanKegiatan:
 *   milik leading kegiatan + umum, yang nonaktif hanya pilihan tersimpan), pilihan tersimpan `template_kak`/`format_dokumen`,
 *   yang berlaku bila otomatis, pilihan format (FormatKakTor::pilihan), `dapat_diubah`/`alasan_terkunci`, `versi`;
 * - `simpan($id, {template?, format?}, $pengguna, $versi?)` — kunci yang dikirim saja (null/'' = otomatis/ikut tahun) lewat
 *   UbahDataKegiatan (aturan ValidasiKegiatan, kunci status/tahun, bentrok versi) → {pesan, diubah, data}.
 */
final class PilihanTemplateKegiatan
{
    /**
     * @param  array<string, mixed>  $kegiatan
     * @return array<string, mixed>
     */
    public static function data(array $kegiatan): array
    {
        $tahun = TahunKegiatan::dari($kegiatan);
        $otomatis = DaftarTemplate::untukKegiatan(['template_kak' => null] + $kegiatan);

        return [
            'kegiatan_id' => (int) $kegiatan['id'],
            'tahun' => $tahun,
            'berlaku' => TemplateDipakai::untuk($kegiatan),
            'template' => [
                'dipilih' => $kegiatan['template_kak'] ?? null,
                'otomatis' => ['id' => $otomatis['kak']['id'] ?? null, 'nama' => $otomatis['kak']['nama'] ?? null, 'asal' => $otomatis['asal'], 'label_asal' => $otomatis['label_asal']],
                'pilihan' => PilihanTemplate::pilihanKegiatan($kegiatan, $tahun),
            ],
            'format' => [
                'dipilih' => $kegiatan['format_dokumen'] ?? null,
                'tahun' => FormatKakTor::tahun($tahun),
                'pilihan' => FormatKakTor::pilihan($tahun),
            ],
            'dapat_diubah' => UbahDataKegiatan::alasanTerkunci($kegiatan) === null,
            'alasan_terkunci' => UbahDataKegiatan::alasanTerkunci($kegiatan),
            'versi' => UbahDataKegiatan::versi($kegiatan),
        ];
    }

    /**
     * @param  array{template?: mixed, format?: mixed}  $isian
     * @param  array<string, mixed>  $pengguna
     * @return array{pesan: string, diubah: list<string>, data: array<string, mixed>}
     *
     * @throws ValidationException Berkunci `template`/`format`.
     * @throws AuthorizationException Bukan cakupan akun / kegiatan terkunci.
     * @throws PengaturanBerubah Kegiatan berubah sejak $versi.
     * @throws RuntimeException Kegiatan tidak ada.
     */
    public static function simpan(int $id, array $isian, array $pengguna, ?string $versi = null): array
    {
        $kiriman = [];
        foreach (['template' => 'template_kak', 'format' => 'format_dokumen'] as $kunci => $kolom) {
            if (array_key_exists($kunci, $isian)) {
                $kiriman[$kolom] = blank($isian[$kunci]) ? null : $isian[$kunci];
            }
        }
        if ($kiriman === []) {
            throw ValidationException::withMessages(['template' => 'Kirim pilihan template dan/atau format.']);
        }

        try {
            $hasil = UbahDataKegiatan::ubah($id, $kiriman, $pengguna, $versi);
        } catch (ValidationException $e) {
            // Galat dikembalikan dengan kunci API (template/format), bukan kolom data kegiatan.
            throw ValidationException::withMessages(array_combine(
                array_map(fn (string $k) => ['template_kak' => 'template', 'format_dokumen' => 'format'][$k] ?? $k, array_keys($e->errors())),
                array_map(fn (array $p) => $p[0], $e->errors()),
            ));
        }
        $data = self::data($hasil['kegiatan']);

        return [
            'pesan' => $hasil['diubah'] === [] ? 'Pilihan template tidak berubah.' : 'Pilihan template tersimpan — '.($data['berlaku']['kak']['nama'] ?? 'susunan baku').', '.mb_strtolower($data['berlaku']['format']['label']).'.',
            'diubah' => $hasil['diubah'],
            'data' => $data,
        ];
    }
}
