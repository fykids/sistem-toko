@extends('layout')

@section('content')
    @php
        $daysHeld = \Illuminate\Support\Carbon::parse($transaction->buy_date)
            ->diffInDays($transaction->sell_date ?? now());
        $taxItems = $transaction->tax_items ?? [];
        $repairs = $transaction->additional_repairs ?? [];
        $intertineExpenses = $transaction->intertine_expenses ?? [];
    @endphp

    <div class="page-heading receipt-page-heading">
        <div><p class="page-eyebrow">Dokumen transaksi</p><h1>Nota Kendaraan</h1><span class="cell-secondary">{{ $transaction->plate_number }} · {{ $transaction->brand }} {{ $transaction->model }}</span></div>
        <a href="{{ route('transactions.index') }}" class="btn btn-secondary receipt-back">← Kembali</a>
    </div>

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

        <div class="receipt-dark-section">
            <div class="receipt-section-title">Harga Jual & Pembayaran</div>
            <table class="receipt-table">
                <tr><td class="description">Harga Jual</td><td class="amount">Rp {{ number_format($transaction->selling_price) }}</td><td class="date">{{ $transaction->sell_date ?? '-' }}</td><td class="status"></td></tr>
                <tr><td class="description">DP</td><td class="amount">Rp {{ number_format($transaction->down_payment) }}</td><td class="date"></td><td class="status"></td></tr>
                <tr><td class="description">Pelunasan</td><td class="amount">Rp {{ number_format($transaction->settlement_amount) }}</td><td class="date"></td><td class="status"></td></tr>
                <tr><td class="description">Pencairan Leasing</td><td class="amount">Rp {{ number_format($transaction->leasing_disbursement) }}</td><td class="date"></td><td class="status"></td></tr>
                <tr class="receipt-rule receipt-total"><td class="description">Sisa Pembayaran</td><td class="amount">Rp {{ number_format($transaction->remaining_payment) }}</td><td colspan="2"></td></tr>
            </table>
        </div>

        <div class="receipt-profit">
            <table class="receipt-table">
                <tr><td class="description">Laba Lama (Harga Jual − Total Modal)</td><td class="amount">Rp {{ number_format($transaction->gross_profit) }}</td><td colspan="2"></td></tr>
                <tr class="receipt-subheading"><td colspan="4">Pengeluaran Intertine</td></tr>
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

        <div class="receipt-profit">
            <div class="receipt-section-title">Kalkulasi Laba Penjualan dan Ripan</div>
            <table class="receipt-table">
                <tr><td class="description">Harga jual</td><td class="amount">Rp {{ number_format($transaction->selling_price) }}</td><td colspan="2"></td></tr>
                <tr><td class="description">Total modal</td><td class="amount">Rp {{ number_format($transaction->total_capital) }}</td><td colspan="2"></td></tr>
                <tr><td class="description">Laba jual (Harga jual − Total modal)</td><td class="amount">Rp {{ number_format($transaction->calculatedSaleProfit()) }}</td><td colspan="2"></td></tr>
                <tr><td class="description">Ripan leasing</td><td class="amount">Rp {{ number_format($transaction->leasing_ripan) }}</td><td colspan="2"></td></tr>
                <tr><td class="description">Ripan asuransi</td><td class="amount">Rp {{ number_format($transaction->insurance_ripan) }}</td><td colspan="2"></td></tr>
                <tr><td class="description">Ripan booster</td><td class="amount">Rp {{ number_format($transaction->booster_ripan) }}</td><td colspan="2"></td></tr>
                <tr class="receipt-net"><td class="description">Total laba baru</td><td class="amount">Rp {{ number_format($transaction->calculatedTotalProfit()) }}</td><td colspan="2"></td></tr>
            </table>
        </div>
    </div>
@endsection