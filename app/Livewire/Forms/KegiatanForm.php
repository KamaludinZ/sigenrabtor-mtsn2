<?php

namespace App\Livewire\Forms;

use App\Rules\KalimatUtuh;
use Livewire\Form;

/**
 * Seluruh isian form Data Kegiatan beserta aturan validasinya.
 *
 * Draf boleh belum lengkap: hanya identitas minimum yang wajib. Kelengkapan dokumen
 * (tujuan, sasaran, dst.) diperiksa terpisah saat pratinjau/cetak.
 */
class KegiatanForm extends Form
{
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
        ];
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
