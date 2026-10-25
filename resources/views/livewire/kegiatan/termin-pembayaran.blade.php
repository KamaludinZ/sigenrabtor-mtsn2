@use('App\Support\Format')
@use('App\Support\Rab\MekanismePembayaran')
@use('App\Support\Stub\StubData')
@use('App\Support\Terbilang')

@php
    $mekanisme = $this->mekanisme;
    $bisaUbah = $this->dapatDiedit();
    $total = $this->anggaran->total();
    $tahun = StubData::tahunAktif()['tahun'];
    // Warna & ikon status; dipilih ulang di klien tiap ketukan nominal (lihat resources/js/termin.js).
    $kelasStatus = [
        'sekaligus' => 'bg-stone-50 text-stone-700 ring-stone-200',
        'pas' => 'bg-brand-50 text-brand-900 ring-brand-200',
        'kurang' => 'bg-gold-50 text-stone-800 ring-gold-300',
        'lebih' => 'bg-red-50 text-red-800 ring-red-200',
    ];
    $ikonStatus = ['sekaligus' => 'info', 'pas' => 'check-circle', 'kurang' => 'alert', 'lebih' => 'alert'];
@endphp

{{--
    Termin pembayaran satu RAB (per sumber dana). Disunting di layar; "Simpan termin" menyimpan seluruh daftar.
    Jumlah termin dibandingkan dengan total RAB secara langsung, dan pratinjau blok "Mekanisme Pembayaran" mengikuti Form RAB.
--}}
<div class="mt-5 grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]" data-testid="termin-pembayaran"
     x-data="jumlahTermin({ total: @js($total) })"
     x-on:beforeunload.window="if ($wire.adaPerubahan) { $event.preventDefault(); $event.returnValue = ''; }">
    <section class="min-w-0 rounded-2xl bg-white shadow-xs ring-1 ring-stone-200">
        <header class="flex flex-wrap items-start justify-between gap-3 border-b border-stone-100 px-5 py-4 sm:px-6">
            <div class="min-w-0">
                <h2 class="flex flex-wrap items-center gap-2 text-base font-semibold text-stone-900">
                    Termin pembayaran <x-rab.lencana-sumber :sumber="$sumber" awalan="RAB" class="text-xs" />
                </h2>
                <p class="mt-0.5 text-sm text-stone-500">Opsional. Bila dibayar bertahap, jumlah semua termin harus sama dengan total RAB {{ $sumber }}.</p>
            </div>
            @if ($adaPerubahan)
                <span class="inline-flex items-center gap-1 text-xs font-medium text-gold-700" data-testid="termin-belum-disimpan" data-termin-belum-disimpan>
                    <span class="size-1.5 rounded-full bg-gold-500"></span> Belum disimpan
                </span>
            @endif
        </header>

        <div class="space-y-5 px-5 py-5 sm:px-6">
            @if (! $bisaUbah)
                {{-- Terkunci: tampilkan termin tersimpan sebagai tabel baca-saja. --}}
                <x-rab.tabel-termin :mekanisme="$mekanisme" :sumber="$sumber" />
            @else
            {{-- Mekanisme --}}
            <div class="grid gap-2 sm:grid-cols-2" role="radiogroup" aria-label="Mekanisme pembayaran">
                @foreach (['sekaligus' => ['Sekaligus', 'Dibayar satu kali sesuai total RAB; blok termin tidak dicetak.'], 'termin' => ['Bertahap (termin)', 'Dibayar beberapa kali pada tanggal tertentu.']] as $kunci => [$label, $keterangan])
                    @php $dipilih = ($kunci === 'termin') === $mekanisme->bertermin(); @endphp
                    <button type="button" role="radio" aria-checked="{{ $dipilih ? 'true' : 'false' }}" @disabled(! $bisaUbah)
                            @unless ($dipilih) wire:click="{{ $kunci === 'termin' ? 'bagiRata(2)' : 'jadikanSekaligus' }}" @endunless
                            data-mekanisme="{{ $kunci }}"
                            @class([
                                'rounded-xl p-3 text-left ring-1 transition disabled:cursor-not-allowed',
                                'bg-brand-50/60 ring-2 ring-brand-600' => $dipilih,
                                'bg-white ring-stone-200 hover:ring-stone-300' => ! $dipilih,
                            ])>
                        <span class="block text-sm font-semibold text-stone-900">{{ $label }}</span>
                        <span class="block text-xs text-stone-500">{{ $keterangan }}</span>
                    </button>
                @endforeach
            </div>

            @if ($terminSebelumSekaligus)
                <div class="flex items-center justify-between gap-3 rounded-lg bg-stone-800 px-3 py-2 text-sm text-white" role="status" data-testid="termin-dilepas">
                    <span>{{ count($terminSebelumSekaligus) }} termin dilepas — RAB {{ $sumber }} dibayar sekaligus setelah disimpan.</span>
                    <button type="button" wire:click="urungkanSekaligus" class="shrink-0 font-semibold text-gold-300 hover:text-gold-200">Urungkan</button>
                </div>
            @endif

            @if ($mekanisme->bertermin())
                {{-- Daftar termin --}}
                <div class="overflow-x-auto rounded-xl ring-1 ring-stone-200">
                    <table class="w-full min-w-[36rem] text-left text-sm" data-testid="tabel-termin">
                        <thead class="bg-stone-50 text-xs font-semibold text-stone-500">
                            <tr>
                                <th scope="col" class="w-20 px-3 py-2">Termin</th>
                                <th scope="col" class="px-3 py-2">Tanggal</th>
                                <th scope="col" class="px-3 py-2 text-right">Nominal</th>
                                <th scope="col" class="w-16 px-3 py-2 text-right">%</th>
                                @if ($bisaUbah)
                                    <th scope="col" class="w-12 px-3 py-2"><span class="sr-only">Aksi</span></th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100">
                            @foreach ($baris as $i => $b)
                                @php $nominal = (int) Format::parseRupiah($b['nominal']); @endphp
                                <tr wire:key="termin-{{ $b['key'] }}" class="align-top" data-termin="{{ $i + 1 }}">
                                    <th scope="row" class="px-3 py-3 font-semibold text-stone-700">Termin {{ $i + 1 }}</th>
                                    <td class="px-3 py-2">
                                        <label for="termin-tanggal-{{ $i }}" class="sr-only">Tanggal termin {{ $i + 1 }}</label>
                                        <input type="date" id="termin-tanggal-{{ $i }}" wire:model.live="baris.{{ $i }}.tanggal" @disabled(! $bisaUbah)
                                               min="{{ $tahun }}-01-01" max="{{ $tahun }}-12-31" class="form-input py-1.5"
                                               @error("baris.{$i}.tanggal") aria-invalid="true" @enderror>
                                        @php $akhirPekan = Format::akhirPekan($b['tanggal']); @endphp
                                        {{-- Tanggal terbaca dalam bahasa Indonesia, tidak bergantung format tanggal browser. --}}
                                        <p class="mt-1 text-xs text-stone-500" data-tanggal-terbaca
                                           x-text="$tanggalPanjang($wire.$get(@js('baris.'.$i.'.tanggal')))">{{ Format::tanggalPanjang($b['tanggal']) }}</p>
                                        <p class="mt-0.5 text-xs font-medium text-gold-700" data-akhir-pekan
                                           x-show="$akhirPekan($wire.$get(@js('baris.'.$i.'.tanggal')))" @unless ($akhirPekan) style="display: none" @endunless
                                           x-text="'Jatuh pada hari ' + $akhirPekan($wire.$get(@js('baris.'.$i.'.tanggal'))) + ' — pastikan pencairan bisa dilakukan.'">{{ $akhirPekan ? "Jatuh pada hari {$akhirPekan} — pastikan pencairan bisa dilakukan." : '' }}</p>
                                        @error("baris.{$i}.tanggal") <p class="form-error">{{ $message }}</p> @enderror
                                    </td>
                                    <td class="px-3 py-2">
                                        <label for="termin-nominal-{{ $i }}" class="sr-only">Nominal termin {{ $i + 1 }}</label>
                                        <x-ui.rupiah :id="'termin-nominal-'.$i" :model="'baris.'.$i.'.nominal'" :disabled="! $bisaUbah" terbilang :nilai-awal="$b['nominal']" class="py-1.5"
                                                     x-on:input="ketik(@js($b['key']), $event.target.value)" />
                                        @error("baris.{$i}.nominal") <p class="form-error">{{ $message }}</p> @enderror
                                    </td>
                                    <td class="px-3 py-3 text-right text-stone-500 tabular-nums" data-persen-termin x-text="teksPersen(@js($b['key']))">{{ str_replace('.', ',', (string) $mekanisme->persen($nominal)) }}%</td>
                                    @if ($bisaUbah)
                                        <td class="px-3 py-2.5 text-right">
                                            <button type="button" wire:click="hapusTermin('{{ $b['key'] }}')" title="Hapus termin {{ $i + 1 }}" aria-label="Hapus termin {{ $i + 1 }}"
                                                    class="rounded-md p-1.5 text-stone-400 hover:bg-red-50 hover:text-red-600">
                                                <x-ui.icon name="trash" class="size-4" />
                                            </button>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($terminTerhapus)
                    <div class="flex items-center justify-between gap-3 rounded-lg bg-stone-800 px-3 py-2 text-sm text-white" role="status" data-testid="termin-terhapus">
                        <span>Termin {{ $terminTerhapus['index'] + 1 }} ({{ $terminTerhapus['baris']['nominal'] !== '' ? 'Rp'.$terminTerhapus['baris']['nominal'] : 'tanpa nominal' }}) dihapus.</span>
                        <button type="button" wire:click="urungkanHapus" class="shrink-0 font-semibold text-gold-300 hover:text-gold-200">Urungkan</button>
                    </div>
                @endif

                @if ($bisaUbah)
                    <div class="flex flex-wrap gap-2">
                        <button type="button" wire:click="tambahTermin" class="btn-secondary" @disabled(count($baris) >= MekanismePembayaran::MAKS_TERMIN)>
                            <x-ui.icon name="plus" class="size-4" /> Tambah termin
                        </button>
                        <button type="button" wire:click="bagiRata" class="btn-ghost" title="Bagi total RAB sama rata ke {{ count($baris) }} termin">
                            Bagi rata {{ count($baris) }} termin
                        </button>
                        @if (count($baris) > 1 && $this->urutanTanggalAcak())
                            <button type="button" wire:click="urutkanTanggal" class="btn-ghost" data-testid="urutkan-tanggal">
                                <x-ui.icon name="calendar" class="size-4" /> Urutkan menurut tanggal
                            </button>
                        @endif
                        @if (in_array($mekanisme->status(), ['kurang', 'lebih'], true))
                            <button type="button" wire:click="isiSisa" class="btn-ghost" data-testid="isi-sisa">
                                Sesuaikan termin terakhir ({{ $mekanisme->selisih() > 0 ? '+' : '−' }}{{ Format::rupiah(abs($mekanisme->selisih())) }})
                            </button>
                        @endif
                    </div>
                @endif
            @endif

            @endif

            {{-- Status jumlah termin terhadap total RAB --}}
            <div class="grid gap-3 rounded-xl px-4 py-3 ring-1 sm:grid-cols-3" data-testid="status-termin" role="status" aria-live="polite"
                 data-status="{{ $mekanisme->status() }}" x-bind:data-status="hasil.status"
                 x-bind:class="@js($kelasStatus)[hasil.status]">
                <div>
                    <p class="text-xs opacity-75">Total RAB {{ $sumber }}</p>
                    <p class="font-semibold tabular-nums">{{ Format::rupiah($total) }}</p>
                </div>
                <div>
                    <p class="text-xs opacity-75">Jumlah termin</p>
                    <p class="font-semibold tabular-nums" data-testid="jumlah-termin"
                       x-text="hasil.status === 'sekaligus' ? '—' : $rupiah(hasil.jumlah)">{{ $mekanisme->bertermin() ? Format::rupiah($mekanisme->jumlah()) : '—' }}</p>
                </div>
                <p class="flex items-start gap-1.5 text-sm font-medium sm:self-center">
                    @foreach (array_unique($ikonStatus) as $ikon)
                        <span x-show="@js($ikonStatus)[hasil.status] === @js($ikon)" @if ($ikonStatus[$mekanisme->status()] !== $ikon) style="display: none" @endif>
                            <x-ui.icon :name="$ikon" class="mt-0.5 size-4 shrink-0" />
                        </span>
                    @endforeach
                    <span data-testid="teks-status-termin" x-text="hasil.teks">{{ $mekanisme->teksStatus() }}</span>
                </p>
            </div>

            @error('termin')
                <p class="flex items-start gap-2 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 ring-1 ring-red-200" role="alert" data-testid="galat-termin">
                    <x-ui.icon name="alert" class="mt-0.5 size-4 shrink-0" /> {{ $message }}
                </p>
            @enderror

            @if ($total === 0 && $mekanisme->bertermin())
                <p class="text-xs text-gold-700">RAB {{ $sumber }} belum berisi rincian; termin baru bisa pas setelah rincian belanja diisi.</p>
            @endif
        </div>

        <footer class="flex flex-col-reverse gap-2 border-t border-stone-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-end sm:px-6">
            @if ($bisaUbah)
                @if ($adaPerubahan)
                    <button type="button" wire:click="batalkan" class="btn-ghost">Batalkan perubahan</button>
                @endif
                <button type="button" wire:click="simpan" class="btn-primary" @disabled(! $adaPerubahan) data-testid="simpan-termin">
                    <x-ui.icon name="save" class="size-4" /> Simpan termin
                </button>
            @else
                <p class="flex items-center gap-2 text-xs text-stone-500">
                    <x-ui.icon name="lock" class="size-3.5" /> Termin terkunci karena kegiatan berstatus {{ $this->status->label() }}.
                </p>
            @endif
        </footer>
    </section>

    {{-- Grid jadwal pembayaran semua sumber dana (sumber yang dibuka disorot) --}}
    <x-rab.jadwal-termin :jadwal="$this->jadwal" :tahun="$tahun" :aktif="$sumber" class="order-last min-w-0 lg:col-span-2" />

    {{-- Pratinjau blok dokumen --}}
    <aside class="space-y-4">
        <div class="rounded-2xl bg-white p-4 shadow-xs ring-1 ring-stone-200" data-testid="pratinjau-mekanisme">
            <p class="flex items-center gap-1.5 text-[11px] font-semibold tracking-wider text-stone-400 uppercase">
                <x-ui.icon name="file-text" class="size-3.5" /> Tercetak di RAB {{ $sumber }}
            </p>
            @if ($mekanisme->bertermin())
                <dl class="mt-3 space-y-1 font-serif text-[13px] text-stone-800">
                    @foreach ($mekanisme->barisDokumen() as $b)
                        <div class="grid grid-cols-[minmax(0,11rem)_auto_1fr] gap-x-1.5">
                            <dt>{{ $b['label'] }}</dt><dd aria-hidden="true">:</dd><dd class="tabular-nums">{{ $b['nilai'] }}</dd>
                        </div>
                    @endforeach
                </dl>
                @if ($mekanisme->status() !== 'pas')
                    <p class="mt-3 text-xs text-gold-700">Dokumen bertanda DRAF sampai jumlah termin sama dengan total RAB.</p>
                @endif
            @else
                <p class="mt-2 text-sm text-stone-500">Tidak ada blok termin — pembayaran sekaligus sebesar {{ Format::rupiah($total) }}.</p>
            @endif
        </div>
        @if ($mekanisme->bertermin() && $total > 0)
            <div class="rounded-2xl bg-white p-4 text-xs text-stone-600 shadow-xs ring-1 ring-stone-200">
                <p class="font-semibold text-stone-700">Terbilang per termin</p>
                <ol class="mt-2 space-y-1">
                    @foreach ($mekanisme->termin as $i => $t)
                        <li><span class="text-stone-400">Termin {{ $i + 1 }}:</span> {{ Terbilang::tampil($t['nominal']) ?? '—' }}</li>
                    @endforeach
                </ol>
            </div>
        @endif
    </aside>
</div>
