<?php

namespace App\Repositories;

use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Enums\SumberDana;
use App\Models\Kegiatan;
use App\Models\RiwayatStatusKegiatan;
use App\Support\Kegiatan\DataPelaksanaan;
use App\Support\Stub\StubData;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Penyimpanan kegiatan di PostgreSQL (activities + anggaran_kegiatan, committees, riwayat_status_kegiatan) dengan bentuk
 * data yang sama dengan StubKegiatanRepository, sehingga komponen tidak berubah saat binding diganti.
 *
 * simpan() hanya mengubah kunci yang dikirim — kunci yang tidak dikirim tetap seperti tersimpan (tiruan mengganti seluruh
 * record) — dan riwayat_status hanya ditambah: entri di luar jumlah yang sudah tersimpan.
 */
class EloquentKegiatanRepository implements KegiatanRepository
{
    /** Kunci data kegiatan → kolom activities. */
    private const KOLOM = [
        'nama_kegiatan' => 'nama_kegiatan',
        'nama_kegiatan_cover' => 'nama_kegiatan_cover',
        'cover_manual' => 'cover_manual',
        'latar_belakang' => 'latar_belakang',
        'tujuan' => 'tujuan',
        'sasaran' => 'sasaran',
        'tanggal_dokumen' => 'tanggal_dokumen',
        'tanggal_laporan' => 'tanggal_laporan',
        'laporan_otomatis' => 'laporan_otomatis',
        'tanggal_mulai' => 'tanggal_mulai',
        'lama_hari' => 'lama_hari',
        'hari_kerja' => 'hari_kerja',
        'bulan_pelaksanaan' => 'bulan_pelaksanaan',
        'leading' => 'kode_leading',
        'kode_rktm' => 'kode_rktm',
        'kode_sk' => 'kode_sk',
        'kode_iksk' => 'kode_iksk',
        'kode_edm' => 'kode_edm',
        'budaya_mutu' => 'budaya_mutu',
        'tempat' => 'tempat',
        'peserta_jumlah' => 'peserta_jumlah',
        'peserta_jenis' => 'peserta_jenis',
        'uraian_pelaksanaan' => 'uraian_pelaksanaan',
        'konteks' => 'konteks',
        'kode_hilang' => 'kode_hilang',
        'riwayat_kode' => 'riwayat_kode',
        'asal' => 'asal',
        'data_pagu_id' => 'data_pagu_id',
        'status' => 'status',
    ];

    public function simpan(array $data, ?int $id = null): int
    {
        return DB::transaction(function () use ($data, $id) {
            $kegiatan = $id === null ? null : Kegiatan::query()->lockForUpdate()->find($id);

            // Pembuat (user_id) dan tahun pemilik dicatat sekali saat kegiatan dibuat, sama dengan tiruan.
            $kegiatan ??= new Kegiatan([
                'user_id' => $data['user_id'] ?? StubData::currentUser()['id'],
                'tahun_anggaran' => $data['tahun_anggaran'] ?? StubData::tahunAktif()['tahun'],
                'nama_kegiatan' => '',
            ]);

            $kegiatan->fill($this->kolom($data));
            // Jejak kunci hanya selama Final (CHECK activities_kunci_check): dibuang dalam simpan yang sama dengan status
            // barunya (mis. buka kunci); untuk Final diisi catatKunci() sesudah riwayatnya tersimpan.
            if ($kegiatan->status !== StatusKegiatan::Final->value) {
                $kegiatan->forceFill(['dikunci_pada' => null, 'dikunci_oleh' => null]);
            }
            // Waktu perubahan catatan konteks (bahan AI) dicatat terpisah dari versi Data Kegiatan.
            if ($kegiatan->isDirty('konteks')) {
                $kegiatan->konteks_diperbarui_pada = blank($kegiatan->konteks) ? null : $kegiatan->freshTimestamp();
            }
            // Salinan tanggal selesai (DataPelaksanaan) untuk pencarian rentang jadwal di database; tidak dikirim ke tampilan.
            $kegiatan->tanggal_selesai = DataPelaksanaan::dari($kegiatan->only(['tanggal_mulai', 'lama_hari', 'hari_kerja']))->selesai()?->toDateString();
            // Setiap simpan memperbarui versi, termasuk bila hanya sumber dana/panitia/riwayat yang berubah.
            $kegiatan->updated_at = $kegiatan->freshTimestamp();
            $kegiatan->save();

            if (array_key_exists('sumber_dana', $data)) {
                $this->simpanSumberDana($kegiatan, (array) $data['sumber_dana']);
            }
            if (array_key_exists('panitia', $data)) {
                $this->simpanPanitia($kegiatan, (array) $data['panitia']);
            }
            if (array_key_exists('riwayat_status', $data)) {
                $this->tambahRiwayat($kegiatan, (array) $data['riwayat_status']);
            }
            $this->catatKunci($kegiatan);

            return $kegiatan->id;
        });
    }

