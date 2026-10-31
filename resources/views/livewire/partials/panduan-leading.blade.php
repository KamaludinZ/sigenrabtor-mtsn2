{{--
    Panduan tahapan kegiatan untuk akun leading (App\Support\Akun\PanduanLangkah::leading()): delapan tahap dari membuat
    kegiatan sampai final, untuk kegiatan yang dipandu (pilihan `kegiatanPanduan`, bawaan = yang paling perlu perhatian).
    Tiap tahap: status, apa yang dilakukan (petunjuk), yang masih kurang, dan tombol menuju tempat mengerjakannya; tahap
    sekarang/perlu perbaikan terbuka, lainnya bisa dibuka. Tanpa kegiatan: tahap 1 mengarah ke Buat kegiatan/Data Pagu.
--}}
@php
    $fokus = $panduan['fokus'];
    $labelTahap = collect(\App\Support\Akun\PanduanLangkah::TAHAP)->map(fn (array $t) => $t[0]);
    $sebaran = collect($panduan['sebaran'])->filter();
@endphp

<section class="mt-6 rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-panduan-leading"
         data-testid="panduan-leading" data-fokus="{{ $fokus['id'] ?? '' }}" data-sekarang="{{ $fokus['sekarang'] ?? ($fokus ? 'selesai' : 'identitas') }}">
    <div class="flex flex-col gap-3 border-b border-stone-100 px-5 py-4 sm:flex-row sm:items-start sm:justify-between sm:px-6">
        <div class="min-w-0">
            <h2 id="judul-panduan-leading" class="text-base font-semibold text-stone-900">Panduan menyusun dokumen kegiatan</h2>
            <p class="mt-0.5 text-sm text-stone-500">Ikuti delapan tahap berikut dari membuat kegiatan sampai dokumen final. Tahap yang sedang dikerjakan terbuka beserta langkah dan kekurangannya.</p>
        </div>
        @if (count($panduan['pilihan']) > 1)
            <div class="w-full shrink-0 sm:w-72">
                <label for="pilih-kegiatan-panduan" class="mb-1 block text-xs font-semibold text-stone-500">Kegiatan yang dipandu</label>
                <select id="pilih-kegiatan-panduan" class="form-select w-full py-1.5 text-sm" wire:model.live="kegiatanPanduan" data-testid="pilih-kegiatan-panduan">
                    @foreach ($panduan['pilihan'] as $p)
                        <option value="{{ $p['id'] }}" @selected($fokus && $p['id'] === $fokus['id'])>{{ $p['nama'] }} — {{ $p['tahap'] }}</option>
                    @endforeach
                </select>
            </div>
        @endif
    </div>

    <div class="space-y-3 px-5 py-4 sm:px-6">
        @if ($fokus)
            <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                <a href="{{ route('kegiatan.edit', $fokus['id']) }}" wire:navigate class="min-w-0 truncate text-sm font-semibold text-stone-900 hover:text-brand-700" data-testid="kegiatan-panduan">{{ $fokus['nama'] }}</a>
                <x-ui.status-badge :status="$fokus['status']" />
                <div class="flex min-w-40 flex-1 items-center gap-2" aria-label="Kemajuan {{ $fokus['persen'] }} persen">
                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-stone-100"><div class="h-full rounded-full bg-brand-600" style="width: {{ $fokus['persen'] }}%"></div></div>
                    <span class="text-xs text-stone-500 tabular-nums" data-testid="persen-panduan">{{ $fokus['persen'] }}%</span>
                </div>
            </div>
            @if ($fokus['catatan'])
                <p class="flex items-start gap-2 rounded-lg bg-gold-50 px-3 py-2 text-sm text-gold-900 ring-1 ring-gold-200" data-testid="catatan-panduan">
                    <x-ui.icon name="alert" class="mt-0.5 size-4 shrink-0" /> <span><span class="font-semibold">Catatan admin:</span> {{ $fokus['catatan'] }}</span>
                </p>
            @endif
        @else
            <div class="flex flex-col gap-3 rounded-xl bg-stone-50 px-4 py-3 ring-1 ring-stone-200 sm:flex-row sm:items-center sm:justify-between" data-testid="panduan-tanpa-kegiatan">
                <p class="text-sm text-stone-600">Belum ada kegiatan TA {{ $panduan['tahun'] }}. Mulai dari tahap 1: buat kegiatan, atau ambil dari Data Pagu bila admin sudah mengisinya.</p>
                <div class="flex shrink-0 flex-wrap gap-2">
                    @if ($panduan['tautan_buat'])
                        <a href="{{ $panduan['tautan_buat'] }}" wire:navigate class="btn-primary" data-testid="panduan-buat-kegiatan"><x-ui.icon name="plus" class="size-4" /> Buat kegiatan</a>
                    @endif
                    @if ($panduan['tautan_pagu'])
                        <a href="{{ $panduan['tautan_pagu'] }}" wire:navigate class="btn-secondary" data-testid="panduan-data-pagu"><x-ui.icon name="wallet" class="size-4" /> Data Pagu</a>
                    @endif
                </div>
            </div>
        @endif

        @if ($panduan['jumlah_kegiatan'] > 1 && $sebaran->isNotEmpty())
            <p class="flex flex-wrap items-center gap-1.5 text-xs text-stone-500" data-testid="sebaran-tahap">
                <span>Kegiatan Anda per tahap:</span>
                @foreach ($sebaran as $kunci => $jumlah)
                    <span class="rounded-full bg-stone-100 px-2 py-0.5 text-stone-700" data-kunci="{{ $kunci }}">{{ $kunci === 'selesai' ? 'Final' : $labelTahap[$kunci] }} · {{ $jumlah }}</span>
                @endforeach
            </p>
        @endif
    </div>

    <ol class="border-t border-stone-100 px-3 py-2 sm:px-4" wire:key="tahap-panduan-{{ $fokus['id'] ?? 'baru' }}">
        @foreach ($panduan['tahap'] as $t)
            @php
                $aktif = in_array($t['status'], ['sekarang', 'perbaiki'], true);
            @endphp
            <li class="relative" data-testid="tahap-panduan" data-kunci="{{ $t['kunci'] }}" data-status="{{ $t['status'] }}" @if ($aktif) aria-current="step" @endif
                x-data="{ buka: {{ $aktif ? 'true' : 'false' }} }">
                @unless ($loop->last)
                    <span @class(['absolute top-10 bottom-0 left-[1.4rem] w-px', 'bg-brand-300' => $t['status'] === 'selesai', 'bg-stone-200' => $t['status'] !== 'selesai'])  aria-hidden="true"></span>
                @endunless
                <button type="button" class="flex w-full items-start gap-3 rounded-xl px-2 py-2 text-left hover:bg-stone-50" x-on:click="buka = ! buka" x-bind:aria-expanded="buka" data-testid="judul-tahap">
                    <span @class([
                        'relative z-10 flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold tabular-nums',
                        'bg-brand-600 text-white' => $t['status'] === 'selesai',
                        'bg-white text-brand-800 ring-2 ring-brand-600' => $t['status'] === 'sekarang',
                        'bg-gold-500 text-white' => $t['status'] === 'perbaiki',
                        'bg-stone-100 text-stone-500' => $t['status'] === 'belum',
                    ])>
                        @if ($t['status'] === 'selesai')
                            <x-ui.icon name="check" class="size-4" />
                        @elseif ($t['status'] === 'perbaiki')
                            <x-ui.icon name="alert" class="size-4" />
                        @else
                            {{ $t['nomor'] }}
                        @endif
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="flex flex-wrap items-center gap-x-2 text-sm font-semibold text-stone-900">
                            {{ $t['judul'] }}
                            <span @class([
                                'text-xs font-medium',
                                'text-brand-700' => in_array($t['status'], ['selesai', 'sekarang'], true),
                                'text-gold-800' => $t['status'] === 'perbaiki',
                                'text-stone-400' => $t['status'] === 'belum',
                            ])>{{ \App\Support\Akun\PanduanLangkah::STATUS[$t['status']] }}</span>
                        </span>
                        <span class="mt-0.5 block text-sm text-stone-500">{{ $t['ringkas'] }}</span>
                    </span>
                    <x-ui.icon name="chevron-down" class="mt-1 size-4 shrink-0 text-stone-400 transition" x-bind:class="{ 'rotate-180': buka }" />
                </button>
                <div class="pb-3 pl-12 pr-2" x-show="buka" @unless ($aktif) x-cloak @endunless data-testid="isi-tahap">
                    <ol class="list-decimal space-y-1 pl-4 text-sm text-stone-700">
                        @foreach ($t['petunjuk'] as $p)
                            <li>{{ $p }}</li>
                        @endforeach
                    </ol>
                    @if ($t['kurang'] !== [])
                        <div class="mt-2 rounded-lg bg-gold-50 px-3 py-2 text-sm text-gold-900 ring-1 ring-gold-200" data-testid="kurang-tahap">
                            <p class="font-semibold">Masih kurang:</p>
                            <ul class="mt-0.5 list-disc pl-4">
                                @foreach ($t['kurang'] as $k)
                                    <li>{{ $k }}</li>
                                @endforeach
                                @if ($t['kurang_lainnya'] > 0)
                                    <li>dan {{ $t['kurang_lainnya'] }} lainnya</li>
                                @endif
                            </ul>
                        </div>
                    @endif
                    @if ($t['tautan'])
                        <a href="{{ $t['tautan'] }}" wire:navigate @class(['mt-2 inline-flex', 'btn-primary' => $aktif, 'btn-secondary' => ! $aktif]) data-testid="buka-tahap">
                            {{ $t['label_tautan'] }} <x-ui.icon name="chevron-right" class="size-4" />
                        </a>
                    @endif
                </div>
            </li>
        @endforeach
    </ol>
</section>
