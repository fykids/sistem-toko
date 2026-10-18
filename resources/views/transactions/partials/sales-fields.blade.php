<section class="form-section sales-section">
    <header class="section-heading"><span class="section-number">04</span>
        <div>
            <h2>Penjualan dan pembayaran</h2>
        </div>
    </header>
    <div class="section-content field-grid two-column">
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
        <div class="form-group"><label for="calculated-total-profit">Total laba</label><input type="text"
                id="calculated-total-profit" value="0" inputmode="decimal" data-rupiah-input readonly
                aria-label="Total laba (Rupiah)"></div>
        @php($expenseInput = old('intertine_expenses', $transaction?->intertine_expenses ?? []))
        @php($expenseItems = collect(is_array($expenseInput) ? $expenseInput : [])->filter(fn($expense) => is_array($expense))->values())
        @php($intertineCategories = ['KR Jual', 'OP Survei', 'Fee CMO'])
        @php($intertineCategoryCosts = $expenseItems->filter(fn(array $expense) => in_array(strtolower(trim($expense['name'] ?? '')), array_map('strtolower', $intertineCategories), true))->mapWithKeys(fn(array $expense) => [strtolower(trim($expense['name'])) => $expense['cost'] ?? null]))
        @php($legacyIntertineExpenses = $expenseItems->reject(fn(array $expense) => in_array(strtolower(trim($expense['name'] ?? '')), array_map('strtolower', $intertineCategories), true))->values())
        <div class="form-group"><label for="calculated-showroom-fee">Fee Showroom (Rp)</label><input type="text"
                id="calculated-showroom-fee" value="0" readonly aria-label="Fee Showroom (Rupiah)"></div>
        <div id="intertine-expenses" class="intertine-expense-fields">
            @foreach ($intertineCategories as $index => $category)
            @php($categoryKey = strtolower($category))
            <div class="form-group intertine-expense-item">
                <input type="hidden" name="intertine_expenses[{{ $index }}][name]" value="{{ $category }}">
                <label for="intertine-expense-cost-{{ $index }}">{{ $category }}</label>
                <input id="intertine-expense-cost-{{ $index }}" type="text"
                    name="intertine_expenses[{{ $index }}][cost]"
                    value="{{ old("intertine_expenses.{$index}.cost", $intertineCategoryCosts->get($categoryKey)) }}"
                    placeholder="Rp 0"
                    inputmode="decimal" data-rupiah-input>
            </div>
            @endforeach
            @foreach ($legacyIntertineExpenses as $index => $expense)
                <input type="hidden" name="intertine_expenses[{{ $index + count($intertineCategories) }}][name]"
                    value="{{ $expense['name'] ?? '' }}">
                <input type="hidden" name="intertine_expenses[{{ $index + count($intertineCategories) }}][cost]"
                    value="{{ $expense['cost'] ?? 0 }}" data-rupiah-input>
            @endforeach
        </div>
        <div class="form-group"><label for="calculated-total-intertine-expenses">Total Pengeluaran Intertine
                (Rp)</label><input type="text" id="calculated-total-intertine-expenses" value="0" readonly
                aria-label="Total Pengeluaran Intertine (Rupiah)"></div>
        <div class="form-group"><label for="calculated-net-profit">Laba Bersih (Rp)</label><input type="text"
                id="calculated-net-profit" value="0" readonly aria-label="Laba Bersih (Rupiah)"></div>
    </div>
</section>
