@props(['susunan', 'bagian' => null, 'cap' => true, 'sunting' => null, 'penanda' => null])

@use('App\Support\Dokumen\PenandaKosong')
@use('App\Support\Dokumen\SuntingDokumen')

{{--
    Pratinjau KAK/TOR bergaya kertas A4: cover lalu sembilan bagian baku dari App\Support\Dokumen\SusunanKak.
    Isian yang belum lengkap tampil sebagai penanda kuning (bukan teks palsu); bagian dari fitur yang belum tersedia
    tampil abu-abu. Cap "DRAF" muncul selama kegiatan belum disetujui, belum lengkap, atau RAB-nya melebihi pagu.
    Penanda pagu di bagian Pembiayaan hanya ada di pratinjau (bukan teks dokumen).
    $bagian (daftar nomor, mis. [4, 7]) menampilkan bagian itu saja tanpa cover — dipakai pratinjau Matrik & Jadwal.
    :cap="false" tanpa cap DRAF/Final (cuplikan per bagian di tab Susun KAK/TOR; capnya tampil sekali di kepala halaman).
    :sunting="['terbuka' => kunci|null]" = mode sunting teks pratinjau: blok ber-`sunting`/`sunting_butir` (SuntingDokumen)
    berbingkai dengan tombol Sunting (`bukaSunting`), yang terbuka diganti <x-dokumen.penyunting-teks>, dan bidang yang belum
    tampil ditawarkan "Tambah …" (SuntingDokumen::tambahan). null = baca saja.
    :penanda="['kegiatan_id' => id, 'pengguna' => akun, 'isi' => bool]" = penanda kosong pratinjau (PenandaKosong): lencana
    jumlah kosong di judul bagian, tingkat (wajib sebelum diajukan / belum lengkap), "Isi di sini" (`isiDiSini`, bila
    blok bersunting & isi = true) dan tautan tempat melengkapinya. null = penanda polos.
--}}
@php
    $daftarBagian = $bagian === null ? $susunan->bagian : array_values(array_filter($susunan->bagian, fn ($b) => in_array($b['nomor'], $bagian, true)));
