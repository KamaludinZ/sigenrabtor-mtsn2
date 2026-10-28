<?php

namespace App\Support\Master;

use App\Contracts\MasterKodeRepository;
use App\Contracts\TahunAnggaranRepository;
use App\Support\Master\Impor\SimpanMasterKode;
use App\Support\MasterKode;
use App\Support\Pengaturan\RiwayatPengaturan;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Persistensi master kode per tahun anggaran (PRD F6 & §14 "master dapat disalin dari tahun sebelumnya"): master tiap
 * tahun berdiri sendiri (MasterKodeRepository per tahun; tahun lain tidak ikut berubah), dan tahun yang mastersnya masih
 * kosong bisa diisi salinan tahun lain tanpa mengunggah ulang.
 * - `sumber($tahun)`: tahun yang disarankan — tahun lain bermaster, terdekat sebelum $tahun lebih dulu.
 * - `salin($dari, $ke, $pengguna)`: per kelompok jenis (`KELOMPOK`: SK & IKSK bersama agar setiap IKSK tetap punya SK
 *   induk; RKTM; EDM beserta nama komponen; 5 Budaya Mutu) hanya yang di tahun tujuan masih kosong — isian yang sudah ada
 *   tidak pernah tertimpa. Hanya admin; tahun tujuan terdaftar & tidak terkunci. Jejak: catatan unggahan (`nama_berkas`
 *   "Salinan master TA {dari}", ringkasan baru per jenis) + riwayat `salin_master_kode`.
 */
final class SalinMasterKode
{
    /** Kelompok jenis yang disalin bersama. */
    public const KELOMPOK = [['sk', 'iksk'], ['rktm'], ['edm'], ['budaya']];

    public static function sumber(int $tahun): ?int
    {
        $tersedia = array_values(array_filter(app(MasterKodeRepository::class)->tahunTersedia(), fn (int $t) => $t !== $tahun));
        $sebelum = array_filter($tersedia, fn (int $t) => $t < $tahun);

        return $sebelum !== [] ? max($sebelum) : ($tersedia !== [] ? min($tersedia) : null);
    }

    /**
     * @param  array{id?: int, name?: string, role: string}  $pengguna
     * @return array{disalin: array<string, int>, dilewati: list<string>} Jenis → jumlah kode disalin; jenis yang dilewati karena sudah terisi.
     *
     * @throws AuthorizationException Bukan admin, tahun tujuan belum dibuat atau terkunci.
     * @throws ValidationException Berkunci `dari`.
     */
    public static function salin(int $dari, int $ke, array $pengguna): array
    {
        if ($pengguna['role'] !== 'admin') {
            throw new AuthorizationException('Master kode diatur admin.');
        }
        $tujuan = collect(app(TahunAnggaranRepository::class)->semua())->firstWhere('tahun', $ke);
        if ($tujuan === null || $tujuan['is_locked']) {
            throw new AuthorizationException($tujuan === null ? "Tahun Anggaran {$ke} belum dibuat." : "Tahun Anggaran {$ke} terkunci — master kodenya hanya bisa dilihat.");
        }
        if ($dari === $ke || ! MasterKode::tersedia($dari)) {
            throw ValidationException::withMessages(['dari' => "Pilih tahun lain yang sudah punya master kode (TA {$dari} tidak bisa disalin)."]);
        }

        $repo = app(MasterKodeRepository::class);
        $master = [];
        $disalin = [];
        $dilewati = [];
        foreach (self::KELOMPOK as $kelompok) {
            $kosongDiTujuan = collect($kelompok)->every(fn (string $j) => JenisKode::baris($j, $ke) === []);
            $adaDiSumber = collect($kelompok)->contains(fn (string $j) => JenisKode::baris($j, $dari) !== []);
            if (! $adaDiSumber) {
                continue;
            }
            if (! $kosongDiTujuan) {
                array_push($dilewati, ...$kelompok);

                continue;
            }
            foreach ($kelompok as $jenis) {
                $master[SimpanMasterKode::BAGIAN[$jenis]] = JenisKode::baris($jenis, $dari);
                $disalin[$jenis] = count($master[SimpanMasterKode::BAGIAN[$jenis]]);
            }
            if (in_array('edm', $kelompok, true)) {
                $master['komponen_edm'] = $repo->komponenEdm($dari);
            }
        }

        if ($master !== []) {
            $repo->ganti($ke, $master);
            $repo->catatUnggahan([
                'tahun' => $ke, 'cakupan' => 'semua', 'nama_berkas' => "Salinan master TA {$dari}",
                'ringkasan' => array_map(fn (int $n) => ['baru' => $n], $disalin),
                'user_id' => $pengguna['id'] ?? null, 'pengguna' => $pengguna['name'] ?? 'Admin',
            ]);
            RiwayatPengaturan::catat('salin_master_kode', "Master kode TA {$dari} disalin ke TA {$ke}: ".implode(', ', array_map(fn (string $j, int $n) => JenisKode::DAFTAR[$j]['label']." {$n}", array_keys($disalin), $disalin))
                .($dilewati !== [] ? ' (sudah terisi, tidak diubah: '.implode(', ', array_map(fn (string $j) => JenisKode::DAFTAR[$j]['label'], $dilewati)).')' : '').'.');
        }

        return ['disalin' => $disalin, 'dilewati' => $dilewati];
    }
}
