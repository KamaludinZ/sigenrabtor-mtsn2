// Unduh KAK/TOR sebagai Word (.docx) yang disusun di peramban (PRD §6 Cetak & Unduh "Word (.docx)"): data pratinjau
// diambil dari `kegiatan.pratinjau.data` (lembar cover & KAK/TOR), lalu penyusun `word-kak.js` — dimuat terpisah saat
// tombol pertama kali ditekan — membuat berkasnya; nama = nama berkas baku dengan akhiran .docx. Gagal → pesan + Coba lagi.
import { simpanBerkas, ukuranBerkas } from './unduh-pdf';
import { GalatUnduhan, ambil, beritahu, laporkan, muatPustaka } from './unduhan';

// "KAK-TOR_…_2026_DRAF.pdf" → "KAK-TOR_…_2026_DRAF.docx".
export function namaWord(nama) {
    return String(nama ?? 'KAK-TOR').replace(/\.pdf$/i, '') + '.docx';
}

export default function unduhWord(urlData, nama) {
    return {
        keadaan: 'siap', // siap | memuat | selesai | gagal
        pesan: '',
        jenisGalat: null,

        get memuat() {
            return this.keadaan === 'memuat';
        },

        async unduh() {
            if (this.memuat) {
                return;
            }
            this.keadaan = 'memuat';
            this.pesan = '';
            this.jenisGalat = null;
            try {
                const [penyusun, respons] = await Promise.all([muatPustaka(() => import('./word-kak')), ambil(urlData)]);
                const data = await respons.json();
                const berkas = await penyusun.susunWord(data).catch(() => {
                    throw new GalatUnduhan('susun');
                });
                const namaBerkas = namaWord(data.cetak?.nama_berkas ?? nama);
                simpanBerkas(berkas, namaBerkas);
                this.keadaan = 'selesai';
                beritahu(`${namaBerkas} diunduh (${ukuranBerkas(berkas.size)}).`);
                window.setTimeout(() => {
                    if (this.keadaan === 'selesai') {
                        this.keadaan = 'siap';
                    }
                }, 2500);
            } catch (galat) {
                const g = laporkan(galat, 'Unduhan Word');
                this.keadaan = 'gagal';
                this.pesan = g.message;
                this.jenisGalat = g.jenis;
            }
        },
    };
}
