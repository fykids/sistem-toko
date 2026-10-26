@extends('layout')

@section('page_class', 'page-shell--receipt')

@section('content')
    @can('viewSensitiveData', \App\Models\CarTransaction::class)
    @php
        $daysHeld = \Illuminate\Support\Carbon::parse($transaction->buy_date)
            ->diffInDays($transaction->sell_date ?? now());
        $setorProfit = (float) $transaction->setor_price - (float) $transaction->total_capital;
        $openingProfit = (float) $transaction->opening_price - (float) $transaction->total_capital;
        $taxItems = $transaction->tax_items ?? [];
        $repairs = $transaction->additional_repairs ?? [];
        $intertineExpenses = $transaction->intertine_expenses ?? [];
        $leasingFields = [
            'disbursement' => 'Pencairan',
            'selling_price' => 'Harga jual',
            'dp' => 'DP',
            'installment_3yr' => 'Angsuran 3 tahun',
            'installment_4yr' => 'Angsuran 4 tahun',
            'installment_5yr' => 'Angsuran 5 tahun',
        ];
        $leasingColumnLabels = [
            'disbursement' => 'Cair',
            'selling_price' => 'Harga jual',
            'dp' => 'DP',
            'installment_3yr' => '3 thn',
            'installment_4yr' => '4 thn',
            'installment_5yr' => '5 thn',
        ];
        $leasingOptions = collect($transaction->leasing_options ?? [])
            ->filter(fn ($option): bool => is_array($option));
    @endphp

    <div class="page-heading receipt-page-heading">
        <div><p class="page-eyebrow">Dokumen transaksi</p><h1>Nota Kendaraan</h1><span class="cell-secondary">{{ $transaction->plate_number }} · {{ $transaction->brand }} {{ $transaction->model }}</span></div>
        <div class="receipt-page-actions">
            @can('update', $transaction)
                <a href="{{ route('transactions.edit', $transaction) }}" class="btn btn-accent">Edit data</a>
            @endcan
            <a href="{{ route('transactions.index') }}" class="btn btn-secondary receipt-back">← Kembali</a>
        </div>
    </div>

    <div class="vehicle-detail-layout">
        <div class="receipt">
            <div class="receipt-header">
                <table class="identity-table">
                    <tr><td>Plat</td><td>:</td><td>{{ $transaction->plate_number }}</td></tr>
                    <tr><td>Merek</td><td>:</td><td>{{ $transaction->brand }}</td></tr>
                    <tr><td>Model</td><td>:</td><td>{{ $transaction->model }}</td></tr>
                    <tr><td>Tahun</td><td>:</td><td>{{ $transaction->year }}</td></tr>
                    <tr><td>Transmisi</td><td>:</td><td>{{ $transaction->transmission }}</td></tr>
                    <tr><td>Warna</td><td>:</td><td>{{ $transaction->color }}</td></tr>
                    <tr><td>Nomor Rangka</td><td>:</td><td>{{ $transaction->chassis_number ?: '-' }}</td></tr>
                    <tr><td>Nomor Mesin</td><td>:</td><td>{{ $transaction->engine_number ?: '-' }}</td></tr>
                    <tr><td>Atas Nama</td><td>:</td><td>{{ $transaction->owner_name ?: '-' }}</td></tr>
                    <tr><td>Km</td><td>:</td><td>{{ $transaction->mileage_km !== null ? number_format($transaction->mileage_km) : '-' }}</td></tr>
                </table>
                <table class="meta-table">
                    <tr><td>Pengurus</td><td>:</td><td>{{ $transaction->manager_name ?: '-' }}</td></tr>
                    <tr><td>Tgl Beli</td><td>:</td><td>{{ $transaction->buy_date }}</td></tr>
                    <tr><td>Tgl Jual</td><td>:</td><td>{{ $transaction->sell_date ?? '-' }}</td></tr>
                    <tr><td>Jumlah Hari</td><td>:</td><td>{{ $daysHeld }}</td></tr>
                </table>
            </div>

            @include('transactions.partials.receipt-financials', [
                'transaction' => $transaction,
                'taxItems' => $taxItems,
                'repairs' => $repairs,
                'intertineExpenses' => $intertineExpenses,
                'setorProfit' => $setorProfit,
                'openingProfit' => $openingProfit,
            ])
        </div>

        <aside class="vehicle-leasing-panel" aria-labelledby="vehicle-leasing-title">
            @if ($transaction->vehicle_photo_path)
                <img
                    class="vehicle-photo-detail"
                    src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($transaction->vehicle_photo_path) }}"
                    alt="Foto {{ $transaction->brand }} {{ $transaction->model }}"
                >
            @endif
            <div class="vehicle-leasing-heading">
                <div>
                    <p class="page-eyebrow">Informasi pelengkap kendaraan</p>
                    <h2 id="vehicle-leasing-title">Harga Leasing</h2>
                </div>
                <span class="leasing-currency-note">Nilai dalam Rupiah</span>
            </div>
            <div class="leasing-vehicle-summary">
                <span class="leasing-vehicle-plate">{{ $transaction->plate_number }}</span>
                <span class="leasing-vehicle-name">{{ $transaction->brand }} {{ $transaction->model }} · {{ $transaction->year }}</span>
            </div>
            @if ($leasingOptions->isNotEmpty())
                <div class="leasing-table-wrap">
                    <table class="leasing-table">
                        <caption class="sr-only">Perbandingan harga leasing untuk {{ $transaction->brand }} {{ $transaction->model }}</caption>
                        <thead>
                            <tr>
                                <th scope="col">Leasing</th>
                                @foreach ($leasingFields as $field => $label)
                                    <th scope="col" title="{{ $label }}">{{ $leasingColumnLabels[$field] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($leasingOptions as $option)
                                <tr>
                                    <th scope="row">{{ $option['name'] ?? '-' }}</th>
                                    @foreach (array_keys($leasingFields) as $field)
                                        <td data-label="{{ $leasingFields[$field] }}">
                                            {{ filled($option[$field] ?? null) ? number_format((float) $option[$field]) : '-' }}
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="leasing-empty-state">Belum ada detail harga leasing untuk kendaraan ini.</p>
            @endif
        </aside>
    </div>
    @else
        <div class="page-heading receipt-page-heading">
            <div>
                <p class="page-eyebrow">Informasi kendaraan</p>
                <h1>{{ $transaction->plate_number }}</h1>
                <span class="cell-secondary">{{ $transaction->brand }} {{ $transaction->model }} · {{ $transaction->year }}</span>
            </div>
            <div class="receipt-page-actions">
                @can('update', $transaction)
                    <a href="{{ route('transactions.edit', $transaction) }}" class="btn btn-accent">Edit data</a>
                @endcan
                <a href="{{ route('transactions.index') }}" class="btn btn-secondary receipt-back">← Kembali</a>
            </div>
        </div>

        <div class="vehicle-detail-layout">
            <section class="receipt">
                <div class="receipt-header">
                    <table class="identity-table">
                        <tr><td>Plat</td><td>:</td><td>{{ $transaction->plate_number }}</td></tr>
                        <tr><td>Merek</td><td>:</td><td>{{ $transaction->brand }}</td></tr>
                        <tr><td>Model</td><td>:</td><td>{{ $transaction->model }}</td></tr>
                        <tr><td>Tahun</td><td>:</td><td>{{ $transaction->year }}</td></tr>
                        <tr><td>Transmisi</td><td>:</td><td>{{ $transaction->transmission }}</td></tr>
                        <tr><td>Warna</td><td>:</td><td>{{ $transaction->color }}</td></tr>
                        <tr><td>Nomor Rangka</td><td>:</td><td>{{ $transaction->chassis_number ?: '-' }}</td></tr>
                        <tr><td>Nomor Mesin</td><td>:</td><td>{{ $transaction->engine_number ?: '-' }}</td></tr>
                        <tr><td>Atas Nama</td><td>:</td><td>{{ $transaction->owner_name ?: '-' }}</td></tr>
                        <tr><td>Km</td><td>:</td><td>{{ $transaction->mileage_km !== null ? number_format($transaction->mileage_km) : '-' }}</td></tr>
                    </table>
                    <table class="meta-table">
                        <tr><td>Pengurus</td><td>:</td><td>{{ $transaction->manager_name ?: '-' }}</td></tr>
                        <tr><td>Tanggal beli</td><td>:</td><td>{{ $transaction->buy_date }}</td></tr>
                        <tr><td>Tanggal jual</td><td>:</td><td>{{ $transaction->sell_date ?? '-' }}</td></tr>
                    </table>
                </div>
                <p class="receipt-back">Rincian finansial transaksi hanya tersedia untuk role Master.</p>
            </section>

            @if ($transaction->vehicle_photo_path)
                <aside class="vehicle-leasing-panel">
                    <img
                        class="vehicle-photo-detail"
                        src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($transaction->vehicle_photo_path) }}"
                        alt="Foto {{ $transaction->brand }} {{ $transaction->model }}"
                    >
                </aside>
            @endif
        </div>
    @endcan
@endsection