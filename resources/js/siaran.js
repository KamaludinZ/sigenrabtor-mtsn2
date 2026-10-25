// Tiruan pembaruan real-time antar-tab (sebelum ada WebSocket): komponen Livewire mengabarkan "anggaran-berubah"
// (RAB atau pagu sebuah kegiatan berubah), lalu kabar itu disiarkan lewat BroadcastChannel ke tab lain di peramban yang
// sama. Tab penerima meneruskannya sebagai event Livewire "anggaran-diperbarui" sehingga Data Kegiatan, RAB, dan daftar
// kegiatan menghitung ulang status pagunya tanpa dimuat ulang.
export const KANAL_ANGGARAN = 'sirabtor-anggaran';

export default function pasangSiaranAnggaran() {
    if (!('BroadcastChannel' in window)) {
        return;
    }

    const kanal = new BroadcastChannel(KANAL_ANGGARAN);

    window.addEventListener('anggaran-berubah', (event) => {
        const kegiatan = Number(event.detail?.kegiatan);

        if (kegiatan > 0) {
            kanal.postMessage({ kegiatan });
        }
    });

    kanal.addEventListener('message', (event) => {
        const kegiatan = Number(event.data?.kegiatan);

        if (kegiatan > 0) {
            window.Livewire?.dispatch('anggaran-diperbarui', { kegiatan });
        }
    });
}
