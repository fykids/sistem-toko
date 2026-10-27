@extends('layout')

@section('page_class', 'page-shell--jual')

@section('content')
@php($vehicle = $saleNote->carTransaction)
<div class="showroom-content jual-page">
    <section class="showroom-page-header jual-heading">
        <div>
            <p class="showroom-page-eyebrow">Penjualan</p>
            <h1>Catat Pelunasan</h1>
            <p>{{ $vehicle->plate_number }} · {{ $vehicle->brand }} {{ $vehicle->model }}</p>
        </div>
        <a href="{{ route('jual.show', $saleNote) }}" class="btn btn-secondary">Batal / Kembali</a>
    </section>

    <section class="panel jual-detail-card" aria-labelledby="payment-summary-title">
        <h2 id="payment-summary-title">Ringkasan transaksi</h2>
        <dl class="jual-detail-list">
            <div><dt>Kendaraan</dt><dd>{{ $vehicle->plate_number }} · {{ $vehicle->brand }} {{ $vehicle->model }}</dd></div>
            <div><dt>Nama pembeli</dt><dd>{{ $saleNote->nama_konsumen }}</dd></div>
            <div><dt>Tanggal transaksi</dt><dd>{{ \Illuminate\Support\Carbon::parse($vehicle->sell_date)->format('d-m-Y') }}</dd></div>
            <div><dt>Harga jual deal</dt><dd>Rp {{ number_format((float) $vehicle->selling_price) }}</dd></div>
            <div><dt>Total sudah dibayar</dt><dd>Rp {{ number_format($saleNote->totalPaidAmount()) }}</dd></div>
            <div>
                <dt>Sisa pembayaran</dt>
                <dd><strong>Rp {{ number_format((float) $vehicle->remaining_payment) }}</strong></dd>
            </div>
        </dl>
    </section>

    <section class="panel jual-detail-card" aria-labelledby="record-payment-title">
        <h2 id="record-payment-title">Data pelunasan</h2>
        <form action="{{ route('jual.payments.store', $saleNote) }}" method="POST">
            @csrf
            <div class="field-grid two-column">
                <div class="form-group">
                    <label for="payment-amount">Nominal pelunasan (Rp)</label>
                    <input id="payment-amount" type="number" name="amount" min="0.01" step="0.01"
                        value="{{ old('amount') }}" inputmode="decimal" required>
                    @error('amount')<span class="form-help">{{ $message }}</span>@enderror
                </div>
                <div class="form-group">
                    <label for="payment-method">Metode / jenis pelunasan</label>
                    <select id="payment-method" name="payment_method" required>
                        <option value="">Pilih metode pelunasan</option>
                        <option value="cash" @selected(old('payment_method') === 'cash')>Pelunasan Tunai</option>
                        <option value="leasing" @selected(old('payment_method') === 'leasing')>Pencairan Leasing</option>
                        <option value="bank_transfer" @selected(old('payment_method') === 'bank_transfer')>Transfer Bank</option>
                    </select>
                    @error('payment_method')<span class="form-help">{{ $message }}</span>@enderror
                </div>
                <div class="form-group">
                    <label for="payment-date">Tanggal pelunasan</label>
                    <input id="payment-date" type="date" name="payment_date"
                        value="{{ old('payment_date', now()->toDateString()) }}" required>
                    @error('payment_date')<span class="form-help">{{ $message }}</span>@enderror
                </div>
                <div class="form-group">
                    <label for="payment-notes">Catatan / keterangan</label>
                    <textarea id="payment-notes" name="notes" rows="3" maxlength="1000"
                        placeholder="Nomor referensi transfer atau catatan leasing">{{ old('notes') }}</textarea>
                    @error('notes')<span class="form-help">{{ $message }}</span>@enderror
                </div>
            </div>
            <div class="jual-page-actions">
                <a href="{{ route('jual.show', $saleNote) }}" class="btn btn-secondary">Batal / Kembali</a>
                <button type="submit" class="btn btn-accent">Simpan Pelunasan</button>
            </div>
        </form>
    </section>
</div>
@endsection
