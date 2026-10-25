@props(['status'])

@php
    /** @var \App\Support\Rab\StatusPagu $status */
    $warna = ['netral' => 'text-stone-500', 'aman' => 'text-brand-700', 'bahaya' => 'text-red-600'][$status->nada()];
@endphp

{{-- Status total RAB terhadap pagu dengan teks & ikon seragam: Pagu belum diisi / Sesuai pagu / Sisa pagu … / Melebihi pagu … --}}
<span {{ $attributes->class(['inline-flex items-center gap-1 font-medium', $warna]) }} data-status-pagu="{{ $status->status }}"><x-ui.icon :name="$status->ikon()" class="size-3.5 shrink-0" />{{ $status->teks() }}</span>
