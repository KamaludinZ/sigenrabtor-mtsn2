@use('App\Support\Stub\StubData')
@use('App\Support\Stub\StubMaster')
@use('Illuminate\Support\Str')

@php
    $tahunMaster = $this->tahunAktif['tahun'];
    $masterTersedia = StubMaster::tersedia();
    $opsiLeading = StubMaster::leadingsUntuk(StubData::currentUser());
    $leadingTerpilih = StubMaster::cari(StubMaster::leadings(), $form->leading);
    $opsiRktm = collect(StubMaster::kodeRktm())->groupBy(fn ($r) => $r['kegiatan']);
    $rktmTerpilih = StubMaster::cari(StubMaster::kodeRktm(), $form->kode_rktm);
    $skTerpilih = StubMaster::cari(StubMaster::kodeSk(), $form->kode_sk);
    $opsiIksk = $form->kode_sk ? StubMaster::kodeIksk($form->kode_sk) : [];
    $ikskTerpilih = StubMaster::cari(StubMaster::kodeIksk(), $form->kode_iksk);
    $edmTerpilih = StubMaster::cari(StubMaster::kodeEdm(), $form->kode_edm);
@endphp

<div class="space-y-5">
    @if ($masterTersedia)
        <p class="flex items-start gap-2 rounded-lg bg-stone-50 px-3 py-2 text-xs text-stone-600 ring-1 ring-stone-200">
            <x-ui.icon name="database" class="mt-px size-4 shrink-0 text-stone-400" />
            Pilihan kode berasal dari master Tahun Anggaran {{ $tahunMaster }} yang diunggah admin dari Excel. Isi master dapat berbeda setiap tahun.
        </p>
    @else
        <p class="flex items-start gap-2 rounded-lg bg-gold-50 px-3 py-2 text-sm text-gold-700 ring-1 ring-gold-200" role="status">
            <x-ui.icon name="alert" class="mt-0.5 size-4 shrink-0" />
            Master kode Tahun Anggaran {{ $tahunMaster }} belum diunggah admin, sehingga kode RKTM, SK, IKSK, dan EDM belum bisa dipilih.
        </p>
    @endif

    <div class="grid gap-5 sm:grid-cols-2">
        <x-ui.field class="sm:col-span-2" label="Leading penanggung jawab" for="leading" error="form.leading"
                    :hint="$leadingTerpilih ? 'Penanda tangan pembuat: '.$leadingTerpilih['jabatan_penanda_tangan'].'.' : 'Menentukan blok tanda tangan dan buku dokumen leading.'">
            <select id="leading" wire:model.live="form.leading" class="form-input">
                <option value="">— Pilih leading —</option>
                @foreach ($opsiLeading as $opsi)
                    <option value="{{ $opsi['kode'] }}">{{ $opsi['kode'] }} · {{ $opsi['nama_unit'] }}</option>
                @endforeach
            </select>
        </x-ui.field>

        <x-ui.field class="sm:col-span-2" label="Kode RKTM" for="kode_rktm" error="form.kode_rktm">
            <select id="kode_rktm" wire:model.live="form.kode_rktm" class="form-input" @disabled(! $masterTersedia)>
                <option value="">— Pilih kode RKTM —</option>
                @foreach ($opsiRktm as $kegiatanRktm => $daftar)
                    <optgroup label="{{ $kegiatanRktm }}">
                        @foreach ($daftar as $opsi)
                            <option value="{{ $opsi['kode'] }}">{{ $opsi['kode'] }} — {{ Str::limit($opsi['sub_kegiatan'], 90) }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
            @if ($rktmTerpilih)
                <div class="mt-2 flex flex-wrap items-start justify-between gap-2 rounded-lg bg-brand-50/60 px-3 py-2 text-xs text-stone-600 ring-1 ring-brand-100">
                    <span class="min-w-0">
                        <span class="font-semibold text-stone-800">{{ $rktmTerpilih['sub_kegiatan'] }}</span><br>
                        {{ $rktmTerpilih['snp'] }} · {{ $rktmTerpilih['kegiatan'] }}
                    </span>
                    @if (mb_strtolower(trim($form->nama_kegiatan)) !== mb_strtolower(\App\Livewire\Forms\KegiatanForm::tanpaAwalanKegiatan($rktmTerpilih['sub_kegiatan'])))
                        <button type="button" wire:click="pakaiNamaDariRktm" class="shrink-0 font-semibold text-brand-700 hover:text-brand-800">
                            Pakai sebagai nama kegiatan
                        </button>
                    @endif
                </div>
            @endif
        </x-ui.field>

        <x-ui.field label="Kode SK" for="kode_sk" error="form.kode_sk" :hint="$skTerpilih['uraian'] ?? 'Sasaran kegiatan (Perjanjian Kinerja).'">
            <select id="kode_sk" wire:model.live="form.kode_sk" class="form-input" @disabled(! $masterTersedia)>
                <option value="">— Pilih kode SK —</option>
                @foreach (StubMaster::kodeSk() as $opsi)
                    <option value="{{ $opsi['kode'] }}">{{ $opsi['kode'] }} — {{ Str::limit($opsi['uraian'], 60) }}</option>
                @endforeach
            </select>
        </x-ui.field>

        <x-ui.field label="Kode IKSK" for="kode_iksk" error="form.kode_iksk"
                    :hint="$ikskTerpilih['uraian'] ?? ($form->kode_sk ? 'Indikator di bawah '.$form->kode_sk.'.' : 'Pilih kode SK lebih dulu.')">
            <select id="kode_iksk" wire:model.live="form.kode_iksk" class="form-input" @disabled(! $masterTersedia || ! $form->kode_sk)>
                <option value="">— Pilih kode IKSK —</option>
                @foreach ($opsiIksk as $opsi)
                    <option value="{{ $opsi['kode'] }}">{{ $opsi['kode'] }} — {{ Str::limit($opsi['uraian'], 60) }}</option>
                @endforeach
            </select>
        </x-ui.field>

        <x-ui.field class="sm:col-span-2" label="Kode EDM" for="kode_edm" error="form.kode_edm" :hint="$edmTerpilih['uraian'] ?? 'Indikator Evaluasi Diri Madrasah.'">
            <select id="kode_edm" wire:model.live="form.kode_edm" class="form-input" @disabled(! $masterTersedia)>
                <option value="">— Pilih kode EDM —</option>
                @foreach (StubMaster::kodeEdm() as $opsi)
                    <option value="{{ $opsi['kode'] }}">{{ $opsi['kode'] }} — {{ Str::limit($opsi['uraian'], 90) }}</option>
                @endforeach
            </select>
        </x-ui.field>
    </div>

    <div>
        <p class="form-label">5 Budaya Mutu</p>
        <div class="flex flex-wrap gap-2" role="radiogroup" aria-label="5 Budaya Mutu">
            @foreach (StubMaster::budayaMutu() as $butir)
                <label @class([
                        'cursor-pointer rounded-lg px-3 py-1.5 text-sm font-medium ring-1 ring-inset transition has-focus-visible:outline-2 has-focus-visible:outline-brand-600',
                        'bg-brand-700 text-white ring-brand-700' => (int) $form->budaya_mutu === $butir['nomor'],
                        'bg-white text-stone-700 ring-stone-300 hover:bg-stone-50' => (int) $form->budaya_mutu !== $butir['nomor'],
                    ])>
                    <input type="radio" name="budaya_mutu" value="{{ $butir['nomor'] }}" wire:model.live="form.budaya_mutu" class="sr-only">
                    {{ $butir['nomor'] }}. {{ $butir['nama'] }}
                </label>
            @endforeach
        </div>
        @error('form.budaya_mutu') <p class="form-error">{{ $message }}</p> @enderror
    </div>
</div>
