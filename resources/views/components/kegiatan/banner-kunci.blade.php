@props(['kegiatanId', 'status', 'subjek' => 'Isian'])

@use('App\Contracts\ArsipFinalRepository')
@use('App\Contracts\KegiatanRepository')
@use('App\Enums\StatusKegiatan')
@use('App\Support\Kegiatan\KunciDokumen')

@php
    // Banner isian terkunci yang sama di semua tab ruang kerja kegiatan. Final: siapa & kapan dikunci (KunciDokumen)
    // dan tautan arsip final; status terkunci lain (Diperiksa/Disetujui): keterangan statusnya.
    $statusEnum = StatusKegiatan::tryFrom($status) ?? StatusKegiatan::Draf;
    $final = $statusEnum === StatusKegiatan::Final;
    $kunci = $final ? KunciDokumen::dari(app(KegiatanRepository::class)->temukan($kegiatanId) ?? []) : null;
    $arsip = $final ? app(ArsipFinalRepository::class)->untukKegiatan($kegiatanId) : [];
@endphp

@unless ($statusEnum->dapatDiedit())
    <div {{ $attributes->class([
            'flex items-start gap-3 rounded-xl px-4 py-3 text-sm ring-1',
            'bg-brand-900 text-brand-50 ring-brand-900' => $final,
            'bg-sky-50 text-sky-900 ring-sky-200' => ! $final,
         ]) }} role="status" data-testid="banner-terkunci" data-status="{{ $statusEnum->value }}">
        <span @class(['grid shrink-0 place-items-center rounded-full', 'size-8 bg-white/10' => $final, 'mt-0.5' => ! $final])>
            <x-ui.icon name="lock" class="size-4" />
        </span>
        <div class="min-w-0 flex-1">
            <p class="font-semibold">{{ $subjek }} terkunci — status {{ $statusEnum->label() }}</p>
            @if ($kunci)
                <p class="mt-0.5 opacity-90" data-testid="jejak-kunci">{{ $kunci->kalimat() }} Data final tidak dapat diubah; hanya admin yang dapat membuka kunci (tercatat siapa &amp; kapan).</p>
                @if ($arsip !== [])
                    <p class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                        <span class="opacity-75">Arsip final:</span>
                        @foreach ($arsip as $sumber => $a)
                            <a href="{{ route('kegiatan.arsip', ['kegiatan' => $kegiatanId, 'sumber' => $sumber, 'v' => $a['diunggah_pada']]) }}" target="_blank" rel="noopener"
                               class="inline-flex items-center gap-1 font-semibold text-white underline decoration-white/40 underline-offset-2 hover:decoration-white">
                                <x-ui.icon name="file-text" class="size-3.5" /> RAB {{ $sumber }}
                            </a>
                        @endforeach
                    </p>
                @endif
            @else
                <p class="mt-0.5 opacity-90">{{ $statusEnum->deskripsi() }}</p>
            @endif
        </div>
    </div>
@endunless
