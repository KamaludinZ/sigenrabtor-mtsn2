// Penjaga sesi untuk aksi Livewire (pasangan middleware WajibMasuk & HanyaAdmin yang persisten): jawaban 401 (belum/sudah
// tidak masuk) mengalihkan ke halaman Masuk ber-`?sesi=berakhir&kembali=<halaman ini>`, 419 (token kedaluwarsa) memuat
// ulang halaman, 403 menampilkan notifikasi — menggantikan dialog/modal bawaan Livewire yang berbahasa Inggris.
export const PESAN_TERLARANG = 'Anda tidak punya akses untuk tindakan ini.';

/** URL halaman Masuk untuk kembali ke $lokasi sesudah masuk. */
export function tautanMasuk(dasar, lokasi) {
    const tujuan = new URL(dasar || '/masuk', lokasi.origin);
    tujuan.searchParams.set('sesi', 'berakhir');
    tujuan.searchParams.set('kembali', `${lokasi.pathname}${lokasi.search}`);

    return tujuan.toString();
}

export default function pasangPenjagaSesi() {
    document.addEventListener('livewire:init', () => {
        window.Livewire.hook('request', ({ fail }) => {
            fail(({ status, content, preventDefault }) => {
                if (status === 401) {
                    preventDefault();
                    let masuk = null;
                    try {
                        masuk = JSON.parse(content ?? '{}').masuk ?? null;
                    } catch {
                        masuk = null;
                    }
                    window.location.href = tautanMasuk(masuk, window.location);
                } else if (status === 419) {
                    preventDefault();
                    window.location.reload();
                } else if (status === 403) {
                    preventDefault();
                    window.dispatchEvent(new CustomEvent('notify', { detail: { message: PESAN_TERLARANG, type: 'error' } }));
                }
            });
        });
    });
}
