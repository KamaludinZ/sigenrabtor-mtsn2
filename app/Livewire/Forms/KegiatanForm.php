<?php

namespace App\Livewire\Forms;

use App\Rules\KalimatUtuh;
use App\Support\Teks;
use Illuminate\Support\Str;
use Livewire\Form;

/**
 * Seluruh isian form Data Kegiatan beserta aturan validasinya.
 *
 * Draf boleh belum lengkap: hanya identitas minimum yang wajib. Kelengkapan dokumen
 * (tujuan, sasaran, dst.) diperiksa terpisah saat pratinjau/cetak.
 */
class KegiatanForm extends Form
{
    /** Isian uraian (teks panjang) yang spasinya dirapikan otomatis. */
    public const URAIAN = ['latar_belakang', 'tujuan', 'sasaran'];

    // Identitas Kegiatan
    public string $nama_kegiatan = '';

    public string $nama_kegiatan_cover = '';

    /** True bila nama cover diubah manual sehingga tidak lagi mengikuti nama kegiatan. */
    public bool $cover_manual = false;

    public string $latar_belakang = '';

    public string $tujuan = '';

    public string $sasaran = '';

    public ?string $tanggal_dokumen = null;

    public ?string $tanggal_laporan = null;

    // Pelaksana & Panitia
    /** @var list<array{key: string, nama: string, jabatan: string, peran: string}> */
    public array $panitia = [];

    /** Batas jumlah anggota tim agar tabel KAK tetap wajar. */
    public const MAKS_PANITIA = 50;

    /** Saran peran tim; isian tetap bebas. */
    public const PERAN_PANITIA = [
        'Ketua', 'Wakil Ketua', 'Sekretaris', 'Bendahara', 'Koordinator', 'Anggota', 'Narasumber', 'Pengarah',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'nama_kegiatan' => ['required', 'string', 'min:5', 'max:255'],
            'nama_kegiatan_cover' => ['required', 'string', 'max:255'],
            'latar_belakang' => ['nullable', 'string', 'max:5000', new KalimatUtuh],
            'tujuan' => ['nullable', 'string', 'max:2000', new KalimatUtuh],
            'sasaran' => ['nullable', 'string', 'max:2000', new KalimatUtuh],
            'tanggal_dokumen' => ['nullable', 'date'],
            'tanggal_laporan' => ['nullable', 'date', 'after_or_equal:tanggal_dokumen'],
            'panitia' => ['array', 'max:'.self::MAKS_PANITIA],
            'panitia.*.nama' => ['required', 'string', 'max:150'],
            'panitia.*.jabatan' => ['nullable', 'string', 'max:150'],
            'panitia.*.peran' => ['required', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'nama_kegiatan' => 'nama kegiatan',
            'nama_kegiatan_cover' => 'nama kegiatan (cover)',
            'latar_belakang' => 'latar belakang',
            'tujuan' => 'tujuan',
            'sasaran' => 'sasaran',
            'tanggal_dokumen' => 'tanggal dokumen',
            'tanggal_laporan' => 'tanggal pelaporan',
            'panitia.*.nama' => 'nama anggota',
            'panitia.*.jabatan' => 'jabatan anggota',
            'panitia.*.peran' => 'peran anggota',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'required' => 'Kolom :attribute wajib diisi.',
            'min' => 'Kolom :attribute minimal :min karakter.',
            'max' => 'Kolom :attribute maksimal :max karakter.',
            'date' => 'Kolom :attribute harus berupa tanggal yang valid.',
            'tanggal_laporan.after_or_equal' => 'Tanggal pelaporan tidak boleh sebelum tanggal dokumen.',
            'panitia.max' => 'Susunan tim maksimal :max orang.',
        ];
    }

    public function tambahPanitia(string $peran = 'Anggota', string $nama = '', string $jabatan = ''): bool
    {
        if (count($this->panitia) >= self::MAKS_PANITIA) {
            return false;
        }

        $this->panitia[] = ['key' => (string) Str::uuid(), 'nama' => $nama, 'jabatan' => $jabatan, 'peran' => $peran];

        return true;
    }

    public function hapusPanitia(int $index): void
    {
        unset($this->panitia[$index]);
        $this->panitia = array_values($this->panitia);
    }

    /**
     * Geser anggota ke atas (-1) atau ke bawah (+1); urutan ini yang tercetak di KAK.
     */
    public function geserPanitia(int $index, int $arah): void
    {
        $tujuan = $index + $arah;

        if (! isset($this->panitia[$index], $this->panitia[$tujuan])) {
            return;
        }

        [$this->panitia[$index], $this->panitia[$tujuan]] = [$this->panitia[$tujuan], $this->panitia[$index]];
    }

    /**
     * Susunan baku panitia kegiatan madrasah; hanya dipakai saat daftar masih kosong.
     */
    public function isiSusunanBaku(): void
    {
        if ($this->panitia !== []) {
            return;
        }

        foreach (['Ketua', 'Sekretaris', 'Bendahara', 'Anggota'] as $peran) {
            $this->tambahPanitia($peran);
        }
    }

    public function rapikanUraian(string $field): void
    {
        if (in_array($field, self::URAIAN, true)) {
            $this->{$field} = Teks::rapikan($this->{$field});
        }
    }

    /**
     * Nama cover selalu kapital dan mengikuti nama kegiatan sampai diubah manual.
     */
    public function syncNamaCover(): void
    {
        if (! $this->cover_manual) {
            $this->nama_kegiatan_cover = mb_strtoupper(trim($this->nama_kegiatan));
        }
    }

    public function setNamaCoverManual(): void
    {
        $this->nama_kegiatan_cover = mb_strtoupper($this->nama_kegiatan_cover);
        $this->cover_manual = $this->nama_kegiatan_cover !== mb_strtoupper(trim($this->nama_kegiatan));
    }

    public function resetNamaCover(): void
    {
        $this->cover_manual = false;
        $this->syncNamaCover();
    }

    /**
     * Peringatan non-blok: teks yang akan menimbulkan pengulangan kata di dokumen,
     * karena template KAK sudah menambahkan kata pembukanya sendiri.
     *
     * @return array<string, string>
     */
    public function peringatan(): array
    {
        $cek = [
            'nama_kegiatan' => ['kegiatan', 'Template sudah menulis “kegiatan …”; awalan “Kegiatan” akan tercetak dobel.'],
            'tujuan' => ['bertujuan', 'Template sudah menulis “Kegiatan … bertujuan …”; hapus kata “bertujuan” di awal.'],
            'sasaran' => ['terwujudnya', 'Template sudah menulis “Terwujudnya …”; hapus kata “Terwujudnya” di awal.'],
        ];

        $peringatan = [];

        foreach ($cek as $field => [$awalan, $pesan]) {
            $kataPertama = mb_strtolower(strtok(trim($this->{$field}), " \t\n") ?: '');

            if ($kataPertama === $awalan) {
                $peringatan[$field] = $pesan;
            }
        }

        return $peringatan;
    }
}
