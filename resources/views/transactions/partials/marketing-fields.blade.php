<section class="form-section marketing-section">
    <header class="section-heading"><span class="section-number">03</span>
        <div>
            <h2>Strategi pemasaran</h2>
        </div>
    </header>
    <div class="section-content marketing-fields">
        @can('viewSensitiveData', \App\Models\CarTransaction::class)
            <div class="form-group"><label for="calculated-total-capital">Total pengeluaran
                </label><input type="text" id="calculated-total-capital" value="0" readonly
                    aria-label="Total pengeluaran modal kendaraan (Rupiah)"></div>
        @endcan
        <div class="form-group setor-price-group">
            <label for="setor-price">Harga setor (Rp)</label>
            <div @class([
                'setor-price-inputs',
                'price-inputs--restricted' => ! $canViewSensitiveData,
            ])>
                <input type="text" name="setor_price" id="setor-price" aria-label="Harga setor (Rupiah)"
                    value="{{ old('setor_price', $transaction?->setor_price) }}" placeholder="Rp 0"
                    inputmode="decimal" data-rupiah-input>
                @can('viewSensitiveData', \App\Models\CarTransaction::class)
                    <input type="text" id="calculated-setor-profit" value="0" readonly
                        aria-label="Selisih harga setor dengan modal (Rupiah)">
                    <input type="text" id="calculated-setor-profit-percentage" value="0%" readonly
                        aria-label="Persentase selisih harga setor dari modal">
                @endcan
            </div>
        </div>
        <div class="form-group opening-price-group">
            <label for="opening-price">Harga buka (Rp)</label>
            <div @class([
                'opening-price-inputs',
                'price-inputs--restricted' => ! $canViewSensitiveData,
            ])>
                <input type="text" name="opening_price" id="opening-price" aria-label="Harga buka (Rupiah)"
                    value="{{ old('opening_price', $transaction?->opening_price) }}" placeholder="Rp 0"
                    inputmode="decimal" data-rupiah-input>
                @can('viewSensitiveData', \App\Models\CarTransaction::class)
                    <input type="text" id="calculated-opening-profit" value="0" readonly
                        aria-label="Selisih harga buka dengan modal (Rupiah)">
                    <input type="text" id="calculated-opening-profit-percentage" value="0%" readonly
                        aria-label="Persentase selisih harga buka dari modal">
                @endcan
            </div>
        </div>
        <div class="form-group selling-price-group">
            <label for="selling-price">Harga jual (Rp)</label>
            <div @class([
                'selling-price-inputs',
                'selling-price-inputs--restricted' => ! $canViewSensitiveData,
            ]) role="group"
                aria-label="{{ $canViewSensitiveData ? 'Harga jual, laba, dan tanggal jual' : 'Harga jual dan tanggal jual' }}">
                @can('viewSensitiveData', \App\Models\CarTransaction::class)
                    <div class="selling-price-amounts">
                @endcan
                    <input type="text" name="selling_price" id="selling-price"
                        value="{{ old('selling_price', $transaction?->selling_price) }}" placeholder="Rp 0"
                        inputmode="decimal" data-rupiah-input aria-label="Harga jual (Rupiah)">
                @can('viewSensitiveData', \App\Models\CarTransaction::class)
                    <input type="text" id="calculated-sale-profit" value="0" inputmode="decimal" data-rupiah-input
                        readonly aria-label="Laba jual (Rupiah)">
                    </div>
                @endcan
                <input id="sell-date" type="date" name="sell_date"
                    value="{{ old('sell_date', $transaction?->sell_date) }}" aria-label="Tanggal jual">
            </div>
        </div>
        <div class="sales-fields-row">
            <div class="form-group"><label for="down-payment">DP (Rp)</label>
                <div class="payment-inputs">
                    <input type="text" name="down_payment" id="down-payment"
                        value="{{ old('down_payment', $transaction?->down_payment) }}" placeholder="Rp 0" inputmode="decimal"
                        data-rupiah-input>
                    <input type="date" name="down_payment_date" id="down-payment-date"
                        value="{{ old('down_payment_date', $transaction?->down_payment_date) }}"
                        aria-label="Tanggal DP">
                </div>
            </div>
            <div class="form-group"><label for="settlement-amount">Pelunasan (Rp)</label>
                <div class="payment-inputs">
                    <input type="text" name="settlement_amount" id="settlement-amount"
                        value="{{ old('settlement_amount', $transaction?->settlement_amount) }}"
                        placeholder="Rp 0" inputmode="decimal" data-rupiah-input>
                    <input type="date" name="settlement_date" id="settlement-date"
                        value="{{ old('settlement_date', $transaction?->settlement_date) }}"
                        aria-label="Tanggal pelunasan">
                </div>
            </div>
            <div class="form-group"><label for="leasing-disbursement">Pencairan leasing (Rp)</label>
                <div class="payment-inputs">
                    <input type="text" name="leasing_disbursement" id="leasing-disbursement"
                        value="{{ old('leasing_disbursement', $transaction?->leasing_disbursement) }}"
                        placeholder="Rp 0" inputmode="decimal" data-rupiah-input>
                    <input type="date" name="leasing_disbursement_date" id="leasing-disbursement-date"
                        value="{{ old('leasing_disbursement_date', $transaction?->leasing_disbursement_date) }}"
                        aria-label="Tanggal pencairan leasing">
                </div>
            </div>
            <div class="form-group">
                <label for="remaining-payment">Sisa pembayaran (Rp)</label>
                <div class="payment-inputs payment-inputs--remaining">
                    <input type="text" name="remaining_payment" id="remaining-payment"
                        value="{{ max((float) old('selling_price', $transaction?->selling_price ?? 0) - (float) old('down_payment', $transaction?->down_payment ?? 0) - (float) old('settlement_amount', $transaction?->settlement_amount ?? 0) - (float) old('leasing_disbursement', $transaction?->leasing_disbursement ?? 0), 0) }}"
                        inputmode="decimal" data-rupiah-input readonly>
                    <input type="date" name="planned_payment_date" id="planned-payment-date"
                        value="{{ old('planned_payment_date', $transaction?->planned_payment_date) }}"
                        aria-label="Tanggal rencana pembayaran (jatuh tempo)" data-planned-payment-date>
                </div>
                @error('planned_payment_date')
                    <span class="form-help">{{ $message }}</span>
                @enderror
            </div>
        </div>
        @if (auth()->user()->role === 'admin')
            @include('transactions.partials.intertine-expense-fields')
        @endif
    </div>
</section>
