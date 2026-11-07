@use('App\Support\Format')
@use('App\Support\Master\TemplateMasterKode')
@use('App\Support\MasterKode')

{{--
    Tab Rencana Aksi Master Kode: tiap rencana aksi beserta tag kodenya (SK, IKSK, RKTM, EDM — otomatis dari relasi
    EDM ↔ RKTM atau manual, 5 Budaya Mutu, leading), anggaran, bulan, dan pemakaian. Kode tag yang tidak ada di master
    tahun ini ditandai merah. Tag disunting di tempat (Daftar::ubahTag → simpanTag) selama tahun tidak terkunci.
--}}
@php
    $bisaUbah = ! $this->tahunTerkunci();
    $pilihan = $tagTerbuka !== null ? $this->pilihanTag() : null;
    $namaLeading = fn (?string $kode) => $kode === null ? null : $kode.' '.(MasterKode::leading($kode)['nama_unit'] ?? '');
    $bulanTeks = fn (array $bulan) => $bulan === [] ? '—' : (count($bulan) === 12 ? 'Sepanjang tahun' : implode(', ', array_map(fn ($n) => TemplateMasterKode::BULAN[$n - 1], $bulan)));
    $chip = function (string $label, ?string $nilai, array $asing, bool $otomatis = false): string {
        if ($nilai === null || $nilai === '') {
            return '';
        }
        $salah = in_array($nilai, $asing, true);

        return '<span class="inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[11px] font-medium ring-1 '
            .($salah ? 'bg-red-50 text-red-700 ring-red-200' : ($otomatis ? 'bg-sky-50 text-sky-800 ring-sky-200' : 'bg-stone-50 text-stone-700 ring-stone-200')).'"'
            .($salah ? ' title="Tidak ada di master tahun ini"' : ($otomatis ? ' title="Otomatis dari relasi EDM ↔ RKTM"' : '')).' data-tag="'.e($label).'">'
            .'<span class="text-stone-400">'.e($label).'</span> '.e($nilai).'</span>';
    };
@endphp

