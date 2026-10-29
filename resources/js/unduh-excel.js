// Unduh RAB satu sumber dana sebagai Excel (.xlsx) yang disusun di peramban (PRD §6 "Unduh Excel — rincian RAB"):
// susunan dokumen dari `kegiatan.pratinjau.data?dokumen=rab&sumber=` dan angka rincian dari `kegiatan.rab_lengkap`
// diambil bersamaan, lalu penyusun `excel-rab.js` — dimuat terpisah saat pertama kali ditekan — membuat bukunya; nama =
// nama berkas baku RAB berakhiran .xlsx. Gagal → pesan + Coba lagi + unduhan cadangan dari server (EksporRabExcel).
import { simpanBerkas, ukuranBerkas } from './unduh-pdf';

// "RAB_…_DIPA_2026_DRAF.pdf" → "RAB_…_DIPA_2026_DRAF.xlsx".
export function namaExcel(nama) {
    return String(nama ?? 'RAB').replace(/\.(pdf|xlsx)$/i, '') + '.xlsx';
}

async function ambilJson(url) {
    const respons = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
    if (!respons.ok) {
        const isi = await respons.json().catch(() => ({}));
        throw new Error(isi.message ?? isi.pesan ?? (respons.status === 403 ? 'Anda tidak berhak mengunduh dokumen ini.' : 'Data RAB belum bisa dimuat — coba lagi.'));
    }

    return respons.json();
}

export default function unduhExcel(urlDokumen, urlRab, sumber, nama, urlServer = null) {
    return {
        keadaan: 'siap', // siap | memuat | selesai | gagal
        pesan: '',
        urlServer,

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
                const [penyusun, dokumen, rab] = await Promise.all([import('./excel-rab'), ambilJson(urlDokumen), ambilJson(urlRab)]);
                if (!dokumen.dokumen?.rab) {
                    throw new Error('RAB sumber dana ini belum bisa disusun.');
                }
                const berkas = await penyusun.susunExcel(dokumen, rab, sumber);
                simpanBerkas(berkas, namaExcel(dokumen.cetak?.nama_berkas ?? nama));
                this.keadaan = 'selesai';
                window.dispatchEvent(new CustomEvent('notify', { detail: { message: `Excel RAB ${sumber} diunduh (${ukuranBerkas(berkas.size)}).`, type: 'success' } }));
                window.setTimeout(() => {
                    if (this.keadaan === 'selesai') {
                        this.keadaan = 'siap';
                    }
                }, 2500);
            } catch (galat) {
                this.keadaan = 'gagal';
                this.pesan = galat.message || 'Excel gagal disusun.';
            }
        },
    };
}