@endphp
<article {{ $attributes->class(['relative mx-auto w-full max-w-[210mm] overflow-hidden bg-white font-serif text-[15px] leading-relaxed text-stone-900 shadow-sm ring-1 ring-stone-200']) }}
         data-testid="pratinjau-kak">
    @if ($cap && $susunan->kunci)
        <x-dokumen.cap-final :kunci="$susunan->kunci" />
    @endif
    @if ($cap && $susunan->draf)
        <x-dokumen.cap-draf class="items-start pt-64" />
    @endif

    {{-- Cover --}}
    @if ($bagian === null)
    <header class="border-b border-stone-200 px-8 py-12 text-center sm:px-14 cetak:px-14" data-testid="pratinjau-kak-cover">
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

    <div class="space-y-6 px-5 py-8 sm:px-14 sm:py-10 cetak:px-14 cetak:py-10">
        @foreach ($daftarBagian as $isiBagian)
            <section data-bagian="{{ $isiBagian['nomor'] }}" id="cetak-kak-bagian-{{ $isiBagian['nomor'] }}" class="scroll-mt-40">
                <h3 class="font-bold uppercase">
                    {{ $isiBagian['nomor'] }}. {{ $isiBagian['judul'] }}
                    @if ($penanda !== null && ($jumlahKosong = PenandaKosong::jumlahBagian($isiBagian)) > 0)
                        <span class="ml-1.5 inline-block rounded-full bg-gold-100 px-1.5 align-middle font-sans text-[10px] font-semibold tracking-normal text-gold-800 normal-case ring-1 ring-gold-200 print:hidden" data-testid="penanda-bagian">{{ $jumlahKosong }} belum lengkap</span>
                    @endif
                </h3>
                <div class="mt-2 space-y-2 pl-5">
                    @foreach ($isiBagian['isi'] as $blok)
                        @php
                            $kunciSunting = $sunting !== null ? ($blok['sunting'] ?? null) : null;
                        @endphp
                        @if ($kunciSunting !== null && $sunting['terbuka'] === $kunciSunting)
                            <x-dokumen.penyunting-teks :kunci="$kunciSunting" />
                            @continue
                        @endif
                        @if ($kunciSunting !== null)
                            <div class="relative -mx-1.5 rounded-sm px-1.5 pt-5 pb-1 outline-1 outline-sky-300 outline-dashed hover:bg-sky-50/60" data-sunting="{{ $kunciSunting }}">
                                <button type="button" wire:click="bukaSunting('{{ $kunciSunting }}')" class="absolute top-0.5 right-1 z-10 inline-flex items-center gap-1 rounded-full bg-sky-600 px-2 py-0.5 font-sans text-[10px] leading-4 font-semibold text-white shadow-sm hover:bg-sky-700 print:hidden" data-testid="sunting-{{ $kunciSunting }}">
                                    <x-ui.icon name="pencil" class="size-3" /> Sunting<span class="sr-only"> {{ SuntingDokumen::label($kunciSunting) }}</span>
                                </button>
                        @endif
                        @switch($blok['jenis'])
                            @case('paragraf')
                                @if (isset($blok['tempat']))
                                    <p id="cetak-{{ $blok['tempat'] }}" class="scroll-mt-40 indent-8 transition-colors sm:text-justify cetak:text-justify target:bg-gold-100 target:outline-2 target:outline-offset-2 target:outline-gold-400" data-tempat-cetak="{{ $blok['tempat'] }}">{{ $blok['teks'] }}</p>
                                @else
                                    <p class="indent-8 sm:text-justify cetak:text-justify">{{ $blok['teks'] }}</p>
                                @endif
                                @break
                            @case('subjudul')
                                <p class="font-semibold">{{ $blok['teks'] }}</p>
                                @break
                            @case('daftar')
                                <ol @class(['space-y-1 pl-6', 'list-decimal' => $blok['bernomor'], 'list-disc' => ! $blok['bernomor'], 'scroll-mt-40 transition-colors target:bg-gold-100 target:outline-2 target:outline-offset-2 target:outline-gold-400' => isset($blok['tempat'])])
                                    @isset($blok['tempat']) id="cetak-{{ $blok['tempat'] }}" data-tempat-cetak="{{ $blok['tempat'] }}" @endisset>
                                    @foreach ($blok['butir'] as $i => $butir)
                                        @php
                                            $kunciButir = $sunting !== null ? ($blok['sunting_butir'][$i] ?? null) : null;
                                        @endphp
                                        @if ($kunciButir !== null && $sunting['terbuka'] === $kunciButir)
                                            <li><x-dokumen.penyunting-teks :kunci="$kunciButir" /></li>
                                        @elseif ($kunciButir !== null)
                                            <li class="relative rounded-sm pt-5 outline-1 outline-sky-300 outline-dashed sm:text-justify cetak:text-justify" data-sunting="{{ $kunciButir }}">
                                                {{ $butir }}
                                                <button type="button" wire:click="bukaSunting('{{ $kunciButir }}')" class="absolute top-0.5 right-1 z-10 inline-flex items-center gap-1 rounded-full bg-sky-600 px-2 py-0.5 font-sans text-[10px] leading-4 font-semibold text-white shadow-sm hover:bg-sky-700 print:hidden" data-testid="sunting-{{ $kunciButir }}">
                                                    <x-ui.icon name="pencil" class="size-3" /> Sunting<span class="sr-only"> {{ SuntingDokumen::label($kunciButir) }}</span>
                                                </button>
                                            </li>
                                        @else
                                            <li class="sm:text-justify cetak:text-justify">{{ $butir }}</li>
                                        @endif
                                    @endforeach
                                </ol>
                                @break
                            @case('kosong')
                                @if ($penanda === null)
                                    <p class="flex items-start gap-1.5 rounded bg-gold-50 px-2 py-1 font-sans text-sm text-gold-700 ring-1 ring-gold-200" data-kosong>
                                        <x-ui.icon name="alert" class="mt-0.5 size-4 shrink-0" />
                                        {{ $blok['pesan'] }}
                                    </p>
                                @else
                                    @php
                                        $tingkat = PenandaKosong::tingkat($blok);
                                        $tempatKosong = PenandaKosong::tempat($blok['asal'] ?? null, $penanda['kegiatan_id'], $penanda['pengguna']);
                                        $bisaIsi = $penanda['isi'] && $sunting === null && isset($blok['sunting']);
                                    @endphp
                                    <div @class(['rounded px-2 py-1 font-sans text-sm ring-1', 'bg-red-50 text-red-800 ring-red-200' => $tingkat === 'wajib', 'bg-gold-50 text-gold-700 ring-gold-200' => $tingkat !== 'wajib'])
                                         data-kosong data-tingkat="{{ $tingkat }}" @if ($blok['asal'] ?? null) data-asal="{{ $blok['asal'] }}" @endif>
                                        <p class="flex flex-wrap items-start gap-x-1.5 gap-y-0.5">
                                            <x-ui.icon name="alert" class="mt-0.5 size-4 shrink-0" />
                                            <span class="min-w-0 flex-1">{{ $blok['pesan'] }}</span>
                                            @if ($tingkat === 'wajib')
                                                <span class="rounded-full bg-red-100 px-1.5 text-[10px] font-semibold text-red-800">{{ PenandaKosong::TINGKAT['wajib'][0] }}</span>
                                            @endif
                                        </p>
                                        @if ($bisaIsi || $tempatKosong)
                                            <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 pl-5.5 text-xs print:hidden">
                                                @if ($bisaIsi)
                                                    <button type="button" wire:click="isiDiSini('{{ $blok['sunting'] }}')" class="inline-flex items-center gap-1 font-semibold text-sky-700 hover:text-sky-900" data-testid="isi-di-sini-{{ $blok['sunting'] }}"><x-ui.icon name="pencil" class="size-3" /> Isi di sini</button>
                                                @endif
                                                @if ($tempatKosong && $tempatKosong['tautan'])
                                                    <a href="{{ $tempatKosong['tautan'] }}" @unless ($tempatKosong['admin']) wire:navigate @endunless class="font-semibold underline underline-offset-2 hover:no-underline" data-testid="lengkapi-kosong">Lengkapi di {{ $tempatKosong['label'] }}</a>
                                                @elseif ($tempatKosong)
                                                    <span class="inline-flex items-center gap-1 text-stone-500"><x-ui.icon name="lock" class="size-3" /> Diatur admin ({{ $tempatKosong['label'] }})</span>
                                                @endif
                                            </p>
                                        @endif
                                    </div>
                                @endif
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
                                <div class="-ml-5 overflow-x-auto sm:ml-0 cetak:ml-0" data-testid="kak-matrik">
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
                                <div class="-ml-5 overflow-x-auto sm:ml-0 cetak:ml-0" data-testid="kak-jadwal">
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
                                <x-dokumen.pengesahan :kolom="$blok['kolom']" id="cetak-kak-pengesahan" class="scroll-mt-40 transition-colors target:bg-gold-100" data-tempat-cetak="kak-pengesahan" />
                                @break
                        @endswitch
                        @if ($kunciSunting !== null)
                            </div>
                        @endif
                    @endforeach
                    @if ($sunting !== null)
                        {{-- Bidang yang belum tampil di dokumen (mis. latar belakang khusus kosong). --}}
                        @foreach (SuntingDokumen::tambahan($isiBagian) as $kunciTambah)
                            @if ($sunting['terbuka'] === $kunciTambah)
                                <x-dokumen.penyunting-teks :kunci="$kunciTambah" />
                            @else
                                <button type="button" wire:click="bukaSunting('{{ $kunciTambah }}')" class="flex w-full items-center gap-1.5 rounded-sm border border-dashed border-sky-300 px-2 py-1.5 font-sans text-xs font-semibold text-sky-700 hover:bg-sky-50 print:hidden" data-testid="tambah-{{ $kunciTambah }}">
                                    <x-ui.icon name="plus" class="size-3.5" /> Tambah {{ mb_strtolower(SuntingDokumen::label($kunciTambah)) }}
                                </button>
                            @endif
                        @endforeach
                    @endif
                </div>
            </section>
        @endforeach
    </div>
</article>
