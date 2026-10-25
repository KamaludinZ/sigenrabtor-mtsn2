@use('App\Enums\SumberDana')
@use('App\Support\Format')
@use('App\Support\Rab\MekanismePembayaran')
@use('App\Support\Terbilang')

@php
    $dipilih = array_column($form->sumberDana(), 'sumber');
    // Teks ringkasan menurut jumlah sumber terpilih (0, 1, 2); dipakai server dan klien (":sumber" diganti nama sumbernya).
    $teksPilihan = [
        ['Belum ada sumber dana dipilih', 'Pilih DIPA, Komite, atau keduanya. Setiap sumber dana mendapat RAB dan dokumen KAK/TOR sendiri.'],
        ['Dibiayai :sumber', 'Akan dibuat 1 dokumen KAK/TOR & RAB :sumber.'],
        ['Dibiayai DIPA dan Komite', 'Akan dibuat 2 dokumen KAK/TOR & RAB terpisah — masing-masing masuk buku sumber dananya.'],
    ];
    $ringkasPilihan = str_replace(':sumber', $dipilih[0] ?? '', $teksPilihan[min(count($dipilih), 2)]);
    // Sumber terpilih yang pagunya belum diisi (kosong/nol) — kelengkapan pagu juga dihitung ulang di klien tiap ketukan.
    $paguKosong = array_column(array_filter($form->sumberDana(), fn ($s) => ($s['pagu'] ?? 0) < 1), 'sumber');
    $teksPaguKosong = $paguKosong === [] ? '' : 'Pagu '.implode(' dan ', $paguKosong).' belum diisi — RAB belum bisa dibandingkan dengan pagu.';
@endphp

