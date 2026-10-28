<?php

namespace App\Support\Pagu;

use App\Contracts\DataPaguRepository;
use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Enums\SumberDana;
use App\Support\Format;
use App\Support\Kegiatan\KalimatKak;
use App\Support\Kegiatan\KunciKegiatan;
use App\Support\Kegiatan\ValidasiKegiatan;
use App\Support\MasterKode;
use App\Support\Rab\AnggaranKegiatan;
use App\Support\Rab\SimpanSumberDana;
use App\Support\Rab\StatusPagu;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Tambah, ubah, dan hapus Data Pagu di server (PRD F12 "Batas pagu"; hanya admin). Isian dirapikan (`rapikan()`: nama
 * tanpa awalan "Kegiatan" & spasi ganda, pagu rupiah berformat → angka, 0/kosong = tanpa pagu) lalu diperiksa: nama
 * wajib ≤ 255 & unik per tahun (DataPagu::kunci), leading aktif atau leading yang sudah tersimpan, pagu ≤ MAKS_PAGU,
 * minimal satu pagu. Ubah tidak memindah tahun data. Hasil membawa `dampak` per sumber dana bagi kegiatan pemakainya
 * (dihitung sebelum diterapkan: pagu kegiatan vs pagu baru, total RAB, status pagu sekarang & bila pagu baru dipakai);
 * `terapkan` sekaligus mengganti sumber dana & pagu RAB kegiatan itu dengan isi Data Pagu (SimpanSumberDana — sumber tanpa
 * pagu di Data Pagu tidak dipilih lagi, RAB-nya dilaporkan di `terlepas`) bila kegiatannya masih bisa diubah (`alasan`
 * bila tidak) — tanpa `terapkan` kegiatan tidak berubah.
 */
final class SimpanDataPagu
{
    /** Kunci galat per sumber dana: `pagu.DIPA`, `pagu.Komite`. */
    public const KOLOM = ['nama_kegiatan', 'leading', 'pagu'];

    /**
     * @param  array<string, mixed>  $isian  {nama_kegiatan, leading, pagu: {DIPA, Komite}} atau pagu_dipa/pagu_komite.
     * @return array{nama_kegiatan: string, leading: string|null, pagu: array<string, int|null>}
     */
    public static function rapikan(array $isian): array
    {
        $pagu = [];
        foreach (SumberDana::cases() as $sumber) {
            $nilai = $isian['pagu'][$sumber->value] ?? $isian['pagu_'.$sumber->kunci()] ?? null;
            $nominal = blank($nilai) ? null : Format::parseRupiah($nilai);
            $pagu[$sumber->value] = $nominal !== null && $nominal > 0 ? $nominal : null;
        }

        return [
            'nama_kegiatan' => trim((string) preg_replace('/\s+/u', ' ', KalimatKak::tanpaAwalanKegiatan((string) ($isian['nama_kegiatan'] ?? '')))),
            'leading' => filled($isian['leading'] ?? null) ? trim((string) $isian['leading']) : null,
            'pagu' => $pagu,
        ];
    }

    /**
     * @param  array<string, mixed>  $isian
     * @param  array{id: int, role: string}  $pengguna
     * @return array{id: string, data: array<string, mixed>, kegiatan: array{id: int, nama: string}|null, dampak: list<array<string, mixed>>, diterapkan: bool, alasan: string|null, terlepas: array<string, string>}
     *
     * @throws AuthorizationException Bukan admin, atau data pagu tidak ditemukan.
     * @throws ValidationException Berkunci nama_kegiatan, leading, pagu.{sumber}.
     */
    public static function simpan(array $isian, ?string $id, array $pengguna, bool $terapkan = false, ?int $tahun = null): array
    {
        self::pastikanAdmin($pengguna);
        $repo = app(DataPaguRepository::class);
        $lama = $id !== null ? ($repo->temukan($id) ?? throw new AuthorizationException('Data pagu tidak ditemukan.')) : null;
        $tahun = $lama['tahun'] ?? $tahun ?? StubData::tahunAktif()['tahun'];
        $data = self::rapikan($isian);

        $galat = self::galat($data, $tahun, $lama);
        if ($galat !== []) {
            throw ValidationException::withMessages($galat);
        }

        $id = $repo->simpan(['tahun' => $tahun] + $data, $lama['id'] ?? null);
        $tersimpan = $repo->temukan($id);
        $pemakai = DataPagu::pemakaian([$tersimpan])[$id];
        $kegiatan = $pemakai ? app(KegiatanRepository::class)->temukan($pemakai['id']) : null;
        $hasil = ['id' => $id, 'data' => $tersimpan, 'kegiatan' => $pemakai, 'dampak' => self::dampak($tersimpan, $kegiatan), 'diterapkan' => false, 'alasan' => null, 'terlepas' => []];

        if (! $terapkan || $kegiatan === null) {
            return $hasil;
        }
        $status = StatusKegiatan::tryFrom((string) ($kegiatan['status'] ?? '')) ?? StatusKegiatan::Draf;
        if (! $status->dapatDiedit()) {
            return ['alasan' => "Kegiatan \"{$pemakai['nama']}\" berstatus {$status->label()} sehingga pagunya tidak diubah."] + $hasil;
        }
        if (($tahunKunci = KunciKegiatan::tahunTerkunci($kegiatan)) !== null) {
            return ['alasan' => "Kegiatan \"{$pemakai['nama']}\" milik Tahun Anggaran {$tahunKunci} yang terkunci sehingga pagunya tidak diubah."] + $hasil;
        }

        $simpan = SimpanSumberDana::simpan($pemakai['id'], DataPagu::sumberDana($tersimpan));

        return ['diterapkan' => true, 'terlepas' => $simpan['terlepas']] + $hasil;
    }

