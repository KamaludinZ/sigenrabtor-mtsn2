@use('App\Livewire\Akun\Profil')
@use('App\Support\Format')

@php
    $akun = $this->akun;
    $rincian = $akun !== null ? $this->rincian : null;
    $statusSandi = $akun !== null && $tab === 'sandi' ? $this->statusSandi : null;
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
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 id="judul-profil" class="text-sm font-semibold text-stone-900">Profil</h2>
                        <p class="mt-0.5 text-xs text-stone-500">Nama & jabatan tercetak sebagai penyusun bila pejabat leading belum diisi.</p>
                    </div>
                    @unless ($ubah)
                        <button type="button" wire:click="bukaUbah" class="btn-secondary shrink-0" data-testid="ubah-profil"><x-ui.icon name="pencil" class="size-4" /> Ubah profil</button>
                    @endunless
                </div>
                @if ($ubah)
                    <form wire:submit="simpanProfil" class="mt-4 space-y-4" data-testid="form-profil" novalidate wire:key="form-profil"
                          x-data="{ nama: $wire.isian.nama, jabatan: $wire.isian.jabatan }" x-on:keydown.escape.prevent="$wire.batalUbah()">
                        @if ($bentrok)
                            <div class="rounded-xl bg-gold-50 p-3 text-sm text-gold-900 ring-1 ring-gold-200" role="alert" data-testid="profil-bentrok">
                                <p class="font-medium">Profil Anda baru saja diubah di tempat lain.</p>
                                <p class="mt-1 text-xs">Tersimpan sekarang: <span class="font-semibold">{{ $bentrok['nama'] }}</span>{{ $bentrok['jabatan'] !== '' ? ' · '.$bentrok['jabatan'] : '' }}. Simpan lagi untuk menimpanya dengan isian Anda, atau pakai data terbaru.</p>
                                <button type="button" wire:click="pakaiTerbaru" x-on:click="$nextTick(() => { nama = $wire.isian.nama; jabatan = $wire.isian.jabatan })" class="mt-2 text-xs font-semibold text-gold-900 underline" data-testid="pakai-terbaru">Pakai data terbaru</button>
                            </div>
                        @endif
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-ui.field label="Nama lengkap & gelar" for="profil-nama" error="isian.nama" required>
                                <input id="profil-nama" type="text" wire:model.live.blur="isian.nama" x-on:input="nama = $event.target.value" maxlength="150" autocomplete="name" autofocus
                                       class="form-input" data-testid="isian-profil-nama" @error('isian.nama') aria-invalid="true" aria-describedby="profil-nama-error" @enderror>
                            </x-ui.field>
                            <x-ui.field label="Jabatan" for="profil-jabatan" error="isian.jabatan" hint="Pilih saran atau ketik sendiri.">
                                <input id="profil-jabatan" type="text" list="saran-jabatan-profil" wire:model.live.blur="isian.jabatan" x-on:input="jabatan = $event.target.value" maxlength="150" autocomplete="organization-title"
                                       class="form-input" data-testid="isian-profil-jabatan" @error('isian.jabatan') aria-invalid="true" aria-describedby="profil-jabatan-error" @enderror>
                                <datalist id="saran-jabatan-profil">
                                    @foreach ($this->saranJabatan() as $saran)
                                        <option value="{{ $saran }}"></option>
                                    @endforeach
                                </datalist>
                            </x-ui.field>
                        </div>
                        <div class="rounded-xl bg-stone-50 p-3 ring-1 ring-stone-200" data-testid="pratinjau-tanda-tangan">
                            <p class="text-[11px] font-semibold tracking-wide text-stone-500 uppercase">Tercetak sebagai Pembuat (bila pejabat leading kosong)</p>
                            <div class="mt-2 border-l-2 border-stone-300 pl-3 text-sm">
                                <p class="text-stone-600" x-text="(jabatan.trim() || 'Jabatan') + ','"></p>
                                <p class="mt-3 font-semibold text-stone-900 underline underline-offset-2" x-text="nama.trim().replace(/\s+/g, ' ') || '…'"></p>
                            </div>
                        </div>
                        <p class="text-xs text-stone-500">Surel, NIP, peran, dan tautan leading diatur admin di Pengaturan › Leading & User.</p>
                        <div class="flex flex-wrap justify-end gap-2">
                            <button type="button" wire:click="batalUbah" class="btn-ghost" data-testid="batal-profil">Batal</button>
                            <button type="submit" wire:loading.attr="disabled" wire:target="simpanProfil" class="btn-primary" data-testid="simpan-profil">
                                <span wire:loading.remove wire:target="simpanProfil">Simpan profil</span>
                                <span wire:loading wire:target="simpanProfil">Menyimpan…</span>
                            </button>
                        </div>
                    </form>
                @else
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
                @endif
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
                        <a href="{{ route('kegiatan.index') }}" wire:navigate class="block min-w-0 rounded-xl bg-stone-50 p-3 ring-1 ring-stone-200 hover:ring-brand-300" data-testid="kegiatan-buatan" data-jumlah="{{ $rincian['kegiatan']['tahun_aktif'] }}">
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
                        <a href="{{ route('dokumen.index') }}" wire:navigate class="block min-w-0 rounded-xl bg-stone-50 p-3 ring-1 ring-stone-200 hover:ring-brand-300" data-testid="dokumen-buatan" data-jumlah="{{ $rincian['dokumen']['jumlah'] }}">
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
            <h2 id="judul-sandi" class="text-sm font-semibold text-stone-900">Ganti sandi</h2>
            <p class="mt-0.5 text-xs text-stone-500" data-testid="status-sandi" data-ada="{{ $statusSandi['ada'] ? 'ya' : 'tidak' }}">
                {{ $statusSandi['diganti_pada'] ? 'Terakhir diganti '.$waktu($statusSandi['diganti_pada']).'.' : 'Sandi belum pernah diganti.' }}
                Sandi disimpan terenkripsi; admin pun tidak bisa melihatnya.
            </p>
            @unless ($statusSandi['ada'])
                <p class="mt-3 rounded-xl bg-gold-50 p-3 text-xs text-gold-900 ring-1 ring-gold-200" data-testid="sandi-belum-ada">
                    Akun ini belum punya sandi (akun contoh mode demo) — kolom <span class="font-semibold">Sandi saat ini</span> boleh dikosongkan. Selama mode demo, halaman Masuk belum memeriksa sandi.
                </p>
            @endunless
            <form wire:submit="gantiSandi" x-data="formSandi" data-nama="{{ $akun['nama'] }}" data-surel="{{ $akun['surel'] }}" x-on:sandi-diganti.window="kosongkan()"
                  class="mt-4 space-y-4" data-testid="form-sandi" novalidate>
                <x-ui.field label="Sandi saat ini" for="sandi-lama" error="isianSandi.sandi_lama" :required="$statusSandi['ada']">
                    <input id="sandi-lama" x-bind:type="lihat ? 'text' : 'password'" type="password" wire:model="isianSandi.sandi_lama" x-on:input="lama = $event.target.value" autocomplete="current-password" maxlength="255"
                           class="form-input" data-testid="isian-sandi_lama" @error('isianSandi.sandi_lama') aria-invalid="true" aria-describedby="sandi-lama-error" @enderror>
                </x-ui.field>
                <x-ui.field label="Sandi baru" for="sandi-baru" error="isianSandi.sandi_baru" required>
                    <div class="relative">
                        <input id="sandi-baru" x-bind:type="lihat ? 'text' : 'password'" type="password" wire:model="isianSandi.sandi_baru" x-on:input="baru = $event.target.value" autocomplete="new-password" maxlength="64"
                               class="form-input pr-10" data-testid="isian-sandi_baru" aria-describedby="syarat-sandi @error('isianSandi.sandi_baru') sandi-baru-error @enderror" @error('isianSandi.sandi_baru') aria-invalid="true" @enderror>
                        <button type="button" x-on:click="lihat = ! lihat" class="absolute top-1/2 right-2 -translate-y-1/2 rounded-md p-1 text-stone-400 hover:text-stone-700" x-bind:aria-label="lihat ? 'Sembunyikan sandi' : 'Tampilkan sandi'" x-bind:aria-pressed="lihat" data-testid="lihat-sandi-akun">
                            <x-ui.icon name="eye" class="size-4" />
                        </button>
                    </div>
                    <ul id="syarat-sandi" class="mt-2 grid gap-1 text-xs sm:grid-cols-2" role="list" aria-live="polite" data-testid="syarat-sandi">
                        @foreach (\App\Support\Akun\GantiSandi::SYARAT as $kunci => $label)
                            <li class="flex items-center gap-1.5" data-syarat="{{ $kunci }}" x-bind:data-ok="syarat.{{ $kunci }} && baru !== '' ? 'ya' : 'tidak'"
                                x-bind:class="{ 'text-brand-700': syarat.{{ $kunci }} && baru !== '', 'text-stone-500': ! (syarat.{{ $kunci }} && baru !== '') }">
                                <span x-show="syarat.{{ $kunci }} && baru !== ''"><x-ui.icon name="check" class="size-3.5" /></span>
                                <span x-show="! (syarat.{{ $kunci }} && baru !== '')"><x-ui.icon name="circle" class="size-3.5" /></span>
                                <span>{{ $label }}</span>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.field>
                <x-ui.field label="Ulangi sandi baru" for="sandi-ulang" error="isianSandi.sandi_ulang" required>
                    <input id="sandi-ulang" x-bind:type="lihat ? 'text' : 'password'" type="password" wire:model="isianSandi.sandi_ulang" x-on:input="ulang = $event.target.value" autocomplete="new-password" maxlength="64"
                           class="form-input" data-testid="isian-sandi_ulang" @error('isianSandi.sandi_ulang') aria-invalid="true" aria-describedby="sandi-ulang-error" @enderror>
                    <p class="mt-1.5 text-xs" x-show="ulang !== ''" x-cloak aria-live="polite" data-testid="sandi-baru-cocok" x-bind:data-cocok="cocok ? 'ya' : 'tidak'"
                       x-bind:class="{ 'text-brand-700': cocok, 'text-gold-700': ! cocok }" x-text="cocok ? 'Sandi sama.' : 'Belum sama dengan sandi baru.'"></p>
                </x-ui.field>
                <div class="flex items-center justify-end gap-2">
                    <button type="submit" wire:loading.attr="disabled" wire:target="gantiSandi" class="btn-primary" data-testid="simpan-sandi">
                        <span wire:loading.remove wire:target="gantiSandi">Ganti sandi</span>
                        <span wire:loading wire:target="gantiSandi">Menyimpan…</span>
                    </button>
                </div>
            </form>
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
