// Penyusun buku Excel (.xlsx) RAB satu sumber dana di peramban, kembaran ekspor server EksporRabExcel: judul, baris DRAF
// + alasan selama belum siap, kepala (label : nilai — uraian), tabel rincian No/(Kode)/Uraian/Vol/Satuan/Harga/Jumlah
// dengan angka asli & rumus (Jumlah = ROUND(Vol × Harga), TOTAL = SUM) agar lembar tetap terhitung bila disunting,
// terbilang, Mekanisme Pembayaran, dan blok pengesahan; A4 tegak, muat selebar halaman, kepala tabel diulang tiap halaman.
// Data: `kegiatan.pratinjau.data?dokumen=rab` (susunan dokumen) + `kegiatan.rab_lengkap` (angka rincian). Dimuat terpisah
// (import dinamis) hanya saat tombol Excel ditekan — pustaka exceljs besar.
import ExcelJS from 'exceljs';

const FORMAT_UANG = '#,##0';
const FORMAT_VOLUME = '#,##0.##';
const GARIS = { style: 'thin', color: { argb: 'FF57534E' } };
const BINGKAI = { top: GARIS, left: GARIS, bottom: GARIS, right: GARIS };
const MERAH = 'FFB91C1C';

// Kolom tabel: judul (TabelRab) → [lebar karakter, kunci rincian, perataan, format].
const KOLOM = {
    No: [5, 'nomor', 'center', '0'],
    Kode: [11, 'kode', 'left', '@'],
    Uraian: [44, 'uraian', 'left', null],
    Vol: [8, 'volume', 'right', FORMAT_VOLUME],
    Satuan: [12, 'satuan', 'left', null],
    'Harga Satuan (Rp)': [16, 'harga_satuan', 'right', FORMAT_UANG],
    'Jumlah (Rp)': [18, 'jumlah', 'right', FORMAT_UANG],
};

const huruf = (n) => String.fromCharCode(64 + n);

// Tinggi baris (poin) untuk teks terlipat di sel gabungan selebar `lebar` karakter — Excel & LibreOffice tidak
// menyesuaikan tinggi sel gabungan sendiri.
export function tinggiBaris(teks, lebar) {
    const muat = Math.max(1, Math.floor(lebar * 1.05) - 1);
    const baris = String(teks ?? '').split('\n').reduce((jumlah, b) => jumlah + Math.max(1, Math.ceil(b.length / muat)), 0);

    return Math.max(15, baris * 14.5);
}

// Bagi kolom menjadi tiga rentang selebar mungkin sama untuk blok pengesahan.
export function bagiTiga(lebar) {
    const total = lebar.reduce((a, b) => a + b, 0);
    const batas = [total / 3, (2 * total) / 3];
    const rentang = [];
    let mulai = 1;
    let jalan = 0;
    lebar.forEach((l, i) => {
        jalan += l;
        if (rentang.length < 2 && jalan >= batas[rentang.length] && i + 1 < lebar.length) {
            rentang.push([mulai, i + 1]);
            mulai = i + 2;
        }
    });
    rentang.push([mulai, lebar.length]);

    return rentang;
}

