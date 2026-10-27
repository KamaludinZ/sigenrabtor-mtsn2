// Penanda "Belum disimpan" formulir Pengaturan AI (Alpine.data). Isian Livewire `model` dibandingkan dengan salinannya
// saat formulir dibuka/terakhir tersimpan (angka-teks dibaca sebagai angka agar "20" sama dengan 20). Formulir yang kotor
// menandai dirinya (data-ai-kotor="ya") sehingga pindah tab (adaFormKotor) & meninggalkan halaman (beforeunload,
// livewire:navigate) meminta konfirmasi; event Livewire `pengaturan-ai-tersimpan` {bagian} mengatur ulang salinan dan
// menyalakan penanda "Tersimpan" sebentar.
const normal = (_, nilai) => (typeof nilai === 'string' && nilai.trim() !== '' && !Number.isNaN(Number(nilai)) ? Number(nilai) : nilai);

const PESAN_TINGGAL = 'Perubahan Pengaturan AI belum disimpan. Tinggalkan halaman ini?';

export default function formAi(model, bagian) {
    return {
        awal: null,
        tersimpan: false,

        init() {
            this.awal = this.salin();
            const tandai = () => {
                this.$el.dataset.aiKotor = this.kotor ? 'ya' : 'tidak';
            };
            tandai();
            this.$watch('kotor', tandai);

            this.saatSimpan = (e) => this.setelTersimpan(e.detail);
            this.saatTinggal = (e) => {
                if (this.kotor) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            };
            this.saatPindah = (e) => {
                if (this.kotor && !window.confirm(PESAN_TINGGAL)) {
                    e.preventDefault();
                }
            };
            window.addEventListener('pengaturan-ai-tersimpan', this.saatSimpan);
            window.addEventListener('beforeunload', this.saatTinggal);
            document.addEventListener('livewire:navigate', this.saatPindah);
        },

        destroy() {
            window.removeEventListener('pengaturan-ai-tersimpan', this.saatSimpan);
            window.removeEventListener('beforeunload', this.saatTinggal);
            document.removeEventListener('livewire:navigate', this.saatPindah);
        },

        salin() {
            return JSON.stringify(this.$wire.$get(model) ?? null, normal);
        },

        get kotor() {
            return this.awal !== null && this.salin() !== this.awal;
        },

        setelTersimpan(detail) {
            if (detail?.bagian !== bagian) {
                return;
            }
            this.awal = this.salin();
            this.tersimpan = true;
            setTimeout(() => {
                this.tersimpan = false;
            }, 2500);
        },
    };
}

/** Ada formulir Pengaturan AI yang belum disimpan di halaman. */
export function adaFormKotor() {
    return document.querySelector('[data-ai-kotor="ya"]') !== null;
}
