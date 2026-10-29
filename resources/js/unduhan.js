// Penanganan galat & notifikasi bersama semua unduhan yang disusun/diambil di peramban (PDF `unduhPdf`, Word `unduhWord`,
// Excel `unduhExcel`): satu pintu permintaan `ambil()` (batas waktu, pemeriksaan luring, jawaban JSON galat server dibaca
// pesannya), penggolongan galat `GalatUnduhan` berjenis `JENIS` dengan pesan yang menjelaskan apa yang harus dilakukan,
// pemuat pustaka `muatPustaka()` (chunk gagal dimuat, mis. sesudah aplikasi diperbarui → minta muat ulang), dan
// notifikasi `beritahu()` ke toast (`notify`). Semua pesan di sini agar ketiga unduhan berbicara dengan bahasa yang sama.

export const BATAS_WAKTU = 90_000;

export const JENIS = {
    luring: 'Koneksi internet terputus — periksa jaringan, lalu coba lagi.',
    jaringan: 'Server tidak dapat dihubungi — periksa jaringan atau coba lagi sebentar lagi.',
    waktu_habis: 'Waktu menunggu server habis — dokumen besar butuh waktu lebih lama; coba lagi.',
    sesi: 'Sesi Anda sudah berakhir — muat ulang halaman, lalu coba lagi.',
    akses: 'Anda tidak berhak mengunduh dokumen ini.',
    tidak_ada: 'Dokumen tidak ditemukan — mungkin sudah dihapus atau sumber dananya berubah. Muat ulang halaman.',
    sibuk: 'Terlalu banyak permintaan dalam waktu singkat — tunggu sebentar, lalu coba lagi.',
    server: 'Server sedang bermasalah saat menyiapkan berkas — coba lagi sebentar lagi.',
    berkas: 'Server tidak mengirim berkas yang diharapkan — coba lagi.',
    pustaka: 'Komponen unduhan gagal dimuat — aplikasi mungkin baru diperbarui. Muat ulang halaman, lalu coba lagi.',
    susun: 'Berkas gagal disusun di peramban — coba lagi atau gunakan unduhan cadangan.',
    batal: 'Unduhan dibatalkan.',
};

export class GalatUnduhan extends Error {
    constructor(jenis, pesan = null, tambahan = {}) {
        super(pesan ?? JENIS[jenis] ?? JENIS.server);
        this.name = 'GalatUnduhan';
        this.jenis = jenis;
        Object.assign(this, tambahan);
    }
}

// Jenis galat dari kode status HTTP.
export function jenisStatus(status) {
    if (status === 401 || status === 419) {
        return 'sesi';
    }
    if (status === 403) {
        return 'akses';
    }
    if (status === 404 || status === 410) {
        return 'tidak_ada';
    }
    if (status === 429) {
        return 'sibuk';
    }
    if (status === 408 || status === 504) {
        return 'waktu_habis';
    }

    return 'server';
}

// Galat apa pun → GalatUnduhan (galat lain yang bukan dari jaringan dianggap gagal menyusun berkas).
export function golongkan(galat) {
    if (galat instanceof GalatUnduhan) {
        return galat;
    }
    if (galat?.name === 'AbortError') {
        return new GalatUnduhan('batal');
    }
    if (typeof navigator !== 'undefined' && navigator.onLine === false) {
        return new GalatUnduhan('luring');
    }

    return new GalatUnduhan(galat instanceof TypeError ? 'jaringan' : 'susun');
}

// fetch dengan batas waktu & pembatalan; jawaban tidak ok → GalatUnduhan berpesan server (JSON {pesan|message}) bila ada,
// kecuali sesi/akses yang selalu memakai pesan baku. `jenisBerkas` = awal Content-Type yang diharapkan (null = bebas).
export async function ambil(url, { accept = 'application/json', jenisBerkas = null, sinyal = null, batasWaktu = BATAS_WAKTU } = {}) {
    if (typeof navigator !== 'undefined' && navigator.onLine === false) {
        throw new GalatUnduhan('luring');
    }
    const pengendali = new AbortController();
    let habis = false;
    const pewaktu = window.setTimeout(() => {
        habis = true;
        pengendali.abort();
    }, batasWaktu);
    sinyal?.addEventListener('abort', () => pengendali.abort(), { once: true });
    try {
        const respons = await fetch(url, {
            headers: { Accept: accept, 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            signal: pengendali.signal,
        });
        const tipe = respons.headers.get('Content-Type') ?? '';
        if (!respons.ok) {
            const jenis = jenisStatus(respons.status);
            const isi = tipe.includes('json') ? await respons.json().catch(() => ({})) : {};
            const pesan = ['sesi', 'akses'].includes(jenis) ? null : (isi.pesan ?? isi.message ?? null);
            throw new GalatUnduhan(jenis, pesan, { status: respons.status, tautanCetak: isi.tautan_cetak ?? null });
        }
        if (jenisBerkas && !tipe.includes(jenisBerkas)) {
            throw new GalatUnduhan(respons.redirected ? 'sesi' : 'berkas');
        }

        return respons;
    } catch (galat) {
        if (galat?.name === 'AbortError') {
            throw new GalatUnduhan(habis ? 'waktu_habis' : 'batal');
        }
        throw golongkan(galat);
    } finally {
        window.clearTimeout(pewaktu);
    }
}

// Muat chunk pustaka penyusun (import dinamis); gagal → GalatUnduhan 'pustaka'.
export async function muatPustaka(pemuat) {
    try {
        return await pemuat();
    } catch {
        throw new GalatUnduhan('pustaka');
    }
}

export function beritahu(pesan, jenis = 'success') {
    window.dispatchEvent(new CustomEvent('notify', { detail: { message: pesan, type: jenis } }));
}

// Laporkan galat unduhan: notifikasi galat (kecuali dibatalkan) + kembalikan pesannya untuk ditampilkan di tempat.
export function laporkan(galat, apa) {
    const g = golongkan(galat);
    if (g.jenis !== 'batal') {
        beritahu(`${apa} gagal: ${g.message}`, 'error');
    }

    return g;
}