export function susunBuku(dokumen, rabLengkap, sumber) {
    const rab = dokumen.dokumen.rab;
    const angka = (rabLengkap.sumber ?? []).find((s) => s.sumber === sumber) ?? { rincian: [], total: 0 };
    const kolom = rab.tabel.kolom.filter((k) => KOLOM[k]);
    const n = kolom.length;
    const buku = new ExcelJS.Workbook();
    buku.creator = 'SIRABTOR';
    buku.title = `RAB ${dokumen.nama_kegiatan ?? ''}`.trim();
    const lembar = buku.addWorksheet(`RAB ${sumber}`.slice(0, 31), {
        pageSetup: { paperSize: 9, orientation: 'portrait', fitToPage: true, fitToWidth: 1, fitToHeight: 0, margins: { left: 0.6, right: 0.6, top: 0.7, bottom: 0.7, header: 0.3, footer: 0.3 } },
        headerFooter: { oddFooter: `${rab.draf ? `&L&KB91C1CDRAF — ${(rab.alasan[0] ?? '').replace(/&/g, '&&')}` : ''}&RHalaman &P dari &N` },
    });
    lembar.columns = kolom.map((k) => ({ width: KOLOM[k][0] }));
    const satuBaris = (nilai, gaya = {}) => {
        const baris = lembar.addRow([nilai]);
        lembar.mergeCells(baris.number, 1, baris.number, n);
        Object.assign(baris.getCell(1), gaya);

        return baris;
    };

    const lebarSemua = kolom.reduce((jumlah, k) => jumlah + KOLOM[k][0], 0);
    const teksJudul = String(rab.judul).toUpperCase();
    const judul = satuBaris(teksJudul, { font: { bold: true, size: 13 }, alignment: { horizontal: 'center', vertical: 'middle', wrapText: true } });
    judul.height = tinggiBaris(teksJudul, lebarSemua * 0.85) + 4;
    if (rab.draf) {
        const teksDraf = `DRAF — ${rab.alasan.join(' ')}`;
        satuBaris(teksDraf, { font: { bold: true, color: { argb: MERAH } }, alignment: { horizontal: 'center', wrapText: true } }).height = tinggiBaris(teksDraf, lebarSemua);
    }
    lembar.addRow([]);
    const batasLabel = Math.min(2, n - 1);
    const lebar = kolom.map((k) => KOLOM[k][0]);
    const lebarLabel = lebar.slice(0, batasLabel).reduce((a, b) => a + b, 0);
    const lebarNilai = lebar.slice(batasLabel).reduce((a, b) => a + b, 0);
    rab.kepala.forEach((k) => {
        const baris = lembar.addRow([]);
        lembar.mergeCells(baris.number, 1, baris.number, batasLabel);
        lembar.mergeCells(baris.number, batasLabel + 1, baris.number, n);
        const label = `${k.label} :`;
        const nilai = k.nilai === null ? '…' : `${k.nilai}${k.uraian ? ` — ${k.uraian}` : ''}`;
        baris.getCell(1).value = label;
        baris.getCell(1).alignment = { wrapText: true, vertical: 'top' };
        baris.getCell(batasLabel + 1).value = nilai;
        baris.getCell(batasLabel + 1).alignment = { wrapText: true, vertical: 'top' };
        baris.height = Math.max(tinggiBaris(label, lebarLabel), tinggiBaris(nilai, lebarNilai));
    });
    lembar.addRow([]);

    const kepala = lembar.addRow(kolom);
    kepala.eachCell((sel) => {
        sel.font = { bold: true };
        sel.alignment = { horizontal: 'center', vertical: 'middle', wrapText: true };
        sel.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFF5F5F4' } };
        sel.border = BINGKAI;
    });
    lembar.pageSetup.printTitlesRow = `${kepala.number}:${kepala.number}`;
    const kolomVol = huruf(kolom.indexOf('Vol') + 1);
    const kolomHarga = huruf(kolom.indexOf('Harga Satuan (Rp)') + 1);
    const kolomJumlah = huruf(kolom.indexOf('Jumlah (Rp)') + 1);
    const awal = kepala.number + 1;
    angka.rincian.forEach((r) => {
        const baris = lembar.addRow(kolom.map((k) => (KOLOM[k][1] === 'kode' ? String(r.kode ?? '') : r[KOLOM[k][1]])));
        kolom.forEach((k, i) => {
            const sel = baris.getCell(i + 1);
            const [, kunci, rata, format] = KOLOM[k];
            sel.border = BINGKAI;
            sel.alignment = { horizontal: rata, vertical: 'top', wrapText: kunci === 'uraian' || kunci === 'satuan' };
            if (format) {
                sel.numFmt = format;
            }
            if (kunci === 'jumlah') {
                sel.value = { formula: `ROUND(${kolomVol}${baris.number}*${kolomHarga}${baris.number},0)`, result: r.jumlah };
            }
        });
    });
    const akhir = awal + angka.rincian.length - 1;

    const total = lembar.addRow([]);
    if (n > 1) {
        lembar.mergeCells(total.number, 1, total.number, n - 1);
    }
    total.getCell(1).value = 'TOTAL KESELURUHAN';
    total.getCell(n).value = angka.rincian.length ? { formula: `SUM(${kolomJumlah}${awal}:${kolomJumlah}${akhir})`, result: angka.total } : 0;
    [1, n].forEach((i) => {
        const sel = total.getCell(i);
        sel.font = { bold: true };
        sel.border = BINGKAI;
        sel.alignment = { horizontal: 'right' };
    });
    total.getCell(n).numFmt = FORMAT_UANG;
    if (rab.tabel.terbilang) {
        const isi = `Terbilang: ${rab.tabel.terbilang}`;
        const baris = satuBaris(isi, { font: { italic: true }, alignment: { horizontal: 'right', wrapText: true }, border: BINGKAI });
        baris.height = tinggiBaris(isi, lebar.reduce((a, b) => a + b, 0));
    }

    if ((rab.mekanisme ?? []).length) {
        lembar.addRow([]);
        rab.mekanisme.forEach((m) => {
            const baris = lembar.addRow([]);
            lembar.mergeCells(baris.number, 1, baris.number, Math.max(1, n - 1));
            baris.getCell(1).value = `${m.label} :`;
            baris.getCell(n).value = m.nilai;
            baris.getCell(n).alignment = { horizontal: 'right' };
        });
    }

    lembar.addRow([]);
    const rentang = bagiTiga(kolom.map((k) => KOLOM[k][0]));
    const ttd = rab.pengesahan ?? [];
    const baris = (isi, gaya = {}) => {
        const b = lembar.addRow([]);
        rentang.forEach(([dari, sampai], i) => {
            if (sampai > dari) {
                lembar.mergeCells(b.number, dari, b.number, sampai);
            }
            const sel = b.getCell(dari);
            sel.value = isi(ttd[i] ?? {});
            sel.alignment = { horizontal: 'center', wrapText: true };
            Object.assign(sel, gaya);
        });

        return b;
    };
    baris((t) => t.tempat_tanggal ?? '');
    baris((t) => t.peran ?? '');
    baris((t) => t.jabatan ?? '', { font: { bold: true } }).height = 30;
    for (let i = 0; i < 3; i++) {
        lembar.addRow([]);
    }
    baris((t) => t.nama ?? '( …………………………… )', { font: { bold: true } });
    baris((t) => `NIP. ${t.nip ?? '……………………'}`);

    return buku;
}

export async function susunExcel(dokumen, rabLengkap, sumber) {
    const isi = await susunBuku(dokumen, rabLengkap, sumber).xlsx.writeBuffer();

    return new Blob([isi], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
}
