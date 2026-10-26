@props(['judul', 'deskripsi' => null, 'remah' => true])

@php
    $tahunAktif = \App\Support\Stub\StubData::tahunAktif();
@endphp

{{--
    Kepala halaman Pengaturan admin: remah "Pengaturan › {judul}" (kecuali di Ikhtisar), judul, cakupan tahun anggaran
    aktif — pengaturan & master berlaku per tahun (PRD F6) — dan slot $aksi (tombol) serta $tab (sub-halaman).
--}}
<div {{ $attributes->class(['flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between']) }} data-testid="kepala-pengaturan">
    <div class="min-w-0">
        @if ($remah)
            <nav class="flex items-center gap-1 text-xs font-medium text-stone-500" aria-label="Breadcrumb">
                <a href="{{ route('pengaturan.index') }}" wire:navigate class="hover:text-stone-800">Pengaturan</a>
                <x-ui.icon name="chevron-right" class="size-3.5" />
                <span class="text-stone-700">{{ $judul }}</span>
            </nav>
        @endif
        <div @class(['flex flex-wrap items-center gap-2', 'mt-1.5' => $remah])>
            <h1 class="text-xl font-bold tracking-tight text-stone-900 sm:text-2xl">{{ $judul }}</h1>
            <span class="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-semibold text-brand-800 ring-1 ring-brand-200 ring-inset"
                  title="Pengaturan dan master berlaku untuk tahun anggaran aktif">
                <x-ui.icon name="calendar" class="size-3.5" />
                Berlaku TA {{ $tahunAktif['tahun'] }}
                @if ($tahunAktif['is_locked'])
                    <x-ui.icon name="lock" class="size-3.5" />
                @endif
            </span>
        </div>
        @if ($deskripsi)
            <p class="mt-1 max-w-3xl text-sm text-stone-500">{{ $deskripsi }}</p>
        @endif
    </div>
    @isset($aksi)
        <div class="flex shrink-0 flex-wrap items-center gap-2">{{ $aksi }}</div>
    @endisset
</div>
@isset($tab)
    <div class="mt-5">{{ $tab }}</div>
@endisset
