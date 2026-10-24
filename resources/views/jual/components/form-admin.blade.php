@include('jual.components.vehicle-card')
@include('jual.components.buyer-card')
@include('jual.components.payment-card')

<div class="jual-form-actions">
    <a href="{{ route('jual.index') }}" class="btn btn-secondary">Batal</a>
    <button type="submit" class="btn btn-accent">{{ $isEditing ? 'Simpan perubahan' : 'Simpan transaksi Jual' }}</button>
</div>
