<?php

namespace App\Support\Rab;

use App\Livewire\Forms\ItemBelanjaForm;
use App\Support\Format;
use Closure;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Validasi sisi server satu rincian belanja RAB — satu sumber aturan, pesan, dan label untuk form item (ItemBelanjaForm)
 * dan RincianRab. Aturan berlaku untuk ketikan mentah ("1,5", "Rp40.000") maupun nilai yang sudah dirapikan.
 */
final class ValidasiItemBelanja
{
    /** @return array<string, mixed> */
    public static function aturan(): array
    {
        return [
            'uraian' => ['required', 'string', 'min:3', 'max:255'],
            'kategori' => ['required', Rule::in(ItemBelanjaForm::KATEGORI)],
            'kode' => ['nullable', 'string', 'max:20', 'regex:/^[0-9A-Za-z.\-]+$/'],
            'volume' => ['required', function (string $attribute, mixed $value, Closure $fail) {
                $angka = ItemBelanjaForm::angkaVolume((string) $value);

                if ($angka === null) {
                    $fail('Volume harus berupa angka, mis. 6 atau 1,5.');
                } elseif ($angka <= 0) {
                    $fail('Volume harus lebih dari 0.');
                } elseif ($angka > ItemBelanjaForm::MAKS_VOLUME) {
                    $fail('Volume terlalu besar (maksimal 100.000).');
                }
            }],
            'satuan' => ['required', 'string', 'max:30'],
            'harga_satuan' => ['required', function (string $attribute, mixed $value, Closure $fail) {
                $harga = Format::parseRupiah($value);

                if ($harga === null || $harga < 1) {
                    $fail('Isi harga satuan dengan angka rupiah lebih dari 0.');
                } elseif ($harga > ItemBelanjaForm::MAKS_HARGA) {
                    $fail('Harga satuan terlalu besar. Periksa jumlah digit.');
                }
            }],
        ];
    }

    /** @return array<string, string> */
    public static function pesan(): array
    {
        return [
            'required' => 'Kolom :attribute wajib diisi.',
            'min' => 'Kolom :attribute minimal :min karakter.',
            'max' => 'Kolom :attribute maksimal :max karakter.',
            'in' => 'Pilih kelompok belanja: bahan, jasa, atau lain-lain.',
            'kode.regex' => 'Kode akun hanya boleh berisi angka, huruf, titik, atau tanda hubung.',
        ];
    }

    /** @return array<string, string> */
    public static function atribut(): array
    {
        return ['uraian' => 'uraian', 'kategori' => 'kelompok belanja', 'kode' => 'kode akun', 'satuan' => 'satuan', 'harga_satuan' => 'harga satuan'];
    }

    /**
     * @param  array<string, mixed>  $isian
     *
     * @throws ValidationException Berkunci isian (mis. `volume`).
     */
    public static function periksa(array $isian): void
    {
        Validator::make($isian, self::aturan(), self::pesan(), self::atribut())->validate();
    }
}
