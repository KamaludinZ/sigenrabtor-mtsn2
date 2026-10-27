{{-- Rincian satu kegiatan tahun lalu untuk menimbang sebelum diduplikat. --}}
<dl class="grid gap-x-6 gap-y-1.5 sm:grid-cols-[8rem_minmax(0,1fr)]" data-rincian-lalu="{{ $b['id'] }}">
    <dt class="font-semibold text-stone-600">Tujuan</dt>
    <dd class="text-stone-700">{{ $b['tujuan'] ? \Illuminate\Support\Str::limit($b['tujuan'], 220) : '—' }}</dd>
    <dt class="font-semibold text-stone-600">Tempat</dt>
    <dd class="text-stone-700">{{ $b['tempat'] ?? '—' }}</dd>
    <dt class="font-semibold text-stone-600">Peserta</dt>
    <dd class="text-stone-700">{{ $b['peserta'] ?? '—' }}</dd>
    <dt class="font-semibold text-stone-600">Lama</dt>
    <dd class="text-stone-700">{{ $b['lama_hari'] ? $b['lama_hari'].' hari' : '—' }}</dd>
</dl>
