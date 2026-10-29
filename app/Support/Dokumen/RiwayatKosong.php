<?php

namespace App\Support\Dokumen;

use App\Support\Kegiatan\KunciKegiatan;
use App\Support\MasterKode;
use App\Support\Stub\StubData;

/**
 * Isi tampilan Riwayat Dokumen selama akun belum punya satu pun dokumen tercatat: cara dokumen masuk riwayat (`LANGKAH`)
 * dan langkah berikutnya yang nyata bagi akun itu — dokumen tahun aktif yang sudah bisa dicetak (DaftarCetak, siap dulu
 * lalu DRAF, maks. `MAKS_CONTOH`) dengan tautan cetak & pratinjau, atau, bila belum ada kegiatan tahun aktif, tautan membuat
 * kegiatan (hanya bila akun boleh membuatnya: admin atau akun dengan leading aktif, tahun aktif tidak terkunci).
 */
final class RiwayatKosong
{
    public const MAKS_CONTOH = 3;

    /** Cara dokumen tercatat di riwayat → [ikon, judul, keterangan]. */
    public const LANGKAH = [
        'cetak' => ['printer', 'Cetak', 'Dokumen yang dicetak atau disimpan sebagai PDF dari halaman cetak.'],
        'unduh' => ['download', 'Unduh', 'Berkas PDF, Word (KAK/TOR), atau Excel (RAB) yang diunduh dari Cetak & Unduh atau Pratinjau.'],
        'arsip' => ['check-badge', 'Arsip final', 'PDF bertanda tangan yang diunggah setelah kegiatan disetujui.'],
    ];

    /**
     * @param  array{id?: int|null, role: string, leadings?: list<string>}  $pengguna
     * @return array{tahun: int, dokumen: array{jumlah: int, siap: int, draf: int, kegiatan: int}, contoh: list<array<string, mixed>>, buat: string|null, terkunci: bool}
     */
    public static function untuk(array $pengguna): array
    {
        $tahun = (int) StubData::tahunAktif()['tahun'];
        $baris = DaftarCetak::baris($pengguna, $tahun);
        usort($baris, fn (array $a, array $b) => ($a['keadaan'] === 'siap' ? 0 : 1) <=> ($b['keadaan'] === 'siap' ? 0 : 1));
        $terkunci = KunciKegiatan::tahunTerkunci([]) !== null;
        $bolehBuat = ! $terkunci && ($pengguna['role'] === 'admin' || MasterKode::pilihanLeading($pengguna, null) !== []);

        return [
            'tahun' => $tahun,
            'dokumen' => DaftarCetak::ringkasan($baris),
            'contoh' => array_map(fn (array $b) => [
                'kunci' => $b['kunci'],
                'nama' => $b['nama'],
                'sumber' => $b['sumber'],
                'keadaan' => $b['keadaan'],
                'label_keadaan' => DaftarCetak::KEADAAN[$b['keadaan']],
                'alasan' => $b['alasan'][0] ?? null,
                'tautan_cetak' => CetakDokumen::tautan($b['kegiatan_id'], $b['sumber'], $b['lembar']),
                'tautan_pratinjau' => route('kegiatan.pratinjau', array_filter(['kegiatan' => $b['kegiatan_id'], 'sumber' => $b['sumber']])),
            ], array_slice($baris, 0, self::MAKS_CONTOH)),
            'buat' => $baris === [] && $bolehBuat ? route('kegiatan.create') : null,
            'terkunci' => $terkunci,
        ];
    }
}
