@extends('layouts.marketing')

@section('title', 'Detail '.$vehicle->brand.' '.$vehicle->model)

@section('content')
    @php($taxStatus = $vehicle->taxStatus())
    @php($leasingOptions = collect($vehicle->leasing_options ?? [])->filter(fn ($option) => is_array($option)))
    <a href="{{ route('marketing.stok.index') }}" class="mb-5 inline-flex items-center gap-2 text-sm font-semibold text-[var(--color-primary)]">
        ← Kembali ke katalog
    </a>

    <article class="overflow-hidden rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] shadow-sm">
        <div class="grid lg:grid-cols-2">
            <div class="min-h-56 bg-[var(--color-surface-primary-muted)]">
                @if ($vehicle->vehicle_photo_path)
                    <img class="h-full max-h-[440px] w-full object-cover"
                        src="{{ asset('storage/'.$vehicle->vehicle_photo_path) }}"
                        alt="{{ $vehicle->brand }} {{ $vehicle->model }}">
                @else
                    <div class="grid min-h-56 h-full place-items-center text-sm text-[var(--color-text-secondary)]">
                        Foto kendaraan belum tersedia
                    </div>
                @endif
            </div>
            <div class="grid content-start gap-5 p-5 sm:p-7">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-[var(--color-primary-eyebrow)]">{{ $vehicle->plate_number }}</p>
                        <h1 class="mt-1 text-2xl font-bold sm:text-3xl">{{ $vehicle->brand }} {{ $vehicle->model }}</h1>
                        <p class="mt-1 text-sm text-[var(--color-text-secondary)]">{{ $vehicle->year }} · {{ $vehicle->transmission }}</p>
                    </div>
                    <span class="status-badge {{ $taxStatus['badgeClass'] }}">
                        Pajak {{ $taxStatus['label'] }}
                    </span>
                </div>
                <div class="rounded-lg bg-[var(--color-primary-soft)] p-4">
                    <span class="block text-sm text-[var(--color-text-secondary)]">Harga penawaran / buka</span>
                    <strong class="text-xl text-[var(--color-primary)]">
                        {{ (float) $vehicle->opening_price > 0 ? 'Rp '.number_format((float) $vehicle->opening_price, 0, ',', '.') : 'Hubungi marketing' }}
                    </strong>
                </div>
                <dl class="grid grid-cols-1 gap-x-5 gap-y-3 text-sm sm:grid-cols-2">
                    <div><dt class="text-[var(--color-text-secondary)]">Warna</dt><dd class="font-semibold">{{ $vehicle->color ?: '—' }}</dd></div>
                    <div><dt class="text-[var(--color-text-secondary)]">Kilometer</dt><dd class="font-semibold">{{ $vehicle->mileage_km ? number_format((int) $vehicle->mileage_km, 0, ',', '.') : '—' }}</dd></div>
                    <div><dt class="text-[var(--color-text-secondary)]">Nomor rangka</dt><dd class="font-semibold">{{ $vehicle->chassis_number ?: '—' }}</dd></div>
                    <div><dt class="text-[var(--color-text-secondary)]">Nomor mesin</dt><dd class="font-semibold">{{ $vehicle->engine_number ?: '—' }}</dd></div>
                    <div><dt class="text-[var(--color-text-secondary)]">Nama pemilik</dt><dd class="font-semibold">{{ $vehicle->owner_name ?: '—' }}</dd></div>
                    <div><dt class="text-[var(--color-text-secondary)]">Tanggal masuk</dt><dd class="font-semibold">{{ $vehicle->buy_date ?: '—' }}</dd></div>
                </dl>
            </div>
        </div>

        <section class="border-t border-[var(--color-border)] p-5 sm:p-7" aria-labelledby="leasing-title">
            <div class="mb-4">
                <h2 id="leasing-title" class="text-lg font-bold">Simulasi angsuran leasing</h2>
                <p class="mt-1 text-sm text-[var(--color-text-secondary)]">Nilai berikut merupakan referensi berdasarkan data kendaraan.</p>
            </div>
            @if ($leasingOptions->isEmpty())
                <p class="rounded-lg bg-[var(--color-surface-subtle)] px-4 py-3 text-sm text-[var(--color-text-secondary)]">
                    Belum ada simulasi leasing untuk kendaraan ini.
                </p>
            @else
                <div class="overflow-x-auto rounded-lg border border-[var(--color-border)]">
                    <table class="w-full min-w-[680px] text-left text-sm">
                        <thead class="bg-[var(--color-surface-table-header)] text-[var(--color-text-strong)]">
                            <tr>
                                <th class="px-3 py-2.5 font-semibold">Leasing</th>
                                <th class="px-3 py-2.5 font-semibold">Pencairan</th>
                                <th class="px-3 py-2.5 font-semibold">Harga jual</th>
                                <th class="px-3 py-2.5 font-semibold">DP</th>
                                <th class="px-3 py-2.5 font-semibold">3 tahun</th>
                                <th class="px-3 py-2.5 font-semibold">4 tahun</th>
                                <th class="px-3 py-2.5 font-semibold">5 tahun</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--color-border-subtle)]">
                            @foreach ($leasingOptions as $option)
                                <tr>
                                    <td class="px-3 py-2.5 font-semibold">{{ $option['name'] ?? '—' }}</td>
                                    @foreach (['disbursement', 'selling_price', 'dp', 'installment_3yr', 'installment_4yr', 'installment_5yr'] as $amountField)
                                        <td class="px-3 py-2.5">
                                            {{ isset($option[$amountField]) ? 'Rp '.number_format((float) $option[$amountField], 0, ',', '.') : '—' }}
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </article>
@endsection
