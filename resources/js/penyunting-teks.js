// Penyunting teks pratinjau (Alpine.data('penyuntingTeks'), pasangan <x-dokumen.penyunting-teks>): penanda "Belum
// disimpan" (ketikan dibanding teks saat penyunting dibuka), draf lokal per kegiatan × bidang di localStorage agar ketikan
// tidak hilang bila halaman tertutup atau koneksi putus (ditawarkan kembali saat bidang itu dibuka lagi, selama teks
// tersimpannya belum berubah), dan konfirmasi sebelum meninggalkan halaman / menutup penyunting / berpindah bidang, mode,
// atau sumber dana selama masih kotor (`x-jaga-sunting`). Draf lokal dibuang saat tersimpan (event Livewire
// `sunting-tersimpan` {kunci}) atau saat pengguna membuangnya.
export const PESAN_BUANG = 'Teks yang sedang disunting belum disimpan. Buang perubahannya?';

export const kunciDraf = (kegiatan, bidang) => `sirabtor.sunting.${kegiatan}.${bidang}`;

function baca(kunci) {
    try {
        return JSON.parse(window.localStorage.getItem(kunci) ?? 'null');
    } catch {
        return null;
    }
}

function tulis(kunci, nilai) {
    try {
        if (nilai === null) {
            window.localStorage.removeItem(kunci);
        } else {
            window.localStorage.setItem(kunci, JSON.stringify(nilai));
        }
    } catch {
        // Penyimpanan peramban tidak tersedia: penanda & konfirmasi tetap berjalan.
    }
}

/** Ada draf lokal bidang ini (dari sesi sebelumnya) — untuk penanda di tombol Sunting. */
export function adaDrafSunting(kegiatan, bidang) {
    const draf = baca(kunciDraf(kegiatan, bidang));

    return draf !== null && typeof draf.teks === 'string';
}

/** Ada penyunting pratinjau yang belum disimpan di halaman. */
export function adaSuntingKotor() {
    return document.querySelector('[data-sunting-kotor="ya"]') !== null;
}

/** Hentikan klik (aksi Livewire) bila ada sunting yang belum disimpan dan pengguna tidak jadi membuangnya. */
export function jagaSunting(el) {
    el.addEventListener('click', (e) => {
        if (adaSuntingKotor() && !window.confirm(PESAN_BUANG)) {
            e.preventDefault();
            e.stopImmediatePropagation();
        }
    }, { capture: true });
}

export default function penyuntingTeks(kegiatan, bidang) {
    const kunci = kunciDraf(kegiatan, bidang);

    return {
        awal: '',
        teks: '',
        draf: null,

        init() {
            this.awal = this.$wire.teksSunting ?? '';
            this.teks = this.awal;
            const lama = baca(kunci);
            // Ditawarkan hanya bila disusun dari teks tersimpan yang sama dan isinya berbeda; selain itu draf usang dibuang.
            if (lama && lama.awal === this.awal && lama.teks !== this.awal) {
                this.draf = lama;
            } else if (lama) {
                tulis(kunci, null);
            }
            this.tandai();

            this.saatTersimpan = (e) => {
                if (e.detail?.kunci === bidang) {
                    tulis(kunci, null);
                }
            };
            this.saatTinggal = (e) => {
                if (this.kotor) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            };
            this.saatPindah = (e) => {
                if (this.kotor && !window.confirm(PESAN_BUANG)) {
                    e.preventDefault();
                }
            };
            window.addEventListener('sunting-tersimpan', this.saatTersimpan);
            window.addEventListener('beforeunload', this.saatTinggal);
            document.addEventListener('livewire:navigate', this.saatPindah);
        },

        destroy() {
            window.removeEventListener('sunting-tersimpan', this.saatTersimpan);
            window.removeEventListener('beforeunload', this.saatTinggal);
            document.removeEventListener('livewire:navigate', this.saatPindah);
        },

        get kotor() {
            return this.teks !== this.awal;
        },

        tandai() {
            this.$root.dataset.suntingKotor = this.kotor ? 'ya' : 'tidak';
        },

        // Setiap ketikan: catat draf lokal selama berbeda dari teks tersimpan.
        ketik(teks) {
            this.teks = teks;
            this.tandai();
            tulis(kunci, this.kotor ? { teks, awal: this.awal, pada: Date.now() } : null);
        },

        pulihkan() {
            this.$wire.teksSunting = this.draf.teks;
            this.ketik(this.draf.teks);
            this.draf = null;
        },

        buangDraf() {
            tulis(kunci, null);
            this.draf = null;
        },

        // Jam draf lokal "21.05" (tanpa locale peramban).
        waktuDraf() {
            if (!this.draf) {
                return '';
            }
            const waktu = new Date(this.draf.pada);

            return `${String(waktu.getHours()).padStart(2, '0')}.${String(waktu.getMinutes()).padStart(2, '0')}`;
        },

        batal() {
            if (this.kotor && !window.confirm(PESAN_BUANG)) {
                return;
            }
            tulis(kunci, null);
            this.teks = this.awal;
            this.tandai();
            this.$wire.tutupSunting();
        },
    };
}
