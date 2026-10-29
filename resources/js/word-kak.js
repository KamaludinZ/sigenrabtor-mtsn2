// Penyusun berkas Word (.docx) KAK/TOR di peramban dari data pratinjau (`kegiatan.pratinjau.data`, DataPratinjau): cover
// (kotak teks cover urut posisi), sembilan bagian KAK/TOR (paragraf rata kanan-kiri, subjudul, daftar bernomor, tabel
// matrik tahapan "v" per bulan, tabel jadwal, lembar pengesahan), dan RAB bila lembarnya diminta (kepala, tabel rincian,
// total & terbilang, mekanisme pembayaran, pengesahan). A4, Times New Roman 12 pt, kaki "Halaman x dari y" + tanda DRAF
// selama dokumen belum siap. Dimuat terpisah (import dinamis) hanya saat tombol Word ditekan — pustaka `docx` besar.
import {
    AlignmentType,
    BorderStyle,
    Document,
    Footer,
    Header,
    Packer,
    PageNumber,
    Paragraph,
    Table,
    TableCell,
    TableLayoutType,
    TableRow,
    TabStopType,
    TextRun,
    VerticalAlign,
    WidthType,
} from 'docx';

const FONT = 'Times New Roman';
const UKURAN = 24; // setengah poin: 12 pt
const CM = 567; // twip per cm
const LEBAR_ISI = 11906 - 2 * 2 * CM; // A4 dikurangi margin kiri & kanan 2 cm
const BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
const MERAH = 'B91C1C';
const GARIS = { style: BorderStyle.SINGLE, size: 4, color: '57534E' };
const TANPA_GARIS = { style: BorderStyle.NONE, size: 0, color: 'FFFFFF' };
const SEL_BULAN = { top: 40, bottom: 40, left: 15, right: 15 };
const RATA = { left: AlignmentType.LEFT, center: AlignmentType.CENTER, right: AlignmentType.RIGHT, kiri: AlignmentType.LEFT, tengah: AlignmentType.CENTER, kanan: AlignmentType.RIGHT };

const run = (teks, opsi = {}) => new TextRun({ text: String(teks ?? ''), font: FONT, size: UKURAN, ...opsi });

const paragraf = (isi, opsi = {}) => new Paragraph({
    children: Array.isArray(isi) ? isi : [run(isi)],
    spacing: { after: 120, line: 300 },
    ...opsi,
});

const sel = (isi, opsi = {}) => new TableCell({
    children: (Array.isArray(isi) ? isi : [isi]).map((p) => (typeof p === 'string' ? paragraf([run(p, { size: 20 })], { spacing: { after: 0 } }) : p)),
    margins: { top: 40, bottom: 40, left: 80, right: 80 },
    verticalAlign: VerticalAlign.TOP,
    ...opsi,
});

const garisTabel = (garis) => ({ top: garis, bottom: garis, left: garis, right: garis, insideHorizontal: garis, insideVertical: garis });

function tabel(baris, lebar, opsi = {}) {
    return new Table({
        rows: baris,
        width: { size: LEBAR_ISI, type: WidthType.DXA },
        columnWidths: lebar,
        layout: TableLayoutType.FIXED,
        borders: garisTabel(GARIS),
        ...opsi,
    });
}

// Lebar kolom (twip) dari persen; kolom tanpa persen mengambil sisanya.
function lebarKolom(persen) {
    const tetap = persen.reduce((jumlah, p) => jumlah + (p ?? 0), 0);
    const sisa = persen.filter((p) => p === null).length;

    return persen.map((p) => Math.round((LEBAR_ISI * (p ?? (100 - tetap) / Math.max(1, sisa))) / 100));
}

function daftar(butir, bernomor) {
    return butir.map((teks, i) => paragraf([run(bernomor ? `${i + 1}.` : '•'), run('\t'), run(teks)], {
        alignment: AlignmentType.JUSTIFIED,
        indent: { left: 720, hanging: 360 },
        tabStops: [{ type: TabStopType.LEFT, position: 720 }],
        spacing: { after: 60, line: 300 },
    }));
}

function matrik(blok) {
    const kepala = new TableRow({
        tableHeader: true,
        children: [sel(paragraf([run('No', { bold: true, size: 18 })], { alignment: AlignmentType.CENTER, spacing: { after: 0 } })),
            sel(paragraf([run('Tahapan / Uraian', { bold: true, size: 18 })], { spacing: { after: 0 } })),
            ...BULAN.map((b) => sel(paragraf([run(b, { bold: true, size: 14 })], { alignment: AlignmentType.CENTER, spacing: { after: 0 } }), { margins: SEL_BULAN }))],
    });
    const baris = [kepala];
    blok.tahapan.forEach((tahap) => {
        baris.push(new TableRow({
            children: [sel(paragraf([run(String(tahap.nomor), { bold: true, size: 18 })], { alignment: AlignmentType.CENTER, spacing: { after: 0 } })),
                sel(paragraf([run(tahap.judul, { bold: true, size: 18 })], { spacing: { after: 0 } }), { columnSpan: 13 })],
        }));
        (tahap.butir ?? []).forEach((butir) => {
            baris.push(new TableRow({
                children: [sel(''), sel(paragraf([run(butir.uraian, { size: 18 })], { spacing: { after: 0 } })),
                    ...BULAN.map((_, i) => sel(paragraf([run((butir.bulan ?? []).includes(i + 1) ? 'v' : '', { size: 18 })], { alignment: AlignmentType.CENTER, spacing: { after: 0 } }), { margins: SEL_BULAN }))],
            }));
        });
    });

    return [tabel(baris, lebarKolom([6, null, ...BULAN.map(() => 4.6)])), paragraf('')];
}

