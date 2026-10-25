<?php

namespace App\Support\Rab\Impor;

use App\Livewire\Forms\ItemBelanjaForm;
use App\Support\Format;
use App\Support\Rab\KalkulatorRab;

/**
 * Memeriksa baris tabel rincian impor dan menyesuaikannya ke format rincian RAB aplikasi secara otomatis:
 * - volume bertuliskan satuan ("6 OJ") dipisah menjadi volume + satuan;
 * - harga satuan kosong dihitung dari jumlah ÷ volume;
 * - jumlah berkas yang memuat pengali di uraian (mis. "2 OJ … (6 orang)" = Rp300.000) → volume disesuaikan agar
 *   volume × harga = jumlah berkas, sehingga total dokumen tidak berubah;
 * - kelompok belanja ditebak dari uraian, kode akun judul kelompok diturunkan ke item di bawahnya.
 * Baris judul kelompok dilewati; item yang sama persis dengan item RAB tersimpan dilewati agar impor ulang aman.
 */
final class PemeriksaImporRab
{
    /**
     * @param  list<array{baris: int, nilai: array<string, mixed>}>  $barisMentah
     * @param  list<array<string, mixed>>  $rincianAda  Item yang sudah ada di RAB sumber dana tujuan.
     * @return list<array{baris: int, status: string, pesan: list<string>, peringatan: list<string>, item: array{kode: string, uraian: string, kategori: string, volume: int|float, satuan: string, harga_satuan: int}|null, jumlah: int|null}>
     */
    public static function periksa(array $barisMentah, array $rincianAda): array
    {
        $ada = [];
        foreach ($rincianAda as $item) {
            $ada[self::kunciItem((string) ($item['uraian'] ?? ''), (float) ($item['volume'] ?? 0), (int) ($item['harga_satuan'] ?? 0))] = true;
        }

        $hasil = [];
        $kodeKelompok = '';
        $uraianDalamBerkas = [];

        foreach ($barisMentah as ['baris' => $nomor, 'nilai' => $nilai]) {
            $pesan = [];
            $peringatan = [];
            $uraian = trim(preg_replace('/\s+/u', ' ', (string) ($nilai['uraian'] ?? '')));
            $kode = self::kode($nilai['kode'] ?? null);

            $kosong = fn (string $kunci) => blank($nilai[$kunci] ?? null);
            if ($kosong('volume') && $kosong('harga_satuan') && $kosong('jumlah')) {
                // Judul kelompok, mis. "521211 Belanja Bahan": kodenya dipakai item di bawahnya yang tanpa kode.
                if (preg_match('/^(\d{6})\b/', $uraian, $cocok)) {
                    $kode = $kode ?: $cocok[1];
                }
                $kodeKelompok = $kode ?: $kodeKelompok;
                $hasil[] = ['baris' => $nomor, 'status' => 'dilewati', 'pesan' => ['Baris judul/kelompok tanpa volume dan harga — tidak diimpor sebagai item.'],
                    'peringatan' => [], 'item' => null, 'jumlah' => null, 'tampil_uraian' => $uraian ?: '—'];

                continue;
            }

            if ($kode === null) {
                $pesan[] = 'Kode akun "'.$nilai['kode'].'" hanya boleh berisi angka, huruf, titik, atau tanda hubung.';
                $kode = '';
            } elseif ($kode === '' && $kodeKelompok !== '') {
                $kode = $kodeKelompok;
                $peringatan[] = "Kode akun {$kode} diambil dari judul kelompok di atasnya.";
            }

            if (mb_strlen($uraian) < 3) {
                $pesan[] = $uraian === '' ? 'Uraian wajib diisi.' : 'Uraian minimal 3 karakter.';
            } elseif (mb_strlen($uraian) > 255) {
                $pesan[] = 'Uraian lebih dari 255 karakter.';
            }

            [$volume, $satuanDariVolume, $pesanVolume] = self::volume($nilai['volume'] ?? null);
            if ($pesanVolume) {
                $pesan[] = $pesanVolume;
            }

            $satuan = trim((string) ($nilai['satuan'] ?? '')) ?: $satuanDariVolume;
            if ($satuan === '') {
                $pesan[] = 'Satuan belum diisi (isi kolom Satuan, atau tulis mis. "6 OJ" di kolom Vol).';
            } elseif (mb_strlen($satuan) > 30) {
                $pesan[] = 'Satuan lebih dari 30 karakter.';
            }

            [$harga, $pesanHarga] = self::rupiah($nilai['harga_satuan'] ?? null, 'Harga satuan');
            [$jumlahBerkas, $pesanJumlah] = self::rupiah($nilai['jumlah'] ?? null, 'Jumlah');
            if ($pesanJumlah) {
                $pesan[] = $pesanJumlah;
            }

            if ($harga === null && $pesanHarga === null && $volume && $jumlahBerkas) {
                $hargaHitung = $jumlahBerkas / $volume;
                if (floor($hargaHitung) == $hargaHitung) {
                    $harga = (int) $hargaHitung;
                    $peringatan[] = 'Harga satuan dihitung dari jumlah ÷ volume: '.Format::rupiah($harga).'.';
                }
            }

            if ($pesanHarga) {
                $pesan[] = $pesanHarga;
            } elseif ($harga === null || $harga < 1) {
                $pesan[] = 'Harga satuan wajib diisi dengan rupiah lebih dari 0.';
            } elseif ($harga > ItemBelanjaForm::MAKS_HARGA) {
                $pesan[] = 'Harga satuan '.Format::rupiah($harga).' terlalu besar. Periksa jumlah digit.';
            }

            $jumlah = null;
            if ($pesan === [] && $volume !== null && $harga !== null) {
                $jumlah = KalkulatorRab::jumlah($volume, $harga);

                if ($jumlahBerkas !== null && $jumlahBerkas !== $jumlah) {
                    $volumeBaru = round($jumlahBerkas / $harga, 2);

                    if ($volumeBaru > 0 && KalkulatorRab::jumlah($volumeBaru, $harga) === $jumlahBerkas) {
                        $peringatan[] = 'Volume disesuaikan dari '.self::teksAngka($volume).' menjadi '.self::teksAngka($volumeBaru)
                            .' agar volume × harga sama dengan jumlah di berkas ('.Format::rupiah($jumlahBerkas).').';
                        $volume = $volumeBaru;
                        $jumlah = $jumlahBerkas;
                    } else {
                        $pesan[] = 'Jumlah di berkas '.Format::rupiah($jumlahBerkas).' tidak sama dengan volume × harga ('.Format::rupiah($jumlah)
                            .') dan tidak dapat disesuaikan otomatis.';
                        $jumlah = null;
                    }
                }
            }

            $item = null;
            $status = $pesan === [] ? 'siap' : 'gagal';

            if ($status === 'siap') {
                $item = [
                    'kode' => $kode,
                    'uraian' => $uraian,
                    'kategori' => ItemBelanjaForm::tebakKategori($uraian),
                    'volume' => floor($volume) == $volume ? (int) $volume : round($volume, 2),
                    'satuan' => $satuan,
                    'harga_satuan' => $harga,
                ];

                $kunci = self::kunciItem($uraian, (float) $volume, $harga);
                if (isset($ada[$kunci])) {
                    $status = 'dilewati';
                    $pesan[] = 'Item yang sama (uraian, volume, harga) sudah ada di RAB ini.';
                }

                $kunciUraian = mb_strtolower($uraian);
                if (isset($uraianDalamBerkas[$kunciUraian])) {
                    $peringatan[] = "Uraian sama dengan baris {$uraianDalamBerkas[$kunciUraian]} — pastikan bukan dobel.";
                }
                $uraianDalamBerkas[$kunciUraian] ??= $nomor;
            }

            $hasil[] = [
                'baris' => $nomor,
                'status' => $status,
                'pesan' => $pesan,
                'peringatan' => $status === 'gagal' ? [] : $peringatan,
                'item' => $item,
                'jumlah' => $status === 'gagal' ? null : $jumlah,
                'tampil_uraian' => $uraian ?: '—',
            ];
        }

        return $hasil;
    }

