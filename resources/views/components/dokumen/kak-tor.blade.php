@props(['susunan', 'bagian' => null])

{{--
    Pratinjau KAK/TOR bergaya kertas A4: cover lalu sembilan bagian baku dari App\Support\Dokumen\SusunanKak.
    Isian yang belum lengkap tampil sebagai penanda kuning (bukan teks palsu); bagian dari fitur yang belum tersedia
    tampil abu-abu. Cap "DRAF" muncul selama kegiatan belum disetujui, belum lengkap, atau RAB-nya melebihi pagu.
    Penanda pagu di bagian Pembiayaan hanya ada di pratinjau (bukan teks dokumen).
    $bagian (daftar nomor, mis. [4, 7]) menampilkan bagian itu saja tanpa cover — dipakai pratinjau Matrik & Jadwal.
--}}
@php
    $daftarBagian = $bagian === null ? $susunan->bagian : array_values(array_filter($susunan->bagian, fn ($b) => in_array($b['nomor'], $bagian, true)));
@endphp
<article {{ $attributes->class(['relative mx-auto w-full max-w-[210mm] overflow-hidden bg-white font-serif text-[15px] leading-relaxed text-stone-900 shadow-sm ring-1 ring-stone-200']) }}
         data-testid="pratinjau-kak">
    @if ($susunan->kunci)
        <x-dokumen.cap-final :kunci="$susunan->kunci" />
    @endif
    @if ($susunan->draf)
        <div class="pointer-events-none absolute inset-0 flex items-start justify-center overflow-hidden pt-64 select-none" aria-hidden="true">
            <span class="-rotate-30 text-[7rem] font-bold tracking-[0.2em] text-stone-900/[0.06]">DRAF</span>
        </div>
    @endif

    {{-- Cover --}}
    @if ($bagian === null)
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
    @endif

    <div class="space-y-6 px-5 py-8 sm:px-14 sm:py-10">
        @foreach ($daftarBagian as $isiBagian)
            <section data-bagian="{{ $isiBagian['nomor'] }}">
                <h3 class="font-bold uppercase">{{ $isiBagian['nomor'] }}. {{ $isiBagian['judul'] }}</h3>
                <div class="mt-2 space-y-2 pl-5">
                    @foreach ($isiBagian['isi'] as $blok)
                        @switch($blok['jenis'])
                            @case('paragraf')
                                @if (isset($blok['tempat']))
                                    <p id="cetak-{{ $blok['tempat'] }}" class="scroll-mt-40 indent-8 transition-colors sm:text-justify target:bg-gold-100 target:outline-2 target:outline-offset-2 target:outline-gold-400" data-tempat-cetak="{{ $blok['tempat'] }}">{{ $blok['teks'] }}</p>
                                @else
                                    <p class="indent-8 sm:text-justify">{{ $blok['teks'] }}</p>
                                @endif
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
                            @case('pagu')
                                @php
                                    $pagu = $susunan->peringatanPagu;
                                    $gayaPagu = ['bahaya' => 'bg-red-50 text-red-700 ring-red-200', 'perhatian' => 'bg-gold-50 text-gold-700 ring-gold-200', 'info' => 'bg-sky-50 text-sky-800 ring-sky-200'][$pagu->nada()];
                                @endphp
                                <div class="rounded px-2 py-1.5 font-sans text-sm ring-1 {{ $gayaPagu }}" data-testid="penanda-pagu" data-status="{{ $pagu->status->status }}">
                                    <p class="flex items-start gap-1.5 font-semibold">
                                        <x-ui.icon :name="$pagu->nada() === 'info' ? 'info' : 'alert'" class="mt-0.5 size-4 shrink-0" />
                                        {{ $pagu->judul() }}
                                    </p>
                                    <p class="mt-0.5 pl-5.5 text-xs text-stone-500">Penanda ini hanya di pratinjau, tidak ikut tercetak.</p>
                                </div>
                                @break
                            @case('matrik')
                                {{-- Tabel tahapan × bulan 1–12 seperti matrik KAK madrasah ("v" di kolom bulan). --}}
                                <div class="-ml-5 overflow-x-auto sm:ml-0" data-testid="kak-matrik">
                                    <table class="w-full min-w-[32rem] border-collapse text-[13px] leading-snug">
                                        <caption class="sr-only">Matrik tahapan kegiatan per bulan, Tahun Anggaran {{ $blok['tahun'] }}</caption>
                                        <thead>
                                            <tr>
                                                <th scope="col" rowspan="2" class="border border-stone-400 px-2 py-1 text-left font-semibold">Kegiatan</th>
                                                <th scope="colgroup" colspan="12" class="border border-stone-400 px-2 py-1 font-semibold">Bulan ({{ $blok['tahun'] }})</th>
                                            </tr>
                                            <tr>
                                                @foreach (range(1, 12) as $b)
                                                    <th scope="col" class="w-6 border border-stone-400 py-0.5 text-center font-semibold">{{ $b }}</th>
                                                @endforeach
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($blok['tahapan'] as $tahap)
                                                <tr>
                                                    <th scope="rowgroup" colspan="13" class="border border-stone-400 px-2 py-1 text-left font-semibold">{{ $tahap['nomor'] }}. {{ $tahap['judul'] }}</th>
                                                </tr>
                                                @foreach ($tahap['butir'] as $butir)
                                                    <tr data-matrik-butir>
                                                        <th scope="row" class="border border-stone-400 py-1 pr-2 pl-5 text-left font-normal">{{ $butir['uraian'] }}</th>
                                                        @foreach (range(1, 12) as $b)
                                                            <td class="border border-stone-400 text-center">{{ in_array($b, $butir['bulan'], true) ? 'v' : '' }}</td>
                                                        @endforeach
                                                    </tr>
                                                @endforeach
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                @break
                            @case('jadwal')
                                <div class="-ml-5 overflow-x-auto sm:ml-0" data-testid="kak-jadwal">
                                    <table class="w-full border-collapse text-[13px] leading-snug">
                                        <thead>
                                            <tr>
                                                <th scope="col" class="w-10 border border-stone-400 px-2 py-1 font-semibold">No</th>
                                                <th scope="col" class="border border-stone-400 px-2 py-1 text-left font-semibold">Uraian Kegiatan</th>
                                                <th scope="col" class="w-40 border border-stone-400 px-2 py-1 text-left font-semibold">Waktu Pelaksanaan</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($blok['baris'] as $baris)
                                                <tr data-jadwal-baris>
                                                    <td class="border border-stone-400 px-2 py-1 text-center">{{ $baris['nomor'] }}</td>
                                                    <td class="border border-stone-400 px-2 py-1">{{ $baris['uraian'] !== '' ? $baris['uraian'] : '……………' }}</td>
                                                    <td class="border border-stone-400 px-2 py-1">{{ $baris['waktu'] ?? '……………' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                @break
                            @case('menunggu')
                                <p class="flex items-start gap-1.5 rounded bg-stone-50 px-2 py-1 font-sans text-sm text-stone-500 ring-1 ring-stone-200" data-menunggu>
                                    <x-ui.icon name="calendar" class="mt-0.5 size-4 shrink-0" />
                                    {{ $blok['pesan'] }}
                                </p>
                                @break
                            @case('pengesahan')
                                <x-dokumen.pengesahan :kolom="$blok['kolom']" />
                                @break
                        @endswitch
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
</article>
