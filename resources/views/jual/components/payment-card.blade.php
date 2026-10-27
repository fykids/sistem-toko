<section aria-labelledby="jual-payment-title">
    @php($initialPaymentType = old('payment_type', 'selling_price'))
    <h3 id="jual-payment-title" class="form-section-title">Skema pembayaran</h3>
    <div class="grid gap-4">
        <div class="form-group">
            <label for="jual-payment-type">Kategori pembayaran</label>
            <div class="grid gap-2">
                <select id="jual-payment-type" name="payment_type" data-jual-payment-type required>
                    @foreach ([
                        'selling_price' => 'Harga Jual (Rp)',
                        'down_payment' => 'DP (Rp)',
                        'settlement_amount' => 'Pelunasan (Rp)',
                        'leasing_disbursement' => 'Pencairan Leasing (Rp)',
                    ] as $field => $label)
                        <option value="{{ $field }}" @selected(old('payment_type', 'selling_price') === $field)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('payment_type')<span class="form-help">{{ $message }}</span>@enderror
                <label for="jual-payment-amount">Nilai pembayaran (Rp)</label>
                <input id="jual-payment-amount" type="text" name="payment_amount"
                    value="{{ old('payment_amount', $selectedVehicle?->getAttribute($initialPaymentType) ?? 0) }}"
                    data-jual-payment-amount data-preserve-value="{{ old('payment_amount') !== null ? 'true' : 'false' }}"
                    data-rupiah-input inputmode="decimal" autocomplete="off" placeholder="Rp 0" required>
                @error('payment_amount')<span class="form-help">{{ $message }}</span>@enderror
            </div>
        </div>
        <div class="field-grid two-column">
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
    </div>
</section>
