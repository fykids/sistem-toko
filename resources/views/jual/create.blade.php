@extends('layout')

@section('page_class', 'page-shell--transaction-form page-shell--transaction-form-entry page-shell--jual-entry')

@section('content')
<form action="{{ $isEditing ? route('jual.update', $saleNote) : route('jual.store') }}" method="POST"
    @class([
        'transaction-form',
        'sell-module-form',
        'transaction-form--master' => $canViewSensitiveData,
        'transaction-form--admin' => ! $canViewSensitiveData,
        'w-full',
        'max-w-3xl',
        'mx-auto',
        '!grid-cols-1',
    ])>
    @csrf
    @if ($isEditing)
        @method('PUT')
    @endif
    @php($transaction = $selectedVehicle)
    @php($initialRemainingPayment = max(
        (float) ($selectedVehicle?->selling_price ?? 0)
            - (float) ($selectedVehicle?->down_payment ?? 0)
            - (float) ($selectedVehicle?->settlement_amount ?? 0)
            - (float) ($selectedVehicle?->leasing_disbursement ?? 0),
        0,
    ))
    <section class="form-section sell-module-form-section">
        <header class="section-heading">
            <span class="section-number">01</span>
            <div>
                <h2 id="jual-form-title">{{ $isEditing ? 'Edit transaksi Jual' : 'Input Penjualan' }}</h2>
                <p>Masukkan data kendaraan, konsumen, dan pembayaran.</p>
            </div>
        </header>

        <div class="section-content grid gap-4">
            @include('jual.components.vehicle-card')

            <section aria-labelledby="jual-payment-title" class="sell-payment-section">
                <h3 id="jual-payment-title" class="form-section-title">Pembayaran</h3>

                <div class="sell-payment-rows">
                    <div class="sell-payment-row">
                        <label for="jual-selling-price">Harga jual (Rp)</label>
                        <div class="sell-payment-row-fields">
                            <div class="form-group">
                                <label class="sr-only" for="jual-selling-price">Nominal harga jual (Rp)</label>
                                <input id="jual-selling-price" type="text" name="selling_price"
                                    value="{{ old('selling_price', number_format((float) ($selectedVehicle?->selling_price ?? 0), 2, ',', '.')) }}"
                                    data-preserve-value="{{ old('selling_price') !== null ? 'true' : 'false' }}"
                                    data-jual-selling-price data-rupiah-input inputmode="decimal"
                                    autocomplete="off" placeholder="Rp 0" required>
                            </div>
                            <div class="form-group">
                                <label for="jual-sale-date">Tanggal transaksi</label>
                                <input id="jual-sale-date" type="date" name="sell_date"
                                    value="{{ old('sell_date', $selectedVehicle?->sell_date ?? now()->toDateString()) }}"
                                    required>
                            </div>
                        </div>
                        @error('selling_price')<span class="form-help">{{ $message }}</span>@enderror
                        @error('sell_date')<span class="form-help">{{ $message }}</span>@enderror
                    </div>

                    <div class="sell-payment-row">
                        <label for="jual-down-payment">DP (Rp)</label>
                        <div class="sell-payment-row-fields">
                            <div class="form-group">
                                <label class="sr-only" for="jual-down-payment">Nominal DP (Rp)</label>
                                <input id="jual-down-payment" type="text" name="down_payment"
                                    value="{{ old('down_payment', number_format((float) ($selectedVehicle?->down_payment ?? 0), 2, ',', '.')) }}"
                                    data-rupiah-input data-jual-payment-amount inputmode="decimal"
                                    autocomplete="off" placeholder="Rp 0" required>
                            </div>
                            <div class="form-group">
                                <label for="jual-down-payment-date">Tanggal pembayaran DP</label>
                                <input id="jual-down-payment-date" type="date" name="down_payment_date"
                                    value="{{ old('down_payment_date', $selectedVehicle?->down_payment_date) }}">
                            </div>
                        </div>
                        @error('down_payment')<span class="form-help">{{ $message }}</span>@enderror
                        @error('down_payment_date')<span class="form-help">{{ $message }}</span>@enderror
                    </div>

                    <div class="sell-payment-row">
                        <label for="jual-settlement-amount">Pelunasan (Rp)</label>
                        <div class="sell-payment-row-fields">
                            <div class="form-group">
                                <label class="sr-only" for="jual-settlement-amount">Nominal pelunasan (Rp)</label>
                                <input id="jual-settlement-amount" type="text" name="settlement_amount"
                                    value="{{ old('settlement_amount', number_format((float) ($selectedVehicle?->settlement_amount ?? 0), 2, ',', '.')) }}"
                                    data-rupiah-input data-jual-payment-amount inputmode="decimal"
                                    autocomplete="off" placeholder="Rp 0" required>
                            </div>
                            <div class="form-group">
                                <label for="jual-settlement-date">Tanggal konsumen melakukan pembayaran</label>
                                <input id="jual-settlement-date" type="date" name="settlement_date"
                                    value="{{ old('settlement_date', $selectedVehicle?->settlement_date) }}">
                            </div>
                        </div>
                        @error('settlement_amount')<span class="form-help">{{ $message }}</span>@enderror
                        @error('settlement_date')<span class="form-help">{{ $message }}</span>@enderror
                    </div>

                    <div class="sell-payment-row">
                        <label for="jual-leasing-disbursement">Pencairan leasing (Rp)</label>
                        <div class="sell-payment-row-fields">
                            <div class="form-group">
                                <label class="sr-only" for="jual-leasing-disbursement">Nominal pencairan leasing (Rp)</label>
                                <input id="jual-leasing-disbursement" type="text" name="leasing_disbursement"
                                    value="{{ old('leasing_disbursement', number_format((float) ($selectedVehicle?->leasing_disbursement ?? 0), 2, ',', '.')) }}"
                                    data-rupiah-input data-jual-payment-amount inputmode="decimal"
                                    autocomplete="off" placeholder="Rp 0" required>
                            </div>
                            <div class="form-group">
                                <label for="jual-leasing-disbursement-date">Tanggal pencairan leasing</label>
                                <input id="jual-leasing-disbursement-date" type="date" name="leasing_disbursement_date"
                                    value="{{ old('leasing_disbursement_date', $selectedVehicle?->leasing_disbursement_date) }}">
                            </div>
                        </div>
                        @error('leasing_disbursement')<span class="form-help">{{ $message }}</span>@enderror
                        @error('leasing_disbursement_date')<span class="form-help">{{ $message }}</span>@enderror
                    </div>

                    <div class="sell-payment-row sell-payment-row--remaining">
                        <label for="jual-planned-payment-date">Sisa pembayaran (Rp)</label>
                        <div class="sell-payment-row-fields">
                            <output id="jual-remaining-payment" class="status-badge"
                                aria-live="polite" data-jual-remaining-payment>
                                Rp {{ number_format($initialRemainingPayment, 2, ',', '.') }}
                            </output>
                            <div class="form-group">
                                <label for="jual-planned-payment-date">Tanggal rencana pembayaran (jatuh tempo)</label>
                                <input id="jual-planned-payment-date" type="date" name="planned_payment_date"
                                    value="{{ old('planned_payment_date', $selectedVehicle?->planned_payment_date) }}"
                                    data-jual-planned-payment-date
                                    @required($initialRemainingPayment > 0)>
                            </div>
                        </div>
                        @error('planned_payment_date')<span class="form-help">{{ $message }}</span>@enderror
                    </div>
                </div>
            </section>

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <a href="{{ route('jual.index') }}" class="btn btn-secondary">Batal / Kembali</a>
                <button type="submit" class="btn btn-accent">
                    {{ $isEditing ? 'Simpan perubahan' : 'Simpan Penjualan' }}
                </button>
            </div>
        </div>
    </section>
</form>
@endsection
