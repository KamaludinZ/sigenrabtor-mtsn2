<?php

namespace App\Support\Dokumen;

use RuntimeException;

/**
 * Validasi penanda `${…}` master .docx sebelum disimpan (PRD Fase 5: "Variabel harus terdaftar agar salah tulis
 * terdeteksi; admin dapat menguji hasil penggantian sebelum template dipakai") — dipakai UnggahMasterTemplate::simpan,
 * formulir unggah Template Master, dan endpoint periksa:
 * - `galat` (menahan simpan): berkas tanpa penanda (`berkas`), penanda tanpa kurung tutup (`penanda`,
 *   PembacaTemplateDocx::baca `tidak_tertutup`), dan tiap penanda tidak terdaftar `variabel.{nama}` beserta saran ejaan
 *   (VariabelTemplate::saran);
 * - `peringatan` (tidak menahan): penanda berjenis `BERDIRI_SENDIRI` (daftar/tabel/blok/gambar — diisi beberapa baris) yang
 *   berbagi paragraf dengan teks lain (hasilnya digabung satu baris), template RAB tanpa `${rab}`, dan penanda dianjurkan
 *   yang belum dipakai (VariabelTemplate::DIANJURKAN, template berisi KAK/TOR).
 */
final class ValidasiVariabelTemplate
{
    /** Jenis variabel yang diisi lebih dari satu baris/objek sehingga harus sendirian di paragrafnya. */
    public const BERDIRI_SENDIRI = ['daftar', 'tabel', 'blok', 'gambar'];

    /**
     * @return array{analisis: AnalisisTemplate, galat: array<string, string>, peringatan: list<array{kunci: string, pesan: string}>, bisa_disimpan: bool}
     *
     * @throws RuntimeException Berkas bukan .docx yang sah.
     */
    public static function periksa(string $path, string $jenis): array
    {
        $baca = PembacaTemplateDocx::baca($path);
        $analisis = AnalisisTemplate::dari(array_map(fn (array $v) => $v['jumlah'], $baca['variabel']));
        $galat = [];
        if ($baca['tidak_tertutup'] !== []) {
            $galat['penanda'] = 'Penanda tanpa kurung tutup: '.implode(', ', array_map(fn (string $p) => '“'.$p.'”', $baca['tidak_tertutup'])).' — lengkapi menjadi ${nama_variabel}.';
        }
        if ($analisis->variabel === []) {
            $galat['berkas'] = 'Belum ada variabel ${…} di dokumen — tandai bagian yang diisi otomatis, mis. ${nama_kegiatan}.';
        }
        foreach ($analisis->tidakTerdaftar() as $v) {
            $galat['variabel.'.$v['nama']] = '${'.$v['nama'].'} tidak terdaftar'.($v['saran'] ? ' — maksud Anda ${'.$v['saran'].'}?' : ' — pilih dari daftar variabel terdaftar.');
        }
        if ($analisis->tidakTerdaftar() !== [] || isset($galat['penanda'])) {
            $galat = ['berkas' => $analisis->tidakTerdaftar() !== [] ? 'Perbaiki variabel yang tidak terdaftar di Word, lalu unggah ulang.' : 'Perbaiki penanda yang tidak tertutup di Word, lalu unggah ulang.'] + $galat;
        }

        $peringatan = [];
        foreach ($baca['paragraf'] as $p) {
            preg_match_all('/\$\{([^{}]*)\}/u', $p['teks'], $cocok);
            foreach (array_unique(array_map(fn (string $n) => trim((string) preg_replace('/\s+/u', ' ', $n)), $cocok[1])) as $nama) {
                $jenisVariabel = VariabelTemplate::DAFTAR[$nama][2] ?? null;
                if (in_array($jenisVariabel, self::BERDIRI_SENDIRI, true) && trim($p['teks']) !== '${'.$nama.'}' && ! isset($peringatan['sendiri.'.$nama])) {
                    $peringatan['sendiri.'.$nama] = ['kunci' => 'sendiri.'.$nama, 'pesan' => '${'.$nama.'} ('.$jenisVariabel.') sebaiknya berdiri sendiri di paragrafnya — kini bersama teks “'.mb_strimwidth(trim($p['teks']), 0, 60, '…').'”, sehingga isinya digabung satu baris.'];
                }
            }
        }
        $nama = array_column($analisis->variabel, 'nama');
        if ($jenis === 'RAB' && ! in_array('rab', $nama, true)) {
            $peringatan['rab'] = ['kunci' => 'rab', 'pesan' => 'Template RAB belum memuat ${rab} — tabel rincian tidak akan terisi otomatis.'];
        }
        if ($jenis !== 'RAB' && $analisis->variabel !== [] && ($kurang = $analisis->dianjurkanBelumAda()) !== []) {
            $peringatan['dianjurkan'] = ['kunci' => 'dianjurkan', 'pesan' => 'Dianjurkan memakai '.implode(', ', array_map(fn (string $n) => '${'.$n.'}', $kurang)).' agar identitas & angka tidak diketik manual.'];
        }

        return ['analisis' => $analisis, 'galat' => $galat, 'peringatan' => array_values($peringatan), 'bisa_disimpan' => $galat === []];
    }
}
