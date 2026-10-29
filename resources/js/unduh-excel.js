// Unduh RAB satu sumber dana sebagai Excel (.xlsx) yang disusun di peramban (PRD §6 "Unduh Excel — rincian RAB"):
// susunan dokumen dari `kegiatan.pratinjau.data?dokumen=rab&sumber=` dan angka rincian dari `kegiatan.rab_lengkap`
// diambil bersamaan, lalu penyusun `excel-rab.js` — dimuat terpisah saat pertama kali ditekan — membuat bukunya; nama =
// nama berkas baku RAB berakhiran .xlsx. Gagal → pesan + Coba lagi + unduhan cadangan dari server (EksporRabExcel).
import { simpanBerkas, ukuranBerkas } from './unduh-pdf';
import { GalatUnduhan, ambil, beritahu, laporkan, muatPustaka } from './unduhan';

// "RAB_…_DIPA_2026_DRAF.pdf" → "RAB_…_DIPA_2026_DRAF.xlsx".
export function namaExcel(nama) {
    return String(nama ?? 'RAB').replace(/\.(pdf|xlsx)$/i, '') + '.xlsx';
}

export default function unduhExcel(urlDokumen, urlRab, sumber, nama, urlServer = null) {
    return {
        keadaan: 'siap', // siap | memuat | selesai | gagal
        pesan: '',
        jenisGalat: null,
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
            this.jenisGalat = null;
            try {
                const [penyusun, dokumen, rab] = await Promise.all([
                    muatPustaka(() => import('./excel-rab')),
                    ambil(urlDokumen).then((r) => r.json()),
                    ambil(urlRab).then((r) => r.json()),
                ]);
                if (!dokumen.dokumen?.rab) {
                    throw new GalatUnduhan('tidak_ada', 'RAB sumber dana ini belum bisa disusun — pilih sumber dana kegiatan dulu.');
                }
                const berkas = await penyusun.susunExcel(dokumen, rab, sumber).catch(() => {
                    throw new GalatUnduhan('susun');
                });
                const namaBerkas = namaExcel(dokumen.cetak?.nama_berkas ?? nama);
                simpanBerkas(berkas, namaBerkas);
                this.keadaan = 'selesai';
                beritahu(`${namaBerkas} diunduh (${ukuranBerkas(berkas.size)}).`);
                window.setTimeout(() => {
                    if (this.keadaan === 'selesai') {
                        this.keadaan = 'siap';
                    }
                }, 2500);
            } catch (galat) {
                const g = laporkan(galat, 'Unduhan Excel');
                this.keadaan = 'gagal';
                this.pesan = g.message;
                this.jenisGalat = g.jenis;
            }
        },
    };
}
