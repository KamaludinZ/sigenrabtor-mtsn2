import unggahArsip, { galatArsip } from './arsip-final';
import { akhirPekan, angka, galatNip, jumlahRab, nip, rapikanKetikan, rupiah, statusPagu, tanggalPanjang, terbilang } from './format';
import editorCover from './editor-cover';
import catatanKonteks, { aspekTerisi, memuatNip } from './konteks';
import formAi, { adaFormKotor } from './form-ai';
import panelKonsistensi, { periksaKonsistensiBulan } from './konsistensi-bulan';
import gridMatrik, { ringkasanMatrik } from './matrik';
import paguKegiatan from './pagu-kegiatan';
import pemilihBulan from './pemilih-bulan';
import penampilDokumen from './penampil-dokumen';
import penyuntingTeks, { adaDrafSunting, adaSuntingKotor, jagaSunting } from './penyunting-teks';
import pasangSiaranAnggaran from './siaran';
import editorTeksBaku, { isiTeksBaku, penandaAsing } from './teks-baku';
import jumlahTermin, { persenTermin, statusTermin } from './termin';
import unduhPdf, { ukuranBerkas } from './unduh-pdf';
import unduhWord from './unduh-word';

// Helper format tersedia di Alpine sebagai $rupiah(n), $angka(n), $jumlahRab(vol, harga), $rapikanKetikan(teks), $terbilang(n),
// $statusPagu(total, pagu), $ringkasanMatrik(matrik), $nip(teks), $galatNip(teks).
window.SIRABTOR = { angka, rupiah, rapikanKetikan, jumlahRab, terbilang, statusPagu, tanggalPanjang, akhirPekan, aspekTerisi, memuatNip, statusTermin, persenTermin, ringkasanMatrik, periksaKonsistensiBulan, nip, galatNip, isiTeksBaku, penandaAsing, galatArsip, adaFormKotor, adaDrafSunting, adaSuntingKotor, ukuranBerkas };

pasangSiaranAnggaran();

document.addEventListener('alpine:init', () => {
    window.Alpine.magic('rupiah', () => rupiah);
    window.Alpine.magic('angka', () => angka);
    window.Alpine.magic('jumlahRab', () => jumlahRab);
    window.Alpine.magic('rapikanKetikan', () => rapikanKetikan);
    window.Alpine.magic('terbilang', () => terbilang);
    window.Alpine.magic('statusPagu', () => statusPagu);
    window.Alpine.magic('tanggalPanjang', () => tanggalPanjang);
    window.Alpine.magic('akhirPekan', () => akhirPekan);
    window.Alpine.magic('nip', () => nip);
    window.Alpine.magic('galatNip', () => galatNip);
    window.Alpine.magic('ringkasanMatrik', () => ringkasanMatrik);

    window.Alpine.data('pemilihBulan', pemilihBulan);
    window.Alpine.data('editorTeksBaku', editorTeksBaku);
    window.Alpine.data('catatanKonteks', catatanKonteks);
    window.Alpine.data('jumlahTermin', jumlahTermin);
    window.Alpine.data('paguKegiatan', paguKegiatan);
    window.Alpine.data('gridMatrik', gridMatrik);
    window.Alpine.data('panelKonsistensi', panelKonsistensi);
    window.Alpine.data('unggahArsip', unggahArsip);
    window.Alpine.data('editorCover', editorCover);
    window.Alpine.data('formAi', formAi);
    window.Alpine.data('penampilDokumen', penampilDokumen);
    window.Alpine.data('penyuntingTeks', penyuntingTeks);
    window.Alpine.data('unduhPdf', unduhPdf);
    window.Alpine.data('unduhWord', unduhWord);

    // x-jaga-sunting: klik (aksi Livewire) minta konfirmasi selama penyunting teks pratinjau belum disimpan.
    window.Alpine.directive('jaga-sunting', (el) => jagaSunting(el));

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
