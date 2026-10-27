@use('App\Support\Stub\StubData')
@use('App\Support\MasterKode')
@use('Illuminate\Support\Str')
@use('Illuminate\Support\Carbon')
@use('App\Support\Kegiatan\RingkasanKode')

@php
    $tahunMaster = $this->tahunAktif['tahun'];
    $masterTersedia = MasterKode::tersedia();
    $opsiLeading = MasterKode::pilihanLeading(StubData::currentUser(), $form->leadingTersimpan());
    $rktmTerpilih = MasterKode::cari(MasterKode::kodeRktm(), $form->kode_rktm);
    $skTerpilih = MasterKode::cari(MasterKode::kodeSk(), $form->kode_sk);
    $opsiIksk = $form->kode_sk ? MasterKode::kodeIksk($form->kode_sk) : [];
    $opsiIkskCari = array_map(fn ($i) => ['nilai' => $i['kode'], 'label' => $i['uraian'], 'keterangan' => null, 'grup' => null], $opsiIksk);
    $ikskTerpilih = MasterKode::cari(MasterKode::kodeIksk(), $form->kode_iksk);
    $edmTerpilih = MasterKode::cari(MasterKode::kodeEdm(), $form->kode_edm);
    $leadingTerpilih = MasterKode::leading($form->leading);
    $bisaUbah = $this->dapatDiedit();
    $opsiRktmCari = array_map(fn ($r) => ['nilai' => $r['kode'], 'label' => $r['sub_kegiatan'], 'keterangan' => null, 'grup' => $r['snp'].' — '.$r['kegiatan']], MasterKode::kodeRktm());
    $komponenEdm = MasterKode::komponenEdm();
    $opsiSkCari = array_map(fn ($sk) => ['nilai' => $sk['kode'], 'label' => $sk['uraian'], 'keterangan' => count(MasterKode::kodeIksk($sk['kode'])).' indikator IKSK', 'grup' => null], MasterKode::kodeSk());
    $opsiEdmCari = array_map(fn ($e) => ['nilai' => $e['kode'], 'label' => $e['uraian'], 'keterangan' => null, 'grup' => $e['komponen'].' · '.($komponenEdm[$e['komponen']] ?? '')], MasterKode::kodeEdm());
@endphp

