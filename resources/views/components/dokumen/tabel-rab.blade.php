@props(['tabel'])

@use('App\Support\Dokumen\TabelRab')

@php
    $sel = 'border border-stone-400 px-2 py-1';
    $rata = ['kiri' => 'text-left', 'tengah' => 'text-center', 'kanan' => 'text-right tabular-nums'];
    $jumlahKolom = count($tabel->kolom);
@endphp

{{--
    Tabel rincian RAB dokumen (gaya Form RAB) dari App\Support\Dokumen\TabelRab — struktur yang sama dengan ekspor Excel.
    Lebar kolom mengikuti lebar isi halaman A4 (colgroup), kepala tabel berulang di tiap halaman cetak, baris tidak
    terbelah antarhalaman; tiap baris berjangkar `cetak-rab-baris-{n}` dan berkait `data-baris` (id item) untuk penyuntingan.
--}}
<div {{ $attributes->class(['min-w-0']) }} data-testid="tabel-rab-{{ mb_strtolower($tabel->sumber ?? 'tanpa-sumber') }}" data-jumlah-baris="{{ count($tabel->baris) }}">
    <table class="w-full table-fixed border-collapse text-left text-[13px] leading-snug">
        <caption class="sr-only">Rincian belanja RAB {{ $tabel->sumber }}</caption>
        <colgroup>
            @foreach ($tabel->lebar() as $kolom => $persen)
                <col data-kolom="{{ $kolom }}" @if ($persen !== null) style="width: {{ $persen }}%" @endif>
            @endforeach
        </colgroup>
        <thead class="bg-stone-100 text-center font-bold [display:table-header-group]">
            <tr>
                @foreach ($tabel->kolom as $kolom)
                    <th scope="col" class="{{ $sel }} align-middle">{{ TabelRab::KOLOM[$kolom][0] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($tabel->baris as $baris)
                <tr id="cetak-rab-baris-{{ $baris['nomor'] }}" class="break-inside-avoid scroll-mt-40 transition-colors target:bg-gold-100" data-nomor="{{ $baris['nomor'] }}" @if ($baris['id']) data-baris="{{ $baris['id'] }}" @endif>
                    @foreach ($tabel->kolom as $kolom)
                        <td @class([$sel, 'align-top', $rata[TabelRab::rata($kolom)], 'font-mono text-xs' => $kolom === 'kode', 'break-words' => in_array($kolom, ['uraian', 'satuan'], true)]) data-kolom="{{ $kolom }}">{{ $baris['teks'][$kolom] }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $jumlahKolom }}" class="{{ $sel }} py-3 text-center text-stone-500 italic" data-kosong>Belum ada rincian belanja RAB {{ $tabel->sumber }}.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot class="font-bold">
            <tr id="cetak-rab-total" class="break-inside-avoid scroll-mt-40 transition-colors target:bg-gold-100" data-tempat-cetak="rab-total">
                <th scope="row" colspan="{{ $jumlahKolom - 1 }}" class="{{ $sel }} text-right uppercase">{{ TabelRab::LABEL_TOTAL }}</th>
                <td class="{{ $sel }} text-right tabular-nums" data-testid="total-tabel-rab">{{ $tabel->totalTeks }}</td>
            </tr>
            @if ($tabel->terbilang)
                <tr class="break-inside-avoid">
                    <td colspan="{{ $jumlahKolom }}" class="{{ $sel }} text-right font-normal italic" data-testid="terbilang-tabel-rab">Terbilang: {{ $tabel->terbilang }}</td>
                </tr>
            @endif
        </tfoot>
    </table>
</div>
