@props(['kegiatanId', 'status', 'subjek' => 'Isian'])

@use('App\Contracts\ArsipFinalRepository')
@use('App\Contracts\KegiatanRepository')
@use('App\Enums\StatusKegiatan')
@use('App\Support\Kegiatan\KunciDokumen')
@use('App\Support\Kegiatan\KunciKegiatan')

@php
    // Banner isian terkunci yang sama di semua tab ruang kerja kegiatan. Final: siapa & kapan dikunci (KunciDokumen)
    // dan tautan arsip final; status terkunci lain (Diperiksa/Disetujui): keterangan statusnya; status masih bisa
    // diubah tetapi tahun anggarannya dikunci admin (KunciKegiatan): keterangan tahun.
    $statusEnum = StatusKegiatan::tryFrom($status) ?? StatusKegiatan::Draf;
    $final = $statusEnum === StatusKegiatan::Final;
    $data = $kegiatanId ? (app(KegiatanRepository::class)->temukan($kegiatanId) ?? []) : [];
    $kunci = $final ? KunciDokumen::dari($data) : null;
    $arsip = $final ? app(ArsipFinalRepository::class)->untukKegiatan($kegiatanId) : [];
    $tahunKunci = $statusEnum->dapatDiedit() ? KunciKegiatan::tahunTerkunci($data) : null;
@endphp

@if ($tahunKunci !== null)
    <div {{ $attributes->class(['flex items-start gap-3 rounded-xl bg-stone-100 px-4 py-3 text-sm text-stone-800 ring-1 ring-stone-300']) }}
         role="status" data-testid="banner-terkunci" data-status="tahun-terkunci">
        <span class="mt-0.5 grid shrink-0 place-items-center"><x-ui.icon name="lock" class="size-4" /></span>
        <div class="min-w-0 flex-1">
            <p class="font-semibold">{{ $subjek }} terkunci — Tahun Anggaran {{ $tahunKunci }} dikunci</p>
            <p class="mt-0.5 text-stone-600">Kegiatan tahun yang dikunci hanya bisa dilihat. Admin dapat membuka kuncinya di Pengaturan › Tahun Anggaran.</p>
        </div>
    </div>
@endif

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
