@use('App\Livewire\Pengaturan\ProfilMadrasah')

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
                <section class="rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-pimpinan" data-testid="pejabat-pimpinan">
                    <header class="flex flex-col gap-3 border-b border-stone-100 px-5 py-4 sm:flex-row sm:items-start sm:justify-between sm:px-6">
                        <div class="min-w-0">
                            <h2 id="judul-pimpinan" class="text-base font-semibold text-stone-900">Pimpinan madrasah &amp; komite</h2>
                            <p class="mt-0.5 text-sm text-stone-500">Berlaku pada Tahun Anggaran {{ $tahun }}; tahun lain menyimpan pejabatnya sendiri.</p>
                        </div>
                        <button type="button" class="btn-secondary shrink-0 self-start" disabled title="Formulir pejabat sedang disiapkan"><x-ui.icon name="pencil" class="size-4" /> Ubah <x-ui.segera /></button>
                    </header>
                    <div class="grid divide-y divide-stone-100 md:grid-cols-2 md:divide-x md:divide-y-0">
                        @foreach ([['kunci' => 'kepala', 'judul' => 'Kepala Madrasah', 'peran' => 'Pengarah kegiatan dan "Menyetujui" di lembar pengesahan', 'nip' => true],
                                   ['kunci' => 'komite', 'judul' => 'Ketua Komite', 'peran' => '"Mengetahui" di dokumen DIPA maupun Komite', 'nip' => false]] as $peran)
                            @php
                                $orang = $pejabat[$peran['kunci']];
                                $lengkap = $orang['nama'] && (! $peran['nip'] || $orang['nip']);
                            @endphp
                            <article class="px-5 py-4 sm:px-6" data-pejabat="{{ $peran['kunci'] }}" data-lengkap="{{ $lengkap ? 'ya' : 'tidak' }}">
                                <div class="flex items-start justify-between gap-2">
                                    <h3 class="text-sm font-semibold text-stone-900">{{ $peran['judul'] }}</h3>
                                    @if ($lengkap)
                                        <span class="inline-flex items-center gap-1 text-xs font-medium text-brand-700"><x-ui.icon name="check-circle" class="size-3.5" /> Lengkap</span>
                                    @else
                                        <span class="inline-flex items-center gap-1 text-xs font-medium text-gold-700"><x-ui.icon name="alert" class="size-3.5" /> Belum lengkap</span>
                                    @endif
                                </div>
                                <p class="mt-0.5 text-xs text-stone-500">{{ $peran['peran'] }}</p>
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
                                            <dd @class(['truncate', 'font-mono text-xs leading-5 text-stone-900' => $orang['nip'], 'text-gold-700' => ! $orang['nip'] && $peran['nip'], 'text-stone-400' => ! $orang['nip'] && ! $peran['nip']])>{{ $orang['nip'] ?? ($peran['nip'] ? 'Belum diisi' : '—') }}</dd>
                                        </div>
                                    </div>
                                </dl>
                            </article>
                        @endforeach
                    </div>
                </section>

                <section class="rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-pejabat-leading" data-testid="pejabat-leading">
                    <header class="border-b border-stone-100 px-5 py-4 sm:px-6">
                        <h2 id="judul-pejabat-leading" class="text-base font-semibold text-stone-900">Pejabat tiap leading</h2>
                        <p class="mt-0.5 text-sm text-stone-500">Penanggung jawab kegiatan dan "Pembuat" di lembar pengesahan. Jabatan diambil dari pengaturan leading.</p>
                    </header>
                    <div class="hidden overflow-x-auto md:block">
                        <table class="w-full text-sm">
                            <thead class="bg-stone-50 text-xs font-semibold text-stone-500">
                                <tr>
                                    <th scope="col" class="min-w-44 px-5 py-2 text-left sm:pl-6">Leading</th>
                                    <th scope="col" class="px-3 py-2 text-left">Jabatan penanda tangan</th>
                                    <th scope="col" class="px-3 py-2 text-left">Nama</th>
                                    <th scope="col" class="px-3 py-2 text-left">NIP</th>
                                    <th scope="col" class="px-5 py-2 text-left sm:pr-6">Akun tertaut</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-stone-100">
                                @foreach ($pejabat['leading'] as $l)
                                    <tr class="align-top" data-pejabat-leading="{{ $l['kode'] }}">
                                        <th scope="row" class="px-5 py-2.5 text-left font-normal whitespace-nowrap sm:pl-6">
                                            <span class="font-mono text-xs text-stone-400">{{ $l['kode'] }}</span>
                                            <span class="font-medium text-stone-800">{{ $l['nama_unit'] }}</span>
                                        </th>
                                        <td class="px-3 py-2.5 text-stone-700">{{ $l['jabatan'] }}</td>
                                        <td @class(['px-3 py-2.5 whitespace-nowrap', 'text-stone-900' => $l['nama'], 'text-gold-700' => ! $l['nama']])>{{ $l['nama'] ?? 'Belum diisi' }}</td>
                                        <td @class(['px-3 py-2.5 font-mono text-xs', 'text-stone-900' => $l['nip'], 'text-stone-400' => ! $l['nip']])>{{ $l['nip'] ?? '—' }}</td>
                                        <td class="px-5 py-2.5 text-xs text-stone-500 sm:pr-6">{{ $l['akun'] === [] ? '—' : implode(', ', $l['akun']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <ul class="divide-y divide-stone-100 md:hidden">
                        @foreach ($pejabat['leading'] as $l)
                            <li class="px-4 py-3" data-pejabat-leading-kartu="{{ $l['kode'] }}">
                                <p class="text-sm"><span class="font-mono text-xs text-stone-400">{{ $l['kode'] }}</span> <span class="font-medium text-stone-800">{{ $l['nama_unit'] }}</span></p>
                                <p class="mt-0.5 text-xs text-stone-600">{{ $l['jabatan'] }}</p>
                                <p @class(['mt-1 text-xs', 'text-stone-900' => $l['nama'], 'text-gold-700' => ! $l['nama']])>
                                    {{ $l['nama'] ?? 'Nama belum diisi' }}{{ $l['nip'] ? ' · NIP '.$l['nip'] : '' }}
                                    @if ($l['akun'] !== [])
                                        <span class="text-stone-500">· akun {{ implode(', ', $l['akun']) }}</span>
                                    @endif
                                </p>
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
                            <p>NIP {{ $pejabat['kepala']['nip'] ?? '[NIP]' }}</p>
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
                        <button type="button" class="btn-secondary shrink-0 self-start" disabled title="Formulir profil sedang disiapkan"><x-ui.icon name="pencil" class="size-4" /> Ubah <x-ui.segera /></button>
                    </header>
                    <dl class="divide-y divide-stone-100">
                        @foreach (ProfilMadrasah::ISIAN_PROFIL as $kunci => $isian)
                            <div class="grid gap-1 px-5 py-3 sm:grid-cols-[11rem_minmax(0,1fr)] sm:gap-4 sm:px-6" data-isian="{{ $kunci }}" data-terisi="{{ $profil[$kunci] !== null ? 'ya' : 'tidak' }}">
                                <dt class="text-sm text-stone-500">
                                    {{ $isian['label'] }}
                                    @unless ($isian['wajib'])
                                        <span class="text-xs text-stone-400">(opsional)</span>
                                    @endunless
                                </dt>
                                <dd class="min-w-0">
                                    @if ($profil[$kunci] !== null)
                                        <p class="text-sm font-medium break-words text-stone-900">{{ $profil[$kunci] }}</p>
                                    @else
                                        <p @class(['flex items-center gap-1 text-sm font-medium', 'text-gold-700' => $isian['wajib'], 'text-stone-400' => ! $isian['wajib']])>
                                            @if ($isian['wajib'])
                                                <x-ui.icon name="alert" class="size-3.5 shrink-0" />
                                            @endif
                                            Belum diisi
                                        </p>
                                    @endif
                                    <p class="mt-0.5 text-xs text-stone-500">{{ $isian['dipakai'] }}</p>
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                </section>

                <section class="rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-logo" data-testid="profil-logo">
                    <header class="flex flex-col gap-3 border-b border-stone-100 px-5 py-4 sm:flex-row sm:items-start sm:justify-between sm:px-6">
                        <div class="min-w-0">
                            <h2 id="judul-logo" class="text-base font-semibold text-stone-900">Logo kop</h2>
                            <p class="mt-0.5 text-sm text-stone-500">Tercetak di kiri kop KAK/TOR, RAB, dan cover buku.</p>
                        </div>
                        <button type="button" class="btn-secondary shrink-0 self-start" disabled title="Unggah logo sedang disiapkan"><x-ui.icon name="upload" class="size-4" /> Unggah <x-ui.segera /></button>
                    </header>
                    <div class="flex items-center gap-4 px-5 py-4 sm:px-6">
                        <div class="flex size-20 shrink-0 items-center justify-center rounded-xl border-2 border-dashed border-stone-300 bg-stone-50 text-stone-400" aria-hidden="true">
                            <x-ui.icon name="building" class="size-8" />
                        </div>
                        <div class="min-w-0 text-sm">
                            @if ($profil['logo_path'])
                                <p class="font-medium text-stone-900">Logo tersimpan</p>
                            @else
                                <p class="flex items-center gap-1 font-medium text-gold-700"><x-ui.icon name="alert" class="size-3.5" /> Belum diunggah</p>
                            @endif
                            <p class="mt-0.5 text-xs text-stone-500">PNG atau JPG, persegi, minimal 300 × 300 piksel; latar transparan agar menyatu dengan kop.</p>
                        </div>
                    </div>
                </section>
            </div>

            <aside class="min-w-0 space-y-6">
                {{-- Pratinjau kop: susunan sama dengan variabel ${kop} (nama kapital, alamat, telepon) --}}
                <section class="rounded-2xl bg-white p-5 shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-pratinjau-kop" data-testid="pratinjau-kop">
                    <h2 id="judul-pratinjau-kop" class="text-sm font-semibold text-stone-900">Pratinjau kop</h2>
                    <p class="mt-0.5 text-xs text-stone-500">Isian kosong ditandai sampai diisi.</p>
                    <div class="mt-4 rounded-lg bg-white p-3 shadow-xs ring-1 ring-stone-200">
                        <div class="flex items-center gap-3 border-b-[3px] border-double border-stone-800 pb-2">
                            <div class="flex size-10 shrink-0 items-center justify-center rounded border border-dashed border-stone-300 text-[8px] text-stone-400" aria-hidden="true">LOGO</div>
                            <div class="min-w-0 flex-1 text-center font-serif leading-tight">
                                <p class="text-[11px] font-bold tracking-wide text-stone-900 uppercase">{{ $profil['nama'] ?? '[Nama madrasah]' }}</p>
                                <p @class(['text-[9px]', 'text-stone-700' => $profil['alamat'], 'text-gold-700 italic' => ! $profil['alamat']])>{{ $profil['alamat'] ?? '[Alamat]' }}</p>
                                <p @class(['text-[9px]', 'text-stone-700' => $profil['telepon'], 'text-gold-700 italic' => ! $profil['telepon']])>Telepon {{ $profil['telepon'] ?? '[telepon]' }}</p>
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
