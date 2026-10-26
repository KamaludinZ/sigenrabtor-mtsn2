// Format angka & rupiah Indonesia di sisi klien — kembaran App\Support\Format agar layar dan server selalu sama.
// Tidak memakai toLocaleString supaya hasilnya tidak bergantung pada data lokal browser.

export function angka(nilai) {
    const n = Number(nilai);
    if (!Number.isFinite(n)) return '';
    const negatif = n < 0;
    const bulat = Math.abs(Math.round(n)).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return (negatif ? '-' : '') + bulat;
}

export function rupiah(nilai) {
    const n = Number(nilai);
    if (!Number.isFinite(n)) return '';
    return (n < 0 ? '-' : '') + 'Rp' + angka(Math.abs(n));
}

// Rapikan ketikan nominal di input: buang non-digit & nol di depan, beri titik ribuan ("010000" → "10.000").
export function rapikanKetikan(teks) {
    return String(teks ?? '')
        .replace(/\D/g, '')
        .replace(/^0+(?=\d)/, '')
        .replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

// Hitung jumlah RAB persis seperti KalkulatorRab::jumlah (volume 2 desimal, pembulatan setengah-ke-atas).
export function jumlahRab(volume, hargaSatuan) {
    let t = String(volume ?? '').replace(/\s/g, '');
    t = t.includes(',') ? t.replace(/\./g, '').replace(',', '.') : (/^\d{1,3}(\.\d{3})+$/.test(t) ? t.replace(/\./g, '') : t);
    const v = parseFloat(t);
    const h = parseInt(String(hargaSatuan ?? '').replace(/\D/g, ''), 10);
    return v > 0 && h > 0 ? Math.floor((Math.round(v * 100) * h + 50) / 100) : null;
}

// Status total RAB terhadap pagu — kembaran App\Support\Rab\StatusPagu (teks & nada sama; uji bersama tests/fixtures/status-pagu.json).
export function statusPagu(total, pagu) {
    if (pagu === null || pagu === undefined || pagu === '') {
        return { status: 'tanpa_pagu', teks: 'Pagu belum diisi', nada: 'netral' };
    }

    const t = Number(total);
    const p = Number(pagu);

    if (t === p) return { status: 'sesuai', teks: 'Sesuai pagu', nada: 'aman' };
    if (t < p) return { status: 'sisa', teks: `Sisa pagu ${rupiah(p - t)}`, nada: 'aman' };
    return { status: 'melebihi', teks: `Melebihi pagu ${rupiah(t - p)}`, nada: 'bahaya' };
}

// Tanggal Indonesia — kembaran Carbon translatedFormat('l, j F Y') locale id (uji bersama tests/fixtures/tanggal.json).
// Dibentuk manual (tanpa toLocaleDateString) agar tidak bergantung bahasa/locale browser.
const NAMA_HARI = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
export const NAMA_BULAN = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

function bacaTanggal(iso) {
    const cocok = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(iso ?? ''));
    if (!cocok) return null;
    const [, y, m, d] = cocok.map(Number);
    const tanggal = new Date(y, m - 1, d);
    return tanggal.getMonth() === m - 1 && tanggal.getDate() === d ? tanggal : null;
}

/** "2026-08-03" → "Senin, 3 Agustus 2026"; '' bila kosong/tidak sah. */
export function tanggalPanjang(iso) {
    const t = bacaTanggal(iso);
    return t ? `${NAMA_HARI[t.getDay()]}, ${t.getDate()} ${NAMA_BULAN[t.getMonth()]} ${t.getFullYear()}` : '';
}

/** Nama hari bila tanggal jatuh di Sabtu/Minggu, selain itu null. */
export function akhirPekan(iso) {
    const t = bacaTanggal(iso);
    return t && (t.getDay() === 0 || t.getDay() === 6) ? NAMA_HARI[t.getDay()] : null;
}

// Terbilang Rupiah — kembaran App\Support\Terbilang (kaidah "se-" untuk 10, 11, 100, 1.000; "satu" untuk juta ke atas).
const SATUAN = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];
const SKALA = [[1e12, 'triliun'], [1e9, 'miliar'], [1e6, 'juta']];

function gabung(depan, sisa) {
    return sisa === 0 ? depan : `${depan} ${eja(sisa)}`;
}

function eja(n) {
    for (const [besar, nama] of SKALA) {
        if (n >= besar) return gabung(`${eja(Math.floor(n / besar))} ${nama}`, n % besar);
    }
    if (n < 12) return SATUAN[n];
    if (n < 20) return `${SATUAN[n - 10]} belas`;
    if (n < 100) return gabung(`${SATUAN[Math.floor(n / 10)]} puluh`, n % 10);
    if (n < 200) return gabung('seratus', n - 100);
    if (n < 1000) return gabung(`${SATUAN[Math.floor(n / 100)]} ratus`, n % 100);
    if (n < 2000) return gabung('seribu', n - 1000);
    return gabung(`${eja(Math.floor(n / 1000))} ribu`, n % 1000);
}

export function terbilangKata(nilai) {
    const n = Math.trunc(Number(nilai));
    if (!Number.isFinite(n) || Math.abs(n) > 999999999999999) return '';
    if (n === 0) return 'nol';
    return n < 0 ? `minus ${eja(-n)}` : eja(n);
}

export function terbilang(nilai) {
    const kata = terbilangKata(nilai);
    return kata === '' ? '' : `${kata} rupiah`.replace(/(^|\s)\S/g, (h) => h.toUpperCase());
}
