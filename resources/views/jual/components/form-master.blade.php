@include('jual.components.vehicle-card')
@include('jual.components.buyer-card')
@include('jual.components.payment-card')

<section class="jual-card jual-card--master" aria-labelledby="jual-master-title">
    <header class="jual-card-heading">
        <span class="jual-section-number">04</span>
        <div>
            <h2 id="jual-master-title">Ringkasan finansial Master</h2>
            <p>Rincian modal dan estimasi hasil.</p>
        </div>
    </header>
    @if ($selectedVehicle)
        <dl class="jual-financial-summary">
            <div><dt>Modal beli</dt><dd>Rp {{ number_format((float) $selectedVehicle->buy_price) }}</dd></div>
            <div><dt>Total modal</dt><dd>Rp {{ number_format((float) $selectedVehicle->total_capital) }}</dd></div>
            <div><dt>Estimasi laba</dt><dd>Rp {{ number_format((float) old('deal_price', $saleNote->deal_price) - (float) $selectedVehicle->total_capital) }}</dd></div>
        </dl>
    @else
        <p class="jual-empty-hint">Pilih kendaraan untuk menampilkan ringkasan finansial.</p>
    @endif
</section>

<div class="jual-form-actions">
    <a href="{{ route('jual.index') }}" class="btn btn-secondary">Batal</a>
    <button type="submit" class="btn btn-accent">{{ $isEditing ? 'Simpan perubahan' : 'Simpan transaksi Jual' }}</button>
</div>
