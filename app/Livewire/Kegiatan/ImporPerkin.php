<?php

namespace App\Livewire\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Livewire\Impor\ImporBertahap;
use App\Support\Format;
use App\Support\Kegiatan\Impor\PembacaPerkin;
use App\Support\Kegiatan\Impor\PemeriksaImporKegiatan;
use App\Support\Kegiatan\Impor\PemeriksaPerkin;
use App\Support\Kegiatan\JadwalPelaksanaan;
use App\Support\Kegiatan\RiwayatStatus;
use App\Support\Kegiatan\TahunKegiatan;
use App\Support\MasterKode;
use App\Support\Stub\StubData;
use Livewire\Attributes\Locked;

/**
 * Impor draf kegiatan massal dari sheet cascade Perkin (PRD §19, tab Perkin halaman Duplikat & Impor): unggah berkas →
 * pratinjau per baris (PemeriksaPerkin: isian kegiatan + kode SK/IKSK terhadap master tahun aktif) di tabel khusus
 * Perkin (asal kode, kode yang dikosongkan, pagu per sumber + baris jumlah, cari) beserta susunan cascade SK → IKSK →
 * konfirmasi (ringkasan draf yang dibuat) → proses bertahap → laporan. Pratinjau bisa dibatalkan kapan saja tanpa
 * membuat kegiatan (konfirmasi inline bila ada baris siap); meninggalkan halaman selama pratinjau/proses dimintai
 * konfirmasi.
 */
class ImporPerkin extends ImporBertahap
{
    /** Sheet yang dibaca dari berkas terakhir. */
    #[Locked]
    public string $lembar = '';

    /** @var list<string> Label kolom opsional yang tidak ada di judul sheet. */
    #[Locked]
    public array $kolomHilang = [];

    #[Locked]
    public bool $konfirmasiImpor = false;

    #[Locked]
    public bool $konfirmasiBatal = false;

    public function kolom(): array
    {
        return ['nama' => 'Nama kegiatan', 'kode' => 'SK · IKSK', 'leading' => 'Leading', 'pagu' => 'Pagu', 'bulan' => 'Bulan'];
    }

    protected function periksaBerkas(string $path, string $namaAsli): array
    {
        $pengguna = StubData::currentUser();
        $leadings = MasterKode::leadingsUntuk($pengguna);
        $tahun = StubData::tahunAktif()['tahun'];
        $bacaan = PembacaPerkin::baca($path, $namaAsli);
        $this->lembar = $bacaan['lembar'];
        $this->kolomHilang = array_values(array_diff_key(PembacaPerkin::LABEL, array_flip($bacaan['kolom'])));

        // Hanya kegiatan tahun aktif: nama yang sama di tahun lalu tetap boleh dibuat lagi untuk tahun ini.
        $namaAda = array_column(array_filter(app(KegiatanRepository::class)->semua(), fn (array $k) => TahunKegiatan::dari($k) === $tahun), 'nama_kegiatan');
        $diperiksa = PemeriksaPerkin::periksa($bacaan['baris'], $namaAda, $leadings, MasterKode::leadings(), count($leadings) === 1 ? $leadings[0]['kode'] : null);

        $uraianSk = array_column(MasterKode::kodeSk(), 'uraian', 'kode');
        $uraianIksk = array_column(MasterKode::kodeIksk(), 'uraian', 'kode');

        return array_map(fn (array $b) => [
            'baris' => $b['baris'],
            // Bentuk terurai untuk tabel pratinjau Perkin (partial tabel-perkin).
            'perkin' => [
                'sk' => $b['kode_sk'], 'sk_uraian' => $uraianSk[$b['kode_sk']] ?? null, 'sk_asal' => $b['asal_sk'],
                'iksk' => $b['kode_iksk'], 'iksk_uraian' => $uraianIksk[$b['kode_iksk']] ?? null, 'iksk_asal' => $b['asal_iksk'],
                'dikosongkan' => $b['dikosongkan'],
                'leading' => $b['leading'], 'leading_nama' => MasterKode::leading($b['leading'])['nama_unit'] ?? null,
                'pagu' => array_column($b['sumber_dana'], 'pagu', 'sumber'),
                'bulan' => JadwalPelaksanaan::teksBulan($b['bulan'], $tahun),
            ],
            'tampil' => [
                'nama' => $b['nama'] ?: '—',
                'kode' => implode(' · ', array_filter([$b['kode_sk'], $b['kode_iksk']])) ?: '—',
                // Kode saja: tabel pratinjau Perkin sudah lebar (kode SK · IKSK).
                'leading' => $b['leading'] ?? '—',
                'pagu' => $b['sumber_dana'] === [] ? '—' : implode(' · ', array_map(fn ($s) => $s['sumber'].' '.Format::rupiah($s['pagu']), $b['sumber_dana'])),
                'bulan' => JadwalPelaksanaan::teksBulan($b['bulan'], $tahun) ?? '—',
            ],
            'status' => $b['status'],
            'pesan' => $b['pesan'],
            'peringatan' => $b['peringatan'],
            'data' => PemeriksaPerkin::dataKegiatan($b),
        ], $diperiksa);
    }

    /** Langkah konfirmasi sebelum draf dibuat: ringkasan yang akan diimpor (konfirmasiImpor()). */
    public function mintaKonfirmasi(): void
    {
        $this->konfirmasiBatal = false;
        $this->konfirmasiImpor = $this->tahap === 'periksa' && $this->jumlah('siap') > 0;
    }

