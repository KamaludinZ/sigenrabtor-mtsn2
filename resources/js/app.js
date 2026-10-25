import { angka, jumlahRab, rapikanKetikan, rupiah, terbilang } from './format';

// Helper format tersedia di Alpine sebagai $rupiah(n), $angka(n), $jumlahRab(vol, harga), $rapikanKetikan(teks), $terbilang(n).
window.SIGENRABTOR = { angka, rupiah, rapikanKetikan, jumlahRab, terbilang };

document.addEventListener('alpine:init', () => {
    window.Alpine.magic('rupiah', () => rupiah);
    window.Alpine.magic('angka', () => angka);
    window.Alpine.magic('jumlahRab', () => jumlahRab);
    window.Alpine.magic('rapikanKetikan', () => rapikanKetikan);
    window.Alpine.magic('terbilang', () => terbilang);

    // x-angka-bulat: isian angka bulat — tolak ketikan e/+/-/titik/koma dan sisakan digit saat menempel ("1.000" → "1000").
    window.Alpine.directive('angka-bulat', (el) => {
        el.addEventListener('keydown', (e) => {
            if (['e', 'E', '+', '-', '.', ','].includes(e.key)) {
                e.preventDefault();
            }
        });
        el.addEventListener('paste', (e) => {
            const teks = e.clipboardData?.getData('text') ?? '';
            const digit = teks.replace(/\D/g, '');

            if (digit !== teks) {
                e.preventDefault();
                el.value = digit;
                el.dispatchEvent(new Event('input', { bubbles: true }));
            }
        });
    });
});
