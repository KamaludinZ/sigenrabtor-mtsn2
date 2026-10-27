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
                <button type="button" wire:click="tambahLeading" class="btn-primary" @disabled($suntingLeading === 'baru')><x-ui.icon name="plus" class="size-4" /> Tambah leading</button>
            @else
                <button type="button" wire:click="tambahAkun" class="btn-primary" @disabled($suntingAkun === 'baru')><x-ui.icon name="plus" class="size-4" /> Tambah akun</button>
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
                @if ($suntingLeading !== null)
                    @php
                        $terpakai = $suntingLeading === 'baru' ? 0 : $this->kodeTerpakai($suntingLeading);
                    @endphp
                    <form wire:submit="simpanLeading" class="mb-6 rounded-2xl bg-white shadow-xs ring-1 ring-brand-200" data-testid="formulir-leading" wire:key="formulir-leading-{{ $suntingLeading }}">
                        <header class="border-b border-stone-100 px-5 py-4 sm:px-6">
                            <h2 class="text-base font-semibold text-stone-900">{{ $suntingLeading === 'baru' ? 'Tambah leading' : 'Ubah leading '.$suntingLeading }}</h2>
                            <p class="mt-0.5 text-sm text-stone-500">Jabatan penanda tangan tercetak di kolom "Pembuat" lembar pengesahan dan menjadi jabatan bawaan pejabat leading.</p>
                        </header>
                        <div class="grid gap-5 px-5 py-5 sm:grid-cols-[7rem_minmax(0,1fr)_6rem] sm:px-6">
                            <x-ui.field label="Kode" for="leading-kode" error="isianLeading.kode" required
                                        :hint="$terpakai > 0 ? 'Terkunci: dipakai '.$terpakai.' kegiatan/akun.' : 'mis. 1.9'">
                                <input type="text" id="leading-kode" wire:model="isianLeading.kode" maxlength="10" inputmode="decimal" autocomplete="off" @readonly($terpakai > 0)
                                       @class(['form-input tabular-nums', 'bg-stone-50 text-stone-500' => $terpakai > 0]) x-init="$el.readOnly || $el.focus()"
                                       @error('isianLeading.kode') aria-invalid="true" aria-describedby="leading-kode-error" @enderror>
                            </x-ui.field>
                            <x-ui.field label="Nama unit" for="leading-nama" error="isianLeading.nama_unit" required>
                                <input type="text" id="leading-nama" wire:model="isianLeading.nama_unit" maxlength="{{ \App\Support\Pengaturan\AturanLeading::MAKS_NAMA }}" autocomplete="off" class="form-input"
                                       placeholder="mis. Waka Kurikulum" x-init="$el.form.querySelector('#leading-kode').readOnly && $el.focus()"
                                       @error('isianLeading.nama_unit') aria-invalid="true" aria-describedby="leading-nama-error" @enderror>
                            </x-ui.field>
                            <x-ui.field class="sm:order-last sm:col-span-3" label="Jabatan penanda tangan" for="leading-jabatan" error="isianLeading.jabatan_penanda_tangan" required>
                                <input type="text" id="leading-jabatan" wire:model="isianLeading.jabatan_penanda_tangan" maxlength="{{ \App\Support\Pengaturan\AturanLeading::MAKS_JABATAN }}" autocomplete="off" class="form-input"
                                       placeholder="mis. Wakil Kepala Bidang Kurikulum"
                                       @error('isianLeading.jabatan_penanda_tangan') aria-invalid="true" aria-describedby="leading-jabatan-error" @enderror>
                            </x-ui.field>
                            <x-ui.field label="Urutan" for="leading-urutan" error="isianLeading.urutan" hint="Urutan tampil">
                                <input type="number" id="leading-urutan" wire:model="isianLeading.urutan" min="0" max="999" class="form-input tabular-nums"
                                       @error('isianLeading.urutan') aria-invalid="true" aria-describedby="leading-urutan-error" @enderror>
                            </x-ui.field>
                        </div>
                        <div class="flex justify-end gap-2 border-t border-stone-100 px-5 py-3 sm:px-6">
                            <button type="button" wire:click="batalLeading" class="btn-ghost">Batal</button>
                            <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="simpanLeading"><x-ui.icon name="save" class="size-4" /> <span wire:loading.remove wire:target="simpanLeading">Simpan</span><span wire:loading wire:target="simpanLeading">Menyimpan…</span></button>
                        </div>
                    </form>
                @endif

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
                                                    <button type="button" wire:click="ubahLeading(@js($l['kode']))" class="rounded-md p-1 text-stone-500 hover:bg-stone-100 hover:text-stone-800" title="Ubah" aria-label="Ubah leading {{ $l['kode'] }}"><x-ui.icon name="pencil" class="size-4" /></button>
                                                    <button type="button" wire:click="ubahStatusLeading(@js($l['kode']))" class="rounded-md p-1 text-stone-500 hover:bg-stone-100 hover:text-stone-800" title="{{ $l['is_active'] ? 'Nonaktifkan' : 'Aktifkan' }}" aria-label="{{ $l['is_active'] ? 'Nonaktifkan' : 'Aktifkan' }} leading {{ $l['kode'] }}"><x-ui.icon :name="$l['is_active'] ? 'lock' : 'check-circle'" class="size-4" /></button>
                                                </span>
                                            </td>
                                        </tr>
                                        @if ($konfirmasiNonaktif === $l['kode'])
                                            @include('livewire.pengaturan.partials.konfirmasi-nonaktif-leading', ['l' => $l, 'kolom' => 5])
                                        @endif
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
                                    <span class="mt-1.5 flex gap-1">
                                        <button type="button" wire:click="ubahLeading(@js($l['kode']))" class="btn-ghost px-2 py-1 text-xs" aria-label="Ubah leading {{ $l['kode'] }}"><x-ui.icon name="pencil" class="size-3.5" /> Ubah</button>
                                        <button type="button" wire:click="ubahStatusLeading(@js($l['kode']))" class="btn-ghost px-2 py-1 text-xs" aria-label="{{ $l['is_active'] ? 'Nonaktifkan' : 'Aktifkan' }} leading {{ $l['kode'] }}"><x-ui.icon :name="$l['is_active'] ? 'lock' : 'check-circle'" class="size-3.5" /> {{ $l['is_active'] ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                    </span>
                                    @if ($konfirmasiNonaktif === $l['kode'])
                                        @include('livewire.pengaturan.partials.konfirmasi-nonaktif-leading', ['l' => $l, 'kolom' => null])
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @else
                @if ($suntingAkun !== null)
                    @include('livewire.pengaturan.partials.formulir-akun')
                @endif

                @php
                    $barisAkun = $this->akunTampil();
                    $kegiatanAkun = $this->kegiatanPerAkun();
                    $arahAkun = $this->arahAkunEfektif();
                    $adaSaringanAkun = $cariAkun !== '' || $peranAkun !== '' || $statusAkun !== '';
                    $chipLeading = function (string $kode) use ($namaLeading) {
                        $aktif = collect($this->leading)->firstWhere('kode', '===', $kode)['is_active'] ?? false;

                        return [$aktif, ($namaLeading[$kode] ?? 'Leading tidak terdaftar').($aktif ? '' : ' — nonaktif')];
                    };
                @endphp
                <section class="rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-daftar-akun" data-testid="daftar-akun">
                    <header class="space-y-3 border-b border-stone-100 px-5 py-4 sm:px-6">
                        <div class="min-w-0">
                            <h2 id="judul-daftar-akun" class="text-base font-semibold text-stone-900">Akun</h2>
                            <p class="mt-0.5 text-sm text-stone-500" data-testid="ringkasan-tabel-akun">
                                {{ $adaSaringanAkun ? 'Menampilkan '.count($barisAkun).' dari '.count($this->akun).' akun.' : $ringkas['akun_aktif'].' aktif ('.$ringkas['per_peran']['admin'].' admin, '.$ringkas['per_peran']['leading'].' leading)'.($ringkas['akun_nonaktif'] ? ' · '.$ringkas['akun_nonaktif'].' nonaktif' : '').'.' }}
                            </p>
                        </div>
                        <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_9rem_9rem]">
                            <label>
                                <span class="sr-only">Cari akun</span>
                                <input type="search" wire:model.live.debounce.300ms="cariAkun" placeholder="Cari nama, surel, atau jabatan…" class="form-input py-1.5" autocomplete="off">
                            </label>
                            <label>
                                <span class="sr-only">Saring peran</span>
                                <select wire:model.live="peranAkun" class="form-input py-1.5" aria-label="Peran akun">
                                    <option value="">Semua peran</option>
                                    @foreach (LeadingUser::PERAN as $kunci => $p)
                                        <option value="{{ $kunci }}">{{ $p['label'] }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label>
                                <span class="sr-only">Saring status</span>
                                <select wire:model.live="statusAkun" class="form-input py-1.5" aria-label="Status akun">
                                    <option value="">Semua status</option>
                                    <option value="aktif">Aktif</option>
                                    <option value="nonaktif">Nonaktif</option>
                                </select>
                            </label>
                        </div>
                    </header>

                    @if ($barisAkun === [])
                        <div class="px-5 py-10 text-center sm:px-6" data-testid="akun-kosong">
                            <p class="text-sm font-medium text-stone-700">Tidak ada akun yang cocok.</p>
                            <button type="button" wire:click="hapusSaringanAkun" class="mt-2 text-sm font-semibold text-brand-700 hover:text-brand-800">Hapus saringan</button>
                        </div>
                    @else
                        {{-- Layar lebar: tabel yang bisa diurutkan. --}}
                        <div class="hidden overflow-x-auto md:block" wire:loading.class="opacity-60" wire:target="cariAkun,peranAkun,statusAkun,urutkanAkun">
                            <table class="w-full text-sm" data-testid="tabel-akun">
                                <thead class="bg-stone-50 text-xs font-semibold text-stone-500">
                                    <tr>
                                        <x-monitoring.judul-urut kolom="nama" label="Nama & surel" :urut="$urutAkun" :arah="$arahAkun" aksi="urutkanAkun" class="pl-5 sm:pl-6" />
                                        <th scope="col" class="px-3 py-2 text-left">Peran &amp; leading</th>
                                        <x-monitoring.judul-urut kolom="kegiatan" label="Kegiatan" :urut="$urutAkun" :arah="$arahAkun" aksi="urutkanAkun" rata="right" />
                                        <x-monitoring.judul-urut kolom="masuk" label="Terakhir masuk" :urut="$urutAkun" :arah="$arahAkun" aksi="urutkanAkun" />
                                        <th scope="col" class="px-5 py-2 text-right sm:px-6">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-stone-100">
                                    @foreach ($barisAkun as $a)
                                        @php
                                            [$labelStatus, $gayaStatus, $ikonStatus] = $status($a['is_active']);
                                        @endphp
                                        <tr @class(['align-top', 'text-stone-500' => ! $a['is_active']]) data-akun="{{ $a['id'] }}" wire:key="akun-{{ $a['id'] }}">
                                            <td class="py-3 pr-3 pl-5 sm:pl-6">
                                                <p class="flex flex-wrap items-center gap-x-2">
                                                    <span @class(['font-medium', 'text-stone-900' => $a['is_active'], 'text-stone-500' => ! $a['is_active']])>{{ $a['name'] }}</span>
                                                    @if ($a['id'] === $saya)
                                                        <span class="text-[11px] font-semibold text-brand-700">Anda</span>
                                                    @endif
                                                </p>
                                                <p class="text-xs text-stone-500">{{ $a['email'] }}</p>
                                                @if ($a['jabatan'])
                                                    <p class="text-xs text-stone-500">{{ $a['jabatan'] }}</p>
                                                @endif
                                            </td>
                                            <td class="px-3 py-3 text-xs">
                                                <span @class(['rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1', 'bg-sky-50 text-sky-800 ring-sky-200' => $a['role'] === 'admin', 'bg-stone-100 text-stone-700 ring-stone-200' => $a['role'] !== 'admin'])>{{ LeadingUser::PERAN[$a['role']]['label'] }}</span>
                                                <p class="mt-1.5 flex flex-wrap gap-1">
                                                    @if ($a['role'] === 'admin')
                                                        <span class="text-stone-500">Semua leading</span>
                                                    @else
                                                        @forelse ($a['leadings'] as $kode)
                                                            @php
                                                                [$leadingAktif, $judulLeading] = $chipLeading($kode);
                                                            @endphp
                                                            <span @class(['rounded-md px-1.5 py-0.5 font-medium whitespace-nowrap ring-1', 'bg-stone-50 text-stone-700 ring-stone-200' => $leadingAktif, 'bg-stone-50 text-stone-400 ring-stone-200 line-through' => ! $leadingAktif]) title="{{ $judulLeading }}">{{ $kode }} {{ $namaLeading[$kode] ?? '' }}</span>
                                                        @empty
                                                            <span class="font-medium text-gold-700">Belum ditautkan</span>
                                                        @endforelse
                                                    @endif
                                                </p>
                                            </td>
                                            <td class="px-3 py-3 text-right whitespace-nowrap text-stone-600 tabular-nums">{{ $kegiatanAkun[$a['id']] ?? 0 }}</td>
                                            <td class="px-3 py-3 text-xs whitespace-nowrap text-stone-600">{{ $a['terakhir_masuk'] ? Format::tanggal($a['terakhir_masuk']) : 'Belum pernah' }}</td>
                                            <td class="py-3 pr-5 pl-3 text-right sm:pr-6">
                                                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold whitespace-nowrap ring-1 {{ $gayaStatus }}"><x-ui.icon :name="$ikonStatus" class="size-3.5" /> {{ $labelStatus }}</span>
                                                <span class="mt-1.5 flex justify-end gap-0.5">
                                                    <button type="button" wire:click="ubahAkun({{ $a['id'] }})" class="rounded-md p-1 text-stone-500 hover:bg-stone-100 hover:text-stone-800" title="Ubah" aria-label="Ubah akun {{ $a['name'] }}"><x-ui.icon name="pencil" class="size-4" /></button>
                                                    <button type="button" class="rounded-md p-1 text-stone-400" disabled title="{{ $a['is_active'] ? 'Nonaktifkan' : 'Aktifkan' }} — sedang disiapkan" aria-label="{{ $a['is_active'] ? 'Nonaktifkan' : 'Aktifkan' }} akun {{ $a['name'] }} (sedang disiapkan)"><x-ui.icon :name="$a['is_active'] ? 'lock' : 'check-circle'" class="size-4" /></button>
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{-- Layar sempit: kartu. --}}
                        <ul class="divide-y divide-stone-100 md:hidden">
                            @foreach ($barisAkun as $a)
                                @php
                                    [$labelStatus, $gayaStatus, $ikonStatus] = $status($a['is_active']);
                                @endphp
                                <li class="px-5 py-3" data-akun="{{ $a['id'] }}" wire:key="kartu-akun-{{ $a['id'] }}">
                                    <div class="flex items-start justify-between gap-3">
                                        <p class="flex min-w-0 flex-wrap items-center gap-x-2">
                                            <span @class(['font-medium', 'text-stone-900' => $a['is_active'], 'text-stone-500' => ! $a['is_active']])>{{ $a['name'] }}</span>
                                            <span @class(['rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1', 'bg-sky-50 text-sky-800 ring-sky-200' => $a['role'] === 'admin', 'bg-stone-100 text-stone-700 ring-stone-200' => $a['role'] !== 'admin'])>{{ LeadingUser::PERAN[$a['role']]['label'] }}</span>
                                            @if ($a['id'] === $saya)
                                                <span class="text-[11px] font-semibold text-brand-700">Anda</span>
                                            @endif
                                        </p>
                                        <span class="inline-flex shrink-0 items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold ring-1 {{ $gayaStatus }}"><x-ui.icon :name="$ikonStatus" class="size-3.5" /> {{ $labelStatus }}</span>
                                    </div>
                                    <p class="truncate text-xs text-stone-500">{{ $a['email'] }}{{ $a['jabatan'] ? ' · '.$a['jabatan'] : '' }}</p>
                                    <p class="mt-1 text-xs text-stone-600">
                                        {{ $a['role'] === 'admin' ? 'Semua leading' : ($a['leadings'] === [] ? 'Belum ditautkan ke leading' : implode(', ', array_map(fn ($k) => $k.' '.($namaLeading[$k] ?? '').($chipLeading($k)[0] ? '' : ' (nonaktif)'), $a['leadings']))) }}
                                    </p>
                                    <p class="text-[11px] text-stone-500">{{ $kegiatanAkun[$a['id']] ?? 0 }} kegiatan · {{ $a['terakhir_masuk'] ? 'masuk '.Format::tanggal($a['terakhir_masuk']) : 'belum pernah masuk' }}</p>
                                    <button type="button" wire:click="ubahAkun({{ $a['id'] }})" class="btn-ghost mt-1.5 px-2 py-1 text-xs" aria-label="Ubah akun {{ $a['name'] }}"><x-ui.icon name="pencil" class="size-3.5" /> Ubah</button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
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