    public function batalKonfirmasi(): void
    {
        $this->konfirmasiImpor = false;
    }

    /** Impor hanya berjalan setelah dikonfirmasi. */
    public function mulaiImpor(): void
    {
        if (! $this->konfirmasiImpor) {
            return;
        }
        $this->konfirmasiImpor = false;
        parent::mulaiImpor();
    }

    /** Batalkan impor dari pratinjau: bila ada baris siap, minta konfirmasi dulu; lalu pratinjau dibuang. */
    public function batalkan(): void
    {
        if ($this->tahap !== 'periksa') {
            return;
        }
        if ($this->jumlah('siap') > 0 && ! $this->konfirmasiBatal) {
            $this->konfirmasiImpor = false;
            $this->konfirmasiBatal = true;

            return;
        }

        $this->ulangi();
        $this->dispatch('notify', message: 'Impor dibatalkan — tidak ada kegiatan yang dibuat.', type: 'info');
    }

    public function lanjutkanPratinjau(): void
    {
        $this->konfirmasiBatal = false;
    }

    /**
     * Ringkasan untuk konfirmasi impor: draf yang dibuat (jumlah & pagu), baris yang tidak diimpor, dan draf yang kodenya
     * perlu dipilih ulang.
     *
     * @return array{kegiatan: int, pagu: array<string, int>, dilewati: int, gagal: int, kode_kosong: int}
     */
    public function ringkasanKonfirmasi(): array
    {
        $kodeKosong = count(array_filter($this->baris, fn (array $b) => $b['status'] === 'siap' && ($b['perkin']['sk'] === null || $b['perkin']['iksk'] === null)));

        return $this->jumlahSiap() + ['dilewati' => $this->jumlah('dilewati'), 'gagal' => $this->jumlah('gagal'), 'kode_kosong' => $kodeKosong];
    }

    protected function simpanBaris(array $data): array
    {
        $repo = app(KegiatanRepository::class);
        $tahun = StubData::tahunAktif()['tahun'];

        // Periksa ulang: kegiatan bernama sama bisa saja dibuat di tab lain selama pratinjau terbuka.
        $kunci = PemeriksaImporKegiatan::kunciNama($data['nama_kegiatan']);
        foreach ($repo->semua() as $kegiatan) {
            if (TahunKegiatan::dari($kegiatan) === $tahun && PemeriksaImporKegiatan::kunciNama((string) ($kegiatan['nama_kegiatan'] ?? '')) === $kunci) {
                return ['status' => 'dilewati', 'pesan' => "Kegiatan dengan nama ini sudah ada di TA {$tahun}."];
            }
        }

        $id = $repo->simpan($data + [
            'riwayat_status' => [RiwayatStatus::entri('dibuat', StatusKegiatan::Draf, StubData::currentUser(), "Diimpor dari cascade Perkin ({$this->namaBerkas}, sheet {$this->lembar}).")],
        ]);

        return ['status' => 'berhasil', 'pesan' => 'Dibuat sebagai Draf.', 'tautan' => route('kegiatan.edit', ['kegiatan' => $id])];
    }

    protected function selesaiDiimpor(int $berhasil): void
    {
        $this->dispatch('kegiatan-diimpor', jumlah: $berhasil);
    }

    /**
     * Susunan cascade baris yang siap untuk pratinjau.
     *
     * @return list<array<string, mixed>>
     */
    public function cascade(): array
    {
        return PemeriksaPerkin::cascade(array_map(fn (array $b) => ['status' => $b['status']] + $b['data'], $this->baris));
    }

    /**
     * Jumlah baris siap beserta pagu per sumber dana (baris jumlah tabel pratinjau).
     *
     * @return array{kegiatan: int, pagu: array<string, int>}
     */
    public function jumlahSiap(): array
    {
        $siap = array_filter($this->baris, fn (array $b) => $b['status'] === 'siap');
        $pagu = [];
        foreach ($siap as $b) {
            foreach ($b['perkin']['pagu'] as $sumber => $nilai) {
                $pagu[$sumber] = ($pagu[$sumber] ?? 0) + $nilai;
            }
        }

        return ['kegiatan' => count($siap), 'pagu' => $pagu];
    }

    public function ulangi(): void
    {
        parent::ulangi();
        $this->reset('lembar', 'kolomHilang', 'konfirmasiImpor', 'konfirmasiBatal');
    }

    protected function labelBaris(array $baris): string
    {
        return $baris['tampil']['nama'];
    }

    protected function namaLaporan(): string
    {
        return 'laporan-impor-perkin';
    }

    public function render()
    {
        return view('livewire.impor.bertahap', [
            'judul' => 'Unggah sheet cascade Perkin',
            'keterangan' => 'Berkas Excel berisi sheet Perkin (mis. MASTER '.StubData::tahunAktif()['tahun'].'.xlsx). Setiap baris kegiatan menjadi draf lengkap dengan kode SK & IKSK, leading, pagu, dan bulan pelaksanaan.',
            'satuan' => 'kegiatan',
            'tabelPratinjau' => 'livewire.kegiatan.partials.tabel-perkin',
            'pratinjauTambahan' => 'livewire.kegiatan.partials.cascade-perkin',
            'aksiPeriksa' => 'livewire.kegiatan.partials.aksi-impor-perkin',
            'jagaHalaman' => true,
        ]);
    }
}
