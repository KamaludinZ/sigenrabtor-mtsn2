// Alpine.data('formMasuk') — formulir Masuk (Akun\Masuk): penanda Caps Lock di isian sandi, hitung mundur jeda percobaan
// (event Livewire `masuk-dijeda` {detik} dari PeriksaMasuk), dan "Ingat saya" = surel diingat di peramban ini
// (localStorage KUNCI_SUREL) lalu diisikan kembali saat halaman dibuka.
export const KUNCI_SUREL = 'sirabtor.masuk.surel';

export default function formMasuk() {
    return {
        capslock: false,
        detik: 0,
        timer: null,

        init() {
            let tersimpan = null;
            try {
                tersimpan = window.localStorage.getItem(KUNCI_SUREL);
            } catch {
                tersimpan = null;
            }
            if (tersimpan && !this.$wire.surel) {
                this.$wire.surel = tersimpan;
                this.$wire.ingat = true;
                this.$nextTick(() => this.$root.querySelector('#sandi')?.focus());
            }
        },

        destroy() {
            clearInterval(this.timer);
        },

        mulaiJeda(detik) {
            clearInterval(this.timer);
            this.detik = Math.max(0, Number(detik) || 0);
            if (this.detik > 0) {
                this.timer = setInterval(() => {
                    this.detik -= 1;
                    if (this.detik <= 0) {
                        clearInterval(this.timer);
                    }
                }, 1000);
            }
        },

        periksaCapslock(event) {
            if (typeof event.getModifierState === 'function') {
                this.capslock = event.getModifierState('CapsLock');
            }
        },

        ingatSurel() {
            const surel = String(this.$root.querySelector('#surel')?.value ?? '').trim();
            try {
                if (this.$root.querySelector('#ingat')?.checked && surel !== '') {
                    window.localStorage.setItem(KUNCI_SUREL, surel);
                } else {
                    window.localStorage.removeItem(KUNCI_SUREL);
                }
            } catch {
                // Penyimpanan peramban tidak tersedia (mode privat) — cukup tidak diingat.
            }
        },
    };
}