<div class="mx-auto max-w-7xl"
     x-data="{ dirty: $wire.entangle('adaPerubahan') }"
     x-on:beforeunload.window="if (dirty) { $event.preventDefault(); $event.returnValue = ''; }"
     @if ($bisaUbah) x-on:keydown.window.ctrl.s.prevent="$wire.simpan()" x-on:keydown.window.meta.s.prevent="$wire.simpan()" @endif>
    {{-- Kepala halaman --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0">
            <nav class="flex items-center gap-1 text-xs font-medium text-stone-500" aria-label="Breadcrumb">
                <a href="{{ route('kegiatan.index') }}" wire:navigate class="hover:text-stone-800">Data Kegiatan</a>
                <x-ui.icon name="chevron-right" class="size-3.5" />
                <a href="{{ route('kegiatan.edit', ['kegiatan' => $kegiatanId]) }}" wire:navigate class="truncate hover:text-stone-800">{{ $dataKegiatan['nama_kegiatan'] }}</a>
                <x-ui.icon name="chevron-right" class="size-3.5" />
                <span class="text-stone-700">Kode &amp; Klasifikasi</span>
            </nav>
            <div class="mt-1.5 flex flex-wrap items-center gap-2">
                <h1 class="text-xl font-bold tracking-tight text-stone-900 sm:text-2xl">Kode &amp; Klasifikasi</h1>
                <x-ui.status-badge :status="$this->status()->value" />
            </div>
            <p class="mt-1 flex flex-wrap items-center gap-x-2 text-sm text-stone-500">
                <span>{{ $dataKegiatan['nama_kegiatan'] }}</span>
                @if ($dataKegiatan['diperbarui_pada'] ?? null)
                    <span class="text-stone-300" aria-hidden="true">·</span>
                    <span data-testid="info-simpan">Terakhir disimpan {{ Carbon::parse($dataKegiatan['diperbarui_pada'])->locale('id')->translatedFormat('j F Y, H.i') }}</span>
                @endif
            </p>
        </div>
        @if ($bisaUbah)
            <div class="flex shrink-0 flex-wrap items-center gap-2">
                @if ($adaPerubahan)
                    <span class="inline-flex items-center gap-1 text-sm font-medium text-gold-700" data-testid="belum-disimpan">
                        <span class="size-1.5 rounded-full bg-gold-500"></span> Belum disimpan
                    </span>
                    <button type="button" class="btn-ghost" wire:click="batalkanPerubahan" wire:loading.attr="disabled">Batalkan</button>
                @endif
                <button type="button" class="btn-primary" wire:click="simpan" wire:loading.attr="disabled" wire:target="simpan" title="Simpan (Ctrl+S)">
                    <x-ui.icon name="save" class="size-4" />
                    <span wire:loading.remove wire:target="simpan">Simpan</span>
                    <span wire:loading wire:target="simpan">Menyimpan…</span>
                </button>
            </div>
        @endif
    </div>

    <div class="mt-5">
        <x-kegiatan.workspace-tabs active="kode" :kegiatan-id="$kegiatanId" :status-kode="$this->statusKode" />
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

    <x-kode.status-kelengkapan :status="$this->statusKode" class="mt-4" />

    @if ($bentrok)
        <div class="mt-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-900 ring-1 ring-red-200" role="alert" data-testid="banner-bentrok">
            <p class="flex items-start gap-2 font-medium">
                <x-ui.icon name="alert" class="mt-0.5 size-4 shrink-0 text-red-600" />
                Kode kegiatan ini diubah di tempat lain sejak halaman dibuka. Pilihan Anda belum disimpan.
            </p>
            <ul class="mt-1.5 ml-6 list-disc space-y-0.5 text-xs">
                @foreach ($bentrok as $isian => [$lama, $baru])
                    <li>{{ RingkasanKode::LABEL[$isian] }}: {{ RingkasanKode::tampilNilai($isian, $lama) }} → {{ RingkasanKode::tampilNilai($isian, $baru) }}</li>
                @endforeach
            </ul>
            <div class="mt-3 ml-6 flex flex-wrap gap-2">
                <button type="button" class="btn-secondary" wire:click="muatVersiTerbaru">Muat versi terbaru</button>
                <button type="button" class="btn-ghost text-red-700" wire:click="simpan(false, true)">Timpa dengan pilihan saya</button>
            </div>
        </div>
    @endif

    <x-kegiatan.banner-kunci :kegiatan-id="$kegiatanId" :status="$this->status()->value" subjek="Kode" class="mt-4" />

    <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <fieldset @disabled(! $bisaUbah) class="min-w-0 space-y-6">
            {{-- 1. Leading --}}
            <x-ui.form-section id="kode-leading" :number="1" title="Leading penanggung jawab" icon="users"
                               description="Unit penerima mandat; menentukan penanda tangan pembuat dan buku dokumen."
                               :kelengkapan="$this->kelengkapan['leading']">
                @if ($leadingTerpilih && ! $leadingTerpilih['is_active'])
                    <p class="mb-3 rounded-lg bg-stone-100 px-3 py-2 text-xs text-stone-700 ring-1 ring-stone-200" role="status" data-testid="leading-nonaktif">
                        Leading tersimpan ({{ $leadingTerpilih['kode'] }} · {{ $leadingTerpilih['nama_unit'] }}) sudah dinonaktifkan admin. Kegiatan ini tetap memakainya; leading nonaktif tidak bisa dipilih untuk kegiatan lain.
                    </p>
                @endif
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
                    <x-ui.pilih-cari id="kode_rktm" model="form.kode_rktm" :opsi="$opsiRktmCari" :disabled="! $masterTersedia || ! $bisaUbah"
                                     placeholder="— Pilih kode RKTM —" cari-placeholder="Cari kode, SNP, atau sub kegiatan…" />
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
                    <x-ui.field label="Kode SK" for="kode_sk" error="form.kode_sk" required
                                :hint="$skTerpilih ? null : 'Wajib: Sasaran Kegiatan Perjanjian Kinerja yang didukung kegiatan ini.'">
                        <x-ui.pilih-cari id="kode_sk" model="form.kode_sk" :opsi="$opsiSkCari" :disabled="! $masterTersedia || ! $bisaUbah"
                                         placeholder="— Pilih kode SK —" cari-placeholder="Cari kode atau sasaran kegiatan…" />
                        @if ($skTerpilih)
                            <p class="mt-1.5 text-xs leading-relaxed text-stone-600" data-testid="uraian-sk">{{ $skTerpilih['uraian'] }}</p>
                        @endif
                    </x-ui.field>
                    <x-ui.field label="Kode IKSK" for="kode_iksk" error="form.kode_iksk"
                                :hint="$form->kode_sk ? count($opsiIksk).' indikator di bawah '.$form->kode_sk.'.' : 'Pilih kode SK lebih dulu; daftar IKSK menyesuaikan SK.'">
                        <x-ui.pilih-cari id="kode_iksk" model="form.kode_iksk" :opsi="$opsiIkskCari" :disabled="! $masterTersedia || ! $form->kode_sk || ! $bisaUbah"
                                         :placeholder="$form->kode_sk ? '— Pilih kode IKSK —' : '— Pilih SK dulu —'" cari-placeholder="Cari indikator…" />
                        @if ($ikskTerpilih)
                            <p class="mt-1.5 text-xs leading-relaxed text-stone-600" data-testid="uraian-iksk">{{ $ikskTerpilih['uraian'] }}</p>
                        @endif
                    </x-ui.field>
                </div>
            </x-ui.form-section>

            {{-- 4. EDM --}}
            <x-ui.form-section id="kode-edm" :number="4" title="Evaluasi Diri Madrasah (EDM)" icon="clipboard"
                               description="Indikator EDM yang didukung kegiatan ini."
                               :kelengkapan="$this->kelengkapan['edm']">
                <x-ui.field label="Kode EDM" for="kode_edm" error="form.kode_edm" :hint="$edmTerpilih['uraian'] ?? null">
                    <x-ui.pilih-cari id="kode_edm" model="form.kode_edm" :opsi="$opsiEdmCari" :disabled="! $masterTersedia || ! $bisaUbah"
                                     placeholder="— Pilih kode EDM —" cari-placeholder="Cari kode atau indikator EDM…" />
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
            <div class="rounded-2xl bg-white p-4 shadow-xs ring-1 ring-stone-200 lg:sticky lg:top-24 lg:max-h-[calc(100vh-7rem)] lg:overflow-y-auto">
                <x-kode.ringkasan :baris="$this->ringkasanKode" judul="Kode terpilih" />

                {{-- Uraian lengkap ada di ringkasan; di sini cukup baris yang tercetak di header RAB (dilipat agar tombol tetap terlihat; wire:ignore.self menjaga status buka/tutup saat render ulang). --}}
                <details class="group mt-4 border-t border-stone-100 pt-3" data-testid="pratinjau-header" wire:ignore.self>
                    <summary class="flex cursor-pointer list-none items-center justify-between text-[11px] font-semibold tracking-wider text-stone-400 uppercase hover:text-stone-600">
                        Tercetak di header RAB
                        <x-ui.icon name="chevron-down" class="size-3.5 transition group-open:rotate-180" />
                    </summary>
                    <dl class="mt-2 space-y-1.5 text-xs">
                        @foreach ($this->pratinjauHeader as $kunci => $baris)
                            <div class="flex gap-2">
                                <dt class="w-24 shrink-0 text-stone-500">{{ $baris['label'] }}</dt>
                                <dd @class(['min-w-0 font-medium', 'text-stone-900' => $baris['nilai'], 'text-stone-400 italic' => ! $baris['nilai']])>
                                    {{ $baris['nilai'] ?? 'belum dipilih' }}
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                </details>

                @if ($this->riwayatKode)
                    <details class="group mt-3 border-t border-stone-100 pt-3" data-testid="riwayat-kode" wire:ignore.self>
                        <summary class="flex cursor-pointer list-none items-center justify-between text-[11px] font-semibold tracking-wider text-stone-400 uppercase hover:text-stone-600">
                            Riwayat perubahan ({{ count($this->riwayatKode) }})
                            <x-ui.icon name="chevron-down" class="size-3.5 transition group-open:rotate-180" />
                        </summary>
                        <ol class="mt-2 space-y-2.5 text-xs">
                            @foreach (array_slice($this->riwayatKode, 0, 5) as $entri)
                                <li>
                                    <p class="text-stone-500">{{ Carbon::parse($entri['pada'])->locale('id')->translatedFormat('j M Y, H.i') }} · {{ $entri['oleh'] }}</p>
                                    @foreach ($entri['perubahan'] as $isian => [$lama, $baru])
                                        <p class="text-stone-700"><span class="font-medium">{{ RingkasanKode::LABEL[$isian] }}</span>: {{ RingkasanKode::tampilNilai($isian, $lama) }} → {{ RingkasanKode::tampilNilai($isian, $baru) }}</p>
                                    @endforeach
                                </li>
                            @endforeach
                        </ol>
                    </details>
                @endif

                @if ($bisaUbah)
                    <div class="mt-4 space-y-2 border-t border-stone-100 pt-4">
                        <button type="button" wire:click="simpan(true)" wire:loading.attr="disabled" wire:target="simpan" class="btn-primary w-full">
                            Simpan &amp; lanjut ke RAB
                            <x-ui.icon name="chevron-right" class="size-4" />
                        </button>
                    </div>
                @endif
            </div>
        </aside>
    </div>
</div>
