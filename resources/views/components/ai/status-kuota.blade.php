@props(['status', 'ringkas' => false])

@use('App\Support\Kegiatan\StatusKuotaAi')

@php
    [$labelTingkat, $warnaTeks, $warnaBatang] = StatusKuotaAi::TINGKAT[$status['tingkat']];
    $kalimat = "Sisa {$status['sisa']} dari {$status['batas']} permintaan AI hari ini.";
@endphp

{{--
    Status kuota AI harian akun yang sedang masuk (App\Support\Kegiatan\StatusKuotaAi::untuk): $ringkas = lencana "Sisa
    n/m" (modal saran, panel usulan jadwal, Riwayat Saran AI); lengkap = meter + tingkat, waktu pulih, rincian per fitur.
--}}
@if ($ringkas)
    <span {{ $attributes->class(['inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset tabular-nums', $warnaTeks,
            'bg-brand-50 ring-brand-200' => $status['tingkat'] === 'aman', 'bg-gold-50 ring-gold-200' => $status['tingkat'] === 'menipis', 'bg-red-50 ring-red-200' => $status['tingkat'] === 'habis']) }}
          title="{{ $kalimat }} Kuota pulih pukul {{ $status['pulih'] }}." data-testid="kuota-ringkas" data-tingkat="{{ $status['tingkat'] }}">
        <x-ui.icon name="sparkles" class="size-3" /> Sisa {{ $status['sisa'] }}/{{ $status['batas'] }}
        <span class="sr-only">— {{ $kalimat }}</span>
    </span>
@else
    <div {{ $attributes }} data-testid="kuota-ai" data-tingkat="{{ $status['tingkat'] }}">
        <div class="flex items-baseline justify-between gap-2">
            <p class="text-xs text-stone-600" data-testid="sisa-ai">{{ $kalimat }}</p>
            <span class="shrink-0 text-[11px] font-semibold {{ $warnaTeks }}">{{ $labelTingkat }}</span>
        </div>
        <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-stone-100" role="progressbar" aria-valuemin="0" aria-valuemax="{{ $status['batas'] }}" aria-valuenow="{{ $status['terpakai'] }}" aria-label="Kuota AI terpakai hari ini">
            <div class="h-full rounded-full {{ $warnaBatang }}" style="width: {{ $status['persen'] }}%"></div>
        </div>
        <p class="mt-1.5 text-[11px] text-stone-500">
            @if ($status['tingkat'] === 'habis')
                Kuota hari ini habis — tombol AI aktif lagi besok (pulih pukul {{ $status['pulih'] }}).
            @else
                Kuota pulih setiap hari pukul {{ $status['pulih'] }}.
            @endif
            @if ($status['khusus'])
                Akun Anda memakai batas khusus dari admin.
            @endif
        </p>
        @if ($status['rincian'] !== [])
            <ul class="mt-2 space-y-0.5 text-[11px] text-stone-600" data-testid="rincian-kuota">
                @foreach ($status['rincian'] as $r)
                    <li class="flex justify-between gap-2"><span>{{ $r['label'] }}</span><span class="tabular-nums">{{ $r['jumlah'] }}×</span></li>
                @endforeach
            </ul>
        @endif
    </div>
@endif
