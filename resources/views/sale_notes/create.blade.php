@extends('layout')

@section('page_class', 'page-shell--transaction-form page-shell--transaction-form-entry page-shell--sale-note-form')

@section('content')
@php($isEditing = $saleNote !== null)
<section class="sale-note-form-heading" aria-labelledby="sale-note-form-title">
    <div>
        <p class="page-eyebrow">Nota penjualan · Modul terpisah</p>
        <h1 id="sale-note-form-title">{{ $isEditing ? 'Edit Nota Penjualan' : 'Buat Nota Penjualan' }}</h1>
        <p>Data nota ini disimpan terpisah dan tidak mengubah data Penjualan yang sudah ada.</p>
    </div>
    <a href="{{ route('nota-penjualan.index') }}" class="btn btn-secondary">Kembali ke daftar</a>
</section>

<form action="{{ $isEditing ? route('nota-penjualan.update', $saleNote) : route('nota-penjualan.store') }}"
    method="POST" @class([
        'transaction-form',
        'transaction-form--sale-note',
        'transaction-form--master' => $canViewSensitiveData,
        'transaction-form--admin' => ! $canViewSensitiveData,
    ])>
    @csrf
    @if ($isEditing)
        @method('PUT')
    @endif

    <section class="sale-note-card sale-note-card--vehicle" aria-labelledby="sale-note-vehicle-title">
        <header class="sale-note-card-heading">
            <span class="sale-note-section-number">01</span>
            <div>
                <h2 id="sale-note-vehicle-title">Data kendaraan</h2>
                <p>Ringkasan kendaraan terkait nota ini.</p>
            </div>
        </header>
        @if ($isEditing)
            <div class="sale-note-vehicle-summary">
                <strong>{{ $selectedVehicle->plate_number }}</strong>
                <span>{{ $selectedVehicle->brand }} {{ $selectedVehicle->model }} · {{ $selectedVehicle->year }}</span>
                <span>{{ $selectedVehicle->transmission }} · {{ $selectedVehicle->color }}</span>
            </div>
        @else
            <label class="sale-note-field">
                <span>Kendaraan</span>
                <select name="car_transaction_id" required>
                    <option value="">Pilih kendaraan</option>
                    @foreach ($availableVehicles as $vehicle)
                        <option value="{{ $vehicle->id }}" @selected(old('car_transaction_id', $selectedVehicle?->id) == $vehicle->id)>
                            {{ $vehicle->plate_number }} · {{ $vehicle->brand }} {{ $vehicle->model }} ({{ $vehicle->year }})
                        </option>
                    @endforeach
                </select>
            </label>
            @error('car_transaction_id')
                <span class="sale-note-error">{{ $message }}</span>
            @enderror
        @endif
        @if ($canViewSensitiveData && $selectedVehicle)
            <div class="sale-note-vehicle-summary sale-note-vehicle-summary--financial">
                <span>Modal beli <strong>Rp {{ number_format((float) $selectedVehicle->buy_price) }}</strong></span>
                <span>Total modal <strong>Rp {{ number_format((float) $selectedVehicle->total_capital) }}</strong></span>
            </div>
        @endif
    </section>

    <section class="sale-note-card sale-note-card--buyer" aria-labelledby="sale-note-buyer-title">
        <header class="sale-note-card-heading">
            <span class="sale-note-section-number">02</span>
            <div>
                <h2 id="sale-note-buyer-title">Data pembeli</h2>
                <p>Identitas pembeli pada nota.</p>
            </div>
        </header>
        <label class="sale-note-field">
            <span>Nama pembeli</span>
            <input type="text" name="buyer_name" value="{{ old('buyer_name', $saleNote?->buyer_name) }}" required maxlength="255">
            @error('buyer_name')<span class="sale-note-error">{{ $message }}</span>@enderror
        </label>
        <label class="sale-note-field">
            <span>NIK</span>
            <input type="text" name="buyer_nik" value="{{ old('buyer_nik', $saleNote?->buyer_nik) }}" maxlength="16">
            @error('buyer_nik')<span class="sale-note-error">{{ $message }}</span>@enderror
        </label>
        <label class="sale-note-field">
            <span>Nomor telepon</span>
            <input type="text" name="buyer_phone" value="{{ old('buyer_phone', $saleNote?->buyer_phone) }}" maxlength="30">
            @error('buyer_phone')<span class="sale-note-error">{{ $message }}</span>@enderror
        </label>
        <label class="sale-note-field">
            <span>Alamat</span>
            <textarea name="buyer_address" rows="3" maxlength="5000">{{ old('buyer_address', $saleNote?->buyer_address) }}</textarea>
            @error('buyer_address')<span class="sale-note-error">{{ $message }}</span>@enderror
        </label>
    </section>

    <section class="sale-note-card sale-note-card--payment" aria-labelledby="sale-note-payment-title">
        <header class="sale-note-card-heading">
            <span class="sale-note-section-number">03</span>
            <div>
                <h2 id="sale-note-payment-title">Pembayaran penjualan</h2>
                <p>Nilai dan tanggal sesuai nota.</p>
            </div>
        </header>
        <label class="sale-note-field">
            <span>Harga deal (Rp)</span>
            <input type="number" name="deal_price" value="{{ old('deal_price', $saleNote?->deal_price) }}" min="0" step="0.01" required>
            @error('deal_price')<span class="sale-note-error">{{ $message }}</span>@enderror
        </label>
        <label class="sale-note-field">
            <span>Metode pembayaran</span>
            <select name="payment_method" required>
                @foreach (['Tunai', 'Transfer', 'Kredit', 'Lainnya'] as $method)
                    <option value="{{ $method }}" @selected(old('payment_method', $saleNote?->payment_method) === $method)>{{ $method }}</option>
                @endforeach
            </select>
            @error('payment_method')<span class="sale-note-error">{{ $message }}</span>@enderror
        </label>
        <label class="sale-note-field">
            <span>Tanggal jual</span>
            <input type="date" name="sell_date" value="{{ old('sell_date', $saleNote?->sell_date?->format('Y-m-d')) }}" required>
            @error('sell_date')<span class="sale-note-error">{{ $message }}</span>@enderror
        </label>
        <label class="sale-note-field">
            <span>Catatan</span>
            <textarea name="notes" rows="3" maxlength="5000">{{ old('notes', $saleNote?->notes) }}</textarea>
            @error('notes')<span class="sale-note-error">{{ $message }}</span>@enderror
        </label>
    </section>

    @if ($canViewSensitiveData)
        <section class="sale-note-card sale-note-card--margin" aria-labelledby="sale-note-margin-title">
            <header class="sale-note-card-heading">
                <span class="sale-note-section-number">04</span>
                <div>
                    <h2 id="sale-note-margin-title">Ringkasan margin</h2>
                    <p>Nilai dihitung dari harga deal nota.</p>
                </div>
            </header>
            @if ($selectedVehicle)
                <dl class="sale-note-margin-list">
                    <div><dt>Modal beli</dt><dd>Rp {{ number_format((float) $selectedVehicle->buy_price) }}</dd></div>
                    <div><dt>Total modal</dt><dd>Rp {{ number_format((float) $selectedVehicle->total_capital) }}</dd></div>
                    <div><dt>Harga deal</dt><dd>Rp {{ number_format((float) old('deal_price', $saleNote?->deal_price ?? 0)) }}</dd></div>
                    <div><dt>Estimasi laba nota</dt><dd>Rp {{ number_format((float) old('deal_price', $saleNote?->deal_price ?? 0) - (float) $selectedVehicle->total_capital) }}</dd></div>
                </dl>
            @else
                <p class="sale-note-margin-empty">Pilih kendaraan untuk melihat ringkasan margin.</p>
            @endif
        </section>
    @endif

    <div class="sale-note-form-actions">
        <a href="{{ route('nota-penjualan.index') }}" class="btn btn-secondary">Batal</a>
        <button type="submit" class="btn btn-accent">{{ $isEditing ? 'Simpan perubahan' : 'Simpan nota' }}</button>
    </div>
</form>
@endsection
