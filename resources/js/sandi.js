// Syarat sandi pendaftaran akun — kembaran App\Support\Akun\PendaftaranAkun::syaratSandi()
// (kasus bersama tests/fixtures/syarat-sandi.json). Dipakai formulir Daftar akun untuk daftar syarat per ketukan.
import { galatNip, nip } from './format';

export const MIN_SANDI = 8;
const MIN_BAGIAN_PRIBADI = 4;

/** @returns {{panjang: boolean, campur: boolean, pribadi: boolean}} */
export function syaratSandi(sandi, nama, surel) {
    const teks = String(sandi ?? '');
    const kecil = teks.toLowerCase();
    const lokal = String(surel ?? '').trim().toLowerCase().split('@')[0];
    const bagian = `${String(nama ?? '').toLowerCase()} ${lokal}`
        .split(/[^\p{L}\p{N}]+/u)
        .filter((b) => [...b].length >= MIN_BAGIAN_PRIBADI);

    return {
        panjang: [...teks].length >= MIN_SANDI,
        campur: /\p{L}/u.test(teks) && /[0-9]/.test(teks),
        pribadi: !bagian.some((b) => kecil.includes(b)),
    };
}

const TINGKAT = ['kosong', 'lemah', 'sedang', 'kuat'];

// Alpine.data('formDaftar'): salinan ketikan lokal (nama, surel, NIP, sandi, ulang, catatan) untuk syarat sandi, kecocokan
// sandi ulang, pratinjau NIP, dan hitungan catatan — Livewire tetap menerima nilainya saat blur/kirim.
export default function formDaftar() {
    return {
        nama: '',
        surel: '',
        nip: '',
        sandi: '',
        ulang: '',
        catatan: '',
        lihat: false,

        init() {
            const isian = this.$wire.isian ?? {};
            for (const kunci of ['nama', 'surel', 'nip', 'sandi', 'catatan']) {
                this[kunci] = String(isian[kunci] ?? '');
            }
            this.ulang = String(isian.sandi_ulang ?? '');
        },

        get syarat() {
            return syaratSandi(this.sandi, this.nama, this.surel);
        },

        get tingkat() {
            return this.sandi === '' ? TINGKAT[0] : TINGKAT[Math.max(1, Object.values(this.syarat).filter(Boolean).length)];
        },

        // Batang ke-n meter kekuatan menyala: lemah 1, sedang 2, kuat 3.
        nyala(n) {
            return n <= TINGKAT.indexOf(this.tingkat);
        },

        get cocok() {
            return this.ulang !== '' && this.ulang === this.sandi;
        },

        get pratinjauNip() {
            return galatNip(this.nip) ?? `Tercetak: NIP. ${nip(this.nip)}`;
        },
    };
}

// Alpine.data('formSandi'): ganti sandi di Akun saya — syarat sandi baru (kembaran GantiSandi::syarat: syarat pendaftaran
// + berbeda dari sandi saat ini) dan kecocokan sandi ulang per ketukan; nama & surel akun dari atribut data-nama/data-surel.
export function formSandi() {
    return {
        lama: '',
        baru: '',
        ulang: '',
        nama: '',
        surel: '',
        lihat: false,

        init() {
            this.nama = this.$root.dataset.nama ?? '';
            this.surel = this.$root.dataset.surel ?? '';
        },

        get syarat() {
            return { ...syaratSandi(this.baru, this.nama, this.surel), beda: this.baru !== '' && this.baru !== this.lama };
        },

        get cocok() {
            return this.ulang !== '' && this.ulang === this.baru;
        },

        kosongkan() {
            this.lama = '';
            this.baru = '';
            this.ulang = '';
        },
    };
}
