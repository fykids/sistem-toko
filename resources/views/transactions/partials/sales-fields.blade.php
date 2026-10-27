@if ($canViewSensitiveData)
<section class="form-section sales-section">
    <header class="section-heading"><span class="section-number">04</span>
        <div>
            <h2>Penjualan dan pembayaran</h2>
        </div>
    </header>
    <div class="section-content field-grid two-column">
        @can('viewSensitiveData', \App\Models\CarTransaction::class)
            <div class="sales-fields">
                <div class="form-group"><label for="leasing-ripan">Ripan leasing (Rp)</label><input type="text"
                        name="leasing_ripan" id="leasing-ripan" aria-label="Ripan leasing (Rupiah)"
                        value="{{ old('leasing_ripan', $transaction?->leasing_ripan) }}" placeholder="Rp 0"
                        inputmode="decimal"
                        data-rupiah-input></div>
                <div class="form-group"><label for="insurance-ripan">Ripan asuransi (Rp)</label><input type="text"
                        name="insurance_ripan" id="insurance-ripan" aria-label="Ripan asuransi (Rupiah)"
                        value="{{ old('insurance_ripan', $transaction?->insurance_ripan) }}" placeholder="Rp 0"
                        inputmode="decimal"
                        data-rupiah-input></div>
                <div class="form-group"><label for="booster-ripan">Ripan booster (Rp)</label><input type="text"
                        name="booster_ripan" id="booster-ripan" aria-label="Ripan booster (Rupiah)"
                        value="{{ old('booster_ripan', $transaction?->booster_ripan) }}" placeholder="Rp 0"
                        inputmode="decimal"
                        data-rupiah-input></div>
            </div>
        @else
            <input type="hidden" name="leasing_ripan" id="leasing-ripan" value="0" data-rupiah-input>
            <input type="hidden" name="insurance_ripan" id="insurance-ripan" value="0" data-rupiah-input>
            <input type="hidden" name="booster_ripan" id="booster-ripan" value="0" data-rupiah-input>
        @endcan
        @can('viewSensitiveData', \App\Models\CarTransaction::class)
            <div class="form-group"><label for="calculated-total-profit">Total laba</label><input type="text"
                    id="calculated-total-profit" value="0" inputmode="decimal" data-rupiah-input readonly
                    aria-label="Total laba (Rupiah)"></div>
        @endcan
        @can('viewSensitiveData', \App\Models\CarTransaction::class)
            <div class="form-group"><label for="calculated-showroom-fee">Fee Showroom (Rp)</label><input type="text"
                    id="calculated-showroom-fee" value="0" readonly aria-label="Fee Showroom (Rupiah)"></div>
        @endcan
        @include('transactions.partials.intertine-expense-fields')
        @can('viewSensitiveData', \App\Models\CarTransaction::class)
            <div class="form-group"><label for="calculated-total-intertine-expenses">Total Pengeluaran Intertine
                    (Rp)</label><input type="text" id="calculated-total-intertine-expenses" value="0" readonly
                    aria-label="Total Pengeluaran Intertine (Rupiah)"></div>
            <div class="form-group"><label for="calculated-net-profit">Laba Bersih (Rp)</label><input type="text"
                    id="calculated-net-profit" value="0" readonly aria-label="Laba Bersih (Rupiah)"></div>
        @endcan
    </div>
</section>
@else
    <input type="hidden" name="leasing_ripan" id="leasing-ripan" value="0" data-rupiah-input>
    <input type="hidden" name="insurance_ripan" id="insurance-ripan" value="0" data-rupiah-input>
    <input type="hidden" name="booster_ripan" id="booster-ripan" value="0" data-rupiah-input>
@endif
