import { angka, jumlahRab, rapikanKetikan, rupiah, terbilang } from './format';

// Helper format tersedia di Alpine sebagai $rupiah(n), $angka(n), $jumlahRab(vol, harga), $rapikanKetikan(teks), $terbilang(n).
window.SIGENRABTOR = { angka, rupiah, rapikanKetikan, jumlahRab, terbilang };

document.addEventListener('alpine:init', () => {
    window.Alpine.magic('rupiah', () => rupiah);
    window.Alpine.magic('angka', () => angka);
    window.Alpine.magic('jumlahRab', () => jumlahRab);
    window.Alpine.magic('rapikanKetikan', () => rapikanKetikan);
    window.Alpine.magic('terbilang', () => terbilang);
});
