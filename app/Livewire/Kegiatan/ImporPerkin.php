<?php

namespace App\Livewire\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Livewire\Impor\ImporBertahap;
use App\Support\Format;
use App\Support\Kegiatan\Impor\PembacaPerkin;
use App\Support\Kegiatan\Impor\PemeriksaPerkin;
use App\Support\Kegiatan\JadwalPelaksanaan;
use App\Support\Kegiatan\TahunKegiatan;
use App\Support\MasterKode;
use App\Support\Stub\StubData;
use Livewire\Attributes\Locked;
use LogicException;

/**
 * Impor draf kegiatan massal dari sheet cascade Perkin (PRD §19, tab Perkin halaman Duplikat & Impor): unggah berkas →
 * pratinjau per baris (PemeriksaPerkin: isian kegiatan + kode SK/IKSK terhadap master tahun aktif) beserta susunan
 * cascade SK → IKSK. Proses impor menyusul.
 */
class ImporPerkin extends ImporBertahap
{
    /** Sheet yang dibaca dari berkas terakhir. */
    #[Locked]
    public string $lembar = '';

    /** @var list<string> Label kolom opsional yang tidak ada di judul sheet. */
    #[Locked]
    public array $kolomHilang = [];

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

        return array_map(fn (array $b) => [
            'baris' => $b['baris'],
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

    /** Proses impor cascade belum tersedia (tombol Segera). */
    public function mulaiImpor(): void {}

    protected function simpanBaris(array $data): array
    {
        throw new LogicException('Proses impor cascade Perkin belum tersedia.');
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

    public function ulangi(): void
    {
        parent::ulangi();
        $this->reset('lembar', 'kolomHilang');
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
            'pratinjauTambahan' => 'livewire.kegiatan.partials.cascade-perkin',
            'prosesSegera' => true,
        ]);
    }
}
