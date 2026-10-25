@props(['susunan'])

{{--
    Pratinjau KAK/TOR bergaya kertas A4: cover lalu sembilan bagian baku dari App\Support\Dokumen\SusunanKak.
    Isian yang belum lengkap tampil sebagai penanda kuning (bukan teks palsu); bagian dari fitur yang belum tersedia
    tampil abu-abu. Cap "DRAF" muncul selama kegiatan belum disetujui atau belum lengkap.
--}}
<article {{ $attributes->class(['relative mx-auto w-full max-w-[210mm] overflow-hidden bg-white font-serif text-[15px] leading-relaxed text-stone-900 shadow-sm ring-1 ring-stone-200']) }}
         data-testid="pratinjau-kak">
    @if ($susunan->draf)
        <div class="pointer-events-none absolute inset-0 flex items-start justify-center overflow-hidden pt-64 select-none" aria-hidden="true">
            <span class="-rotate-30 text-[7rem] font-bold tracking-[0.2em] text-stone-900/[0.06]">DRAF</span>
        </div>
    @endif

    {{-- Cover --}}
    <header class="border-b border-stone-200 px-8 py-12 text-center sm:px-14" data-testid="pratinjau-kak-cover">
        <p class="text-sm font-bold tracking-wide uppercase">{{ $susunan->cover['judul'] }}</p>
        <h2 class="mx-auto mt-4 max-w-xl text-lg font-bold uppercase">{{ $susunan->cover['kegiatan'] }}</h2>
        <p class="mt-4 text-sm font-semibold uppercase">{{ $susunan->cover['madrasah'] }}</p>
        <p class="text-sm font-semibold uppercase">{{ $susunan->cover['tahun'] }}</p>
        @if ($susunan->cover['sumber'])
            <p class="mt-3 inline-block rounded border border-stone-300 px-2 py-0.5 font-sans text-xs font-semibold tracking-wide text-stone-600">{{ $susunan->cover['sumber'] }}</p>
        @endif
        @if ($susunan->draf)
            <p class="mt-3 font-sans text-xs font-semibold tracking-[0.3em] text-red-700">DRAF</p>
        @endif
    </header>

    <div class="space-y-6 px-8 py-10 sm:px-14">
        @foreach ($susunan->bagian as $bagian)
            <section data-bagian="{{ $bagian['nomor'] }}">
                <h3 class="font-bold uppercase">{{ $bagian['nomor'] }}. {{ $bagian['judul'] }}</h3>
                <div class="mt-2 space-y-2 pl-5">
                    @foreach ($bagian['isi'] as $blok)
                        @switch($blok['jenis'])
                            @case('paragraf')
                                <p class="indent-8 sm:text-justify">{{ $blok['teks'] }}</p>
                                @break
                            @case('subjudul')
                                <p class="font-semibold">{{ $blok['teks'] }}</p>
                                @break
                            @case('daftar')
                                <ol @class(['space-y-1 pl-6', 'list-decimal' => $blok['bernomor'], 'list-disc' => ! $blok['bernomor']])>
                                    @foreach ($blok['butir'] as $butir)
                                        <li class="sm:text-justify">{{ $butir }}</li>
                                    @endforeach
                                </ol>
                                @break
                            @case('kosong')
                                <p class="flex items-start gap-1.5 rounded bg-gold-50 px-2 py-1 font-sans text-sm text-gold-700 ring-1 ring-gold-200" data-kosong>
                                    <x-ui.icon name="alert" class="mt-0.5 size-4 shrink-0" />
                                    {{ $blok['pesan'] }}
                                </p>
                                @break
                            @case('menunggu')
                                <p class="flex items-start gap-1.5 rounded bg-stone-50 px-2 py-1 font-sans text-sm text-stone-500 ring-1 ring-stone-200" data-menunggu>
                                    <x-ui.icon name="calendar" class="mt-0.5 size-4 shrink-0" />
                                    {{ $blok['pesan'] }}
                                </p>
                                @break
                            @case('pengesahan')
                                <div @class(['grid gap-6 pt-4 text-center text-sm sm:gap-4', 'sm:grid-cols-3' => count($blok['kolom']) === 3, 'sm:grid-cols-2' => count($blok['kolom']) === 2])>
                                    @foreach ($blok['kolom'] as $ttd)
                                        <div class="flex flex-col">
                                            <p class="min-h-5">{{ $ttd['tempat_tanggal'] }}</p>
                                            <p>{{ $ttd['peran'] }}</p>
                                            <p class="font-semibold">{{ $ttd['jabatan'] }}</p>
                                            <div class="h-16" aria-hidden="true"></div>
                                            <p @class(['font-semibold underline underline-offset-2' => $ttd['nama'], 'text-stone-400' => ! $ttd['nama']])>
                                                {{ $ttd['nama'] ?? '( …………………………… )' }}
                                            </p>
                                            <p class="text-stone-600">NIP. {{ $ttd['nip'] ?? '……………………' }}</p>
                                        </div>
                                    @endforeach
                                </div>
                                @break
                        @endswitch
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
</article>