function jadwal(blok) {
    const kepala = new TableRow({
        tableHeader: true,
        children: ['No', 'Uraian Kegiatan', 'Waktu Pelaksanaan'].map((judul) => sel(paragraf([run(judul, { bold: true, size: 20 })], { alignment: AlignmentType.CENTER, spacing: { after: 0 } }))),
    });

    return [tabel([kepala, ...blok.baris.map((b) => new TableRow({
        children: [sel(paragraf([run(String(b.nomor), { size: 20 })], { alignment: AlignmentType.CENTER, spacing: { after: 0 } })), sel(b.uraian ?? ''), sel(b.waktu ?? '')],
    }))], lebarKolom([8, null, 32])), paragraf('')];
}

function pengesahan(kolom) {
    const isi = (ttd) => [
        paragraf(ttd.tempat_tanggal ?? '', { alignment: AlignmentType.CENTER, spacing: { after: 0 } }),
        paragraf(ttd.peran ?? '', { alignment: AlignmentType.CENTER, spacing: { after: 0 } }),
        paragraf([run(ttd.jabatan ?? '', { bold: true })], { alignment: AlignmentType.CENTER, spacing: { after: 1200 } }),
        paragraf([run(ttd.nama ?? '( …………………………… )', ttd.nama ? { bold: true, underline: {} } : { color: '78716C' })], { alignment: AlignmentType.CENTER, spacing: { after: 0 } }),
        paragraf(`NIP. ${ttd.nip ?? '……………………'}`, { alignment: AlignmentType.CENTER, spacing: { after: 0 } }),
    ];
    const lebar = Math.round(LEBAR_ISI / Math.max(1, kolom.length));

    return [paragraf(''), new Table({
        rows: [new TableRow({ cantSplit: true, children: kolom.map((ttd) => new TableCell({ children: isi(ttd), borders: garisTabel(TANPA_GARIS) })) })],
        width: { size: LEBAR_ISI, type: WidthType.DXA },
        columnWidths: kolom.map(() => lebar),
        layout: TableLayoutType.FIXED,
        borders: garisTabel(TANPA_GARIS),
    })];
}

function blokKak(blok) {
    switch (blok.jenis) {
        case 'paragraf':
            return [paragraf(blok.teks, { alignment: AlignmentType.JUSTIFIED, indent: { firstLine: 709 } })];
        case 'subjudul':
            return [paragraf([run(blok.teks, { bold: true })], { spacing: { before: 120, after: 80 } })];
        case 'daftar':
            return daftar(blok.butir ?? [], blok.bernomor !== false);
        case 'kosong':
        case 'menunggu':
            return [paragraf([run(`[${blok.pesan}]`, { italics: true, color: MERAH })])];
        case 'matrik':
            return matrik(blok);
        case 'jadwal':
            return jadwal(blok);
        case 'pengesahan':
            return pengesahan(blok.kolom ?? []);
        default:
            return []; // pagu & blok layar lain tidak tercetak
    }
}

function kaki(draf) {
    return new Footer({
        children: [paragraf([
            ...(draf.draf ? [run(`DRAF — ${draf.alasan[0] ?? 'belum siap dicetak'}`, { size: 16, color: MERAH })] : []),
            run('\tHalaman ', { size: 16 }), new TextRun({ children: [PageNumber.CURRENT], font: FONT, size: 16 }),
            run(' dari ', { size: 16 }), new TextRun({ children: [PageNumber.TOTAL_PAGES], font: FONT, size: 16 }),
        ], { tabStops: [{ type: TabStopType.RIGHT, position: LEBAR_ISI }], spacing: { after: 0 } })],
    });
}

function kepala(draf) {
    return new Header({ children: draf.draf ? [paragraf([run('DRAF', { bold: true, color: MERAH, size: 20 })], { alignment: AlignmentType.RIGHT, spacing: { after: 0 } })] : [] });
}

const halaman = { size: { width: 11906, height: 16838 }, margin: { top: 2 * CM, bottom: 2 * CM, left: 2 * CM, right: 2 * CM, header: CM, footer: CM } };

