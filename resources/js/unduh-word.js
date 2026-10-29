// Unduh KAK/TOR sebagai Word (.docx) yang disusun di peramban (PRD §6 Cetak & Unduh "Word (.docx)"): data pratinjau
// diambil dari `kegiatan.pratinjau.data` (lembar cover & KAK/TOR), lalu penyusun `word-kak.js` — dimuat terpisah saat
// tombol pertama kali ditekan — membuat berkasnya; nama = nama berkas baku dengan akhiran .docx. Bila penyusun peramban
// gagal dimuat atau gagal menyusun (`pustaka`/`susun`) dan `urlServer` ada, berkas yang sama diambil dari server
// (`kegiatan.kak.word`, WordKakTor); bila server juga gagal, galat asal yang tampil. Kegiatan bermaster KAK/TOR terpilih
// (`data.template.kak`) selalu diunduh dari server (PengisiTemplateDocx). Gagal → pesan + Coba lagi.
import { simpanBerkas, ukuranBerkas } from './unduh-pdf';
import { GalatUnduhan, ambil, beritahu, golongkan, laporkan, muatPustaka } from './unduhan';

const MIME_WORD = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

// "KAK-TOR_…_2026_DRAF.pdf" → "KAK-TOR_…_2026_DRAF.docx".
export function namaWord(nama) {
    return String(nama ?? 'KAK-TOR').replace(/\.pdf$/i, '') + '.docx';
}

export default function unduhWord(urlData, nama, urlServer = null) {
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
                let berkas;
                let namaBerkas;
                let server = false;
                try {
                    [berkas, namaBerkas] = await this.susunPeramban();
                } catch (galat) {
                    const g = golongkan(galat);
                    if (!urlServer || !['pustaka', 'susun'].includes(g.jenis)) {
                        throw g;
                    }
                    [berkas, namaBerkas] = await this.ambilServer().catch(() => {
                        throw g;
                    });
                    server = true;
                }
                simpanBerkas(berkas, namaBerkas);
                this.keadaan = 'selesai';
                beritahu(`${namaBerkas} diunduh (${ukuranBerkas(berkas.size)})${server ? ' — disusun server' : ''}.`);
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

        // Susun di peramban: data pratinjau + pustaka docx (chunk terpisah).
        async susunPeramban() {
            const [penyusun, respons] = await Promise.all([muatPustaka(() => import('./word-kak')), ambil(urlData)]);
            const data = await respons.json();
            if (data.template?.kak && urlServer) {
                // Master KAK/TOR terpilih (Format Seragam): diisi server dari berkas master aslinya.
                return this.ambilServer();
            }
            const berkas = await penyusun.susunWord(data).catch(() => {
                throw new GalatUnduhan('susun');
            });

            return [berkas, namaWord(data.cetak?.nama_berkas ?? nama)];
        },

        // Cadangan: berkas .docx yang sama disusun server.
        async ambilServer() {
            const respons = await ambil(urlServer, { accept: `${MIME_WORD}, application/json`, jenisBerkas: MIME_WORD });
            const berkas = await respons.blob();

            return [berkas, respons.headers.get('X-Nama-Berkas') ?? namaWord(nama)];
        },
    };
}
