// Penampil dokumen tab Pratinjau (Alpine.data('penampilDokumen'), kembaran PenampilDokumen di server): lembar cover,
// KAK/TOR, dan RAB selebar kertas A4 dengan perbesaran (pas lebar ruang, 75/100/125%, atau lebar layar), perkiraan batas
// halaman A4 tiap lembar, penunjuk "Halaman x dari y", dan bagian aktif untuk daftar isi. Semuanya dihitung di klien dari
// ukuran lembar (tanpa request); pilihan perbesaran diingat per peramban.
const KUNCI_SIMPAN = 'sirabtor.pratinjau.perbesaran';

export const RASIO_A4 = 297 / 210;

// Lebar A4 (210 mm) dalam piksel CSS.
export const LEBAR_A4_PX = (210 / 25.4) * 96;

// Jumlah halaman A4 sebuah lembar dari ukurannya (toleransi 2% agar cover A4 tepat tetap satu halaman).
export function jumlahHalaman(tinggi, lebar) {
    if (!(tinggi > 0 && lebar > 0)) {
        return 1;
    }

    return Math.max(1, Math.ceil(tinggi / (lebar * RASIO_A4) - 0.02));
}

// Skala "pas": lembar A4 selebar ruang, tidak melebihi ukuran asli.
export function skalaPas(lebarRuang) {
    return lebarRuang > 0 ? Math.min(1, Math.max(0.3, lebarRuang / LEBAR_A4_PX)) : 1;
}

export default function penampilDokumen(pilihan, bawaan, isi = []) {
    return {
        perbesaran: bawaan,
        lebarRuang: 0,
        halaman: {},
        rasio: {},
        halamanAktif: 1,
        bagianAktif: isi[0]?.kunci ?? null,
        isiTerbuka: false,
        kosongKe: 0,

        init() {
            let simpanan = null;
            try {
                simpanan = window.localStorage.getItem(KUNCI_SIMPAN);
            } catch {
                simpanan = null;
            }
            this.perbesaran = pilihan.includes(simpanan) ? simpanan : window.innerWidth < 640 ? 'layar' : bawaan;

            const ruang = this.$refs.ruang;
            const ukurRuang = () => {
                const gaya = window.getComputedStyle(ruang);
                this.lebarRuang = ruang.clientWidth - parseFloat(gaya.paddingLeft) - parseFloat(gaya.paddingRight);
            };
            ukurRuang();
            new ResizeObserver(ukurRuang).observe(ruang);

            this.pengamat = new ResizeObserver((entri) => entri.forEach((e) => this.hitung(e.target)));
            const daftarkan = () => ruang.querySelectorAll('[data-lembar]').forEach((el) => this.pengamat.observe(el));
            daftarkan();
            // Lembar baru setelah Livewire memuat ulang (mis. ganti sumber dana) ikut diamati.
            new MutationObserver(() => requestAnimationFrame(() => {
                daftarkan();
                this.posisi();
            })).observe(ruang, { childList: true, subtree: true });

            let menunggu = false;
            const jadwalkan = () => {
                if (!menunggu) {
                    menunggu = true;
                    requestAnimationFrame(() => {
                        menunggu = false;
                        this.posisi();
                    });
                }
            };
            window.addEventListener('scroll', jadwalkan, { passive: true });
            window.addEventListener('resize', jadwalkan, { passive: true });
            this.$watch('perbesaran', (nilai) => {
                try {
                    window.localStorage.setItem(KUNCI_SIMPAN, nilai);
                } catch {
                    // Penyimpanan peramban tidak tersedia: pilihan berlaku sampai halaman ditutup.
                }
                requestAnimationFrame(() => this.posisi());
            });
        },

        get cetak() {
            return this.perbesaran !== 'layar';
        },

        get skala() {
            if (!this.cetak) {
                return 1;
            }

            return this.perbesaran === 'pas' ? skalaPas(this.lebarRuang) : Number(this.perbesaran) / 100;
        },

        get persen() {
            return Math.round(this.skala * 100);
        },

        gayaLembar() {
            return this.cetak ? { width: '210mm', zoom: this.skala } : {};
        },

        get totalHalaman() {
            return Object.values(this.halaman).reduce((jumlah, n) => jumlah + n, 0);
        },

        // Lompat ke penanda isian kosong berikutnya/sebelumnya ([data-kosong], urut lembar) dan sorot sebentar.
        keKosong(arah) {
            const penanda = [...this.$refs.ruang.querySelectorAll('[data-kosong]')];
            if (penanda.length === 0) {
                this.kosongKe = 0;

                return;
            }
            const sekarang = this.kosongKe > 0 ? this.kosongKe - 1 : arah > 0 ? -1 : 0;
            this.kosongKe = ((sekarang + arah + penanda.length) % penanda.length) + 1;
            const el = penanda[this.kosongKe - 1];
            el.scrollIntoView({ block: 'center', behavior: 'instant' });
            el.classList.add('penanda-sorot');
            window.setTimeout(() => el.classList.remove('penanda-sorot'), 1600);
        },

        // Posisi batas halaman di lembar (persen tinggi lembar), satu per pergantian halaman.
        garis(lembar) {
            const n = this.halaman[lembar] ?? 1;

            return Array.from({ length: n - 1 }, (_, i) => ({ ke: i + 2, atas: `${(i + 1) * (this.rasio[lembar] ?? 1) * 100}%` }));
        },

        hitung(el) {
            const lembar = el.dataset.lembar;
            const tinggi = el.offsetHeight;
            const lebar = el.offsetWidth;
            this.halaman = { ...this.halaman, [lembar]: jumlahHalaman(tinggi, lebar) };
            this.rasio = { ...this.rasio, [lembar]: tinggi > 0 ? (lebar * RASIO_A4) / tinggi : 1 };
            this.posisi();
        },

        // Halaman & bagian yang sedang dibaca: titik acuan 30% tinggi jendela.
        posisi() {
            const acuan = window.innerHeight * 0.3;
            let lewat = 0;
            let aktif = 1;
            for (const el of this.$refs.ruang.querySelectorAll('[data-lembar]')) {
                const r = el.getBoundingClientRect();
                const n = this.halaman[el.dataset.lembar] ?? 1;
                if (r.top > acuan) {
                    aktif = Math.max(1, lewat);
                    break;
                }
                if (r.bottom <= acuan) {
                    lewat += n;
                    aktif = lewat;
                    continue;
                }
                aktif = lewat + Math.min(n, Math.floor((acuan - r.top) / (r.width * RASIO_A4)) + 1);
                break;
            }
            this.halamanAktif = Math.min(Math.max(1, aktif), Math.max(1, this.totalHalaman));

            let bagian = isi[0]?.kunci ?? null;
            for (const butir of isi) {
                const sasaran = document.getElementById(butir.jangkar);
                if (sasaran && sasaran.getBoundingClientRect().top <= acuan) {
                    bagian = butir.kunci;
                }
            }
            this.bagianAktif = bagian;
        },
    };
}
