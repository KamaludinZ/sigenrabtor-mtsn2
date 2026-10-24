@use('App\Support\MasterKode')
@use('App\Livewire\Forms\KegiatanForm')
@use('App\Support\Kegiatan\RingkasanKode')

@php
    // Ringkasan saja: kode & klasifikasi diatur di halaman tersendiri agar tidak ada dua editor untuk data yang sama.
    $r = MasterKode::cari(MasterKode::kodeRktm(), $form->kode_rktm);
@endphp

<div class="space-y-4" data-testid="ringkasan-kode">
    <x-kode.ringkasan :baris="RingkasanKode::susun($form->only(RingkasanKode::ISIAN))" kolom />

    @error('form.kode_sk')
        <p class="form-error" role="alert" data-testid="error-kode-sk">{{ $message }} Atur di halaman Kode &amp; Klasifikasi.</p>
    @enderror

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
