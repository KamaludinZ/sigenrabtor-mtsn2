@use('App\Support\Kegiatan\BagianAi')
@use('App\Support\Kegiatan\KonteksAi')
@use('App\Support\Kegiatan\JadwalKegiatan')
@use('Illuminate\Support\Carbon')

@php
    $bisaUbah = $this->dapatDiedit();
    $kegiatan = $this->kegiatanSekarang();
    $susunan = $this->susunan();
    $ringkasan = $this->ringkasan();
    $jumlahTerisi = count(array_filter($ringkasan['terisi']));
    $ikon = ['nama_cover' => 'book', 'latar_belakang' => 'text-align', 'tujuan' => 'check-badge', 'sasaran' => 'users', 'pelaksanaan' => 'clipboard', 'jadwal' => 'calendar'];
    $placeholder = [
        'latar_belakang' => 'Alasan khusus kegiatan ini diselenggarakan (opsional) — mis. tindak lanjut temuan EDM atau kebutuhan mendesak.',
        'tujuan' => 'mis. menghasilkan dokumen kurikulum yang terlegitimasi sesuai prinsip partisipatif, transparan, dan akuntabel.',
        'sasaran' => 'mis. dokumen perencanaan yang handal dan akurat sesuai kebutuhan riil madrasah.',
        'pelaksanaan' => 'mis. workshop tatap muka dengan narasumber pengawas madrasah, dilanjutkan kerja kelompok.',
    ];
    $maks = ['latar_belakang' => 5000, 'tujuan' => 2000, 'sasaran' => 2000, 'pelaksanaan' => 3000];
@endphp

