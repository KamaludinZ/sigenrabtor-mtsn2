// Konsistensi bulan Matrik & Jadwal — kembaran App\Support\Kegiatan\KonsistensiBulan (pesan harus sama persis; kasus
// bersama tests/fixtures/konsistensi-bulan.json). Dihitung di klien karena tanda matrik adalah state lokal yang baru
// terkirim saat Simpan; panel ikut berubah tiap sel ditandai atau baris jadwal diubah.

import { NAMA_BULAN } from './format';

/** Persiapan wajar dimulai paling awal sekian bulan sebelum pelaksanaan (KonsistensiBulan::BATAS_PERSIAPAN). */
export const BATAS_PERSIAPAN = 3;

const urut = (bulan) => [...new Set(Object.values(bulan ?? {}).map(Number))].filter((b) => b >= 1 && b <= 12).sort((a, b) => a - b);
const daftar = (nilai) => (Array.isArray(nilai) ? nilai : Object.values(nilai ?? {}));
const nama = (bulan) => bulan.map((b) => NAMA_BULAN[b - 1]).join(', ');
const temuanBaru = (kunci, tautan, tingkat, pesan) => ({ kunci, tautan, tingkat, pesan });

/** Baris jadwal yang sedang disunting {mulai, sampai} → daftar bulan, seperti MatrikJadwal::jadwalUntukDisimpan(). */
export function bulanBarisJadwal(baris) {
    const mulai = Number(baris?.mulai);
    const sampai = Number(baris?.sampai);

    if (!Number.isInteger(mulai) || mulai < 1 || mulai > 12) {
        return [];
    }

    const akhir = Number.isInteger(sampai) && sampai > mulai && sampai <= 12 ? sampai : mulai;

    return Array.from({ length: akhir - mulai + 1 }, (_, i) => mulai + i);
}

/**
 * @param {{ pelaksanaan: number[], laporan: number|null, matrik: Object, jadwal: Array<{bulan: number[]}> }} data
 * @returns {Array<{kunci: string, tautan: string, tingkat: 'peringatan'|'saran', pesan: string}>}
 */
