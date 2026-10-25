@props(['jenis', 'aktif' => false, 'nilaiAwal' => '', 'totalRab' => null, 'paguDilepas' => null])

@use('App\Support\Format')
@use('App\Support\Rab\StatusPagu')

@php
    /** @var \App\Enums\SumberDana $jenis */
    $kunci = $jenis->kunci();
    $label = $jenis->value;
    $id = 'pagu_'.$kunci;
    $model = 'form.'.$id;
    $syarat = 'form.dana_'.$kunci;
    $statusAwal = $totalRab !== null ? StatusPagu::hitung($totalRab, Format::parseRupiah($nilaiAwal)) : null;
@endphp

{{--
    Input pagu satu sumber dana (PRD F5 & F12), bersyarat: aktif hanya bila sumbernya dicentang — seketika di klien tanpa
    menunggu server — dan kursor langsung pindah ke sini begitu dicentang. Terbilang ikut berubah tiap ketukan; bila RAB
    sumber ini sudah berisi, status batas pagu terhadap total RAB juga dihitung ulang selagi mengetik ($statusPagu, kembaran
    App\Support\Rab\StatusPagu), sebelum pagu dikirim ke server.
--}}
<div x-data x-init="$watch(() => $wire.{{ $syarat }}, (dipilih) => dipilih && $nextTick(() => document.getElementById(@js($id))?.focus()))"
     data-testid="input-pagu-{{ $kunci }}">
    <label for="{{ $id }}" class="mb-1 block text-xs font-medium text-stone-600">Pagu {{ $label }}</label>
    <x-ui.rupiah :id="$id" :model="$model" :disabled="! $aktif" :aktif-jika="$syarat" terbilang :nilai-awal="$nilaiAwal"
                 x-bind:placeholder="$wire.{{ $syarat }} ? '0' : 'Centang {{ $label }} dulu'" />
    @error($model) <p class="form-error" id="{{ $id }}-error">{{ $message }}</p> @enderror

    @if ($statusAwal)
        {{-- Elemen ini muncul/hilang bersama RAB sumber yang dipilih, jadi state Alpine-nya selalu dibuat dengan total terbaru. --}}
        <p class="mt-1.5 flex flex-wrap items-center justify-between gap-x-3 gap-y-0.5 text-xs" x-show="$wire.{{ $syarat }}"
           x-data="{
               total: @js($totalRab),
               get status() {
                   const angka = parseInt(String(this.$wire.$get(@js($model)) ?? '').replace(/\D/g, ''), 10);

                   return this.$statusPagu(this.total, Number.isNaN(angka) ? null : angka);
               },
           }">
            <span class="text-stone-500">Terhadap total RAB {{ Format::rupiah($totalRab) }}</span>
            <span class="inline-flex items-center gap-1 font-medium" data-testid="status-pagu-form-{{ $kunci }}" aria-live="polite"
                  data-status-pagu="{{ $statusAwal->status }}" x-bind:data-status-pagu="status.status"
                  x-bind:class="{ 'text-stone-500': status.nada === 'netral', 'text-brand-700': status.nada === 'aman', 'text-red-600': status.nada === 'bahaya' }">
                <span @style(['display: none' => $statusAwal->nada() !== 'bahaya']) x-show="status.nada === 'bahaya'"><x-ui.icon name="alert" class="size-3.5 shrink-0" /></span>
                <span @style(['display: none' => $statusAwal->nada() !== 'aman']) x-show="status.nada === 'aman'"><x-ui.icon name="check-circle" class="size-3.5 shrink-0" /></span>
                <span @style(['display: none' => $statusAwal->nada() !== 'netral']) x-show="status.nada === 'netral'"><x-ui.icon name="info" class="size-3.5 shrink-0" /></span>
                <span x-text="status.teks">{{ $statusAwal->teks() }}</span>
            </span>
        </p>
    @endif

    @unless ($aktif)
        @if ($paguDilepas !== null)
            <p class="mt-1 text-[11px] text-stone-500" data-testid="pagu-dilepas-{{ $kunci }}" x-show="! $wire.{{ $syarat }}">Pagu sebelumnya Rp{{ $paguDilepas }} dipakai lagi bila {{ $label }} dicentang.</p>
        @else
            <p class="mt-1 text-[11px] text-stone-400" x-show="! $wire.{{ $syarat }}">Centang {{ $label }} bila kegiatan ini juga dibiayai {{ $label }}.</p>
        @endif
    @endunless
</div>
