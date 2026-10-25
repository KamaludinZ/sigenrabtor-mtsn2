// Grid matrik tahapan × bulan (halaman Matrik & Jadwal). Sel ditampilkan dari state $wire.matrik sehingga ikut berubah
// saat server mengisi otomatis/mengurungkan. Mouse: tekan lalu seret melintasi sel untuk menandai/menghapus beberapa bulan
// sekaligus — selama menyeret hanya state klien yang berubah ($set tanpa request), lalu tiap baris yang berubah dikirim
// sekali lewat aturBulan(). Sentuh & keyboard: ketuk/Enter/Spasi pada sel (event click), agar geser tabel di layar
// sempit tidak ikut menandai sel.

const urut = (bulan) => [...new Set(bulan.map(Number))].filter((b) => b >= 1 && b <= 12).sort((a, b) => a - b);

export default function gridMatrik({ dapatDiedit = true } = {}) {
    return {
        /** 'tandai' | 'hapus' | null — mode seretan mouse yang sedang berjalan. */
        menyeret: null,
        /** Baris yang berubah selama seretan: "tahapan|index". */
        berubah: new Set(),
        /** Klik setelah pointerdown mouse sudah ditangani oleh seretan. */
        abaikanKlik: false,

        /** Layar sempit: geser tabel agar kolom bulan pelaksanaan pertama langsung terlihat di samping kolom uraian. */
        init() {
            this.$nextTick(() => {
                const wadah = this.$el;
                const kepala = wadah.querySelector('thead th');
                const sorot = wadah.querySelector('thead th[data-pelaksanaan]');

                if (!kepala || !sorot || wadah.scrollWidth <= wadah.clientWidth) {
                    return;
                }

                const terlihat = wadah.clientWidth - kepala.offsetWidth;
                wadah.scrollLeft = Math.max(0, sorot.offsetLeft - kepala.offsetWidth - (terlihat - sorot.offsetWidth) / 2);
            });
        },

        bulan(tahapan, index) {
            return this.$wire.matrik?.[tahapan]?.[index]?.bulan ?? [];
        },

        ditandai(tahapan, index, bulan) {
            return this.bulan(tahapan, index).includes(bulan);
        },

        /** Ubah satu sel di state klien saja; dikirim ke server lewat kirim(). */
        atur(tahapan, index, bulan, tandai) {
            const lama = this.bulan(tahapan, index);
            const baru = urut(tandai ? [...lama, bulan] : lama.filter((b) => b !== bulan));

            if (baru.length === lama.length && baru.every((b, i) => b === lama[i])) {
                return;
            }

            this.$wire.$set(`matrik.${tahapan}.${index}.bulan`, baru, false);
            this.berubah.add(`${tahapan}|${index}`);
        },

        kirim() {
            for (const kunci of this.berubah) {
                const [tahapan, index] = kunci.split('|');
                this.$wire.aturBulan(tahapan, Number(index), this.bulan(tahapan, Number(index)));
            }
            this.berubah.clear();
        },

        mulai(event, tahapan, index, bulan) {
            if (!dapatDiedit || event.pointerType !== 'mouse' || event.button !== 0) {
                return;
            }

            event.preventDefault();
            this.menyeret = this.ditandai(tahapan, index, bulan) ? 'hapus' : 'tandai';
            this.abaikanKlik = true;
            this.atur(tahapan, index, bulan, this.menyeret === 'tandai');
        },

        lewati(tahapan, index, bulan) {
            if (this.menyeret) {
                this.atur(tahapan, index, bulan, this.menyeret === 'tandai');
            }
        },

        selesai() {
            if (this.menyeret) {
                this.menyeret = null;
                this.kirim();
                // Bila tombol dilepas di sel lain, tidak ada event click yang perlu diabaikan.
                setTimeout(() => (this.abaikanKlik = false), 0);
            }
        },

        klik(tahapan, index, bulan) {
            if (this.abaikanKlik) {
                this.abaikanKlik = false;

                return;
            }

            if (dapatDiedit) {
                this.atur(tahapan, index, bulan, !this.ditandai(tahapan, index, bulan));
                this.kirim();
            }
        },
    };
}