export function periksaKonsistensiBulan({ pelaksanaan, laporan = null, matrik = {}, jadwal = [] }) {
    const bulanPelaksanaan = urut(pelaksanaan);

    if (bulanPelaksanaan.length === 0) {
        return [temuanBaru('tanpa-bulan', 'acuan', 'saran', 'Bulan pelaksanaan belum diisi di Data Kegiatan — konsistensi bulan belum bisa diperiksa.')];
    }

    const awal = bulanPelaksanaan[0];
    const teksAwal = nama([awal]);
    const teksLaporan = laporan === null ? null : nama([laporan]);
    const sebelum = (bulan, batas) => bulan.filter((b) => b < batas);
    const sesudah = (bulan, batas) => (batas === null ? [] : bulan.filter((b) => b > batas));
    const selisih = (a, b) => a.filter((x) => !b.includes(x));
    const tahap = (kunci) => urut(daftar(matrik?.[kunci]).flatMap((b) => daftar(b?.bulan)));
    const [persiapan, tahapPelaksanaan, evaluasi] = [tahap('persiapan'), tahap('pelaksanaan'), tahap('evaluasi_pelaporan')];
    const hasil = [];
    let kurang;
    let dini;
    let lewat;

    if (tahapPelaksanaan.length > 0 && (kurang = selisih(bulanPelaksanaan, tahapPelaksanaan)).length > 0) {
        hasil.push(temuanBaru('matrik-pelaksanaan-kurang', 'matrik', 'peringatan', `Tahap Pelaksanaan belum ditandai di ${nama(kurang)} (bulan pelaksanaan).`));
    }
    if ((dini = sebelum(tahapPelaksanaan, awal - 1)).length > 0) {
        hasil.push(temuanBaru('matrik-pelaksanaan-dini', 'matrik', 'peringatan', `Tahap Pelaksanaan ditandai di ${nama(dini)}, jauh sebelum bulan pelaksanaan (${teksAwal}).`));
    }
    if ((lewat = sesudah(tahapPelaksanaan, laporan)).length > 0) {
        hasil.push(temuanBaru('matrik-pelaksanaan-lewat', 'matrik', 'peringatan', `Tahap Pelaksanaan ditandai di ${nama(lewat)}, melewati bulan batas laporan (${teksLaporan}).`));
    }
    if ((lewat = sesudah(persiapan, awal)).length > 0) {
        hasil.push(temuanBaru('matrik-persiapan-sesudah', 'matrik', 'peringatan', `Tahap Persiapan ditandai di ${nama(lewat)}, sesudah pelaksanaan dimulai (${teksAwal}).`));
    }
    if ((dini = sebelum(persiapan, awal - BATAS_PERSIAPAN)).length > 0) {
        hasil.push(temuanBaru('matrik-persiapan-dini', 'matrik', 'saran', `Tahap Persiapan ditandai di ${nama(dini)}, lebih dari ${BATAS_PERSIAPAN} bulan sebelum pelaksanaan (${teksAwal}).`));
    }
    if ((dini = sebelum(evaluasi, awal)).length > 0) {
        hasil.push(temuanBaru('matrik-evaluasi-sebelum', 'matrik', 'peringatan', `Tahap Evaluasi dan Pelaporan ditandai di ${nama(dini)}, sebelum pelaksanaan dimulai (${teksAwal}).`));
    }
    if ((lewat = sesudah(evaluasi, laporan)).length > 0) {
        hasil.push(temuanBaru('matrik-evaluasi-lewat', 'matrik', 'peringatan', `Tahap Evaluasi dan Pelaporan ditandai di ${nama(lewat)}, melewati bulan batas laporan (${teksLaporan}).`));
    }

    const barisJadwal = daftar(jadwal).map((b) => urut(daftar(b?.bulan)));
    const bulanJadwal = urut(barisJadwal.flat());

    if (bulanJadwal.length > 0 && (kurang = selisih(bulanPelaksanaan, bulanJadwal)).length > 0) {
        hasil.push(temuanBaru('jadwal-pelaksanaan-kurang', 'jadwal', 'peringatan', `Jadwal belum memuat bulan pelaksanaan (${nama(kurang)}).`));
    }

    let sebelumnya = null;
    barisJadwal.forEach((bulan, i) => {
        if (bulan.length === 0) {
            return;
        }

        const nomor = i + 1;
        let x;
        if ((x = sesudah(bulan, laporan)).length > 0) {
            hasil.push(temuanBaru(`jadwal-${nomor}-lewat`, `baris-jadwal-${nomor}`, 'peringatan', `Baris ${nomor} dijadwalkan ${nama(x)}, melewati bulan batas laporan (${teksLaporan}).`));
        }
        if ((x = sebelum(bulan, awal - BATAS_PERSIAPAN)).length > 0) {
            hasil.push(temuanBaru(`jadwal-${nomor}-dini`, `baris-jadwal-${nomor}`, 'peringatan', `Baris ${nomor} dijadwalkan ${nama(x)}, lebih dari ${BATAS_PERSIAPAN} bulan sebelum pelaksanaan (${teksAwal}) — mungkin tersalin dari kegiatan lain.`));
        }
        if (sebelumnya !== null && bulan[0] < sebelumnya.mulai) {
            hasil.push(temuanBaru(`jadwal-${nomor}-urutan`, `baris-jadwal-${nomor}`, 'saran', `Baris ${nomor} (${nama([bulan[0]])}) lebih awal dari baris ${sebelumnya.nomor} (${nama([sebelumnya.mulai])}) — urutkan agar kronologis.`));
        }

        sebelumnya = { nomor, mulai: bulan[0] };
    });

    const bulanMatrik = urut([...persiapan, ...tahapPelaksanaan, ...evaluasi]);

    if (bulanMatrik.length > 0 && bulanJadwal.length > 0) {
        const hanyaJadwal = selisih(bulanJadwal, bulanMatrik);
        const hanyaMatrik = selisih(bulanMatrik, bulanJadwal);

        if (hanyaJadwal.length > 0) {
            hasil.push(temuanBaru('jadwal-tanpa-matrik', 'jadwal', 'saran', `Jadwal memuat ${nama(hanyaJadwal)} yang tidak ditandai di matrik tahapan.`));
        }
        if (hanyaMatrik.length > 0) {
            hasil.push(temuanBaru('matrik-tanpa-jadwal', 'matrik', 'saran', `Matrik tahapan menandai ${nama(hanyaMatrik)} yang tidak ada di jadwal.`));
        }
    }

    return hasil;
}

/**
 * Panel "Konsistensi bulan": temuan dihitung ulang dari $wire.matrik (state lokal) dan $wire.jadwal tiap kali berubah.
 * Acuan (bulan pelaksanaan & bulan batas laporan) dari Data Kegiatan tidak berubah di halaman ini.
 */
export default function panelKonsistensi({ pelaksanaan = [], laporan = null } = {}) {
    return {
        siap: false,

        init() {
            this.siap = true;
        },

        get temuan() {
            return periksaKonsistensiBulan({
                pelaksanaan,
                laporan,
                matrik: this.$wire.matrik,
                jadwal: daftar(this.$wire.jadwal).map((b) => ({ bulan: bulanBarisJadwal(b) })),
            });
        },

        /** Kembaran KonsistensiBulan::adaIsian(): ada bulan bertanda di matrik atau jadwal. */
        get adaIsian() {
            const matrik = Object.values(this.$wire.matrik ?? {}).flatMap((butir) => daftar(butir).flatMap((b) => daftar(b?.bulan)));

            return urut(matrik).length > 0 || daftar(this.$wire.jadwal).some((b) => bulanBarisJadwal(b).length > 0);
        },

        get jumlahPeringatan() {
            return this.temuan.filter((t) => t.tingkat === 'peringatan').length;
        },
    };
}