{{--
    Kalimat KAK (PRD §20): enam bagian yang bisa dibantu AI dalam urutan dokumen. Tiap kartu: isian (jadwal: ringkasan +
    tautan Matrik & Jadwal), bahan dari data kegiatan untuk AI, apa yang dihasilkan AI, dan pratinjau bagian KAK.
--}}
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
                <span class="text-stone-700">Kalimat KAK</span>
            </nav>
            <div class="mt-1.5 flex flex-wrap items-center gap-2">
                <h1 class="text-xl font-bold tracking-tight text-stone-900 sm:text-2xl">Kalimat KAK</h1>
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
                <button type="button" class="btn-primary" wire:click="simpan" wire:loading.attr="disabled" wire:target="simpan" title="Simpan (Ctrl+S)" data-testid="simpan-kalimat">
                    <x-ui.icon name="save" class="size-4" />
                    <span wire:loading.remove wire:target="simpan">Simpan</span>
                    <span wire:loading wire:target="simpan">Menyimpan…</span>
                </button>
            </div>
        @endif
    </div>

    <div class="mt-5">
        <x-kegiatan.workspace-tabs active="kalimat" :kegiatan-id="$kegiatanId" />
    </div>

    <p class="mt-6 flex items-start gap-2 rounded-lg bg-white px-3 py-2 text-xs text-stone-600 ring-1 ring-stone-200">
        <x-ui.icon name="sparkles" class="mt-px size-4 shrink-0 text-brand-600" />
        AI hanya menyarankan kalimat untuk keenam bagian ini — Anda yang memilih, mengedit, lalu menerima atau menolaknya. Angka, kode, dan nama pejabat tidak pernah diubah AI.
    </p>

    @if ($bentrok)
        <div class="mt-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-900 ring-1 ring-red-200" role="alert" data-testid="banner-bentrok">
            <p class="flex items-start gap-2 font-medium">
                <x-ui.icon name="alert" class="mt-0.5 size-4 shrink-0 text-red-600" />
                Kalimat kegiatan ini diubah di tempat lain sejak halaman dibuka. Suntingan Anda belum disimpan.
            </p>
            <ul class="mt-1.5 ml-6 list-disc space-y-0.5 text-xs">
                @foreach (array_keys($bentrok) as $kolom)
                    <li>{{ collect(BagianAi::BAGIAN)->firstWhere('kolom', $kolom)['judul'] }}</li>
                @endforeach
            </ul>
            <div class="mt-3 ml-6 flex flex-wrap gap-2">
                <button type="button" class="btn-secondary" wire:click="muatVersiTerbaru">Muat versi terbaru</button>
                <button type="button" class="btn-ghost text-red-700" wire:click="simpan(true)">Timpa dengan suntingan saya</button>
            </div>
        </div>
    @endif

    <x-kegiatan.banner-kunci :kegiatan-id="$kegiatanId" :status="$this->status()->value" subjek="Kalimat KAK" class="mt-4" />

    <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <fieldset @disabled(! $bisaUbah) class="min-w-0 space-y-6" data-testid="bagian-kalimat">
            @foreach (BagianAi::BAGIAN as $kunci => $b)
                <x-ui.form-section :id="'kalimat-'.$kunci" :number="$loop->iteration" :title="$b['judul']" :icon="$ikon[$kunci]"
                                   :description="'Tercetak di '.$b['tercetak'].'.'"
                                   :kelengkapan="$ringkasan['terisi'][$kunci] ? 'lengkap' : ($kunci === 'latar_belakang' ? 'opsional' : 'kosong')"
                                   data-bagian-ai="{{ $kunci }}">
                    <x-slot:actions>
                        <button type="button" class="btn-secondary px-2.5 py-1.5 text-xs" disabled title="Sedang disiapkan" data-testid="buat-ai-{{ $kunci }}">
                            <x-ui.icon name="sparkles" class="size-3.5" /> Buat dengan AI <x-ui.segera />
                        </button>
                    </x-slot:actions>

                    @switch($kunci)
                        @case('nama_cover')
                            <x-ui.field label="Nama kegiatan (cover)" for="nama_kegiatan_cover" error="form.nama_kegiatan_cover" required
                                        hint="Selalu huruf kapital; tercetak di cover KAK/TOR dan judul RAB.">
                                <x-slot:aside>
                                    @if ($form->cover_manual)
                                        <button type="button" wire:click="resetNamaCover" class="font-semibold text-brand-700 hover:text-brand-800">Samakan dengan nama kegiatan</button>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded bg-brand-50 px-1.5 py-0.5 font-semibold text-brand-700"><x-ui.icon name="check" class="size-3" /> Otomatis</span>
                                    @endif
                                </x-slot:aside>
                                <input type="text" id="nama_kegiatan_cover" wire:model.live.blur="form.nama_kegiatan_cover"
                                       x-on:input.capture="const [a, b] = [$el.selectionStart, $el.selectionEnd]; $el.value = $el.value.toUpperCase(); $el.setSelectionRange(a, b)" maxlength="255"
                                       autocomplete="off" class="form-input uppercase tracking-wide" placeholder="Terisi otomatis dari nama kegiatan"
                                       @error('form.nama_kegiatan_cover') aria-invalid="true" aria-describedby="nama_kegiatan_cover-error" @enderror>
                            </x-ui.field>
                            @break

                        @case('jadwal')
                            @php
                                $barisJadwal = array_values(array_filter($this->jadwal->baris, fn ($r) => $r['uraian'] !== '' || $r['bulan'] !== []));
                            @endphp
                            @if ($barisJadwal === [])
                                <p class="rounded-lg bg-stone-50 px-3 py-2.5 text-sm text-stone-500 ring-1 ring-stone-200" data-testid="jadwal-kosong">Jadwal kegiatan belum diisi.</p>
                            @else
                                <ol class="divide-y divide-stone-100 rounded-lg ring-1 ring-stone-200" data-testid="ringkasan-jadwal">
                                    @foreach ($barisJadwal as $r)
                                        <li class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-0.5 px-3 py-2 text-sm">
                                            <span class="min-w-0 text-stone-800">{{ $r['uraian'] ?: '—' }}</span>
                                            <span class="shrink-0 text-xs text-stone-500">{{ (new JadwalKegiatan([$r]))->waktu(0, $tahunAktif) ?? 'Bulan belum diisi' }}</span>
                                        </li>
                                    @endforeach
                                </ol>
                            @endif
                            <a href="{{ route('kegiatan.matrik', ['kegiatan' => $kegiatanId]) }}#jadwal" wire:navigate class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-brand-700 hover:text-brand-800">
                                Ubah jadwal &amp; usulan AI di Matrik &amp; Jadwal <x-ui.icon name="chevron-right" class="size-3.5" />
                            </a>
                            @break

                        @default
                            <x-ui.uraian :id="$b['kolom']" :label="$b['judul']" :model="'form.'.$b['kolom']" :max="$maks[$kunci]" :rows="$kunci === 'latar_belakang' ? 4 : 3"
                                         :placeholder="$placeholder[$kunci]" />
                    @endswitch

                    @include('livewire.kegiatan.partials.bahan-kalimat')
                </x-ui.form-section>
            @endforeach
        </fieldset>

        {{-- Panel samping --}}
        <aside class="space-y-4 lg:sticky lg:top-24 lg:self-start">
            <nav class="rounded-2xl bg-white p-4 shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-daftar-bagian" data-testid="daftar-bagian-kalimat">
                <div class="flex items-center justify-between gap-2">
                    <h2 id="judul-daftar-bagian" class="text-xs font-semibold tracking-wide text-stone-500 uppercase">Bagian KAK</h2>
                    <span class="rounded-full bg-stone-100 px-2 py-0.5 text-[11px] font-semibold text-stone-600 tabular-nums">{{ $jumlahTerisi }}/{{ count(BagianAi::BAGIAN) }} terisi</span>
                </div>
                <ol class="mt-2 space-y-0.5 text-sm">
                    @foreach (BagianAi::BAGIAN as $kunci => $b)
                        <li>
                            <a href="#kalimat-{{ $kunci }}" class="flex items-center gap-2 rounded-md px-2 py-1.5 text-stone-700 hover:bg-stone-50 hover:text-stone-900" data-terisi="{{ $ringkasan['terisi'][$kunci] ? 'ya' : 'tidak' }}">
                                @if ($ringkasan['terisi'][$kunci])
                                    <x-ui.icon name="check-circle" class="size-4 shrink-0 text-brand-600" />
                                @else
                                    <x-ui.icon name="circle" class="size-4 shrink-0 text-stone-300" />
                                @endif
                                <span class="min-w-0 truncate">{{ $b['judul'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ol>
            </nav>

            <section class="rounded-2xl bg-white p-4 shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-konteks-ai" data-testid="konteks-kalimat">
                <h2 id="judul-konteks-ai" class="text-sm font-semibold text-stone-900">Catatan konteks</h2>
                <p class="mt-0.5 text-xs text-stone-500">Bahan tambahan AI dari Data Kegiatan; makin lengkap, makin sesuai kalimatnya.</p>
                <ul class="mt-2 space-y-1 text-xs">
                    @foreach (KonteksAi::ASPEK as $aspek => [$label])
                        <li class="flex items-center gap-2 {{ $ringkasan['aspek'][$aspek] ? 'text-stone-700' : 'text-stone-400' }}" data-aspek="{{ $aspek }}">
                            <x-ui.icon :name="$ringkasan['aspek'][$aspek] ? 'check-circle' : 'circle'" class="size-3.5 shrink-0 {{ $ringkasan['aspek'][$aspek] ? 'text-brand-600' : '' }}" />
                            {{ $label }}
                        </li>
                    @endforeach
                </ul>
                <a href="{{ route('kegiatan.edit', ['kegiatan' => $kegiatanId]) }}#konteks" wire:navigate class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-brand-700 hover:text-brand-800">
                    {{ $ringkasan['konteks'] ? 'Ubah catatan konteks' : 'Tulis catatan konteks' }} <x-ui.icon name="chevron-right" class="size-3.5" />
                </a>
            </section>

            <section class="rounded-2xl bg-white p-4 shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-batas-ai" data-testid="batas-aman-ai">
                <h2 id="judul-batas-ai" class="text-sm font-semibold text-stone-900">Batas aman AI</h2>
                <ul class="mt-2 space-y-1.5 text-xs text-stone-600">
                    <li class="flex items-start gap-2"><x-ui.icon name="lock" class="mt-px size-3.5 shrink-0 text-stone-400" /> Angka, kode, dan nama pejabat tidak pernah diubah AI.</li>
                    <li class="flex items-start gap-2"><x-ui.icon name="lock" class="mt-px size-3.5 shrink-0 text-stone-400" /> NIP dan nama pejabat tidak dikirim ke penyedia AI.</li>
                    <li class="flex items-start gap-2"><x-ui.icon name="history" class="mt-px size-3.5 shrink-0 text-stone-400" /> Setiap saran tercatat di riwayat saran AI.</li>
                    <li class="flex items-start gap-2"><x-ui.icon name="info" class="mt-px size-3.5 shrink-0 text-stone-400" />
                        {{ $ringkasan['penyedia'] ? 'Penyedia: '.$ringkasan['penyedia'].'.' : 'Penyedia AI belum dipilih admin — saran memakai contoh tiruan.' }}
                        Batas {{ $ringkasan['batas'] }} permintaan per hari.
                    </li>
                </ul>
            </section>
        </aside>
    </div>
</div>
