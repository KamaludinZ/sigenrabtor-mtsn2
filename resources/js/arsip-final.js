// Kembaran klien App\Support\Kegiatan\ArsipFinal::galatBerkas() (kasus bersama tests/fixtures/arsip-final.json): berkas
// arsip final diperiksa di peramban sebelum dikirim, sehingga PDF yang salah atau terlalu besar tidak sempat diunggah.

export const MAKS_KB = 10240;

/** Pesan galat berkas arsip (kosong, bukan .pdf, terlalu besar, isi bukan PDF) atau null bila boleh diunggah. */
export function galatArsip({ nama, ukuran, kepala }) {
    const ekstensi = String(nama ?? '').includes('.') ? String(nama).split('.').pop().toLowerCase() : '';

    if (!(ukuran > 0)) {
        return 'Berkas kosong — pilih ulang hasil pindaian.';
    }
    if (ekstensi !== 'pdf') {
        return 'Arsip final harus berupa berkas PDF.';
    }
    if (ukuran > MAKS_KB * 1024) {
        return `Ukuran arsip maksimal ${MAKS_KB / 1024} MB — perkecil resolusi pindaian.`;
    }
    if (!String(kepala ?? '').startsWith('%PDF-')) {
        return 'Berkas ini bukan PDF yang sah — simpan ulang hasil pindaian sebagai PDF.';
    }

    return null;
}

/** Lima bait pertama berkas sebagai teks (penanda %PDF-). */
async function kepalaBerkas(berkas) {
    return new TextDecoder('latin1').decode(await berkas.slice(0, 5).arrayBuffer());
}

/**
 * Satu baris arsip (per sumber dana): berkas yang dipilih diperiksa dulu; bila lolos diunggah lewat $wire.upload ke
 * properti `berkas.{sumber}` (server menyimpannya di updatedBerkas), dengan bilah kemajuan.
 */
export default function unggahArsip({ sumber }) {
    return {
        unggah: false,
        persen: 0,
        galat: null,

        async pilih(event) {
            const berkas = event.target.files?.[0];
            event.target.value = '';
            if (!berkas || this.unggah) {
                return;
            }

            this.galat = galatArsip({ nama: berkas.name, ukuran: berkas.size, kepala: berkas.size > 0 ? await kepalaBerkas(berkas) : '' });
            if (this.galat) {
                return;
            }

            this.unggah = true;
            this.persen = 0;
            this.$wire.upload(
                `berkas.${sumber}`,
                berkas,
                () => { this.unggah = false; },
                () => { this.unggah = false; this.galat = 'Unggahan gagal — periksa sambungan lalu coba lagi.'; },
                (e) => { this.persen = e.detail.progress; },
                () => { this.unggah = false; },
            );
        },
    };
}