    public function temukan(int $id): ?array
    {
        $kegiatan = $this->query()->find($id);

        return $kegiatan ? $this->keData($kegiatan) : null;
    }

    public function terbaru(int $batas = 10): array
    {
        return Kegiatan::query()->orderByDesc('updated_at')->orderByDesc('id')->limit(max(0, $batas))
            ->get(['id', 'nama_kegiatan', 'status', 'updated_at'])
            ->map(fn (Kegiatan $k) => [
                'id' => $k->id,
                'nama_kegiatan' => $k->nama_kegiatan,
                'status' => $k->status,
                'diperbarui_pada' => self::waktu($k->updated_at),
            ])->all();
    }

    public function semua(): array
    {
        return $this->query()->orderByDesc('updated_at')->orderByDesc('id')->get()
            ->map(fn (Kegiatan $k) => $this->keData($k))->all();
    }

    public function riwayatIsian(string $kolom, int $batas = 20): array
    {
        if (! in_array($kolom, self::KOLOM_RIWAYAT, true)) {
            throw new InvalidArgumentException("Riwayat isian kolom [{$kolom}] tidak tersedia.");
        }

        $nilai = [];
        foreach (Kegiatan::query()->whereNotNull($kolom)->orderByDesc('updated_at')->orderByDesc('id')->pluck($kolom) as $isi) {
            $isi = trim($isi);
            $nilai[mb_strtolower($isi)] ??= $isi;
        }
        unset($nilai['']);

        return array_slice(array_values($nilai), 0, max(0, $batas));
    }

    /** @return Builder<Kegiatan> */
    private function query(): Builder
    {
        return Kegiatan::query()->with(['anggaran', 'panitia', 'riwayatStatus', 'duplikatDari:id,tahun_anggaran']);
    }

    /**
     * Nilai kolom untuk kunci yang dikirim saja.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function kolom(array $data): array
    {
        $kolom = [];
        foreach (self::KOLOM as $kunci => $nama) {
            if (array_key_exists($kunci, $data)) {
                $kolom[$nama] = self::nilai($kunci, $data[$kunci]);
            }
        }
        if (array_key_exists('duplikat_dari', $data)) {
            $kolom['duplikat_dari_id'] = filled($data['duplikat_dari']['id'] ?? null) ? (int) $data['duplikat_dari']['id'] : null;
        }
        // Impor yang membuat kegiatan (impor_kegiatan); hanya dikirim jalur impor bertahap.
        if (array_key_exists('impor_id', $data)) {
            $kolom['impor_id'] = filled($data['impor_id']) ? (string) $data['impor_id'] : null;
        }

        return $kolom;
    }

    private static function nilai(string $kunci, mixed $nilai): mixed
    {
        return match ($kunci) {
            'nama_kegiatan' => (string) $nilai,
            'cover_manual', 'hari_kerja' => (bool) $nilai,
            'laporan_otomatis' => $nilai === null || (bool) $nilai,
            'lama_hari', 'peserta_jumlah', 'budaya_mutu' => filled($nilai) ? (int) $nilai : null,
            'tanggal_dokumen', 'tanggal_laporan', 'tanggal_mulai', 'data_pagu_id' => filled($nilai) ? (string) $nilai : null,
            'bulan_pelaksanaan' => array_values(array_map('intval', (array) $nilai)),
            'asal' => $nilai ?: 'manual',
            'status' => $nilai ?: StatusKegiatan::Draf->value,
            default => $nilai,
        };
    }

    /**
     * Satu baris anggaran per sumber dana; sumber yang tidak dipilih lagi hanya ditandai `dipilih = false` (pagu
     * dikosongkan seperti di form) agar rincian & termin RAB-nya tetap tersimpan (RabRepository::sumberTersimpan).
     *
     * @param  array<int, array{sumber?: string, pagu?: int|null}>  $sumberDana
     */
    private function simpanSumberDana(Kegiatan $kegiatan, array $sumberDana): void
    {
        $pagu = [];
        foreach ($sumberDana as $baris) {
            $pagu[(string) ($baris['sumber'] ?? '')] = filled($baris['pagu'] ?? null) ? (int) $baris['pagu'] : null;
        }

        $kegiatan->anggaran()->whereNotIn('sumber_dana', array_keys($pagu))->update(['dipilih' => false, 'pagu' => null]);
        foreach ($pagu as $sumber => $nilai) {
            $kegiatan->anggaran()->updateOrCreate(['sumber_dana' => $sumber], ['dipilih' => true, 'pagu' => $nilai]);
        }
    }

