@use('App\Livewire\Pengaturan\LeadingUser')
@use('App\Support\Format')

@php
    $ringkas = $this->ringkasan();
    $akunPerLeading = $this->akunPerLeading();
    $kegiatan = $this->kegiatanPerLeading();
    $namaLeading = array_column($this->leading, 'nama_unit', 'kode');
    $saya = \App\Support\Stub\StubData::currentUser()['id'];
    $jumlahTab = ['leading' => $ringkas['leading_aktif'], 'akun' => $ringkas['akun_aktif']];
    $status = fn (bool $aktif) => $aktif
        ? ['Aktif', 'bg-brand-50 text-brand-800 ring-brand-200', 'check-circle']
        : ['Nonaktif', 'bg-stone-100 text-stone-600 ring-stone-200', 'lock'];
@endphp

<div class="mx-auto max-w-7xl">
    <x-pengaturan.kepala judul="Leading & User"
                         deskripsi="Unit penerima mandat beserta jabatan penanda tangannya, dan akun yang boleh masuk. Akun leading hanya melihat kegiatan leading yang ditautkan kepadanya.">
        <x-slot:aksi>
            @if ($tab === 'leading')
                <button type="button" class="btn-primary" disabled title="Sedang disiapkan"><x-ui.icon name="plus" class="size-4" /> Tambah leading <x-ui.segera /></button>
            @else
                <button type="button" class="btn-primary" disabled title="Sedang disiapkan"><x-ui.icon name="plus" class="size-4" /> Tambah akun <x-ui.segera /></button>
            @endif
        </x-slot:aksi>
        <x-slot:tab>
            <nav class="-mb-px flex gap-1 overflow-x-auto border-b border-stone-200" aria-label="Bagian leading & user">
                @foreach (LeadingUser::TAB as $kunci => $label)
                    @if ($kunci === $tab)
                        <span aria-current="page" class="flex shrink-0 items-center gap-2 border-b-2 border-brand-700 px-3 py-2.5 text-sm font-semibold text-brand-800" data-tab="{{ $kunci }}">
                            {{ $label }} <span class="rounded-full bg-brand-50 px-1.5 text-[11px] font-semibold tabular-nums">{{ $jumlahTab[$kunci] }}</span>
                        </span>
                    @else
                        <button type="button" wire:click="$set('tab', '{{ $kunci }}')" data-tab="{{ $kunci }}"
                                class="flex shrink-0 items-center gap-2 border-b-2 border-transparent px-3 py-2.5 text-sm font-medium text-stone-500 hover:border-stone-300 hover:text-stone-800">
                            {{ $label }} <span class="rounded-full bg-stone-100 px-1.5 text-[11px] font-semibold text-stone-600 tabular-nums">{{ $jumlahTab[$kunci] }}</span>
                        </button>
                    @endif
                @endforeach
            </nav>
        </x-slot:tab>
    </x-pengaturan.kepala>

    <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="min-w-0" wire:key="tab-{{ $tab }}">
            @if ($tab === 'leading')
                @php
                    $baris = $this->leadingTampil();
                    $pejabat = $this->pejabatLeading();
                    $arahLeading = $this->arahLeadingEfektif();
                    $adaSaringan = $cariLeading !== '' || $statusLeading !== '';
                @endphp
                <section class="rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-daftar-leading" data-testid="daftar-leading">
                    <header class="space-y-3 border-b border-stone-100 px-5 py-4 sm:px-6">
                        <div class="min-w-0">
                            <h2 id="judul-daftar-leading" class="text-base font-semibold text-stone-900">Leading</h2>
                            <p class="mt-0.5 text-sm text-stone-500" data-testid="ringkasan-tabel-leading">
                                {{ $adaSaringan ? 'Menampilkan '.count($baris).' dari '.count($this->leading).' leading.' : $ringkas['leading_aktif'].' aktif'.($ringkas['leading_nonaktif'] ? ' · '.$ringkas['leading_nonaktif'].' nonaktif' : '').' · jabatan penanda tangan tercetak di kolom "Pembuat" lembar pengesahan.' }}
                            </p>
                        </div>
                        <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_11rem]">
                            <label>
                                <span class="sr-only">Cari leading</span>
                                <input type="search" wire:model.live.debounce.300ms="cariLeading" placeholder="Cari kode, unit, atau jabatan…" class="form-input py-1.5" autocomplete="off">
                            </label>
                            <label>
                                <span class="sr-only">Saring status</span>
                                <select wire:model.live="statusLeading" class="form-input py-1.5" aria-label="Status leading">
                                    <option value="">Semua status</option>
                                    <option value="aktif">Aktif</option>
                                    <option value="nonaktif">Nonaktif</option>
                                </select>
                            </label>
                        </div>
                    </header>

                    @if ($baris === [])
                        <div class="px-5 py-10 text-center sm:px-6" data-testid="leading-kosong">
                            <p class="text-sm font-medium text-stone-700">Tidak ada leading yang cocok.</p>
                            <button type="button" wire:click="hapusSaringanLeading" class="mt-2 text-sm font-semibold text-brand-700 hover:text-brand-800">Hapus saringan</button>
                        </div>
                    @else
                        {{-- Layar lebar: tabel yang bisa diurutkan. --}}
                        <div class="hidden overflow-x-auto md:block" wire:loading.class="opacity-60" wire:target="cariLeading,statusLeading,urutkanLeading">
                            <table class="w-full text-sm" data-testid="tabel-leading">
                                <thead class="bg-stone-50 text-xs font-semibold text-stone-500">
                                    <tr>
                                        <x-monitoring.judul-urut kolom="kode" label="Kode" :urut="$urutLeading" :arah="$arahLeading" aksi="urutkanLeading" class="pl-5 sm:pl-6" />
                                        <x-monitoring.judul-urut kolom="nama" label="Nama unit & jabatan penanda tangan" :urut="$urutLeading" :arah="$arahLeading" aksi="urutkanLeading" />
                                        <th scope="col" class="px-3 py-2 text-left">Pejabat TA {{ \App\Support\Stub\StubData::tahunAktif()['tahun'] }} &amp; akun</th>
                                        <x-monitoring.judul-urut kolom="kegiatan" label="Kegiatan" :urut="$urutLeading" :arah="$arahLeading" aksi="urutkanLeading" rata="right" />
                                        <th scope="col" class="px-5 py-2 text-right sm:px-6">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-stone-100">
                                    @foreach ($baris as $l)
                                        @php
                                            [$labelStatus, $gayaStatus, $ikonStatus] = $status($l['is_active']);
                                            $tertaut = $akunPerLeading[$l['kode']] ?? [];
                                            $p = $pejabat[$l['kode']] ?? null;
                                        @endphp
                                        <tr @class(['align-top', 'text-stone-500' => ! $l['is_active']]) data-leading="{{ $l['kode'] }}" wire:key="leading-{{ $l['kode'] }}">
                                            <td class="py-3 pr-3 pl-5 font-semibold whitespace-nowrap text-stone-900 tabular-nums sm:pl-6">{{ $l['kode'] }}</td>
                                            <td class="px-3 py-3">
                                                <p class="font-medium text-stone-900">{{ $l['nama_unit'] }}</p>
                                                <p class="text-xs text-stone-500">{{ $l['jabatan_penanda_tangan'] }}</p>
                                            </td>
                                            <td class="px-3 py-3 text-xs">
                                                <p data-kolom="pejabat">
                                                    @if ($p && $p['nama'])
                                                        <span class="text-stone-800">{{ $p['nama'] }}</span>
                                                    @elseif ($l['is_active'])
                                                        <a href="{{ route('pengaturan.madrasah.index', ['tab' => 'pejabat']) }}" wire:navigate class="text-stone-500 underline decoration-dotted hover:text-brand-700" title="Selama kosong, dokumen memakai nama akun pembuat kegiatan">Pejabat belum diisi</a>
                                                    @else
                                                        <span class="text-stone-400">—</span>
                                                    @endif
                                                </p>
                                                <p class="mt-1 flex flex-wrap gap-1" data-kolom="akun">
                                                    @forelse ($tertaut as $a)
                                                        <span @class(['inline-flex items-center rounded-md px-1.5 py-0.5 whitespace-nowrap ring-1', 'bg-white text-stone-700 ring-stone-200' => $a['is_active'], 'bg-stone-50 text-stone-400 ring-stone-200 line-through' => ! $a['is_active']])
                                                              @if (! $a['is_active']) title="Akun nonaktif" @endif>{{ $a['name'] }}</span>
                                                    @empty
                                                        <span @class(['font-medium text-gold-700' => $l['is_active'], 'text-stone-400' => ! $l['is_active']])>Belum ada akun</span>
                                                    @endforelse
                                                </p>
                                            </td>
                                            <td class="px-3 py-3 text-right whitespace-nowrap text-stone-600 tabular-nums">{{ $kegiatan[$l['kode']] ?? 0 }}</td>
                                            <td class="py-3 pr-5 pl-3 text-right sm:pr-6">
                                                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold whitespace-nowrap ring-1 {{ $gayaStatus }}"><x-ui.icon :name="$ikonStatus" class="size-3.5" /> {{ $labelStatus }}</span>
                                                <span class="mt-1.5 flex justify-end gap-0.5">
                                                    <button type="button" class="rounded-md p-1 text-stone-400" disabled title="Ubah — sedang disiapkan" aria-label="Ubah leading {{ $l['kode'] }} (sedang disiapkan)"><x-ui.icon name="pencil" class="size-4" /></button>
                                                    <button type="button" class="rounded-md p-1 text-stone-400" disabled title="{{ $l['is_active'] ? 'Nonaktifkan' : 'Aktifkan' }} — sedang disiapkan" aria-label="{{ $l['is_active'] ? 'Nonaktifkan' : 'Aktifkan' }} leading {{ $l['kode'] }} (sedang disiapkan)"><x-ui.icon :name="$l['is_active'] ? 'lock' : 'check-circle'" class="size-4" /></button>
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{-- Layar sempit: kartu. --}}
                        <ul class="divide-y divide-stone-100 md:hidden">
                            @foreach ($baris as $l)
                                @php
                                    [$labelStatus, $gayaStatus, $ikonStatus] = $status($l['is_active']);
                                    $tertaut = $akunPerLeading[$l['kode']] ?? [];
                                    $p = $pejabat[$l['kode']] ?? null;
                                @endphp
                                <li class="px-5 py-3" data-leading="{{ $l['kode'] }}" wire:key="kartu-leading-{{ $l['kode'] }}">
                                    <div class="flex items-start justify-between gap-3">
                                        <p class="min-w-0"><span class="font-semibold text-stone-900 tabular-nums">{{ $l['kode'] }}</span> <span class="font-medium text-stone-900">{{ $l['nama_unit'] }}</span></p>
                                        <span class="inline-flex shrink-0 items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold ring-1 {{ $gayaStatus }}"><x-ui.icon :name="$ikonStatus" class="size-3.5" /> {{ $labelStatus }}</span>
                                    </div>
                                    <p class="text-xs text-stone-500">{{ $l['jabatan_penanda_tangan'] }} · {{ $kegiatan[$l['kode']] ?? 0 }} kegiatan</p>
                                    <p class="mt-1 text-xs text-stone-600">Pejabat: {{ $p && $p['nama'] ? $p['nama'] : 'belum diisi' }}</p>
                                    <p class="text-xs text-stone-600">Akun: {{ $tertaut === [] ? 'belum ada' : implode(', ', array_map(fn ($a) => $a['name'].($a['is_active'] ? '' : ' (nonaktif)'), $tertaut)) }}</p>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @else
                <section class="rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-daftar-akun" data-testid="daftar-akun">
                    <header class="border-b border-stone-100 px-5 py-4 sm:px-6">
                        <h2 id="judul-daftar-akun" class="text-base font-semibold text-stone-900">Akun</h2>
                        <p class="mt-0.5 text-sm text-stone-500">{{ $ringkas['akun_aktif'] }} aktif ({{ $ringkas['per_peran']['admin'] }} admin, {{ $ringkas['per_peran']['leading'] }} leading){{ $ringkas['akun_nonaktif'] ? ' · '.$ringkas['akun_nonaktif'].' nonaktif' : '' }}.</p>
                    </header>
                    <ul class="divide-y divide-stone-100">
                        @foreach ($this->akun as $a)
                            @php
                                [$labelStatus, $gayaStatus, $ikonStatus] = $status($a['is_active']);
                            @endphp
                            <li class="grid gap-x-4 gap-y-2 px-5 py-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,14rem)_8rem] sm:px-6" data-akun="{{ $a['id'] }}" wire:key="akun-{{ $a['id'] }}">
                                <div class="min-w-0">
                                    <p class="flex flex-wrap items-center gap-2">
                                        <span @class(['font-medium', 'text-stone-900' => $a['is_active'], 'text-stone-500' => ! $a['is_active']])>{{ $a['name'] }}</span>
                                        <span @class(['rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1', 'bg-sky-50 text-sky-800 ring-sky-200' => $a['role'] === 'admin', 'bg-stone-100 text-stone-700 ring-stone-200' => $a['role'] !== 'admin'])>{{ LeadingUser::PERAN[$a['role']]['label'] }}</span>
                                        @if ($a['id'] === $saya)
                                            <span class="text-[11px] font-semibold text-brand-700">Anda</span>
                                        @endif
                                    </p>
                                    <p class="truncate text-xs text-stone-500">{{ $a['email'] }}{{ $a['jabatan'] ? ' · '.$a['jabatan'] : '' }}</p>
                                </div>
                                <div class="min-w-0 text-xs">
                                    @if ($a['role'] === 'admin')
                                        <span class="text-stone-500">Semua leading</span>
                                    @else
                                        <span class="flex flex-wrap gap-1">
                                            @foreach ($a['leadings'] as $kode)
                                                <span class="rounded-md bg-stone-50 px-1.5 py-0.5 font-medium text-stone-700 ring-1 ring-stone-200" title="{{ $namaLeading[$kode] ?? 'Leading tidak terdaftar' }}">{{ $kode }} {{ $namaLeading[$kode] ?? '' }}</span>
                                            @endforeach
                                        </span>
                                    @endif
                                </div>
                                <div class="flex items-start justify-between gap-2 sm:flex-col sm:items-end">
                                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold ring-1 {{ $gayaStatus }}"><x-ui.icon :name="$ikonStatus" class="size-3.5" /> {{ $labelStatus }}</span>
                                    <span class="text-[11px] text-stone-500">{{ $a['terakhir_masuk'] ? 'Masuk '.Format::tanggal($a['terakhir_masuk']) : 'Belum pernah masuk' }}</span>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>

        <aside class="min-w-0 space-y-6">
            <section class="rounded-2xl bg-white p-5 shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-ringkasan-leading" data-testid="ringkasan-leading-user">
                <h2 id="judul-ringkasan-leading" class="text-sm font-semibold text-stone-900">Ringkasan</h2>
                <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
                    <div class="rounded-lg bg-stone-50 px-3 py-2 ring-1 ring-stone-200"><dt class="text-xs text-stone-500">Leading aktif</dt><dd class="text-lg font-bold text-stone-900 tabular-nums">{{ $ringkas['leading_aktif'] }}</dd></div>
                    <div class="rounded-lg bg-stone-50 px-3 py-2 ring-1 ring-stone-200"><dt class="text-xs text-stone-500">Akun aktif</dt><dd class="text-lg font-bold text-stone-900 tabular-nums">{{ $ringkas['akun_aktif'] }}</dd></div>
                </dl>
                @if ($ringkas['tanpa_akun'] !== [])
                    <p class="mt-3 flex items-start gap-2 text-xs text-gold-800" data-testid="leading-tanpa-akun">
                        <x-ui.icon name="alert" class="mt-px size-3.5 shrink-0 text-gold-600" />
                        <span>Leading tanpa akun aktif: {{ implode(', ', array_map(fn ($k) => $k.' '.($namaLeading[$k] ?? ''), $ringkas['tanpa_akun'])) }} — kegiatannya hanya bisa diisi admin.</span>
                    </p>
                @endif
            </section>

            <section class="rounded-2xl bg-white p-5 shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-peran" data-testid="keterangan-peran">
                <h2 id="judul-peran" class="text-sm font-semibold text-stone-900">Dua peran</h2>
                <dl class="mt-3 space-y-3 text-sm">
                    @foreach (LeadingUser::PERAN as $kunci => $p)
                        <div>
                            <dt class="font-medium text-stone-900">{{ $p['label'] }}</dt>
                            <dd class="mt-0.5 text-xs text-stone-600">{{ $p['hak'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        </aside>
    </div>
</div>
