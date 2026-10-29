@use('App\Livewire\Pengaturan\FormatSeragam')
@use('App\Support\Dokumen\FormatSeragam as AturanFormat')

@php
    $profil = $this->profil;
    $ringkasan = $this->ringkasan;
    $unsur = [
        'kop' => ['building', 'Kop & profil madrasah', 'Nama, alamat, telepon, kota, visi, dan logo di kop semua dokumen.', route('pengaturan.madrasah.index'), 'Atur profil'],
        'susunan' => ['layers', 'Susunan baku', 'Urutan bagian KAK/TOR dan isi RAB sesuai format madrasah.', null, 'Lihat susunan'],
        'penomoran' => ['tag', 'Penomoran otomatis', 'Nomor dokumen mengikuti aturan per jenis dan tahun anggaran aktif.', null, 'Lihat aturan'],
        'template' => ['file-text', 'Pilihan template', 'Variasi template sesuai jenis kegiatan dan leading.', null, 'Lihat pilihan'],
    ];
@endphp

<div class="mx-auto max-w-7xl">
    <x-pengaturan.kepala judul="Format Seragam"
                         deskripsi="Semua KAK/TOR, RAB, dan set dokumen mengikuti format resmi madrasah yang sama: kop, susunan bagian, penomoran, dan template. Berubah di sini, berlaku di dokumen semua kegiatan.">
        <x-slot:tab>
            <nav class="-mb-px flex gap-1 overflow-x-auto border-b border-stone-200" aria-label="Bagian format seragam" data-testid="tab-format">
                @foreach (FormatSeragam::TAB as $kunci => $label)
                    @if ($kunci === $tab)
                        <span aria-current="page" class="flex shrink-0 items-center gap-2 border-b-2 border-brand-700 px-3 py-2.5 text-sm font-semibold text-brand-800" data-tab="{{ $kunci }}">{{ $label }}</span>
                    @else
                        <button type="button" wire:click="$set('tab', '{{ $kunci }}')" class="flex shrink-0 items-center gap-2 border-b-2 border-transparent px-3 py-2.5 text-sm font-medium text-stone-500 hover:border-stone-300 hover:text-stone-800" data-tab="{{ $kunci }}">{{ $label }}</button>
                    @endif
                @endforeach
            </nav>
        </x-slot:tab>
    </x-pengaturan.kepala>

    @if ($tab === 'ringkasan')
        <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <div class="grid min-w-0 gap-4 sm:grid-cols-2" data-testid="ringkasan-format">
                @foreach ($unsur as $kunci => [$ikon, $judul, $keterangan, $tautan, $aksi])
                    <section class="flex flex-col rounded-2xl bg-white p-5 shadow-xs ring-1 ring-stone-200" data-unsur="{{ $kunci }}" data-status="{{ $ringkasan[$kunci]['status'] }}">
                        <div class="flex items-start gap-3">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-700 ring-1 ring-brand-100"><x-ui.icon :name="$ikon" class="size-4" /></span>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="text-sm font-semibold text-stone-900">{{ $judul }}</h2>
                                    @if ($ringkasan[$kunci]['status'] === 'siap')
                                        <span class="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2 py-0.5 text-[11px] font-semibold text-brand-800 ring-1 ring-brand-200"><x-ui.icon name="check" class="size-3" /> Siap</span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-gold-50 px-2 py-0.5 text-[11px] font-semibold text-gold-800 ring-1 ring-gold-200"><x-ui.icon name="alert" class="size-3" /> Perlu dilengkapi</span>
                                    @endif
                                </div>
                                <p class="mt-1 text-xs text-stone-500">{{ $keterangan }}</p>
                            </div>
                        </div>
                        <p class="mt-3 flex-1 text-sm text-stone-700" data-testid="status-{{ $kunci }}">{{ $ringkasan[$kunci]['teks'] }}</p>
                        <div class="mt-4">
                            @if ($tautan)
                                <a href="{{ $tautan }}" wire:navigate class="btn-secondary px-3 py-1.5 text-xs">{{ $aksi }} <x-ui.icon name="chevron-right" class="size-3.5" /></a>
                            @else
                                <button type="button" wire:click="$set('tab', '{{ $kunci }}')" class="btn-secondary px-3 py-1.5 text-xs">{{ $aksi }} <x-ui.icon name="chevron-right" class="size-3.5" /></button>
                            @endif
                        </div>
                    </section>
                @endforeach
            </div>

            <aside class="min-w-0 space-y-4">
                <section class="rounded-2xl bg-white p-5 shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-kop-format" data-testid="kop-format">
                    <h2 id="judul-kop-format" class="text-sm font-semibold text-stone-900">Kop dokumen</h2>
                    <p class="mt-0.5 text-xs text-stone-500">Tercetak sama di cover, KAK/TOR, RAB, dan lembar pengesahan.</p>
                    <div class="mt-4 rounded-lg bg-white p-3 shadow-xs ring-1 ring-stone-200">
                        <div class="flex items-center gap-3 border-b-[3px] border-double border-stone-800 pb-2">
                            @if ($profil['logo'])
                                <img src="{{ $profil['logo'] }}" alt="" class="size-10 shrink-0 object-contain" data-testid="kop-format-logo">
                            @else
                                <div class="flex size-10 shrink-0 items-center justify-center rounded border border-dashed border-stone-300 text-[8px] text-stone-400" aria-hidden="true">LOGO</div>
                            @endif
                            <div class="min-w-0 flex-1 text-center font-serif leading-tight">
                                <p class="text-[11px] font-bold tracking-wide text-stone-900 uppercase">{{ $profil['nama'] ?? '[Nama madrasah]' }}</p>
                                <p @class(['text-[9px]', 'text-stone-700' => $profil['alamat'], 'text-gold-700 italic' => ! $profil['alamat']])>{{ $profil['alamat'] ?? '[Alamat]' }}</p>
                                <p @class(['text-[9px]', 'text-stone-700' => $profil['telepon'], 'text-gold-700 italic' => ! $profil['telepon']])>Telepon {{ $profil['telepon'] ?? '[telepon]' }} · {{ $profil['kota'] ?? '[kota]' }}</p>
                            </div>
                        </div>
                    </div>
                </section>
                <section class="rounded-2xl bg-stone-50 p-5 ring-1 ring-stone-200">
                    <h2 class="text-sm font-semibold text-stone-900">Mengapa seragam?</h2>
                    <ul class="mt-2 space-y-1.5 text-xs text-stone-600" role="list">
                        <li class="flex gap-2"><x-ui.icon name="check" class="mt-0.5 size-3.5 shrink-0 text-brand-700" /> Satu data kegiatan menjadi sumber cover, KAK/TOR, RAB, dan pengesahan.</li>
                        <li class="flex gap-2"><x-ui.icon name="check" class="mt-0.5 size-3.5 shrink-0 text-brand-700" /> Perubahan format di sini langsung berlaku untuk dokumen berikutnya.</li>
                        <li class="flex gap-2"><x-ui.icon name="check" class="mt-0.5 size-3.5 shrink-0 text-brand-700" /> Dokumen lama di Riwayat Dokumen tetap tercatat apa adanya.</li>
                    </ul>
                </section>
            </aside>
        </div>
    @elseif ($tab === 'susunan')
        @php
            $susunan = $this->susunan;
        @endphp
        <div class="mt-6 grid gap-6 lg:grid-cols-2" data-testid="susunan-format">
            <section class="min-w-0 rounded-2xl bg-white p-5 shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-susunan-kak">
                <h2 id="judul-susunan-kak" class="text-sm font-semibold text-stone-900">KAK/TOR — {{ count($susunan['kak']) }} bagian</h2>
                <p class="mt-0.5 text-xs text-stone-500">Urutan baku; isi tiap bagian disusun otomatis dari data kegiatan.</p>
                <ol class="mt-4 divide-y divide-stone-100 rounded-xl ring-1 ring-stone-200" role="list" data-testid="susunan-kak">
                    @foreach ($susunan['kak'] as $bagian)
                        <li class="flex items-center gap-3 px-3 py-2.5 text-sm" data-bagian="{{ $bagian['nomor'] }}">
                            <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-stone-100 text-xs font-semibold text-stone-700 tabular-nums">{{ $bagian['nomor'] }}</span>
                            <span class="font-medium text-stone-800">{{ $bagian['judul'] }}</span>
                        </li>
                    @endforeach
                </ol>
            </section>
            <section class="min-w-0 rounded-2xl bg-white p-5 shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-susunan-rab">
                <h2 id="judul-susunan-rab" class="text-sm font-semibold text-stone-900">RAB — kepala & tabel rincian</h2>
                <p class="mt-0.5 text-xs text-stone-500">Satu RAB per sumber dana; total & terbilang dihitung otomatis.</p>
                <h3 class="mt-4 text-xs font-semibold text-stone-500">Kepala ({{ count($susunan['rab_kepala']) }} baris)</h3>
                <ul class="mt-1.5 flex flex-wrap gap-1.5" role="list" data-testid="susunan-rab-kepala">
                    @foreach ($susunan['rab_kepala'] as $label)
                        <li class="rounded-md bg-stone-100 px-2 py-1 text-xs text-stone-700">{{ $label }}</li>
                    @endforeach
                </ul>
                <h3 class="mt-4 text-xs font-semibold text-stone-500">Kolom tabel ({{ count($susunan['rab_kolom']) }})</h3>
                <div class="mt-1.5 overflow-x-auto">
                    <table class="w-full min-w-[28rem] border-collapse text-xs" data-testid="susunan-rab-kolom">
                        <tr>
                            @foreach ($susunan['rab_kolom'] as $kolom)
                                <th scope="col" class="border border-stone-300 bg-stone-50 px-2 py-1.5 font-semibold text-stone-700">{{ $kolom }}</th>
                            @endforeach
                        </tr>
                    </table>
                </div>
                <p class="mt-3 text-xs text-stone-500">Diakhiri TOTAL KESELURUHAN, terbilang, Mekanisme Pembayaran (bila bertermin), dan lembar pengesahan.</p>
            </section>
        </div>
    @elseif ($tab === 'penomoran')
        <section class="mt-6 rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-penomoran" data-testid="penomoran-format">
            <header class="border-b border-stone-100 px-5 py-4">
                <h2 id="judul-penomoran" class="text-sm font-semibold text-stone-900">Aturan nomor dokumen per jenis</h2>
                <p class="mt-0.5 text-xs text-stone-500">Nomor diberikan otomatis saat dokumen final; nomor urut dimulai lagi tiap tahun anggaran.</p>
            </header>
            <div class="hidden md:block">
                <table class="w-full table-fixed text-left text-sm">
                    <colgroup><col class="w-[20%]"><col class="w-[34%]"><col class="w-[26%]"><col class="w-[10%]"><col class="w-[10%]"></colgroup>
                    <thead class="border-b border-stone-100 text-xs font-semibold text-stone-500">
                        <tr>
                            <th scope="col" class="px-5 py-2.5">Jenis dokumen</th>
                            <th scope="col" class="px-3 py-2.5">Pola</th>
                            <th scope="col" class="px-3 py-2.5">Nomor berikutnya</th>
                            <th scope="col" class="px-3 py-2.5 text-right">Terakhir</th>
                            <th scope="col" class="px-5 py-2.5 text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($this->penomoran as $aturan)
                            <tr data-testid="aturan-nomor" data-jenis="{{ $aturan['jenis'] }}">
                                <td class="px-5 py-3 font-medium text-stone-800">{{ $aturan['label'] }}</td>
                                <td class="px-3 py-3"><code class="rounded bg-stone-100 px-1.5 py-0.5 font-mono text-xs break-all text-stone-700">{{ $aturan['pola'] }}</code></td>
                                <td class="px-3 py-3 font-mono text-xs break-all text-stone-800" data-testid="contoh-nomor">{{ $aturan['contoh'] }}</td>
                                <td class="px-3 py-3 text-right text-stone-600 tabular-nums">{{ $aturan['terakhir'] }}</td>
                                <td class="px-5 py-3 text-right">
                                    <span @class(['rounded-full px-2 py-0.5 text-[11px] font-semibold', 'bg-brand-50 text-brand-800' => $aturan['aktif'], 'bg-stone-100 text-stone-500' => ! $aturan['aktif']])>{{ $aturan['aktif'] ? 'Otomatis' : 'Tidak dinomori' }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <ul class="divide-y divide-stone-100 md:hidden" role="list">
                @foreach ($this->penomoran as $aturan)
                    <li class="px-4 py-3.5" data-testid="kartu-aturan-nomor" data-jenis="{{ $aturan['jenis'] }}">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-sm font-semibold text-stone-900">{{ $aturan['label'] }}</p>
                            <span @class(['rounded-full px-2 py-0.5 text-[11px] font-semibold', 'bg-brand-50 text-brand-800' => $aturan['aktif'], 'bg-stone-100 text-stone-500' => ! $aturan['aktif']])>{{ $aturan['aktif'] ? 'Otomatis' : 'Tidak dinomori' }}</span>
                        </div>
                        <p class="mt-1 font-mono text-xs break-all text-stone-600">{{ $aturan['pola'] }}</p>
                        <p class="mt-1 text-xs text-stone-500">Berikutnya <span class="font-mono font-semibold text-stone-800">{{ $aturan['contoh'] }}</span> · terakhir {{ $aturan['terakhir'] }}</p>
                    </li>
                @endforeach
            </ul>
            <footer class="border-t border-stone-100 px-5 py-4">
                <h3 class="text-xs font-semibold text-stone-500">Penanda pola</h3>
                <dl class="mt-2 grid gap-x-6 gap-y-1.5 text-xs sm:grid-cols-2" data-testid="penanda-nomor">
                    @foreach (AturanFormat::TOKEN as $penanda => $arti)
                        <div class="flex gap-2">
                            <dt><code class="rounded bg-stone-100 px-1 py-0.5 font-mono text-[11px] text-stone-700">{{ $penanda }}</code></dt>
                            <dd class="text-stone-500">{{ $arti }}</dd>
                        </div>
                    @endforeach
                </dl>
            </footer>
        </section>
    @else
        @php
            $template = $this->template;
        @endphp
        <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]" data-testid="template-format">
            <section class="min-w-0 rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-template-jenis">
                <header class="border-b border-stone-100 px-5 py-4">
                    <h2 id="judul-template-jenis" class="text-sm font-semibold text-stone-900">Template per jenis kegiatan</h2>
                    <p class="mt-0.5 text-xs text-stone-500">Jenis kegiatan ditebak dari nama kegiatan; tanpa template khusus dokumen memakai susunan baku & cover default.</p>
                </header>
                <ul class="divide-y divide-stone-100" role="list">
                    @foreach ($template['jenis'] as $t)
                        <li class="grid gap-2 px-5 py-3.5 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)] sm:items-center" data-testid="template-jenis" data-jenis="{{ $t['kunci'] }}">
                            <p class="text-sm font-medium text-stone-800">{{ $t['label'] }}</p>
                            <p class="text-xs text-stone-600"><span class="text-stone-400 sm:hidden">KAK/TOR: </span>{{ $t['kak'] ?? 'Susunan baku' }}</p>
                            <p class="text-xs text-stone-600"><span class="text-stone-400 sm:hidden">Cover: </span>{{ $t['cover'] ?? 'Cover default' }}</p>
                        </li>
                    @endforeach
                </ul>
            </section>
            <section class="min-w-0 rounded-2xl bg-white p-5 shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-template-leading">
                <h2 id="judul-template-leading" class="text-sm font-semibold text-stone-900">Khusus leading</h2>
                <p class="mt-0.5 text-xs text-stone-500">Mengalahkan template per jenis untuk leading itu.</p>
                <ul class="mt-3 space-y-2" role="list">
                    @forelse ($template['leading'] as $t)
                        <li class="rounded-xl bg-stone-50 px-3 py-2.5 text-xs ring-1 ring-stone-200" data-testid="template-leading" data-leading="{{ $t['leading'] }}">
                            <p class="font-semibold text-stone-800">Leading {{ $t['leading'] }} · {{ $t['nama_leading'] }}</p>
                            <p class="mt-0.5 text-stone-500">{{ $t['label_jenis'] }}</p>
                            <p class="mt-1 text-stone-700">KAK/TOR: {{ $t['kak'] ?? 'mengikuti jenis' }} · Cover: {{ $t['cover'] ?? 'mengikuti jenis' }}</p>
                        </li>
                    @empty
                        <li class="text-xs text-stone-500">Belum ada template khusus leading.</li>
                    @endforelse
                </ul>
            </section>
        </div>
    @endif
</div>
