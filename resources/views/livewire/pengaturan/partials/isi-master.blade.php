{{--
    Isi master KAK/TOR (PratinjauTemplate) sebagai lembar teks: mode `penanda` menandai variabel terdaftar/tidak, mode `isi`
    mengganti variabel dengan nilai contoh kegiatan (kosong ditandai). Dipakai panel pratinjau master Template Dokumen dan
    pratinjau Format Seragam. Variabel: $isiMaster (PratinjauTemplate), $modeMaster (penanda|isi).
--}}
@php
    $gayaBagian = ['header' => 'border-b border-dashed border-stone-300 pb-3 text-sm text-stone-600', 'isi' => '', 'footer' => 'border-t border-dashed border-stone-300 pt-3 text-sm text-stone-600'];
@endphp
<article class="mx-auto w-full max-w-[210mm] space-y-2 bg-white px-6 py-8 font-serif text-[15px] leading-relaxed text-stone-900 shadow-sm ring-1 ring-stone-200 sm:px-12 sm:py-10" data-testid="isi-master" data-mode="{{ $modeMaster }}">
    @foreach ($isiMaster->paragraf as $paragraf)
        <div class="{{ $gayaBagian[$paragraf['bagian']] }} break-words" data-bagian-master="{{ $paragraf['bagian'] }}">
            @foreach ($paragraf['segmen'] as $s)
                @if ($s['jenis'] === 'teks')
                    {{ $s['teks'] }}
                @elseif (! $s['terdaftar'])
                    <code class="rounded bg-red-50 px-1 font-sans text-[13px] font-semibold text-red-700" title="Tidak terdaftar" data-penanda="tidak-terdaftar">${{ '{' }}{{ $s['nama'] }}{{ '}' }}</code>
                @elseif ($modeMaster === 'penanda')
                    <code class="rounded bg-brand-50 px-1 font-sans text-[13px] font-semibold text-brand-800" title="{{ \App\Support\Dokumen\VariabelTemplate::DAFTAR[$s['nama']][0] }}" data-penanda="terdaftar">${{ '{' }}{{ $s['nama'] }}{{ '}' }}</code>
                @elseif (in_array($s['nilai'], [null, '', []], true))
                    <mark class="rounded bg-gold-100 px-1 font-sans text-[13px] font-semibold text-gold-700" title="Belum ada datanya{{ isset(\App\Support\Dokumen\NilaiVariabel::ASAL[$s['nama']]) ? ' — isi di '.\App\Support\Dokumen\NilaiVariabel::ASAL[$s['nama']] : '' }}" data-isi="kosong">${{ '{' }}{{ $s['nama'] }}{{ '}' }} kosong</mark>
                @elseif (is_array($s['nilai']))
                    <div class="rounded bg-brand-50/60 px-1 text-stone-900" title="${{ '{' }}{{ $s['nama'] }}{{ '}' }}" data-isi="{{ $s['nama'] }}">
                        @if (\App\Support\Dokumen\VariabelTemplate::DAFTAR[$s['nama']][2] === 'daftar')
                            <ol class="list-decimal space-y-0.5 pl-6">
                                @foreach ($s['nilai'] as $butir)
                                    <li>{{ $butir }}</li>
                                @endforeach
                            </ol>
                        @else
                            @foreach ($s['nilai'] as $baris)
                                <span class="block">{{ $baris }}</span>
                            @endforeach
                        @endif
                    </div>
                @else
                    <mark class="rounded bg-brand-50/60 px-0.5 text-stone-900" title="${{ '{' }}{{ $s['nama'] }}{{ '}' }}" data-isi="{{ $s['nama'] }}">{{ $s['nilai'] }}</mark>
                @endif
            @endforeach
        </div>
    @endforeach
</article>
