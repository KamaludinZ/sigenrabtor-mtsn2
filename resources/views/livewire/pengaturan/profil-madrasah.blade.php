@use('App\Livewire\Pengaturan\ProfilMadrasah')
@use('App\Support\Pengaturan\Nip')

@php
    $profil = $this->profil;
    $pejabat = $this->pejabat;
    $kurang = $this->kurang();
    $tahun = \App\Support\Stub\StubData::tahunAktif()['tahun'];
@endphp

<div class="mx-auto max-w-7xl">
    <x-pengaturan.kepala judul="Profil & Pejabat"
                         deskripsi="Identitas madrasah untuk kop dokumen dan pejabat penanda tangan yang berlaku pada tahun anggaran aktif. Diisi admin sekali, lalu tercetak otomatis di cover, KAK/TOR, RAB, dan lembar pengesahan semua kegiatan.">
        <x-slot:tab>
            <nav class="-mb-px flex gap-1 overflow-x-auto border-b border-stone-200" aria-label="Bagian profil & pejabat">
                @foreach (ProfilMadrasah::TAB as $kunci => $label)
                    @php
                        $jumlahKurang = count($kurang[$kunci] ?? []);
                    @endphp
                    @if ($kunci === $tab)
                        <span aria-current="page" class="flex shrink-0 items-center gap-2 border-b-2 border-brand-700 px-3 py-2.5 text-sm font-semibold text-brand-800" data-tab="{{ $kunci }}">
                            {{ $label }}
                            @if ($jumlahKurang > 0)
                                <span class="rounded-full bg-gold-100 px-1.5 text-[11px] font-semibold text-gold-800 tabular-nums" title="{{ $jumlahKurang }} isian belum lengkap">{{ $jumlahKurang }}</span>
                            @endif
                        </span>
                    @elseif (in_array($kunci, ProfilMadrasah::TAB_TERSEDIA, true))
                        <button type="button" wire:click="$set('tab', '{{ $kunci }}')" data-tab="{{ $kunci }}"
                                class="flex shrink-0 items-center gap-2 border-b-2 border-transparent px-3 py-2.5 text-sm font-medium text-stone-500 hover:border-stone-300 hover:text-stone-800">
                            {{ $label }}
                            @if ($jumlahKurang > 0)
                                <span class="rounded-full bg-gold-100 px-1.5 text-[11px] font-semibold text-gold-800 tabular-nums" title="{{ $jumlahKurang }} isian belum lengkap">{{ $jumlahKurang }}</span>
                            @endif
                        </button>
                    @else
                        <span class="flex shrink-0 cursor-not-allowed items-center gap-2 border-b-2 border-transparent px-3 py-2.5 text-sm font-medium text-stone-400" title="Sedang disiapkan">
                            {{ $label }} <x-ui.segera />
                        </span>
                    @endif
                @endforeach
            </nav>
        </x-slot:tab>
    </x-pengaturan.kepala>

    <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]" wire:key="tab-{{ $tab }}">
        @if ($tab === 'pejabat')
            {{-- ===================== Pejabat penanda tangan ===================== --}}
            <div class="min-w-0 space-y-6">
                @if ($this->tahunTerkunci())
                    <p class="flex items-start gap-2 rounded-xl bg-stone-100 px-4 py-3 text-sm text-stone-700 ring-1 ring-stone-200" data-testid="pejabat-terkunci">
                        <x-ui.icon name="lock" class="mt-0.5 size-4 shrink-0" /> Tahun Anggaran {{ $tahun }} terkunci — pejabatnya hanya bisa dilihat.
                    </p>
                @endif

                <section class="rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-pimpinan" data-testid="pejabat-pimpinan">
                    <header class="border-b border-stone-100 px-5 py-4 sm:px-6">
                        <h2 id="judul-pimpinan" class="text-base font-semibold text-stone-900">Pimpinan madrasah &amp; komite</h2>
                        <p class="mt-0.5 text-sm text-stone-500">Berlaku pada Tahun Anggaran {{ $tahun }}; tahun lain menyimpan pejabatnya sendiri.</p>
                    </header>
                    <div class="grid divide-y divide-stone-100 md:grid-cols-2 md:divide-x md:divide-y-0">
                        @foreach ([['kunci' => 'kepala', 'judul' => 'Kepala Madrasah', 'peran' => 'Pengarah kegiatan dan "Menyetujui" di lembar pengesahan', 'nip' => true],
                                   ['kunci' => 'komite', 'judul' => 'Ketua Komite', 'peran' => '"Mengetahui" di dokumen DIPA maupun Komite', 'nip' => false]] as $peran)
                            @php
                                $orang = $pejabat[$peran['kunci']];
                                $lengkap = $orang['nama'] && (! $peran['nip'] || $orang['nip']);
                            @endphp
                            <article class="min-w-0 px-5 py-4 sm:px-6" data-pejabat="{{ $peran['kunci'] }}" data-lengkap="{{ $lengkap ? 'ya' : 'tidak' }}" wire:key="pejabat-{{ $peran['kunci'] }}-{{ $ubahPejabat === $peran['kunci'] ? 'ubah' : 'lihat' }}">
                                <div class="flex items-start justify-between gap-2">
                                    <h3 class="text-sm font-semibold text-stone-900">{{ $peran['judul'] }}</h3>
                                    @if ($ubahPejabat !== $peran['kunci'])
                                        <span class="flex items-center gap-2">
                                            @if ($lengkap)
                                                <span class="inline-flex items-center gap-1 text-xs font-medium text-brand-700"><x-ui.icon name="check-circle" class="size-3.5" /> Lengkap</span>
                                            @else
                                                <span class="inline-flex items-center gap-1 text-xs font-medium text-gold-700"><x-ui.icon name="alert" class="size-3.5" /> Belum lengkap</span>
                                            @endif
                                            @unless ($this->tahunTerkunci())
                                                <button type="button" wire:click="bukaUbahPejabat('{{ $peran['kunci'] }}')" class="btn-ghost px-2 py-1 text-xs" aria-label="Ubah {{ $peran['judul'] }}"><x-ui.icon name="pencil" class="size-3.5" /> Ubah</button>
                                            @endunless
                                        </span>
                                    @endif
                                </div>
                                <p class="mt-0.5 text-xs text-stone-500">{{ $peran['peran'] }}</p>
                                @if ($ubahPejabat === $peran['kunci'])
                                    <form wire:submit="simpanPejabat" class="mt-3 space-y-4" data-testid="formulir-pejabat">
                                        <x-pengaturan.input-pejabat :awalan="'pejabat-'.$peran['kunci']" :nip-wajib="$peran['nip']" :jabatan-bawaan="\App\Support\Stub\StubPejabatRepository::bawaan()[$peran['kunci']]['jabatan']" />
                                        <div class="flex justify-end gap-2">
                                            <button type="button" wire:click="batalUbahPejabat" class="btn-ghost">Batal</button>
                                            <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="simpanPejabat"><x-ui.icon name="save" class="size-4" /> Simpan</button>
                                        </div>
                                    </form>
                                @else
                                    <dl class="mt-3 space-y-2 text-sm">
                                        <div>
                                            <dt class="text-xs text-stone-500">Jabatan tercetak</dt>
                                            <dd class="font-medium text-stone-800">{{ $orang['jabatan'] }}</dd>
                                        </div>
                                        <div class="grid grid-cols-2 gap-3">
                                            <div class="min-w-0">
                                                <dt class="text-xs text-stone-500">Nama</dt>
                                                <dd @class(['truncate', 'font-medium text-stone-900' => $orang['nama'], 'text-gold-700' => ! $orang['nama']])>{{ $orang['nama'] ?? 'Belum diisi' }}</dd>
                                            </div>
                                            <div class="min-w-0">
                                                <dt class="text-xs text-stone-500">NIP{{ $peran['nip'] ? '' : ' (opsional)' }}</dt>
                                                <dd @class(['truncate', 'font-mono text-xs leading-5 text-stone-900' => $orang['nip'], 'text-gold-700' => ! $orang['nip'] && $peran['nip'], 'text-stone-400' => ! $orang['nip'] && ! $peran['nip']])>{{ $orang['nip'] ? Nip::format($orang['nip']) : ($peran['nip'] ? 'Belum diisi' : '—') }}</dd>
                                            </div>
                                        </div>
                                    </dl>
                                @endif
                            </article>
                        @endforeach
                    </div>
                </section>

                <section class="rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-pejabat-leading" data-testid="pejabat-leading">
                    <header class="border-b border-stone-100 px-5 py-4 sm:px-6">
                        <h2 id="judul-pejabat-leading" class="text-base font-semibold text-stone-900">Pejabat tiap leading</h2>
                        <p class="mt-0.5 text-sm text-stone-500">Penanggung jawab kegiatan dan "Pembuat" di lembar pengesahan. Jabatan bawaan diambil dari pengaturan leading.</p>
                    </header>
                    <div class="hidden overflow-x-auto md:block">
                        <table class="w-full text-sm">
                            <thead class="bg-stone-50 text-xs font-semibold text-stone-500">
                                <tr>
                                    <th scope="col" class="min-w-44 px-5 py-2 text-left sm:pl-6">Leading</th>
                                    <th scope="col" class="px-3 py-2 text-left">Jabatan tercetak</th>
                                    <th scope="col" class="px-3 py-2 text-left">Nama</th>
                                    <th scope="col" class="px-3 py-2 text-left">NIP</th>
                                    <th scope="col" class="px-3 py-2 text-left">Akun tertaut</th>
                                    <th scope="col" class="px-5 py-2 sm:pr-6"><span class="sr-only">Aksi</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-stone-100">
                                @foreach ($pejabat['leading'] as $l)
                                    @php
                                        $kunciLeading = 'leading:'.$l['kode'];
                                    @endphp
                                    <tr class="align-top" data-pejabat-leading="{{ $l['kode'] }}" wire:key="baris-pejabat-{{ $l['kode'] }}">
                                        <th scope="row" class="px-5 py-2.5 text-left font-normal whitespace-nowrap sm:pl-6">
                                            <span class="font-mono text-xs text-stone-400">{{ $l['kode'] }}</span>
                                            <span class="font-medium text-stone-800">{{ $l['nama_unit'] }}</span>
                                        </th>
                                        <td class="px-3 py-2.5 text-stone-700">{{ $l['jabatan'] }}</td>
                                        <td @class(['px-3 py-2.5 whitespace-nowrap', 'text-stone-900' => $l['nama'], 'text-gold-700' => ! $l['nama']])>{{ $l['nama'] ?? 'Belum diisi' }}</td>
                                        <td @class(['px-3 py-2.5 font-mono text-xs whitespace-nowrap', 'text-stone-900' => $l['nip'], 'text-stone-400' => ! $l['nip']])>{{ $l['nip'] ? Nip::format($l['nip']) : '—' }}</td>
                                        <td class="px-3 py-2.5 text-xs text-stone-500">{{ $l['akun'] === [] ? '—' : implode(', ', $l['akun']) }}</td>
                                        <td class="px-5 py-2.5 text-right sm:pr-6">
                                            @if ($ubahPejabat !== $kunciLeading && ! $this->tahunTerkunci())
                                                <button type="button" wire:click="bukaUbahPejabat('{{ $kunciLeading }}')" class="btn-ghost px-2 py-1 text-xs" aria-label="Ubah pejabat {{ $l['kode'] }} {{ $l['nama_unit'] }}"><x-ui.icon name="pencil" class="size-3.5" /> Ubah</button>
                                            @endif
                                        </td>
                                    </tr>
                                    @if ($ubahPejabat === $kunciLeading)
                                        <tr wire:key="formulir-pejabat-{{ $l['kode'] }}">
                                            <td colspan="6" class="bg-stone-50/70 px-5 py-4 sm:px-6">
                                                <form wire:submit="simpanPejabat" class="space-y-4" data-testid="formulir-pejabat">
                                                    <p class="text-sm font-semibold text-stone-900">Pejabat {{ $l['kode'] }} {{ $l['nama_unit'] }} · TA {{ $tahun }}</p>
                                                    <x-pengaturan.input-pejabat :awalan="'pejabat-leading-'.str_replace('.', '-', $l['kode'])" :jabatan-bawaan="\App\Support\Stub\StubPejabatRepository::bawaan()['leading'][$l['kode']]['jabatan']" />
                                                    <div class="flex justify-end gap-2">
                                                        <button type="button" wire:click="batalUbahPejabat" class="btn-ghost">Batal</button>
                                                        <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="simpanPejabat"><x-ui.icon name="save" class="size-4" /> Simpan</button>
                                                    </div>
                                                </form>
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <ul class="divide-y divide-stone-100 md:hidden">
                        @foreach ($pejabat['leading'] as $l)
                            @php
                                $kunciLeading = 'leading:'.$l['kode'];
                            @endphp
                            <li class="px-4 py-3" data-pejabat-leading-kartu="{{ $l['kode'] }}" wire:key="kartu-pejabat-{{ $l['kode'] }}-{{ $ubahPejabat === $kunciLeading ? 'ubah' : 'lihat' }}">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="text-sm"><span class="font-mono text-xs text-stone-400">{{ $l['kode'] }}</span> <span class="font-medium text-stone-800">{{ $l['nama_unit'] }}</span></p>
                                        <p class="mt-0.5 text-xs text-stone-600">{{ $l['jabatan'] }}</p>
                                    </div>
                                    @if ($ubahPejabat !== $kunciLeading && ! $this->tahunTerkunci())
                                        <button type="button" wire:click="bukaUbahPejabat('{{ $kunciLeading }}')" class="btn-ghost shrink-0 px-2 py-1 text-xs" aria-label="Ubah pejabat {{ $l['kode'] }} {{ $l['nama_unit'] }}"><x-ui.icon name="pencil" class="size-3.5" /> Ubah</button>
                                    @endif
                                </div>
                                @if ($ubahPejabat === $kunciLeading)
                                    <form wire:submit="simpanPejabat" class="mt-3 space-y-4" data-testid="formulir-pejabat">
                                        <x-pengaturan.input-pejabat :awalan="'pejabat-leading-m-'.str_replace('.', '-', $l['kode'])" :jabatan-bawaan="\App\Support\Stub\StubPejabatRepository::bawaan()['leading'][$l['kode']]['jabatan']" />
                                        <div class="flex justify-end gap-2">
                                            <button type="button" wire:click="batalUbahPejabat" class="btn-ghost">Batal</button>
                                            <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="simpanPejabat"><x-ui.icon name="save" class="size-4" /> Simpan</button>
                                        </div>
                                    </form>
                                @else
                                    <p @class(['mt-1 text-xs', 'text-stone-900' => $l['nama'], 'text-gold-700' => ! $l['nama']])>
                                        {{ $l['nama'] ?? 'Nama belum diisi' }}{{ $l['nip'] ? ' · NIP '.Nip::format($l['nip']) : '' }}
                                        @if ($l['akun'] !== [])
                                            <span class="text-stone-500">· akun {{ implode(', ', $l['akun']) }}</span>
                                        @endif
                                    </p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    <p class="border-t border-stone-100 px-5 py-3 text-xs text-stone-500 sm:px-6">Selama nama pejabat leading belum diisi, kolom "Pembuat" memakai nama akun leading yang menyusun kegiatan.</p>
                </section>
            </div>

            <aside class="min-w-0 space-y-6">
                {{-- Pratinjau blok tanda tangan lembar pengesahan --}}
                <section class="rounded-2xl bg-white p-5 shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-blok-ttd" data-testid="pratinjau-ttd">
                    <h2 id="judul-blok-ttd" class="text-sm font-semibold text-stone-900">Blok tanda tangan</h2>
                    <p class="mt-0.5 text-xs text-stone-500">Lembar pengesahan setiap KAK/TOR &amp; RAB.</p>
                    <div class="mt-4 rounded-lg bg-stone-50 p-3 font-serif text-[11px] leading-snug text-stone-800 ring-1 ring-stone-200">
                        <div class="grid grid-cols-2 gap-3 text-center">
                            <div data-ttd="komite">
                                <p>Mengetahui,</p>
                                <p>{{ $pejabat['komite']['jabatan'] }}</p>
                                <p class="mt-6 font-semibold underline">{{ $pejabat['komite']['nama'] ?? '[Nama Ketua Komite]' }}</p>
                            </div>
                            <div data-ttd="leading">
                                <p>{{ $profil['kota'] ?? '[Kota]' }}, [tanggal dokumen]</p>
                                <p>[Jabatan pejabat leading]</p>
                                <p class="mt-6 font-semibold underline">[Nama pejabat leading]</p>
                                <p>NIP [NIP]</p>
                            </div>
                        </div>
                        <div class="mt-4 text-center" data-ttd="kepala">
                            <p>Menyetujui,</p>
                            <p>{{ $pejabat['kepala']['jabatan'] }}</p>
                            <p class="mt-6 font-semibold underline">{{ $pejabat['kepala']['nama'] ?? '[Nama Kepala Madrasah]' }}</p>
                            <p>NIP {{ $pejabat['kepala']['nip'] ? Nip::format($pejabat['kepala']['nip']) : '[NIP]' }}</p>
                        </div>
                    </div>
                    <p class="mt-3 flex items-start gap-1.5 text-xs text-stone-500"><x-ui.icon name="lock" class="mt-0.5 size-3.5 shrink-0" /> Nama dan NIP pejabat tidak pernah dikirim ke penyedia AI.</p>
                </section>
                @include('livewire.pengaturan.partials.kesiapan-madrasah')
            </aside>
        @else
            {{-- ===================== Profil madrasah ===================== --}}
            <div class="min-w-0 space-y-6">
                <section class="rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-identitas" data-testid="profil-identitas">
                    <header class="flex flex-col gap-3 border-b border-stone-100 px-5 py-4 sm:flex-row sm:items-start sm:justify-between sm:px-6">
                        <div class="min-w-0">
                            <h2 id="judul-identitas" class="text-base font-semibold text-stone-900">Identitas madrasah</h2>
                            <p class="mt-0.5 text-sm text-stone-500">Satu profil untuk semua tahun; perubahan langsung dipakai dokumen yang dicetak berikutnya.</p>
                        </div>
                        @unless ($ubahProfil)
                            <button type="button" wire:click="bukaUbahProfil" class="btn-secondary shrink-0 self-start"><x-ui.icon name="pencil" class="size-4" /> Ubah</button>
                        @endunless
                    </header>

                    @if ($ubahProfil)
                        {{-- Formulir: isian dirapikan saat disimpan (tidak di tempat) agar ketikan tidak tertimpa. --}}
                        <form wire:submit="simpanProfil" class="space-y-5 px-5 py-5 sm:px-6" data-testid="formulir-profil" wire:key="formulir-profil">
                            <div class="grid gap-5 sm:grid-cols-2">
                                <x-ui.field class="sm:col-span-2" label="Nama madrasah" for="profil-nama" error="isian.nama" required hint="Nama lengkap sesuai SK pendirian; tercetak kapital di kop dan cover.">
                                    <input type="text" id="profil-nama" wire:model="isian.nama" maxlength="150" class="form-input" autocomplete="organization" @error('isian.nama') aria-invalid="true" aria-describedby="profil-nama-error" @enderror>
                                </x-ui.field>
                                <x-ui.field label="Nama singkat" for="profil-nama-singkat" error="isian.nama_singkat" required hint="Mis. MTsN 2 Kota Malang — untuk sebutan sumber dana di dokumen.">
                                    <input type="text" id="profil-nama-singkat" wire:model="isian.nama_singkat" maxlength="60" class="form-input" @error('isian.nama_singkat') aria-invalid="true" aria-describedby="profil-nama-singkat-error" @enderror>
                                </x-ui.field>
                                <x-ui.field label="Kota" for="profil-kota" error="isian.kota" required hint="Tempat penandatanganan, mis. Malang.">
                                    <input type="text" id="profil-kota" wire:model="isian.kota" maxlength="60" class="form-input" autocomplete="address-level2" @error('isian.kota') aria-invalid="true" aria-describedby="profil-kota-error" @enderror>
                                </x-ui.field>
                                <x-ui.field class="sm:col-span-2" label="Alamat" for="profil-alamat" error="isian.alamat" hint="Wajib untuk kop; jalan, nomor, kelurahan, kode pos.">
                                    <input type="text" id="profil-alamat" wire:model="isian.alamat" maxlength="255" class="form-input" autocomplete="street-address" placeholder="Jl. … No. …, Kota Malang 65…">
                                </x-ui.field>
                                <x-ui.field label="Telepon" for="profil-telepon" error="isian.telepon" hint="Wajib untuk kop.">
                                    <input type="tel" id="profil-telepon" wire:model="isian.telepon" maxlength="40" class="form-input" autocomplete="tel" inputmode="tel" placeholder="(0341) …" @error('isian.telepon') aria-invalid="true" aria-describedby="profil-telepon-error" @enderror>
                                </x-ui.field>
                                <x-ui.field class="sm:col-span-2" label="Visi" for="profil-visi" error="isian.visi" hint="Opsional; maksimal 1.000 karakter.">
                                    <textarea id="profil-visi" wire:model="isian.visi" rows="3" maxlength="1000" class="form-input"></textarea>
                                </x-ui.field>
                            </div>
                            <div class="flex flex-wrap items-center justify-end gap-2 border-t border-stone-100 pt-4">
                                <button type="button" wire:click="batalUbahProfil" class="btn-ghost">Batal</button>
                                <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="simpanProfil">
                                    <x-ui.icon name="save" class="size-4" /> Simpan profil
                                </button>
                            </div>
                        </form>
                    @else
                        <dl class="divide-y divide-stone-100" wire:key="ringkasan-profil">
                            @foreach (ProfilMadrasah::ISIAN_PROFIL as $kunci => $isianProfil)
                                <div class="grid gap-1 px-5 py-3 sm:grid-cols-[11rem_minmax(0,1fr)] sm:gap-4 sm:px-6" data-isian="{{ $kunci }}" data-terisi="{{ $profil[$kunci] !== null ? 'ya' : 'tidak' }}">
                                    <dt class="text-sm text-stone-500">
                                        {{ $isianProfil['label'] }}
                                        @unless ($isianProfil['wajib'])
                                            <span class="text-xs text-stone-400">(opsional)</span>
                                        @endunless
                                    </dt>
                                    <dd class="min-w-0">
                                        @if ($profil[$kunci] !== null)
                                            <p class="text-sm font-medium break-words whitespace-pre-line text-stone-900">{{ $profil[$kunci] }}</p>
                                        @else
                                            <p @class(['flex items-center gap-1 text-sm font-medium', 'text-gold-700' => $isianProfil['wajib'], 'text-stone-400' => ! $isianProfil['wajib']])>
                                                @if ($isianProfil['wajib'])
                                                    <x-ui.icon name="alert" class="size-3.5 shrink-0" />
                                                @endif
                                                Belum diisi
                                            </p>
                                        @endif
                                        <p class="mt-0.5 text-xs text-stone-500">{{ $isianProfil['dipakai'] }}</p>
                                    </dd>
                                </div>
                            @endforeach
                        </dl>
                    @endif
                </section>

                <section class="rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-logo" data-testid="profil-logo">
                    <header class="border-b border-stone-100 px-5 py-4 sm:px-6">
                        <h2 id="judul-logo" class="text-base font-semibold text-stone-900">Logo kop</h2>
                        <p class="mt-0.5 text-sm text-stone-500">Tercetak di kiri kop KAK/TOR, RAB, dan cover buku.</p>
                    </header>
                    {{-- wire:key per keadaan: status Alpine unggah dibuat ulang, tidak terbawa morph. --}}
                    <div class="px-5 py-4 sm:px-6" wire:key="logo-{{ $this->logoBisaDipratinjau() ? 'baru' : ($profil['logo_path'] ? 'ada' : 'kosong') }}"
                         x-data="{ unggah: false, persen: 0 }"
                         x-on:livewire-upload-start="unggah = true; persen = 0"
                         x-on:livewire-upload-progress="persen = $event.detail.progress"
                         x-on:livewire-upload-finish="unggah = false"
                         x-on:livewire-upload-error="unggah = false">
                        <div class="flex flex-wrap items-center gap-4">
                            <div class="flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-stone-50 ring-1 ring-stone-200" data-testid="gambar-logo">
                                @if ($this->logoBisaDipratinjau())
                                    <img src="{{ $logo->temporaryUrl() }}" alt="Pratinjau logo baru" class="size-full object-contain p-1.5">
                                @elseif ($this->urlLogo())
                                    <img src="{{ $this->urlLogo() }}" alt="Logo madrasah" class="size-full object-contain p-1.5">
                                @else
                                    <x-ui.icon name="building" class="size-8 text-stone-300" />
                                @endif
                            </div>
                            <div class="min-w-0 flex-1 text-sm">
                                @if ($this->logoBisaDipratinjau())
                                    <p class="font-medium text-stone-900">{{ $logo->getClientOriginalName() }}</p>
                                    <p class="text-xs text-stone-500">Logo baru — belum disimpan. Periksa di pratinjau kop.</p>
                                @elseif ($profil['logo_path'])
                                    <p class="flex items-center gap-1 font-medium text-brand-700"><x-ui.icon name="check-circle" class="size-3.5" /> Logo tersimpan</p>
                                    <p class="text-xs text-stone-500">Ganti untuk memakai logo lain.</p>
                                @else
                                    <p class="flex items-center gap-1 font-medium text-gold-700"><x-ui.icon name="alert" class="size-3.5" /> Belum diunggah</p>
                                    <p class="text-xs text-stone-500">PNG atau JPG, persegi, minimal {{ ProfilMadrasah::MIN_SISI_LOGO }} × {{ ProfilMadrasah::MIN_SISI_LOGO }} piksel, maks. {{ ProfilMadrasah::MAKS_LOGO_KB / 1024 }} MB; latar transparan agar menyatu dengan kop.</p>
                                @endif
                            </div>
                            <div class="flex shrink-0 flex-wrap gap-2">
                                @if ($this->logoBisaDipratinjau())
                                    <button type="button" wire:click="batalLogo" class="btn-ghost">Batal</button>
                                    <button type="button" wire:click="simpanLogo" class="btn-primary" wire:loading.attr="disabled" wire:target="simpanLogo"><x-ui.icon name="save" class="size-4" /> Simpan logo</button>
                                @else
                                    <label for="logo-madrasah" class="btn-secondary cursor-pointer has-focus-visible:outline-2 has-focus-visible:outline-brand-600">
                                        <x-ui.icon name="upload" class="size-4" /> {{ $profil['logo_path'] ? 'Ganti logo' : 'Unggah logo' }}
                                        <input type="file" id="logo-madrasah" wire:model="logo" accept="image/png,image/jpeg" class="sr-only" data-testid="input-logo">
                                    </label>
                                    @if ($profil['logo_path'] && ! $konfirmasiHapusLogo)
                                        <button type="button" wire:click="$set('konfirmasiHapusLogo', true)" class="btn-ghost text-red-700 hover:bg-red-50">
                                            <x-ui.icon name="trash" class="size-4" /> Hapus
                                        </button>
                                    @endif
                                @endif
                            </div>
                        </div>
                        @if ($profil['logo_path'] && $konfirmasiHapusLogo && ! $this->logoBisaDipratinjau())
                            <div class="mt-3 flex flex-wrap items-center justify-between gap-2 rounded-lg bg-red-50 px-3 py-2 ring-1 ring-red-200" role="group" aria-label="Konfirmasi hapus logo">
                                <span class="text-sm text-red-800">Hapus logo kop? Dokumen dicetak tanpa logo sampai diunggah lagi.</span>
                                <span class="flex gap-1.5">
                                    <button type="button" class="btn-ghost px-2 py-1 text-xs" wire:click="$set('konfirmasiHapusLogo', false)">Batal</button>
                                    <button type="button" class="btn-danger px-2.5 py-1 text-xs" wire:click="hapusLogo">Hapus</button>
                                </span>
                            </div>
                        @endif
                        <div x-show="unggah" x-cloak class="mt-3">
                            <div class="h-1.5 overflow-hidden rounded-full bg-stone-100"><div class="h-full rounded-full bg-brand-600 transition-all" x-bind:style="`width: ${persen}%`"></div></div>
                            <p class="mt-1 text-xs text-stone-500">Mengunggah… <span x-text="persen"></span>%</p>
                        </div>
                        @error('logo')
                            <p class="mt-3 flex items-start gap-2 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 ring-1 ring-red-200" role="alert" data-testid="galat-logo">
                                <x-ui.icon name="alert" class="mt-0.5 size-4 shrink-0" /> {{ $message }}
                            </p>
                        @enderror
                    </div>
                </section>
            </div>

            <aside class="min-w-0 space-y-6">
                {{-- Pratinjau kop: susunan sama dengan variabel ${kop} (nama kapital, alamat, telepon); saat formulir terbuka mengikuti ketikan. --}}
                <section class="rounded-2xl bg-white p-5 shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-pratinjau-kop" data-testid="pratinjau-kop">
                    <h2 id="judul-pratinjau-kop" class="text-sm font-semibold text-stone-900">Pratinjau kop</h2>
                    <p class="mt-0.5 text-xs text-stone-500">{{ $ubahProfil ? 'Mengikuti isian formulir (belum disimpan).' : 'Isian kosong ditandai sampai diisi.' }}</p>
                    <div class="mt-4 rounded-lg bg-white p-3 shadow-xs ring-1 ring-stone-200">
                        <div class="flex items-center gap-3 border-b-[3px] border-double border-stone-800 pb-2">
                            @if ($this->logoBisaDipratinjau() || $this->urlLogo())
                                <img src="{{ $this->logoBisaDipratinjau() ? $logo->temporaryUrl() : $this->urlLogo() }}" alt="" class="size-10 shrink-0 object-contain" data-testid="kop-logo">
                            @else
                                <div class="flex size-10 shrink-0 items-center justify-center rounded border border-dashed border-stone-300 text-[8px] text-stone-400" aria-hidden="true">LOGO</div>
                            @endif
                            <div class="min-w-0 flex-1 text-center font-serif leading-tight" @if ($ubahProfil) x-data @endif>
                                @if ($ubahProfil)
                                    <p class="text-[11px] font-bold tracking-wide text-stone-900 uppercase" x-text="$wire.isian.nama?.trim() || '[Nama madrasah]'" data-kop="nama"></p>
                                    <p class="text-[9px]" x-bind:class="{ 'text-stone-700': $wire.isian.alamat?.trim(), 'text-gold-700 italic': ! $wire.isian.alamat?.trim() }" x-text="$wire.isian.alamat?.trim() || '[Alamat]'" data-kop="alamat"></p>
                                    <p class="text-[9px]" x-bind:class="{ 'text-stone-700': $wire.isian.telepon?.trim(), 'text-gold-700 italic': ! $wire.isian.telepon?.trim() }" x-text="'Telepon ' + ($wire.isian.telepon?.trim() || '[telepon]')" data-kop="telepon"></p>
                                @else
                                    <p class="text-[11px] font-bold tracking-wide text-stone-900 uppercase" data-kop="nama">{{ $profil['nama'] ?? '[Nama madrasah]' }}</p>
                                    <p @class(['text-[9px]', 'text-stone-700' => $profil['alamat'], 'text-gold-700 italic' => ! $profil['alamat']]) data-kop="alamat">{{ $profil['alamat'] ?? '[Alamat]' }}</p>
                                    <p @class(['text-[9px]', 'text-stone-700' => $profil['telepon'], 'text-gold-700 italic' => ! $profil['telepon']]) data-kop="telepon">Telepon {{ $profil['telepon'] ?? '[telepon]' }}</p>
                                @endif
                            </div>
                        </div>
                    </div>
                    <dl class="mt-4 space-y-1.5 text-xs" aria-label="Variabel template yang terisi dari profil">
                        @foreach (['nama_madrasah' => 'Nama madrasah', 'kop' => 'Kop (nama, alamat, telepon)', 'kota' => 'Kota', 'logo' => 'Logo'] as $variabel => $arti)
                            <div class="flex items-baseline justify-between gap-2">
                                <dt><code class="rounded bg-stone-100 px-1 py-0.5 font-mono text-[11px] text-stone-700">${{ '{'.$variabel.'}' }}</code></dt>
                                <dd class="text-right text-stone-500">{{ $arti }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </section>
                @include('livewire.pengaturan.partials.kesiapan-madrasah')
            </aside>
        @endif
    </div>
</div>
