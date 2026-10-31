@use('App\Support\Pengaturan\UjiKoneksiAi')

@php
    $uji = UjiKoneksiAi::terakhir($penyedia);
    $target = "ujiKoneksi('{$penyedia}')";
@endphp

{{--
    Tombol Uji koneksi satu penyedia (pengaturan tersimpan; PengaturanAi::ujiKoneksi → UjiKoneksiAi) + hasil terakhirnya
    selama pengaturan belum berubah. $testid membedakan tempatnya (kartu penyedia aktif/cadangan, baris penyedia).
--}}
<div class="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1.5" data-testid="uji-{{ $testid }}">
    <button type="button" wire:click="{{ $target }}" wire:loading.attr="disabled" wire:target="{{ $target }}" class="btn-ghost px-2.5 py-1.5 text-xs" data-testid="uji-koneksi-{{ $testid }}">
        <span wire:loading.remove wire:target="{{ $target }}" class="inline-flex items-center gap-1.5"><x-ui.icon name="check-circle" class="size-3.5" /> Uji koneksi</span>
        <span wire:loading.flex wire:target="{{ $target }}" class="items-center gap-1.5"><x-ui.icon name="loader" class="size-3.5 animate-spin" /> Menguji…</span>
    </button>
    @if ($uji)
        <p @class(['flex min-w-0 items-start gap-1.5 text-xs', 'text-brand-800' => $uji['berhasil'], 'text-red-700' => ! $uji['berhasil']])
           data-testid="hasil-uji-{{ $testid }}" data-berhasil="{{ $uji['berhasil'] ? '1' : '0' }}" data-jenis="{{ $uji['jenis'] ?? '' }}">
            <x-ui.icon :name="$uji['berhasil'] ? 'check' : 'alert'" class="mt-px size-3.5 shrink-0" />
            <span class="min-w-0">
                <span class="font-semibold">{{ $uji['berhasil'] ? 'Terhubung' : $uji['judul'] }}</span>
                — {{ $uji['pesan'] }}
                <span class="text-stone-500">(diuji {{ \Illuminate\Support\Carbon::parse($uji['pada'])->setTimezone(config('app.timezone'))->locale('id')->translatedFormat('j M, H.i') }})</span>
            </span>
        </p>
    @endif
</div>
