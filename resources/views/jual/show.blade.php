@extends('layout')

@section('page_class', 'page-shell--jual')

@section('content')
@php($vehicle = $saleNote->carTransaction)
<div class="showroom-content jual-page">
    <section class="showroom-page-header jual-heading">
        <div>
            <p class="showroom-page-eyebrow">Detail Menu Jual</p>
            <h1>{{ $saleNote->nama_konsumen }}</h1>
            <p>{{ $vehicle->plate_number }} · {{ $vehicle->brand }} {{ $vehicle->model }} · {{ \Illuminate\Support\Carbon::parse($vehicle->sell_date)->format('d-m-Y') }}</p>
        </div>
        <div class="jual-page-actions">
            @if ((float) $vehicle->remaining_payment > 0)
                <a href="{{ route('jual.payments.create', $saleNote) }}" class="btn btn-accent">Catat pelunasan</a>
            @endif
            <a href="{{ route('jual.edit', $saleNote) }}" class="btn btn-accent">Edit</a>
            <form action="{{ route('jual.destroy', $saleNote) }}" method="POST" class="jual-delete-form">
                @csrf
                @method('DELETE')
                <button class="btn btn-secondary" type="submit">Hapus</button>
            </form>
            <a href="{{ route('jual.index') }}" class="btn btn-secondary">Kembali ke Jual</a>
        </div>
    </section>
    <section class="panel jual-detail-card" aria-label="Detail transaksi Jual">
        <dl class="jual-detail-list">
            <div><dt>Nama konsumen</dt><dd>{{ $saleNote->nama_konsumen }}</dd></div>
            <div><dt>NIK</dt><dd>{{ $saleNote->buyer_nik ?: '—' }}</dd></div>
            <div><dt>Alamat</dt><dd>{{ $saleNote->buyer_address ?: '—' }}</dd></div>
            <div><dt>No. HP</dt><dd>{{ $saleNote->buyer_phone ?: '—' }}</dd></div>
            <div><dt>Harga jual</dt><dd>Rp {{ number_format((float) $vehicle->selling_price) }}</dd></div>
            <div><dt>Tanggal jual</dt><dd>{{ \Illuminate\Support\Carbon::parse($vehicle->sell_date)->format('d-m-Y') }}</dd></div>
            <div><dt>DP</dt><dd>Rp {{ number_format((float) $vehicle->down_payment) }}</dd></div>
            <div><dt>Tanggal pembayaran DP</dt><dd>{{ $vehicle->down_payment_date ? \Illuminate\Support\Carbon::parse($vehicle->down_payment_date)->format('d-m-Y') : '—' }}</dd></div>
            <div><dt>Pelunasan diterima</dt><dd>Rp {{ number_format((float) $vehicle->settlement_amount) }}</dd></div>
            <div><dt>Tanggal pelunasan diterima</dt><dd>{{ $vehicle->settlement_date ? \Illuminate\Support\Carbon::parse($vehicle->settlement_date)->format('d-m-Y') : '—' }}</dd></div>
            <div><dt>Pencairan leasing</dt><dd>Rp {{ number_format((float) $vehicle->leasing_disbursement) }}</dd></div>
            <div><dt>Tanggal pencairan leasing</dt><dd>{{ $vehicle->leasing_disbursement_date ? \Illuminate\Support\Carbon::parse($vehicle->leasing_disbursement_date)->format('d-m-Y') : '—' }}</dd></div>
            <div><dt>Total dibayar</dt><dd>Rp {{ number_format($saleNote->totalPaidAmount()) }}</dd></div>
            <div><dt>Sisa pembayaran</dt><dd>Rp {{ number_format((float) $vehicle->remaining_payment) }}</dd></div>
            <div><dt>Rencana pembayaran</dt><dd>{{ $vehicle->planned_payment_date ? \Illuminate\Support\Carbon::parse($vehicle->planned_payment_date)->format('d-m-Y') : '—' }}</dd></div>
            @php($paymentStatus = $saleNote->paymentStatus())
            <div>
                <dt>Status pembayaran</dt>
                <dd>
                    <span @class([
                        'status-badge',
                        'pending' => $paymentStatus !== 'paid',
                    ])>
                        {{ ['paid' => 'Lunas', 'leasing' => 'Proses Leasing', 'unpaid' => 'Belum Lunas'][$paymentStatus] }}
                    </span>
                </dd>
            </div>
            @can('viewSensitiveData', \App\Models\CarTransaction::class)
                @php($dealProfit = (float) $vehicle->selling_price - (float) $vehicle->total_capital)
                @php($totalDealProfit = $dealProfit + (float) $vehicle->leasing_ripan + (float) $vehicle->insurance_ripan + (float) $vehicle->booster_ripan)
                <div><dt>Modal beli</dt><dd>Rp {{ number_format((float) $vehicle->buy_price) }}</dd></div>
                <div><dt>Total modal</dt><dd>Rp {{ number_format((float) $vehicle->total_capital) }}</dd></div>
                <div><dt>Laba deal</dt><dd>Rp {{ number_format($totalDealProfit) }}</dd></div>
            @endcan
        </dl>
    </section>
    <section class="panel jual-detail-card" aria-labelledby="jual-payments-title">
        <h2 id="jual-payments-title">Riwayat pelunasan</h2>
        @if ($saleNote->payments->isNotEmpty())
            <div class="table-scroll">
                <table class="data-table jual-table">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Metode</th>
                            <th class="numeric">Nominal</th>
                            <th>Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($saleNote->payments as $payment)
                            <tr>
                                <td>{{ $payment->payment_date->format('d-m-Y') }}</td>
                                <td>{{ ['cash' => 'Pelunasan Tunai', 'leasing' => 'Pencairan Leasing', 'bank_transfer' => 'Transfer Bank'][$payment->payment_method] }}</td>
                                <td class="numeric">Rp {{ number_format((float) $payment->amount) }}</td>
                                <td>{{ $payment->notes ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p>Belum ada pembayaran pelunasan tambahan yang dicatat.</p>
        @endif
    </section>
</div>
@endsection
