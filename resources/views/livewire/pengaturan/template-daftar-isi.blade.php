@use('App\Enums\SumberDana')
@use('App\Support\Dokumen\DaftarIsi')

@php
    $pratinjau = $this->pratinjau;
    $buku = $this->bukuPratinjau;
    $berubah = $this->berubah();
    $adaKhusus = $this->adaKhusus();
    $kolomDipilih = (array) ($konfigurasi['kolom'] ?? []);
    $totalDipilih = in_array('total', $kolomDipilih, true);
@endphp

<div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,30rem)]" data-testid="tab-daftar-isi">
    <div class="min-w-0 space-y-6">
        {{-- Peta buku --}}
        <section class="rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-peta-buku" data-testid="peta-buku">
            <header class="border-b border-stone-100 px-5 py-4 sm:px-6">
                <h2 id="judul-peta-buku" class="text-base font-semibold text-stone-900">Buku per leading &amp; sumber dana</h2>
                <p class="mt-0.5 text-sm text-stone-500">Setiap buku punya halaman “Daftar Kegiatan” sendiri. Pilih buku untuk memberi pengaturan khusus; selebihnya memakai pengaturan bawaan.</p>
            </header>
            <div class="px-5 pt-4 sm:px-6">
                <button type="button" wire:click="pilih(null, null)" aria-pressed="{{ $leading === null ? 'true' : 'false' }}" data-testid="pilih-bawaan"
                        @class(['flex w-full items-center justify-between gap-3 rounded-xl px-4 py-3 text-left ring-1 transition', 'bg-brand-50 ring-2 ring-brand-600' => $leading === null, 'ring-stone-200 hover:bg-stone-50' => $leading !== null])>
                    <span>
                        <span class="block text-sm font-semibold text-stone-900">Pengaturan bawaan</span>
                        <span class="block text-xs text-stone-500">Berlaku untuk semua buku tanpa pengaturan khusus.</span>
                    </span>
                    <x-ui.icon name="settings" class="size-5 shrink-0 text-stone-400" />
                </button>
            </div>
            <ul class="divide-y divide-stone-100 px-5 py-2 sm:px-6" aria-label="Buku per leading">
                @foreach ($this->peta as $baris)
                    <li class="flex flex-wrap items-center gap-x-3 gap-y-2 py-2.5" wire:key="leading-{{ $baris['kode'] }}">
                        <p class="min-w-0 flex-1 text-sm">
                            <span class="font-mono text-xs text-stone-400">{{ $baris['kode'] }}</span>
                            <span class="font-medium text-stone-800">{{ $baris['nama_unit'] }}</span>
                        </p>
                        <div class="flex gap-2">
                            @foreach ($baris['buku'] as $sumber => $isiBuku)
                                @php
                                    $dipilih = $leading === $baris['kode'] && $this->sumber === $sumber;
                                @endphp
                                <button type="button" wire:click="pilih('{{ $baris['kode'] }}', '{{ $sumber }}')" aria-pressed="{{ $dipilih ? 'true' : 'false' }}"
                                        data-testid="buku-{{ $baris['kode'] }}-{{ mb_strtolower($sumber) }}" data-khusus="{{ $isiBuku['khusus'] ? 'ya' : 'tidak' }}"
                                        aria-label="Daftar isi {{ $baris['nama_unit'] }} {{ $sumber }}: {{ $isiBuku['jumlah'] }} kegiatan{{ $isiBuku['khusus'] ? ', pengaturan khusus' : '' }}"
                                        @class(['w-28 rounded-lg px-2.5 py-1.5 text-left text-xs ring-1 transition', 'bg-brand-50 ring-2 ring-brand-600' => $dipilih, 'ring-stone-200 hover:bg-stone-50' => ! $dipilih])>
                                    <span class="flex items-center justify-between gap-1 font-semibold text-stone-800">
                                        {{ $sumber }}
                                        @if ($isiBuku['khusus'])
                                            <span class="rounded bg-gold-100 px-1 text-[10px] font-semibold text-gold-700">Khusus</span>
                                        @endif
                                    </span>
                                    <span @class(['block', 'text-stone-500' => $isiBuku['jumlah'] > 0, 'text-stone-400' => $isiBuku['jumlah'] === 0])>{{ $isiBuku['jumlah'] }} kegiatan</span>
                                </button>
                            @endforeach
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>

        {{-- Editor konfigurasi --}}
        <section class="rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-atur-daftar-isi" data-testid="editor-daftar-isi">
            <header class="flex flex-wrap items-center justify-between gap-2 border-b border-stone-100 px-5 py-4 sm:px-6">
                <div class="min-w-0">
                    <h2 id="judul-atur-daftar-isi" class="text-base font-semibold text-stone-900">{{ $leading === null ? 'Pengaturan bawaan' : 'Daftar isi '.$this->namaBuku() }}</h2>
                    <p class="mt-0.5 text-xs text-stone-500" data-testid="status-daftar-isi">
                        @if ($leading === null)
                            Dipakai semua buku kecuali yang diberi pengaturan khusus.
                        @elseif ($adaKhusus)
                            Memakai pengaturan khusus.
                        @else
                            Mengikuti pengaturan bawaan — simpan untuk membuat pengaturan khusus buku ini.
                        @endif
                    </p>
                </div>
                @if ($berubah)
                    <span class="inline-flex items-center gap-1 text-xs font-medium text-gold-700" data-testid="daftar-isi-berubah"><span class="size-1.5 rounded-full bg-gold-500"></span> Belum disimpan</span>
                @endif
            </header>
            <div class="space-y-5 px-5 py-5 sm:px-6">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field label="Judul halaman" for="di-judul">
                        <input type="text" id="di-judul" wire:model.live.debounce.400ms="konfigurasi.judul" maxlength="100" class="form-input" autocomplete="off">
                    </x-ui.field>
                    <x-ui.field label="Subjudul" for="di-subjudul" hint="Penanda: {leading}, {sumber}, {tahun} — terisi otomatis per buku.">
                        <input type="text" id="di-subjudul" wire:model.live.debounce.400ms="konfigurasi.subjudul" maxlength="200" class="form-input" autocomplete="off">
                    </x-ui.field>
                </div>

                <fieldset>
                    <legend class="form-label">Kolom tabel</legend>
                    <div class="grid gap-2 sm:grid-cols-3">
                        @foreach (DaftarIsi::KOLOM as $kunci => [$label, $wajib])
                            <label @class(['flex items-center gap-2 rounded-lg px-2.5 py-2 text-sm ring-1 ring-stone-200', 'text-stone-500' => $wajib, 'cursor-pointer text-stone-800 hover:bg-stone-50' => ! $wajib])>
                                @if ($wajib)
                                    <input type="checkbox" checked disabled class="size-4 rounded accent-brand-700">
                                    {{ $label }} <span class="text-[10px] text-stone-400">wajib</span>
                                @else
                                    <input type="checkbox" value="{{ $kunci }}" wire:model.live="konfigurasi.kolom" class="size-4 rounded accent-brand-700" data-kolom="{{ $kunci }}">
                                    {{ $label }}
                                @endif
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <div class="grid gap-4 sm:grid-cols-2">
                    <fieldset>
                        <legend class="form-label">Urutan kegiatan</legend>
                        <div class="space-y-1.5">
                            @foreach (DaftarIsi::URUTAN as $kunci => $label)
                                <label class="flex cursor-pointer items-center gap-2 text-sm text-stone-800">
                                    <input type="radio" name="di-urutan" value="{{ $kunci }}" wire:model.live="konfigurasi.urutan" class="size-4 accent-brand-700">
                                    {{ $label }}{{ $kunci === 'kode_rktm' ? ' (sesuai PRD)' : '' }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                    <div>
                        <p class="form-label">Baris jumlah</p>
                        <label @class(['flex items-start gap-2 text-sm', 'cursor-pointer text-stone-800' => $totalDipilih, 'text-stone-400' => ! $totalDipilih])>
                            <input type="checkbox" wire:model.live="konfigurasi.tampilkan_jumlah" @disabled(! $totalDipilih) class="mt-0.5 size-4 rounded accent-brand-700" data-testid="di-tampilkan-jumlah">
                            Tampilkan baris jumlah anggaran di akhir tabel
                        </label>
                        @unless ($totalDipilih)
                            <p class="form-hint">Aktif bila kolom Jumlah Anggaran ditampilkan.</p>
                        @endunless
                    </div>
                </div>

                <x-ui.field label="Catatan kaki (opsional)" for="di-catatan" hint="Tercetak di bawah tabel, mis. keterangan sumber data.">
                    <textarea id="di-catatan" wire:model.live.debounce.400ms="konfigurasi.catatan" rows="2" maxlength="500" class="form-input resize-y"></textarea>
                </x-ui.field>

                <div class="flex flex-wrap items-center justify-end gap-2 border-t border-stone-100 pt-4">
                    @if ($leading === null)
                        <button type="button" class="btn-ghost mr-auto px-2.5 py-1.5 text-xs" wire:click="pakaiSusunanAplikasi">Pakai susunan aplikasi</button>
                    @elseif ($adaKhusus)
                        <button type="button" class="btn-ghost mr-auto px-2.5 py-1.5 text-xs" wire:click="kembalikanBawaan">Kembalikan ke pengaturan bawaan</button>
                    @endif
                    @if ($berubah)
                        <button type="button" class="btn-ghost" wire:click="batalkan">Batalkan</button>
                    @endif
                    {{-- Dua varian berkunci (lihat konvensi @disabled + wire:loading). --}}
                    @if ($berubah || ($leading !== null && ! $adaKhusus))
                        <button type="button" class="btn-primary" wire:key="simpan-di-aktif" wire:click="simpan" wire:loading.attr="disabled" wire:target="simpan">
                            <x-ui.icon name="save" class="size-4" />
                            {{ $leading === null ? 'Simpan bawaan' : ($adaKhusus ? 'Simpan' : 'Simpan sebagai pengaturan khusus') }}
                        </button>
                    @else
                        <button type="button" class="btn-primary" wire:key="simpan-di-terkunci" disabled>
                            <x-ui.icon name="save" class="size-4" /> Tersimpan
                        </button>
                    @endif
                </div>
            </div>
        </section>
    </div>

    {{-- Pratinjau halaman --}}
    <aside class="min-w-0">
        <section class="rounded-2xl bg-white p-4 shadow-xs ring-1 ring-stone-200 sm:p-5 xl:sticky xl:top-24" aria-labelledby="judul-pratinjau-di" data-testid="panel-pratinjau-daftar-isi">
            <h2 id="judul-pratinjau-di" class="text-sm font-semibold text-stone-900">Pratinjau halaman</h2>
            <p class="text-xs text-stone-500" data-testid="buku-pratinjau">
                {{ $leading === null ? 'Contoh buku ' : 'Buku ' }}{{ $this->namaBuku($buku['leading'], $buku['sumber']) }} · {{ count($pratinjau['baris']) }} kegiatan
            </p>
            <div class="mt-3 overflow-x-auto">
                <x-dokumen.daftar-isi :halaman="$pratinjau" class="min-w-[20rem] max-w-md" />
            </div>
            <p class="mt-3 text-xs text-stone-500">Nomor halaman (“…”) diisi otomatis saat buku digabung, mengikuti urutan kegiatan ini.</p>
        </section>
    </aside>
</div>
