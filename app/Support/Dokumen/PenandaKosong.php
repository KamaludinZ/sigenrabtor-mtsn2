<?php

namespace App\Support\Dokumen;

/**
 * Penanda isian kosong di pratinjau dokumen (PRD F5 "Periksa Kelengkapan — bagian kosong dan data wajib yang belum
 * terisi ditandai"): satu daftar untuk KAK/TOR (blok `kosong` SusunanKak beserta `asal` tempat mengisi & `wajib`) dan RAB
 * (header yang belum diisi, sumber dana, rincian, termin) — urutannya sama dengan penanda `[data-kosong]` di lembar,
 * sehingga navigasi "kosong berikutnya" di klien dan ringkasan di server selalu sejalan. `TINGKAT`: wajib = menghalangi
 * pengajuan (nama kegiatan, tanggal mulai & lama, tempat, leading), dokumen = membuat cap DRAF. Tautan tempat
 * melengkapinya lewat SusunanOtomatis::tempat (diatur admin hanya bertautan bagi admin).
 */
final class PenandaKosong
{
    /** Tingkat → [label lencana, keterangan]. */
    public const TINGKAT = [
        'wajib' => ['Wajib sebelum diajukan', 'Harus diisi sebelum kegiatan diajukan untuk diperiksa.'],
        'dokumen' => ['Belum lengkap', 'Dokumen tetap bisa dicetak dengan cap DRAF selama masih kosong.'],
    ];

    /** @param  array<string, mixed>  $blok  Blok `kosong` SusunanKak. */
    public static function tingkat(array $blok): string
    {
        return ! empty($blok['wajib']) ? 'wajib' : 'dokumen';
    }

    /**
     * Tempat melengkapi satu penanda; null bila asalnya tidak diketahui.
     *
     * @param  array{role?: string}  $pengguna
     * @return array{kunci: string, label: string, tautan: string|null, admin: bool}|null
     */
    public static function tempat(?string $asal, ?int $kegiatanId, array $pengguna): ?array
    {
        return $asal !== null && isset(SusunanOtomatis::TEMPAT[$asal])
            ? SusunanOtomatis::tempat($asal, $kegiatanId, ($pengguna['role'] ?? null) === 'admin')
            : null;
    }

    /**
     * Semua penanda kosong satu set dokumen, urut lembar (KAK/TOR lalu RAB).
     *
     * @return list<array{dokumen: string, bagian: int|null, pesan: string, tingkat: string, asal: string|null, sunting: string|null}>
     */
    public static function daftar(?SusunanKak $kak, ?SusunanRab $rab): array
    {
        $daftar = [];
        foreach ($kak?->bagian ?? [] as $b) {
            foreach ($b['isi'] as $blok) {
                if (($blok['jenis'] ?? null) === 'kosong') {
                    $daftar[] = ['dokumen' => 'kak', 'bagian' => $b['nomor'], 'pesan' => $blok['pesan'], 'tingkat' => self::tingkat($blok), 'asal' => $blok['asal'] ?? null, 'sunting' => $blok['sunting'] ?? null];
                }
            }
        }
        if ($rab !== null) {
            $rabKosong = fn (string $pesan, ?string $asal) => ['dokumen' => 'rab', 'bagian' => null, 'pesan' => $pesan, 'tingkat' => 'dokumen', 'asal' => $asal, 'sunting' => null];
            foreach ($rab->kepala as $baris) {
                if ($baris['nilai'] === null) {
                    $daftar[] = $rabKosong("{$baris['label']} belum diisi.", null);
                }
            }
            if ($rab->anggaran === null) {
                $daftar[] = $rabKosong('Pilih sumber dana kegiatan agar RAB dapat disusun.', 'sumber_dana');
            } elseif ($rab->anggaran->jumlahItem() === 0) {
                $daftar[] = $rabKosong("Belum ada rincian belanja RAB {$rab->sumber}.", 'rab');
            }
            if ($rab->statusTermin) {
                $daftar[] = $rabKosong("{$rab->statusTermin}.", 'rab');
            }
        }

        return $daftar;
    }

    /**
     * @param  list<array{dokumen: string, tingkat: string}>  $daftar
     * @return array{jumlah: int, wajib: int, kak: int, rab: int}
     */
    public static function ringkasan(array $daftar): array
    {
        $hitung = fn (callable $syarat) => count(array_filter($daftar, $syarat));

        return [
            'jumlah' => count($daftar),
            'wajib' => $hitung(fn (array $p) => $p['tingkat'] === 'wajib'),
            'kak' => $hitung(fn (array $p) => $p['dokumen'] === 'kak'),
            'rab' => $hitung(fn (array $p) => $p['dokumen'] === 'rab'),
        ];
    }

    /**
     * Jumlah penanda kosong satu bagian KAK/TOR (lencana di judul bagian).
     *
     * @param  array{isi: list<array<string, mixed>>}  $bagian
     */
    public static function jumlahBagian(array $bagian): int
    {
        return count(array_filter($bagian['isi'], fn (array $b) => ($b['jenis'] ?? null) === 'kosong'));
    }
}
