// Pagu per sumber dana di Data Kegiatan — dihitung dari state $wire (centang & ketikan pagu) sehingga ringkasan pilihan,
// status pagu yang belum lengkap, total pagu, dan ringkasan pagu terhadap RAB langsung berubah tanpa menunggu server.
// Teks dan total RAB dikirim dari server (Blade) sebagai satu sumber; status memakai statusPagu, kembaran StatusPagu.
import { statusPagu } from './format';

/**
 * @param {{ sumber: {label: string, kunci: string}[], teksPilihan?: string[][], rab?: Object<string, {total: number, item: number}> }} opsi
 */
export default function paguKegiatan({ sumber, teksPilihan = [], rab = {} }) {
    return {
        sumber,
        teksPilihan,
        rab,

        dipilih(kunci) {
            return Boolean(this.$wire.$get(`form.dana_${kunci}`));
        },

        /** Pagu yang sedang tertulis (0 bila kosong). */
        pagu(kunci) {
            const angka = parseInt(String(this.$wire.$get(`form.pagu_${kunci}`) ?? '').replace(/\D/g, ''), 10);

            return Number.isNaN(angka) ? 0 : angka;
        },

        get terpilih() {
            return this.sumber.filter((s) => this.dipilih(s.kunci));
        },

        get ringkasPilihan() {
            const label = this.terpilih.map((s) => s.label);

            return (this.teksPilihan[Math.min(label.length, 2)] ?? []).map((teks) => teks.replace(':sumber', label[0] ?? ''));
        },

        get paguKosong() {
            return this.terpilih.filter((s) => this.pagu(s.kunci) < 1).map((s) => s.label);
        },

        get teksPaguKosong() {
            return this.paguKosong.length ? `Pagu ${this.paguKosong.join(' dan ')} belum diisi — RAB belum bisa dibandingkan dengan pagu.` : '';
        },

        get totalPagu() {
            return this.terpilih.reduce((jumlah, s) => jumlah + this.pagu(s.kunci), 0);
        },

        totalRab(label) {
            return this.rab[label]?.total ?? 0;
        },

        get totalRabTerpilih() {
            return this.terpilih.reduce((jumlah, s) => jumlah + this.totalRab(s.label), 0);
        },

        /** Status RAB terhadap pagu yang sedang tertulis; null bila RAB sumber ini belum berisi rincian. */
        status(s) {
            if (!(this.rab[s.label]?.item > 0)) {
                return null;
            }

            return statusPagu(this.totalRab(s.label), this.pagu(s.kunci) || null);
        },

        /** Lebar bilah pemakaian pagu (0–100). */
        lebar(s) {
            const pagu = this.pagu(s.kunci);

            return pagu ? Math.min(100, (this.totalRab(s.label) / pagu) * 100) : 0;
        },
    };
}