{{--
    Pemilihan sumber dana kegiatan (PRD F5): kartu per sumber berisi pagu (dari cascade Perkin) dan ringkasan RAB-nya.
    Melepas sumber yang RAB-nya sudah berisi tidak menghapus RAB itu, tetapi RAB tersebut tidak ikut dicetak.
    Ringkasan pilihan, label & gaya kartu, dan status pagu yang belum lengkap (lencana kartu, baris ringkasan, total pagu)
    dihitung dari state $wire sehingga langsung berubah saat sumber dicentang atau pagu diketik — tanpa menunggu respons
    server atau kotak pagu ditinggalkan. Render server memberi keadaan awal yang sama.
--}}
<div class="space-y-4" data-testid="pemilihan-sumber-dana"
     x-data="{
         sumber: @js(array_map(fn ($j) => ['label' => $j->value, 'kunci' => $j->kunci()], SumberDana::cases())),
         teksPilihan: @js($teksPilihan),
         dipilih(kunci) {
             return Boolean(this.$wire.$get('form.dana_' + kunci));
         },
         get terpilih() {
             return this.sumber.filter((s) => this.dipilih(s.kunci)).map((s) => s.label);
         },
         get ringkasPilihan() {
             return this.teksPilihan[Math.min(this.terpilih.length, 2)].map((teks) => teks.replace(':sumber', this.terpilih[0] ?? ''));
         },
         pagu(kunci) {
             const angka = parseInt(String(this.$wire.$get('form.pagu_' + kunci) ?? '').replace(/\D/g, ''), 10);

             return Number.isNaN(angka) ? 0 : angka;
         },
         get paguKosong() {
             return this.sumber.filter((s) => this.dipilih(s.kunci) && this.pagu(s.kunci) < 1).map((s) => s.label);
         },
         get teksPaguKosong() {
             return this.paguKosong.length ? `Pagu ${this.paguKosong.join(' dan ')} belum diisi — RAB belum bisa dibandingkan dengan pagu.` : '';
         },
         get totalPagu() {
             return this.sumber.reduce((jumlah, s) => jumlah + (this.dipilih(s.kunci) ? this.pagu(s.kunci) : 0), 0);
         },
     }">
    <div class="flex items-start gap-3 rounded-xl bg-stone-50 px-4 py-3 ring-1 ring-stone-200" data-testid="ringkasan-pilihan-sumber" data-jumlah="{{ count($dipilih) }}"
         x-bind:data-jumlah="terpilih.length">
        <x-ui.icon name="wallet" class="mt-0.5 size-5 shrink-0 text-stone-400" />
        <div class="min-w-0">
            <p class="flex flex-wrap items-center gap-1.5 text-sm font-semibold text-stone-900">
                <span x-text="ringkasPilihan[0]">{{ $ringkasPilihan[0] }}</span>
                @foreach (SumberDana::cases() as $jenisLencana)
                    <x-rab.lencana-sumber :sumber="$jenisLencana->value" class="text-[11px]" x-show="dipilih('{{ $jenisLencana->kunci() }}')"
                                          :style="in_array($jenisLencana->value, $dipilih, true) ? null : 'display: none'" />
                @endforeach
            </p>
            <p class="text-xs text-stone-500" x-text="ringkasPilihan[1]">{{ $ringkasPilihan[1] }}</p>
            <p class="mt-1 flex items-start gap-1 text-xs font-medium text-gold-700" role="status" data-testid="pagu-belum-lengkap"
               x-show="paguKosong.length" @style(['display: none' => $paguKosong === []])>
                <x-ui.icon name="alert" class="mt-px size-3.5 shrink-0" />
                <span x-text="teksPaguKosong">{{ $teksPaguKosong }}</span>
            </p>
        </div>
    </div>

    <div class="grid gap-3 lg:grid-cols-2">
        @foreach (SumberDana::cases() as $jenis)
            @php
                $kunci = $jenis->kunci();
                $label = $jenis->value;
                $aktif = $form->{'dana_'.$kunci};
                $idPagu = 'pagu_'.$kunci;
                $rekap = $aktif ? ($this->rekapRab[$label] ?? null) : null;
                $anggaran = $aktif ? ($this->anggaranRab[$label] ?? null) : null;
                $terlepas = $aktif ? null : ($this->rabTerlepas[$label] ?? null);
            @endphp
            <article @class([
                    'flex flex-col rounded-2xl ring-1 transition',
                    'bg-brand-50/40 ring-2 ring-brand-300' => $aktif,
                    'bg-white ring-stone-200' => ! $aktif,
                 ]) data-testid="sumber-{{ $kunci }}" data-dipilih="{{ $aktif ? 'ya' : 'tidak' }}"
                 x-bind:class="{ 'bg-brand-50/40 ring-2 ring-brand-300': dipilih('{{ $kunci }}'), 'bg-white ring-stone-200': ! dipilih('{{ $kunci }}') }">
                {{-- Kepala kartu: pilih/lepas sumber dana --}}
                <label class="flex cursor-pointer items-start gap-3 px-4 pt-4">
                    <input type="checkbox" wire:model.live="form.dana_{{ $kunci }}" class="mt-0.5 size-4 rounded accent-brand-700">
                    <span class="min-w-0 flex-1">
                        <span class="flex items-center justify-between gap-2">
                            <x-rab.lencana-sumber :sumber="$label" class="text-xs" />
                            <span class="flex flex-wrap items-center justify-end gap-1.5">
                                <span class="rounded-full bg-gold-50 px-2 py-0.5 text-[11px] font-semibold text-gold-700 ring-1 ring-gold-200" data-testid="pagu-kosong-{{ $kunci }}"
                                      x-show="dipilih('{{ $kunci }}') && pagu('{{ $kunci }}') < 1" @style(['display: none' => ! in_array($label, $paguKosong, true)])>Pagu belum diisi</span>
                                <span @class(['text-[11px] font-semibold', 'text-brand-700' => $aktif, 'text-stone-400' => ! $aktif])
                                      x-bind:class="{ 'text-brand-700': dipilih('{{ $kunci }}'), 'text-stone-400': ! dipilih('{{ $kunci }}') }"
                                      x-text="dipilih('{{ $kunci }}') ? 'Dipilih' : 'Tidak dipakai'">{{ $aktif ? 'Dipilih' : 'Tidak dipakai' }}</span>
                            </span>
                        </span>
                        <span class="mt-1 block text-xs text-stone-500">{{ $jenis->keterangan() }}</span>
                    </span>
                </label>

                <div class="space-y-3 px-4 pt-3 pb-4">
                    <x-kegiatan.input-pagu :jenis="$jenis" :aktif="$aktif" :nilai-awal="$form->{$idPagu}" :pagu-dilepas="$paguDilepas[$kunci] ?? null"
                                           :total-rab="$rekap && $rekap->jumlahItem() > 0 ? $rekap->total : null" />

                    {{-- Ringkasan RAB sumber ini --}}
                    @if ($rekap)
                        <div class="rounded-xl bg-white p-3 ring-1 ring-stone-200" data-testid="rekap-rab-{{ $kunci }}">
                            <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1 text-xs">
                                <span class="font-semibold text-stone-700">RAB {{ $label }}</span>
                                <span class="text-stone-500">{{ $rekap->jumlahItem() > 0 ? 'RAB tersusun ('.$rekap->jumlahItem().' item)' : 'RAB belum berisi rincian' }}</span>
                            </div>
                            {{-- Warna netral: kondisi batas pagu ditunjukkan langsung di bawah input pagu (ikut ketikan). --}}
                            <p class="mt-1 text-right text-base font-bold text-stone-900 tabular-nums">{{ Format::rupiah($rekap->total) }}</p>
                            @if ($terbilangRekap = Terbilang::tampil($rekap->total))
                                <p class="text-right text-xs text-stone-500 italic">{{ $terbilangRekap }}</p>
                            @endif
                            @if ($anggaran && $anggaran->adaTermin())
                                @php $mekanisme = MekanismePembayaran::dari($anggaran); @endphp
                                <p @class(['mt-1 text-right text-xs', 'text-stone-500' => $mekanisme->status() === 'pas', 'font-medium text-gold-700' => $mekanisme->status() !== 'pas'])
                                   data-testid="termin-form-{{ $kunci }}">
                                    {{ count($mekanisme->termin) }} termin · {{ $mekanisme->teksStatus() }}
                                </p>
                            @endif

                            <div class="mt-2.5 flex flex-wrap items-center justify-between gap-2 border-t border-stone-100 pt-2.5">
                                @if ($rekap->jumlahItem() > 0)
                                    {{-- Rincian baca-saja; status buka/tutup dijaga saat Livewire merender ulang. --}}
                                    <details class="group w-full" wire:ignore.self data-testid="rincian-rab-{{ $kunci }}">
                                        <summary class="flex cursor-pointer list-none items-center justify-between gap-2 text-xs font-semibold text-brand-700 hover:text-brand-800">
                                            <span class="flex items-center gap-1">
                                                <x-ui.icon name="chevron-down" class="size-3.5 transition group-open:rotate-180" />
                                                Lihat rincian RAB {{ $label }}
                                            </span>
                                            @if ($kegiatanId)
                                                <a href="{{ route('kegiatan.rab', ['kegiatan' => $kegiatanId, 'sumber' => $label]) }}" wire:navigate
                                                   class="font-semibold text-stone-600 hover:text-stone-900" x-on:click.stop>Buka RAB {{ $label }} →</a>
                                            @endif
                                        </summary>
                                        <x-rab.tabel-rincian :anggaran="$anggaran" class="mt-2" />
                                    </details>
                                @elseif ($kegiatanId)
                                    <a href="{{ route('kegiatan.rab', ['kegiatan' => $kegiatanId, 'sumber' => $label]) }}" wire:navigate
                                       class="text-xs font-semibold text-brand-700 hover:text-brand-800">Susun RAB {{ $label }} →</a>
                                @endif
                            </div>
                        </div>
                        @error('form.termin_'.$kunci)
                            <p class="form-error" role="alert" data-testid="galat-termin-{{ $kunci }}">
                                {{ $message }}
                                <a href="{{ route('kegiatan.rab', ['kegiatan' => $kegiatanId, 'sumber' => $label, 'halaman' => 'termin']) }}" wire:navigate class="font-semibold underline">Atur termin</a>
                            </p>
                        @enderror
                    @elseif ($terlepas)
                        {{-- Sumber dilepas padahal RAB-nya sudah berisi: RAB disimpan, tetapi tidak dicetak. --}}
                        <div class="flex items-start gap-2 rounded-xl bg-gold-50 px-3 py-2.5 text-xs text-stone-800 ring-1 ring-gold-200" role="status" data-testid="rab-terlepas-{{ $kunci }}">
                            <x-ui.icon name="alert" class="mt-px size-4 shrink-0 text-gold-600" />
                            <div class="min-w-0">
                                <p>RAB {{ $label }} tersimpan ({{ $terlepas->jumlahItem() }} item, {{ Format::rupiah($terlepas->total()) }}) tetapi tidak ikut dicetak selama {{ $label }} tidak dipilih.</p>
                                @if ($this->dapatDiedit())
                                    <button type="button" wire:click="pilihSumberLagi('{{ $kunci }}')" class="mt-1.5 font-semibold text-brand-700 hover:text-brand-800">Pilih {{ $label }} lagi</button>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            </article>
        @endforeach
    </div>

    @error('form.sumber_dana') <p class="form-error" role="alert">{{ $message }}</p> @enderror

    <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-stone-50 px-4 py-3 ring-1 ring-stone-200">
        <p class="text-xs text-stone-600">
            @if (count($dipilih) === 2)
                Total pagu hanya ringkasan — setiap RAB dibandingkan dengan pagu sumber dananya sendiri.
            @else
                Total RAB nanti dibandingkan dengan pagu; muncul peringatan bila melebihi.
            @endif
        </p>
        <div class="text-right text-sm text-stone-600">
            <p>Total pagu <span class="font-bold whitespace-nowrap text-stone-900 tabular-nums" data-testid="total-pagu" x-text="$rupiah(totalPagu)">{{ Format::rupiah($form->totalPagu()) }}</span></p>
            <p class="text-xs font-medium text-gold-700" data-testid="jumlah-pagu-kosong" x-show="paguKosong.length" @style(['display: none' => $paguKosong === []])
               x-text="`${paguKosong.length} pagu belum diisi`">{{ count($paguKosong) }} pagu belum diisi</p>
        </div>
    </div>
</div>
