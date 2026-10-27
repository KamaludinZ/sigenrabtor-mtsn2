@use('App\Support\Dokumen\ProsesPdfBuku')

@php
    // Status pembuatan PDF buku terakhir (PembuatPdfBuku): dipantau berkala selama berjalan.
    $pdf = $pekerjaan['pdf'];
    $berjalan = ProsesPdfBuku::berjalan($pdf);
    $gaya = [
        'antre' => ['Dalam antrean', 'bg-stone-100 text-stone-700 ring-stone-200'],
        'proses' => ['Diproses', 'bg-sky-50 text-sky-800 ring-sky-200'],
        'selesai' => ['Selesai', 'bg-brand-50 text-brand-800 ring-brand-200'],
        'gagal' => ['Gagal', 'bg-red-50 text-red-700 ring-red-200'],
    ][$pdf['status']];
@endphp

<section class="mt-6 rounded-2xl bg-white p-4 shadow-xs ring-1 ring-stone-200 sm:p-5" aria-labelledby="judul-status-pdf" data-testid="status-pdf" data-status="{{ $pdf['status'] }}"
         wire:key="status-pdf-{{ $pekerjaan['id'] }}-{{ $pdf['status'] }}" @if ($berjalan) wire:poll.750ms="pantauPdf" @endif>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <h2 id="judul-status-pdf" class="flex flex-wrap items-center gap-2 text-sm font-semibold text-stone-900">
                @if ($berjalan)
                    <x-ui.icon name="loader" class="size-4 animate-spin text-brand-700" />
                @endif
                PDF buku
                <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset {{ $gaya[1] }}">{{ $gaya[0] }}</span>
            </h2>
            <p class="mt-0.5 text-xs text-stone-500">
                Dimulai {{ \Illuminate\Support\Carbon::parse($pekerjaan['dibuat_pada'])->locale('id')->translatedFormat('j M Y, H.i') }} oleh {{ $pekerjaan['dibuat_oleh'] }} · {{ $pekerjaan['jumlah_kegiatan'] }} kegiatan · ± {{ $pekerjaan['halaman'] }} halaman{{ $pekerjaan['ada_draf'] ? ' · berisi dokumen DRAF' : '' }}
            </p>
        </div>
        <div class="flex shrink-0 flex-wrap items-center gap-2">
            @if ($pdf['status'] === 'selesai' && $pekerjaan['file_pdf_path'])
                <a href="{{ route('buku.unduh', ['buku' => $pekerjaan['id']]) }}" class="btn-primary px-3 py-1.5 text-sm" data-testid="unduh-pdf-buku">
                    <x-ui.icon name="download" class="size-4" /> Unduh PDF
                </a>
            @elseif ($pdf['status'] === 'gagal')
                <button type="button" wire:click="gabungPdf" class="btn-secondary px-3 py-1.5 text-sm" data-testid="ulangi-pdf-buku">Coba lagi</button>
            @endif
        </div>
    </div>

    <div class="mt-3 h-2 overflow-hidden rounded-full bg-stone-100" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $pdf['persen'] }}" aria-label="Kemajuan pembuatan PDF">
        <div @class(['h-full rounded-full transition-all duration-500', 'bg-red-500' => $pdf['status'] === 'gagal', 'bg-brand-600' => $pdf['status'] !== 'gagal']) style="width: {{ $pdf['status'] === 'gagal' ? max($pdf['persen'], 8) : $pdf['persen'] }}%"></div>
    </div>

    <ol class="mt-3 grid gap-2 text-xs sm:grid-cols-5" data-testid="tahap-pdf">
        @foreach (ProsesPdfBuku::langkah($pdf) as $l)
            <li class="flex items-start gap-1.5" data-tahap="{{ $l['kunci'] }}" data-keadaan="{{ $l['keadaan'] }}">
                @switch($l['keadaan'])
                    @case('selesai')
                        <x-ui.icon name="check-circle" class="mt-px size-4 shrink-0 text-brand-600" />
                        @break
                    @case('berjalan')
                        <x-ui.icon name="loader" class="mt-px size-4 shrink-0 animate-spin text-sky-600" />
                        @break
                    @case('gagal')
                        <x-ui.icon name="alert" class="mt-px size-4 shrink-0 text-red-600" />
                        @break
                    @default
                        <x-ui.icon name="circle" class="mt-px size-4 shrink-0 text-stone-300" />
                @endswitch
                <span @class(['text-stone-800 font-medium' => in_array($l['keadaan'], ['berjalan', 'gagal'], true), 'text-stone-600' => $l['keadaan'] === 'selesai', 'text-stone-400' => $l['keadaan'] === 'menunggu'])>{{ $l['label'] }}</span>
            </li>
        @endforeach
    </ol>

    @if ($pdf['pesan'])
        <p @class(['mt-3 rounded-lg px-3 py-2 text-xs ring-1', 'bg-red-50 text-red-700 ring-red-200' => $pdf['status'] === 'gagal', 'bg-brand-50 text-brand-800 ring-brand-200' => $pdf['status'] !== 'gagal']) role="{{ $pdf['status'] === 'gagal' ? 'alert' : 'status' }}" data-testid="pesan-pdf">{{ $pdf['pesan'] }}</p>
    @endif
</section>
