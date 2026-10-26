@extends('layouts.marketing')

@section('title', 'Katalog Stok Mobil')

@section('content')
    <section class="mb-6">
        <p class="mb-1 text-sm font-semibold uppercase tracking-wide text-[var(--color-primary-eyebrow)]">Stok tersedia</p>
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-[var(--color-text-primary)] sm:text-3xl">Katalog mobil</h1>
                <p class="mt-1 text-sm text-[var(--color-text-secondary)]">Informasi kendaraan untuk referensi penawaran.</p>
            </div>
            <p class="text-sm text-[var(--color-text-secondary)]">
                {{ $vehicles->total() }} unit tersedia
            </p>
        </div>
    </section>

    <form action="{{ route('marketing.stok.index') }}" method="GET"
        class="mb-6 grid gap-3 rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] p-4 sm:grid-cols-2 lg:grid-cols-5">
        <input type="hidden" name="q" value="{{ $filters['q'] ?? '' }}">
        <label class="grid gap-1 text-sm font-semibold text-[var(--color-text-strong)]">
            Transmisi
            <select class="min-h-10 rounded-lg border border-[var(--color-control-border)] bg-[var(--color-control-surface)] px-3 font-normal"
                name="transmission">
                <option value="">Semua</option>
                <option value="manual" @selected(($filters['transmission'] ?? '') === 'manual')>Manual</option>
                <option value="otomatis" @selected(in_array(($filters['transmission'] ?? ''), ['otomatis', 'automatic'], true))>Otomatis</option>
            </select>
        </label>
        <label class="grid gap-1 text-sm font-semibold text-[var(--color-text-strong)]">
            Tahun dari
            <input class="min-h-10 rounded-lg border border-[var(--color-control-border)] bg-[var(--color-control-surface)] px-3 font-normal"
                type="number" name="year_from" min="1900" max="2100" value="{{ $filters['year_from'] ?? '' }}">
        </label>
        <label class="grid gap-1 text-sm font-semibold text-[var(--color-text-strong)]">
            Tahun sampai
            <input class="min-h-10 rounded-lg border border-[var(--color-control-border)] bg-[var(--color-control-surface)] px-3 font-normal"
                type="number" name="year_to" min="1900" max="2100" value="{{ $filters['year_to'] ?? '' }}">
        </label>
        <div class="flex flex-wrap items-end gap-2 sm:col-span-2 lg:col-span-5">
            <button class="btn btn-accent" type="submit">Cari stok</button>
            <a class="btn btn-secondary" href="{{ route('marketing.stok.index') }}">Reset filter</a>
        </div>
    </form>

    @if ($vehicles->isEmpty())
        <div class="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] px-5 py-10 text-center">
            <h2 class="text-lg font-semibold">Stok tidak ditemukan</h2>
            <p class="mt-1 text-sm text-[var(--color-text-secondary)]">Coba ubah kata pencarian atau filter kendaraan.</p>
        </div>
    @else
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($vehicles as $vehicle)
                @php($taxStatus = $vehicle->taxStatus())
                <article class="overflow-hidden rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] shadow-sm">
                    <a href="{{ route('marketing.stok.show', $vehicle->id) }}"
                        class="block text-inherit no-underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--color-primary-focus)]">
                        @if ($vehicle->vehicle_photo_path)
                            <img class="aspect-[16/10] w-full object-cover"
                                src="{{ asset('storage/'.$vehicle->vehicle_photo_path) }}"
                                alt="{{ $vehicle->brand }} {{ $vehicle->model }}" loading="lazy">
                        @else
                            <div class="grid aspect-[16/10] place-items-center bg-[var(--color-surface-primary-muted)] text-sm font-semibold text-[var(--color-text-secondary)]">
                                Foto kendaraan belum tersedia
                            </div>
                        @endif
                        <div class="grid gap-3 p-4">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <h2 class="text-lg font-bold">{{ $vehicle->brand }} {{ $vehicle->model }}</h2>
                                    <p class="text-sm text-[var(--color-text-secondary)]">
                                        {{ $vehicle->plate_number }} · {{ $vehicle->year }}
                                    </p>
                                </div>
                                <span class="shrink-0 rounded-full bg-[var(--color-surface-success)] px-2.5 py-1 text-xs font-semibold text-[var(--color-text-positive)]">
                                    Tersedia
                                </span>
                            </div>
                            <div class="flex flex-wrap gap-2 text-xs text-[var(--color-text-secondary)]">
                                <span class="rounded-full bg-[var(--color-surface-subtle)] px-2.5 py-1">{{ $vehicle->transmission }}</span>
                                <span class="rounded-full bg-[var(--color-surface-subtle)] px-2.5 py-1">{{ $vehicle->color }}</span>
                                <span class="status-badge {{ $taxStatus['badgeClass'] }}">
                                    Pajak {{ $taxStatus['label'] }}
                                </span>
                            </div>
                            <div class="border-t border-[var(--color-border-subtle)] pt-3">
                                <span class="block text-xs text-[var(--color-text-secondary)]">Harga penawaran / buka</span>
                                <strong class="text-lg text-[var(--color-primary)]">
                                    {{ (float) $vehicle->opening_price > 0 ? 'Rp '.number_format((float) $vehicle->opening_price, 0, ',', '.') : 'Hubungi marketing' }}
                                </strong>
                            </div>
                            <span class="text-sm font-semibold text-[var(--color-primary)]">Lihat spesifikasi & simulasi leasing →</span>
                        </div>
                    </a>
                </article>
            @endforeach
        </div>

        <nav class="mt-6" aria-label="Paginasi katalog stok">
            {{ $vehicles->links() }}
        </nav>
    @endif
@endsection
