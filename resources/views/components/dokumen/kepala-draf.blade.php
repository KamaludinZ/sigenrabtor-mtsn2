@props(['status'])

{{-- Penanda cap dokumen di kepala dokumen pratinjau (StatusDraf): DRAF beserta jumlah alasannya, atau siap dicetak. --}}
<span {{ $attributes->class([
        'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold normal-case tracking-normal ring-1 ring-inset',
        'bg-red-50 text-red-700 ring-red-200' => $status->draf,
        'bg-brand-50 text-brand-800 ring-brand-200' => ! $status->draf,
    ]) }} data-testid="kepala-draf" data-draf="{{ $status->draf ? 'ya' : 'tidak' }}" @if ($status->draf) title="{{ implode(' ', array_column($status->alasan, 'teks')) }}" @endif>
    @if ($status->draf)
        DRAF · {{ count($status->alasan) }} alasan
    @else
        <x-ui.icon name="check-circle" class="size-3" /> Siap dicetak
    @endif
</span>
