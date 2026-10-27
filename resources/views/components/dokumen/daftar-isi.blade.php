@props(['halaman', 'nomor' => null, 'posisi' => 'bawah_tengah'])

@use('App\Support\Dokumen\DaftarIsi')
@use('App\Support\Format')

{{--
    Halaman "Daftar Kegiatan" (daftar isi) buku A4 dari DaftarIsi::halaman(): judul, subjudul, tabel kolom terpilih, baris
    jumlah, catatan. Kolom Halaman memakai `halaman` tiap baris (simulasi NomorHalaman) atau "…" bila belum diketahui;
    $nomor = label nomor halaman ini sendiri ($posisi: NomorHalaman::POSISI).
--}}
<article {{ $attributes->class(['relative mx-auto aspect-[210/297] w-full bg-white px-[8%] py-[9%] font-serif text-stone-900 shadow-sm ring-1 ring-stone-200']) }} data-testid="pratinjau-daftar-isi">
    <h3 class="text-center text-sm font-bold tracking-wide uppercase" data-testid="di-judul-tercetak">{{ $halaman['judul'] }}</h3>
    @if ($halaman['subjudul'] !== '')
        <p class="mt-1 text-center text-[11px]" data-testid="di-subjudul-tercetak">{{ $halaman['subjudul'] }}</p>
    @endif
    <table class="mt-4 w-full border-collapse text-[10px] leading-snug">
        <thead>
            <tr>
                @foreach ($halaman['kolom'] as $kolom)
                    <th scope="col" @class(['border border-stone-500 px-1 py-0.5 font-semibold', 'text-left' => in_array($kolom, ['nama_kegiatan', 'bulan'], true), 'text-right' => $kolom === 'total', 'text-center' => in_array($kolom, ['no', 'kode_rktm', 'halaman'], true)])
                        data-kolom-tercetak="{{ $kolom }}">{{ DaftarIsi::KOLOM[$kolom][0] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($halaman['baris'] as $baris)
                <tr data-baris-daftar-isi>
                    @foreach ($halaman['kolom'] as $kolom)
                        <td @class(['border border-stone-500 px-1 py-0.5 align-top', 'text-center' => in_array($kolom, ['no', 'kode_rktm', 'halaman'], true), 'text-right tabular-nums' => $kolom === 'total']) @if ($kolom === 'halaman') data-halaman-tercetak @endif>
                            @switch($kolom)
                                @case('total') {{ Format::angka($baris['total']) }} @break
                                @case('halaman') {{ $baris['halaman'] ?? '…' }} @break
                                @case('kode_rktm') {{ $baris['kode_rktm'] ?? '—' }} @break
                                @case('bulan') {{ $baris['bulan'] ?? '—' }} @break
                                @default {{ $baris[$kolom] }}
                            @endswitch
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($halaman['kolom']) }}" class="border border-stone-500 px-1 py-3 text-center font-sans text-stone-400 italic">Belum ada kegiatan di buku ini.</td>
                </tr>
            @endforelse
            @if ($halaman['jumlah'] !== null && $halaman['baris'] !== [])
                @php
                    $posisiTotal = array_search('total', $halaman['kolom'], true);
                @endphp
                <tr class="font-semibold" data-testid="di-baris-jumlah">
                    <td colspan="{{ $posisiTotal }}" class="border border-stone-500 px-1 py-0.5 text-right">Jumlah</td>
                    <td class="border border-stone-500 px-1 py-0.5 text-right tabular-nums">{{ Format::angka($halaman['jumlah']) }}</td>
                    @if (count($halaman['kolom']) - $posisiTotal - 1 > 0)
                        <td colspan="{{ count($halaman['kolom']) - $posisiTotal - 1 }}" class="border border-stone-500"></td>
                    @endif
                </tr>
            @endif
        </tbody>
    </table>
    @if ($halaman['catatan'] !== '')
        <p class="mt-2 text-[9px] text-stone-600 italic" data-testid="di-catatan-tercetak">{{ $halaman['catatan'] }}</p>
    @endif
    @if ($nomor)
        <p @class(['absolute text-[9px] text-stone-600', 'bottom-[4%] inset-x-0 text-center' => $posisi === 'bawah_tengah', 'bottom-[4%] right-[8%]' => $posisi === 'bawah_kanan', 'top-[4%] right-[8%]' => $posisi === 'atas_kanan']) data-testid="nomor-halaman-daftar-isi">{{ $nomor }}</p>
    @endif
</article>
