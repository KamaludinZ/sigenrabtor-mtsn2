@props(['susunan'])

{{--
    Pratinjau dokumen RAB bergaya kertas A4 dari App\Support\Dokumen\SusunanRab: judul, header (label : nilai), tabel
    rincian (gaya dokumen), Mekanisme Pembayaran bila bertermin, lalu lembar pengesahan seperti KAK. Isian yang belum
    lengkap tampil sebagai penanda kuning; cap "DRAF" mengikuti aturan KAK.
--}}
<article {{ $attributes->class(['relative mx-auto w-full max-w-[210mm] overflow-hidden bg-white font-serif text-[15px] leading-relaxed text-stone-900 shadow-sm ring-1 ring-stone-200']) }}
         data-testid="pratinjau-rab">
    @if ($susunan->kunci)
        <x-dokumen.cap-final :kunci="$susunan->kunci" />
    @endif
    @if ($susunan->draf)
        <div class="pointer-events-none absolute inset-0 flex items-start justify-center overflow-hidden pt-64 select-none" aria-hidden="true">
            <span class="-rotate-30 text-[7rem] font-bold tracking-[0.2em] text-stone-900/[0.06]">DRAF</span>
        </div>
    @endif

    <div class="space-y-6 px-5 py-8 sm:px-12 sm:py-10">
        <header class="text-center">
            <h2 class="mx-auto max-w-xl font-bold uppercase">{{ $susunan->judul }}</h2>
            @if ($susunan->draf)
                <p class="mt-2 font-sans text-xs font-semibold tracking-[0.3em] text-red-700">DRAF</p>
            @endif
        </header>

        <dl class="grid gap-x-3 gap-y-1 text-sm sm:grid-cols-[11rem_minmax(0,1fr)]" data-testid="kepala-rab">
            @foreach ($susunan->kepala as $baris)
                <dt class="font-semibold sm:font-normal">{{ $baris['label'] }}<span class="hidden sm:inline"> :</span></dt>
                <dd @class(['mb-1 sm:mb-0', 'scroll-mt-40 transition-colors target:bg-gold-100 target:outline-2 target:outline-offset-2 target:outline-gold-400' => isset($baris['tempat'])])
                    data-kepala="{{ $baris['label'] }}" @isset($baris['tempat']) id="cetak-{{ $baris['tempat'] }}" data-tempat-cetak="{{ $baris['tempat'] }}" @endisset>
                    @if ($baris['nilai'] !== null)
                        <span>{{ $baris['nilai'] }}</span>
                        @if ($baris['uraian'])
                            <span class="text-stone-600">— {{ $baris['uraian'] }}</span>
                        @endif
                    @else
                        <span class="inline-flex items-start gap-1.5 rounded bg-gold-50 px-2 py-0.5 font-sans text-xs text-gold-700 ring-1 ring-gold-200" data-kosong>
                            <x-ui.icon name="alert" class="mt-px size-3.5 shrink-0" />
                            Belum diisi{{ $baris['asal'] ? ' — '.$baris['asal'] : '' }}
                        </span>
                    @endif
                </dd>
            @endforeach
        </dl>

        @if ($susunan->anggaran)
            {{-- Tabel lebih lebar dari kertas di layar sempit: digeser, bukan terpotong. --}}
            <div class="-mx-5 overflow-x-auto px-5 sm:mx-0 sm:px-0">
                <x-rab.tabel-rincian :anggaran="$susunan->anggaran" gaya="dokumen" class="min-w-[34rem] font-sans" />
            </div>
        @else
            <p class="flex items-start gap-1.5 rounded bg-gold-50 px-2 py-1 font-sans text-sm text-gold-700 ring-1 ring-gold-200" data-kosong>
                <x-ui.icon name="alert" class="mt-0.5 size-4 shrink-0" /> Pilih sumber dana kegiatan agar RAB dapat disusun.
            </p>
        @endif

        @if ($susunan->mekanisme)
            <dl class="grid max-w-md scroll-mt-40 grid-cols-[minmax(0,1fr)_auto] gap-x-3 text-sm transition-colors target:bg-gold-100" data-testid="mekanisme-rab" id="cetak-rab-mekanisme" data-tempat-cetak="rab-mekanisme">
                @foreach ($susunan->mekanisme as $baris)
                    <dt>{{ $baris['label'] }} :</dt>
                    <dd class="text-right tabular-nums">{{ $baris['nilai'] }}</dd>
                @endforeach
            </dl>
            @if ($susunan->statusTermin)
                <p class="flex items-start gap-1.5 rounded bg-gold-50 px-2 py-1 font-sans text-sm text-gold-700 ring-1 ring-gold-200" data-kosong>
                    <x-ui.icon name="alert" class="mt-0.5 size-4 shrink-0" /> {{ $susunan->statusTermin }}.
                </p>
            @endif
        @endif

        <x-dokumen.pengesahan :kolom="$susunan->pengesahan" data-testid="pengesahan-rab" id="cetak-rab-pengesahan" class="scroll-mt-40 transition-colors target:bg-gold-100" data-tempat-cetak="rab-pengesahan" />
    </div>
</article>
