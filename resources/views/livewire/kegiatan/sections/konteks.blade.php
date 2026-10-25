@use('App\Support\Kegiatan\KonteksAi')

@php
    $aspek = KonteksAi::aspekTerisi($form->konteks);
    $dariIsian = KonteksAi::dariIsian($form->dataUntukDisimpan());
    $terisi = count(array_filter($dariIsian, fn ($b) => $b['nilai'] !== null));
@endphp

{{--
    Konteks kegiatan: catatan bebas leading sebagai bahan "Buat dengan AI" (tidak tercetak). Panduan empat aspek membantu
    menulis catatan yang berguna; panel kanan menunjukkan apa yang sudah diketahui AI dari isian lain agar tidak diulang.
--}}
<div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_18rem]" data-testid="bagian-konteks">
    <div class="min-w-0 space-y-3">
        <x-ui.uraian id="catatan_konteks" label="Catatan konteks untuk AI" model="form.konteks" :max="3000" :rows="5"
                     :warning="KonteksAi::peringatanPrivasi($form->konteks)"
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

        {{-- Panduan aspek: tanda centang bila catatan sudah menyinggungnya. --}}
        <div class="rounded-xl bg-stone-50 p-3 ring-1 ring-stone-200" data-testid="panduan-konteks">
            <p class="text-xs font-semibold text-stone-600">Catatan yang membantu AI biasanya menyebut:</p>
            <ul class="mt-2 grid gap-1.5 text-xs sm:grid-cols-2">
                @foreach (KonteksAi::ASPEK as $kunci => [$label])
                    <li class="flex items-center gap-1.5" data-aspek="{{ $kunci }}" data-terisi="{{ $aspek[$kunci] ? 'ya' : 'tidak' }}">
                        @if ($aspek[$kunci])
                            <x-ui.icon name="check-circle" class="size-4 shrink-0 text-brand-600" />
                            <span class="text-stone-800">{{ $label }}</span>
                        @else
                            <x-ui.icon name="circle" class="size-4 shrink-0 text-stone-300" />
                            <span class="text-stone-500">{{ $label }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>

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
    </aside>
</div>
