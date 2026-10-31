// Kolom tanda tangan lembar pengesahan per baris tampil — kembaran SusunanKak::barisPengesahan (dipakai penyusun Word &
// Excel di peramban): format 9 bagian satu baris sejajar; format 5 bab kolom ber-`baris` (0 = Menyetujui & pembuat
// berdampingan, 1 = Mengetahui di tengah bawah).
export function barisPengesahan(kolom) {
    const baris = [];
    kolom.forEach((ttd) => {
        const i = Number(ttd.baris ?? 0);
        (baris[i] ??= []).push(ttd);
    });

    return baris.filter(Boolean);
}
