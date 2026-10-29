// Unduh PDF langsung dari server tanpa meninggalkan halaman (PRD §6 Cetak & Unduh): berkas diambil lewat `ambil()`
// (unduhan.js: batas waktu, luring, `Accept: application/pdf, application/json` — server menjawab JSON {pesan,
// tautan_cetak} bila gagal disusun),
// kemajuan dibaca dari aliran respons (Content-Length), lalu disimpan dengan nama berkas baku dari header
// X-Nama-Berkas/Content-Disposition (cadangan: nama dari server saat halaman dirender). Bisa dibatalkan; gagal →
// pesan + jalan keluar lewat halaman cetak ("Simpan sebagai PDF").

import { GalatUnduhan, ambil, beritahu, laporkan } from './unduhan';

const KB = 1024;

// Ukuran berkas terbaca: "245 KB", "1,2 MB" (tanpa locale peramban, sama gaya ArsipFinal::ukuran).
export function ukuranBerkas(bita) {
    if (!Number.isFinite(bita) || bita <= 0) {
        return '';
    }
    if (bita < KB * KB) {
        return `${Math.max(1, Math.round(bita / KB))} KB`;
    }

    return `${(bita / (KB * KB)).toFixed(1).replace('.', ',')} MB`;
}

// Nama berkas dari header respons; null bila tidak ada.
export function namaDariRespons(respons) {
    const langsung = respons.headers.get('X-Nama-Berkas');
    if (langsung) {
        return langsung;
    }
    const disposisi = respons.headers.get('Content-Disposition') ?? '';
    const cocok = disposisi.match(/filename\*?=(?:UTF-8'')?"?([^";]+)"?/i);

    return cocok ? decodeURIComponent(cocok[1]) : null;
}

// Simpan blob sebagai berkas unduhan bernama `nama` (tautan sementara, alamat objek dilepas sesudahnya).
export function simpanBerkas(berkas, nama) {
    const alamat = URL.createObjectURL(berkas);
    const tautan = document.createElement('a');
    tautan.href = alamat;
    tautan.download = nama;
    tautan.rel = 'noopener';
    document.body.appendChild(tautan);
    tautan.click();
    tautan.remove();
    window.setTimeout(() => URL.revokeObjectURL(alamat), 10000);
}

export default function unduhPdf(url, nama, tautanCetak = null) {
    return {
        keadaan: 'siap', // siap | memuat | selesai | gagal
        persen: null,
        diterima: '',
        pesan: '',
        jenisGalat: null,
        tautanCetak,
        pengendali: null,

        get memuat() {
            return this.keadaan === 'memuat';
        },

        async unduh() {
            if (this.memuat) {
                return;
            }
            this.keadaan = 'memuat';
            this.persen = null;
            this.diterima = '';
            this.pesan = '';
            this.jenisGalat = null;
            this.pengendali = new AbortController();
            try {
                const respons = await ambil(url, { accept: 'application/pdf, application/json', jenisBerkas: 'application/pdf', sinyal: this.pengendali.signal });
                const berkas = await this.baca(respons);
                if (berkas.size === 0) {
                    throw new GalatUnduhan('berkas');
                }
                const namaBerkas = namaDariRespons(respons) ?? nama;
                this.simpan(berkas, namaBerkas);
                this.keadaan = 'selesai';
                beritahu(`${namaBerkas} diunduh (${ukuranBerkas(berkas.size)}).`);
                window.setTimeout(() => {
                    if (this.keadaan === 'selesai') {
                        this.keadaan = 'siap';
                    }
                }, 2500);
            } catch (galat) {
                const g = laporkan(galat, 'Unduhan PDF');
                if (g.jenis === 'batal') {
                    this.keadaan = 'siap';
                    beritahu('Unduhan PDF dibatalkan.', 'info');

                    return;
                }
                this.keadaan = 'gagal';
                this.pesan = g.message;
                this.jenisGalat = g.jenis;
                this.tautanCetak = g.tautanCetak ?? this.tautanCetak;
            } finally {
                this.pengendali = null;
            }
        },

        // Baca aliran respons sambil menghitung kemajuan (persen bila server mengirim Content-Length).
        async baca(respons) {
            const total = Number(respons.headers.get('Content-Length')) || 0;
            if (!respons.body || !respons.body.getReader) {
                return new Blob([await respons.arrayBuffer()], { type: 'application/pdf' });
            }
            const pembaca = respons.body.getReader();
            const potongan = [];
            let jumlah = 0;
            for (;;) {
                const { done, value } = await pembaca.read();
                if (done) {
                    break;
                }
                potongan.push(value);
                jumlah += value.length;
                this.diterima = ukuranBerkas(jumlah);
                this.persen = total > 0 ? Math.min(100, Math.round((jumlah / total) * 100)) : null;
            }

            return new Blob(potongan, { type: 'application/pdf' });
        },

        simpan(berkas, namaBerkas) {
            simpanBerkas(berkas, namaBerkas);
        },

        batal() {
            this.pengendali?.abort();
        },
    };
}
