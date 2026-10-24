@use('App\Support\Stub\StubData')
@use('App\Support\MasterKode')
@use('Illuminate\Support\Str')

@php
    $tahunMaster = $this->tahunAktif['tahun'];
    $masterTersedia = MasterKode::tersedia();
    $opsiLeading = MasterKode::leadingsUntuk(StubData::currentUser());
    $opsiRktm = collect(MasterKode::kodeRktm())->groupBy(fn ($r) => $r['snp'].' — '.$r['kegiatan']);
    $rktmTerpilih = MasterKode::cari(MasterKode::kodeRktm(), $form->kode_rktm);
    $skTerpilih = MasterKode::cari(MasterKode::kodeSk(), $form->kode_sk);
    $opsiIksk = $form->kode_sk ? MasterKode::kodeIksk($form->kode_sk) : [];
    $ikskTerpilih = MasterKode::cari(MasterKode::kodeIksk(), $form->kode_iksk);
    $edmTerpilih = MasterKode::cari(MasterKode::kodeEdm(), $form->kode_edm);
    $leadingTerpilih = MasterKode::cari(MasterKode::leadings(), $form->leading);
    $bisaUbah = $this->dapatDiedit();
@endphp

<div class="mx-auto max-w-7xl"
     x-data="{ dirty: $wire.entangle('adaPerubahan') }"
     x-on:beforeunload.window="if (dirty) { $event.preventDefault(); $event.returnValue = ''; }">
    {{-- Kepala halaman --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0">
            <nav class="flex items-center gap-1 text-xs font-medium text-stone-500" aria-label="Breadcrumb">
                <span>Data Kegiatan</span>
                <x-ui.icon name="chevron-right" class="size-3.5" />
                <a href="{{ route('kegiatan.edit', ['kegiatan' => $kegiatanId]) }}" wire:navigate class="truncate hover:text-stone-800">{{ $dataKegiatan['nama_kegiatan'] }}</a>
                <x-ui.icon name="chevron-right" class="size-3.5" />
                <span class="text-stone-700">Kode &amp; Klasifikasi</span>
            </nav>
            <div class="mt-1.5 flex flex-wrap items-center gap-2">
                <h1 class="text-xl font-bold tracking-tight text-stone-900 sm:text-2xl">Kode &amp; Klasifikasi</h1>
                <x-ui.status-badge :status="$this->status()->value" />
            </div>
            <p class="mt-1 text-sm text-stone-500">{{ $dataKegiatan['nama_kegiatan'] }}</p>
        </div>
        @if ($bisaUbah)
            <div class="flex shrink-0 items-center gap-2">
                @if ($adaPerubahan)
                    <span class="inline-flex items-center gap-1 text-sm font-medium text-gold-700" data-testid="belum-disimpan">
                        <span class="size-1.5 rounded-full bg-gold-500"></span> Belum disimpan
                    </span>
                @endif
                <button type="button" class="btn-primary" wire:click="simpan" wire:loading.attr="disabled" wire:target="simpan">
                    <x-ui.icon name="save" class="size-4" />
                    Simpan
                </button>
            </div>
        @endif
    </div>

    <div class="mt-5">
        <x-kegiatan.workspace-tabs active="kode" :kegiatan-id="$kegiatanId" />
    </div>

    @if ($masterTersedia)
        <p class="mt-6 flex items-start gap-2 rounded-lg bg-white px-3 py-2 text-xs text-stone-600 ring-1 ring-stone-200">
            <x-ui.icon name="database" class="mt-px size-4 shrink-0 text-stone-400" />
            Semua pilihan berasal dari master Tahun Anggaran {{ $tahunMaster }} yang diunggah admin dari Excel. Isi master dapat berbeda setiap tahun.
        </p>
    @else
        <p class="mt-6 flex items-start gap-2 rounded-lg bg-gold-50 px-3 py-2 text-sm text-gold-700 ring-1 ring-gold-200" role="status">
            <x-ui.icon name="alert" class="mt-0.5 size-4 shrink-0" />
            Master kode Tahun Anggaran {{ $tahunMaster }} belum diunggah admin, sehingga kode RKTM, SK, IKSK, dan EDM belum bisa dipilih.
        </p>
    @endif

    @unless ($bisaUbah)
        <p class="mt-4 flex items-center gap-2 rounded-xl bg-sky-50 px-4 py-3 text-sm text-sky-900 ring-1 ring-sky-200" role="status" data-testid="banner-terkunci">
            <x-ui.icon name="lock" class="size-4 shrink-0" />
            Kode terkunci karena kegiatan berstatus {{ $this->status()->label() }}.
        </p>
    @endunless

    <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <fieldset @disabled(! $bisaUbah) class="min-w-0 space-y-6">
            {{-- 1. Leading --}}
            <x-ui.form-section id="kode-leading" :number="1" title="Leading penanggung jawab" icon="users"
                               description="Unit penerima mandat; menentukan penanda tangan pembuat dan buku dokumen."
                               :kelengkapan="$this->kelengkapan['leading']">
                @if ($form->leading && ! collect($opsiLeading)->contains('kode', $form->leading))
                    <p class="mb-3 rounded-lg bg-gold-50 px-3 py-2 text-xs text-gold-700 ring-1 ring-gold-200" role="status">
                        Leading tersimpan ({{ $leadingTerpilih['kode'] ?? $form->leading }} · {{ $leadingTerpilih['nama_unit'] ?? 'tidak dikenal' }}) bukan milik akun Anda.
                    </p>
                @endif
                <x-kode.pilih-leading :opsi="$opsiLeading" :terpilih="$form->leading" :peran="StubData::currentUser()['role']" />
                @error('form.leading') <p class="form-error" role="alert">{{ $message }}</p> @enderror
            </x-ui.form-section>

            {{-- 2. Program RKTM --}}
            <x-ui.form-section id="kode-rktm" :number="2" title="Program RKTM" icon="layers"
                               description="Kode RKTM menempatkan kegiatan pada SNP, kegiatan, dan sub kegiatan rencana kerja tahunan."
                               :kelengkapan="$this->kelengkapan['rktm']">
                <x-ui.field label="Kode RKTM" for="kode_rktm" error="form.kode_rktm">
                    <select id="kode_rktm" wire:model.live="form.kode_rktm" class="form-input" @disabled(! $masterTersedia)>
                        <option value="">— Pilih kode RKTM —</option>
                        @foreach ($opsiRktm as $grupRktm => $daftar)
                            <optgroup label="{{ $grupRktm }}">
                                @foreach ($daftar as $opsi)
                                    <option value="{{ $opsi['kode'] }}">{{ $opsi['kode'] }} — {{ Str::limit($opsi['sub_kegiatan'], 90) }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </x-ui.field>
                @if ($rktmTerpilih)
                    <dl class="mt-3 grid gap-2 rounded-lg bg-stone-50 px-3 py-2.5 text-xs ring-1 ring-stone-200 sm:grid-cols-3">
                        <div><dt class="text-stone-500">SNP</dt><dd class="font-medium text-stone-800">{{ $rktmTerpilih['snp'] }}</dd></div>
                        <div><dt class="text-stone-500">Kegiatan</dt><dd class="font-medium text-stone-800">{{ $rktmTerpilih['kegiatan'] }}</dd></div>
                        <div><dt class="text-stone-500">Sub kegiatan</dt><dd class="font-medium text-stone-800">{{ $rktmTerpilih['sub_kegiatan'] }}</dd></div>
                    </dl>
                @endif
            </x-ui.form-section>

            {{-- 3. Perjanjian kinerja: SK → IKSK --}}
            <x-ui.form-section id="kode-perkin" :number="3" title="Perjanjian Kinerja" icon="check-badge"
                               description="Sasaran Kegiatan (SK) dan Indikator Kinerja Sasaran Kegiatan (IKSK) dari cascade Perkin."
                               :kelengkapan="$this->kelengkapan['perkin']">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-ui.field label="Kode SK" for="kode_sk" error="form.kode_sk" :hint="$skTerpilih['uraian'] ?? null">
                        <select id="kode_sk" wire:model.live="form.kode_sk" class="form-input" @disabled(! $masterTersedia)>
                            <option value="">— Pilih kode SK —</option>
                            @foreach (MasterKode::kodeSk() as $opsi)
                                <option value="{{ $opsi['kode'] }}">{{ $opsi['kode'] }} — {{ Str::limit($opsi['uraian'], 60) }}</option>
                            @endforeach
                        </select>
                    </x-ui.field>
                    <x-ui.field label="Kode IKSK" for="kode_iksk" error="form.kode_iksk"
                                :hint="$ikskTerpilih['uraian'] ?? ($form->kode_sk ? count($opsiIksk).' indikator di bawah '.$form->kode_sk.'.' : 'Pilih kode SK lebih dulu.')">
                        <select id="kode_iksk" wire:model.live="form.kode_iksk" class="form-input" @disabled(! $masterTersedia || ! $form->kode_sk)>
                            <option value="">— Pilih kode IKSK —</option>
                            @foreach ($opsiIksk as $opsi)
                                <option value="{{ $opsi['kode'] }}">{{ $opsi['kode'] }} — {{ Str::limit($opsi['uraian'], 60) }}</option>
                            @endforeach
                        </select>
                    </x-ui.field>
                </div>
            </x-ui.form-section>

            {{-- 4. EDM --}}
            <x-ui.form-section id="kode-edm" :number="4" title="Evaluasi Diri Madrasah (EDM)" icon="clipboard"
                               description="Indikator EDM yang didukung kegiatan ini."
                               :kelengkapan="$this->kelengkapan['edm']">
                <x-ui.field label="Kode EDM" for="kode_edm" error="form.kode_edm" :hint="$edmTerpilih['uraian'] ?? null">
                    <select id="kode_edm" wire:model.live="form.kode_edm" class="form-input" @disabled(! $masterTersedia)>
                        <option value="">— Pilih kode EDM —</option>
                        @foreach (MasterKode::kodeEdm() as $opsi)
                            <option value="{{ $opsi['kode'] }}">{{ $opsi['kode'] }} — {{ Str::limit($opsi['uraian'], 90) }}</option>
                        @endforeach
                    </select>
                </x-ui.field>
            </x-ui.form-section>

            {{-- 5. 5 Budaya Mutu --}}
            <x-ui.form-section id="kode-budaya" :number="5" title="5 Budaya Mutu" icon="sparkles"
                               description="Nilai budaya kerja yang paling diperkuat oleh kegiatan."
                               :kelengkapan="$this->kelengkapan['budaya']">
                <div class="flex flex-wrap gap-2" role="radiogroup" aria-label="5 Budaya Mutu">
                    @foreach (MasterKode::budayaMutu() as $butir)
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
            </x-ui.form-section>
        </fieldset>

        {{-- Pratinjau header RAB --}}
        <aside class="space-y-4">
            <div class="rounded-2xl bg-white p-4 shadow-xs ring-1 ring-stone-200 lg:sticky lg:top-24" data-testid="pratinjau-header">
                <p class="text-[11px] font-semibold tracking-wider text-stone-400 uppercase">Tercetak di header RAB</p>
                <dl class="mt-3 space-y-3 text-sm">
                    @foreach ($this->pratinjauHeader as $kunci => $baris)
                        <div>
                            <dt class="text-xs text-stone-500">{{ $baris['label'] }}</dt>
                            <dd @class(['font-medium', 'text-stone-900' => $baris['nilai'], 'text-stone-400 italic' => ! $baris['nilai']])>
                                {{ $baris['nilai'] ?? 'belum dipilih' }}
                            </dd>
                            @if ($baris['detail'])
                                <dd class="mt-0.5 line-clamp-2 text-xs text-stone-500">{{ $baris['detail'] }}</dd>
                            @endif
                        </div>
                    @endforeach
                </dl>

                @if ($bisaUbah)
                    <div class="mt-4 space-y-2 border-t border-stone-100 pt-4">
                        <button type="button" wire:click="simpan(true)" wire:loading.attr="disabled" class="btn-primary w-full">
                            Simpan &amp; lanjut ke RAB
                            <x-ui.icon name="chevron-right" class="size-4" />
                        </button>
                    </div>
                @endif
            </div>
        </aside>
    </div>
</div>
