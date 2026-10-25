@props([
    'anggaran',
    'kelompok' => 'urutan',
    'gaya' => 'layar',
    'kode' => null,
])

@use('App\Support\Dokumen\AngkaDokumen')
@use('App\Support\Format')
@use('App\Support\Rab\KelompokRincian')
@use('App\Support\Stub\StubData')

@php
    // Komponen baca-saja satu RAB (satu sumber dana). Penyunting interaktif ada di partial livewire.kegiatan.partials.tabel-rincian.
    $dokumen = $gaya === 'dokumen';
    $rincian = $anggaran->hasil->rincian;
    $grup = KelompokRincian::kelompokkan($rincian, $kelompok);
    $berkelompok = $kelompok !== 'urutan' && isset(KelompokRincian::MODE[$kelompok]);
    $tampilKode = $kode ?? collect($rincian)->contains(fn ($item) => filled($item['kode'] ?? null));
    $angka = AngkaDokumen::dari($anggaran->hasil, $anggaran->sumber, StubData::tahunAktif()['tahun'], StubData::madrasah()['nama_singkat']);
    $kolom = $tampilKode ? 7 : 6;
    $sel = $dokumen ? 'border border-stone-400 px-2 py-1' : 'px-3 py-2';
    $volume = fn ($nilai) => Format::angka(is_numeric($nilai) ? $nilai + 0 : 0);
@endphp

{{--
    Tabel rincian belanja satu RAB: No, (Kode), Uraian, Vol, Satuan, Harga Satuan, Jumlah, lalu TOTAL KESELURUHAN dan
    terbilang dari AngkaDokumen. gaya="layar" untuk aplikasi (kartu bila wadahnya sempit), gaya="dokumen" untuk cetak.
--}}
<div {{ $attributes->class(['@container min-w-0']) }} data-testid="tabel-rab-{{ mb_strtolower($anggaran->sumber) }}">
    @if ($rincian === [])
        <p @class(['text-sm text-stone-500', 'rounded-lg border border-dashed border-stone-300 px-4 py-6 text-center' => ! $dokumen])>
            Belum ada rincian belanja RAB {{ $anggaran->sumber }}.
        </p>
    @else
        <table @class([
                'w-full border-collapse text-left',
                'text-[13px]' => $dokumen,
                'hidden text-sm @lg:table' => ! $dokumen,
            ])>
            <caption class="sr-only">Rincian belanja RAB {{ $anggaran->sumber }}</caption>
            <thead @class(['bg-stone-100 text-center font-bold' => $dokumen, 'border-b border-stone-200 text-xs font-semibold text-stone-500' => ! $dokumen])>
                <tr>
                    <th scope="col" class="{{ $sel }} w-10">No</th>
                    @if ($tampilKode)
                        <th scope="col" class="{{ $sel }}">Kode</th>
                    @endif
                    <th scope="col" class="{{ $sel }}">Uraian</th>
                    <th scope="col" class="{{ $sel }} text-right">Vol</th>
                    <th scope="col" class="{{ $sel }}">Satuan</th>
                    <th scope="col" class="{{ $sel }} text-right">Harga Satuan (Rp)</th>
                    <th scope="col" class="{{ $sel }} text-right">Jumlah (Rp)</th>
                </tr>
            </thead>
            @foreach ($grup as $g)
                <tbody @class(['divide-y divide-stone-100' => ! $dokumen]) data-kelompok="{{ $g['kunci'] }}">
                    @if ($berkelompok)
                        <tr @class(['bg-stone-50 font-semibold' => $dokumen, 'bg-brand-50/60 text-xs font-semibold text-brand-900' => ! $dokumen])>
                            <th scope="colgroup" colspan="{{ $kolom }}" class="{{ $sel }} text-left">{{ $g['label'] }} · {{ count($g['item']) }} item</th>
                        </tr>
                    @endif
                    @foreach ($g['item'] as $baris)
                        <tr>
                            <td class="{{ $sel }} text-center tabular-nums">{{ $baris['nomor'] }}.</td>
                            @if ($tampilKode)
                                <td class="{{ $sel }} font-mono text-xs">{{ $baris['kode'] }}</td>
                            @endif
                            <td class="{{ $sel }}">{{ $baris['uraian'] }}</td>
                            <td class="{{ $sel }} text-right tabular-nums">{{ $volume($baris['volume']) }}</td>
                            <td class="{{ $sel }}">{{ $baris['satuan'] }}</td>
                            <td class="{{ $sel }} text-right tabular-nums">{{ Format::angka($baris['harga_satuan']) }}</td>
                            <td class="{{ $sel }} text-right tabular-nums">{{ Format::angka($baris['jumlah']) }}</td>
                        </tr>
                    @endforeach
                    @if ($berkelompok)
                        <tr @class(['italic' => $dokumen, 'bg-stone-50/80 text-xs text-stone-600' => ! $dokumen])>
                            <td colspan="{{ $kolom - 1 }}" class="{{ $sel }} text-right">Subtotal {{ $g['label'] }}</td>
                            <td class="{{ $sel }} text-right font-semibold tabular-nums">{{ Format::angka($g['subtotal']) }}</td>
                        </tr>
                    @endif
                </tbody>
            @endforeach
            <tfoot @class(['font-bold' => $dokumen, 'border-t-2 border-stone-200' => ! $dokumen])>
                <tr>
                    <th scope="row" colspan="{{ $kolom - 1 }}" class="{{ $sel }} text-right uppercase">Total keseluruhan</th>
                    <td class="{{ $sel }} text-right tabular-nums" data-testid="total-tabel-rab">{{ $angka->angkaTabel() }}</td>
                </tr>
                <tr>
                    <td colspan="{{ $kolom }}" @class([$sel, 'text-right italic', 'font-normal' => $dokumen, 'text-xs text-stone-500' => ! $dokumen])>
                        Terbilang: {{ $angka->terbilang() }}
                    </td>
                </tr>
            </tfoot>
        </table>

        @unless ($dokumen)
            {{-- Wadah sempit: kartu per item --}}
            <ul class="divide-y divide-stone-100 rounded-lg bg-white ring-1 ring-stone-200 @lg:hidden">
                @foreach ($grup as $g)
                    @if ($berkelompok)
                        <li class="bg-brand-50/60 px-3 py-1.5 text-xs font-semibold text-brand-900">{{ $g['label'] }} · {{ count($g['item']) }} item</li>
                    @endif
                    @foreach ($g['item'] as $baris)
                        <li class="flex items-start justify-between gap-3 px-3 py-2 text-sm">
                            <span class="min-w-0">
                                <span class="block text-stone-800"><span class="text-stone-400">{{ $baris['nomor'] }}.</span> {{ $baris['uraian'] }}</span>
                                <span class="block text-xs text-stone-500 tabular-nums">{{ $volume($baris['volume']) }} {{ $baris['satuan'] }} × {{ Format::rupiah($baris['harga_satuan']) }}</span>
                            </span>
                            <span class="shrink-0 font-medium text-stone-900 tabular-nums">{{ Format::rupiah($baris['jumlah']) }}</span>
                        </li>
                    @endforeach
                @endforeach
                <li class="flex items-baseline justify-between gap-3 bg-stone-50 px-3 py-2">
                    <span class="text-xs font-semibold text-stone-600 uppercase">Total</span>
                    <span class="text-right">
                        <span class="block font-bold text-stone-900 tabular-nums">{{ $angka->nominal() }}</span>
                        <span class="block text-xs text-stone-500 italic">{{ $angka->terbilang() }}</span>
                    </span>
                </li>
            </ul>
        @endunless
    @endif
</div>