    /** @param  array<int, array{nama?: string, jabatan?: string|null, peran?: string}>  $panitia */
    private function simpanPanitia(Kegiatan $kegiatan, array $panitia): void
    {
        $kegiatan->panitia()->delete();
        foreach (array_values($panitia) as $i => $anggota) {
            $kegiatan->panitia()->create([
                'urutan' => $i + 1,
                'nama' => (string) ($anggota['nama'] ?? ''),
                'jabatan' => $anggota['jabatan'] ?? null,
                'peran' => (string) ($anggota['peran'] ?? 'Anggota'),
            ]);
        }
    }

    /** @param  list<array<string, mixed>>  $riwayat  Seluruh riwayat; hanya entri sesudah yang tersimpan yang ditambahkan. */
    private function tambahRiwayat(Kegiatan $kegiatan, array $riwayat): void
    {
        $tersimpan = $kegiatan->riwayatStatus()->count();
        foreach (array_slice(array_values($riwayat), $tersimpan) as $entri) {
            $kegiatan->riwayatStatus()->create([
                'aksi' => $entri['aksi'],
                'status' => $entri['status'],
                'user_id' => StubData::currentUser()['id'],
                'oleh' => $entri['oleh'],
                'peran' => $entri['peran'],
                'catatan' => $entri['catatan'] ?? null,
                'sidik' => $entri['sidik'] ?? null,
                'created_at' => $entri['pada'] ?? now(),
            ]);
        }
    }

    /** Jejak kunci final (dikunci_pada/oleh) = entri finalkan terakhir selama berstatus Final. */
    private function catatKunci(Kegiatan $kegiatan): void
    {
        $kunci = $kegiatan->status === StatusKegiatan::Final->value
            ? $kegiatan->riwayatStatus()->reorder()->where('aksi', 'finalkan')->latest('id')->first()
            : null;

        $kegiatan->forceFill(['dikunci_pada' => $kunci?->created_at, 'dikunci_oleh' => $kunci?->user_id]);
        if ($kegiatan->isDirty()) {
            $kegiatan->save();
        }
    }

    /** @return array<string, mixed> */
    private function keData(Kegiatan $kegiatan): array
    {
        $data = [
            'id' => $kegiatan->id,
            'status' => $kegiatan->status,
            'diperbarui_pada' => self::waktu($kegiatan->updated_at),
            'dibuat_pada' => self::waktu($kegiatan->created_at),
            'user_id' => $kegiatan->user_id,
            'tahun_anggaran' => $kegiatan->tahun_anggaran,
        ];
        foreach (self::KOLOM as $kunci => $kolom) {
            $data[$kunci] = $kegiatan->getAttribute($kolom);
        }

        $anggaran = $kegiatan->anggaran->keyBy('sumber_dana');
        $data['sumber_dana'] = [];
        foreach (SumberDana::cases() as $sumber) {
            if (($baris = $anggaran->get($sumber->value)) && $baris->dipilih) {
                $data['sumber_dana'][] = ['sumber' => $sumber->value, 'pagu' => $baris->pagu];
            }
        }

        $data['panitia'] = $kegiatan->panitia->map(fn ($a) => ['nama' => $a->nama, 'jabatan' => $a->jabatan, 'peran' => $a->peran])->all();
        $data['riwayat_status'] = $kegiatan->riwayatStatus->map(fn (RiwayatStatusKegiatan $r) => array_filter([
            'aksi' => $r->aksi,
            'status' => $r->status,
            'oleh' => $r->oleh,
            'peran' => $r->peran,
            'pada' => $r->created_at->setTimezone(config('app.timezone'))->toIso8601String(),
            'catatan' => $r->catatan,
            'sidik' => $r->sidik,
        ], fn ($nilai) => $nilai !== null))->all();
        $data['konteks_diperbarui_pada'] = self::waktu($kegiatan->konteks_diperbarui_pada);
        $data['duplikat_dari'] = $kegiatan->duplikat_dari_id
            ? ['id' => $kegiatan->duplikat_dari_id, 'tahun' => $kegiatan->duplikatDari?->tahun_anggaran]
            : null;
        if ($kegiatan->impor_id !== null) {
            $data['impor_id'] = $kegiatan->impor_id;
        }

        return $data;
    }

    /** Waktu bermikrodetik di zona waktu aplikasi: menjadi versi pengenal perubahan dari tab lain. */
    private static function waktu(?CarbonInterface $waktu): ?string
    {
        return $waktu?->setTimezone(config('app.timezone'))->format('Y-m-d\TH:i:s.uP');
    }
}