function cover(data) {
    const item = [...(data.teks ?? [])].sort((a, b) => a.y - b.y);
    let sebelum = 6;
    const isi = item.flatMap((kotak) => {
        const teks = data.nilai?.[kotak.field];
        if (!teks) {
            return [];
        }
        // Persen tinggi A4 (29,7 cm) → twip, dikurangi tinggi baris sebelumnya agar cover tetap satu halaman.
        const jarak = Math.max(0, (kotak.y - sebelum) * 0.297 * CM - 2 * kotak.font_size * 20);
        sebelum = kotak.y;

        return [paragraf(String(teks).split('\n').map((baris, i) => run(baris, { bold: kotak.weight !== 'normal', size: Math.round(kotak.font_size * 2), break: i > 0 ? 1 : 0 })), {
            alignment: RATA[kotak.align] ?? AlignmentType.CENTER,
            spacing: { before: Math.round(jarak), after: 0 },
        })];
    });

    return { properties: { page: halaman }, headers: { default: kepala(data) }, children: isi.length ? isi : [paragraf('')] };
}

function kak(data, judul) {
    const anak = [];
    data.bagian.forEach((bagian) => {
        anak.push(paragraf([run(`${bagian.nomor}. ${String(bagian.judul).toUpperCase()}`, { bold: true })], { spacing: { before: 240, after: 120 }, keepNext: true }));
        bagian.isi.forEach((blok) => anak.push(...blokKak(blok)));
    });

    return { properties: { page: halaman, titlePage: false }, headers: { default: kepala(data) }, footers: { default: kaki(data) }, children: [
        paragraf([run(judul, { bold: true, size: 26 })], { alignment: AlignmentType.CENTER, spacing: { after: 240 } }),
        ...anak,
    ] };
}

function rab(data) {
    const t = data.tabel;
    const lebar = lebarKolom(t.kolom.map((k, i) => (i === t.kolom.indexOf('Uraian') ? null : ({ No: 6, Kode: 11, Vol: 7, Satuan: 12, 'Harga Satuan (Rp)': 14, 'Jumlah (Rp)': 15 }[k] ?? 10))));
    const kanan = (k) => ['Vol', 'Harga Satuan (Rp)', 'Jumlah (Rp)'].includes(k);
    const baris = [new TableRow({ tableHeader: true, children: t.kolom.map((k) => sel(paragraf([run(k, { bold: true, size: 18 })], { alignment: AlignmentType.CENTER, spacing: { after: 0 } }), { shading: { fill: 'F5F5F4' } })) })];
    t.baris.forEach((b) => baris.push(new TableRow({ cantSplit: true, children: b.map((isi, i) => sel(paragraf([run(isi, { size: 18 })], { alignment: kanan(t.kolom[i]) ? AlignmentType.RIGHT : AlignmentType.LEFT, spacing: { after: 0 } }))) })));
    baris.push(new TableRow({ cantSplit: true, children: [
        sel(paragraf([run('TOTAL KESELURUHAN', { bold: true, size: 18 })], { alignment: AlignmentType.RIGHT, spacing: { after: 0 } }), { columnSpan: t.kolom.length - 1 }),
        sel(paragraf([run(t.total_teks, { bold: true, size: 18 })], { alignment: AlignmentType.RIGHT, spacing: { after: 0 } })),
    ] }));
    if (t.terbilang) {
        baris.push(new TableRow({ cantSplit: true, children: [sel(paragraf([run(`Terbilang: ${t.terbilang}`, { italics: true, size: 18 })], { alignment: AlignmentType.RIGHT, spacing: { after: 0 } }), { columnSpan: t.kolom.length })] }));
    }

    return { properties: { page: halaman }, headers: { default: kepala(data) }, footers: { default: kaki(data) }, children: [
        paragraf([run(String(data.judul).toUpperCase(), { bold: true })], { alignment: AlignmentType.CENTER, spacing: { after: 240 } }),
        ...data.kepala.map((k) => paragraf([run(`${k.label}\t: `), run(k.nilai ?? '…'), ...(k.uraian ? [run(` — ${k.uraian}`)] : [])], { tabStops: [{ type: TabStopType.LEFT, position: 3 * CM + 1000 }], spacing: { after: 40 } })),
        paragraf(''),
        tabel(baris, lebar),
        paragraf(''),
        ...(data.mekanisme ?? []).map((m) => paragraf(`${m.label} : ${m.nilai}`, { spacing: { after: 40 } })),
        ...pengesahan(data.pengesahan ?? []),
    ] };
}

// Dokumen docx dari data pratinjau; lembar yang tidak dimuat (null) dilewati.
export function susunDokumen(data) {
    const judul = 'KERANGKA ACUAN KERJA (KAK) / TERM OF REFERENCE (TOR)';
    const bagian = [];
    if (data.dokumen.cover) {
        bagian.push(cover(data.dokumen.cover));
    }
    if (data.dokumen.kak) {
        bagian.push(kak(data.dokumen.kak, judul));
    }
    if (data.dokumen.rab) {
        bagian.push(rab(data.dokumen.rab));
    }

    return new Document({
        creator: 'SIRABTOR',
        title: String(data.cetak?.nama_berkas ?? data.nama_kegiatan ?? 'KAK-TOR').replace(/\.pdf$/, ''),
        description: `KAK/TOR ${data.nama_kegiatan ?? ''}`.trim(),
        styles: { default: { document: { run: { font: FONT, size: UKURAN } } } },
        sections: bagian,
    });
}

export async function susunWord(data) {
    return Packer.toBlob(susunDokumen(data));
}
