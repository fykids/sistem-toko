<div class="receipt-body">
    <div class="receipt-section-title receipt-section-total"><span>Modal beli</span><span>Rp {{ number_format($transaction->buy_price) }}</span></div>
    <table class="receipt-table">
        <tr class="receipt-subheading"><td colspan="4">Pengeluaran</td></tr>
        <tr><td class="description receipt-indent">OP Pembelian/Uang Jalan</td><td class="amount">Rp {{ number_format($transaction->purchase_operational) }}</td><td class="date"></td><td class="status"></td></tr>
        <tr><td class="description receipt-indent">KR Beli</td><td class="amount">Rp {{ number_format($transaction->other_costs) }}</td><td class="date"></td><td class="status"></td></tr>
        <tr class="receipt-subheading"><td colspan="4">Biaya Pajak</td></tr>
        @forelse ($taxItems as $taxItem)
            <tr>
                <td class="description receipt-indent">{{ $taxItem['name'] }}</td>
                <td class="amount">Rp {{ number_format($taxItem['cost']) }}</td>
                <td class="date">{{ $taxItem['date'] ?? '-' }}</td>
                <td class="status">{{ ucfirst(str_replace('_', ' ', $taxItem['payment_status'] ?? 'lainnya')) }}@if (!empty($taxItem['payment_note']))<br>{{ $taxItem['payment_note'] }}@endif</td>
            </tr>
        @empty
            <tr><td class="description receipt-indent">Belum ada item pajak</td><td class="amount">Rp -</td><td class="date"></td><td class="status"></td></tr>
        @endforelse
        <tr class="receipt-rule receipt-total"><td class="description">Total Biaya Pajak</td><td class="amount">Rp {{ number_format(array_sum(array_column($taxItems, 'cost'))) }}</td><td colspan="2"></td></tr>
        <tr class="receipt-subheading"><td colspan="4">Perbaikan</td></tr>
        @forelse ($repairs as $repair)
            <tr>
                <td class="description receipt-indent">{{ $repair['name'] }}</td>
                <td class="amount">Rp {{ number_format($repair['cost']) }}</td>
                <td class="date">{{ $repair['date'] ?? '-' }}</td>
                <td class="status">{{ ucfirst(str_replace('_', ' ', $repair['payment_status'] ?? 'lainnya')) }}@if (!empty($repair['payment_note']))<br>{{ $repair['payment_note'] }}@endif</td>
            </tr>
        @empty
            <tr><td class="description receipt-indent">Belum ada item perbaikan</td><td class="amount">Rp -</td><td class="date"></td><td class="status"></td></tr>
        @endforelse
        <tr class="receipt-rule receipt-total"><td class="description">Total Biaya Perbaikan</td><td class="amount">Rp {{ number_format($transaction->total_repair_cost) }}</td><td colspan="2"></td></tr>
    </table>
    <div class="receipt-grand-total"><span>Total Modal</span><span>Rp {{ number_format($transaction->total_capital) }}</span></div>
</div>

<div class="receipt-price-reference">
    <div class="receipt-section-title">Harga Setor & Harga Buka</div>
    <table class="receipt-table">
        <tr>
            <td class="description">Harga Setor</td>
            <td class="amount">Rp {{ number_format($transaction->setor_price) }}</td>
            <td class="receipt-reference-label">Selisih dari modal</td>
            <td class="amount">Rp {{ number_format($setorProfit) }}</td>
        </tr>
        <tr>
            <td class="description">Harga Buka</td>
            <td class="amount">Rp {{ number_format($transaction->opening_price) }}</td>
            <td class="receipt-reference-label">Selisih dari modal</td>
            <td class="amount">Rp {{ number_format($openingProfit) }}</td>
        </tr>
    </table>
</div>

<div class="receipt-dark-section">
    <div class="receipt-section-title">Harga Jual & Pembayaran</div>
    <table class="receipt-table">
        <tr><td class="description">Harga Jual</td><td class="amount">Rp {{ number_format($transaction->selling_price) }}</td><td class="date">{{ $transaction->sell_date ?? '-' }}</td><td class="status"></td></tr>
        <tr><td class="description">DP</td><td class="amount">Rp {{ number_format($transaction->down_payment) }}</td><td class="date">{{ $transaction->down_payment_date ?? '-' }}</td><td class="status"></td></tr>
        <tr><td class="description">Pelunasan</td><td class="amount">Rp {{ number_format($transaction->settlement_amount) }}</td><td class="date">{{ $transaction->settlement_date ?? '-' }}</td><td class="status"></td></tr>
        <tr><td class="description">Pencairan Leasing</td><td class="amount">Rp {{ number_format($transaction->leasing_disbursement) }}</td><td class="date">{{ $transaction->leasing_disbursement_date ?? '-' }}</td><td class="status"></td></tr>
        <tr class="receipt-rule receipt-total"><td class="description">Sisa Pembayaran</td><td class="amount">Rp {{ number_format($transaction->remaining_payment) }}</td><td colspan="2"></td></tr>
    </table>
</div>

<div class="receipt-profit receipt-profit-calculation">
    <div class="receipt-section-title">Kalkulasi Laba Penjualan dan Ripan</div>
    <table class="receipt-table">
        <tr><td class="description">Laba jual (Harga jual − Total modal)</td><td class="amount">Rp {{ number_format($transaction->calculatedSaleProfit()) }}</td><td colspan="2"></td></tr>
        <tr><td class="description">Ripan leasing</td><td class="amount">Rp {{ number_format($transaction->leasing_ripan) }}</td><td colspan="2"></td></tr>
        <tr><td class="description">Ripan asuransi</td><td class="amount">Rp {{ number_format($transaction->insurance_ripan) }}</td><td colspan="2"></td></tr>
        <tr><td class="description">Ripan booster</td><td class="amount">Rp {{ number_format($transaction->booster_ripan) }}</td><td colspan="2"></td></tr>
        <tr class="receipt-net"><td class="description">Total laba baru</td><td class="amount">Rp {{ number_format($transaction->calculatedTotalProfit()) }}</td><td colspan="2"></td></tr>
    </table>
</div>

<div class="receipt-profit receipt-profit-summary">
    <div class="receipt-section-title">Pengeluaran Intertine</div>
    <table class="receipt-table">
        @forelse ($intertineExpenses as $expense)
            <tr><td class="description receipt-indent">{{ $expense['name'] }}</td><td class="amount">Rp {{ number_format($expense['cost']) }}</td><td class="date">{{ $expense['date'] ?? '' }}</td><td class="status"></td></tr>
        @empty
            <tr><td class="description receipt-indent">Belum ada pengeluaran intertine</td><td class="amount">Rp -</td><td class="date"></td><td class="status"></td></tr>
        @endforelse
        <tr><td class="description">Fee Showroom</td><td class="amount">Rp {{ number_format($transaction->showroom_fee) }}</td><td colspan="2"></td></tr>
        <tr class="receipt-rule receipt-total"><td class="description">Total Pengeluaran Intertine</td><td class="amount">Rp {{ number_format($transaction->total_intertine_expense) }}</td><td colspan="2"></td></tr>
        <tr class="receipt-net"><td class="description">Laba Bersih</td><td class="amount">Rp {{ number_format($transaction->net_profit) }}</td><td colspan="2"></td></tr>
    </table>
</div>
