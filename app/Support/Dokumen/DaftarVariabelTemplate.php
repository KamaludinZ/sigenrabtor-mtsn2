<?php

namespace App\Support\Dokumen;

use App\Contracts\TemplateDokumenRepository;
use Illuminate\Validation\ValidationException;

/**
 * Daftar variabel terdaftar untuk penyusun template master (PRD §7C "Pemetaan variabel template"; endpoint
 * `pengaturan.template.variabel`): tiap VariabelTemplate::DAFTAR per kelompok (`KELOMPOK`) — penanda `${…}`, keterangan,
 * jenis, `berdiri_sendiri` (ValidasiVariabelTemplate::BERDIRI_SENDIRI), `dianjurkan` (VariabelTemplate::DIANJURKAN), `asal`
 * isian (NilaiVariabel::ASAL), pemakaian di master tahun itu (`dipakai` jumlah template + nama), dan — bila ada kegiatan
 * contoh — `contoh` nilainya (NilaiVariabel::untuk: teks, daftar/blok = list baris, tabel = ringkasan; null = kosong).
 */
final class DaftarVariabelTemplate
{
    /**
     * @param  array<string, mixed>|null  $kegiatan  Kegiatan contoh untuk nilai variabel.
     * @return array{tahun: int, jumlah: int, contoh_kegiatan: array{id: int, nama: string, sumber: string|null}|null, kelompok: list<array{kunci: string, judul: string, variabel: list<array<string, mixed>>}>}
     *
     * @throws ValidationException Sumber dana tidak dipilih kegiatan contoh (`sumber`).
     */
    public static function untuk(int $tahun, ?array $kegiatan = null, ?string $sumber = null): array
    {
        $nilai = null;
        if ($kegiatan !== null) {
            $sumberKegiatan = array_column($kegiatan['sumber_dana'] ?? [], 'sumber');
            if ($sumber !== null && ! in_array($sumber, $sumberKegiatan, true)) {
                throw ValidationException::withMessages(['sumber' => "Sumber dana {$sumber} tidak dipilih untuk kegiatan contoh ini."]);
            }
            $sumber ??= $sumberKegiatan[0] ?? null;
            $nilai = NilaiVariabel::untuk($kegiatan, $sumber);
        }

        $pemakaian = [];
        foreach (app(TemplateDokumenRepository::class)->semua($tahun) as $t) {
            foreach ((array) ($t['variabel'] ?? []) as $v) {
                $pemakaian[$v][] = (string) $t['nama'];
            }
        }

        $kelompok = [];
        foreach (VariabelTemplate::KELOMPOK as $kunci => $judul) {
            $kelompok[$kunci] = ['kunci' => $kunci, 'judul' => $judul, 'variabel' => []];
        }
        foreach (VariabelTemplate::DAFTAR as $nama => [$keterangan, $kunciKelompok, $jenis]) {
            $kelompok[$kunciKelompok]['variabel'][] = [
                'nama' => $nama,
                'penanda' => '${'.$nama.'}',
                'keterangan' => $keterangan,
                'jenis' => $jenis,
                'berdiri_sendiri' => in_array($jenis, ValidasiVariabelTemplate::BERDIRI_SENDIRI, true),
                'dianjurkan' => in_array($nama, VariabelTemplate::DIANJURKAN, true),
                'asal' => NilaiVariabel::ASAL[$nama] ?? null,
                'dipakai' => ['jumlah' => count($pemakaian[$nama] ?? []), 'template' => $pemakaian[$nama] ?? []],
                'contoh' => $nilai === null ? null : ($nilai[$nama] ?? null),
            ];
        }

        return [
            'tahun' => $tahun,
            'jumlah' => count(VariabelTemplate::DAFTAR),
            'contoh_kegiatan' => $kegiatan === null ? null : ['id' => (int) $kegiatan['id'], 'nama' => (string) ($kegiatan['nama_kegiatan'] ?? ''), 'sumber' => $sumber],
            'kelompok' => array_values(array_filter($kelompok, fn (array $k) => $k['variabel'] !== [])),
        ];
    }
}
