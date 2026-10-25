@use('App\Support\Format')

@php
    $sumberDanaOpsi = [
        ['kunci' => 'dipa', 'label' => 'DIPA', 'keterangan' => 'Daftar Isian Pelaksanaan Anggaran (APBN).'],
        ['kunci' => 'komite', 'label' => 'Komite', 'keterangan' => 'Dana komite madrasah.'],
    ];
@endphp

<div class="space-y-4">
    <div class="grid gap-3 sm:grid-cols-2">
        @foreach ($sumberDanaOpsi as $opsi)
            @php
                $aktif = $form->{'dana_'.$opsi['kunci']};
                $idPagu = 'pagu_'.$opsi['kunci'];
            @endphp
            <div @class([
                    'rounded-xl p-4 ring-1 transition',
                    'bg-brand-50/50 ring-brand-300' => $aktif,
                    'bg-white ring-stone-200' => ! $aktif,
                 ]) data-testid="sumber-{{ $opsi['kunci'] }}">
                <label class="flex cursor-pointer items-start gap-3">
                    <input type="checkbox" wire:model.live="form.dana_{{ $opsi['kunci'] }}" class="mt-0.5 size-4 rounded accent-brand-700">
                    <span>
                        <span class="block text-sm font-semibold text-stone-800">{{ $opsi['label'] }}</span>
                        <span class="block text-xs text-stone-500">{{ $opsi['keterangan'] }}</span>
                    </span>
                </label>

                <div class="mt-3">
                    <label for="{{ $idPagu }}" class="mb-1 block text-xs font-medium text-stone-600">Pagu {{ $opsi['label'] }}</label>
                    <x-ui.rupiah :id="$idPagu" :model="'form.'.$idPagu" :disabled="! $aktif" :aktif-jika="'form.dana_'.$opsi['kunci']"
                                 terbilang :nilai-awal="$form->{$idPagu}" />
                    @error('form.'.$idPagu) <p class="form-error" id="{{ $idPagu }}-error">{{ $message }}</p> @enderror
                </div>

                @if ($aktif && ($rekap = $this->rekapRab[$opsi['label']] ?? null))
                    <p class="mt-3 flex flex-wrap items-center justify-between gap-1 border-t border-stone-200/70 pt-2.5 text-xs" data-testid="rekap-rab-{{ $opsi['kunci'] }}">
                        <span class="text-stone-500">{{ $rekap->jumlahItem() > 0 ? 'RAB tersusun ('.$rekap->jumlahItem().' item)' : 'RAB belum berisi rincian' }}</span>
                        <span @class([
                            'font-semibold tabular-nums',
                            'text-brand-700' => in_array($rekap->statusPagu(), ['sesuai', 'sisa'], true),
                            'text-red-600' => $rekap->statusPagu() === 'melebihi',
                            'text-stone-700' => $rekap->statusPagu() === 'tanpa_pagu',
                        ])>{{ Format::rupiah($rekap->total) }}</span>
                        @if ($terbilangRekap = \App\Support\Terbilang::tampil($rekap->total))
                            <span class="w-full text-right text-stone-500 italic">{{ $terbilangRekap }}</span>
                        @endif
                    </p>

                    @if ($rekap->jumlahItem() > 0)
                        {{-- Rincian baca-saja; status buka/tutup dijaga saat Livewire merender ulang. --}}
                        <details class="group mt-2" wire:ignore.self data-testid="rincian-rab-{{ $opsi['kunci'] }}">
                            <summary class="flex cursor-pointer list-none items-center gap-1 text-xs font-semibold text-brand-700 hover:text-brand-800">
                                <x-ui.icon name="chevron-down" class="size-3.5 transition group-open:rotate-180" />
                                Lihat rincian RAB {{ $opsi['label'] }}
                            </summary>
                            <x-rab.tabel-rincian :anggaran="$this->anggaranRab[$opsi['label']]" class="mt-2" />
                        </details>
                    @endif
                @endif
            </div>
        @endforeach
    </div>

    @error('form.sumber_dana') <p class="form-error" role="alert">{{ $message }}</p> @enderror

    <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-stone-50 px-4 py-3 ring-1 ring-stone-200">
        <p class="text-xs text-stone-600">
            @if (count($form->sumberDana()) === 2)
                Dibiayai DIPA dan Komite: RAB dan dokumen disusun terpisah untuk masing-masing sumber dana.
            @else
                Total RAB nanti dibandingkan dengan pagu; muncul peringatan bila melebihi.
            @endif
        </p>
        <p class="text-sm text-stone-600">Total pagu <x-ui.uang :nilai="$form->totalPagu()" class="font-bold text-stone-900" data-testid="total-pagu" /></p>
    </div>
</div>
