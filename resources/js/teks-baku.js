// Kembaran klien App\Support\Pengaturan\TeksBaku (kasus bersama tests/fixtures/teks-baku.json): penanda {tahun} dan
// {nama_kegiatan} diisi untuk pratinjau saat mengetik di editor latar belakang baku.

const PENANDA = /\{tahun\}|\{nama_kegiatan\}/g;

/** "Kegiatan Workshop …" → "Workshop …" (KalimatKak::tanpaAwalanKegiatan). */
function tanpaAwalanKegiatan(nama) {
    return String(nama).trim().replace(/^kegiatan\s+/iu, '').trim();
}

/**
 * Teks dipecah menjadi potongan biasa & nilai penanda: [{ teks, penanda: bool }]. Nama null = {nama_kegiatan} dibiarkan.
 */
export function bagianTeksBaku(teks, tahun, nama = null) {
    const sumber = String(teks ?? '');
    const bagian = [];
    let awal = 0;
    for (const cocok of sumber.matchAll(PENANDA)) {
        if (cocok[0] === '{nama_kegiatan}' && nama === null) {
            continue;
        }
        if (cocok.index > awal) {
            bagian.push({ teks: sumber.slice(awal, cocok.index), penanda: false });
        }
        bagian.push({ teks: cocok[0] === '{tahun}' ? String(tahun) : tanpaAwalanKegiatan(nama), penanda: true });
        awal = cocok.index + cocok[0].length;
    }
    if (awal < sumber.length) {
        bagian.push({ teks: sumber.slice(awal), penanda: false });
    }

    return bagian;
}

/** TeksBaku::isi(). */
export function isiTeksBaku(teks, tahun, nama = null) {
    return bagianTeksBaku(teks, tahun, nama).map((b) => b.teks).join('');
}

/** TeksBaku::penandaAsing(): penanda {…} di luar daftar boleh, urut kemunculan tanpa dobel. */
export function penandaAsing(teks, boleh) {
    return [...new Set(String(teks ?? '').match(/\{[^{}]*\}/gu) ?? [])].filter((p) => !boleh.includes(p));
}

/**
 * Alpine.data('editorTeksBaku', { tahun, contoh }): pratinjau, peringatan penanda, dan sisip penanda di posisi kursor
 * (event input agar wire:model ikut berubah).
 */
export default function editorTeksBaku({ tahun, contoh }) {
    return {
        bagian(teks, pakaiNama = false) {
            return bagianTeksBaku(teks, tahun, pakaiNama ? contoh : null);
        },
        asing(teks, boleh) {
            return penandaAsing(teks, boleh);
        },
        sisip(ref, penanda) {
            const el = this.$refs[ref];
            el.focus();
            el.setRangeText(penanda, el.selectionStart, el.selectionEnd, 'end');
            el.dispatchEvent(new Event('input', { bubbles: true }));
        },
    };
}
