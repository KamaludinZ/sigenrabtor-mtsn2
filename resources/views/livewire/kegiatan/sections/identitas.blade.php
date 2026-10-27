@php
    $peringatan = $form->peringatan();
    $tahunDok = $this->tahunAktif['tahun'];
    $mulaiDok = $form->tanggalMulai();
    $selesaiDok = $form->tanggalSelesai();
@endphp

<div class="grid gap-5 sm:grid-cols-2">
    {{-- Ambil dari Data Pagu: nama kegiatan, leading, dan pagu DIPA/Komite terisi sekaligus (tetap bisa diubah). --}}
    @php
        $paguTertaut = $this->dataPaguTertaut();
    @endphp
    @if ($this->dapatDiedit() && ($this->opsiDataPagu !== [] || $paguTertaut))
        <div class="rounded-xl bg-brand-50/60 p-4 ring-1 ring-brand-100 sm:col-span-2" data-testid="ambil-data-pagu">
            @if ($paguTertaut)
                <p class="mb-3 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-brand-900" data-testid="data-pagu-tertaut">
                    <x-ui.icon name="check-circle" class="size-4 shrink-0 text-brand-600" />
                    <span>Dari Data Pagu: <span class="font-semibold">{{ $paguTertaut['nama_kegiatan'] }}</span></span>
                    <span class="text-xs text-brand-800/80">{{ \App\Support\Pagu\DataPagu::keterangan($paguTertaut) }}</span>
                    <button type="button" wire:click="lepasDataPagu" class="text-xs font-semibold text-brand-700 hover:text-brand-800">Lepas tautan</button>
                </p>
            @endif
            <label for="pilih-data-pagu" class="form-label">{{ $paguTertaut ? 'Ganti dari Data Pagu' : 'Ambil dari Data Pagu' }}</label>
            <x-ui.pilih-cari id="pilih-data-pagu" model="pilihanPagu" :opsi="$this->opsiDataPagu" placeholder="Pilih nama kegiatan dari Data Pagu…" cari-placeholder="Ketik nama kegiatan…" />
            <p class="form-hint">Nama kegiatan, leading, dan pagu DIPA/Komite terisi otomatis; masih bisa diubah. Hanya nama yang belum menjadi kegiatan.</p>
        </div>
    @endif

    <x-ui.field class="sm:col-span-2" label="Nama kegiatan" for="nama_kegiatan" error="form.nama_kegiatan" required
                :warning="$peringatan['nama_kegiatan'] ?? null"
                hint="Tulis tanpa awalan “Kegiatan”, mis. Workshop Penyusunan Kurikulum Madrasah dan Perangkat Pembelajaran.">
        <input type="text" id="nama_kegiatan" wire:model.live.debounce.500ms="form.nama_kegiatan" maxlength="255"
               autocomplete="off" class="form-input" placeholder="Nama kegiatan sesuai cascade Perkin"
               @error('form.nama_kegiatan') aria-invalid="true" aria-describedby="nama_kegiatan-error" @enderror>
    </x-ui.field>

    <x-ui.field class="sm:col-span-2" label="Nama kegiatan (cover)" for="nama_kegiatan_cover" error="form.nama_kegiatan_cover" required
                hint="Selalu huruf kapital; tercetak di cover KAK/TOR dan judul RAB.">
        <x-slot:aside>
            @if ($form->cover_manual)
                <button type="button" wire:click="resetNamaCover" class="font-semibold text-brand-700 hover:text-brand-800">
                    Samakan dengan nama kegiatan
                </button>
            @else
                <span class="inline-flex items-center gap-1 rounded bg-brand-50 px-1.5 py-0.5 font-semibold text-brand-700">
                    <x-ui.icon name="check" class="size-3" /> Otomatis
                </span>
            @endif
        </x-slot:aside>
        <input type="text" id="nama_kegiatan_cover" wire:model.live.blur="form.nama_kegiatan_cover"
               x-on:input.capture="const [a, b] = [$el.selectionStart, $el.selectionEnd]; $el.value = $el.value.toUpperCase(); $el.setSelectionRange(a, b)" maxlength="255"
               autocomplete="off" class="form-input uppercase tracking-wide" placeholder="Terisi otomatis dari nama kegiatan"
               @error('form.nama_kegiatan_cover') aria-invalid="true" aria-describedby="nama_kegiatan_cover-error" @enderror>
    </x-ui.field>

    <x-ui.uraian class="sm:col-span-2" id="latar_belakang" label="Latar belakang (paragraf khusus)" model="form.latar_belakang"
                 :max="5000" :rows="4" placeholder="Alasan khusus kegiatan ini diselenggarakan (opsional)."
                 hint="Tercetak di antara paragraf baku visi madrasah dan kalimat penutup kegiatan — keduanya diatur admin per tahun anggaran." />

    {{-- Susunan bagian 1 KAK: paragraf baku & kalimat penutup dari Pengaturan › Dasar hukum, terisi data kegiatan ini. --}}
    @php
        $latarKak = $this->susunanLatar;
    @endphp
    <details class="-mt-3 rounded-lg bg-stone-50 ring-1 ring-stone-200 sm:col-span-2" data-testid="susunan-latar">
        <summary class="cursor-pointer px-3 py-2 text-xs font-semibold text-stone-600 hover:text-stone-900">Susunan latar belakang di KAK/TOR</summary>
        <div class="space-y-1.5 border-t border-stone-200 px-3 py-2.5 font-serif text-[13px] leading-relaxed text-stone-700">
            <p class="line-clamp-2 text-stone-500" title="{{ $latarKak['baku'] }}" data-latar="baku">{{ $latarKak['baku'] }}</p>
            <p data-latar="khusus" x-text="($wire.form.latar_belakang ?? '').trim() || '[Paragraf khusus di atas — opsional]'"
               x-bind:class="{ 'text-stone-400 italic': ! ($wire.form.latar_belakang ?? '').trim() }">{{ trim((string) $form->latar_belakang) ?: '[Paragraf khusus di atas — opsional]' }}</p>
            <p class="text-stone-900" data-latar="penutup">{{ $latarKak['penutup'] }}</p>
        </div>
        @if ($latarKak['kosong'] !== [])
            <p class="flex items-start gap-1.5 px-3 pb-1 text-xs text-gold-700" data-testid="latar-kosong">
                <x-ui.icon name="alert" class="mt-px size-3.5 shrink-0" /> Lengkapi {{ mb_strtolower(implode(', ', $latarKak['kosong'])) }} agar kalimat penutup tercetak utuh.
            </p>
        @endif
        <p class="px-3 pb-2 text-xs text-stone-500">Paragraf baku &amp; kalimat penutup diatur admin di Pengaturan › Profil &amp; Pejabat › Dasar hukum.</p>
    </details>

    <x-ui.uraian class="sm:col-span-2" id="tujuan" label="Tujuan" model="form.tujuan" :max="2000"
                 :warning="$peringatan['tujuan'] ?? null"
                 placeholder="mis. menghasilkan dokumen kurikulum yang terlegitimasi sesuai prinsip partisipatif, transparan, dan akuntabel."
                 hint="Wajib untuk dokumen lengkap. Tercetak sebagai: “Kegiatan {nama} bertujuan {tujuan}”." />

    <x-ui.uraian class="sm:col-span-2" id="sasaran" label="Sasaran" model="form.sasaran" :max="2000"
                 :warning="$peringatan['sasaran'] ?? null"
                 placeholder="mis. dokumen perencanaan yang handal dan akurat sesuai kebutuhan riil madrasah."
                 hint="Wajib untuk dokumen lengkap. Tercetak sebagai: “Terwujudnya {sasaran}”." />

    <x-ui.field label="Tanggal dokumen" for="tanggal_dokumen" error="form.tanggal_dokumen"
                :hint="\App\Support\Format::tanggal($form->tanggal_dokumen) ? 'Tercetak: '.\App\Support\Format::tanggal($form->tanggal_dokumen) : 'Tanggal pada lembar pengesahan.'">
        <input type="date" id="tanggal_dokumen" wire:model.live="form.tanggal_dokumen" class="form-input"
               min="{{ $tahunDok - 1 }}-01-01" max="{{ $mulaiDok?->toDateString() ?? $tahunDok.'-12-31' }}"
               @error('form.tanggal_dokumen') aria-invalid="true" aria-describedby="tanggal_dokumen-error" @enderror>
    </x-ui.field>

    <x-ui.field label="Tanggal pelaporan" for="tanggal_laporan" error="form.tanggal_laporan"
                :warning="$peringatan['tanggal_laporan'] ?? null"
                :hint="(\App\Support\Format::tanggal($form->tanggal_laporan) ? 'Tercetak: '.\App\Support\Format::tanggal($form->tanggal_laporan).'. ' : 'Batas penyerahan laporan ke kepala madrasah. ')
                    .($form->laporan_otomatis ? 'Otomatis 10 hari setelah kegiatan selesai.' : 'Diatur manual — kosongkan untuk kembali otomatis.')">
        <input type="date" id="tanggal_laporan" wire:model.live="form.tanggal_laporan" class="form-input"
               @if ($selesaiDok) min="{{ $selesaiDok->toDateString() }}" @endif
               @error('form.tanggal_laporan') aria-invalid="true" aria-describedby="tanggal_laporan-error" @enderror>
    </x-ui.field>
</div>
