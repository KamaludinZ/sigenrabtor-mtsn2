@use('App\Support\Kegiatan\KonteksAi')

@php
    $aspek = KonteksAi::aspekTerisi($form->konteks);
    $nip = KonteksAi::peringatanPrivasi($form->konteks) !== null;
    $dariIsian = KonteksAi::dariIsian($form->dataUntukDisimpan());
    $terisi = count(array_filter($dariIsian, fn ($b) => $b['nilai'] !== null));
    $contoh = KonteksAi::contoh($form->nama_kegiatan);
@endphp

{{--
    Konteks kegiatan: catatan bebas leading sebagai bahan "Buat dengan AI" (tidak tercetak). Panduan empat aspek membantu
    menulis catatan yang berguna; panel kanan menunjukkan apa yang sudah diketahui AI dari isian lain agar tidak diulang.
--}}
<div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_18rem]" data-testid="bagian-konteks"
     x-data="catatanKonteks({ aspek: @js(KonteksAi::ASPEK), kunci: @js('sirabtor:konteks:'.($kegiatanId ?? 'baru')), simpanOtomatis: @js($kegiatanId !== null && $this->dapatDiedit()), tersimpanPada: @js($konteksTersimpanPada) })"
     x-on:input="ketik($event)" x-on:kegiatan-tersimpan.window="tersimpan()" x-on:konteks-tersimpan.window="konteksTersimpan($event)">
    <div class="min-w-0 space-y-3">
        {{-- Draf lokal: ketikan yang belum sempat tersimpan (halaman tertutup/koneksi putus) bisa dipulihkan. --}}
        <div x-show="draf" x-cloak class="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-gold-50 px-3 py-2.5 text-sm text-stone-800 ring-1 ring-gold-200"
             role="status" data-testid="draf-konteks">
            <span class="flex items-start gap-2">
                <x-ui.icon name="history" class="mt-0.5 size-4 shrink-0 text-gold-600" />
                <span>Ada catatan konteks yang belum tersimpan di perangkat ini (<span x-text="waktuDraf()"></span>).</span>
            </span>
            <span class="flex gap-2">
                <button type="button" x-on:click="abaikan()" class="btn-ghost px-2 py-1 text-xs">Abaikan</button>
                <button type="button" x-on:click="pulihkan()" class="btn-secondary px-2.5 py-1 text-xs">Pulihkan</button>
            </span>
        </div>

        <x-ui.uraian id="catatan_konteks" label="Catatan konteks untuk AI" model="form.konteks" :max="3000" :rows="5"
                     placeholder="Contoh: kegiatan lanjutan dari workshop tahun lalu; peserta 49 guru dan 6 tenaga kependidikan; narasumber pengawas madrasah; output berupa dokumen KTSP yang disahkan kepala madrasah."
                     hint="Opsional dan tidak tercetak di dokumen. Dipakai tombol “Buat dengan AI” pada latar belakang, tujuan, sasaran, pelaksanaan, jadwal, dan nama cover.">
            <x-slot:actions>
                <span class="hidden text-[11px] text-stone-400 sm:inline">Sisipkan:</span>
                @foreach (KonteksAi::ASPEK as $kunci => [$label, $awalan])
                    {{-- Menambah baris berawalan aspek di akhir catatan lalu menaruh kursor di sana. --}}
                    <button type="button" data-sisip="{{ $kunci }}" title="Tambah baris “{{ trim($awalan) }}”"
                            x-on:click="const ta = $refs.ta; ta.value = (ta.value.trim() ? ta.value.replace(/\s*$/, '\n') : '') + @js($awalan); n = ta.value.length; ta.dispatchEvent(new Event('input', { bubbles: true })); ta.focus(); ta.setSelectionRange(ta.value.length, ta.value.length)"
                            class="rounded-md px-1.5 py-0.5 text-[11px] font-medium whitespace-nowrap text-stone-500 ring-1 ring-stone-200 ring-inset hover:bg-stone-100 hover:text-stone-800">
                        + {{ strtok($label, ' ') }}
                    </button>
                @endforeach
            </x-slot:actions>
        </x-ui.uraian>

        @if ($konteksDikosongkan !== null && trim($form->konteks) === '')
            <div class="-mt-1 flex flex-wrap items-center justify-between gap-2 rounded-lg bg-stone-800 px-3 py-2 text-xs text-white" role="status" data-testid="konteks-dikosongkan">
                <span>Catatan konteks dikosongkan — “Buat dengan AI” hanya memakai isian lain, hasilnya lebih umum.</span>
                <button type="button" wire:click="urungkanKosongkanKonteks" class="shrink-0 font-semibold text-gold-300 hover:text-gold-200">Urungkan</button>
            </div>
        @endif

        {{-- Status penyimpanan catatan (simpan otomatis untuk kegiatan tersimpan; draf lokal untuk kegiatan baru). --}}
        <p class="-mt-1 flex items-center gap-1.5 text-[11px] text-stone-500" data-testid="status-simpan-konteks" aria-live="polite"
           x-bind:data-simpan="simpan">
            <x-ui.icon name="save" class="size-3.5 text-stone-400" />
            <span x-text="teksSimpan()">{{ $kegiatanId ? ($konteksTersimpanPada ? "Tersimpan otomatis pukul {$konteksTersimpanPada}." : 'Tersimpan otomatis beberapa saat setelah berhenti mengetik.') : 'Tersimpan di perangkat ini sampai kegiatan disimpan.' }}</span>
        </p>

        {{-- Peringatan NIP ikut tiap ketukan (dihitung di klien; awal dari server). --}}
        <p x-show="nip" @unless ($nip) style="display: none" @endunless data-testid="peringatan-nip" data-nip="{{ $nip ? 'ya' : 'tidak' }}" x-bind:data-nip="nip ? 'ya' : 'tidak'"
           class="-mt-1 flex items-start gap-1.5 text-xs font-medium text-gold-700" role="alert">
            <x-ui.icon name="alert" class="mt-px size-3.5 shrink-0" />
            Catatan memuat NIP. Hapus dari sini — NIP disembunyikan otomatis dan tidak pernah dikirim ke penyedia AI.
        </p>

        {{-- Panduan aspek: tanda centang bila catatan sudah menyinggungnya (ikut tiap ketukan). --}}
        <div class="rounded-xl bg-stone-50 p-3 ring-1 ring-stone-200" data-testid="panduan-konteks">
            <p class="text-xs font-semibold text-stone-600">Catatan yang membantu AI biasanya menyebut:</p>
            <ul class="mt-2 grid gap-2 text-xs sm:grid-cols-2">
                @foreach (KonteksAi::ASPEK as $kunci => [$label])
                    <li class="flex items-center gap-1.5" data-aspek="{{ $kunci }}" data-terisi="{{ $aspek[$kunci] ? 'ya' : 'tidak' }}"
                        x-bind:data-terisi="terisi[@js($kunci)] ? 'ya' : 'tidak'">
                        <span x-show="terisi[@js($kunci)]" @unless ($aspek[$kunci]) style="display: none" @endunless>
                            <x-ui.icon name="check-circle" class="size-4 shrink-0 text-brand-600" />
                        </span>
                        <span x-show="! terisi[@js($kunci)]" @if ($aspek[$kunci]) style="display: none" @endif>
                            <x-ui.icon name="circle" class="size-4 shrink-0 text-stone-300" />
                        </span>
                        <span class="min-w-0">
                            <span @class(['block font-medium', 'text-stone-800' => $aspek[$kunci], 'text-stone-500' => ! $aspek[$kunci]])
                                  x-bind:class="terisi[@js($kunci)] ? 'text-stone-800' : 'text-stone-500'">{{ $label }}</span>
                            <span class="block text-[11px] text-stone-400" data-contoh-aspek="{{ $kunci }}">{{ KonteksAi::CONTOH_ASPEK[$kunci] }}</span>
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>

        {{-- Contoh catatan utuh per jenis kegiatan; yang cocok dengan nama kegiatan di urutan pertama. --}}
        <details class="group rounded-xl bg-white ring-1 ring-stone-200" wire:ignore.self data-testid="contoh-konteks">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-2 px-3 py-2.5 text-xs font-semibold text-brand-700 hover:text-brand-800">
                <span class="flex items-center gap-1.5">
                    <x-ui.icon name="note" class="size-4" /> Lihat contoh catatan ({{ count($contoh) }})
                    @if ($contoh[0]['cocok'])
                        <span class="rounded-full bg-brand-50 px-1.5 py-px text-[10px] text-brand-800 ring-1 ring-brand-200">ada yang cocok</span>
                    @endif
                </span>
                <x-ui.icon name="chevron-down" class="size-4 transition group-open:rotate-180" />
            </summary>
            <div class="grid gap-2 border-t border-stone-100 p-3 sm:grid-cols-2">
                @foreach ($contoh as $c)
                    <article class="flex flex-col rounded-lg bg-stone-50 p-3 ring-1 ring-stone-200" data-contoh="{{ $c['kunci'] }}" wire:key="contoh-{{ $c['kunci'] }}">
                        <h4 class="flex flex-wrap items-center gap-1.5 text-xs font-semibold text-stone-800">
                            {{ $c['judul'] }}
                            @if ($c['cocok'])
                                <span class="rounded-full bg-brand-50 px-1.5 py-px text-[10px] font-semibold text-brand-800 ring-1 ring-brand-200">Cocok dengan nama kegiatan</span>
                            @endif
                        </h4>
                        <p class="mt-1.5 flex-1 text-xs leading-relaxed whitespace-pre-line text-stone-600">{{ $c['teks'] }}</p>
                        <button type="button" x-on:click="pakaiContoh(@js($c['teks']))" class="btn-ghost mt-2 self-start px-2 py-1 text-xs">
                            <x-ui.icon name="copy" class="size-3.5" /> Pakai contoh
                        </button>
                    </article>
                @endforeach
            </div>
            <p class="border-t border-stone-100 px-3 py-2 text-[11px] text-stone-500">“Pakai contoh” menambahkan teks di bawah catatan yang sudah ada — sesuaikan angka dan kebutuhan kegiatan Anda.</p>
        </details>

        <p class="flex items-start gap-2 rounded-lg bg-stone-50 px-3 py-2 text-xs text-stone-600 ring-1 ring-stone-200">
            <x-ui.icon name="lock" class="mt-px size-4 shrink-0 text-stone-400" />
            Jangan menuliskan NIP atau nama pejabat di sini. Data pejabat tidak pernah dikirim ke penyedia AI, dan NIP yang terlanjur tertulis disembunyikan otomatis.
        </p>
    </div>

    {{-- Yang sudah diketahui AI dari isian lain --}}
    <aside class="rounded-xl bg-white p-4 ring-1 ring-stone-200" data-testid="bahan-ai">
        <p class="flex items-center gap-1.5 text-[11px] font-semibold tracking-wider text-stone-400 uppercase">
            <x-ui.icon name="sparkles" class="size-3.5" /> Sudah diketahui AI
        </p>
        <p class="mt-1 text-xs text-stone-500">Diambil dari isian lain ({{ $terisi }}/{{ count($dariIsian) }}) — tidak perlu ditulis ulang di catatan.</p>
        <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-xs lg:grid-cols-1">
            @foreach ($dariIsian as $b)
                <div>
                    <dt class="text-stone-400">{{ $b['label'] }}</dt>
                    <dd @class(['text-stone-800' => $b['nilai'] !== null, 'text-stone-400 italic' => $b['nilai'] === null])>{{ $b['nilai'] ?? 'belum diisi' }}</dd>
                </div>
            @endforeach
        </dl>
        <p class="mt-3 border-t border-stone-100 pt-2.5 text-xs" data-testid="status-catatan-ai">
            <span class="text-stone-400">Catatan leading:</span>
            <span x-show="teks.trim() !== ''" @if (trim($form->konteks) === '') style="display: none" @endif class="text-stone-800">
                ikut terkirim (<span x-text="$angka(teks.length)">{{ mb_strlen($form->konteks) }}</span> karakter)
            </span>
            <span x-show="teks.trim() === ''" @if (trim($form->konteks) !== '') style="display: none" @endif class="text-gold-700">kosong — AI hanya memakai isian di atas</span>
        </p>
    </aside>
</div>
