{{--
    Panduan langkah persiapan tahun anggaran untuk admin (App\Support\Akun\PanduanLangkah::admin()): urutan yang harus
    dilengkapi sebelum leading menyusun kegiatan — langkah wajib dulu lalu opsional; langkah berikutnya disorot. Selama
    belum lengkap daftarnya terbuka; sesudah lengkap diringkas (bisa dibuka lagi).
--}}
<section class="mt-6 rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-panduan-admin"
         data-testid="panduan-admin" data-lengkap="{{ $panduan['lengkap'] ? '1' : '0' }}" data-wajib-siap="{{ $panduan['wajib_siap'] }}" data-wajib="{{ $panduan['wajib'] }}"
         x-data="{ buka: {{ $panduan['lengkap'] ? 'false' : 'true' }} }">
    <div class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
        <div class="min-w-0">
            <h2 id="judul-panduan-admin" class="flex flex-wrap items-center gap-2 text-base font-semibold text-stone-900">
                Langkah persiapan TA {{ $panduan['tahun'] }}
                @if ($panduan['lengkap'])
                    <span class="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2 py-0.5 text-xs font-semibold text-brand-800 ring-1 ring-brand-200"><x-ui.icon name="check" class="size-3" /> Siap dipakai leading</span>
                @else
                    <span class="rounded-full bg-gold-50 px-2 py-0.5 text-xs font-semibold text-gold-800 ring-1 ring-gold-200">{{ $panduan['wajib'] - $panduan['wajib_siap'] }} langkah wajib belum selesai</span>
                @endif
            </h2>
            <p class="mt-0.5 text-sm text-stone-500">
                {{ $panduan['lengkap'] ? 'Semua langkah wajib selesai — leading sudah bisa menyusun kegiatan. Langkah opsional menyempurnakan dokumen.' : 'Lengkapi langkah wajib berurutan agar dokumen leading tercetak lengkap tanpa cap DRAF dari pengaturan.' }}
            </p>
        </div>
        <div class="flex shrink-0 items-center gap-3">
            <div class="w-40" aria-hidden="true">
                <div class="h-2 overflow-hidden rounded-full bg-stone-100"><div class="h-full rounded-full bg-brand-600" style="width: {{ $panduan['persen'] }}%"></div></div>
                <p class="mt-1 text-xs text-stone-500 tabular-nums">{{ $panduan['siap'] }}/{{ $panduan['jumlah'] }} langkah selesai</p>
            </div>
            <button type="button" class="btn-ghost px-2 py-1 text-xs" x-on:click="buka = ! buka" x-bind:aria-expanded="buka" aria-controls="daftar-langkah-admin" data-testid="buka-langkah-admin">
                <x-ui.icon name="chevron-down" class="size-4 transition" x-bind:class="{ 'rotate-180': buka }" />
                <span x-text="buka ? 'Ringkas' : 'Lihat langkah'">{{ $panduan['lengkap'] ? 'Lihat langkah' : 'Ringkas' }}</span>
            </button>
        </div>
    </div>

    <ol id="daftar-langkah-admin" class="divide-y divide-stone-100 border-t border-stone-100" x-show="buka" @if ($panduan['lengkap']) x-cloak @endif>
        @foreach ($panduan['langkah'] as $l)
            <li @class(['flex flex-col gap-3 px-5 py-3.5 sm:flex-row sm:items-center sm:px-6', 'bg-brand-50/50' => $l['status'] === 'sekarang'])
                data-testid="langkah-admin" data-kunci="{{ $l['kunci'] }}" data-status="{{ $l['status'] }}" @if ($l['status'] === 'sekarang') aria-current="step" @endif>
                <div class="flex min-w-0 flex-1 items-start gap-3">
                    <span @class([
                        'flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold tabular-nums',
                        'bg-brand-600 text-white' => $l['status'] === 'selesai',
                        'bg-white text-brand-800 ring-2 ring-brand-600' => $l['status'] === 'sekarang',
                        'bg-stone-100 text-stone-500' => $l['status'] === 'belum',
                    ])>
                        @if ($l['status'] === 'selesai')
                            <x-ui.icon name="check" class="size-4" /><span class="sr-only">Selesai:</span>
                        @else
                            {{ $l['nomor'] }}
                        @endif
                    </span>
                    <div class="min-w-0">
                        <p class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm font-semibold text-stone-900">
                            {{ $l['judul'] }}
                            <span @class(['rounded px-1.5 py-0.5 text-[11px] font-medium', 'bg-stone-100 text-stone-600' => $l['wajib'], 'bg-white text-stone-500 ring-1 ring-stone-200' => ! $l['wajib']])>{{ $l['wajib'] ? 'Wajib' : 'Opsional' }}</span>
                            @if ($l['status'] === 'sekarang')
                                <span class="text-xs font-semibold text-brand-700">Langkah berikutnya</span>
                            @endif
                        </p>
                        <p @class(['mt-0.5 text-sm', 'text-stone-600' => $l['siap'], 'text-gold-800' => ! $l['siap'] && $l['wajib'], 'text-stone-500' => ! $l['siap'] && ! $l['wajib']]) data-testid="keterangan-langkah">{{ $l['keterangan'] }}</p>
                        @unless ($l['siap'])
                            <p class="mt-0.5 text-xs text-stone-500">{{ $l['alasan'] }}</p>
                        @endunless
                    </div>
                </div>
                @if ($l['tautan'])
                    <a href="{{ $l['tautan'] }}" wire:navigate @class(['ml-10 shrink-0 self-start sm:ml-0 sm:self-center', 'btn-primary' => $l['status'] === 'sekarang', 'btn-secondary' => $l['status'] === 'belum', 'btn-ghost text-sm' => $l['status'] === 'selesai'])
                       data-testid="buka-langkah">
                        {{ $l['status'] === 'selesai' ? 'Lihat' : $l['label_tautan'] }} <x-ui.icon name="chevron-right" class="size-4" />
                    </a>
                @endif
            </li>
        @endforeach
    </ol>
</section>
