// Pemilih bulan pelaksanaan (Alpine.data). Pilihan langsung tampil tanpa menunggu server lalu dikirim utuh
// (angka 1–12, urut naik) ke properti Livewire; server tidak perlu mengubahnya lagi sehingga klik beruntun tidak tertimpa.
// Shift + klik memilih rentang dari bulan terakhir yang diklik; panah kiri/kanan, Home, End memindah fokus.
export default function pemilihBulan(model = 'form.bulan_pelaksanaan') {
    return {
        jangkar: null,

        pilihan() {
            return (this.$wire.$get(model) ?? []).map(Number);
        },

        dipilih(bulan) {
            return this.pilihan().includes(bulan);
        },

        simpan(bulan) {
            const rapi = [...new Set(bulan)].filter((b) => b >= 1 && b <= 12).sort((a, b) => a - b);
            this.$wire.$set(model, rapi);
        },

        klik(bulan, event) {
            if (event.shiftKey && this.jangkar !== null && this.jangkar !== bulan) {
                const dari = Math.min(this.jangkar, bulan);
                const sampai = Math.max(this.jangkar, bulan);
                const rentang = Array.from({ length: sampai - dari + 1 }, (_, i) => dari + i);
                this.simpan([...this.pilihan(), ...rentang]);
            } else {
                this.simpan(this.dipilih(bulan) ? this.pilihan().filter((b) => b !== bulan) : [...this.pilihan(), bulan]);
            }

            this.jangkar = bulan;
        },

        kosongkan() {
            this.jangkar = null;
            this.simpan([]);
        },

        geser(event) {
            const tombol = [...this.$el.querySelectorAll('[data-bulan]')];
            const posisi = tombol.indexOf(document.activeElement);
            const tujuan = { ArrowRight: posisi + 1, ArrowLeft: posisi - 1, Home: 0, End: tombol.length - 1 }[event.key];

            if (posisi < 0 || tujuan === undefined) {
                return;
            }

            event.preventDefault();
            tombol[Math.max(0, Math.min(tombol.length - 1, tujuan))].focus();
        },
    };
}