    /**
     * Hapus data pagu; kegiatan yang sudah dibuat tidak berubah (tautannya saja yang dilepas).
     *
     * @param  array{id: int, role: string}  $pengguna
     * @return array{id: int, nama: string}|null Kegiatan yang memakai data itu.
     */
    public static function hapus(string $id, array $pengguna): ?array
    {
        self::pastikanAdmin($pengguna);
        $repo = app(DataPaguRepository::class);
        $data = $repo->temukan($id) ?? throw new AuthorizationException('Data pagu tidak ditemukan.');
        $pemakai = DataPagu::pemakaian([$data])[$id];
        $repo->hapus($id);

        return $pemakai;
    }

    /**
     * Galat isian yang sudah dirapikan, berkunci nama_kegiatan / leading / pagu.{sumber}.
     *
     * @param  array{nama_kegiatan: string, leading: string|null, pagu: array<string, int|null>}  $data
     * @param  array<string, mixed>|null  $lama
     * @return array<string, string>
     */
    public static function galat(array $data, int $tahun, ?array $lama = null): array
    {
        $galat = [];
        $kunci = DataPagu::kunci($data['nama_kegiatan']);
        if ($data['nama_kegiatan'] === '') {
            $galat['nama_kegiatan'] = 'Nama kegiatan wajib diisi.';
        } elseif (mb_strlen($data['nama_kegiatan']) > 255) {
            $galat['nama_kegiatan'] = 'Nama kegiatan maksimal 255 karakter.';
        } elseif (collect(app(DataPaguRepository::class)->semua($tahun))->contains(fn (array $p) => $p['id'] !== ($lama['id'] ?? null) && DataPagu::kunci($p['nama_kegiatan']) === $kunci)) {
            $galat['nama_kegiatan'] = 'Nama ini sudah ada di Data Pagu.';
        }

        $leadingLama = $lama['leading'] ?? null;
        if ($data['leading'] !== null && $data['leading'] !== $leadingLama && MasterKode::cari(MasterKode::leadings(), $data['leading']) === null) {
            $galat['leading'] = 'Leading tidak dikenal.';
        }

        foreach ($data['pagu'] as $sumber => $pagu) {
            if ($pagu !== null && $pagu > ValidasiKegiatan::MAKS_PAGU) {
                $galat["pagu.{$sumber}"] = 'Pagu melebihi batas wajar.';
            }
        }
        if (array_filter($data['pagu']) === []) {
            $galat['pagu.'.SumberDana::Komite->value] = 'Isi pagu DIPA atau Komite.';
        }

        return $galat;
    }

    /**
     * Dampak pagu Data Pagu pada RAB kegiatan pemakainya, per sumber dana yang berpagu di salah satunya: pagu kegiatan,
     * pagu Data Pagu, total RAB, status pagu sekarang, dan status bila pagu Data Pagu dipakai (`status_baru`).
     *
     * @param  array<string, mixed>  $dataPagu
     * @param  array<string, mixed>|null  $kegiatan
     * @return list<array{sumber: string, pagu_kegiatan: int|null, pagu_data: int|null, berubah: bool, total_rab: int, status: string|null, status_baru: string, keterangan_baru: string}>
     */
    public static function dampak(array $dataPagu, ?array $kegiatan): array
    {
        if ($kegiatan === null) {
            return [];
        }
        $anggaran = AnggaranKegiatan::perSumber($kegiatan);

        $hasil = [];
        foreach (SumberDana::urutan() as $sumber) {
            $a = $anggaran[$sumber] ?? null;
            $paguData = $dataPagu['pagu'][$sumber] ?? null;
            if ($a === null && $paguData === null) {
                continue;
            }
            $total = $a?->total() ?? 0;
            $baru = StatusPagu::hitung($total, $paguData);
            $hasil[] = [
                'sumber' => $sumber,
                'pagu_kegiatan' => $a?->pagu(),
                'pagu_data' => $paguData,
                'berubah' => $a === null || $a->pagu() !== $paguData,
                'total_rab' => $total,
                'status' => $a?->keteranganPagu()->status,
                'status_baru' => $baru->status,
                'keterangan_baru' => $baru->teks(),
            ];
        }

        return $hasil;
    }

    /**
     * Kalimat dampak untuk notifikasi: sumber yang pagunya berbeda dengan kegiatan & statusnya bila pagu baru dipakai.
     *
     * @param  array{kegiatan: array{nama: string}|null, dampak: list<array<string, mixed>>, diterapkan: bool}  $hasil
     */
    public static function ringkasDampak(array $hasil): ?string
    {
        $berubah = array_filter($hasil['dampak'], fn (array $d) => $d['berubah']);
        if ($hasil['kegiatan'] === null || $berubah === []) {
            return null;
        }
        $rincian = implode('; ', array_map(fn (array $d) => "{$d['sumber']} {$d['keterangan_baru']}", $berubah));

        return $hasil['diterapkan']
            ? "Pagu RAB kegiatan \"{$hasil['kegiatan']['nama']}\" ikut diperbarui ({$rincian})."
            : "Pagu kegiatan \"{$hasil['kegiatan']['nama']}\" tidak ikut berubah; dengan pagu baru: {$rincian}.";
    }

    /** @param  array{role: string}  $pengguna */
    private static function pastikanAdmin(array $pengguna): void
    {
        if ($pengguna['role'] !== 'admin') {
            throw new AuthorizationException('Data Pagu diatur admin.');
        }
    }
}
