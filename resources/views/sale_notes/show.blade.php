@extends('layout')

@section('page_class', 'page-shell--sale-note')

@section('content')
@php($vehicle = $saleNote->carTransaction)
<div class="showroom-content sale-note-page">
    <section class="showroom-page-header sale-note-heading">
        <div>
            <p class="showroom-page-eyebrow">Detail nota penjualan</p>
            <h1>{{ $saleNote->buyer_name }}</h1>
            <p>{{ $vehicle->plate_number }} · {{ $vehicle->brand }} {{ $vehicle->model }} · {{ $saleNote->sell_date->format('d-m-Y') }}</p>
        </div>
        <div class="sale-note-page-actions">
            <a href="{{ route('nota-penjualan.edit', $saleNote) }}" class="btn btn-accent">Edit nota</a>
            <a href="{{ route('nota-penjualan.index') }}" class="btn btn-secondary">Kembali</a>
        </div>
    </section>
    <section class="panel sale-note-detail-card" aria-label="Detail nota">
        <dl class="sale-note-detail-list">
            <div><dt>Nama pembeli</dt><dd>{{ $saleNote->buyer_name }}</dd></div>
            <div><dt>NIK</dt><dd>{{ $saleNote->buyer_nik ?: '—' }}</dd></div>
            <div><dt>Nomor telepon</dt><dd>{{ $saleNote->buyer_phone ?: '—' }}</dd></div>
            <div><dt>Alamat</dt><dd>{{ $saleNote->buyer_address ?: '—' }}</dd></div>
            <div><dt>Harga deal</dt><dd>Rp {{ number_format((float) $saleNote->deal_price) }}</dd></div>
            <div><dt>Metode pembayaran</dt><dd>{{ $saleNote->payment_method }}</dd></div>
            <div><dt>Tanggal jual</dt><dd>{{ $saleNote->sell_date->format('d-m-Y') }}</dd></div>
            <div><dt>Catatan</dt><dd>{{ $saleNote->notes ?: '—' }}</dd></div>
            @can('viewSensitiveData', \App\Models\CarTransaction::class)
                @php($dealProfit = (float) $saleNote->deal_price - (float) $vehicle->total_capital)
                @php($totalDealProfit = $dealProfit + (float) $vehicle->leasing_ripan + (float) $vehicle->insurance_ripan + (float) $vehicle->booster_ripan)
                <div><dt>Modal beli</dt><dd>Rp {{ number_format((float) $vehicle->buy_price) }}</dd></div>
                <div><dt>Total modal</dt><dd>Rp {{ number_format((float) $vehicle->total_capital) }}</dd></div>
                <div><dt>Laba jual</dt><dd>Rp {{ number_format($dealProfit) }}</dd></div>
                <div><dt>Laba stor</dt><dd>Rp {{ number_format((float) $vehicle->setor_price - (float) $vehicle->total_capital) }}</dd></div>
                <div><dt>Laba deal</dt><dd>Rp {{ number_format($totalDealProfit) }}</dd></div>
            @endcan
        </dl>
    </section>
</div>
@endsection