<div class="mt-4 border-t border-stone-100" data-testid="rencana-aksi">
    @if ($bisaUbah && ($rktmAsing = $this->jumlahRktmAsing()) > 0)
        <div class="flex flex-col gap-2 border-b border-gold-200 bg-gold-50 px-5 py-3 text-sm text-gold-900 sm:flex-row sm:items-center sm:justify-between sm:px-6" data-testid="rktm-asing-ra">
            <p class="flex items-start gap-2"><x-ui.icon name="alert" class="mt-0.5 size-4 shrink-0" /> {{ $rktmAsing }} rencana aksi bertag kode RKTM yang tidak ada di master TA {{ $this->tahunDilihat() }} — kode RKTM berganti tiap tahun.</p>
            <button type="button" wire:click="sesuaikanRktm" wire:loading.attr="disabled" wire:target="sesuaikanRktm" class="btn-secondary shrink-0 px-3 py-1.5 text-xs" data-testid="sesuaikan-rktm"><x-ui.icon name="sparkles" class="size-3.5" /> Sesuaikan otomatis</button>
        </div>
    @endif
    <ul class="divide-y divide-stone-100">
        @foreach ($baris as $ra)
            @php
                $n = $pakai[$ra['kode']] ?? 0;
                $tag = $ra['tag'];
                $terbuka = $tagTerbuka === $ra['kode'];
            @endphp
            <li class="px-5 py-3.5 sm:px-6" data-kode="{{ $ra['kode'] }}" data-asing="{{ $ra['asing'] === [] ? 'tidak' : 'ya' }}" wire:key="ra-{{ $ra['kode'] }}">
                <div class="grid gap-x-5 gap-y-2 lg:grid-cols-[4.5rem_minmax(0,1fr)_12rem_8rem]">
                    <p class="font-semibold text-stone-900 tabular-nums">{{ $ra['kode'] }}</p>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-stone-900">{{ $ra['uraian'] }}</p>
                        <p class="mt-0.5 text-xs text-stone-500">
                            @if ($tag['kode_leading'] !== null)
                                <span data-testid="leading-ra">{{ $namaLeading($tag['kode_leading']) }}</span>
                            @else
                                <span class="font-medium text-gold-700" data-testid="leading-ra-kosong">Belum ada leading</span>
                            @endif
                            @if (filled($ra['unit'] ?? null))
                                · unit di berkas: {{ $ra['unit'] }}
                            @endif
                        </p>
                        <div class="mt-1.5 flex flex-wrap gap-1" data-testid="tag-ra">
                            {!! $chip('SK', $tag['kode_sk'], $ra['asing']) !!}
                            {!! $chip('IKSK', $tag['kode_iksk'], $ra['asing']) !!}
                            {!! $chip('RKTM', $tag['kode_rktm'], $ra['asing']) !!}
                            @foreach ($tag['kode_edm'] as $edm)
                                {!! $chip('EDM', $edm, $ra['asing'], $tag['edm_otomatis']) !!}
                            @endforeach
                            @foreach ($tag['budaya_mutu'] as $nomor)
                                {!! $chip('5BM', (string) $nomor, []) !!}
                            @endforeach
                            @if ($tag['kode_sk'] === null && $tag['kode_iksk'] === null && $tag['kode_rktm'] === null && $tag['kode_edm'] === [])
                                <span class="text-[11px] text-stone-400">Belum bertag kode</span>
                            @endif
                        </div>
                    </div>
                    <div class="text-xs text-stone-600 lg:text-right">
                        <p>DIPA <span class="font-medium text-stone-800 tabular-nums">{{ Format::rupiah($ra['pagu_dipa']) }}</span></p>
                        <p>Komite <span class="font-medium text-stone-800 tabular-nums">{{ Format::rupiah($ra['pagu_komite']) }}</span></p>
                        <p class="mt-0.5 text-stone-500" data-testid="bulan-ra">{{ $bulanTeks($ra['bulan']) }}</p>
                    </div>
                    <div class="flex items-start justify-between gap-2 lg:flex-col lg:items-end">
                        <span @class(['text-xs tabular-nums', 'font-semibold text-brand-800' => $n > 0, 'text-stone-400' => $n === 0])>{{ $n > 0 ? $n.' kegiatan' : 'Belum dipakai' }}</span>
                        @if ($bisaUbah && ! $terbuka)
                            <button type="button" wire:click="ubahTag('{{ $ra['kode'] }}')" class="btn-ghost px-2 py-1 text-xs" data-testid="ubah-tag-{{ $ra['kode'] }}"><x-ui.icon name="pencil" class="size-3.5" /> Ubah tag</button>
                        @endif
                    </div>
                </div>

                @if ($terbuka && $pilihan !== null)
                    <form wire:submit="simpanTag" x-on:keydown.escape.prevent="$wire.batalTag()" class="mt-3 space-y-4 rounded-xl bg-stone-50 p-4 ring-1 ring-stone-200" data-testid="form-tag-ra">
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <label for="tag-sk" class="form-label">SK</label>
                                <select id="tag-sk" wire:model.live="isianTag.kode_sk" class="form-input" data-testid="tag-sk">
                                    <option value="">— Tanpa SK —</option>
                                    @foreach ($pilihan['sk'] as $o)
                                        <option value="{{ $o['kode'] }}">{{ $o['kode'] }} — {{ \Illuminate\Support\Str::limit($o['uraian'], 60) }}</option>
                                    @endforeach
                                </select>
                                @error('isianTag.kode_sk') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="tag-iksk" class="form-label">IKSK</label>
                                <select id="tag-iksk" wire:model="isianTag.kode_iksk" class="form-input" data-testid="tag-iksk" @disabled(($isianTag['kode_sk'] ?? '') === '')>
                                    <option value="">{{ ($isianTag['kode_sk'] ?? '') === '' ? 'Pilih SK dulu' : '— Tanpa IKSK —' }}</option>
                                    @foreach ($pilihan['iksk'] as $o)
                                        <option value="{{ $o['kode'] }}">{{ $o['kode'] }} — {{ \Illuminate\Support\Str::limit($o['uraian'], 60) }}</option>
                                    @endforeach
                                </select>
                                @error('isianTag.kode_iksk') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="tag-rktm" class="form-label">RKTM (sub kegiatan)</label>
                                <select id="tag-rktm" wire:model.live="isianTag.kode_rktm" class="form-input" data-testid="tag-rktm">
                                    <option value="">— Tanpa RKTM —</option>
                                    @foreach ($pilihan['rktm'] as $o)
                                        <option value="{{ $o['kode'] }}">{{ $o['kode'] }} — {{ \Illuminate\Support\Str::limit($o['sub_kegiatan'], 60) }}</option>
                                    @endforeach
                                </select>
                                @error('isianTag.kode_rktm') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="tag-leading" class="form-label">Leading</label>
                                <select id="tag-leading" wire:model="isianTag.kode_leading" class="form-input" data-testid="tag-leading">
                                    <option value="">— Belum ada —</option>
                                    @foreach ($pilihan['leading'] as $o)
                                        <option value="{{ $o['kode'] }}">{{ $o['kode'] }} {{ $o['nama_unit'] }}</option>
                                    @endforeach
                                    @if (($isianTag['kode_leading'] ?? '') !== '' && MasterKode::cari($pilihan['leading'], $isianTag['kode_leading']) === null)
                                        <option value="{{ $isianTag['kode_leading'] }}">{{ $namaLeading($isianTag['kode_leading']) }} (nonaktif)</option>
                                    @endif
                                </select>
                                @error('isianTag.kode_leading') <p class="form-error">{{ $message }}</p> @enderror
                                @if (filled($ra['unit'] ?? null) && ($unitSama = $this->jumlahUnitSama($ra['kode'])) > 0)
                                    <label class="mt-1.5 flex items-start gap-2 text-xs text-stone-600">
                                        <input type="checkbox" wire:model="isianTag.semua_unit" class="mt-0.5 size-3.5 rounded accent-brand-700" data-testid="tag-semua-unit">
                                        Terapkan juga ke {{ $unitSama }} rencana aksi lain unit “{{ $ra['unit'] }}” (diingat untuk unggahan berikutnya)
                                    </label>
                                @endif
                            </div>
                        </div>

                        <fieldset>
                            <legend class="form-label">Indikator EDM</legend>
                            <label class="mt-1 inline-flex items-center gap-2 text-sm text-stone-700">
                                <input type="checkbox" wire:model.live="isianTag.edm_otomatis" class="size-4 rounded accent-brand-700" data-testid="tag-edm-otomatis">
                                Otomatis dari relasi EDM ↔ RKTM
                            </label>
                            <div class="mt-2 flex flex-wrap gap-1.5" data-testid="tag-edm">
                                @foreach ($pilihan['edm'] as $o)
                                    <label @class(['inline-flex cursor-pointer items-center gap-1 rounded-md px-2 py-1 text-xs ring-1', 'bg-white ring-stone-300' => ! ($isianTag['edm_otomatis'] ?? false), 'cursor-not-allowed bg-stone-100 text-stone-500 ring-stone-200' => $isianTag['edm_otomatis'] ?? false]) title="{{ $o['uraian'] }}">
                                        <input type="checkbox" value="{{ $o['kode'] }}" wire:model="isianTag.kode_edm" class="size-3.5 rounded accent-brand-700" @disabled($isianTag['edm_otomatis'] ?? false)>
                                        {{ $o['kode'] }}
                                    </label>
                                @endforeach
                            </div>
                            @if ($isianTag['edm_otomatis'] ?? false)
                                <p class="mt-1 text-xs text-stone-500">Mengikuti kode RKTM terpilih: {{ ($isianTag['kode_edm'] ?? []) === [] ? 'belum ada indikator yang merekomendasikannya' : implode(', ', $isianTag['kode_edm']) }}.</p>
                            @endif
                            @error('isianTag.kode_edm') <p class="form-error">{{ $message }}</p> @enderror
                        </fieldset>

                        <fieldset>
                            <legend class="form-label">5 Budaya Mutu</legend>
                            <div class="mt-1 flex flex-wrap gap-1.5" data-testid="tag-budaya">
                                @forelse ($pilihan['budaya'] as $o)
                                    <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-md bg-white px-2 py-1 text-xs ring-1 ring-stone-300">
                                        <input type="checkbox" value="{{ $o['nomor'] }}" wire:model="isianTag.budaya_mutu" class="size-3.5 rounded accent-brand-700"> {{ $o['nomor'] }}. {{ $o['nama'] }}
                                    </label>
                                @empty
                                    <span class="text-xs text-stone-500">Master 5 Budaya Mutu TA ini belum ada.</span>
                                @endforelse
                            </div>
                            @error('isianTag.budaya_mutu') <p class="form-error">{{ $message }}</p> @enderror
                        </fieldset>

                        <div class="flex justify-end gap-2">
                            <button type="button" wire:click="batalTag" class="btn-ghost">Batal</button>
                            <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="simpanTag" data-testid="simpan-tag"><x-ui.icon name="save" class="size-4" /> Simpan tag</button>
                        </div>
                    </form>
                @endif
            </li>
        @endforeach
    </ul>
</div>
