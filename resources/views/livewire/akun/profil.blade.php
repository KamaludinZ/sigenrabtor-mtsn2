@use('App\Livewire\Akun\Profil')
@use('App\Support\Format')

@php
    $akun = $this->akun;
    $rincian = $akun !== null ? $this->rincian : null;
    $waktu = fn (?string $iso) => $iso ? Format::tanggal(substr($iso, 0, 10)).' · '.\Carbon\CarbonImmutable::parse($iso)->setTimezone(config('app.timezone'))->format('H.i') : '—';
@endphp

<div class="mx-auto max-w-5xl">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="flex min-w-0 items-center gap-4">
            <span class="grid size-14 shrink-0 place-items-center rounded-full bg-brand-100 text-lg font-bold text-brand-800" aria-hidden="true">
                {{ \Illuminate\Support\Str::of($akun['nama'] ?? $currentUser['name'] ?? '?')->explode(' ')->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('') }}
            </span>
            <div class="min-w-0">
                <h1 class="truncate text-xl font-bold tracking-tight text-stone-900 sm:text-2xl">{{ $akun['nama'] ?? 'Akun saya' }}</h1>
                <p class="mt-0.5 text-sm text-stone-500">{{ $akun['label_peran'] ?? '' }}{{ ($akun['jabatan'] ?? null) ? ' · '.$akun['jabatan'] : '' }}</p>
            </div>
        </div>
    </div>

    <nav class="mt-5 -mb-px flex gap-1 overflow-x-auto border-b border-stone-200" aria-label="Bagian akun" data-testid="tab-akun">
        @foreach (Profil::TAB as $kunci => $label)
            @if ($kunci === $tab)
                <span aria-current="page" class="shrink-0 border-b-2 border-brand-700 px-3 py-2.5 text-sm font-semibold text-brand-800" data-tab="{{ $kunci }}">{{ $label }}</span>
            @else
                <button type="button" wire:click="$set('tab', '{{ $kunci }}')" class="shrink-0 border-b-2 border-transparent px-3 py-2.5 text-sm font-medium text-stone-500 hover:border-stone-300 hover:text-stone-800" data-tab="{{ $kunci }}">{{ $label }}</button>
            @endif
        @endforeach
    </nav>

    @if ($akun === null)
        <p class="mt-6 rounded-2xl bg-white px-5 py-10 text-center text-sm text-stone-500 shadow-xs ring-1 ring-stone-200" data-testid="akun-tidak-tercatat">Akun ini belum tercatat di Pengaturan › Leading & User.</p>
    @elseif ($tab === 'profil')
        <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
            <section class="min-w-0 rounded-2xl bg-white p-5 shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-profil" data-testid="profil-akun">
                <h2 id="judul-profil" class="text-sm font-semibold text-stone-900">Profil</h2>
                <p class="mt-0.5 text-xs text-stone-500">Nama & jabatan tercetak sebagai penyusun bila pejabat leading belum diisi.</p>
                <dl class="mt-4 grid gap-x-6 gap-y-4 sm:grid-cols-2">
                    @foreach ([
                        'nama' => ['Nama lengkap', $akun['nama']],
                        'surel' => ['Surel (untuk masuk)', $akun['surel']],
                        'jabatan' => ['Jabatan', $akun['jabatan'] ?? '—'],
                        'nip' => ['NIP', $akun['nip_format'] ?? '—'],
                    ] as $kunci => [$label, $nilai])
                        <div data-profil="{{ $kunci }}">
                            <dt class="text-xs font-semibold text-stone-500">{{ $label }}</dt>
                            <dd @class(['mt-0.5 text-sm text-stone-900', 'font-mono' => in_array($kunci, ['surel', 'nip'], true)])>{{ $nilai }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>
            <div class="min-w-0 space-y-6 lg:col-start-1">
                @if ($akun['peran'] !== 'admin')
                    <section class="rounded-2xl bg-white p-5 shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-tercetak" data-testid="tercetak-akun">
                        <h2 id="judul-tercetak" class="text-sm font-semibold text-stone-900">Tercetak di dokumen</h2>
                        <p class="mt-0.5 text-xs text-stone-500">Penanda tangan kolom Pembuat di lembar pengesahan KAK/TOR & RAB TA {{ $rincian['tahun'] }}.</p>
                        @if ($rincian['tercetak'] === [])
                            <p class="mt-3 text-sm text-stone-500">Belum ada leading tertaut.</p>
                        @else
                            <ul class="mt-3 space-y-3" role="list">
                                @foreach ($rincian['tercetak'] as $c)
                                    <li class="rounded-xl bg-stone-50 p-3 ring-1 ring-stone-200" data-testid="tercetak-leading" data-leading="{{ $c['kode'] }}" data-sumber="{{ $c['sumber'] }}">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <p class="text-xs font-semibold text-stone-700">{{ $c['kode'] }} · {{ $c['nama_unit'] }}</p>
                                            <span @class(['rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset',
                                                'bg-brand-50 text-brand-800 ring-brand-200' => $c['sumber'] === 'akun',
                                                'bg-white text-stone-700 ring-stone-200' => $c['sumber'] === 'pejabat',
                                                'bg-gold-50 text-gold-800 ring-gold-200' => $c['sumber'] === 'kosong'])>{{ $c['label_sumber'] }}</span>
                                        </div>
                                        <div class="mt-2 border-l-2 border-stone-300 pl-3 text-sm">
                                            <p class="text-stone-600">{{ $c['jabatan'] }},</p>
                                            <p class="mt-3 font-semibold text-stone-900 underline underline-offset-2">{{ $c['nama'] ?? '…' }}</p>
                                            @if ($c['nip'])
                                                <p class="font-mono text-xs text-stone-600">NIP. {{ $c['nip'] }}</p>
                                            @endif
                                        </div>
                                        <p class="mt-2 text-xs text-stone-500">{{ $c['keterangan'] }}</p>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>
                @endif
                <section class="rounded-2xl bg-white p-5 shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-kerja-akun" data-testid="kerja-akun">
                    <h2 id="judul-kerja-akun" class="text-sm font-semibold text-stone-900">Kegiatan & dokumen Anda</h2>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <a href="{{ route('kegiatan.index') }}" wire:navigate class="rounded-xl bg-stone-50 p-3 ring-1 ring-stone-200 hover:ring-brand-300" data-testid="kegiatan-buatan" data-jumlah="{{ $rincian['kegiatan']['tahun_aktif'] }}">
                            <p class="text-xs text-stone-500">Kegiatan yang Anda buat · TA {{ $rincian['tahun'] }}</p>
                            <p class="mt-1 text-2xl font-bold text-stone-900">{{ $rincian['kegiatan']['tahun_aktif'] }}</p>
                            <p class="text-xs text-stone-500">{{ $rincian['kegiatan']['semua'] }} di semua tahun anggaran</p>
                            @if ($rincian['kegiatan']['tahun_aktif'] > 0)
                                <div class="mt-2 flex flex-wrap gap-1.5">
                                    @foreach ($rincian['kegiatan']['per_status'] as $status => $n)
                                        @if ($n > 0)
                                            <span class="inline-flex items-center gap-1" data-status="{{ $status }}"><x-ui.status-badge :status="$status" /> <span class="text-xs text-stone-600">{{ $n }}</span></span>
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                        </a>
                        <a href="{{ route('dokumen.index') }}" wire:navigate class="rounded-xl bg-stone-50 p-3 ring-1 ring-stone-200 hover:ring-brand-300" data-testid="dokumen-buatan" data-jumlah="{{ $rincian['dokumen']['jumlah'] }}">
                            <p class="text-xs text-stone-500">Dokumen yang Anda cetak/unduh</p>
                            <p class="mt-1 text-2xl font-bold text-stone-900">{{ $rincian['dokumen']['jumlah'] }}</p>
                            <p class="text-xs text-stone-500">{{ $rincian['dokumen']['tahun_aktif'] }} di TA {{ $rincian['tahun'] }}</p>
                            @if ($d = $rincian['dokumen']['terakhir'])
                                <p class="mt-2 truncate text-xs text-stone-600" title="{{ $d['nama_berkas'] }}">Terakhir: {{ $d['nama_berkas'] }} · {{ $waktu($d['dibuat_pada']) }}</p>
                            @endif
                        </a>
                    </div>
                </section>
            </div>
            <aside class="min-w-0 space-y-4 lg:col-start-2 lg:row-span-2 lg:row-start-1">
                <section class="rounded-2xl bg-white p-5 shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-peran" data-testid="peran-akun">
                    <h2 id="judul-peran" class="text-sm font-semibold text-stone-900">Peran & leading</h2>
                    <p class="mt-2 inline-flex items-center gap-1.5 rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-semibold text-brand-800 ring-1 ring-brand-200" data-peran="{{ $akun['peran'] }}">{{ $akun['label_peran'] }}</p>
                    @if ($akun['peran'] === 'admin')
                        <p class="mt-2 text-xs text-stone-500">Admin melihat & memeriksa kegiatan semua leading serta mengatur aplikasi.</p>
                    @elseif ($akun['leading'] === [])
                        <p class="mt-2 text-xs text-gold-800">Belum tertaut ke leading — minta admin menautkannya agar bisa membuat kegiatan.</p>
                    @else
                        <ul class="mt-3 space-y-1.5" role="list" data-testid="leading-akun">
                            @foreach ($akun['leading'] as $l)
                                <li @class(['rounded-lg bg-stone-50 px-2.5 py-1.5 text-xs ring-1 ring-stone-200', 'text-stone-400 line-through' => ! $l['aktif']]) data-leading="{{ $l['kode'] }}">
                                    <span class="font-semibold text-stone-800">{{ $l['kode'] }}</span> · {{ $l['nama_unit'] }}@unless ($l['aktif']) (nonaktif)@endunless
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    <p class="mt-3 text-xs text-stone-500">Peran & tautan leading diatur admin di Pengaturan › Leading & User.</p>
                </section>
                <section class="rounded-2xl bg-stone-50 p-5 ring-1 ring-stone-200" data-testid="status-akun">
                    <dl class="space-y-2 text-xs">
                        <div class="flex justify-between gap-2"><dt class="text-stone-500">Status</dt><dd class="font-semibold {{ $akun['aktif'] ? 'text-brand-800' : 'text-red-700' }}">{{ $akun['aktif'] ? 'Aktif' : 'Nonaktif' }}</dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-stone-500">Terakhir masuk</dt><dd class="text-right text-stone-700">{{ $waktu($akun['terakhir_masuk']) }}</dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-stone-500">Akun dibuat</dt><dd class="text-right text-stone-700">{{ $waktu($akun['dibuat_pada']) }}</dd></div>
                        <div class="flex justify-between gap-2" data-testid="sesi-ini"><dt class="text-stone-500">Sesi ini</dt><dd class="text-right text-stone-700">{{ $rincian['sesi']['masuk_pada'] ? 'Masuk '.$waktu($rincian['sesi']['masuk_pada']) : 'Mode demo (tanpa masuk)' }}</dd></div>
                    </dl>
                </section>
                <section class="rounded-2xl bg-white p-5 shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-kuota-akun" data-testid="kuota-akun">
                    <h2 id="judul-kuota-akun" class="mb-3 text-sm font-semibold text-stone-900">Bantuan AI hari ini</h2>
                    <x-ai.status-kuota :status="$rincian['kuota']" />
                </section>
            </aside>
        </div>
    @elseif ($tab === 'sandi')
        <section class="mt-6 max-w-xl rounded-2xl bg-white p-5 shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-sandi" data-testid="sandi-akun">
            <h2 id="judul-sandi" class="text-sm font-semibold text-stone-900">Ubah sandi</h2>
            <p class="mt-0.5 text-xs text-stone-500">Setelah sandi diganti, perangkat lain yang masih masuk dengan akun ini akan keluar.</p>
            <div class="mt-4 space-y-4">
                @foreach (['sandi_lama' => ['Sandi saat ini', 'current-password'], 'sandi_baru' => ['Sandi baru', 'new-password'], 'sandi_ulang' => ['Ulangi sandi baru', 'new-password']] as $kunci => [$label, $auto])
                    <label class="block">
                        <span class="form-label">{{ $label }}</span>
                        <input type="password" autocomplete="{{ $auto }}" class="form-input mt-1" disabled data-testid="isian-{{ $kunci }}">
                    </label>
                @endforeach
            </div>
            <ul class="mt-4 grid gap-1.5 text-xs text-stone-600 sm:grid-cols-2" role="list" data-testid="syarat-sandi">
                @foreach (Profil::SYARAT_SANDI as $syarat)
                    <li class="flex items-center gap-1.5"><x-ui.icon name="check" class="size-3.5 text-stone-400" /> {{ $syarat }}</li>
                @endforeach
            </ul>
            <div class="mt-5 flex items-center justify-end gap-2">
                <button type="button" class="btn-primary" disabled title="Sedang disiapkan">Simpan sandi <x-ui.segera /></button>
            </div>
        </section>
    @else
        <section class="mt-6 rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-aktivitas" data-testid="aktivitas-akun">
            <header class="border-b border-stone-100 px-5 py-4">
                <h2 id="judul-aktivitas" class="text-sm font-semibold text-stone-900">Riwayat masuk</h2>
                <p class="mt-0.5 text-xs text-stone-500">{{ \App\Support\Akun\DataAkun::BATAS_RIWAYAT }} kali masuk terakhir. Tidak mengenali salah satunya? Ganti sandi lalu kabari admin.</p>
            </header>
            @if ($akun['riwayat_masuk'] === [])
                <p class="px-5 py-8 text-center text-sm text-stone-500" data-testid="aktivitas-kosong">Belum ada catatan masuk.</p>
            @else
                <ol class="divide-y divide-stone-100" role="list">
                    @foreach ($akun['riwayat_masuk'] as $masuk)
                        <li class="flex flex-col gap-0.5 px-5 py-3 text-sm sm:flex-row sm:items-center sm:justify-between" data-testid="riwayat-masuk">
                            <span class="text-stone-800">{{ $waktu($masuk['waktu'] ?? null) }}</span>
                            <span class="min-w-0 truncate text-xs text-stone-500 sm:max-w-[60%] sm:text-right" title="{{ $masuk['agen'] ?? '' }}">{{ $masuk['alamat_ip'] ?? 'IP tidak tercatat' }} · {{ \Illuminate\Support\Str::limit($masuk['agen'] ?? 'perangkat tidak dikenal', 60) }}</span>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>
    @endif
</div>
