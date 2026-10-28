@props(['active' => 'data', 'kegiatanId' => null, 'statusKode' => null])

@use('App\Contracts\KegiatanRepository')
@use('App\Support\Kegiatan\RingkasanKode')

@php
    // Lencana kelengkapan di tab Kode: dari isian langsung bila dikirim halaman Kode, selain itu dari data tersimpan.
    if (! $statusKode && $kegiatanId && ($tersimpan = app(KegiatanRepository::class)->temukan($kegiatanId))) {
        $statusKode = RingkasanKode::status(RingkasanKode::susun(array_intersect_key($tersimpan, array_flip(RingkasanKode::ISIAN)), \App\Support\Kegiatan\TahunKegiatan::dari($tersimpan)));
    }

    // Tab ruang kerja satu kegiatan. Tab tanpa rute (belum dibangun) atau yang butuh kegiatan tersimpan ditampilkan non-aktif.
    $tabs = [
        ['key' => 'data', 'label' => 'Data Kegiatan', 'icon' => 'clipboard', 'route' => $kegiatanId ? 'kegiatan.edit' : 'kegiatan.create', 'needs_id' => false],
        ['key' => 'kode', 'label' => 'Kode & Klasifikasi', 'icon' => 'tag', 'route' => 'kegiatan.kode', 'needs_id' => true],
        ['key' => 'rab', 'label' => 'RAB', 'icon' => 'coins', 'route' => 'kegiatan.rab', 'needs_id' => true],
        ['key' => 'matrik', 'label' => 'Matrik & Jadwal', 'icon' => 'calendar', 'route' => 'kegiatan.matrik', 'needs_id' => true],
        ['key' => 'kalimat', 'label' => 'Kalimat KAK', 'icon' => 'sparkles', 'route' => 'kegiatan.kalimat', 'needs_id' => true],
        ['key' => 'pratinjau', 'label' => 'Pratinjau Dokumen', 'icon' => 'eye', 'route' => 'kegiatan.pratinjau', 'needs_id' => true],
    ];
@endphp

<nav class="-mb-px flex gap-1 overflow-x-auto border-b border-stone-200" aria-label="Bagian kegiatan">
    @foreach ($tabs as $tab)
        @php
            $available = Route::has($tab['route']) && (! $tab['needs_id'] || $kegiatanId);
            $isActive = $tab['key'] === $active;
        @endphp
        @if ($available && ! $isActive)
            <a href="{{ route($tab['route'], $tab['needs_id'] || $kegiatanId ? ['kegiatan' => $kegiatanId] : []) }}" wire:navigate
               class="flex shrink-0 items-center gap-2 border-b-2 border-transparent px-3 py-2.5 text-sm font-medium text-stone-500 hover:border-stone-300 hover:text-stone-800">
                <x-ui.icon :name="$tab['icon']" class="size-4" />
                {{ $tab['label'] }}
                @if ($tab['key'] === 'kode' && $statusKode)
                    <x-kegiatan.lencana-kode :status="$statusKode" />
                @endif
            </a>
        @elseif ($isActive)
            <span aria-current="page"
                  class="flex shrink-0 items-center gap-2 border-b-2 border-brand-700 px-3 py-2.5 text-sm font-semibold text-brand-800">
                <x-ui.icon :name="$tab['icon']" class="size-4" />
                {{ $tab['label'] }}
                @if ($tab['key'] === 'kode' && $statusKode)
                    <x-kegiatan.lencana-kode :status="$statusKode" />
                @endif
            </span>
        @else
            <span class="flex shrink-0 cursor-not-allowed items-center gap-2 border-b-2 border-transparent px-3 py-2.5 text-sm font-medium text-stone-400"
                  title="{{ Route::has($tab['route']) ? 'Simpan kegiatan terlebih dahulu' : 'Fitur ini sedang disiapkan' }}">
                <x-ui.icon :name="$tab['icon']" class="size-4" />
                {{ $tab['label'] }}
            </span>
        @endif
    @endforeach
</nav>
