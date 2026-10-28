// Tiruan pembaruan real-time antar-tab (sebelum ada WebSocket): komponen Livewire mengabarkan "anggaran-berubah"
// (RAB atau pagu sebuah kegiatan berubah), lalu kabar itu disiarkan lewat BroadcastChannel ke tab lain di peramban yang
// sama. Tab penerima meneruskannya sebagai event Livewire "anggaran-diperbarui" sehingga Data Kegiatan, RAB, dan daftar
// kegiatan menghitung ulang status pagunya tanpa dimuat ulang.
export const KANAL_ANGGARAN = 'sirabtor-anggaran';

// Draf dokumen kegiatan disimpan/dihapus (tab Susun KAK/TOR): "draf-dokumen-berubah" → tab lain "draf-dokumen-diperbarui".
export const KANAL_DRAF = 'sirabtor-draf-dokumen';

function pasangKanal(nama, kabar, terima) {
    const kanal = new BroadcastChannel(nama);

    window.addEventListener(kabar, (event) => {
        const kegiatan = Number(event.detail?.kegiatan);

        if (kegiatan > 0) {
            kanal.postMessage({ kegiatan });
        }
    });

    kanal.addEventListener('message', (event) => {
        const kegiatan = Number(event.data?.kegiatan);

        if (kegiatan > 0) {
            window.Livewire?.dispatch(terima, { kegiatan });
        }
    });
}

export default function pasangSiaranAnggaran() {
    if (!('BroadcastChannel' in window)) {
        return;
    }

    pasangKanal(KANAL_ANGGARAN, 'anggaran-berubah', 'anggaran-diperbarui');
    pasangKanal(KANAL_DRAF, 'draf-dokumen-berubah', 'draf-dokumen-diperbarui');
}
