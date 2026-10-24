@props(['status'])

{{-- Lencana kelengkapan kode di tab ruang kerja; aria-hidden agar nama tab tetap "Kode & Klasifikasi" (keterangan lewat title). --}}
@if ($status['status'] === 'lengkap')
    <span class="grid size-4 place-items-center rounded-full bg-brand-100 text-brand-700" aria-hidden="true"
          title="Semua kode lengkap" data-testid="lencana-kode" data-status="lengkap">
        <x-ui.icon name="check" class="size-2.5" />
    </span>
@else
    <span @class([
            'rounded-full px-1.5 py-px text-[10px] leading-4 font-semibold tabular-nums',
            'bg-red-100 text-red-700' => $status['status'] === 'wajib',
            'bg-gold-100 text-gold-700' => $status['status'] === 'sebagian',
        ]) aria-hidden="true" title="{{ $status['pesan'] }}" data-testid="lencana-kode" data-status="{{ $status['status'] }}">{{ $status['terisi'] }}/{{ $status['total'] }}</span>
@endif
