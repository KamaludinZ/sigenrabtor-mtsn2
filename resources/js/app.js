import { angka, jumlahRab, rapikanKetikan, rupiah } from './format';

// Helper format tersedia di Alpine sebagai $rupiah(n), $angka(n), $jumlahRab(vol, harga), $rapikanKetikan(teks).
window.SIGENRABTOR = { angka, rupiah, rapikanKetikan, jumlahRab };

document.addEventListener('alpine:init', () => {
    window.Alpine.magic('rupiah', () => rupiah);
    window.Alpine.magic('angka', () => angka);
    window.Alpine.magic('jumlahRab', () => jumlahRab);
    window.Alpine.magic('rapikanKetikan', () => rapikanKetikan);
});
