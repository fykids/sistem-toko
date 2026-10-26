@extends('layout')

@section('page_class', 'page-shell--transaction-form page-shell--transaction-form-entry')

@section('content')
<form action="{{ $isEditing ? route('jual.update', $saleNote) : route('jual.store') }}" method="POST"
    @class([
        'transaction-form',
        'sell-module-form',
        'transaction-form--master' => $canViewSensitiveData,
        'transaction-form--admin' => ! $canViewSensitiveData,
        'w-full',
        'max-w-none',
        '!grid-cols-1',
    ])>
    @csrf
    @if ($isEditing)
        @method('PUT')
    @endif
    @php($transaction = $selectedVehicle)
    <div class="sell-module-layout grid grid-cols-12 gap-4 items-start">
    <div class="sell-module-main col-span-12 lg:col-span-5 min-w-0">
    <section class="form-section">
        <header class="section-heading">
            <span class="section-number">01</span>
            <div>
                <h2 id="jual-form-title">{{ $isEditing ? 'Edit transaksi Jual' : 'Input Penjualan' }}</h2>
                <p>Masukkan data pembeli dan pembayaran untuk mobil yang dipilih.</p>
            </div>
        </header>
        <div class="section-content grid gap-4">
            @include('jual.components.vehicle-card')
            <section aria-labelledby="jual-price-title">
                <h3 id="jual-price-title" class="form-section-title">Harga jual</h3>
                <div class="form-group">
                    <label for="jual-selling-price">Harga jual (Rp)</label>
                    <input id="jual-selling-price" type="text" name="selling_price"
                        value="{{ old('selling_price', $selectedVehicle?->selling_price ?? 0) }}"
                        data-preserve-value="{{ old('selling_price') !== null ? 'true' : 'false' }}"
                        data-jual-selling-price data-rupiah-input inputmode="decimal" autocomplete="off"
                        placeholder="Rp 0" required>
                </div>
                @error('selling_price')<span class="form-help">{{ $message }}</span>@enderror
            </section>
            <section aria-labelledby="jual-payment-title">
                @php($initialPaymentType = old('payment_type', 'down_payment'))
                @php($paymentDateFields = [
                    'down_payment' => 'down_payment_date',
                    'leasing_disbursement' => 'leasing_disbursement_date',
                    'settlement_amount' => 'settlement_date',
                ])
                @php($initialPaymentDateField = $paymentDateFields[$initialPaymentType] ?? 'down_payment_date')
                <h3 id="jual-payment-title" class="form-section-title">Pilihan skema pembayaran</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                    <div class="form-group">
                        <label for="jual-payment-type">Jenis pembayaran</label>
                        <select id="jual-payment-type" name="payment_type" data-jual-payment-type required>
                            @foreach ([
                                'down_payment' => 'DP (Rp)',
                                'leasing_disbursement' => 'Pencairan Leasing (Rp)',
                                'settlement_amount' => 'Pelunasan (Rp)',
                            ] as $field => $label)
                                <option value="{{ $field }}" @selected($initialPaymentType === $field)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('payment_type')<span class="form-help">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label for="jual-payment-amount">Nilai nominal (Rp)</label>
                        <input id="jual-payment-amount" type="text" name="payment_amount"
                            value="{{ old('payment_amount', $selectedVehicle?->getAttribute($initialPaymentType) ?? 0) }}"
                            data-jual-payment-amount
                            data-preserve-value="{{ old('payment_amount') !== null ? 'true' : 'false' }}"
                            data-rupiah-input inputmode="decimal" autocomplete="off" placeholder="Rp 0" required>
                        @error('payment_amount')<span class="form-help">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label for="jual-payment-date">Tanggal Pembayaran</label>
                        <input id="jual-payment-date" type="date" name="payment_date"
                            value="{{ old('payment_date', $selectedVehicle?->getAttribute($initialPaymentDateField)) }}"
                            data-jual-payment-date
                            data-preserve-value="{{ old('payment_date') !== null ? 'true' : 'false' }}">
                        @error('payment_date')<span class="form-help">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="grid gap-4">
                    <div class="form-group">
                        <label for="jual-remaining-payment">Sisa pembayaran (Rp)</label>
                        <output id="jual-remaining-payment" class="status-badge" aria-live="polite"
                            data-jual-remaining-payment>
                            Rp {{ number_format((float) ($selectedVehicle?->remaining_payment ?? 0)) }}
                        </output>
                    </div>
                    <div class="form-group">
                        <label for="jual-sale-date">Tanggal jual</label>
                        <input id="jual-sale-date" type="date" name="sell_date"
                            value="{{ old('sell_date', $selectedVehicle?->sell_date ?? now()->toDateString()) }}" required>
                        @error('sell_date')<span class="form-help">{{ $message }}</span>@enderror
                    </div>
                </div>
            </section>
            <section aria-labelledby="jual-costs-title">
                <h3 id="jual-costs-title" class="form-section-title">Biaya & komisi penjualan</h3>
                @include('transactions.partials.intertine-expense-fields', [
                    'showLegacyIntertineExpenses' => false,
                    'expenseGridClass' => 'field-grid two-column',
                ])
            </section>
            @include('jual.components.vehicle-preview')
            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <a href="{{ route('jual.index') }}" class="btn btn-secondary">Batal / Kembali</a>
                <button type="submit" class="btn btn-accent">
                    {{ $isEditing ? 'Simpan perubahan' : 'Simpan Penjualan' }}
                </button>
            </div>
        </div>
    </section>
    </div>
    @include('jual.components.leasing-preview')
    </div>
</form>
@endsection
