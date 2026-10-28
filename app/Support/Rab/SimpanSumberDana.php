<?php

namespace App\Support\Rab;

use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Enums\SumberDana;
use App\Support\Format;
use App\Support\Kegiatan\ValidasiKegiatan;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Simpan pilihan sumber dana & pagu satu kegiatan di server (PRD F5 "Sumber Dana" & F12): hanya `sumber_dana` yang
 * ditulis — isian lain tidak disentuh. Isian diterima sebagai daftar {sumber, pagu} atau peta sumber → pagu
 * (`rapikan()`: urut baku SumberDana, pagu rupiah berformat → angka, kosong = pagu belum diisi), diperiksa
 * ValidasiKegiatan (sumber DIPA/Komite tanpa ganda, pagu 1..MAKS_PAGU), dan ditolak bila kegiatan terkunci. RAB sumber
 * yang tidak dipilih lagi tetap tersimpan (tidak dicetak) dan dilaporkan di `terlepas`; `pagu` = StatusPagu tiap sumber.
 */
final class SimpanSumberDana
{
    /**
     * @param  array<int|string, mixed>  $isian  list<{sumber, pagu}> atau ['DIPA' => pagu|null, ...].
     * @return list<array{sumber: string, pagu: int|null}>
     */
    public static function rapikan(array $isian): array
    {
        $peta = [];
        foreach ($isian as $kunci => $nilai) {
            [$sumber, $pagu] = is_array($nilai) ? [(string) ($nilai['sumber'] ?? ''), $nilai['pagu'] ?? null] : [(string) $kunci, $nilai];
            if (! array_key_exists($sumber, $peta)) {
                $peta[$sumber] = blank($pagu) ? null : Format::parseRupiah($pagu);
            }
        }

        // Urut baku; sumber di luar SumberDana tetap ikut (di akhir) agar ditolak validasi, bukan diam-diam dibuang.
        $urut = array_flip(SumberDana::urutan());
        uksort($peta, fn ($a, $b) => [$urut[$a] ?? PHP_INT_MAX, (string) $a] <=> [$urut[$b] ?? PHP_INT_MAX, (string) $b]);

        return array_map(fn ($sumber, ?int $pagu) => ['sumber' => (string) $sumber, 'pagu' => $pagu], array_keys($peta), $peta);
    }

    /**
     * @param  array<int|string, mixed>  $isian
     * @return array{sumber_dana: list<array{sumber: string, pagu: int|null}>, pagu: array<string, StatusPagu>, terlepas: array<string, string>}
     *
     * @throws ValidationException Berkunci `sumber_dana.*`.
     * @throws AuthorizationException Kegiatan terkunci.
     * @throws RuntimeException Kegiatan tidak ditemukan.
     */
    public static function simpan(int $kegiatanId, array $isian): array
    {
        $repo = app(KegiatanRepository::class);
        $tersimpan = $repo->temukan($kegiatanId) ?? throw new RuntimeException("Kegiatan {$kegiatanId} tidak ditemukan.");
        $status = StatusKegiatan::tryFrom((string) ($tersimpan['status'] ?? '')) ?? StatusKegiatan::Draf;
        if (! $status->dapatDiedit()) {
            throw new AuthorizationException('Kegiatan berstatus '.$status->label().' sehingga isiannya terkunci.');
        }

        $sumberDana = self::rapikan($isian);
        ValidasiKegiatan::periksa(['sumber_dana' => $sumberDana] + $tersimpan, ['sumber_dana']);
        $repo->simpan(array_merge($tersimpan, ['sumber_dana' => $sumberDana]), $kegiatanId);

        $kegiatan = $repo->temukan($kegiatanId) ?? $tersimpan;
        $pagu = [];
        $terlepas = [];
        foreach (AnggaranKegiatan::untuk($kegiatan, sertakanTerlepas: true) as $a) {
            if ($a->dipilih) {
                $pagu[$a->sumber] = $a->keteranganPagu();
            } elseif ($a->jumlahItem() > 0) {
                $terlepas[$a->sumber] = "RAB {$a->sumber} (".Format::rupiah($a->total()).") tetap tersimpan tetapi tidak dicetak selama {$a->sumber} tidak dipilih.";
            }
        }

        return ['sumber_dana' => $sumberDana, 'pagu' => $pagu, 'terlepas' => $terlepas];
    }
}
