// Unduh KAK/TOR sebagai Word (.docx) yang disusun di peramban (PRD §6 Cetak & Unduh "Word (.docx)"): data pratinjau
// diambil dari `kegiatan.pratinjau.data` (lembar cover & KAK/TOR), lalu penyusun `word-kak.js` — dimuat terpisah saat
// tombol pertama kali ditekan — membuat berkasnya; nama = nama berkas baku dengan akhiran .docx. Gagal → pesan + Coba lagi.
import { simpanBerkas, ukuranBerkas } from './unduh-pdf';

// "KAK-TOR_…_2026_DRAF.pdf" → "KAK-TOR_…_2026_DRAF.docx".
export function namaWord(nama) {
    return String(nama ?? 'KAK-TOR').replace(/\.pdf$/i, '') + '.docx';
}

export default function unduhWord(urlData, nama) {
    return {
        keadaan: 'siap', // siap | memuat | selesai | gagal
        pesan: '',

        get memuat() {
            return this.keadaan === 'memuat';
        },

        async unduh() {
            if (this.memuat) {
                return;
            }
            this.keadaan = 'memuat';
            this.pesan = '';
            try {
                const [penyusun, respons] = await Promise.all([
                    import('./word-kak'),
                    fetch(urlData, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' }),
                ]);
                if (!respons.ok) {
                    const isi = await respons.json().catch(() => ({}));
                    throw new Error(isi.message ?? isi.pesan ?? (respons.status === 403 ? 'Anda tidak berhak mengunduh dokumen ini.' : 'Data dokumen belum bisa dimuat — coba lagi.'));
                }
                const data = await respons.json();
                const berkas = await penyusun.susunWord(data);
                simpanBerkas(berkas, namaWord(data.cetak?.nama_berkas ?? nama));
                this.keadaan = 'selesai';
                window.dispatchEvent(new CustomEvent('notify', { detail: { message: `Word KAK/TOR diunduh (${ukuranBerkas(berkas.size)}).`, type: 'success' } }));
                window.setTimeout(() => {
                    if (this.keadaan === 'selesai') {
                        this.keadaan = 'siap';
                    }
                }, 2500);
            } catch (galat) {
                this.keadaan = 'gagal';
                this.pesan = galat.message || 'Word gagal disusun.';
            }
        },
    };
}
