@use('App\Support\Format')
@use('App\Support\Rab\AnggaranKegiatan')

@php
    $dipilih = array_values(array_filter($this->anggaran, fn ($a) => $a->dipilih));
    $terlepas = array_values(array_filter($this->anggaran, fn ($a) => ! $a->dipilih));
    $gabungan = AnggaranKegiatan::gabungan($dipilih);
@endphp

{{--
    Ringkasan anggaran per sumber dana: tiap kartu = satu RAB & satu dokumen (DIPA/Komite) sekaligus tab pemilihnya.
    Kartu total kegiatan hanya ringkasan; dokumen tetap dicetak per sumber dana.
--}}
<section class="mt-6" aria-label="Anggaran per sumber dana" data-testid="ringkasan-sumber">
    <div class="grid gap-2 sm:gap-3 lg:grid-cols-3">
        <div role="tablist" aria-label="RAB per sumber dana"
             @class(['grid gap-2 sm:gap-3 lg:col-span-2', 'grid-cols-2' => count($dipilih) > 1, 'grid-cols-1 sm:grid-cols-2' => count($dipilih) === 1])>
            @foreach ($dipilih as $anggaran)
                @php
                    $aktif = $anggaran->sumber === $sumber && $halaman === 'rincian';
                    $selisih = $anggaran->hasil->selisih();
                @endphp
                <button type="button" role="tab" aria-selected="{{ $aktif ? 'true' : 'false' }}"
                        wire:click="pilihSumber('{{ $anggaran->sumber }}')" data-sumber="{{ $anggaran->sumber }}"
                        @class([
                            'min-w-0 rounded-2xl p-3 text-left ring-1 transition sm:p-4',
                            'bg-white ring-2 ring-brand-700 shadow-sm' => $aktif,
                            'bg-white ring-stone-200 hover:ring-stone-300' => ! $aktif,
                        ])>
                    <span class="flex items-center justify-between gap-2">
                        <x-rab.lencana-sumber :sumber="$anggaran->sumber" awalan="RAB" class="text-xs" />
                        @if ($aktif)
                            <span class="hidden rounded-full bg-brand-50 px-2 py-0.5 text-[11px] font-semibold text-brand-800 sm:inline">Sedang dibuka</span>
                        @endif
                    </span>
                    <span class="mt-1.5 block truncate text-lg font-bold text-stone-900 tabular-nums sm:text-xl">{{ Format::rupiah($anggaran->total()) }}</span>
                    <span class="block truncate text-xs text-stone-500">
                        Pagu {{ $anggaran->pagu() !== null ? Format::rupiah($anggaran->pagu()) : 'belum diisi' }} · {{ $anggaran->jumlahItem() }} item
                    </span>
                    @if ($anggaran->pagu())
                        <span class="mt-2 block h-1.5 overflow-hidden rounded-full bg-stone-100" aria-hidden="true">
                            <span @class(['block h-full rounded-full', 'bg-brand-600' => $selisih >= 0, 'bg-red-500' => $selisih < 0])
                                  style="width: {{ $anggaran->hasil->persenPagu() }}%"></span>
                        </span>
                    @endif
                    <span class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs font-medium">
                        <x-rab.status-pagu :status="$anggaran->keteranganPagu()" data-testid="status-pagu-{{ mb_strtolower($anggaran->sumber) }}" />
                        @if ($anggaran->adaTermin())
                            <span @class(['inline-flex items-center gap-1', 'text-stone-500' => $anggaran->terminSesuai(), 'text-gold-700' => ! $anggaran->terminSesuai()])>
                                <x-ui.icon :name="$anggaran->terminSesuai() ? 'calendar' : 'alert'" class="size-3.5 shrink-0" />
                                {{ count($anggaran->termin) }} termin{{ $anggaran->terminSesuai() ? '' : ' belum pas' }}
                            </span>
                        @endif
                    </span>
                </button>
            @endforeach
        </div>

        @if (count($dipilih) > 1)
            <div class="flex min-w-0 flex-col justify-between rounded-2xl bg-stone-100/70 p-3 ring-1 ring-stone-200 sm:p-4" data-testid="total-kegiatan">
                <div>
                    <p class="text-sm font-semibold text-stone-700">Total kegiatan</p>
                    <p class="mt-1.5 truncate text-lg font-bold text-stone-900 tabular-nums sm:text-xl">{{ Format::rupiah($gabungan['total']) }}</p>
                    <p class="truncate text-xs text-stone-500">Pagu {{ Format::rupiah($gabungan['pagu']) }} · {{ $gabungan['jumlah_item'] }} item</p>
                </div>
                <p class="mt-2 hidden text-xs text-stone-500 sm:block">{{ count($dipilih) }} RAB terpisah — dokumen KAK &amp; RAB dicetak per sumber dana.</p>
            </div>
        @endif
    </div>

    @foreach ($terlepas as $anggaran)
        <p class="mt-3 flex items-start gap-2 rounded-xl bg-gold-50 px-4 py-3 text-sm text-stone-800 ring-1 ring-gold-200" role="status" data-testid="rab-terlepas">
            <x-ui.icon name="alert" class="mt-0.5 size-4 shrink-0 text-gold-600" />
            <span>
                <x-rab.lencana-sumber :sumber="$anggaran->sumber" awalan="RAB" /> ({{ $anggaran->jumlahItem() }} item, {{ Format::rupiah($anggaran->total()) }}) masih tersimpan, tetapi {{ $anggaran->sumber }}
                tidak lagi dipilih sebagai sumber dana sehingga tidak ikut dicetak.
                <a href="{{ route('kegiatan.edit', ['kegiatan' => $kegiatanId]) }}#sumber-dana" wire:navigate class="font-semibold text-brand-700 hover:text-brand-800">Pilih lagi di Data Kegiatan</a>
            </span>
        </p>
        @if ($anggaran->jumlahItem() > 0)
            <details class="group mt-2 rounded-xl bg-white p-3 ring-1 ring-stone-200" wire:ignore.self data-testid="isi-rab-terlepas">
                <summary class="flex cursor-pointer list-none items-center gap-1 text-xs font-semibold text-stone-600 hover:text-stone-900">
                    <x-ui.icon name="chevron-down" class="size-3.5 transition group-open:rotate-180" />
                    Lihat isi RAB {{ $anggaran->sumber }} (baca-saja)
                </summary>
                <x-rab.tabel-rincian :anggaran="$anggaran" class="mt-2" />
            </details>
        @endif
    @endforeach
</section>
