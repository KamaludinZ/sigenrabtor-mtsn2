@use('App\Livewire\Pengaturan\TemplateNomorHalaman')
@use('App\Support\Dokumen\NomorHalaman')

@php
    $hasil = $this->hasil();
    $buku = $this->bukuContoh;
    $simulasi = NomorHalaman::simulasi($hasil, $buku['kegiatan']);
    $berubah = $this->berubah();
    $posisiLabel = ['bawah_tengah' => 'bottom-[3%] inset-x-0 text-center', 'bawah_kanan' => 'bottom-[3%] right-[8%] text-right', 'atas_kanan' => 'top-[3%] right-[8%] text-right'][$hasil['posisi']];
    $warnaJenis = ['cover_buku' => 'bg-brand-700', 'daftar_isi' => 'bg-stone-400', 'cover_kegiatan' => 'bg-gold-400', 'kak' => 'bg-stone-200', 'rab' => 'bg-sky-200'];
@endphp

<div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,32rem)]" data-testid="tab-nomor-halaman">
    <section class="rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-aturan-nomor" data-testid="form-nomor-halaman">
        <header class="flex flex-wrap items-center justify-between gap-2 border-b border-stone-100 px-5 py-4 sm:px-6">
            <div class="min-w-0">
                <h2 id="judul-aturan-nomor" class="text-base font-semibold text-stone-900">Aturan nomor halaman</h2>
                <p class="mt-0.5 text-sm text-stone-500">Berlaku untuk semua buku tahun ini. Nomor di daftar isi mengikuti aturan yang sama.</p>
            </div>
            @if ($berubah)
                <span class="inline-flex items-center gap-1 text-xs font-medium text-gold-700" data-testid="nomor-berubah"><span class="size-1.5 rounded-full bg-gold-500"></span> Belum disimpan</span>
            @endif
        </header>
        <div class="space-y-6 px-5 py-5 sm:px-6">
            <fieldset>
                <legend class="form-label">Format</legend>
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach (NomorHalaman::PRESET as $format => $contoh)
                        <label class="flex cursor-pointer items-center gap-2.5 rounded-lg px-3 py-2 text-sm ring-1 ring-stone-200 ring-inset hover:bg-stone-50 has-checked:bg-brand-50 has-checked:ring-2 has-checked:ring-brand-600">
                            <input type="radio" name="format-nomor" value="{{ $format }}" wire:model.live="pilihanFormat" class="size-4 accent-brand-700">
                            <span class="min-w-0">
                                <code class="block text-xs font-semibold text-stone-800">{{ $format }}</code>
                                <span class="block text-[11px] text-stone-500">mis. {{ $contoh }}</span>
                            </span>
                        </label>
                    @endforeach
                    <label class="flex cursor-pointer items-center gap-2.5 rounded-lg px-3 py-2 text-sm ring-1 ring-stone-200 ring-inset hover:bg-stone-50 has-checked:bg-brand-50 has-checked:ring-2 has-checked:ring-brand-600">
                        <input type="radio" name="format-nomor" value="{{ TemplateNomorHalaman::FORMAT_LAIN }}" wire:model.live="pilihanFormat" class="size-4 accent-brand-700">
                        <span class="text-sm font-medium text-stone-800">Format lain…</span>
                    </label>
                </div>
                @if ($pilihanFormat === TemplateNomorHalaman::FORMAT_LAIN)
                    <div class="mt-3">
                        <x-ui.field label="Format sendiri" for="format-lain" error="formatLain" hint="Pakai {x} untuk nomor halaman dan {y} untuk nomor halaman terakhir, mis. “Halaman {x}”.">
                            <input type="text" id="format-lain" wire:model.live.debounce.400ms="formatLain" maxlength="40" class="form-input" autocomplete="off" placeholder="Halaman {x}">
                        </x-ui.field>
                    </div>
                @endif
            </fieldset>

            <div class="grid gap-5 sm:grid-cols-2">
                <fieldset>
                    <legend class="form-label">Posisi</legend>
                    <div class="space-y-1.5">
                        @foreach (NomorHalaman::POSISI as $kunci => $label)
                            <label class="flex cursor-pointer items-center gap-2 text-sm text-stone-800">
                                <input type="radio" name="posisi-nomor" value="{{ $kunci }}" wire:model.live="aturan.posisi" class="size-4 accent-brand-700">
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <x-ui.field label="Nomor halaman pertama" for="mulai-dari" hint="Biasanya 1; isi angka lain bila buku melanjutkan dokumen lain.">
                    <input type="number" id="mulai-dari" min="1" max="999" wire:model.live.debounce.400ms="aturan.mulai_dari" class="form-input w-28" inputmode="numeric">
                </x-ui.field>
            </div>

            <fieldset class="space-y-2.5">
                <legend class="form-label">Halaman tanpa nomor</legend>
                <label class="flex items-start gap-2 text-sm text-stone-800">
                    <input type="checkbox" wire:model.live="aturan.sampul_tanpa_nomor" class="mt-0.5 size-4 rounded accent-brand-700" data-testid="sampul-tanpa-nomor">
                    <span>Sampul tanpa nomor <span class="block text-xs text-stone-500">Cover buku dan cover tiap kegiatan.</span></span>
                </label>
                <label class="flex items-start gap-2 text-sm text-stone-800">
                    <input type="checkbox" wire:model.live="aturan.daftar_isi_tanpa_nomor" class="mt-0.5 size-4 rounded accent-brand-700" data-testid="daftar-isi-tanpa-nomor">
                    <span>Daftar isi tanpa nomor</span>
                </label>
                <label class="flex items-start gap-2 text-sm text-stone-800">
                    <input type="checkbox" wire:model.live="aturan.hitung_tanpa_nomor" class="mt-0.5 size-4 rounded accent-brand-700" data-testid="hitung-tanpa-nomor">
                    <span>Halaman tanpa nomor tetap dihitung <span class="block text-xs text-stone-500">Seperti Word: nomor setelah sampul melanjutkan hitungan (sampul = halaman 1).</span></span>
                </label>
            </fieldset>

            <label class="flex items-start gap-2 text-sm text-stone-800">
                <input type="checkbox" wire:model.live="aturan.urut_mengikuti_daftar" class="mt-0.5 size-4 rounded accent-brand-700">
                <span>Urutan kegiatan dalam buku mengikuti daftar isi <span class="block text-xs text-stone-500">Kegiatan digabung sesuai urutan di halaman Daftar Kegiatan sehingga nomor di daftar isi selalu cocok.</span></span>
            </label>

            <div class="flex flex-wrap items-center justify-end gap-2 border-t border-stone-100 pt-4">
                <button type="button" class="btn-ghost mr-auto px-2.5 py-1.5 text-xs" wire:click="pakaiAturanAplikasi">Pakai aturan aplikasi</button>
                @if ($berubah)
                    <button type="button" class="btn-ghost" wire:click="batalkan">Batalkan</button>
                    <button type="button" class="btn-primary" wire:key="simpan-nomor-aktif" wire:click="simpan" wire:loading.attr="disabled" wire:target="simpan">
                        <x-ui.icon name="save" class="size-4" /> Simpan aturan
                    </button>
                @else
                    <button type="button" class="btn-primary" wire:key="simpan-nomor-terkunci" disabled><x-ui.icon name="save" class="size-4" /> Tersimpan</button>
                @endif
            </div>
        </div>
    </section>

    {{-- Simulasi buku --}}
    <aside class="min-w-0">
        <section class="rounded-2xl bg-white p-4 shadow-xs ring-1 ring-stone-200 sm:p-5 xl:sticky xl:top-24" aria-labelledby="judul-simulasi-nomor" data-testid="simulasi-nomor">
            <h2 id="judul-simulasi-nomor" class="text-sm font-semibold text-stone-900">Simulasi buku</h2>
            <p class="text-xs text-stone-500" data-testid="ringkasan-simulasi">
                {{ $buku['nama'] }} · {{ count($buku['kegiatan']) }} kegiatan · {{ count($simulasi['halaman']) }} halaman (perkiraan {{ array_sum(NomorHalaman::HALAMAN_KEGIATAN) }} halaman per kegiatan)
            </p>
            <ol class="mt-3 grid grid-cols-6 gap-1.5 sm:grid-cols-8 xl:grid-cols-6" aria-label="Halaman buku contoh">
                @foreach ($simulasi['halaman'] as $nomorUrut => $h)
                    <li class="relative aspect-[210/297] overflow-hidden rounded-sm bg-white ring-1 ring-stone-300" title="{{ $h['judul'] }}"
                        data-halaman="{{ $nomorUrut + 1 }}" data-jenis="{{ $h['jenis'] }}" data-label="{{ $h['label'] ?? '' }}">
                        <span class="absolute inset-x-0 top-0 h-1 {{ $warnaJenis[$h['jenis']] }}" aria-hidden="true"></span>
                        <span class="absolute inset-x-1 top-2 truncate text-[7px] leading-tight text-stone-500">{{ $h['judul'] }}</span>
                        @if ($h['label'] !== null)
                            <span class="absolute {{ $posisiLabel }} px-0.5 font-serif text-[7px] leading-none font-semibold whitespace-nowrap text-stone-800">{{ $h['label'] }}</span>
                        @else
                            <span class="absolute inset-x-0 bottom-[3%] text-center text-[6px] text-stone-300 italic">tanpa nomor</span>
                        @endif
                    </li>
                @endforeach
            </ol>
            <div class="mt-3 flex flex-wrap gap-x-3 gap-y-1 text-[11px] text-stone-500" aria-hidden="true">
                <span class="inline-flex items-center gap-1"><span class="size-2 rounded-sm bg-brand-700"></span> Cover buku</span>
                <span class="inline-flex items-center gap-1"><span class="size-2 rounded-sm bg-stone-400"></span> Daftar isi</span>
                <span class="inline-flex items-center gap-1"><span class="size-2 rounded-sm bg-gold-400"></span> Cover kegiatan</span>
                <span class="inline-flex items-center gap-1"><span class="size-2 rounded-sm bg-stone-200"></span> KAK/TOR</span>
                <span class="inline-flex items-center gap-1"><span class="size-2 rounded-sm bg-sky-200"></span> RAB</span>
            </div>
            @if ($buku['kegiatan'] !== [])
                <div class="mt-4 border-t border-stone-100 pt-3">
                    <h3 class="text-[11px] font-semibold tracking-wider text-stone-400 uppercase">Kolom Halaman di daftar isi</h3>
                    <ol class="mt-1.5 space-y-1 text-xs text-stone-700" data-testid="halaman-daftar-isi">
                        @foreach ($buku['kegiatan'] as $i => $nama)
                            <li class="flex justify-between gap-3"><span class="min-w-0 truncate">{{ $i + 1 }}. {{ $nama }}</span><span class="shrink-0 font-semibold tabular-nums" data-mulai="{{ $i }}">{{ $simulasi['mulai'][$i] ?? '—' }}</span></li>
                        @endforeach
                    </ol>
                </div>
            @endif
        </section>
    </aside>
</div>
