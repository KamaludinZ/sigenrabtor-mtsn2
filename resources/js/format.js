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
