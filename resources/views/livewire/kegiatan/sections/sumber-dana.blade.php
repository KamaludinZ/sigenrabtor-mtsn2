@use('App\Enums\SumberDana')
@use('App\Support\Format')
@use('App\Support\Rab\MekanismePembayaran')
@use('App\Support\Terbilang')

@php
    $dipilih = array_column($form->sumberDana(), 'sumber');
    $ringkasPilihan = match (count($dipilih)) {
        0 => ['Belum ada sumber dana dipilih', 'Pilih DIPA, Komite, atau keduanya. Setiap sumber dana mendapat RAB dan dokumen KAK/TOR sendiri.'],
        1 => ["Dibiayai {$dipilih[0]}", "Akan dibuat 1 dokumen KAK/TOR & RAB {$dipilih[0]}."],
        default => ['Dibiayai DIPA dan Komite', 'Akan dibuat 2 dokumen KAK/TOR & RAB terpisah — masing-masing masuk buku sumber dananya.'],
    };
@endphp

{{--
    Pemilihan sumber dana kegiatan (PRD F5): kartu per sumber berisi pagu (dari cascade Perkin) dan ringkasan RAB-nya.
    Melepas sumber yang RAB-nya sudah berisi tidak menghapus RAB itu, tetapi RAB tersebut tidak ikut dicetak.
--}}
<div class="space-y-4" data-testid="pemilihan-sumber-dana">
    <div class="flex items-start gap-3 rounded-xl bg-stone-50 px-4 py-3 ring-1 ring-stone-200" data-testid="ringkasan-pilihan-sumber" data-jumlah="{{ count($dipilih) }}">
        <x-ui.icon name="wallet" class="mt-0.5 size-5 shrink-0 text-stone-400" />
        <div class="min-w-0">
            <p class="flex flex-wrap items-center gap-1.5 text-sm font-semibold text-stone-900">
                {{ $ringkasPilihan[0] }}
                @foreach ($dipilih as $sumber)
                    <x-rab.lencana-sumber :sumber="$sumber" class="text-[11px]" />
                @endforeach
            </p>
            <p class="text-xs text-stone-500">{{ $ringkasPilihan[1] }}</p>
        </div>
    </div>

    <div class="grid gap-3 lg:grid-cols-2">
        @foreach (SumberDana::cases() as $jenis)
            @php
                $kunci = $jenis->kunci();
                $label = $jenis->value;
                $aktif = $form->{'dana_'.$kunci};
                $idPagu = 'pagu_'.$kunci;
                $rekap = $aktif ? ($this->rekapRab[$label] ?? null) : null;
                $anggaran = $aktif ? ($this->anggaranRab[$label] ?? null) : null;
                $terlepas = $aktif ? null : ($this->rabTerlepas[$label] ?? null);
            @endphp
            <article @class([
                    'flex flex-col rounded-2xl ring-1 transition',
                    'bg-brand-50/40 ring-2 ring-brand-300' => $aktif,
                    'bg-white ring-stone-200' => ! $aktif,
                 ]) data-testid="sumber-{{ $kunci }}" data-dipilih="{{ $aktif ? 'ya' : 'tidak' }}">
                {{-- Kepala kartu: pilih/lepas sumber dana --}}
                <label class="flex cursor-pointer items-start gap-3 px-4 pt-4">
                    <input type="checkbox" wire:model.live="form.dana_{{ $kunci }}" class="mt-0.5 size-4 rounded accent-brand-700">
                    <span class="min-w-0 flex-1">
                        <span class="flex items-center justify-between gap-2">
                            <x-rab.lencana-sumber :sumber="$label" class="text-xs" />
                            <span @class(['text-[11px] font-semibold', 'text-brand-700' => $aktif, 'text-stone-400' => ! $aktif])>{{ $aktif ? 'Dipilih' : 'Tidak dipakai' }}</span>
                        </span>
                        <span class="mt-1 block text-xs text-stone-500">{{ $jenis->keterangan() }}</span>
                    </span>
                </label>

                <div class="space-y-3 px-4 pt-3 pb-4">
                    {{-- Pagu --}}
                    <div>
                        <label for="{{ $idPagu }}" class="mb-1 block text-xs font-medium text-stone-600">Pagu {{ $label }}</label>
                        <x-ui.rupiah :id="$idPagu" :model="'form.'.$idPagu" :disabled="! $aktif" :aktif-jika="'form.dana_'.$kunci"
                                     terbilang :nilai-awal="$form->{$idPagu}" />
                        @error('form.'.$idPagu) <p class="form-error" id="{{ $idPagu }}-error">{{ $message }}</p> @enderror
                        @unless ($aktif)
                            <p class="mt-1 text-[11px] text-stone-400">Centang {{ $label }} bila kegiatan ini juga dibiayai {{ $label }}.</p>
                        @endunless
                    </div>

                    {{-- Ringkasan RAB sumber ini --}}
                    @if ($rekap)
                        <div class="rounded-xl bg-white p-3 ring-1 ring-stone-200" data-testid="rekap-rab-{{ $kunci }}">
                            <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1 text-xs">
                                <span class="font-semibold text-stone-700">RAB {{ $label }}</span>
                                <span class="text-stone-500">{{ $rekap->jumlahItem() > 0 ? 'RAB tersusun ('.$rekap->jumlahItem().' item)' : 'RAB belum berisi rincian' }}</span>
                            </div>
                            <p @class([
                                'mt-1 text-right text-base font-bold tabular-nums',
                                'text-brand-700' => $rekap->keteranganPagu()->nada() === 'aman',
                                'text-red-600' => $rekap->keteranganPagu()->melebihi(),
                                'text-stone-800' => $rekap->keteranganPagu()->nada() === 'netral',
                            ])>{{ Format::rupiah($rekap->total) }}</p>
                            @if ($terbilangRekap = Terbilang::tampil($rekap->total))
                                <p class="text-right text-xs text-stone-500 italic">{{ $terbilangRekap }}</p>
                            @endif
                            @if ($rekap->jumlahItem() > 0)
                                <p class="mt-1.5 text-right text-xs">
                                    <x-rab.status-pagu :status="$rekap->keteranganPagu()" data-testid="status-pagu-form-{{ $kunci }}" />
                                </p>
                            @endif
                            @if ($anggaran && $anggaran->adaTermin())
                                @php $mekanisme = MekanismePembayaran::dari($anggaran); @endphp
                                <p @class(['mt-1 text-right text-xs', 'text-stone-500' => $mekanisme->status() === 'pas', 'font-medium text-gold-700' => $mekanisme->status() !== 'pas'])
                                   data-testid="termin-form-{{ $kunci }}">
                                    {{ count($mekanisme->termin) }} termin · {{ $mekanisme->teksStatus() }}
                                </p>
                            @endif

                            <div class="mt-2.5 flex flex-wrap items-center justify-between gap-2 border-t border-stone-100 pt-2.5">
                                @if ($rekap->jumlahItem() > 0)
                                    {{-- Rincian baca-saja; status buka/tutup dijaga saat Livewire merender ulang. --}}
                                    <details class="group w-full" wire:ignore.self data-testid="rincian-rab-{{ $kunci }}">
                                        <summary class="flex cursor-pointer list-none items-center justify-between gap-2 text-xs font-semibold text-brand-700 hover:text-brand-800">
                                            <span class="flex items-center gap-1">
                                                <x-ui.icon name="chevron-down" class="size-3.5 transition group-open:rotate-180" />
                                                Lihat rincian RAB {{ $label }}
                                            </span>
                                            @if ($kegiatanId)
                                                <a href="{{ route('kegiatan.rab', ['kegiatan' => $kegiatanId, 'sumber' => $label]) }}" wire:navigate
                                                   class="font-semibold text-stone-600 hover:text-stone-900" x-on:click.stop>Buka RAB {{ $label }} →</a>
                                            @endif
                                        </summary>
                                        <x-rab.tabel-rincian :anggaran="$anggaran" class="mt-2" />
                                    </details>
                                @elseif ($kegiatanId)
                                    <a href="{{ route('kegiatan.rab', ['kegiatan' => $kegiatanId, 'sumber' => $label]) }}" wire:navigate
                                       class="text-xs font-semibold text-brand-700 hover:text-brand-800">Susun RAB {{ $label }} →</a>
                                @endif
                            </div>
                        </div>
                        @error('form.termin_'.$kunci)
                            <p class="form-error" role="alert" data-testid="galat-termin-{{ $kunci }}">
                                {{ $message }}
                                <a href="{{ route('kegiatan.rab', ['kegiatan' => $kegiatanId, 'sumber' => $label, 'halaman' => 'termin']) }}" wire:navigate class="font-semibold underline">Atur termin</a>
                            </p>
                        @enderror
                    @elseif ($terlepas)
                        {{-- Sumber dilepas padahal RAB-nya sudah berisi: RAB disimpan, tetapi tidak dicetak. --}}
                        <p class="flex items-start gap-2 rounded-xl bg-gold-50 px-3 py-2.5 text-xs text-stone-800 ring-1 ring-gold-200" role="status" data-testid="rab-terlepas-{{ $kunci }}">
                            <x-ui.icon name="alert" class="mt-px size-4 shrink-0 text-gold-600" />
                            <span>RAB {{ $label }} tersimpan ({{ $terlepas->jumlahItem() }} item, {{ Format::rupiah($terlepas->total()) }}) tetapi tidak ikut dicetak selama {{ $label }} tidak dipilih. Centang lagi untuk memakainya.</span>
                        </p>
                    @endif
                </div>
            </article>
        @endforeach
    </div>

    @error('form.sumber_dana') <p class="form-error" role="alert">{{ $message }}</p> @enderror

    <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-stone-50 px-4 py-3 ring-1 ring-stone-200">
        <p class="text-xs text-stone-600">
            @if (count($dipilih) === 2)
                Total pagu hanya ringkasan — setiap RAB dibandingkan dengan pagu sumber dananya sendiri.
            @else
                Total RAB nanti dibandingkan dengan pagu; muncul peringatan bila melebihi.
            @endif
        </p>
        <p class="text-sm text-stone-600">Total pagu <x-ui.uang :nilai="$form->totalPagu()" class="font-bold text-stone-900" data-testid="total-pagu" /></p>
    </div>
</div>
