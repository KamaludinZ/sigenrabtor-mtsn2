import { angka, jumlahRab, rapikanKetikan, rupiah, statusPagu, terbilang } from './format';
import catatanKonteks, { aspekTerisi, memuatNip } from './konteks';
import pemilihBulan from './pemilih-bulan';

// Helper format tersedia di Alpine sebagai $rupiah(n), $angka(n), $jumlahRab(vol, harga), $rapikanKetikan(teks), $terbilang(n),
// $statusPagu(total, pagu).
window.SIRABTOR = { angka, rupiah, rapikanKetikan, jumlahRab, terbilang, statusPagu, aspekTerisi, memuatNip };

document.addEventListener('alpine:init', () => {
    window.Alpine.magic('rupiah', () => rupiah);
    window.Alpine.magic('angka', () => angka);
    window.Alpine.magic('jumlahRab', () => jumlahRab);
    window.Alpine.magic('rapikanKetikan', () => rapikanKetikan);
    window.Alpine.magic('terbilang', () => terbilang);
    window.Alpine.magic('statusPagu', () => statusPagu);

    window.Alpine.data('pemilihBulan', pemilihBulan);
    window.Alpine.data('catatanKonteks', catatanKonteks);

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
