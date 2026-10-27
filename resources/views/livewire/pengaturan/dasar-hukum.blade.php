@use('App\Livewire\Pengaturan\ProfilMadrasah')

@php
    $dasarHukum = $this->dasarHukum;
    $latar = $this->latarBelakang;
    $sisip = $this->kalimatSisip();
    $ta = $this->tahunDilihat();
    $tahunAktif = \App\Support\Stub\StubData::tahunAktif()['tahun'];
    $contoh = \App\Support\Kegiatan\KalimatKak::tanpaAwalanKegiatan($this->contohKegiatan());
    $kesiapan = $this->kesiapan();
    $pengaturan = $this->pengaturan;
@endphp

<div class="mx-auto max-w-7xl">
    <x-pengaturan.kepala judul="Profil & Pejabat"
                         deskripsi="Dasar hukum dan paragraf latar belakang baku tercetak di bagian 1–2 setiap KAK/TOR. Diatur per tahun anggaran; leading menambahkan paragraf khusus kegiatannya sendiri di Data Kegiatan.">
        <x-slot:tab>
            <nav class="-mb-px flex gap-1 overflow-x-auto border-b border-stone-200" aria-label="Bagian profil & pejabat">
                @foreach (ProfilMadrasah::TAB as $kunci => $label)
                    @if ($kunci === 'dasar_hukum')
                        <span aria-current="page" class="flex shrink-0 items-center gap-2 border-b-2 border-brand-700 px-3 py-2.5 text-sm font-semibold text-brand-800" data-tab="{{ $kunci }}">{{ $label }}</span>
                    @else
                        <a href="{{ route('pengaturan.madrasah.index', $kunci === 'profil' ? [] : ['tab' => $kunci]) }}" wire:navigate data-tab="{{ $kunci }}"
                           class="flex shrink-0 items-center gap-2 border-b-2 border-transparent px-3 py-2.5 text-sm font-medium text-stone-500 hover:border-stone-300 hover:text-stone-800">{{ $label }}</a>
                    @endif
                @endforeach
            </nav>
        </x-slot:tab>
    </x-pengaturan.kepala>

    <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="min-w-0 space-y-6">
            {{-- Pemilih tahun: dasar hukum & latar belakang disimpan per tahun anggaran; dokumen memakai tahun aktif. --}}
            <div class="flex flex-wrap items-center justify-between gap-3" data-testid="pemilih-tahun-dasar-hukum">
                <div class="inline-flex rounded-lg bg-stone-100 p-1" role="group" aria-label="Tahun anggaran">
                    @foreach ($this->daftarTahun() as $t)
                        @php
                            $dipilih = $t['tahun'] === $ta;
                        @endphp
                        <button type="button" wire:click="$set('tahun', '{{ $t['is_active'] ? '' : $t['tahun'] }}')" aria-pressed="{{ $dipilih ? 'true' : 'false' }}" data-tahun="{{ $t['tahun'] }}"
                                @class(['inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium', 'bg-white text-stone-900 shadow-xs' => $dipilih, 'text-stone-500 hover:text-stone-800' => ! $dipilih])>
                            TA {{ $t['tahun'] }}
                            @if ($t['is_active'])
                                <span class="rounded bg-brand-50 px-1 text-[10px] font-semibold text-brand-800">aktif</span>
                            @elseif ($t['is_locked'])
                                <x-ui.icon name="lock" class="size-3.5 text-stone-400" />
                            @endif
                        </button>
                    @endforeach
                </div>
                <p class="text-xs text-stone-500">
                    Dokumen tahun aktif memakai dasar hukum TA {{ $tahunAktif }}.
                    @if ($pengaturan['diperbarui_pada'])
                        <span class="text-stone-400">· TA {{ $ta }} diperbarui {{ \App\Support\Format::tanggal($pengaturan['diperbarui_pada']) }}</span>
                    @endif
                </p>
            </div>

            @if ($this->tahunTerkunci())
                <p class="flex items-start gap-2 rounded-xl bg-stone-100 px-4 py-3 text-sm text-stone-700 ring-1 ring-stone-200" data-testid="dasar-hukum-terkunci">
                    <x-ui.icon name="lock" class="mt-0.5 size-4 shrink-0" /> Tahun Anggaran {{ $ta }} terkunci — dasar hukum &amp; latar belakangnya hanya bisa dilihat.
                </p>
            @endif

            {{-- Dasar hukum --}}
            <section class="rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-dasar-hukum" data-testid="daftar-dasar-hukum">
                <header class="flex flex-col gap-3 border-b border-stone-100 px-5 py-4 sm:flex-row sm:items-start sm:justify-between sm:px-6">
                    <div class="min-w-0">
                        <h2 id="judul-dasar-hukum" class="text-base font-semibold text-stone-900">Dasar hukum</h2>
                        <p class="mt-0.5 text-sm text-stone-500">{{ count($dasarHukum) }} butir · tercetak bernomor di bagian 2 KAK/TOR sesuai urutan ini.</p>
                    </div>
                    <div class="flex shrink-0 flex-wrap gap-2 self-start">
                        <button type="button" class="btn-secondary" disabled title="Sedang disiapkan"><x-ui.icon name="plus" class="size-4" /> Tambah butir <x-ui.segera /></button>
                    </div>
                </header>
                @if ($dasarHukum === [])
                    <p class="px-5 py-8 text-center text-sm text-stone-500 sm:px-6" data-testid="dasar-hukum-kosong">
                        Belum ada dasar hukum TA {{ $ta }}.
                        @unless ($pengaturan['diatur'])
                            <span class="block text-xs">Tahun ini belum diatur — paragraf latar belakang memakai teks bawaan.</span>
                        @endunless
                    </p>
                @else
                    <ol class="divide-y divide-stone-100">
                        @foreach ($dasarHukum as $i => $butir)
                            <li class="flex items-start gap-3 px-5 py-3 sm:px-6" data-butir="{{ $i + 1 }}" data-id="{{ $butir['id'] }}" wire:key="butir-{{ $butir['id'] }}">
                                <span class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-stone-100 text-xs font-semibold text-stone-600 tabular-nums" aria-hidden="true">{{ $i + 1 }}</span>
                                <p class="min-w-0 flex-1 text-sm text-stone-800">{{ $butir['teks'] }}</p>
                            </li>
                        @endforeach
                    </ol>
                @endif
                <p class="border-t border-stone-100 px-5 py-3 text-xs text-stone-500 sm:px-6">Akhiri butir dengan titik koma (;) dan butir terakhir dengan titik (.) seperti di dokumen.</p>
            </section>

            {{-- Latar belakang baku --}}
            <section class="rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-latar-baku" data-testid="latar-belakang-baku">
                <header class="flex flex-col gap-3 border-b border-stone-100 px-5 py-4 sm:flex-row sm:items-start sm:justify-between sm:px-6">
                    <div class="min-w-0">
                        <h2 id="judul-latar-baku" class="text-base font-semibold text-stone-900">Latar belakang baku</h2>
                        <p class="mt-0.5 text-sm text-stone-500">Paragraf pembuka bagian 1 KAK/TOR semua kegiatan (visi madrasah).</p>
                    </div>
                    <button type="button" class="btn-secondary shrink-0 self-start" disabled title="Sedang disiapkan"><x-ui.icon name="pencil" class="size-4" /> Ubah <x-ui.segera /></button>
                </header>
                <div class="space-y-4 px-5 py-4 sm:px-6">
                    <div>
                        <h3 class="text-xs font-semibold text-stone-500">Paragraf baku</h3>
                        <p class="mt-1 text-sm leading-relaxed text-stone-800" data-testid="paragraf-baku">{{ $latar }}</p>
                        <p class="mt-1 text-xs text-stone-500">Tahun anggaran tersisip otomatis (TA {{ $ta }}).</p>
                    </div>
                    <div>
                        <h3 class="text-xs font-semibold text-stone-500">Kalimat penutup tiap kegiatan</h3>
                        <p class="mt-1 rounded-lg bg-stone-50 px-3 py-2 text-sm leading-relaxed text-stone-800 ring-1 ring-stone-200" data-testid="kalimat-sisip">
                            {{ $sisip['sebelum'] }}@if ($sisip['sesudah'] !== null)<mark class="rounded bg-brand-100 px-1 font-medium text-brand-900">nama kegiatan</mark>{{ $sisip['sesudah'] }}@endif
                        </p>
                        <p class="mt-1 text-xs text-stone-500">Nama kegiatan disisipkan otomatis (tanpa awalan "Kegiatan"). Di antara paragraf baku dan kalimat ini, leading dapat menambah paragraf khusus kegiatannya.</p>
                    </div>
                </div>
            </section>
        </div>

        <aside class="min-w-0 space-y-6">
            {{-- Pratinjau bagian 1–2 KAK dengan contoh kegiatan: susunan sama dengan SusunanKak. --}}
            <section class="rounded-2xl bg-white p-5 shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-pratinjau-dasar" data-testid="pratinjau-dasar-hukum">
                <h2 id="judul-pratinjau-dasar" class="text-sm font-semibold text-stone-900">Tercetak di KAK/TOR</h2>
                <p class="mt-0.5 text-xs text-stone-500">Contoh: {{ $contoh }} · TA {{ $ta }}.</p>
                <div class="mt-4 space-y-3 rounded-lg bg-white p-3 font-serif text-[11px] leading-snug text-stone-800 shadow-xs ring-1 ring-stone-200">
                    <div>
                        <p class="font-bold">1. Latar Belakang</p>
                        <p class="mt-1 text-justify indent-4">{{ $latar }}</p>
                        <p class="mt-1 text-justify indent-4 text-stone-400 italic">[Paragraf khusus kegiatan, bila diisi leading]</p>
                        <p class="mt-1 text-justify indent-4">{{ \App\Support\Kegiatan\KalimatKak::latarBelakangKegiatan($contoh, $ta) }}</p>
                    </div>
                    <div>
                        <p class="font-bold">2. Dasar Hukum</p>
                        <ol class="mt-1 list-decimal space-y-0.5 pl-5">
                            @foreach ($dasarHukum as $butir)
                                <li>{{ $butir['teks'] }}</li>
                            @endforeach
                        </ol>
                    </div>
                </div>
                <dl class="mt-4 space-y-1.5 text-xs" aria-label="Variabel template">
                    @foreach (['latar_belakang' => 'Paragraf baku + kalimat penutup', 'dasar_hukum' => 'Daftar bernomor'] as $variabel => $arti)
                        <div class="flex items-baseline justify-between gap-2">
                            <dt><code class="rounded bg-stone-100 px-1 py-0.5 font-mono text-[11px] text-stone-700">${{ '{'.$variabel.'}' }}</code></dt>
                            <dd class="text-right text-stone-500">{{ $arti }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>

            @if ($kesiapan)
                <section class="rounded-2xl bg-white p-5 shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-kesiapan-dasar" data-testid="kesiapan-dasar-hukum">
                    <h2 id="judul-kesiapan-dasar" class="text-sm font-semibold text-stone-900">Kesiapan TA {{ $tahunAktif }}</h2>
                    <p class="mt-2 flex items-start gap-2 text-sm" data-siap="{{ $kesiapan['siap'] ? 'ya' : 'tidak' }}">
                        <x-ui.icon :name="$kesiapan['siap'] ? 'check-circle' : 'alert'" @class(['mt-0.5 size-4 shrink-0', 'text-brand-600' => $kesiapan['siap'], 'text-gold-600' => ! $kesiapan['siap']]) />
                        <span class="min-w-0">
                            <span class="block font-medium text-stone-800">{{ $kesiapan['judul'] }}</span>
                            <span class="block text-xs text-stone-500">{{ $kesiapan['keterangan'] }}</span>
                        </span>
                    </p>
                </section>
            @endif
        </aside>
    </div>
</div>
