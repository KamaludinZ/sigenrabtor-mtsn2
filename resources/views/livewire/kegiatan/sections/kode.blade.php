@use('App\Support\Stub\StubMaster')
@use('App\Livewire\Forms\KegiatanForm')

@php
    // Ringkasan saja: kode & klasifikasi diatur di halaman tersendiri agar tidak ada dua editor untuk data yang sama.
    $ringkas = [
        'Leading' => ($l = StubMaster::cari(StubMaster::leadings(), $form->leading)) ? $l['kode'].' · '.$l['nama_unit'] : null,
        'Kode RKTM' => ($r = StubMaster::cari(StubMaster::kodeRktm(), $form->kode_rktm)) ? $r['kode'].' — '.$r['sub_kegiatan'] : null,
        'Kode SK' => $form->kode_sk ?: null,
        'Kode IKSK' => $form->kode_iksk ?: null,
        'Kode EDM' => $form->kode_edm ?: null,
        '5 Budaya Mutu' => ($b = StubMaster::cari(StubMaster::budayaMutu(), $form->budaya_mutu, 'nomor')) ? $b['nomor'].'. '.$b['nama'] : null,
    ];
@endphp

<div class="space-y-4" data-testid="ringkasan-kode">
    <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
        @foreach ($ringkas as $label => $nilai)
            <div class="min-w-0">
                <dt class="text-xs text-stone-500">{{ $label }}</dt>
                <dd @class(['truncate font-medium', 'text-stone-900' => $nilai, 'text-stone-400 italic' => ! $nilai])>{{ $nilai ?? 'belum dipilih' }}</dd>
            </div>
        @endforeach
    </dl>

    <div class="flex flex-wrap items-center gap-2">
        @if ($kegiatanId)
            <a href="{{ route('kegiatan.kode', ['kegiatan' => $kegiatanId]) }}" wire:navigate class="btn-secondary">
                <x-ui.icon name="tag" class="size-4" />
                Atur kode &amp; klasifikasi
            </a>
        @else
            <p class="text-xs text-stone-500">Simpan draf dulu, lalu atur kode di halaman <span class="font-medium text-stone-700">Kode &amp; Klasifikasi</span>.</p>
        @endif

        @if ($r && mb_strtolower(trim($form->nama_kegiatan)) !== mb_strtolower(KegiatanForm::tanpaAwalanKegiatan($r['sub_kegiatan'])))
            <button type="button" wire:click="pakaiNamaDariRktm" class="btn-ghost text-brand-700">
                Pakai sub kegiatan RKTM sebagai nama kegiatan
            </button>
        @endif
    </div>
</div>
