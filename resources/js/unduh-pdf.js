// Unduh PDF langsung dari server tanpa meninggalkan halaman (PRD §6 Cetak & Unduh): berkas diambil lewat fetch
// (`Accept: application/pdf, application/json` — server menjawab JSON {pesan, tautan_cetak} bila gagal disusun),
// kemajuan dibaca dari aliran respons (Content-Length), lalu disimpan dengan nama berkas baku dari header
// X-Nama-Berkas/Content-Disposition (cadangan: nama dari server saat halaman dirender). Bisa dibatalkan; gagal →
// pesan + jalan keluar lewat halaman cetak ("Simpan sebagai PDF").

const KB = 1024;

// Ukuran berkas terbaca: "245 KB", "1,2 MB" (tanpa locale peramban, sama gaya ArsipFinal::ukuran).
export function ukuranBerkas(bita) {
    if (!Number.isFinite(bita) || bita <= 0) {
        return '';
    }
    if (bita < KB * KB) {
        return `${Math.max(1, Math.round(bita / KB))} KB`;
    }

    return `${(bita / (KB * KB)).toFixed(1).replace('.', ',')} MB`;
}

// Nama berkas dari header respons; null bila tidak ada.
export function namaDariRespons(respons) {
    const langsung = respons.headers.get('X-Nama-Berkas');
    if (langsung) {
        return langsung;
    }
    const disposisi = respons.headers.get('Content-Disposition') ?? '';
    const cocok = disposisi.match(/filename\*?=(?:UTF-8'')?"?([^";]+)"?/i);

    return cocok ? decodeURIComponent(cocok[1]) : null;
}

export default function unduhPdf(url, nama, tautanCetak = null) {
    return {
        keadaan: 'siap', // siap | memuat | selesai | gagal
        persen: null,
        diterima: '',
        pesan: '',
        tautanCetak,
        pengendali: null,

        get memuat() {
            return this.keadaan === 'memuat';
        },

        async unduh() {
            if (this.memuat) {
                return;
            }
            this.keadaan = 'memuat';
            this.persen = null;
            this.diterima = '';
            this.pesan = '';
            this.pengendali = new AbortController();
            try {
                const respons = await fetch(url, {
                    headers: { Accept: 'application/pdf, application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    signal: this.pengendali.signal,
                });
                const jenis = respons.headers.get('Content-Type') ?? '';
                if (!respons.ok || !jenis.includes('application/pdf')) {
                    const isi = jenis.includes('json') ? await respons.json().catch(() => ({})) : {};
                    this.tautanCetak = isi.tautan_cetak ?? this.tautanCetak;
                    throw new Error(isi.pesan ?? (respons.status === 403 ? 'Anda tidak berhak mengunduh dokumen ini.' : 'PDF belum bisa disiapkan — coba lagi sebentar lagi.'));
                }
                const berkas = await this.baca(respons);
                this.simpan(berkas, namaDariRespons(respons) ?? nama);
                this.keadaan = 'selesai';
                window.dispatchEvent(new CustomEvent('notify', { detail: { message: `PDF diunduh (${ukuranBerkas(berkas.size)}).`, type: 'success' } }));
                window.setTimeout(() => {
                    if (this.keadaan === 'selesai') {
                        this.keadaan = 'siap';
                    }
                }, 2500);
            } catch (galat) {
                if (galat.name === 'AbortError') {
                    this.keadaan = 'siap';

                    return;
                }
                this.keadaan = 'gagal';
                this.pesan = galat.message || 'PDF gagal diunduh.';
            } finally {
                this.pengendali = null;
            }
        },

        // Baca aliran respons sambil menghitung kemajuan (persen bila server mengirim Content-Length).
        async baca(respons) {
            const total = Number(respons.headers.get('Content-Length')) || 0;
            if (!respons.body || !respons.body.getReader) {
                return new Blob([await respons.arrayBuffer()], { type: 'application/pdf' });
            }
            const pembaca = respons.body.getReader();
            const potongan = [];
            let jumlah = 0;
            for (;;) {
                const { done, value } = await pembaca.read();
                if (done) {
                    break;
                }
                potongan.push(value);
                jumlah += value.length;
                this.diterima = ukuranBerkas(jumlah);
                this.persen = total > 0 ? Math.min(100, Math.round((jumlah / total) * 100)) : null;
            }

            return new Blob(potongan, { type: 'application/pdf' });
        },

        simpan(berkas, namaBerkas) {
            const alamat = URL.createObjectURL(berkas);
            const tautan = document.createElement('a');
            tautan.href = alamat;
            tautan.download = namaBerkas;
            tautan.rel = 'noopener';
            document.body.appendChild(tautan);
            tautan.click();
            tautan.remove();
            window.setTimeout(() => URL.revokeObjectURL(alamat), 10000);
        },

        batal() {
            this.pengendali?.abort();
        },
    };
}
