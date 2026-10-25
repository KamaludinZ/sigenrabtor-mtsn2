// Catatan konteks untuk AI — kembaran App\Support\Kegiatan\KonteksAi di klien (daftar aspek & kata penandanya dikirim
// dari server sehingga tetap satu sumber). Ditambah draf lokal per kegiatan agar ketikan tidak hilang bila halaman
// tertutup atau koneksi terputus sebelum disimpan (PRD N5).

const POLA_NIP = /(?<!\d)\d{18}(?!\d)|\d{8}\s\d{6}\s\d\s\d{3}/;

/** { latar: true, peserta: false, … } — aspek yang sudah disebut di catatan. */
export function aspekTerisi(teks, aspek) {
    const kecil = String(teks ?? '').toLowerCase();

    return Object.fromEntries(Object.entries(aspek).map(([kunci, [, , kata]]) => [kunci, kata.some((k) => kecil.includes(k))]));
}

export function memuatNip(teks) {
    const isi = String(teks ?? '');

    return POLA_NIP.test(isi) || /\bNIP\b/i.test(isi);
}

const baca = (kunci) => {
    try {
        return JSON.parse(localStorage.getItem(kunci) ?? 'null');
    } catch {
        return null;
    }
};

const tulis = (kunci, nilai) => {
    try {
        nilai === null ? localStorage.removeItem(kunci) : localStorage.setItem(kunci, JSON.stringify(nilai));
    } catch {
        // Penyimpanan lokal tidak tersedia (mode privat/penuh): fitur draf lokal dilewati.
    }
};

export default function catatanKonteks({ aspek, kunci, simpanOtomatis = false, tersimpanPada = null, model = 'form.konteks', idInput = 'catatan_konteks', jeda = 2000 }) {
    let pewaktu = null;

    return {
        teks: '',
        draf: null,
        /** '' | 'menunggu' | 'tersimpan' — status simpan otomatis ke server (kegiatan yang sudah tersimpan). */
        simpan: tersimpanPada ? 'tersimpan' : '',
        pada: tersimpanPada,

        init() {
            this.teks = String(this.$wire.$get(model) ?? '');
            const lokal = baca(kunci);

            // Draf lokal yang berbeda dari data tersimpan ditawarkan untuk dipulihkan; yang sama dibuang.
            if (lokal && lokal.teks.trim() !== '' && lokal.teks !== this.teks) {
                this.draf = lokal;
            } else {
                tulis(kunci, null);
            }

            this.$watch(() => this.$wire.$get(model), (nilai) => (this.teks = String(nilai ?? '')));
        },

        get terisi() {
            return aspekTerisi(this.teks, aspek);
        },

        get nip() {
            return memuatNip(this.teks);
        },

        ketik(event) {
            if (event.target.id !== idInput) {
                return;
            }

            this.teks = event.target.value;
            tulis(kunci, { teks: this.teks, waktu: new Date().toISOString() });

            // Simpan otomatis setelah berhenti mengetik: nilai dikirim apa adanya (server tidak mengubahnya di tempat).
            if (simpanOtomatis) {
                clearTimeout(pewaktu);
                this.simpan = 'menunggu';
                pewaktu = setTimeout(() => this.$wire.$set(model, this.teks), jeda);
            }
        },

        /** Server sudah menyimpan catatan: draf lokal tidak diperlukan lagi. */
        konteksTersimpan(event) {
            clearTimeout(pewaktu);
            this.simpan = 'tersimpan';
            this.pada = event.detail?.pada ?? this.pada;
            tulis(kunci, null);
        },

        teksSimpan() {
            if (!simpanOtomatis) return 'Tersimpan di perangkat ini sampai kegiatan disimpan.';
            if (this.simpan === 'menunggu') return 'Menyimpan otomatis…';
            if (this.simpan === 'tersimpan') return `Tersimpan otomatis pukul ${this.pada}.`;
            return 'Tersimpan otomatis beberapa saat setelah berhenti mengetik.';
        },

        waktuDraf() {
            return this.draf ? new Date(this.draf.waktu).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' }) : '';
        },

        pulihkan() {
            const input = document.getElementById(idInput);

            if (this.draf && input) {
                input.value = this.draf.teks;
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('blur'));
            }

            this.draf = null;
        },

        abaikan() {
            tulis(kunci, null);
            this.draf = null;
        },

        /** Contoh panduan ke catatan: mengisi bila kosong, menambah di bawah bila sudah ada isi (tidak menimpa). */
        pakaiContoh(teks) {
            const input = document.getElementById(idInput);

            if (!input) {
                return;
            }

            input.value = input.value.trim() ? `${input.value.replace(/\s*$/, '')}\n\n${teks}` : teks;
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('blur'));
            input.focus();
        },

        /** Kegiatan tersimpan: catatan sudah aman di server, draf lokal tidak diperlukan lagi. */
        tersimpan() {
            tulis(kunci, null);
            this.draf = null;
        },
    };
}
