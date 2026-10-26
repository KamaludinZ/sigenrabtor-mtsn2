// Editor tata letak kotak teks cover (Pengaturan › Template Dokumen). Kotak dibaca & diubah sebagai state lokal
// $wire.kotak ($set tanpa request) sehingga seret terasa seketika; seluruh susunan baru terkirim saat "Simpan tata
// letak" dan server merapikannya lewat TeksCover::rapikan(). Posisi & lebar dalam persen halaman, ukuran huruf dalam pt
// pada lebar A4 (595 pt) — rumus gaya sama dengan <x-dokumen.cover>.

const LEBAR_PT = 595;
const batas = (n, min, max) => Math.min(max, Math.max(min, n));
const bulat = (n) => Math.round(n * 10) / 10;

export default function editorCover({ bawaan = [], label = {}, nilai = {} } = {}) {
    return {
        /** Isi contoh tiap kolom (kegiatan × sumber dana contoh). */
        nilai,
        /** Indeks kotak terpilih. */
        pilih: 0,
        /** Seretan berjalan: { i, mode: 'pindah'|'lebar', x0, y0, awal } */
        seret: null,
        berubah: false,

        get kotak() {
            return Object.values(this.$wire.kotak ?? {});
        },

        get terpilih() {
            return this.kotak[this.pilih] ?? null;
        },

        label(field) {
            return label[field] ?? field;
        },

        gaya(k) {
            return [
                `left: ${k.x}%`, `top: ${k.y}%`, `width: ${k.width}%`, `text-align: ${k.align}`,
                `font-weight: ${k.weight === 'bold' ? 700 : 400}`, `color: ${k.color}`, `font-size: calc(${k.font_size} * 100cqw / ${LEBAR_PT})`,
            ].join('; ');
        },

        atur(i, kolom, nilai) {
            this.$wire.$set(`kotak.${i}.${kolom}`, nilai, false);
            this.berubah = true;
        },

        /** Isian angka panel properti: dibatasi seperti TeksCover::rapikan(). */
        aturAngka(kolom, nilai) {
            const k = this.terpilih;
            const n = Number(String(nilai).replace(',', '.'));

            if (!k || !Number.isFinite(n)) {
                return;
            }

            const rentang = { x: [0, 100 - k.width], y: [0, 100], width: [5, 100 - k.x], font_size: [6, 48] }[kolom];
            this.atur(this.pilih, kolom, bulat(batas(n, ...rentang)));
        },

        mulai(event, i, mode) {
            if (event.pointerType === 'mouse' && event.button !== 0) {
                return;
            }

            event.preventDefault();
            this.pilih = i;
            const k = this.kotak[i];
            this.seret = { i, mode, x0: event.clientX, y0: event.clientY, awal: { x: k.x, y: k.y, width: k.width } };
            this.$refs.kanvas.focus({ preventScroll: true });
        },

        gerak(event) {
            if (!this.seret) {
                return;
            }

            const r = this.$refs.kanvas.getBoundingClientRect();
            const dx = ((event.clientX - this.seret.x0) / r.width) * 100;
            const dy = ((event.clientY - this.seret.y0) / r.height) * 100;
            const { i, mode, awal } = this.seret;

            if (mode === 'pindah') {
                this.atur(i, 'x', bulat(batas(awal.x + dx, 0, 100 - awal.width)));
                this.atur(i, 'y', bulat(batas(awal.y + dy, 0, 97)));
            } else {
                this.atur(i, 'width', bulat(batas(awal.width + dx, 5, 100 - awal.x)));
            }
        },

        selesai() {
            this.seret = null;
        },

        /** Panah menggeser kotak terpilih 0,5% (Shift: 2%); Tab/Shift+Tab di kanvas berpindah kotak. */
        tombol(event) {
            const k = this.terpilih;
            const arah = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] }[event.key];

            if (!k || !arah) {
                return;
            }

            event.preventDefault();
            const langkah = event.shiftKey ? 2 : 0.5;
            this.atur(this.pilih, 'x', bulat(batas(k.x + arah[0] * langkah, 0, 100 - k.width)));
            this.atur(this.pilih, 'y', bulat(batas(k.y + arah[1] * langkah, 0, 97)));
        },

        tengahkan() {
            const k = this.terpilih;

            if (k) {
                this.atur(this.pilih, 'x', bulat((100 - k.width) / 2));
            }
        },

        tampil(field) {
            return this.kotak.some((k) => k.field === field);
        },

        pilihField(field) {
            const i = this.kotak.findIndex((k) => k.field === field);

            if (i >= 0) {
                this.pilih = i;
            }
        },

        /** Sembunyikan kolom (kotaknya dibuang) atau tampilkan lagi di posisi bawaan. */
        alihTampil(field) {
            const sekarang = this.kotak;
            const baru = this.tampil(field)
                ? sekarang.filter((k) => k.field !== field)
                : [...sekarang, structuredClone(bawaan.find((k) => k.field === field))].filter(Boolean);

            this.$wire.$set('kotak', baru, false);
            this.berubah = true;
            this.pilih = Math.max(0, Math.min(this.pilih, baru.length - 1));

            if (!sekarang.some((k) => k.field === field)) {
                this.pilih = baru.length - 1;
            }
        },

        kembalikan() {
            this.$wire.$set('kotak', structuredClone(bawaan), false);
            this.berubah = true;
            this.pilih = 0;
        },
    };
}
