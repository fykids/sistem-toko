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
        <a href="{{ route('transactions.index') }}" class="btn btn-secondary receipt-back">← Kembali</a>
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
            <a href="{{ route('transactions.index') }}" class="btn btn-secondary receipt-back">← Kembali</a>
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