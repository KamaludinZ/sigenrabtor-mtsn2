<?php

namespace App\Livewire\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Livewire\Impor\ImporBertahap;
use App\Support\Format;
use App\Support\Kegiatan\Impor\PembacaImporKegiatan;
use App\Support\Kegiatan\Impor\PemeriksaImporKegiatan;
use App\Support\Kegiatan\JadwalPelaksanaan;
use App\Support\MasterKode;
use App\Support\Stub\StubData;

/**
 * Impor kegiatan massal dari template Excel (nama kegiatan + leading, pagu DIPA/Komite, bulan) menjadi kegiatan Draf.
 */
class ImporKegiatan extends ImporBertahap
{
    public function kolom(): array
    {
        return ['nama' => 'Nama kegiatan', 'leading' => 'Leading', 'pagu' => 'Pagu', 'bulan' => 'Bulan'];
    }

    protected function periksaBerkas(string $path, string $namaAsli): array
    {
        $pengguna = StubData::currentUser();
        $leadings = MasterKode::leadingsUntuk($pengguna);
        $tahun = StubData::tahunAktif()['tahun'];

        $diperiksa = PemeriksaImporKegiatan::periksa(
            PembacaImporKegiatan::baca($path, $namaAsli),
            array_column(app(KegiatanRepository::class)->semua(), 'nama_kegiatan'),
            $leadings,
            MasterKode::leadings(),
            // Akun leading dengan satu leading: kolom Leading boleh dikosongkan.
            count($leadings) === 1 ? $leadings[0]['kode'] : null,
        );

        return array_map(fn (array $b) => [
            'baris' => $b['baris'],
            'tampil' => [
                'nama' => $b['nama'] ?: '—',
                'leading' => $b['leading_label'] ?? '—',
                'pagu' => $b['sumber_dana'] === [] ? '—' : implode(' · ', array_map(fn ($s) => $s['sumber'].' '.Format::rupiah($s['pagu']), $b['sumber_dana'])),
                'bulan' => JadwalPelaksanaan::teksBulan($b['bulan'], $tahun) ?? '—',
            ],
            'status' => $b['status'],
            'pesan' => $b['pesan'],
            'peringatan' => $b['peringatan'],
            'data' => PemeriksaImporKegiatan::dataKegiatan($b),
        ], $diperiksa);
    }

    protected function simpanBaris(array $data): array
    {
        $repo = app(KegiatanRepository::class);

        // Periksa ulang: kegiatan bernama sama bisa saja dibuat di tab lain selama pratinjau terbuka.
        $kunci = PemeriksaImporKegiatan::kunciNama($data['nama_kegiatan']);
        foreach ($repo->semua() as $kegiatan) {
            if (PemeriksaImporKegiatan::kunciNama((string) ($kegiatan['nama_kegiatan'] ?? '')) === $kunci) {
                return ['status' => 'dilewati', 'pesan' => 'Kegiatan dengan nama ini sudah ada di aplikasi.'];
            }
        }

        $id = $repo->simpan($data);

        return ['status' => 'berhasil', 'pesan' => 'Dibuat sebagai Draf.', 'tautan' => route('kegiatan.edit', ['kegiatan' => $id])];
    }

    protected function labelBaris(array $baris): string
    {
        return $baris['tampil']['nama'];
    }

    protected function selesaiDiimpor(int $berhasil): void
    {
        $this->dispatch('kegiatan-diimpor', jumlah: $berhasil);
    }

    protected function namaLaporan(): string
    {
        return 'laporan-impor-kegiatan';
    }

    public function render()
    {
        return view('livewire.impor.bertahap', [
            'judul' => 'Impor kegiatan dari Excel',
            'keterangan' => 'Satu baris = satu kegiatan Draf. Nama yang sudah ada dilewati, jadi berkas yang sama aman diimpor ulang setelah baris gagal diperbaiki.',
            'tautanTemplate' => route('kegiatan.template'),
            'satuan' => 'kegiatan',
        ]);
    }
}
