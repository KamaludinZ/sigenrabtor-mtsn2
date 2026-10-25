// Termin pembayaran di klien — kembaran App\Support\Rab\MekanismePembayaran (status, teks, persen); diuji bersama lewat
// tests/fixtures/status-termin.json. Dipakai agar jumlah termin, selisih, dan persen ikut tiap ketukan nominal.
import { rupiah } from './format';

const angka = (nilai) => parseInt(String(nilai ?? '').replace(/\D/g, ''), 10) || 0;

export function statusTermin(total, nominal) {
    const jumlah = nominal.reduce((a, n) => a + (Number(n) || 0), 0);
    const selisih = total - jumlah;

    if (nominal.length === 0) return { status: 'sekaligus', teks: 'Dibayar sekaligus', jumlah, selisih };
    if (selisih === 0) return { status: 'pas', teks: 'Jumlah termin sama dengan total RAB', jumlah, selisih };
    if (selisih > 0) return { status: 'kurang', teks: `Termin kurang ${rupiah(selisih)} dari total RAB`, jumlah, selisih };
    return { status: 'lebih', teks: `Termin lebih ${rupiah(-selisih)} dari total RAB`, jumlah, selisih };
}

/** Bagian nominal terhadap total, satu desimal (sama dengan MekanismePembayaran::persen). */
export function persenTermin(nominal, total) {
    return total > 0 ? Math.round((nominal / total) * 1000) / 10 : 0;
}

/**
 * Alpine.data('jumlahTermin'): nominal dibaca dari state Livewire, ditimpa ketikan yang belum terkirim (isian
 * nominal baru tersinkron saat ditinggalkan). Ketikan dibuang begitu server memperbarui daftar termin.
 */
export default function jumlahTermin({ total }) {
    return {
        total,
        ketikan: {},

        init() {
            this.$watch(() => JSON.stringify(this.$wire.$get('baris')), () => (this.ketikan = {}));
        },

        ketik(key, nilai) {
            this.ketikan = { ...this.ketikan, [key]: angka(nilai) };
        },

        nominal() {
            return (this.$wire.$get('baris') ?? []).map((b) => this.ketikan[b.key] ?? angka(b.nominal));
        },

        get hasil() {
            return statusTermin(this.total, this.nominal());
        },

        teksPersen(key) {
            const baris = this.$wire.$get('baris') ?? [];
            const indeks = baris.findIndex((b) => b.key === key);

            return indeks < 0 ? '' : `${String(persenTermin(this.nominal()[indeks], this.total)).replace('.', ',')}%`;
        },
    };
}
