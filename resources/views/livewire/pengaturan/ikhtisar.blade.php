@use('App\Support\Pengaturan\BagianPengaturan')

@php
    $kesiapan = $this->kesiapan;
    $wajib = $kesiapan->jumlahWajib();
    $wajibSiap = $kesiapan->jumlahWajibSiap();
    $persen = $wajib === 0 ? 100 : (int) round($wajibSiap / $wajib * 100);
@endphp

<div class="mx-auto max-w-7xl">
    <x-pengaturan.kepala judul="Pengaturan" :remah="false"
                         deskripsi="Pusat pengaturan admin: tahun anggaran, data madrasah, master kode, template, dan AI yang dipakai seluruh dokumen. Perubahan di sini berlaku untuk semua leading." />

    <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        {{-- Kesiapan tahun anggaran (di layar sempit tampil lebih dulu: inilah yang perlu dikerjakan). --}}
        <aside class="lg:order-last">
            <section class="rounded-2xl bg-white p-5 shadow-xs ring-1 ring-stone-200 lg:sticky lg:top-24" aria-labelledby="judul-kesiapan" data-testid="kesiapan-pengaturan">
                <h2 id="judul-kesiapan" class="text-sm font-semibold text-stone-900">Kesiapan Tahun Anggaran {{ $kesiapan->tahun }}</h2>
                <p class="mt-0.5 text-xs text-stone-500">Lengkapi yang wajib sebelum leading mulai mengisi kegiatan.</p>
                <div class="mt-3 flex items-center gap-3">
                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-stone-100" role="progressbar" aria-label="Butir wajib yang sudah siap"
                         aria-valuemin="0" aria-valuemax="{{ $wajib }}" aria-valuenow="{{ $wajibSiap }}">
                        <div class="h-full rounded-full bg-brand-600" style="width: {{ $persen }}%"></div>
                    </div>
                    <span class="text-xs font-semibold text-stone-700 tabular-nums" data-testid="jumlah-wajib-siap">{{ $wajibSiap }}/{{ $wajib }} wajib siap</span>
                </div>

                <ol class="mt-4 space-y-3">
                    @foreach ($kesiapan->butir as $butir)
                        @php
                            $bagianButir = BagianPengaturan::cari($butir['bagian']);
                        @endphp
                        <li class="flex items-start gap-2.5" data-kesiapan="{{ $butir['kunci'] }}" data-siap="{{ $butir['siap'] ? 'ya' : 'tidak' }}">
                            @if ($butir['siap'])
                                <x-ui.icon name="check-circle" class="mt-0.5 size-4 shrink-0 text-brand-600" />
                            @elseif ($butir['wajib'])
                                <x-ui.icon name="alert" class="mt-0.5 size-4 shrink-0 text-gold-600" />
                            @else
                                <x-ui.icon name="circle" class="mt-0.5 size-4 shrink-0 text-stone-300" />
                            @endif
                            <div class="min-w-0">
                                <p class="flex flex-wrap items-center gap-1.5 text-sm font-medium text-stone-800">
                                    <a href="{{ $bagianButir['url'] ?? '#bagian-'.$butir['bagian'] }}" @if ($bagianButir['tersedia'] ?? false) wire:navigate @endif
                                       class="hover:text-brand-700">{{ $butir['judul'] }}</a>
                                    @unless ($butir['wajib'])
                                        <span class="rounded-full bg-stone-100 px-1.5 py-px text-[10px] font-semibold text-stone-500">Opsional</span>
                                    @endunless
                                </p>
                                <p class="text-xs text-stone-500">{{ $butir['keterangan'] }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </section>
        </aside>

        {{-- Peta bagian pengaturan per kelompok --}}
        <div class="min-w-0 space-y-8">
            @foreach (BagianPengaturan::KELOMPOK as $kunciKelompok => $judulKelompok)
                <section aria-labelledby="kelompok-{{ $kunciKelompok }}">
                    <h2 id="kelompok-{{ $kunciKelompok }}" class="text-[11px] font-semibold tracking-wider text-stone-400 uppercase">{{ $judulKelompok }}</h2>
                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                        @foreach ($this->kelompok[$kunciKelompok] ?? [] as $bagian)
                            @php
                                $butirBagian = $kesiapan->untukBagian($bagian['kunci']);
                                $perlu = count(array_filter($butirBagian, fn ($b) => $b['wajib'] && ! $b['siap']));
                                $semuaSiap = $butirBagian !== [] && count(array_filter($butirBagian, fn ($b) => $b['siap'])) === count($butirBagian);
                            @endphp
                            <article id="bagian-{{ $bagian['kunci'] }}" class="flex scroll-mt-24 flex-col rounded-2xl bg-white p-5 shadow-xs ring-1 ring-stone-200"
                                     data-testid="bagian-{{ $bagian['kunci'] }}" data-tersedia="{{ $bagian['tersedia'] ? 'ya' : 'tidak' }}" aria-labelledby="judul-bagian-{{ $bagian['kunci'] }}">
                                <header class="flex items-start gap-3">
                                    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-700 ring-1 ring-brand-100" aria-hidden="true">
                                        <x-ui.icon :name="$bagian['ikon']" class="size-5" />
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <h3 id="judul-bagian-{{ $bagian['kunci'] }}" class="text-sm font-semibold text-stone-900">{{ $bagian['judul'] }}</h3>
                                        @if ($perlu > 0)
                                            <span class="mt-1 inline-flex items-center gap-1 rounded-full bg-gold-50 px-2 py-0.5 text-[11px] font-semibold text-gold-700 ring-1 ring-gold-200" data-status="perlu">
                                                {{ $perlu }} perlu dilengkapi
                                            </span>
                                        @elseif ($semuaSiap)
                                            <span class="mt-1 inline-flex items-center gap-1 rounded-full bg-brand-50 px-2 py-0.5 text-[11px] font-semibold text-brand-800 ring-1 ring-brand-200" data-status="siap">
                                                <x-ui.icon name="check" class="size-3" /> Siap
                                            </span>
                                        @elseif ($butirBagian !== [])
                                            <span class="mt-1 inline-flex items-center gap-1 rounded-full bg-stone-100 px-2 py-0.5 text-[11px] font-semibold text-stone-600" data-status="opsional">
                                                Opsional · belum diatur
                                            </span>
                                        @endif
                                    </div>
                                </header>
                                <p class="mt-3 text-sm text-stone-600">{{ $bagian['ringkas'] }}</p>
                                <ul class="mt-3 space-y-1 text-xs text-stone-500">
                                    @foreach ($bagian['butir'] as $isi)
                                        <li class="flex items-start gap-2"><span class="mt-1.5 size-1 shrink-0 rounded-full bg-stone-300" aria-hidden="true"></span>{{ $isi }}</li>
                                    @endforeach
                                </ul>
                                <div class="mt-auto pt-4">
                                    <div class="flex items-center justify-between gap-2 border-t border-stone-100 pt-3">
                                        @if ($bagian['tersedia'])
                                            <a href="{{ $bagian['url'] }}" wire:navigate class="btn-secondary px-3 py-1.5 text-xs">
                                                Buka {{ $bagian['judul'] }}
                                                <x-ui.icon name="chevron-right" class="size-3.5" />
                                            </a>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 text-xs font-medium text-stone-400" title="Halaman ini sedang disiapkan">
                                                <x-ui.icon name="loader" class="size-3.5" /> Segera disiapkan
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endforeach

            <p class="flex items-start gap-2 rounded-xl bg-gold-50 px-4 py-3 text-xs text-gold-700 ring-1 ring-gold-200">
                <x-ui.icon name="info" class="mt-px size-4 shrink-0" />
                Pengaturan &amp; master berlaku per tahun anggaran. Mengganti tahun aktif tidak menghapus data tahun lain; master bisa disalin dari tahun sebelumnya.
            </p>
        </div>
    </div>
</div>
