@use('App\Livewire\Impor\ImporBertahap')
@use('App\Support\Format')

@php
    $langkah = ['unggah' => 'Unggah', 'periksa' => 'Periksa', 'proses' => 'Proses', 'selesai' => 'Laporan'];
    $urutan = array_flip(array_keys($langkah));
    $lencana = [
        'siap' => ['Siap', 'bg-brand-50 text-brand-800 ring-brand-200'],
        'berhasil' => ['Berhasil', 'bg-brand-50 text-brand-800 ring-brand-200'],
        'dilewati' => ['Dilewati', 'bg-stone-100 text-stone-700 ring-stone-300'],
        'gagal' => ['Gagal', 'bg-red-50 text-red-700 ring-red-200'],
        'dibatalkan' => ['Dibatalkan', 'bg-stone-100 text-stone-500 ring-stone-200'],
    ];
    $kolom = $this->kolom();
@endphp

{{--
    Alur impor bertahap bersama: Unggah → Periksa (pratinjau per baris) → Proses (bilah kemajuan + log) → Laporan.
    Dipakai komponen turunan App\Livewire\Impor\ImporBertahap. Opsional: $tautanTemplate, $ringkasanTotal (impor RAB),
    $tabelPratinjau (partial pengganti tabel pratinjau; di dalam x-data `saring`), $pratinjauTambahan (partial di bawah
    tabel pratinjau), $prosesSegera (tombol impor belum aktif).
--}}
<section class="rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" data-testid="impor-bertahap" data-tahap="{{ $tahap }}">
    <header class="flex flex-col gap-3 border-b border-stone-100 px-5 py-4 sm:flex-row sm:items-start sm:justify-between sm:px-6">
        <div class="min-w-0">
            <h2 class="text-base font-semibold text-stone-900">{{ $judul }}</h2>
            <p class="mt-0.5 max-w-2xl text-sm text-stone-500">{{ $keterangan }}</p>
        </div>
        <ol class="flex shrink-0 items-center gap-1.5 text-xs font-medium" aria-label="Langkah impor">
            @foreach ($langkah as $kunci => $label)
                @php $ke = $urutan[$kunci]; $kini = $urutan[$tahap]; @endphp
                <li @class([
                        'flex items-center gap-1 rounded-full px-2 py-0.5 ring-1 ring-inset',
                        'bg-brand-700 text-white ring-brand-700' => $ke === $kini,
                        'bg-brand-50 text-brand-800 ring-brand-200' => $ke < $kini,
                        'text-stone-400 ring-stone-200' => $ke > $kini,
                    ]) @if ($ke === $kini) aria-current="step" @endif>
                    <span class="tabular-nums">{{ $ke + 1 }}</span> {{ $label }}
                </li>
            @endforeach
        </ol>
    </header>

    {{-- wire:key per tahap: isi tahap diganti utuh, bukan ditambal, agar status Alpine tahap sebelumnya (x-show unggah) tidak terbawa. --}}
    <div class="px-5 py-5 sm:px-6" wire:key="tahap-{{ $tahap }}">
        @if ($tahap === 'unggah')
            <div x-data="{ unggah: false, persen: 0 }"
                 x-on:livewire-upload-start="unggah = true; persen = 0"
                 x-on:livewire-upload-progress="persen = $event.detail.progress"
                 x-on:livewire-upload-finish="unggah = false"
                 x-on:livewire-upload-error="unggah = false">
                <label for="berkas-impor"
                       class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-stone-300 px-6 py-8 text-center transition hover:border-brand-400 hover:bg-brand-50/40">
                    <x-ui.icon name="upload" class="size-8 text-stone-400" />
                    <span class="text-sm font-semibold text-stone-800">Pilih berkas Excel</span>
                    <span class="text-xs text-stone-500">.xlsx, .xls, atau .csv · maks. {{ ImporBertahap::MAKS_KB / 1024 }} MB</span>
                    <input type="file" id="berkas-impor" wire:model="berkas" accept=".xlsx,.xls,.csv" class="sr-only" data-testid="input-berkas-impor">
                </label>

                <div class="mt-3" x-show="unggah" x-cloak>
                    <div class="h-1.5 overflow-hidden rounded-full bg-stone-100"><div class="h-full rounded-full bg-brand-600 transition-all" x-bind:style="`width: ${persen}%`"></div></div>
                    <p class="mt-1 text-xs text-stone-500">Mengunggah… <span x-text="persen"></span>%</p>
                </div>
                <p class="mt-3 flex items-center gap-2 text-sm text-stone-600" wire:loading.flex wire:target="berkas">
                    <x-ui.icon name="loader" class="size-4 animate-spin" /> Membaca dan memeriksa setiap baris…
                </p>
            </div>

            @if ($galat)
                <p class="mt-4 flex items-start gap-2 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-200" role="alert" data-testid="galat-impor">
                    <x-ui.icon name="alert" class="mt-0.5 size-4 shrink-0" /> {{ $galat }}
                </p>
            @endif

            @if ($tautanTemplate ?? null)
                <p class="mt-4 text-xs text-stone-500">
                    Belum punya berkasnya?
                    <a href="{{ $tautanTemplate }}" class="font-semibold text-brand-700 hover:text-brand-800">Unduh template</a>, isi, lalu unggah di sini.
                </p>
            @endif
        @elseif ($tahap === 'periksa')
            @php
                $siap = $this->jumlah('siap');
                $gagal = $this->jumlah('gagal');
                $dilewati = $this->jumlah('dilewati');
            @endphp
            <div x-data="{ saring: '{{ $gagal > 0 ? 'gagal' : 'semua' }}' }">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-stone-600">
                        <span class="font-semibold text-stone-900">{{ $namaBerkas }}</span> · {{ count($baris) }} baris diperiksa
                    </p>
                    <div class="inline-flex rounded-lg bg-stone-100 p-0.5 text-xs font-semibold" role="tablist" aria-label="Saring baris">
                        @foreach (['semua' => 'Semua ('.count($baris).')', 'siap' => 'Siap ('.$siap.')', 'dilewati' => 'Dilewati ('.$dilewati.')', 'gagal' => 'Gagal ('.$gagal.')'] as $kunci => $label)
                            <button type="button" role="tab" x-on:click="saring = '{{ $kunci }}'" x-bind:aria-selected="saring === '{{ $kunci }}'"
                                    class="rounded-md px-2.5 py-1 text-stone-500 aria-selected:bg-white aria-selected:text-stone-900 aria-selected:shadow-xs"
                                    data-testid="saring-{{ $kunci }}">{{ $label }}</button>
                        @endforeach
                    </div>
                </div>

                @if ($tabelPratinjau ?? null)
                    @include($tabelPratinjau)
                @else
                    <div class="mt-3 max-h-[28rem] overflow-auto rounded-xl ring-1 ring-stone-200" data-testid="pratinjau-impor">
                        <table class="w-full text-left text-sm">
                            <thead class="sticky top-0 bg-stone-50 text-xs font-semibold text-stone-500">
                                <tr>
                                    <th scope="col" class="px-3 py-2 text-right">Baris</th>
                                    @foreach ($kolom as $label)
                                        <th scope="col" @class(['px-3 py-2', 'hidden md:table-cell' => ! $loop->first])>{{ $label }}</th>
                                    @endforeach
                                    <th scope="col" class="px-3 py-2">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-stone-100">
                                @foreach ($baris as $b)
                                    <tr data-status="{{ $b['status'] }}" x-show="saring === 'semua' || saring === '{{ $b['status'] }}'" class="align-top">
                                        <td class="px-3 py-2 text-right text-stone-400 tabular-nums">{{ $b['baris'] }}</td>
                                        @foreach (array_keys($kolom) as $kunci)
                                            <td @class(['px-3 py-2', 'font-medium text-stone-800' => $loop->first, 'hidden whitespace-nowrap text-stone-600 md:table-cell' => ! $loop->first])>
                                                {{ $b['tampil'][$kunci] ?? '—' }}
                                                @if ($loop->first)
                                                    {{-- Layar sempit: kolom lain diringkas di bawah nama. --}}
                                                    <span class="mt-0.5 block text-xs font-normal text-stone-500 md:hidden">
                                                        {{ collect($b['tampil'])->except($kunci)->reject(fn ($v) => $v === '—')->implode(' · ') }}
                                                    </span>
                                                @endif
                                            </td>
                                        @endforeach
                                        <td class="min-w-[12rem] px-3 py-2">
                                            <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset {{ $lencana[$b['status']][1] }}">{{ $lencana[$b['status']][0] }}</span>
                                            @foreach ($b['pesan'] as $pesan)
                                                <p @class(['mt-1 text-xs', 'text-red-700' => $b['status'] === 'gagal', 'text-stone-500' => $b['status'] !== 'gagal'])>{{ $pesan }}</p>
                                            @endforeach
                                            @foreach ($b['peringatan'] as $pesan)
                                                <p class="mt-1 text-xs text-gold-700">{{ $pesan }}</p>
                                            @endforeach
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if ($pratinjauTambahan ?? null)
                    @include($pratinjauTambahan)
                @endif

                @if ($ringkasanTotal ?? null)
                    {{-- Impor rincian RAB: cocokkan total dengan baris TOTAL berkas dan lihat dampaknya pada pagu. --}}
                    @php $selisihBerkas = $ringkasanTotal['berkas'] !== null ? $ringkasanTotal['berkas'] - $ringkasanTotal['siap'] - $ringkasanTotal['dilewati'] : null; @endphp
                    <div class="mt-3 grid gap-3 rounded-xl bg-stone-50 p-3 text-sm ring-1 ring-stone-200 sm:grid-cols-3" data-testid="ringkasan-total-impor">
                        <div>
                            <p class="text-xs text-stone-500">Total item siap diimpor</p>
                            <p class="font-semibold text-stone-900 tabular-nums">{{ Format::rupiah($ringkasanTotal['siap']) }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-stone-500">Baris TOTAL di berkas</p>
                            @if ($ringkasanTotal['berkas'] === null)
                                <p class="text-stone-500">Tidak ada</p>
                            @elseif ($ringkasanTotal['cocok'])
                                <p class="flex items-center gap-1 font-semibold text-brand-700" data-testid="total-berkas-cocok">
                                    <x-ui.icon name="check-circle" class="size-4" /> {{ Format::rupiah($ringkasanTotal['berkas']) }} — cocok
                                </p>
                            @else
                                <p class="font-semibold text-gold-700" data-testid="total-berkas-selisih">
                                    {{ Format::rupiah($ringkasanTotal['berkas']) }} — selisih {{ Format::rupiah(abs($selisihBerkas)) }}{{ $gagal > 0 ? ' (ada baris gagal)' : '' }}
                                </p>
                            @endif
                        </div>
                        <div>
                            <p class="text-xs text-stone-500">RAB sesudah impor</p>
                            <p class="font-semibold text-stone-900 tabular-nums">{{ Format::rupiah($ringkasanTotal['sesudah']) }}</p>
                            <x-rab.status-pagu :status="$ringkasanTotal['status']" class="text-xs" data-testid="status-pagu-sesudah-impor" />
                        </div>
                    </div>
                @endif

                @if ($gagal > 0)
                    <p class="mt-3 flex items-start gap-2 text-xs text-stone-600">
                        <x-ui.icon name="info" class="mt-0.5 size-3.5 shrink-0 text-stone-400" />
                        Baris gagal tidak diimpor. Perbaiki di berkas lalu unggah lagi — baris yang sudah masuk akan dilewati.
                    </p>
                @endif

                <div class="mt-4 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button type="button" wire:click="ulangi" class="btn-ghost">Ganti berkas</button>
                    @if ($prosesSegera ?? false)
                        <button type="button" class="btn-primary" disabled data-testid="mulai-impor">
                            <x-ui.icon name="upload" class="size-4" /> Impor {{ $siap }} {{ $satuan }} <x-ui.segera />
                        </button>
                    @else
                        <button type="button" wire:click="mulaiImpor" class="btn-primary" @disabled($siap === 0) data-testid="mulai-impor">
                            <x-ui.icon name="upload" class="size-4" />
                            {{ $siap > 0 ? "Impor {$siap} {$satuan}" : "Tidak ada {$satuan} yang siap diimpor" }}
                        </button>
                    @endif
                </div>
            </div>
        @elseif ($tahap === 'proses')
            {{-- Klien memanggil lanjutkanImpor() berulang; tiap panggilan memproses beberapa baris lalu layar diperbarui. --}}
            <div x-data="{ jalan: true }" x-init="(async () => { while (jalan && await $wire.lanjutkanImpor()) {} })()" data-testid="proses-impor">
                <div class="flex items-center justify-between gap-3 text-sm">
                    <p class="flex items-center gap-2 font-medium text-stone-800">
                        <x-ui.icon name="loader" class="size-4 animate-spin text-brand-700" />
                        Memproses baris {{ min($posisi, count($baris)) }} dari {{ count($baris) }}…
                    </p>
                    <span class="font-semibold text-stone-900 tabular-nums" data-testid="persen-impor">{{ $this->persen() }}%</span>
                </div>
                <div class="mt-2 h-2 overflow-hidden rounded-full bg-stone-100" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $this->persen() }}" aria-label="Kemajuan impor">
                    <div class="h-full rounded-full bg-brand-600 transition-all duration-300" style="width: {{ $this->persen() }}%"></div>
                </div>
                <p class="mt-2 text-xs text-stone-500">
                    {{ $this->jumlahHasil('berhasil') }} berhasil · {{ $this->jumlahHasil('dilewati') }} dilewati · {{ $this->jumlahHasil('gagal') }} gagal
                </p>

                <ol class="mt-3 max-h-48 space-y-1 overflow-auto text-xs" aria-live="polite" data-testid="log-impor">
                    @foreach (array_reverse(array_slice($hasil, -8)) as $h)
                        <li class="flex items-start gap-2">
                            <span class="inline-flex shrink-0 rounded-full px-1.5 py-px text-[10px] font-semibold ring-1 ring-inset {{ $lencana[$h['status']][1] }}">{{ $lencana[$h['status']][0] }}</span>
                            <span class="min-w-0 text-stone-600">Baris {{ $h['baris'] }}: {{ $h['label'] }}</span>
                        </li>
                    @endforeach
                </ol>

                <div class="mt-4 flex justify-end">
                    <button type="button" class="btn-secondary" x-on:click="jalan = false; $wire.hentikan()">Hentikan</button>
                </div>
            </div>
        @else
            @php
                $berhasil = $this->jumlahHasil('berhasil');
                $gagal = $this->jumlahHasil('gagal');
                $dilewati = $this->jumlahHasil('dilewati');
                $dibatalkan = $this->jumlahHasil('dibatalkan');
                [$ikon, $nada] = match (true) {
                    $berhasil > 0 && $gagal === 0 && $dibatalkan === 0 => ['check-circle', 'bg-brand-50 text-brand-900 ring-brand-200'],
                    $berhasil > 0 => ['alert', 'bg-gold-50 text-stone-800 ring-gold-200'],
                    default => ['alert', 'bg-red-50 text-red-800 ring-red-200'],
                };
            @endphp
            <div data-testid="laporan-impor">
                <div class="flex items-start gap-3 rounded-xl px-4 py-3 ring-1 {{ $nada }}" role="status">
                    <x-ui.icon :name="$ikon" class="mt-0.5 size-5 shrink-0" />
                    <div class="min-w-0">
                        <p class="font-semibold" data-testid="ringkasan-impor">
                            @if ($dihentikan)
                                Impor dihentikan: {{ $berhasil }} {{ $satuan }} berhasil sebelum dihentikan.
                            @elseif ($berhasil > 0)
                                Impor selesai: {{ $berhasil }} {{ $satuan }} berhasil {{ $kataBerhasil ?? 'ditambahkan' }}.
                            @else
                                Tidak ada {{ $satuan }} yang ditambahkan.
                            @endif
                        </p>
                        <p class="text-sm">
                            {{ $berhasil }} berhasil · {{ $dilewati }} dilewati · {{ $gagal }} gagal{{ $dibatalkan ? ' · '.$dibatalkan.' dibatalkan' : '' }} dari {{ count($hasil) }} baris berkas {{ $namaBerkas }}.
                        </p>
                    </div>
                </div>

                <ol class="mt-4 max-h-[24rem] divide-y divide-stone-100 overflow-auto rounded-xl ring-1 ring-stone-200" data-testid="rincian-laporan-impor">
                    @foreach ($hasil as $h)
                        <li class="flex flex-wrap items-start justify-between gap-x-3 gap-y-1 px-3 py-2 text-sm" data-status="{{ $h['status'] }}">
                            <span class="flex min-w-0 items-start gap-2">
                                <span class="w-14 shrink-0 text-right text-xs text-stone-400 tabular-nums">Baris {{ $h['baris'] }}</span>
                                <span class="min-w-0">
                                    <span class="block font-medium text-stone-800">{{ $h['label'] }}</span>
                                    @if ($h['pesan'])
                                        <span @class(['block text-xs', 'text-red-700' => $h['status'] === 'gagal', 'text-stone-500' => $h['status'] !== 'gagal'])>{{ $h['pesan'] }}</span>
                                    @endif
                                </span>
                            </span>
                            <span class="flex shrink-0 items-center gap-2">
                                @if ($h['tautan'])
                                    <a href="{{ $h['tautan'] }}" wire:navigate class="text-xs font-semibold text-brand-700 hover:text-brand-800">Buka</a>
                                @endif
                                <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset {{ $lencana[$h['status']][1] }}">{{ $lencana[$h['status']][0] }}</span>
                            </span>
                        </li>
                    @endforeach
                </ol>

                <div class="mt-4 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button type="button" wire:click="ulangi" class="btn-ghost">Impor berkas lain</button>
                    <button type="button" wire:click="unduhLaporan" class="btn-secondary" data-testid="unduh-laporan-impor">
                        <x-ui.icon name="download" class="size-4" /> Unduh laporan
                    </button>
                </div>
            </div>
        @endif
    </div>
</section>