    /**
     * Kode akun: angka dari Excel (521211.0) jadi teks; '' bila kosong; null bila berisi karakter tak sah.
     */
    private static function kode(mixed $nilai): ?string
    {
        if (is_int($nilai) || is_float($nilai)) {
            return (string) (int) $nilai;
        }

        $kode = trim((string) $nilai);

        return $kode === '' || (mb_strlen($kode) <= 20 && preg_match('/^[0-9A-Za-z.\-]+$/', $kode)) ? $kode : null;
    }

    /**
     * Volume angka atau teks ("1,5", "6 OJ", "56 pack") → [volume, satuan dari teks, pesan galat].
     *
     * @return array{0: float|null, 1: string, 2: string|null}
     */
    private static function volume(mixed $nilai): array
    {
        if (is_int($nilai) || is_float($nilai)) {
            $angka = (float) $nilai;
            $satuan = '';
        } elseif (preg_match('/^\s*([\d.,]+)\s*(.*)$/u', (string) $nilai, $cocok)) {
            $angka = ItemBelanjaForm::angkaVolume($cocok[1]);
            $satuan = trim($cocok[2]);
        } else {
            return [null, '', blank($nilai) ? 'Volume wajib diisi.' : 'Volume "'.$nilai.'" bukan angka (mis. 6 atau 1,5).'];
        }

        return match (true) {
            $angka === null => [null, $satuan, 'Volume "'.$nilai.'" bukan angka (mis. 6 atau 1,5).'],
            $angka <= 0 => [null, $satuan, 'Volume harus lebih dari 0.'],
            $angka > 100_000 => [null, $satuan, 'Volume terlalu besar (maksimal 100.000).'],
            default => [round($angka, 2), $satuan, null],
        };
    }

    /**
     * Rupiah bulat dari angka atau teks ("Rp 600.000", "600.000,-", "600000"); [null, null] bila kosong.
     *
     * @return array{0: int|null, 1: string|null}
     */
    private static function rupiah(mixed $nilai, string $label): array
    {
        if ($nilai === null || $nilai === '') {
            return [null, null];
        }

        if (is_int($nilai) || is_float($nilai)) {
            return floor($nilai) == $nilai && $nilai >= 0
                ? [(int) $nilai, null]
                : [null, "{$label} harus rupiah bulat (tertulis {$nilai})."];
        }

        if (! preg_match('/^\s*(rp\.?)?\s*[\d.\s]+(,-?|,00)?\s*$/i', (string) $nilai)) {
            return [null, "{$label} \"{$nilai}\" bukan angka rupiah."];
        }

        return [Format::parseRupiah(preg_replace('/,(-|00)?\s*$/', '', (string) $nilai)), null];
    }

    private static function kunciItem(string $uraian, float $volume, int $harga): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $uraian))).'|'.round($volume, 2).'|'.$harga;
    }

    private static function teksAngka(float $angka): string
    {
        return ItemBelanjaForm::teksVolume(floor($angka) == $angka ? (int) $angka : $angka);
    }
}
