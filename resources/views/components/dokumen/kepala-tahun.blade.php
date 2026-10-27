@props(['ringkasan', 'tahun'])

{{-- Penanda sinkronisasi tahun di kepala satu dokumen pratinjau (TahunDokumen::untukDokumen); hanya di layar. --}}
<a href="#tahun-cetak" {{ $attributes->class([
        'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold normal-case tracking-normal ring-1 ring-inset',
        'bg-brand-50 text-brand-800 ring-brand-200' => $ringkasan['beda'] === 0,
        'bg-gold-50 text-gold-700 ring-gold-200' => $ringkasan['beda'] > 0,
    ]) }} data-testid="kepala-tahun" data-beda="{{ $ringkasan['beda'] }}">
    <x-ui.icon :name="$ringkasan['beda'] === 0 ? 'check-circle' : 'alert'" class="size-3" />
    TA {{ $tahun }} · {{ $ringkasan['beda'] === 0 ? 'tahun sinkron' : $ringkasan['beda'].' menyebut tahun lain' }}
</a>
