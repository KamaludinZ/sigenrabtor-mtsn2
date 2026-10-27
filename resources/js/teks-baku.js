// Kembaran klien App\Support\Pengaturan\TeksBaku (kasus bersama tests/fixtures/teks-baku.json): penanda {tahun} dan
// variabel kegiatan diisi untuk pratinjau saat mengetik di editor latar belakang baku.

const PENANDA = /\{[a-z_]+\}/g;

/**
 * Teks dipecah menjadi potongan biasa & penanda terisi: [{ teks, penanda: bool, kosong: bool }]. $nilai = { penanda: nilai }
 * (TeksBaku::nilaiKegiatan); penanda bernilai null tetap tertulis dengan kosong = true, penanda lain dibiarkan sebagai teks.
 */
export function bagianTeksBaku(teks, tahun, nilai = {}) {
    const sumber = String(teks ?? '');
    const bagian = [];
    let awal = 0;
    for (const cocok of sumber.matchAll(PENANDA)) {
        const penanda = cocok[0];
        if (penanda !== '{tahun}' && !Object.hasOwn(nilai, penanda)) {
            continue;
        }
        if (cocok.index > awal) {
            bagian.push({ teks: sumber.slice(awal, cocok.index), penanda: false, kosong: false });
        }
        const isi = penanda === '{tahun}' ? String(tahun) : nilai[penanda];
        bagian.push({ teks: isi ?? penanda, penanda: true, kosong: isi === null || isi === undefined });
        awal = cocok.index + penanda.length;
    }
    if (awal < sumber.length) {
        bagian.push({ teks: sumber.slice(awal), penanda: false, kosong: false });
    }

    return bagian;
}

/** TeksBaku::isiNilai(): penanda bernilai null dibiarkan. */
export function isiTeksBaku(teks, tahun, nilai = {}) {
    return bagianTeksBaku(teks, tahun, nilai).map((b) => b.teks).join('');
}

/** TeksBaku::penandaAsing(): penanda {…} di luar daftar boleh, urut kemunculan tanpa dobel. */
export function penandaAsing(teks, boleh) {
    return [...new Set(String(teks ?? '').match(/\{[^{}]*\}/gu) ?? [])].filter((p) => !boleh.includes(p));
}

/**
 * Alpine.data('editorTeksBaku', { tahun, nilai }): pratinjau dengan nilai contoh kegiatan, peringatan penanda, dan sisip
 * penanda di posisi kursor (event input agar wire:model ikut berubah).
 */
export default function editorTeksBaku({ tahun, nilai }) {
    return {
        bagian(teks, pakaiNilai = false) {
            return bagianTeksBaku(teks, tahun, pakaiNilai ? nilai : {});
        },
        asing(teks, boleh) {
            return penandaAsing(teks, boleh);
        },
        memuat(teks, penanda) {
            return String(teks ?? '').includes(penanda);
        },
        sisip(ref, penanda) {
            const el = this.$refs[ref];
            el.focus();
            el.setRangeText(penanda, el.selectionStart, el.selectionEnd, 'end');
            el.dispatchEvent(new Event('input', { bubbles: true }));
        },
    };
}
