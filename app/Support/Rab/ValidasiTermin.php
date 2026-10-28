<?php

namespace App\Support\Rab;

use App\Support\Format;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Aturan baris termin pembayaran (PRD F11) — satu sumber aturan & pesan untuk halaman termin (TerminPembayaran) dan
 * SimpanTermin: paling banyak MekanismePembayaran::MAKS_TERMIN, tanggal wajib (Y-m-d) di tahun anggaran dan tidak
 * mundur dari termin sebelumnya, nominal lebih dari Rp0. Kecocokan jumlah dengan total RAB: SimpanTermin::pesanTidakPas.
 */
final class ValidasiTermin
{
    /**
     * @param  list<array{tanggal?: string|null, nominal?: mixed}>  $baris  Baris yang divalidasi (urutan termin).
     * @return array<string, mixed> Berkunci `baris.*` seperti properti halaman termin.
     */
    public static function aturan(array $baris, int $tahun): array
    {
        return [
            'baris' => ['array', 'max:'.MekanismePembayaran::MAKS_TERMIN],
            'baris.*.tanggal' => ['required', 'date_format:Y-m-d', function (string $atribut, mixed $nilai, Closure $gagal) use ($baris, $tahun) {
                $index = (int) explode('.', $atribut)[1];
                $tanggal = rescue(fn () => CarbonImmutable::createFromFormat('Y-m-d', (string) $nilai), null, false);

                if ($tanggal === null) {
                    return;
                }

                if ($tanggal->year !== $tahun) {
                    $gagal("Tanggal termin harus di Tahun Anggaran {$tahun}.");
                } elseif ($index > 0 && filled($sebelum = $baris[$index - 1]['tanggal'] ?? null) && $nilai < $sebelum) {
                    $gagal('Tanggal termin '.($index + 1).' tidak boleh sebelum termin '.$index.'.');
                }
            }],
            'baris.*.nominal' => ['required', function (string $atribut, mixed $nilai, Closure $gagal) {
                if ((int) Format::parseRupiah((string) $nilai) < 1) {
                    $gagal('Nominal termin harus lebih dari Rp0.');
                }
            }],
        ];
    }

    /** @return array<string, string> */
    public static function pesan(): array
    {
        return [
            'baris.max' => 'Termin paling banyak :max.',
            'baris.*.tanggal.required' => 'Tanggal termin wajib diisi.',
            'baris.*.tanggal.date_format' => 'Tanggal termin tidak valid.',
            'baris.*.nominal.required' => 'Nominal termin wajib diisi.',
        ];
    }

    /**
     * @param  list<array{tanggal?: string|null, nominal?: mixed}>  $baris
     *
     * @throws ValidationException Berkunci `baris.{i}.tanggal` / `baris.{i}.nominal` / `baris`.
     */
    public static function periksa(array $baris, int $tahun): void
    {
        Validator::make(['baris' => $baris], self::aturan($baris, $tahun), self::pesan())->validate();
    }
}
